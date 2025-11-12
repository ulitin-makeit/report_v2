<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Brs\Report\Model\Orm\AgentTable;
use Brs\Report\Model\Orm\CashRegisterTable;
use Brs\Report\Model\Orm\ReportTable;
use Brs\Report\Model\Orm\SaleTable;
use Brs\Report\Model\Orm\UniversalTable;

class brs_report extends CModule
{
    const MODULE_ID = 'brs.report';
    public $MODULE_ID = self::MODULE_ID;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_CSS;
    public $strError = '';

    function __construct()
    {
        $arModuleVersion = array();

        include(dirname(__FILE__) . '/version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];

        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = 'BRS Модуль отчётов';
        $this->MODULE_DESCRIPTION = 'Устанавливает и добавляет свои отчёты';

        $this->PARTNER_NAME = 'Отдел разработки BRS';
        $this->PARTNER_URI = 'https://brs.ru/';
    }

    function DoInstall()
    {

        ModuleManager::registerModule(self::MODULE_ID);

		$dateNextAgent = new \DateTime();

		$dateNextAgent->modify('1 day');

		$dateNextAgent = $dateNextAgent->format('d.m.Y').' 09:00:00';

		\CAgent::AddAgent('Brs\Report\Agent\Universal::init();', self::MODULE_ID, 'N', 86400, '', 'Y', $dateNextAgent);

        $this->InstallDB();

    }

    function DoUninstall()
    {

        $this->UnInstallDB();

		\CAgent::RemoveModuleAgents(self::MODULE_ID);

		ModuleManager::unRegisterModule(self::MODULE_ID);

    }

    function InstallDB()
    {

        Loader::includeModule(static::MODULE_ID);

        global $DB;

        $DB->RunSQLBatch(__DIR__.'/install.sql');

    }

    function UnInstallDB()
    {

        if (Loader::includeModule($this->MODULE_ID))
        {
            $connection = Application::getInstance()->getConnection();
            $connection->dropTable(UniversalTable::getTableName());
            $connection->dropTable(SaleTable::getTableName());
            $connection->dropTable(ReportTable::getTableName());
            $connection->dropTable(CashRegisterTable::getTableName());
            $connection->dropTable(AgentTable::getTableName());
        }

    }
}