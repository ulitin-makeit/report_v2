<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/intranet/public/crm/reports/report/index.php");
$APPLICATION->SetTitle(GetMessage("CRM_TITLE"));
?>
<?$APPLICATION->IncludeComponent('bitrix:crm.control_panel', '', array('ID' => 'REPORT', 'ACTIVE_ITEM_ID' => 'REPORT'));?>
<?$APPLICATION->IncludeComponent('brs.report:report', 'report', $_REQUEST);?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>