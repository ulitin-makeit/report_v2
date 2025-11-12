<?php

	namespace Brs\Report\Page;

	use Bitrix\Main\Application;
	use Bitrix\Main\Loader;
	use Bitrix\Main\UserTable;
	use Bitrix\Crm\StatusTable; 
	use Bitrix\Crm\Category\Entity\DealCategoryTable;

	use Brs\Report\Model\Orm\ClientsTable; // ОРМ таблицы отчёта
	use Brs\FinancialCard\Models\FinancialCardHotelChainTable;
	use Brs\CorporateClients\Models\Orm\CorporateClientsTable;

	/**
	 * Обработчик страницы отчёта "Отчёт по продажам".
	 * 
	 * Формирует выходные данные (грид, список) в шаблон компонента универсального отчёта.
	 */
	class Clients extends AbstractPage {

		public static string $gridCode = 'brsReportClientsList';
		public static string $filterCode = 'brsReportClientsFilter';

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
			Loader::IncludeModule('brs.corporateclients');

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

			\Brs\Report\Agent\Clients::init();

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
			header('Content-Disposition: attachment; filename=Отчёт по клиентам.csv');

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

			Header('Content-Type: application/vnd.ms-excel');
			Header('Content-Disposition: attachment;filename=Отчёт по клиентам.xls');

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
			foreach(ClientsTable::$codeHeaderFields as $columnCode => $columnName){

				$type = 'string';

				if(str_replace('День рождения', '', $columnName) != $columnName){
					$type = 'date';
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

			}

			// фильтрация по дате рождения
			if($getListParams['filter']['<=BIRTHDATE']){

				$finishDateBirthdate = new \DateTime($getListParams['filter']['<=BIRTHDATE']);
				$finishDateBirthdate = $finishDateBirthdate->format('m.d');

				$startDateBirthdate = new \DateTime($getListParams['filter']['>=BIRTHDATE']);
				$startDateBirthdate = $startDateBirthdate->format('m.d');

				$clientBirthdate = $this->getBirthdateClientIdIntervalList($startDateBirthdate,$finishDateBirthdate);

				if(!empty($clientBirthdate)){
					$getListParams['filter']['CONTACT_ID'] = $clientBirthdate;
				}else{
					$getListParams['filter']['CONTACT_ID'] = '';
				}

				unset($getListParams['filter']['<=BIRTHDATE']);
				unset($getListParams['filter']['>=BIRTHDATE']);
			}
			return array(
				'grid' => $grid,
				'getListParams' => $getListParams
			);

		}

		/**
		 * Метод возвращает id контактов у которых день рождение в указанный период
		 *
		 * @param string $startDayMonth - от даты (m.d)
		 * @param string $finishDayMonth - до даты (m.d)
		 * @return array
		 */
		private function getBirthdateClientIdIntervalList(string $startDayMonth, string $finishDayMonth): array {

			global $DB;

			if($finishDayMonth > $startDayMonth) { // если отрезок времени не прерывается новыйм годом
				$clientListDb = $DB->query("SELECT `ID` FROM `b_crm_contact` WHERE DATE_FORMAT(BIRTHDATE, '%m.%d') >= '".$startDayMonth."' AND DATE_FORMAT(BIRTHDATE, '%m.%d') <= '".$finishDayMonth."';");

			} else {
				$clientListDb = $DB->query("SELECT `ID` FROM `b_crm_contact` 
				WHERE (DATE_FORMAT(BIRTHDATE, '%m.%d') >= '".$startDayMonth."' AND DATE_FORMAT(BIRTHDATE, '%m.%d') <= '12.31')
				OR (DATE_FORMAT(BIRTHDATE, '%m.%d') >= '01.01' AND DATE_FORMAT(BIRTHDATE, '%m.%d') <= '".$finishDayMonth."');");
			}

			$ids = [];

			while($client = $clientListDb->fetch()){
				$ids[] = $client['ID'];
			}
			return $ids;

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
				// не выводим в грид колонку День рождения стандартный фильтр
				if($this->columnNameToCode[$columnName] == 'BIRTHDATE_STANDARD'){
					continue;
				}

				$sort = $this->columnNameToCode[$columnName];


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

			// список возможных мест работы клинта
			$placeWork = CorporateClientsTable::getList(['select' => ['TITLE']])->fetchAll();

			$placeWorkList = [
				'' => 'Любая'
			];
			foreach($placeWork as $items){
				$placeWorkList[$items['TITLE']] = $items['TITLE'];
			}

			// список поля тип клиента
			$contactStages = [
				'' => 'Любой'
			];
			$contactStageList = StatusTable::getList([
				'filter' => [
					'ENTITY_ID' => 'CONTACT_TYPE'
				],
				'order' => [
					'NAME' => 'ASC'
				]
			])->fetchAll();

			if(is_array($contactStageList) && count($contactStageList) > 0){
				foreach($contactStageList as $contactStage){
					$contactStages[$contactStage['NAME']] = $contactStage['NAME'];
				}
			}

			// формируем параметры фильтра
			foreach($document['header'] as $key => $columnName){

				$type = 'string';

				$params = [];

				if(str_replace('День рождения', '', $columnName) != $columnName){
					$type = 'date';
				} else if(str_replace('День рождения стандартный фильтр', '', $columnName) != $columnName) {
					$type = 'date';
				} else if(str_replace('Тип клиента по идентификатору КС', '', $columnName) != $columnName){

					$type = 'list';

					$items = [
						'Не заполнено'=>'Не заполнено',
						'Внешний клиент'=>'Внешний клиент',
						'Клиент банка'=>'Клиент банка',

					];

					$params = ['multiple' => 'Y'];
					$this->columnNameToCode[$columnName] = 'KS_TYPE';

				} else if(str_replace('Сделки за 3 мес', '', $columnName) != $columnName){
					$type = 'list';

					$items = [
						'Да'=>'Да',
						'Нет'=>'Нет',

					];
				} else if(str_replace('Сделки за 6 мес', '', $columnName) != $columnName){
					$type = 'list';

					$items = [
						'Да'=>'Да',
						'Нет'=>'Нет',

					];
				} else if(str_replace('Сделки за 12 мес', '', $columnName) != $columnName){
					$type = 'list';

					$items = [
						'Да'=>'Да',
						'Нет'=>'Нет',

					];
				} else if(str_replace('Сделки за 24 мес', '', $columnName) != $columnName){
					$type = 'list';

					$items = [
						'Да'=>'Да',
						'Нет'=>'Нет',

					];
				} else if(str_replace('Корпоративный клиент', '', $columnName) != $columnName){
					$type = 'list';

					$items = [
						'Да'=>'Да',
						'Нет'=>'Нет',

					];
				} else if(str_replace('Статус', '', $columnName) != $columnName){
					$type = 'list';

					$items = [
						'Активный'=>'Активный',
						'Блокированный'=>'Блокированный',
						'Не заполнено'=>'Не заполнено',
					];
				} else if(str_replace('Место работы', '', $columnName) != $columnName){

					$type = 'list';

					$items = $placeWorkList;

					$params = ['multiple' => 'Y'];

				} else if(str_replace('Тип клиента', '', $columnName) != $columnName){

					$type = 'list';

					$items = $contactStages;

					$params = ['multiple' => 'Y'];

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
			$navigationRecordCount = ClientsTable::getList($navGetListParams)->getSelectedRowsCount();
			
			$grid['navigation']->allowAllRecords(true)->setPageSize($navigationParams['nPageSize'])->setRecordCount($navigationRecordCount)->initFromUri();

			return array('grid' => $grid, 'filter' => $arFilter);

		}

		/**
		 * Метод генерирует список из ORM agent.
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
					$this->columnNameToCode[$columnCode] = ClientsTable::$codeHeaderFields[$columnCode];
				}

			} else {
				$this->columnNameToCode = ClientsTable::$codeHeaderFields; // массив соответствий названий колонок и кодов
			}

			// шапка документа
			$header = array();

			foreach($this->columnNameToCode as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header);

			$this->columnNameToCode = array_flip($this->columnNameToCode);

			$bodyRows = array();

			$agentCollection = ClientsTable::getList($arGetListParameters)->fetchAll();

			foreach($agentCollection as $agent){

				$row = array();

				$ormCollectValues = $agent;

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

