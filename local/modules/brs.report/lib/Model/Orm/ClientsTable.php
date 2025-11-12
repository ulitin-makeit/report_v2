<?php

	namespace Brs\Report\Model\Orm;

	use \Bitrix\Main\ORM\Data\DataManager;
	use \Bitrix\Main\ORM\Fields;

	class ClientsTable extends DataManager {

		// поля отчёта (соответствие коду)
		public static array $codeHeaderFields = array(
			'CONTACT_ID' => 'ID Клиента',
			'FIO' => 'Клиент',
			'KS_ID' => 'Идентификатор клиента КС',
			'KS_TYPE' => 'Тип клиента по идентификатору КС',
			'TYPE' => 'Тип клиента',
			'CORPORATE_CLIENT' => 'Корпоративный клиент',
			'STATUS' => 'Статус',
			'PLACE_WORK' => 'Место работы',
			'PHONE' => 'Телефон',
			'EMAIL' => 'E-mail',
			'BIRTHDATE' => 'День рождения',
			'BIRTHDATE_STANDARD' => 'День рождения стандартный фильтр',
			'THREE_MONTH' => 'Сделки за 3 мес',
			'SIX_MONTH' => 'Сделки за 6 мес',
			'TWELVE_MONTH' => 'Сделки за 12 мес',
			'TWO_YEARS' => 'Сделки за 24 мес',

		);

		public static function getTableName(): string {
			return 'brs_report_clients';
		}

		public static function getMap(): array {

			return [

				new Fields\IntegerField('CONTACT_ID', [
					'primary' => true,
				]),
				new Fields\StringField('FIO'),
				new Fields\StringField('KS_ID'),
				new Fields\StringField('TYPE'),
				new Fields\StringField('STATUS'),
				new Fields\StringField('PHONE'),
				new Fields\StringField('EMAIL'),
				new Fields\StringField('THREE_MONTH'),
				new Fields\StringField('SIX_MONTH'),
				new Fields\StringField('TWELVE_MONTH'),
				new Fields\StringField('TWO_YEARS'),
				new Fields\DateField('BIRTHDATE'),
				new Fields\DateField('BIRTHDATE_STANDARD'),
				new Fields\StringField('CORPORATE_CLIENT'),
				new Fields\StringField('PLACE_WORK'),
				new Fields\StringField('KS_TYPE'),
			];
		}

	}
