<?php

	namespace Brs\Report\Model\Orm;

	use Bitrix\Main\ORM\Data\DataManager;
	use Bitrix\Main\ORM\Fields;

	class ReportTable extends DataManager {

		public static function getTableName(){
			return 'brs_report';
		}

		public static function getMap(){

			return [

				new Fields\IntegerField('id', [

					'primary' => true,
					'autocomplete' => true,

					'column_name' => 'ID'

				]),

				new Fields\IntegerField('sort', [
					'column_name' => 'SORT'
				]),

				new Fields\StringField('title', [
					'column_name' => 'TITLE',
				]),

				new Fields\StringField('handler', [
					'column_name' => 'HANDLER',
				]),

				new Fields\StringField('template', [
					'column_name' => 'TEMPLATE',
				]),

				new Fields\StringField('code', [
					'column_name' => 'CODE',
				])

			];

		}

		public static function getList(array $parameters = []){

			if(!array_key_exists('select', $parameters)) {
				$parameters['select'] = ['*'];
			}

			return parent::getList($parameters);

		}

	}