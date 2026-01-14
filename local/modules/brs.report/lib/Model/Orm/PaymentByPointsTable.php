<?php

namespace Brs\Report\Model\Orm;

use \Bitrix\Main\ORM\Data\DataManager;
use \Bitrix\Main\ORM\Fields;

/**
 * ORM-таблица для отчёта "Оплата баллами"
 *
 * Хранит данные по успешным операциям оплаты баллами программы лояльности
 */
class PaymentByPointsTable extends DataManager {

	// типы операций
	public static array $operationTypes = [
		'Продажа',
		'Возврат'
	];

	// типы программ лояльности
	public static array $loyaltyProgramTypes = [
		'Membership Rewards',
		'Imperia Rewards'
	];

	// поля отчёта (соответствие коду)
	public static array $codeHeaderFields = array(
		'PAYERS_FULL_NAME' => 'ФИО клиента',
		'CLIENT_KS_ID' => 'Идентификатор клиента КС',
		'CONTACT_ID' => 'ID клиента в Б24',
		'DEAL_ID' => 'Номер сделки',
		'DEAL_CATEGORY_NAME' => 'Тип сделки',
		'DATE_SERVICE_PROVISION' => 'Дата оказания услуги',
		'LEAD_ID' => 'Номер лида',
		'LEAD_TITLE' => 'Название лида',
		'LOYALTY_PROGRAM_TYPE' => 'Тип программы лояльности',
		'OPERATION_TYPE' => 'Тип операции',
		'POINT_AMOUNT' => 'Сумма списания в баллах',
		'AMOUNT' => 'Сумма списания в рублях'
	);

	public static function getTableName(): string {
		return 'brs_report_payment_by_points';
	}

	public static function getMap(): array {

		return [

			new Fields\IntegerField('ID', [
				'primary' => true,
				'autocomplete' => true,
			]),

			// основные поля отчёта
			new Fields\StringField('PAYERS_FULL_NAME'), // ФИО клиента
			new Fields\StringField('CLIENT_KS_ID'), // Идентификатор клиента КС
			new Fields\IntegerField('CONTACT_ID'), // ID клиента в Б24
			new Fields\IntegerField('DEAL_ID'), // Номер сделки
			new Fields\StringField('DEAL_CATEGORY_NAME'), // Тип сделки (название категории)
			new Fields\DateField('DATE_SERVICE_PROVISION'), // Дата оказания услуги
			new Fields\StringField('LEAD_ID'), // Номер лида
			new Fields\TextField('LEAD_TITLE'), // Название лида
			new Fields\StringField('LOYALTY_PROGRAM_TYPE'), // Тип программы лояльности
			new Fields\StringField('OPERATION_TYPE'), // Тип операции (продажа/возврат)
			new Fields\StringField('POINT_AMOUNT'), // Сумма списания в баллах
			new Fields\StringField('AMOUNT'), // Сумма списания в рублях

		];
	}

}
