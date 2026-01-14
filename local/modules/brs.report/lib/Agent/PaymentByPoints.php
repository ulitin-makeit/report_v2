<?php

namespace Brs\Report\Agent;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

use Brs\Report\Model\Orm\PaymentByPointsTable; // ОРМ таблицы отчёта
use Brs\IncomingPaymentEcomm\Models\PaymentTransactionTable; // транзакции оплаты
use Brs\Currency\Models\CurrencyPointTable; // типы программ лояльности

ini_set('memory_limit', '5000M');

/**
 * Агент отчёта "Оплата баллами"
 *
 * Генерирует отчёт по успешным операциям оплаты баллами программы лояльности.
 * Использует предзагрузку данных для оптимизации производительности.
 */
class PaymentByPoints {

	/** @var array Предзагруженные данные сделок */
	private static array $dealsData = [];

	/** @var array Предзагруженные названия категорий сделок */
	private static array $dealCategories = [];

	/** @var array Предзагруженные данные контактов */
	private static array $contactsData = [];

	/** @var array Предзагруженные даты оказания услуги */
	private static array $serviceDates = [];

	/** @var array Предзагруженные данные лидов */
	private static array $leadsData = [];

	/**
	 * Метод инициализирует перезапись отчёта в таблице.
	 *
	 * @return string
	 */
	static function init(): string {

		// подключаем модули
		\CModule::IncludeModule('crm');
		\CModule::IncludeModule('brs.incomingpaymentecomm');
		\CModule::IncludeModule('brs.currency');
		\CModule::IncludeModule('brs.report');

		// предзагружаем все данные
		self::preloadAllData();

		// генерируем сам отчёт
		$document = self::generateDocumentReport();

		// заполняем таблицу
		self::fillReportTable($document);

		// очищаем предзагруженные данные
		self::clearPreloadedData();

		// сохраняем дату последнего обновления отчёта
		Option::set('brs.report', 'BRS_REPORT_PAYMENT_BY_POINTS_DATE_REFRESH', (new \DateTime())->format('d.m.Y H:i:s'), SITE_ID);

		return '\\Brs\\Report\\Agent\\PaymentByPoints::init();';

	}

	/**
	 * Предзагружает все необходимые данные одним набором запросов
	 *
	 * @return void
	 */
	private static function preloadAllData(): void
	{
		self::preloadDeals();
		self::preloadDealCategories();
		self::preloadContacts();
		self::preloadServiceDates();
		self::preloadLeads();
	}

	/**
	 * Очищает предзагруженные данные для освобождения памяти
	 *
	 * @return void
	 */
	private static function clearPreloadedData(): void
	{
		self::$dealsData = [];
		self::$dealCategories = [];
		self::$contactsData = [];
		self::$serviceDates = [];
		self::$leadsData = [];
	}

	/**
	 * Предзагружает данные сделок одним запросом
	 *
	 * @return void
	 */
	private static function preloadDeals(): void
	{
		$connection = Application::getConnection();
		$sql = "
			SELECT 
				d.ID, 
				d.CATEGORY_ID, 
				d.CONTACT_ID, 
				u.UF_CRM_LEAD
			FROM b_crm_deal d
			LEFT JOIN b_uts_crm_deal u ON u.VALUE_ID = d.ID
		";

		$result = $connection->query($sql);

		while ($row = $result->fetch()) {
			// UF_CRM_LEAD может быть сериализованным массивом или строкой
			$leadIds = [];
			if (!empty($row['UF_CRM_LEAD'])) {
				// Проверяем, является ли значение сериализованным
				if (self::isSerialized($row['UF_CRM_LEAD'])) {
					$leadIds = @unserialize($row['UF_CRM_LEAD']);
					if (!is_array($leadIds)) {
						$leadIds = [];
					}
				} else {
					// Если не сериализовано, это может быть одиночное значение
					$leadIds = [(int)$row['UF_CRM_LEAD']];
				}
			}

			self::$dealsData[(int)$row['ID']] = [
				'CATEGORY_ID' => $row['CATEGORY_ID'],
				'CONTACT_ID' => $row['CONTACT_ID'],
				'LEAD_IDS' => $leadIds // Теперь это массив
			];
		}
	}

	/**
	 * Предзагружает названия категорий сделок одним запросом
	 *
	 * @return void
	 */
	private static function preloadDealCategories(): void
	{
		$connection = Application::getConnection();
		$sql = "SELECT ID, NAME FROM b_crm_deal_category";

		$result = $connection->query($sql);

		while ($row = $result->fetch()) {
			self::$dealCategories[(int)$row['ID']] = $row['NAME'];
		}
	}

	/**
	 * Предзагружает данные контактов (ФИО и KS ID) одним запросом
	 *
	 * @return void
	 */
	private static function preloadContacts(): void
	{
		$connection = Application::getConnection();
		$sql = "
			SELECT 
				c.ID, 
				CONCAT(c.LAST_NAME, ' ', c.NAME, ' ', c.SECOND_NAME) as FULL_NAME,
				u.UF_CRM_CONTACT_KS_ID as KS_ID,
				p.MR_ACCOUNT_ID, p.IMPERIA_ACCOUNT_ID
			FROM b_crm_contact c
			LEFT JOIN b_uts_crm_contact u ON u.VALUE_ID = c.ID
			LEFT JOIN brs_contact_point_card p ON p.CONTACT_ID = c.ID
		";

		$result = $connection->query($sql);

		while ($row = $result->fetch()) {
			// Убираем лишние пробелы из ФИО
			$fullName = preg_replace('/\s+/', ' ', trim($row['FULL_NAME']));
			self::$contactsData[(int)$row['ID']] = [
				'FULL_NAME' => $fullName,
				'KS_ID' => $row['KS_ID'] ?? '',
				'MR_ACCOUNT_ID' => $row['MR_ACCOUNT_ID'],
				'IMPERIA_ACCOUNT_ID' => $row['IMPERIA_ACCOUNT_ID']
			];
		}
	}

	/**
	 * Предзагружает даты оказания услуги напрямую из UF_DATE_SERVICE_PROVISION
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
	 * Предзагружает данные лидов одним запросом
	 *
	 * @return void
	 */
	private static function preloadLeads(): void
	{
		$connection = Application::getConnection();

		// Исправлено: выбираем ID и Название из таблицы лидов (b_crm_lead)
		$sql = "SELECT ID, TITLE FROM b_crm_lead";

		$result = $connection->query($sql);

		while ($row = $result->fetch()) {
			// Сохраняем массив: [ID лида] => 'Название лида'
			self::$leadsData[(int)$row['ID']] = $row['TITLE'] ?? '';
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
	 * Получает название категории сделки из предзагруженных данных
	 *
	 * @param int|null $categoryId ID категории
	 * @return string Название категории или пустая строка
	 */
	private static function getDealCategoryName(?int $categoryId): string
	{
		if ($categoryId === null) {
			return '';
		}

		return self::$dealCategories[$categoryId] ?? '';
	}

	/**
	 * Получает данные контакта из предзагруженных данных
	 *
	 * @param int|null $contactId ID контакта
	 * @return array Данные контакта ['FULL_NAME' => ..., 'KS_ID' => ...]
	 */
	private static function getContactData(?int $contactId): array
	{
		if ($contactId === null) {
			return ['FULL_NAME' => '', 'KS_ID' => ''];
		}

		return self::$contactsData[$contactId] ?? ['FULL_NAME' => '', 'KS_ID' => ''];
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
	 * Получает данные лида из предзагруженных данных
	 *
	 * @param int|null $leadId ID лида
	 * @return string Название лида или пустая строка
	 */
	private static function getLeadTitle(?int $leadId): string
	{
		if ($leadId === null) {
			return '';
		}

		return self::$leadsData[$leadId] ?? '';
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

		foreach(PaymentByPointsTable::$codeHeaderFields as $code => $ruLang){
			$header[] = $ruLang;
		}

		$headerKeys = array_flip($header);

		// очищаем таблицу
		Application::getConnection()->truncateTable(PaymentByPointsTable::getTableName());

		if (empty($document['body'])) {
			return;
		}

		// формируем единый SQL запрос на вставку в таблицу
		$sqlInsert = 'INSERT INTO `brs_report_payment_by_points` (
			`PAYERS_FULL_NAME`, 
			`CLIENT_KS_ID`, 
			`CONTACT_ID`, 
			`DEAL_ID`, 
			`DEAL_CATEGORY_NAME`, 
			`DATE_SERVICE_PROVISION`, 
			`LEAD_ID`, 
			`LEAD_TITLE`, 
			`LOYALTY_PROGRAM_TYPE`, 
			`OPERATION_TYPE`, 
			`POINT_AMOUNT`, 
			`AMOUNT`
		) VALUES '."\r\n";

		$sqlInsertValues = [];

		foreach($document['body'] as $row){
			$sqlInsertValues[] = '(\''.
				$DB->ForSql($row[$headerKeys['ФИО клиента']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Идентификатор клиента КС']]).'\', \''.
				$DB->ForSql($row[$headerKeys['ID клиента в Б24']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Номер сделки']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Тип сделки']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Дата оказания услуги']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Номер лида']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Название лида']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Тип программы лояльности']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Тип операции']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Сумма списания в баллах']]).'\', \''.
				$DB->ForSql($row[$headerKeys['Сумма списания в рублях']]).'\')';
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

		foreach(PaymentByPointsTable::$codeHeaderFields as $code => $ruLang){
			$header[] = $ruLang;
		}

		$headerKeys = array_flip($header);

		// тело документа
		$bodyRows = array();

		// получаем транзакции оплаты баллами через ORM
		$transactions = PaymentTransactionTable::getList([
			'select' => [
				'ID', 'DEAL_ID', 'PAYMENT_TYPE', 'AMOUNT', 'POINT_AMOUNT', 'CURRENCY', 'DATE'
			],
			'filter' => [
				'STATUS' => PaymentTransactionTable::PAYMENT_STATUS_SUCCESS,
				'PAYMENT_BY_POINT' => true
			],
			'order' => [
				'ID' => 'DESC'
			]
		]);

		if($transactions->getSelectedRowsCount() == 0){
			return [
				'header' => $header,
				'body' => $bodyRows,
			];
		}

		$transactionCollection = $transactions->fetchAll();

		// типы операций
		$operationTypeMap = [
			PaymentTransactionTable::PAYMENT_TYPE_INCOMING => 'Продажа',
			PaymentTransactionTable::PAYMENT_TYPE_REFUND => 'Возврат'
		];

		// обходим массив транзакций и формируем тело документа
		foreach($transactionCollection as $transaction){

			$dealId = (int)$transaction['DEAL_ID'];

			// получаем данные сделки из предзагруженных данных
			$dealData = self::getDealData($dealId);

			if ($dealData === null) {
				continue;
			}

			// получаем название категории сделки
			$categoryId = $dealData['CATEGORY_ID'] ? (int)$dealData['CATEGORY_ID'] : null;
			$dealCategoryName = self::getDealCategoryName($categoryId);

			// получаем данные контакта
			$contactId = $dealData['CONTACT_ID'] ? (int)$dealData['CONTACT_ID'] : null;
			$contactData = self::getContactData($contactId);

			// формируем ссылку на контакт
			$contactLink = $contactId
				? '<a href="https://crm.rstls.ru/crm/contact/details/'. $contactId .'/">' . $contactData['FULL_NAME'] . '</a>'
				: '';

			// получаем дату оказания услуги
			$dateServiceProvision = self::getServiceDate($dealId);

			// получаем данные лида
			$leadIds = $dealData['LEAD_IDS'] ?? [];
			$leadIdsStr = '';
			$leadTitlesStr = '';

			if (!empty($leadIds)) {
				$leadIdsArr = [];
				$leadTitlesArr = [];

				foreach ($leadIds as $leadId) {
					if ($leadId > 0) {
						$leadIdsArr[] = $leadId;
						$leadTitle = self::getLeadTitle($leadId);
						if (!empty($leadTitle)) {
							$leadTitlesArr[] = $leadTitle;
						}
					}
				}

				$leadIdsStr = implode(', ', $leadIdsArr);
				$leadTitlesStr = implode(', ', $leadTitlesArr);
			}

			// определяем тип программы лояльности
			$loyaltyProgramType = '';
			if (!empty($transaction['CURRENCY']) && isset(CurrencyPointTable::$pointProgramCodeToName[$transaction['CURRENCY']])) {
				$loyaltyProgramType = CurrencyPointTable::$pointProgramCodeToName[$transaction['CURRENCY']];
			}

			// определяем тип операции
			$operationType = $operationTypeMap[$transaction['PAYMENT_TYPE']] ?? '';

			// форматируем суммы
			$pointAmount = number_format((float)$transaction['POINT_AMOUNT'], 2, ',', '');
			$amount = number_format((float)$transaction['AMOUNT'], 2, ',', '');

			// формируем строку документа
			$bodyRow = [
				$headerKeys['ФИО клиента'] => $contactLink,
				$headerKeys['Идентификатор клиента КС'] => $contactData['KS_ID'],
				$headerKeys['ID клиента в Б24'] => $contactId ?? '',
				$headerKeys['Номер сделки'] => $dealId,
				$headerKeys['Тип сделки'] => $dealCategoryName,
				$headerKeys['Дата оказания услуги'] => $dateServiceProvision,
				$headerKeys['Номер лида'] => $leadIdsStr,
				$headerKeys['Название лида'] => $leadTitlesStr,
				$headerKeys['Тип программы лояльности'] => $loyaltyProgramType,
				$headerKeys['Тип операции'] => $operationType,
				$headerKeys['Сумма списания в баллах'] => $pointAmount,
				$headerKeys['Сумма списания в рублях'] => $amount,
			];

			$bodyRows[] = $bodyRow;

		}

		return array(
			'header' => $header,
			'body' => $bodyRows,
		);

	}

}
