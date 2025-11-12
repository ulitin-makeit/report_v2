<?php

	use Bitrix\Main\Application;

	/**
	 * Основной компонент для работы с отчётами.
	 */
	class Report extends \CBitrixComponent {

		/**
		 * Вызываем либо детальную страницу (report.detail), либо список (report.list).
		 */
		public function executeComponent(){

			CJSCore::Init(['jquery3']);

			$page = 'list';

			// получаем полный объект запроса
			$request = Application::getInstance()->getContext()->getRequest();

			$reportCode = $request->get('report');

			if($reportCode){
				$page = 'detail';
			}

			$this->arResult['reportCode'] = $reportCode;

			$this->includeComponentTemplate($page);

		}

	}