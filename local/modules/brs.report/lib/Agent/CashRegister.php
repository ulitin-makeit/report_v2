<?php

	namespace Brs\Report\Agent;

	use Bitrix\Main\Application;
	use Bitrix\Main\Config\Option;

	use Brs\Report\Model\Orm\CashRegisterTable; // ОРМ таблицы отчёта
	use Brs\ReceiptOfd\Models\ReceiptTable; // ОРМ таблицы отчёта
	use Brs\Exchange1C\Models\AccountingEntryTable; // проводки
	use Brs\Report\Model\Orm\UniversalTable; // универсальный отчёт

	ini_set('memory_limit', '5000M');

	/**
	 * Агент отчёта, перезаписывает данные в таблицу по нему (чтобы можно было фильтровать и список использовать на странице отчёта).
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

			// генерируем сам отчёт
			$document = self::generateDocumentReport();

			// заполняем таблицу
			self::fillReportTable($document);

			Option::set('brs.report', 'BRS_REPORT_CASH_REGISTER_DATE_REFRESH', (new \DateTime())->format('d.m.Y H:i:s'), SITE_ID); // сохраняем дату последнего обновления отчёта

			return '\\Brs\\Report\\Agent\\CashRegister::init();';

		}

		/**
		 * Метод заполняет таблицу отчётов.
		 * 
		 * @param array $document
		 */
		private function fillReportTable(array $document){

			global $DB;

			// шапка документа
			$header = array();

			foreach(CashRegisterTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header); // переворачиваем массив и ищем по ключам
			
			// принудительно обновляем универсальный отчёт
			Universal::init('changedDeal');

			// очищаем таблицу
			Application::getConnection()->truncateTable(CashRegisterTable::getTableName());

			// формируем единый SQL запрос на вставку в таблицу
			$sqlInsert = 'INSERT INTO `brs_report_cash_register` (`DEAL_ID`, `TRANSACTION_DATE`, `DATE_SERVICE_PROVISION`, `TRANSACTION_AMOUNT_RUB`, `RECEIPT_TYPE`, `PAYMENT_METHOD`, `PAYERS_FULL_NAME`, `UNLOADING_OFD`, `UNLOADING_1C`) VALUES '."\r\n";
			$sqlInsertValues = [];

			foreach($document['body'] as $row){
				$sqlInsertValues[] = '(\''.$row[$headerKeys['Номер сделки']].'\', \''.$row[$headerKeys['Дата транзакции']].'\', \''.$row[$headerKeys['Дата оказания услуги']].'\', \''.$row[$headerKeys['Сумма транзакции, руб.']].'\', \''.$row[$headerKeys['Тип чека']].'\', \''.$row[$headerKeys['Способ оплаты']].'\', \''.$row[$headerKeys['Клиент']].'\', \''.$row[$headerKeys['Выгрузка ОФД']].'\', \''.$row[$headerKeys['Выгрузка 1С']].'\')';
			}

			$sqlInsert = $sqlInsert.implode(','."\r\n", $sqlInsertValues).';';

			$DB->query($sqlInsert);
			
		}

		/**
		 * Метод формирует заголовок и тело документа (отчёта).
		 * 
		 * @return array header, body
		 */
		private static function generateDocumentReport(){

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
			$paymentType = ReceiptTable::PAYMENT_TYPE_LANG;

			$paymentMethods = [
				'ACQUIRING' => 'Эквайринг',
				'SERVICE' => 'Услуга',
			];

			$headerKeys = array_flip($header);

			// тело документа
			$bodyRows = array();

			// получаем чеки
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

			// получаем строки универсального отчёта
			$universalListDb = UniversalTable::getList([
				'select' => [

					'DEAL_ID',

					'DATE_SERVICE_PROVISION'

				]
			])->fetchAll();

			$universalList = [];
				
			foreach($universalListDb as $universal){
				$universalList[$universal['DEAL_ID']] = $universal;
			}

			// получаем данные проводок
			$accoutingListDb = AccountingEntryTable::getList([
				'select' => [
					'ID', 'UID', 'STATUS'
				],
				'filter' => [
					'!UID' => '',
					'STATUS' => 'SUCCESS'
				],
				'order' => [
					'ID' => 'DESC'
				]
			]);

			$accoutingList = [];
				
			foreach($accoutingListDb as $accouting){
				$accoutingList[$accouting['UID']] = $accouting;
			}

			// обходим массив сделок и формируем тело документа
			foreach($receiptCollection as $receipt){

				$deal = \CCrmDeal::GetByID($receipt['DEAL_ID']);

				// исключаем категорию Elite Tiers Registration
				if($deal['CATEGORY_ID'] === '21') {
					continue;
				}


				$request = json_decode($receipt['REQUEST_RECEIPT_JSON'], true);

				$sumTransaction = 0; // сумма транзации

				$paymentItems = $request['Request']['CustomerReceipt']['PaymentItems'];

				foreach($paymentItems as $paymentItem){
					$sumTransaction += $paymentItem['Sum'];
				}

				$sumTransaction = number_format((float)$sumTransaction, 2, ',', '');


				$clientId = $deal['CONTACT_ID'];
				$contact = \CCrmContact::GetByID($clientId);
				$client = '<a href="/crm/contact/details/'. $clientId .'/">' . "{$contact['LAST_NAME']} {$contact['NAME']} {$contact['SECOND_NAME']}" . '</a>';

				$is1C = 'Нет';

				if(array_key_exists($receipt['UID'], $accoutingList)){
					$is1C = 'Да';
				}

				$receiptTypeName = '';

				if(is_array($receiptType[$receipt['RECEIPT_TYPE']])){
					$receiptTypeName = $receiptType[$receipt['RECEIPT_TYPE']][$receipt['PAYMENT_TYPE']];
				} else {
					$receiptTypeName = $receiptType[$receipt['RECEIPT_TYPE']];
				}

				// способ оплаты
				$paymentMethod = $paymentMethods['ACQUIRING']; // по умолчанию эквайринг

				$costumerPaymentType = $request['Request']['CustomerReceipt']['PaymentType'];

				if($costumerPaymentType == 4){
					$paymentMethod = $paymentMethods['SERVICE'];
				}

				$dateServiceProvision = '';

				// получаем дату оказания услуги из универсального отчёта
				if(array_key_exists($receipt['DEAL_ID'], $universalList)){

					$universalReport = $universalList[$receipt['DEAL_ID']];

					$dateServiceProvision = $universalReport['DATE_SERVICE_PROVISION'] ? $universalReport['DATE_SERVICE_PROVISION']->format('Y-m-d') : '';

				}

				// формируем строку документа (заполняем по умолчанию)
				$bodyRow = [
					
					$headerKeys['Номер сделки'] => $receipt['DEAL_ID'],
					$headerKeys['Дата транзакции'] => $receipt['DATE_CREATE'] ? $receipt['DATE_CREATE']->format('Y-m-d') : '',
					$headerKeys['Дата оказания услуги'] => $dateServiceProvision,
					$headerKeys['Сумма транзакции, руб.'] => $sumTransaction,
					$headerKeys['Тип чека'] => $receiptTypeName,
					$headerKeys['Способ оплаты'] => $paymentMethod,
					$headerKeys['Клиент'] => $client,
					$headerKeys['Выгрузка ОФД'] => !empty($receipt['RECEIPT_URL']) ? 'Да' : 'Нет',
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