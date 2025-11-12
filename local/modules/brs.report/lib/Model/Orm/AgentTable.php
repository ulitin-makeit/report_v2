<?php

	namespace Brs\Report\Model\Orm;

	use \Bitrix\Main\ORM\Data\DataManager;
	use \Bitrix\Main\ORM\Fields;

	class AgentTable extends DataManager {

		// поля отчёта (соответствие коду)
		public static array $codeHeaderFields = array(

			'NUMBER_DEAL' => 'Номер сделки', 
			'RESPONSIBLE_NAME' => 'Ответственное лицо', 
			'TITLE_DEAL' => 'Название сделки', 
			'CATEGORY_NAME' => 'Категория',
			'COMMENT_TEAMLEADER' => 'Комментарий Тимлидеру', 
			'KS_ID' => 'ID клиента', 
			'UCHASTIYA_AGENTA_V_PRODAZHE' => 'Участие агента', 
			'COMPLEMENTARY' => 'Связанные сделки', 
			'LEAD_ID' => 'Лид', 
			'CITY_NAME' => 'Город', 
			'COUNTRY_NAME' => 'Страна', 
			'RESULT' => 'Результат сделки', 
			'STATUS' => 'Статус сделки', 
			'TYPE_CLIENT' => 'Тип клиента', 
			'CLIENT_NAME' => 'Клиент', 
			'PARTNER' => 'Партнёр', 
			'DATE_SERVICE' => 'Дата оказания услуги', 
			'TYPE_PAYMENT' => 'Тип оплаты', 
			'DATE_PAYED_CLIENT' => 'Дата оплаты Клиентом', 
			'CLIENT_TOTAL_PAID' => 'Итого оплачено Клиентом', 
			'FINANCIAL_CARD_DATE_CREATED' => 'Дата создания фин.карты', 
			'PARTNER_FULL_NAME' => 'Полное наименование организации', 
			'SUM_SELL' => 'Сумма продажи', 
			'SUM_PAY_PROVIDER' => 'Оплата поставщику', 
			'SUM_NETTO_CURRENCY' => 'Нетто в валюте поставщика', 
			'SUM_NETTO_RUB' => 'Нетто в рублях', 
			'PROFIT' => 'Прибыль', 
			'DATE_CANCEL_OPERATION' => 'Дата отмены операции',
			'STATUS_CARD_REFUND' => 'Статус карты возврата',
			'SUM_CLIENT_RETURN' => 'Сумма возврата клиентом', 
			'PROFIT_RSTLS_WITH_RETURN' => 'Прибыль РС ТЛС с учетом возврата', 
			'DATE_CREATE' => 'Дата создания сделки', 
			'IS_CROSS_SELLING' => 'Кросс-продажа', 
			'CROSS_SELLING_REASON' => 'Кросс-продажа причина',
			'DEFERRED_DATE_ACTIVE_FINISH' => 'Дата отложенной оплаты', 
			'DEFERRED_CURRENCY' => 'Валюта отложенной оплаты', 
			'DEFERRED_AMOUNT' => 'Сумма отложенной оплаты, руб', 
			'DEFERRED_AMOUNT_CURRENCY' => 'Сумма отложенной оплаты, валюта', 
			'DATE_START' => 'Дата начала', 
			'DATE_FINISH' => 'Дата окончания', 

		);

		public static function getTableName(): string {
			return 'brs_report_agent';
		}

		public static function getMap(): array {

			return [

				new Fields\IntegerField('DEAL_ID', [ // номер сделки
					'primary' => true,
					'autocomplete' => true,
					'column_name' => 'DEAL_ID'
				]),

				new Fields\IntegerField('NUMBER_DEAL'), // номер сделки
				new Fields\StringField('TITLE_DEAL'), // название сделки
				new Fields\StringField('CATEGORY_NAME'), // категория
				new Fields\StringField('RESPONSIBLE_NAME'), // ответственное лицо
				new Fields\StringField('COMMENT_TEAMLEADER'), // комментарий Тимлидеру
				new Fields\StringField('KS_ID'), // ID клиента (идентификатор клиента кс)
				new Fields\StringField('COMPLEMENTARY'), // связанные сделки
				new Fields\StringField('LEAD_ID'), // лид
				new Fields\StringField('CITY_NAME'), // город
				new Fields\StringField('COUNTRY_NAME'), // страна
				new Fields\StringField('RESULT'), // результат сделки
				new Fields\StringField('STATUS'), // статус сделки
				new Fields\StringField('TYPE_CLIENT'), // тип клиента
				new Fields\StringField('CLIENT_NAME'), // клиент
				new Fields\StringField('PARTNER'), // партнёр
				new Fields\DateField('DATE_SERVICE'), // дата оказания услуги
				new Fields\StringField('TYPE_PAYMENT'), // тип оплаты
				new Fields\DateField('DATE_PAYED_CLIENT'), // дата оплаты Клиентом
				new Fields\StringField('CLIENT_TOTAL_PAID'), // итого оплачено клиентом
				new Fields\DateField('FINANCIAL_CARD_DATE_CREATED'), // дата создания фин.карты
				new Fields\StringField('PARTNER_FULL_NAME'), // полное наименование организации
				new Fields\StringField('SUM_SELL'), // сумма продажи
				new Fields\StringField('SUM_PAY_PROVIDER'), // оплата поставщику
				new Fields\StringField('SUM_NETTO_CURRENCY'), // нетто в Валюте поставщика
				new Fields\StringField('SUM_NETTO_RUB'), // нетто в рублях
				new Fields\StringField('PROFIT'), // прибыль
				new Fields\DateField('DATE_CANCEL_OPERATION'), // дата отмены операции
				new Fields\StringField('STATUS_CARD_REFUND'), // статус карты возврата
				new Fields\StringField('SUM_CLIENT_RETURN'), // сумма возврата клиентом
				new Fields\StringField('PROFIT_RSTLS_WITH_RETURN'), // прибыль РС ТЛС с учетом возврата
				new Fields\DateField('DATE_CREATE'), // дата создания сделки
				new Fields\StringField('UCHASTIYA_AGENTA_V_PRODAZHE'), // % участия агента в продаже
				new Fields\StringField('IS_CROSS_SELLING'), // Кросс-продажа
				new Fields\StringField('CROSS_SELLING_REASON'), // Кросс-продажа (причина)
				new Fields\DateField('DEFERRED_DATE_ACTIVE_FINISH'), // Дата отложенной оплаты
				new Fields\StringField('DEFERRED_CURRENCY'), // Валюта отложенной оплаты
				new Fields\StringField('DEFERRED_AMOUNT'), // Сумма отложенной оплаты, руб
				new Fields\StringField('DEFERRED_AMOUNT_CURRENCY'), // Сумма отложенной оплаты, валюта
				new Fields\DateField('DATE_START'), // Дата начала
				new Fields\DateField('DATE_FINISH'), // Дата окончания

			];
		}

	}
