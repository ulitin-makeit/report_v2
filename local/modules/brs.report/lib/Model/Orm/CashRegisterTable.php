<?php

	namespace Brs\Report\Model\Orm;

	use \Bitrix\Main\ORM\Data\DataManager;
	use \Bitrix\Main\ORM\Fields;

	class CashRegisterTable extends DataManager {

		// типы чеков
		public static array $receiptType = [
			'Аванс',
			'Полный расчёт',
			'Предоплата',
			'Передача в кредит',
			'Оплата в кредит',
			'Возврат денежных средств, полученных от покупателя',
			'Возврат аванса',
			'Чек коррекции/приход',
			'Чек коррекции/расход',
			'Чек коррекции/Возврат прихода',
			'Чек коррекции/Возврат расхода',
			'Выдача денежных средств покупателю',
			'Возврат денежных средств, выданных покупателю'
		];

		// способы оплат
		public static array $paymentMethods = [
			'Эквайринг',
			'Услуга',
			'Безналичный платеж',
			'Наличный платёж',
			'Сертификат'
		];

		// типы оплаты (для фильтра)
		public static array $paymentTypes = [];

		// поля отчёта (соответствие коду)
		public static array $codeHeaderFields = array(

			'DEAL_ID' => 'Номер сделки', 
			'TRANSACTION_DATE' => 'Дата транзакции', 
			'DATE_SERVICE_PROVISION' => 'Дата оказания услуги', 
			'TRANSACTION_AMOUNT_RUB' => 'Сумма транзакции, руб.', 
			'RECEIPT_TYPE' => 'Тип чека', 
			'PAYMENT_METHOD' => 'Способ оплаты', 
			'PAYMENT_TYPE_DEAL' => 'Тип оплаты',
			'PAYERS_FULL_NAME' => 'Клиент',
			'UNLOADING_OFD' => 'Выгрузка ОФД',
			'UNLOADING_1C' => 'Выгрузка 1С',

		);

		public static function getTableName(): string {
			return 'brs_report_cash_register';
		}

		public static function getMap(): array {

			return [

				new Fields\IntegerField('ID', [
					'primary' => true,
					'autocomplete' => true,
				]),

				new Fields\IntegerField('DEAL_ID'), // № сделки

				// сгненерированные поля отчёта
				new Fields\DateField('TRANSACTION_DATE'), // Дата транзакции
				new Fields\DateField('DATE_SERVICE_PROVISION'), // Дата оказания услуги
				new Fields\StringField('TRANSACTION_AMOUNT_RUB'), // Сумма транзакции, руб.
				new Fields\StringField('RECEIPT_TYPE'), // Тип чека
				new Fields\StringField('PAYMENT_METHOD'), // Способ оплаты
				new Fields\StringField('PAYMENT_TYPE_DEAL'), // Тип оплаты (из сделки)
				new Fields\StringField('PAYERS_FULL_NAME'), // ФИО плательщика
				new Fields\StringField('UNLOADING_OFD'), // Выгрузка ОФД
				new Fields\StringField('UNLOADING_1C'), // Выгрузка 1С

			];
		}

		/**
		 * Загружает варианты типов оплаты из БД для фильтра
		 * 
		 * @return array
		 */
		public static function getPaymentTypes(): array {
			if (!empty(self::$paymentTypes)) {
				return self::$paymentTypes;
			}

			$connection = \Bitrix\Main\Application::getConnection();
			
			// Получаем ID поля
			$sqlFieldId = "SELECT ID FROM b_user_field WHERE FIELD_NAME = 'UF_BRS_CRM_DEAL_PAYMENT_TYPE'";
			$resultFieldId = $connection->query($sqlFieldId);
			$fieldRow = $resultFieldId->fetch();
			
			if (!$fieldRow) {
				return self::$paymentTypes;
			}
			
			$fieldId = (int)$fieldRow['ID'];
			
			// Загружаем варианты списка
			$sqlEnum = "SELECT VALUE FROM b_user_field_enum WHERE USER_FIELD_ID = {$fieldId} ORDER BY SORT, VALUE";
			$resultEnum = $connection->query($sqlEnum);
			
			while ($row = $resultEnum->fetch()) {
				self::$paymentTypes[] = $row['VALUE'];
			}

			return self::$paymentTypes;
		}

	}