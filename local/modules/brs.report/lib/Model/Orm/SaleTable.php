<?php

	namespace Brs\Report\Model\Orm;

	use \Bitrix\Main\ORM\Data\DataManager;
	use \Bitrix\Main\ORM\Fields;

	class SaleTable extends DataManager {

		// поля отчёта (соответствие коду)
		public static array $codeHeaderFields = array(

			'ID' => 'ID',
			'NUMBER_DEAL' => 'Номер сделки',
			'TITLE_DEAL' => 'Название сделки', 
			'TYPE' => 'Тип', 
			'DATE_PAYMENT_BY_CLIENT' => 'Дата оплаты Клиентом',
			'DATE_CANCELLATION_OPERATION_REFUND' => 'Дата отмены операции (возврат)',
			'RETURN_DATE' => 'Дата возврата',
			'TRANSACTION_CREATION_DATE' => 'Дата создания сделки',
			'ACCOUNT_NUMBER' => 'Номер счёта',
			'RESPONSIBLE_PERSON' => 'Ответственное лицо',
			'AGENT_PARTICIPATION_IN_SALE' => '% участия агента в продаже',
			'AGENT_PARTICIPATION' => 'Участие агента',
			'CLIENT_TYPE' => 'Тип клиента',
			'CLIENT_ID' => 'ID клиента',
			'CARD_TYPE' => 'Тип карты',
			'MARKETING_CHANNEL' => 'Маркетинговый канал',
			'COUNTRY' => 'Страна',
			'CITY' => 'Город',
			'CATEGORY' => 'Категория',
			'HOTEL' => 'Гостиница',
			'FULL_NUMBER_OF_NIGHTS' => 'Общее количество ночей',
			'PARTNER' => 'Партнер',
			'FULL_NAME_SUPPLIER' => 'Полное наименование поставщика',
			'DATE_PAYMENT_TO_PARTNER_SUPPLIER' => 'Дата оплаты партнеру (поставщику)',
			'DATE_SERVICE_PROVISION' => 'Дата оказания услуги',
			'SALE_AMOUNT' => 'Сумма продажи',
			'PROFIT' => 'Прибыль',
			'PROFIT_WITHOUT_VAT' => 'Прибыль без НДС',
			'PARTNERS_COMMISSION' => 'Комиссия ПАРТНЕРА',
			'ADDITIONAL_BENEFIT' => 'Дополнительная выгода',
			'SERVICE_FEE' => 'Сервисный сбор',
			'SR' => 'SR',
			'LR' => 'LR',
			'MR_POINTS' => 'Баллы MR',
			'IMP_POINTS' => 'Баллы IMP',
			'CASHLESS_PAYMENT' => 'Безналичный расчет',
			'CASH' => 'Наличные',
			'MAP' => 'Карта',
			'CERTIFICATE' => 'Сертификат',
			'LOSS_PER_COMPANY' => 'Убыток на компанию',
			'LOSS_PER_EMPLOYEE' => 'Убыток на сотрудника',
			'TID_AMOUNT' => 'Сумма TID',
			'COMMUNICATION_CHANNEL' => 'Канал связи',
			'REQUEST_TYPE' => 'Тип запроса',
			'TRANSACTION_STATUS' => 'Статус сделки',
			'THE_RESULT_TRANSACTION' => 'Результат сделки',
			'RELATED_TRANSACTIONS' => 'Связанные сделки',
			'LEAD_ID' => 'Лид',
			'TOUR' => 'Тур',
			'NET_IN_RUBLES' => 'Нетто в рублях',
			'THE_REASON_FOR_TRANSACTION_STAGE_IS_LOST' => 'Причина стадии Сделка проиграна',
			'CHAIN' => 'Цепочка',
			'TRANSACTION_CURRENCY' => 'Валюта сделки',
			'DATE_CREATION' => 'Дата создания фин.карты',
			'CLIENT' => 'Клиент',
			'PAYMENT_RATE' => 'Курс оплаты',
			'RATE_PAYMENT_CENTRAL_BANK' => 'Курс оплаты ЦБ',
			'REFUND_CARD_STATUS' => 'Статус карты возврата',
			'FINANCIAL_CARD_SCHEME' => 'Схема финансовой карты',
			'DEFERRED_DATE_ACTIVE_FINISH' => 'Дата отложенной оплаты', 
			'DEFERRED_CURRENCY' => 'Валюта отложенной оплаты', 
			'DEFERRED_AMOUNT' => 'Сумма отложенной оплаты, руб', 
			'DEFERRED_AMOUNT_CURRENCY' => 'Сумма отложенной оплаты, валюта', 
			'PAYMENT_TYPE' => 'Тип оплаты',
			'TOTAL_PAID_BY_CLIENT' => 'Итого оплачено клиентом',
			'NET_SUPPLIER_CURRENCY' => 'Нетто в Валюте поставщика',
			'GROSS_SUPPLIER_CURRENCY' => 'Брутто в Валюте поставщика',
			'COMMISION_SUPPLIER_CURRENCY' => 'Комиссия поставщика в Валюте',
			'REFOUND_AMOUNT_CLIENT' => 'Сумма возврата клиенту',
			'DATE_START' => 'Дата начала',
			'DATE_CLOSE' => 'Дата завершения',
			'PRODUCTS_FEE_REFOUND' => 'Сбор РС ТЛС за возврат',
			'AVERAGE_RATE' => 'Средний курс для возврата',
		);

		// финансовые поля
		public static $financialFields = [
			'SALE_AMOUNT', 'PROFIT', 'PROFIT_WITHOUT_VAT', 'MR_POINTS', 'IMP_POINTS', 'SR', 'LR', 'PARTNERS_COMMISSION', 'ADDITIONAL_BENEFIT', 'SERVICE_FEE', 'CASHLESS_PAYMENT', 'NET_IN_RUBLES', 'TOTAL_PAID_BY_CLIENT'
		];

		public static function getTableName(): string {
			return 'brs_report_sale';
		}

		public static function getMap(): array {

			return [

				new Fields\IntegerField('ID', [
					'primary' => true,
					'autocomplete' => true,
					'column_name' => 'ID'
				]),
				new Fields\IntegerField('DEAL_ID', [ // номер сделки
					'column_name' => 'DEAL_ID'
				]),

				new Fields\IntegerField('NUMBER_DEAL'), // номер сделки
				new Fields\StringField('TITLE_DEAL'), // название сделки
				new Fields\DateField('DATE_PAYMENT_BY_CLIENT'), // дата оплаты клиентом 
				new Fields\DateField('DATE_CANCELLATION_OPERATION_REFUND'), // дата отмены операции (возврат) 
				new Fields\DateField('RETURN_DATE'), // дата возврата
				new Fields\DateField('TRANSACTION_CREATION_DATE'), // дата создания сделки 
				new Fields\StringField('TYPE'), // тип текущего отчёта
				new Fields\StringField('ACCOUNT_NUMBER'), // номер счёта 
				new Fields\StringField('RESPONSIBLE_PERSON'), // ответственное лицо 
				new Fields\StringField('AGENT_PARTICIPATION_IN_SALE'), // % участия агента в продаже
				new Fields\StringField('AGENT_PARTICIPATION'), // участие агента
				new Fields\StringField('CLIENT_TYPE'), // тип клиента 
				new Fields\StringField('CLIENT_ID'), // id клиента 
				new Fields\StringField('CARD_TYPE'), // тип карты 
				new Fields\StringField('MARKETING_CHANNEL'), // маркетинговый канал 
				new Fields\StringField('COUNTRY'), // страна 
				new Fields\StringField('CITY'), // город 
				new Fields\StringField('CATEGORY'), // категория 
				new Fields\StringField('HOTEL'), // гостиница
				new Fields\StringField('FULL_NUMBER_OF_NIGHTS'), // общее количество ночей
				new Fields\StringField('PARTNER'), // партнер 
				new Fields\StringField('FULL_NAME_SUPPLIER'), // полное наименование поставщика
				new Fields\DateField('DATE_PAYMENT_TO_PARTNER_SUPPLIER'), // дата оплаты партнеру (поставщику) 
				new Fields\DateField('DATE_SERVICE_PROVISION'), // дата оказания услуги 
				new Fields\StringField('SALE_AMOUNT'), // сумма продажи 
				new Fields\StringField('PROFIT'), // прибыль 
				new Fields\StringField('PROFIT_WITHOUT_VAT'), // прибыль без ндс 
				new Fields\StringField('PARTNERS_COMMISSION'), // комиссия партнера
				new Fields\StringField('ADDITIONAL_BENEFIT'), // дополнительная выгода 
				new Fields\StringField('SERVICE_FEE'), // сервисный сбор 
				new Fields\StringField('SR'), // sr 
				new Fields\StringField('LR'), // lr
				new Fields\StringField('MR_POINTS'), // баллы mr
				new Fields\StringField('IMP_POINTS'), // баллы imp 
				new Fields\StringField('CASHLESS_PAYMENT'), // безналичный расчет
				new Fields\StringField('CASH'), // наличные
				new Fields\StringField('MAP'), // карта
				new Fields\StringField('CERTIFICATE'), // сертификат 
				new Fields\StringField('LOSS_PER_COMPANY'), // убыток на компанию 
				new Fields\StringField('LOSS_PER_EMPLOYEE'), // убыток на сотрудника 
				new Fields\StringField('TID_AMOUNT'), // сумма tid
				new Fields\StringField('COMMUNICATION_CHANNEL'), // канал связи 
				new Fields\StringField('REQUEST_TYPE'), // тип запроса 
				new Fields\StringField('TRANSACTION_STATUS'), // статус сделки 
				new Fields\StringField('THE_RESULT_TRANSACTION'), // результат сделки 
				new Fields\StringField('RELATED_TRANSACTIONS'), // связанные сделки 
				new Fields\StringField('LEAD_ID'), // лид
				new Fields\StringField('TOUR'), // тур 
				new Fields\StringField('NET_IN_RUBLES'), // нетто в рублях 
				new Fields\StringField('THE_REASON_FOR_TRANSACTION_STAGE_IS_LOST'), // причина стадии сделка проиграна 
				new Fields\StringField('CHAIN'), // цепочка 
				new Fields\StringField('TRANSACTION_CURRENCY'), // валюта сделки
				new Fields\DateField('DATE_CREATION'), // дата создания фин.карты
				new Fields\StringField('CLIENT'), // клиент 
				new Fields\StringField('PAYMENT_RATE'), // курс оплаты
				new Fields\StringField('RATE_PAYMENT_CENTRAL_BANK'), // курс оплаты ЦБ
				new Fields\StringField('REFUND_CARD_STATUS'), // статус карты возврата
				new Fields\StringField('FINANCIAL_CARD_SCHEME'), // схема финансовой карты
				new Fields\DateField('DEFERRED_DATE_ACTIVE_FINISH'), // Дата отложенной оплаты
				new Fields\StringField('DEFERRED_CURRENCY'), // Валюта отложенной оплаты
				new Fields\StringField('DEFERRED_AMOUNT'), // Сумма отложенной оплаты, руб
				new Fields\StringField('DEFERRED_AMOUNT_CURRENCY'), // Сумма отложенной оплаты, валюта
				new Fields\StringField('PAYMENT_TYPE'), // тип оплаты
				new Fields\StringField('TOTAL_PAID_BY_CLIENT'), // итого оплачено клиентом
				new Fields\StringField('NET_SUPPLIER_CURRENCY'), // Нетто в Валюте поставщика
				new Fields\StringField('GROSS_SUPPLIER_CURRENCY'), // Брутто в Валюте поставщика
				new Fields\StringField('COMMISION_SUPPLIER_CURRENCY'), // Комиссия поставщика в Валюте
				new Fields\StringField('REFOUND_AMOUNT_CLIENT'), // Сумма возврата клиенту
				new Fields\StringField('DATE_START'), // Дата начала
				new Fields\StringField('DATE_CLOSE'), // Дата завершения
				new Fields\StringField('PRODUCTS_FEE_REFOUND'), // Сбор РС ТЛС за возврат
				new Fields\StringField('AVERAGE_RATE'), // Средний курс для возврата
			];
		}

		/**
		 * Сбрасывает инкремент текущей таблицы.
		 * 
		 * @global type $DB
		 */
		public static function resetAutoIncrement(){

			global $DB;

			$DB->query('ALTER TABLE `'.self::getTableName().'` AUTO_INCREMENT = 1');

		}

	}
