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

		// поля отчёта (соответствие коду)
		public static array $codeHeaderFields = array(

			'DEAL_ID' => 'Номер сделки', 
			'TRANSACTION_DATE' => 'Дата транзакции', 
			'DATE_SERVICE_PROVISION' => 'Дата оказания услуги', 
			'TRANSACTION_AMOUNT_RUB' => 'Сумма транзакции, руб.', 
			'RECEIPT_TYPE' => 'Тип чека', 
			'PAYMENT_METHOD' => 'Способ оплаты', 
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
				new Fields\StringField('PAYERS_FULL_NAME'), // ФИО плательщика
				new Fields\StringField('UNLOADING_OFD'), // Выгрузка ОФД
				new Fields\StringField('UNLOADING_1C'), // Выгрузка 1С

			];
		}

	}
