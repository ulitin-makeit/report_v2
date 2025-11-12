<?php

	namespace Brs\Report\Controller;

	use Bitrix\Main\Engine\Controller;
	use Bitrix\Main\Engine\Response\AjaxJson;
	use Bitrix\Main\Error;
	use Bitrix\Main\Loader;
	use Bitrix\Main\ErrorCollection;

	use \Brs\Report\Agent\Universal;

	/**
	 * Класс контроллер содержащий Ajax методы для работы с универсальным отчётом.
	 */
	class UniversalController extends Controller {

		/**
		 * Метод обновляет информацию в универсальном отчёте.
		 * 
		 * @param string $operationType
		 * @return AjaxJson
		 */
		public function updateAction(string $operationType): AjaxJson {

			Universal::init($operationType);

			return AjaxJson::createSuccess([
				true
			]);

		}

	}