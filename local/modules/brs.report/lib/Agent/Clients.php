<?php

	namespace Brs\Report\Agent;

	use Bitrix\Crm\ContactTable;
	use Bitrix\Main\Application;
	use Bitrix\Main\Config\Option;
	use Brs\Report\Model\Orm\ClientsTable;
	use Brs\CorporateClients\Models\Orm\CorporateClientsTable;

	class Clients {

		/**
		 * Метод инициализирует перезапись отчёта в таблице.
		 *
		 * @return string
		 * @throws \Bitrix\Main\ArgumentException
		 * @throws \Bitrix\Main\ArgumentOutOfRangeException
		 * @throws \Bitrix\Main\ObjectPropertyException
		 * @throws \Bitrix\Main\SystemException
		 */
		static function init() : string {

			\ini_set('memory_limit', -1);
			\set_time_limit(0);

			// подключаем модули
			\CModule::IncludeModule('brs.report');
			\CModule::IncludeModule('brs.corporateclients');

			// генерируем отчёт
			self::generateDocumentReport();

			Option::set('brs.report', 'BRS_REPORT_CLIENTS_DATE_REFRESH', (new \DateTime())->format('d.m.Y H:i:s'), SITE_ID); // сохраняем дату последнего обновления отчёта

			return '\\Brs\\Report\\Agent\\Clients::init();';

		}

		/**
		 * Метод генерирует данные для отчета и перезаписывает таблицу
		 *
		 * @return void
		 * @throws \Bitrix\Main\ArgumentException
		 * @throws \Bitrix\Main\ObjectPropertyException
		 * @throws \Bitrix\Main\SystemException
		 */
		private static function generateDocumentReport() {

			$statusOptions = self::getStatusOptions();
			$typeOptions = \CCrmStatus::GetStatusList('CONTACT_TYPE');

			$sql = "SELECT c.ID, COUNT(d.ID) as DealsCount,
						SUM(CASE WHEN d.DATE_CREATE >= NOW() - INTERVAL 24 MONTH AND d.CATEGORY_ID <> '21' THEN 1 ELSE 0 END) as Last24Months,
						SUM(CASE WHEN d.DATE_CREATE >= NOW() - INTERVAL 12 MONTH AND d.CATEGORY_ID <> '21' THEN 1 ELSE 0 END) as Last12Months,
						SUM(CASE WHEN d.DATE_CREATE >= NOW() - INTERVAL 6 MONTH AND d.CATEGORY_ID <> '21' THEN 1 ELSE 0 END) as Last6Months,
						SUM(CASE WHEN d.DATE_CREATE >= NOW() - INTERVAL 3 MONTH AND d.CATEGORY_ID <> '21' THEN 1 ELSE 0 END) as Last3Months
						FROM b_crm_contact c
						LEFT JOIN b_crm_deal d ON c.ID = d.CONTACT_ID
						GROUP BY c.ID";

			global $DB;
			$query = $DB->Query($sql);

			$contactsWithDeals = [];

			while ($row = $query->Fetch()) {

				$linkLast3Months = '<a href="https://'.$_SERVER['SERVER_NAME'].'/crm/contact/details/'.$row['ID'].'/?tab_deal=Y&type_filter=filter_3_monts">Да</a>';
				$linkLast6Months = '<a href="https://'.$_SERVER['SERVER_NAME'].'/crm/contact/details/'.$row['ID'].'/?tab_deal=Y&type_filter=filter_6_monts">Да</a>';
				$linkLast12Months = '<a href="https://'.$_SERVER['SERVER_NAME'].'/crm/contact/details/'.$row['ID'].'/?tab_deal=Y&type_filter=filter_12_monts">Да</a>';
				$linkLast24Months = '<a href="https://'.$_SERVER['SERVER_NAME'].'/crm/contact/details/'.$row['ID'].'/?tab_deal=Y&type_filter=filter_24_monts">Да</a>';

				$contactsWithDeals[$row['ID']] = [
					'CONTACT_ID' => $row['ID'],
					'TWO_YEARS' => ($row['Last24Months'] > 0) ? $linkLast24Months : 'Нет',
					'TWELVE_MONTH' => ($row['Last12Months'] > 0) ? $linkLast12Months : 'Нет',
					'SIX_MONTH' => ($row['Last6Months'] > 0) ? $linkLast6Months : 'Нет',
					'THREE_MONTH' => ($row['Last3Months'] > 0) ? $linkLast3Months : 'Нет',
				];
			}

			$allContacts = ContactTable::getList([
				'select' => ['ID', 'NAME', 'LAST_NAME','BIRTHDATE','UF_MOM_PLACE_WORK', 'SECOND_NAME', 'UF_CRM_CONTACT_KS_ID', 'TYPE_ID', 'UF_CRM_CONTACT_STATUS', 'PHONE', 'EMAIL','UF_CRM_CONTACT_CORPORATE_CLIENT']
			])->fetchAll();

			$resultContacts = [];

			foreach ($allContacts as $contact) {

				//тип клиента по Идентификатору клиента КС
				if(empty($contact['UF_CRM_CONTACT_KS_ID'])) {
					$resultContacts[$contact['ID']]['KS_TYPE'] = 'Не заполнено';
				} elseif (preg_replace("/[^0-9]/", '', $contact['UF_CRM_CONTACT_KS_ID']) === $contact['UF_CRM_CONTACT_KS_ID']) { // если только цифры то 'Клиент банка'
					$resultContacts[$contact['ID']]['KS_TYPE'] = 'Клиент банка';
				} else { // если только цифры и буквы - 'Внешний клиент'
					$resultContacts[$contact['ID']]['KS_TYPE'] = 'Внешний клиент';
				}

				$resultContacts[$contact['ID']]['CONTACT_ID'] = $contact['ID'];
				$resultContacts[$contact['ID']]['FIO'] = '<a href="https://'.$_SERVER['SERVER_NAME'].'/crm/contact/details/'.$contact['ID'].'/">'.$contact['LAST_NAME'].' '.$contact['NAME'].' '.$contact['SECOND_NAME'].'</a>';
				$resultContacts[$contact['ID']]['KS_ID'] = $contact['UF_CRM_CONTACT_KS_ID'];
				$resultContacts[$contact['ID']]['TYPE'] = $typeOptions[$contact['TYPE_ID']];
				$resultContacts[$contact['ID']]['STATUS'] = $statusOptions[$contact['UF_CRM_CONTACT_STATUS']];
				$resultContacts[$contact['ID']]['PHONE'] = $contact['PHONE'];
				$resultContacts[$contact['ID']]['EMAIL'] = $contact['EMAIL'];
				$resultContacts[$contact['ID']]['TWELVE_MONTH'] = $contactsWithDeals[$contact['ID']]['TWELVE_MONTH'];
				$resultContacts[$contact['ID']]['THREE_MONTH'] = $contactsWithDeals[$contact['ID']]['THREE_MONTH'];
				$resultContacts[$contact['ID']]['SIX_MONTH'] = $contactsWithDeals[$contact['ID']]['SIX_MONTH'];
				$resultContacts[$contact['ID']]['TWO_YEARS'] = $contactsWithDeals[$contact['ID']]['TWO_YEARS'];
				$resultContacts[$contact['ID']]['BIRTHDATE'] = $contact['BIRTHDATE'];
				$resultContacts[$contact['ID']]['BIRTHDATE_STANDARD'] = $contact['BIRTHDATE'];
				$resultContacts[$contact['ID']]['PLACE_WORK'] = $contact['UF_MOM_PLACE_WORK'] !='' ? CorporateClientsTable::getByPrimary($contact['UF_MOM_PLACE_WORK'],['select' => ['TITLE']])->fetch()['TITLE'] : '';
				$resultContacts[$contact['ID']]['CORPORATE_CLIENT'] = $contact['UF_CRM_CONTACT_CORPORATE_CLIENT'] ? 'Да' : 'Нет';
			}

			Application::getConnection()->truncateTable(ClientsTable::getTableName());

			$resultContacts = array_chunk($resultContacts, 20000);

			foreach($resultContacts as $fragmentationResultContacts) {
				ClientsTable::addMulti($fragmentationResultContacts);
			}

		}

		/**
		 * Метод формирует список типов клиента
		 *
		 * @return array
		 */
		private static function getStatusOptions()
		{
			$enumList = \CUserFieldEnum::GetList([], [
				'USER_FIELD_NAME' => 'UF_CRM_CONTACT_STATUS',
			]);

			$options = [];
			while ($enum = $enumList->Fetch()) {
				$options[$enum['ID']] = $enum['VALUE'];
			}

			return $options;
		}
	}