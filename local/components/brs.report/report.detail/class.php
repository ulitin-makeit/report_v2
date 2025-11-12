<?php

	use Bitrix\Main\Loader;

	use Brs\Report\Report;

	class ReportComponent extends \CBitrixComponent {

		public function executeComponent(){

			CJSCore::Init(['jquery3']);

			Loader::includeModule('brs.report');

			$reportObject = Report::getByCode($this->arParams['reportCode']);

			$report = Report::create($reportObject); // создаём объект отчёта

			$handler = $report->getHandler(); // получаем обработчик

			$page = '';

			// если мы получили обънет обработчика, то запускаем его
			if(is_object($handler)){

				$page = $reportObject->getTemplate();

				if(!$handler->checkRights()){
					$page = 'access';
				}

				$this->arResult = $handler->getData($reportObject); // из обработчика вставляем данные в arResult соответствующего шаблона

			} else {
				throw new \Exception('report not handler');
			}

			$this->includeComponentTemplate($page);

		}

	}