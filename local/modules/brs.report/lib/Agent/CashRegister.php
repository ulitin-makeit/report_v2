<?php

	namespace Brs\Report\Agent;

	use Bitrix\Main\Application;
	use Bitrix\Main\Config\Option;

	use Brs\Report\Model\Orm\CashRegisterTable; // ОРМ таблицы отчёта
	use Brs\ReceiptOfd\Models\ReceiptTable; // ОРМ таблицы отчёта
	use Brs\Exchange1C\Models\AccountingEntryTable; // проводки

	ini_set('memory_limit', '5000M');

	/**
	 * Агент отчёта, перезаписывает данные в таблицу по нему (чтобы можно было фильтровать и список использовать на странице отчёта).
	 * 
	 * Оптимизированная версия с предзагрузкой данных:
	 * - Сделки загружаются одним запросом
	 * - Контакты загружаются одним запросом
	 * - Даты оказания услуги загружаются напрямую из UF_DATE_SERVICE_PROVISION
	 * - Проводки загружаются одним запросом
	 */
	class CashRegister {

		static array $nds = array(
			'VAT_10' => 'CalculatedVat10110', // налог на добавленную стоимость (НДС) 10%;
			'VAT_20' => 'CalculatedVat20120', // НДС 20%
			'VAT_0' => 0, // НДС 0%;
			'VAT_NO' => 0, // НДС не облагается;
			'VAT_10_110' => 'CalculatedVat10110', // вычисленный НДС 10% от 110% суммы;
			'VAT_18_118' => 'CalculatedVat18118', // вычисленный НДС 18% от 118% суммы;
			'VAT_20_120' => 'CalculatedVat20120'
		);

		static array $headerCodes; // содержит массив соответствий

		/** @var array Предзагруженные данные сделок [deal_id => ['CATEGORY_ID' => ..., 'CONTACT_ID' => ...]] */
		private static array $dealsData = [];

		/** @var array Предзагруженные данные контактов [contact_id => 'ФИО'] */
		private static array $contactsData = [];

		/** @var array Предзагруженные даты оказания услуги [deal_id => 'date'] */
		private static array $serviceDates = [];

		/** @var array Предзагруженные проводки [uid => true] */
		private static array $accountingEntries = [];

		/*
		 * Метод инициализирует перезапись отчёта в таблице.
		 * 
		 * @return string
		 */
		static function init() : string {
			
			// подключаем модули
			\CModule::IncludeModule('crm');
			\CModule::IncludeModule('brs.receiptofd');
			\CModule::IncludeModule('brs.exchange1c');
			\CModule::IncludeModule('brs.report');
			\CModule::IncludeModule('brs.financialcard');
			\CModule::IncludeModule('brs.incomingpaymentecomm');

			// предзагружаем все данные
			self::preloadAllData();

			// генерируем сам отчёт
			$document = self::generateDocumentReport();

			// заполняем таблицу
			self::fillReportTable($document);

			// очищаем предзагруженные данные
			self::clearPreloadedData();

			Option::set('brs.report', 'BRS_REPORT_CASH_REGISTER_DATE_REFRESH', (new \DateTime())->format('d.m.Y H:i:s'), SITE_ID); // сохраняем дату последнего обновления отчёта

			return '\\Brs\\Report\\Agent\\CashRegister::init();';

		}

		/**
		 * Предзагружает все необходимые данные одним набором запросов
		 * 
		 * @return void
		 */
		private static function preloadAllData(): void
		{
			self::preloadDeals();
			self::preloadContacts();
			self::preloadServiceDates();
			self::preloadAccountingEntries();
		}

		/**
		 * Очищает предзагруженные данные для освобождения памяти
		 * 
		 * @return void
		 */
		private static function clearPreloadedData(): void
		{
			self::$dealsData = [];
			self::$contactsData = [];
			self::$serviceDates = [];
			self::$accountingEntries = [];
		}

		/**
		 * Предзагружает данные сделок (CATEGORY_ID, CONTACT_ID) одним запросом
		 * 
		 * @return void
		 */
		private static function preloadDeals(): void
		{
			$connection = Application::getConnection();
			$sql = "SELECT ID, CATEGORY_ID, CONTACT_ID FROM b_crm_deal";
			
			$result = $connection->query($sql);
			
			while ($row = $result->fetch()) {
				self::$dealsData[(int)$row['ID']] = [
					'CATEGORY_ID' => $row['CATEGORY_ID'],
					'CONTACT_ID' => $row['CONTACT_ID']
				];
			}
		}

		/**
		 * Предзагружает данные контактов (ФИО) одним запросом
		 * По аналогии с ClientDataProvider
		 * 
		 * @return void
		 */
		private static function preloadContacts(): void
		{
			$connection = Application::getConnection();
			$sql = "
				SELECT 
					ID, 
					CONCAT(LAST_NAME, ' ', NAME, ' ', SECOND_NAME) as FULL_NAME
				FROM b_crm_contact
			";
			
			$result = $connection->query($sql);
			
			while ($row = $result->fetch()) {
				// Убираем лишние пробелы из ФИО
				$fullName = preg_replace('/\s+/', ' ', trim($row['FULL_NAME']));
				self::$contactsData[(int)$row['ID']] = $fullName;
			}
		}

		/**
		 * Предзагружает даты оказания услуги напрямую из UF_DATE_SERVICE_PROVISION
		 * По аналогии с ServiceDateDataProvider
		 * 
		 * @return void
		 */
		private static function preloadServiceDates(): void
		{
			$connection = Application::getConnection();
			$sql = "SELECT VALUE_ID as DEAL_ID, UF_DATE_SERVICE_PROVISION as FIELD_VALUE FROM b_uts_crm_deal";
			
			$result = $connection->query($sql);
			
			while ($row = $result->fetch()) {
				$dealId = (int)$row['DEAL_ID'];
				$value = $row['FIELD_VALUE'];
				
				if ($value !== null && $value !== '') {
					self::$serviceDates[$dealId] = self::formatServiceDate($value);
				} else {
					self::$serviceDates[$dealId] = '';
				}
			}
		}

		/**
		 * Форматирует дату оказания услуги
		 * Обрабатывает как обычные значения, так и сериализованные массивы
		 * 
		 * @param string $value Значение даты из БД
		 * @return string Форматированная дата (Y-m-d)
		 */
		private static function formatServiceDate(string $value): string
		{
			// Проверяем сериализованные данные
			if (self::isSerialized($value)) {
				$unserialized = @unserialize($value);
				
				if (is_array($unserialized)) {
					$dates = [];
					foreach ($unserialized as $dateValue) {
						if ($dateValue !== null && $dateValue !== '') {
							$formatted = self::parseDateToYmd((string)$dateValue);
							if ($formatted !== '') {
								$dates[] = $formatted;
							}
						}
					}
					
					// Удаляем дубликаты и сортируем
					$dates = array_unique($dates);
					sort($dates);
					
					return implode(', ', $dates);
				}
			}
			
			return self::parseDateToYmd($value);
		}

		/**
		 * Парсит дату в формат Y-m-d
		 * 
		 * @param string $value Исходное значение даты
		 * @return string Дата в формате Y-m-d
		 */
		private static function parseDateToYmd(string $value): string
		{
			$timestamp = strtotime($value);
			
			if ($timestamp === false) {
				return '';
			}
			
			return date('Y-m-d', $timestamp);
		}

		/**
		 * Проверяет является ли строка сериализованными данными
		 * По аналогии с DateFieldHelper
		 * 
		 * @param string $value Проверяемое значение
		 * @return bool
		 */
		private static function isSerialized(string $value): bool
		{
			if ($value === 'b:0;' || $value === 'b:1;' || $value === 'N;') {
				return true;
			}
			
			if (preg_match('/^(a|O|s):\d+:/', $value)) {
				return true;
			}
			
			return false;
		}

		/**
		 * Предзагружает данные проводок одним запросом
		 * 
		 * @return void
		 */
		private static function preloadAccountingEntries(): void
		{
			$accountingListDb = AccountingEntryTable::getList([
				'select' => ['UID'],
				'filter' => [
					'!UID' => '',
					'STATUS' => 'SUCCESS'
				]
			]);
			
			foreach ($accountingListDb as $accounting) {
				self::$accountingEntries[$accounting['UID']] = true;
			}
		}

		/**
		 * Получает данные сделки из предзагруженных данных
		 * 
		 * @param int $dealId ID сделки
		 * @return array|null Данные сделки или null если не найдена
		 */
		private static function getDealData(int $dealId): ?array
		{
			return self::$dealsData[$dealId] ?? null;
		}

		/**
		 * Получает ФИО контакта из предзагруженных данных
		 * 
		 * @param int|null $contactId ID контакта
		 * @return string ФИО контакта или пустая строка
		 */
		private static function getContactName(?int $contactId): string
		{
			if ($contactId === null) {
				return '';
			}
			
			return self::$contactsData[$contactId] ?? '';
		}

		/**
		 * Получает дату оказания услуги из предзагруженных данных
		 * 
		 * @param int $dealId ID сделки
		 * @return string Дата или пустая строка
		 */
		private static function getServiceDate(int $dealId): string
		{
			return self::$serviceDates[$dealId] ?? '';
		}

		/**
		 * Проверяет наличие проводки по UID
		 * 
		 * @param string $uid UID проводки
		 * @return bool
		 */
		private static function hasAccountingEntry(string $uid): bool
		{
			return isset(self::$accountingEntries[$uid]);
		}

		/**
		 * Метод заполняет таблицу отчётов.
		 * 
		 * @param array $document
		 */
		private static function fillReportTable(array $document): void
		{
			global $DB;

			// шапка документа
			$header = array();

			foreach(CashRegisterTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header); // переворачиваем массив и ищем по ключам

			// очищаем таблицу
			Application::getConnection()->truncateTable(CashRegisterTable::getTableName());

			if (empty($document['body'])) {
				return;
			}

			// формируем единый SQL запрос на вставку в таблицу
			$sqlInsert = 'INSERT INTO `brs_report_cash_register` (`DEAL_ID`, `TRANSACTION_DATE`, `DATE_SERVICE_PROVISION`, `TRANSACTION_AMOUNT_RUB`, `RECEIPT_TYPE`, `PAYMENT_METHOD`, `PAYERS_FULL_NAME`, `UNLOADING_OFD`, `UNLOADING_1C`) VALUES '."\r\n";
			$sqlInsertValues = [];

			foreach($document['body'] as $row){
				$sqlInsertValues[] = '(\''.$DB->ForSql($row[$headerKeys['Номер сделки']]).'\', \''.$DB->ForSql($row[$headerKeys['Дата транзакции']]).'\', \''.$DB->ForSql($row[$headerKeys['Дата оказания услуги']]).'\', \''.$DB->ForSql($row[$headerKeys['Сумма транзакции, руб.']]).'\', \''.$DB->ForSql($row[$headerKeys['Тип чека']]).'\', \''.$DB->ForSql($row[$headerKeys['Способ оплаты']]).'\', \''.$DB->ForSql($row[$headerKeys['Клиент']]).'\', \''.$DB->ForSql($row[$headerKeys['Выгрузка ОФД']]).'\', \''.$DB->ForSql($row[$headerKeys['Выгрузка 1С']]).'\')';
			}

			$sqlInsert = $sqlInsert.implode(','."\r\n", $sqlInsertValues).';';

			$DB->query($sqlInsert);
		}

		/**
		 * Метод формирует заголовок и тело документа (отчёта).
		 * 
		 * @return array header, body
		 */
		private static function generateDocumentReport(): array
		{
			// шапка документа
			$header = array();

			foreach(CashRegisterTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$receiptType = [
				'Income' => ReceiptTable::PAYMENT_TYPE_LANG,
				'IncomeReturn' => 'Возврат денежных средств, полученных от покупателя',
				'IncomePrepayment' => ReceiptTable::PAYMENT_TYPE_LANG,
				'IncomeReturnPrepayment' => 'Возврат аванса',
				'IncomeCorrection' => 'Чек коррекции/приход',
				'BuyCorrection' => 'Чек коррекции/расход',
				'IncomeReturnCorrection' => 'Чек коррекции/Возврат прихода',
				'ExpenseReturnCorrection' => 'Чек коррекции/Возврат расхода',
				'Expense' => 'Выдача денежных средств покупателю',
				'ExpenseReturn' => 'Возврат денежных средств, выданных покупателю'
			];

			$paymentMethods = [
				'ACQUIRING' => 'Эквайринг',
				'SERVICE' => 'Услуга',
			];

			$headerKeys = array_flip($header);

			// тело документа
			$bodyRows = array();

			// получаем чеки через ORM
			$receipt = ReceiptTable::getList([
				'select' => [
					'DEAL_ID', 'REQUEST_RECEIPT_JSON', 'UID', 'RECEIPT_TYPE', 'PAYMENT_TYPE', 'DATE_CREATE', 'RECEIPT_URL'
				],
				'order' => [
					'ID' => 'DESC'
				]
			]);

			if($receipt->getSelectedRowsCount() == 0){
				return [
					'header' => $header,
					'body' => $bodyRows,
				];
			}

			$receiptCollection = $receipt->fetchAll();

			// обходим массив чеков и формируем тело документа
			foreach($receiptCollection as $receiptItem){

				$dealId = (int)$receiptItem['DEAL_ID'];
				
				// получаем данные сделки из предзагруженных данных
				$dealData = self::getDealData($dealId);
				
				if ($dealData === null) {
					continue;
				}

				// исключаем категорию Elite Tiers Registration (ID = 21)
				if ($dealData['CATEGORY_ID'] === '21') {
					continue;
				}

				$request = json_decode($receiptItem['REQUEST_RECEIPT_JSON'], true);

				// вычисляем сумму транзакции
				$sumTransaction = 0;
				$paymentItems = $request['Request']['CustomerReceipt']['PaymentItems'] ?? [];

				foreach($paymentItems as $paymentItem){
					$sumTransaction += $paymentItem['Sum'];
				}

				$sumTransaction = number_format((float)$sumTransaction, 2, ',', '');

				// получаем ФИО клиента из предзагруженных данных
				$contactId = $dealData['CONTACT_ID'] ? (int)$dealData['CONTACT_ID'] : null;
				$contactName = self::getContactName($contactId);
				$client = $contactId 
					? '<a href="/crm/contact/details/'. $contactId .'/">' . $contactName . '</a>'
					: '';

				// проверяем наличие проводки
				$is1C = self::hasAccountingEntry($receiptItem['UID']) ? 'Да' : 'Нет';

				// определяем тип чека
				$receiptTypeName = '';
				if (isset($receiptType[$receiptItem['RECEIPT_TYPE']])) {
					if (is_array($receiptType[$receiptItem['RECEIPT_TYPE']])) {
						$receiptTypeName = $receiptType[$receiptItem['RECEIPT_TYPE']][$receiptItem['PAYMENT_TYPE']] ?? '';
					} else {
						$receiptTypeName = $receiptType[$receiptItem['RECEIPT_TYPE']];
					}
				}

				// определяем способ оплаты
				$paymentMethod = $paymentMethods['ACQUIRING']; // по умолчанию эквайринг
				$costumerPaymentType = $request['Request']['CustomerReceipt']['PaymentType'] ?? null;

				if ($costumerPaymentType == 4) {
					$paymentMethod = $paymentMethods['SERVICE'];
				}

				// получаем дату оказания услуги из предзагруженных данных
				$dateServiceProvision = self::getServiceDate($dealId);

				// формируем строку документа
				$bodyRow = [
					$headerKeys['Номер сделки'] => $receiptItem['DEAL_ID'],
					$headerKeys['Дата транзакции'] => $receiptItem['DATE_CREATE'] ? $receiptItem['DATE_CREATE']->format('Y-m-d') : '',
					$headerKeys['Дата оказания услуги'] => $dateServiceProvision,
					$headerKeys['Сумма транзакции, руб.'] => $sumTransaction,
					$headerKeys['Тип чека'] => $receiptTypeName,
					$headerKeys['Способ оплаты'] => $paymentMethod,
					$headerKeys['Клиент'] => $client,
					$headerKeys['Выгрузка ОФД'] => !empty($receiptItem['RECEIPT_URL']) ? 'Да' : 'Нет',
					$headerKeys['Выгрузка 1С'] => $is1C,
				];
				
				$bodyRows[] = $bodyRow;

			}

			return array(
				'header' => $header,
				'body' => $bodyRows,
			);

		}

	}