<?php

	namespace Brs\Report\Page;

	use Bitrix\Crm\Category\Entity\DealCategoryTable;
	use Bitrix\Main\Application;
	use Bitrix\Main\Loader;
	use Brs\FinancialCard\Models\FinancialCardHotelChainTable;
	use Brs\Report\Model\Orm\UniversalTable; // ОРМ таблицы отчёта

	/**
	 * Обработчик страницы отчёта "Универсальный отчёт".
	 * 
	 * Формирует выходные данные (грид, список) в шаблон компонента универсального отчёта.
	 */
	class Universal extends AbstractPage {

		public static string $gridCode = 'brsReportUniversalList';
		public static string $filterCode = 'brsReportUniversalFilter';

		private array $columnNameToCodeDelete = [];

		private $reportObject; // orm объект отчёта

		private array $columnNameToCode; // массив соответствий имён колонок и кодов

		/**
		 * Проверяет права на доступ пользователей к отчёту.
		 * 
		 * @return boolean
		 */
		public function checkRights(): bool {
			return true;
		}

		/**
		 * Метод формирует данные для отчёта и возвращает в "arResult".
		 */
		function getData(object $reportObject): array {

			Loader::includeModule('brs.financialcard');
			Loader::includeModule('brs.incomingpaymentecomm');

			$this->reportObject = $reportObject; // сохраняем объект в классе

			// формируем данные на вывод в шаблон
			$arResult = array(
				'TITLE' => $reportObject->getTitle()
			);

			// запускаем экшены
			$this->runAction();

			// формируем список и фильтр на вывод в шаблон
			$arResult += $this->setTemplate();

			return $arResult;

		}

		/**
		 * Метод запускает установленные экшены.
		 * 
		 * @global type $APPLICATION
		 * @return boolean
		 */
		public function runAction(): bool {
			
			global $APPLICATION;

			// получаем полный объект запроса
			$request = Application::getInstance()->getContext()->getRequest();

			// если передаются данные по экспорту
			if(!empty($request->get('export'))){

				$APPLICATION->RestartBuffer();

				if($request->get('export') == 'csv'){
					$this->actionExportCsv($request->get('limit'), $request->get('columnSort'));
				} else if($request->get('export') == 'xls'){
					$this->actionExportXls($request->get('limit'), $request->get('columnSort'));
				}

				exit; // останавливаем выполнение всех дальнейших скриптов

			} else if(!empty($request->get('refresh'))){

				$APPLICATION->RestartBuffer();

				$this->actionRefreshReport();

			} else {
				return false;
			}

		}

		/**
		 * Экшен экспорта отчёта в формате csv
		 */
		private function actionExportCsv(string $limit = 'false', string $columnSort = 'false'): void {

			$settings = $this->getSettingsOfFilterGridList();

			unset($settings['getListParams']['offset']);

			// если пользователь выбрал, что необходимо выгрузить отчёт с ограничением
			if($limit == 'false'){
				unset($settings['getListParams']['limit']);
			}

			$document = $this->getDocument($settings['getListParams']);
			
			$this->exportCsv($document); // экспортируем в CSV файл

		}

		/**
		 * Экшен экспорта отчёта в формате XLS
		 */
		private function actionExportXls(string $limit = 'false', string $columnSort = 'false'): void {

			$settings = $this->getSettingsOfFilterGridList();

			unset($settings['getListParams']['offset']);

			// если пользователь выбрал, что необходимо выгрузить отчёт с ограничением
			if($limit == 'false'){
				unset($settings['getListParams']['limit']);
			}

			$document = $this->getDocument($settings['getListParams'], $columnSort);

			$this->exportXls($document); // экспортируем в XLS файл

		}

		/**
		 * Экшен обновления отчёта
		 */
		private function actionRefreshReport(): void {

			global $APPLICATION;
			
			\ini_set('memory_limit', -1);
			\set_time_limit(0);

			\Brs\Report\Agent\Universal::init();

			LocalRedirect($APPLICATION->GetCurPageParam('', array('refresh')));

		}

		/**
		 * Метод генерирует и отдаёт CSV файл в буфер пользователю (происходит скачивание).
		 */
		private function exportCsv(array $document): void {

			$csvFullDocument = array();

			$csvFullDocument[] = $document['header'];
			$csvFullDocument += $document['body'];

			// устанавливаем разделители
			$separator = ';';
			$enclosure = '"';

			// устанавливаем заголовки
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Transfer-Encoding: UTF-8');
			header('Content-Disposition: attachment; filename=Универсальный отчёт.csv');

			echo pack("CCC", 0xef, 0xbb, 0xbf);

			// сохраняем соединение в буфере
			$output = fopen('php://output', 'wb');

			// обходим каждый элемент массива и записываем в буфер построчно
			foreach($csvFullDocument as $row){
				fputcsv($output, $row, $separator, $enclosure); // записываем строку в буфер
			}

			// закрываем соединение (сбрасываем)
			fclose($output);

		}

		/**
		 * Метод генерирует и отдаёт XLS файл в буфер пользователю (происходит скачивание).
		 */
		private function exportXls(array $document): void {

			while(ob_get_level()){ // сбрасываем буфер всех доступных уровней
				ob_get_clean();
			}

			Header('Content-Type: application/vnd.ms-excel');
			Header('Content-Disposition: attachment;filename=Универсальный отчёт.xls');

			Header('Content-Type: application/octet-stream');
			Header('Content-Transfer-Encoding: binary');

			// добавляем маркер BOM UTF-8
			if(defined('BX_UTF') && BX_UTF){
				echo chr(239).chr(187).chr(191);
			}

			// формируем верхнюю часть документа
			echo '<meta http-equiv="Content-type" content="text/html;charset='.LANG_CHARSET.'" />';

			// формируем верхнюю часть таблицы
			echo '<table border="1"><thead><tr>';

			foreach($document['header'] as $headerName){
				echo '<th>'.$headerName.'</th>';
			}

			echo '</tr></thead>';

			// формируем тело таблицы
			echo '<tbody>';

			foreach($document['body'] as $row){
				
				echo '<tr>';

				foreach($row as $columnText){
					echo '<td>'.mb_eregi_replace("\n", '<br>', $columnText).'</td>';
				}

				echo '</tr>';

			}

			echo '</tbody>';

			echo '</table>';

			exit;

		}


		/**
		 * Метод отдаёт объёкт грида и параметры getList для ORM
		 * 
		 * @return array
		 */
		private function getSettingsOfFilterGridList(): array {
			
			$grid = array();

			$getListParams = array();

			// получаем данные грида
			$gridOptions = new \Bitrix\Main\Grid\Options(self::$gridCode);

			// получаем постраничную навигацию
			$grid['navigation'] = new \Bitrix\Main\UI\PageNavigation(self::$gridCode);
			
			// получаем ранее установленную постраничную навигацию
			$navigationParams = $gridOptions->GetNavParams();
			
			// формируем постраничную навигацию
			$page = 1;
			
			if(array_key_exists(self::$gridCode, $_REQUEST)){
				$page = (int) str_replace('page-', $_REQUEST[self::$gridCode]);
			}
			
			$grid['navigation']->allowAllRecords(true)->setCurrentPage($page)->setPageSize($navigationParams['nPageSize'])->initFromUri();
			
			if($navigationParams['nPageSize'] > 0){
				
				$getListParams['offset'] = $grid['navigation']->getOffset();
				
				$getListParams['limit'] = $grid['navigation']->getLimit();
				
			}
			
			// формируем сортировку
			$sortData = $gridOptions->getSorting();
			
			$getListParams['order'] = $sortData['sort'];

			// получаем фильтр из грида
			$filterOption = new \Bitrix\Main\UI\Filter\Options(self::$filterCode);
 
			$filterData = $filterOption->getFilter([]);

			$getListParams['filter'] = array();

			// формируем параметры фильтра в ORM
			foreach(UniversalTable::$codeHeaderFields as $columnCode => $columnName){

				$type = 'string';

				if(str_replace('Дата', '', $columnName) != $columnName){
					$type = 'date';
				} else if(str_replace('Цепочка', '', $columnName) != $columnName){
					$type = 'multipleString';
				}

				if(array_key_exists($columnCode, $filterData) && $type == 'string'){
					$getListParams['filter']['%'.$columnCode] = $filterData[$columnCode];
				}

				if(array_key_exists($columnCode.'_from', $filterData) && $type == 'date') {
					$getListParams['filter']['>='.$columnCode] = $filterData[$columnCode.'_from'];
				}

				if(array_key_exists($columnCode.'_to', $filterData) && $type == 'date') {
					$getListParams['filter']['<='.$columnCode] = $filterData[$columnCode.'_to'];
				}

				if(array_key_exists($columnCode, $filterData) && $type == 'string'){
					$getListParams['filter']['%'.$columnCode] = $filterData[$columnCode];
				}

				if(array_key_exists($columnCode, $filterData) && $type == 'multipleString'){

					$multiple = [
						'LOGIC' => 'OR'
					];

					foreach($filterData[$columnCode] as $multipleValue){
						$multiple[]['%'.$columnCode] = $multipleValue;
					}

					$getListParams['filter'] = $multiple;

				}

				if (isset($filterData['!KATEGORIYA'])) {
					$getListParams['filter']['!KATEGORIYA'] = $filterData['!KATEGORIYA'];
				}

			}

			return array(
				'grid' => $grid,
				'getListParams' => $getListParams
			);

		}

		/**
		 * Метод формирует данные для вывода в шаблон (фильтр, список)
		 * 
		 * @return array
		 */
		private function setTemplate(): array {
			
			// получаем обработанные настройки из грида и фильтра (сам грид и параметры для getList ORM)
			$settings = $this->getSettingsOfFilterGridList();

			// формируем основные перменные
			$getListParams = $settings['getListParams'];

			$grid = $settings['grid'];

			// получаем данные грида
			$gridOptions = new \Bitrix\Main\Grid\Options(self::$gridCode);
			
			// получаем ранее установленную постраничную навигацию
			$navigationParams = $gridOptions->GetNavParams();

			// получаем документ
			$document = $this->getDocument($getListParams);

			$headerKeys = array_flip($document['header']);

			$columnGrid = [];

			// формируем колонки в грид
			foreach($document['header'] as $key => $columnName){

				$sort = $this->columnNameToCode[$columnName];

				if($this->columnNameToCode[$columnName] == 'NOMER_SDELKI'){
					$sort = 'DEAL_ID';
				}

				$columnGrid[] = array(
					'id' => $this->columnNameToCode[$columnName],
					'name' => $columnName,
					'sort' => $sort,
					'default' => true
				);

			}

			$rowsGrid = [];

			// формируем строки в грид
			foreach($document['body'] as $row){

				$dataRowGrid = [];

				foreach($row as $columnId => $columnValue){

					if($this->columnNameToCode[$document['header'][$columnId]] == 'NOMER_SDELKI'){
						$columnValue = '<a href="/crm/deal/details/'.(int) str_replace('Сделка №', '', $columnValue).'/">'.$columnValue.'</a>';
					} else if($this->columnNameToCode[$document['header'][$columnId]] == 'LEAD_ID' && !empty($columnValue)){
						$columnValue = '<a href="/crm/lead/details/'.$columnValue.'/">'.$columnValue.'</a>';
					}

					$dataRowGrid[$this->columnNameToCode[$document['header'][$columnId]]] = $columnValue;

				}

				$rowsGrid[] = array(
					'data' => $dataRowGrid
				);

			}
			
			// формируем выходной грид
			$grid['columns'] = $columnGrid;
			$grid['rows'] = $rowsGrid;

			$arFilter = array();
			$chains = [
				'' => 'Любая'
			];
			$chainList = FinancialCardHotelChainTable::getList([])->fetchAll();

			if(is_array($chainList) && count($chainList) > 0){
				foreach($chainList as $chain){
					$chains[$chain['VALUE']] = $chain['VALUE'];
				}
			}

			$stagesNameList = [];

			$stagesNameDb = UniversalTable::getList([
				'select' => [
					'STATUS_SDELKI'
				],
				'group' => [
					'STATUS_SDELKI'
				]
			])->fetchAll();

			foreach($stagesNameDb as $stagesName){

				if(is_null(current($stagesName))){
					continue;
				}

				$stagesNameList[current($stagesName)] = current($stagesName);

			}

			$dealCategories = [
				'' => 'Любая'
			];

			$dealCategoryList = DealCategoryTable::getList([
				'filter' => [
					'IS_LOCKED' => false
				],
				'order' => [
					'NAME' => 'ASC'
				]
			])->fetchAll();

			if (is_array($dealCategoryList) && count($dealCategoryList) > 0) {
				foreach ($dealCategoryList as $dealCategory) {
					$dealCategories[$dealCategory['NAME']] = $dealCategory['NAME'];
				}
			}

			// формируем параметры фильтра
			foreach($document['header'] as $key => $columnName){

				$type = 'string';

				$params = [];

				if(str_replace('Дата', '', $columnName) != $columnName){
					$type = 'date';
				} else if(str_replace('Схема финансовой карты', '', $columnName) != $columnName){

					$type = 'list';

					$items = [
						'Агент покупателя' => 'Агент покупателя',
						'Агент Поставщика&nbsp;SR' => 'Агент Поставщика SR',
						'Агент Поставщика&nbsp;LR' => 'Агент Поставщика LR',
						'Оказание услуг' => 'Оказание услуг',
						'Сервисный сбор РС&nbsp;ТЛС' => 'Сервисный сбор РС ТЛС'
					];

					$params = ['multiple' => 'Y'];

				} else if(str_replace('Цепочка', '', $columnName) != $columnName){

					$type = 'list';

					$items = $chains;

					$params = ['multiple' => 'Y'];

				} else if(str_replace('Статус сделки', '', $columnName) != $columnName){

					$type = 'list';

					$items = $stagesNameList;

					$params = ['multiple' => 'Y'];

				} else if (str_replace('Категория', '', $columnName) != $columnName) {

					$type = 'list';

					$items = $dealCategories;

					$params = ['multiple' => 'Y'];

					$arFilter[] = array(
						'id' => '!' . $this->columnNameToCode[$columnName],
						'name' => 'Исключение категорий',
						'type' => 'list',
						'items' => $items,
						'params' => $params
					);

				}

				$arFilter[] = array(
					'id' => $this->columnNameToCode[$columnName],
					'name' => $columnName,
					'type' => $type,
					'items' => $items,
					'params' => $params
				);

			}

			// формируем параметры гетлиста для постраничной навигации
			$navGetListParams = $getListParams;
			
			unset($navGetListParams['limit']);
			
			$navGetListParams['count_total'] = true;
			
			// получаем общее к-во строк
			$navigationRecordCount = UniversalTable::getList($navGetListParams)->getSelectedRowsCount();
			
			$grid['navigation']->allowAllRecords(true)->setPageSize($navigationParams['nPageSize'])->setRecordCount($navigationRecordCount)->initFromUri();

			return array('grid' => $grid, 'filter' => $arFilter);

		}

		/**
		 * Метод генерирует список из ORM universal.
		 * 
		 * @return array
		 */
		private function getDocument(array $arGetListParameters = [], string $columnSort = 'false'): array {

			$gridOptions = new \Bitrix\Main\Grid\Options(self::$gridCode);

			// если пользователь выбрал, что необходимо выгрузить отчёт с сортировкой колонок
			if($columnSort != 'false'){

				$columnSort = $gridOptions->GetVisibleColumns();

				$this->columnNameToCode = [];

				foreach($columnSort as $columnCode){
					$this->columnNameToCode[$columnCode] = UniversalTable::$codeHeaderFields[$columnCode];
				}

			} else {
				$this->columnNameToCode = UniversalTable::$codeHeaderFields; // массив соответствий названий колонок и кодов
			}

			// шапка документа
			$header = array();

			foreach($this->columnNameToCode as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header);

			$this->columnNameToCode = array_flip($this->columnNameToCode);

			$bodyRows = array();

			$universalCollection = UniversalTable::getList($arGetListParameters)->fetchAll();

			foreach($universalCollection as $universal){

				$row = array();

				$ormCollectValues = $universal;

				unset($ormCollectValues['DEAL_ID']);

				foreach($this->columnNameToCode as $name => $code){

					$value = $ormCollectValues[$code];

					// массив превращаем в строку
					if($code == 'DATA_VYLETA'){
						$value = implode(', ', $value);
					}
					
					// если это поле дата
					if($value instanceof \Bitrix\Main\Type\DateTime){
						$value = $value->toString();
					}

					$row[$headerKeys[$name]] = $value;

				}

				$bodyRows[] = $row;

			}

			return array(
				'header' => $header,
				'body' => $bodyRows
			);

		}

	}
