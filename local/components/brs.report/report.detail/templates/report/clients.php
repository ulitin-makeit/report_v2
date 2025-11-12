<?php

	// если вызов файла напрямую по ссылке, то блокируем
	if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true){
		die(); 
	}

	use Brs\Report\Page\Clients, Bitrix\Main\Config\Option, Bitrix\Main\Page\Asset;
    use Brs\Main\classes\general\FilterUIModification;

	\CJSCore::Init(array('jquery'));

	\Bitrix\Main\UI\Extension::load('ui.dialogs.messagebox');

	\Bitrix\Main\UI\Extension::load('ui.forms'); 
	\Bitrix\Main\UI\Extension::load('ui.icons');

	\Bitrix\Main\UI\Extension::load('ui.alerts');

	$APPLICATION->SetTitle($arResult['TITLE']);

	$result = $arResult;

	// устанавливаем контентную часть внутрь заголовка
	$this->SetViewTarget('inside_pagetitle');

    // установка фолтного фильтра
    $filterParams = [
        'filter_birthdate_next_30' => [
            'name'   => 'следующие 30 дней',
            'fields' => [
                'BIRTHDATE_datesel' => 'NEXT_DAYS',
                'BIRTHDATE_from' => '',
                'BIRTHDATE_to' => '',
                'BIRTHDATE_days' => '30',
                'BIRTHDATE_month' => '',
                'BIRTHDATE_quarter' => '',
            ],
            'filter_rows' => 'BIRTHDATE',
            'for_all'     => false,
            'sort'        => '0',
        ],
    ];
    $res = FilterUIModification::updateFilterInDB(Clients::$filterCode, 'filter_birthdate_next_30', $filterParams);
    //если первое добавление фильтра пользователю то обновляем страницу что бы фильтр отобразился
    if($res == 'add'){
        header("Refresh: 0");
    }

?>
<div class="ui-alert ui-alert-xs ui-alert-icon-info">
	<span class="ui-alert-message"><strong>Внимание!</strong> Данные отчёта актуальны на <b><?=Option::get('brs.report', 'BRS_REPORT_CLIENTS_DATE_REFRESH')?></b>. <a href="<?=$APPLICATION->GetCurPageParam('refresh=true', array('refresh'))?>" href="">Обновить</a></span>
</div>
<?
	// устанавливаем контентную в заголовок
	$this->SetViewTarget('pagetitle');
	
	// вызываем фильтр
	$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
		
		'FILTER_ID' => Clients::$filterCode,
		'GRID_ID' => Clients::$gridCode,
		
		'FILTER' => $arResult['filter'],
		
		'ENABLE_LIVE_SEARCH' => true, 
		'ENABLE_LABEL' => true
		
	]);

?>
	<a href="#" onclick="reportClientsExport();" class="ui-btn ui-btn-primary">Экспортировать отчёт</a>
<?

	$this->EndViewTarget();
	
	// вызываем компонент вывода списка
	$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
		
		'GRID_ID' => Clients::$gridCode,
		'COLUMNS' => $arResult['grid']['columns'],
		'TOTAL_COLUMNS' => [],
		'ROWS' => $arResult['grid']['rows'],
		'NAV_OBJECT' => $arResult['grid']['navigation'],
		'AJAX_MODE' => 'Y',
		'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '', ''),
		
		'PAGE_SIZES' => array(
			
			['NAME' => '10', 'VALUE' => '10'], 
			['NAME' => '20', 'VALUE' => '20'], 
			['NAME' => '50', 'VALUE' => '50'], 
			['NAME' => '100', 'VALUE' => '100']
			
		),
		
		'AJAX_OPTION_JUMP' => 'Y',
		
		'SHOW_ROW_CHECKBOXES' => true,
		'SHOW_CHECK_ALL_CHECKBOXES' => true,
		'SHOW_ROW_ACTIONS_MENU' => true,
		'SHOW_GRID_SETTINGS_MENU' => true,
		'SHOW_NAVIGATION_PANEL' => true,
		'SHOW_PAGINATION' => true,
		'SHOW_SELECTED_COUNTER' => true,
		'SHOW_TOTAL_COUNTER' => true,
		'SHOW_PAGESIZE' => true,
		'SHOW_ACTION_PANEL' => true,
		'TOTAL_ROWS_COUNT_HTML' => '<span class="main-grid-panel-content-title">Всего найдено:</span> <span class="main-grid-panel-content-text">' . $arResult['grid']['navigation']->getRecordCount() . '</span>',
		
		'ALLOW_COLUMNS_SORT' => true,
		'ALLOW_COLUMNS_RESIZE' => true,
		'ALLOW_HORIZONTAL_SCROLL' => true,
		'ALLOW_SORT' => true,
		'ALLOW_PIN_HEADER' => true,
		
		'AJAX_OPTION_HISTORY' => 'N'
		
	]);

?>

<script>
	
	function reportClientsExport(){
		
		BX.UI.Dialogs.MessageBox.show({
			
			message: `
			   <label class="ui-ctl ui-ctl-checkbox">
				  <input id="report-Clients-export-column-sort" type="checkbox" class="ui-ctl-element">
					 <div class="ui-ctl-label-text">Выгрузить отчёт с установленной сортировкой столбцов</div>
			   </label>
			   <label class="ui-ctl ui-ctl-checkbox">
				  <input id="report-Clients-export-limit" type="checkbox" class="ui-ctl-element">
					 <div class="ui-ctl-label-text">Выгрузить отчёт с ограничением кол-ва строк как на текущей странице</div>
			   </label>
				<select id="report-Clients-export-select-format" class="select-report-Clients">
					<option value="xls" checked>В XLS формате</option>
					<option value="csv">В CSV формате</option>
				</select>
			`,
			
			title: 'В каком формате вам экспортировать отчёт?',
			
			buttons: [
			
				new BX.UI.Button({
					color: BX.UI.Button.Color.SUCCESS,
					text: 'Экспортировать',
					onclick: function(button, event) {
						
						var limit = $('#report-Clients-export-limit').prop('checked');
						var exportFormat = $('#report-Clients-export-select-format').val();
						var columnSort = $('#report-Clients-export-column-sort').prop('checked');
						
						window.open('/crm/reports/report/?report=Clients&export=' + exportFormat + '&limit=' + limit + '&columnSort=' + columnSort, '_blank');
						
						button.context.close();
						
					}
				}),
				
				new BX.UI.CancelButton({
					onclick: function(button, event) {
						button.context.close();
					}
				})
				
			],
			
		});
		
	}
	
</script>