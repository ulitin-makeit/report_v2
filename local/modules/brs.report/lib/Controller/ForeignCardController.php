<?php

	namespace Brs\Report\Controller;

	use Bitrix\Main\Engine\Controller;
	use Bitrix\Main\Engine\Response\AjaxJson;
	use Bitrix\Main\Error;
	use Bitrix\Main\ErrorCollection;
	use Bitrix\Main\Loader;
	use Brs\Report\Agent\ForeignCard;

	/**
	 * Класс контроллер содержащий Ajax методы для работы с универсальным отчётом.
	 */
	class ForeignCardController extends Controller {

		/**
		 * Метод обновляет информацию в универсальном отчёте.
		 *
		 * @param string $operationType
		 * @return AjaxJson
		 */
		public function updateAction (string $operationType): AjaxJson {

			ForeignCard::init($operationType);

			return AjaxJson::createSuccess([true]);

		}

	}