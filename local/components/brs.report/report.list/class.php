<?php
ini_set('display_errors','on');

	use Bitrix\Main\Loader;

	use Brs\Report\Report;

	class ReportList extends \CBitrixComponent {

		public function executeComponent(){

			CJSCore::Init(['jquery3']);

			Loader::includeModule('brs.report');

			$this->arResult = Report::getAll()->fetchCollection();

			if($this->arResult->count() == 0){
				$this->includeComponentTemplate();
				return;
			}

			while($item = $this->arResult->next()){

				$report = $item->getHandler();

				if(!$report->checkRights()){
					$this->arResult->deleteCurrent();
				}

			}

			$this->includeComponentTemplate();

		}

	}