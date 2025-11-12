<?php

	namespace Brs\Report\Agent;

	use Bitrix\Main\Application;
	use Bitrix\Main\Config\Option;

	use Brs\Report\Model\Orm\AgentTable; // ОРМ таблицы отчёта
	use Brs\Report\Model\Orm\UniversalTable; // ОРМ таблицы отчёта
	use Brs\Main\Model\Orm\Crm\Deal\DealPropertyTable; // свойства сделки

	/**
	 * Агент отчёта, перезаписывает данные в таблицу по нему (чтобы можно было фильтровать и список использовать на странице отчёта).
	 */
	class Agent {

		static array $headerCodes; // содержит массив соответствий

		/*
		 * Метод инициализирует перезапись отчёта в таблице.
		 * 
		 * @param string $typeRefresh
		 * @return string
		 */
		static function init(string $typeRefresh = 'all') : string {
			
			\ini_set('memory_limit', -1);
			\set_time_limit(0);

			// подключаем модули
			\CModule::IncludeModule('crm');
			\CModule::IncludeModule('brs.report');
			\CModule::IncludeModule('brs.financialcard');
			\CModule::IncludeModule('brs.incomingpaymentecomm');

			// генерируем сам отчёт
			$document = self::generateDocumentReport($typeRefresh);

			self::fillReportTable($document);

			Option::set('brs.report', 'BRS_REPORT_AGENT_DATE_REFRESH', (new \DateTime())->format('d.m.Y H:i:s'), SITE_ID); // сохраняем дату последнего обновления отчёта

			return '\\Brs\\Report\\Agent\\Agent::init();';

		}

		/**
		 * Метод заполняет таблицу отчётов.
		 * 
		 * @param array $document
		 */
		private function fillReportTable(array $document){

			// шапка документа
			$header = array();

			foreach(AgentTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header); // переворачиваем массив и ищем по ключам
			
			$agentNameToCode = array_flip(AgentTable::$codeHeaderFields); // массив соответствий названий колонок и кодов

			// очищаем таблицу
			Application::getConnection()->truncateTable(AgentTable::getTableName());

			// создаём коллекцию
			$agentCollection = AgentTable::createCollection();

			// обходим строки документа и записываем в коллекцию
			foreach($document['body'] as $row){
				
				// создаём объект отчёта
				$agent = AgentTable::createObject();
				
				// получаем и заполняем идентификатор сделки
				$agent->setDealId((int) str_replace('Сделка №', '', $row[$headerKeys['Номер сделки']]));

				// обходим данные в строке и заполняем ORM объект
				foreach($row as $columnId => $columnValue){

					if($agentNameToCode[$header[$columnId]] == 'DATA_VYLETA' && !empty($columnValue)){
						$columnValue = explode(', ', $columnValue);
					}

					if(!empty($columnValue)){
						$agent->set($agentNameToCode[$header[$columnId]], $columnValue);
					}

				}

				$agentCollection->add($agent); // добавляем сформированный объект в коллекцию

			}

			@$agentCollection->save(); // сохраняем коллекцию

		}

		/**
		 * Метод формирует заголовок и тело документа (отчёта).
		 * 
		 * @param string $typeRefresh
		 * @return array header,body
		 */
		private static function generateDocumentReport(){

			// подключаем класс компонента расчёта финансовых карточек
			\CBitrixComponent::includeComponentClass('brs.financialcard:financial-card.calc');

			// получаем путь к формулам финансовой карточки
			$pathFinancialCardFormulas = str_replace('class.php', 'formulas/', (new \ReflectionClass(\FinancialCalcComponent::class))->getFileName());

			// шапка документа
			$header = array();

			foreach(AgentTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header);

			// принудительно обновляем агентский отчёт
			Universal::init('changedDeal');

			// тело документа
			$bodyRows = array();

			$universalCollection = UniversalTable::getList([])->fetchCollection();

			foreach($universalCollection as $universal){

				$bodyRow = [
					$headerKeys['Номер сделки'] => $universal->getDealId(),
					$headerKeys['Название сделки'] => $universal->getTitleDeal(),
					$headerKeys['Категория'] => $universal->get('KATEGORIYA'),
					$headerKeys['Ответственное лицо'] => $universal->get('OTVETSTVENNOE_LITSO'),
					$headerKeys['Комментарий Тимлидеру'] => $universal->get('COMMENT_TEAMLEADER'),
					$headerKeys['ID клиента'] => $universal->get('ID_KLIENTA'),
					$headerKeys['Связанные сделки'] => $universal->get('BIND_DEAL'),
					$headerKeys['Лид'] => $universal->get('LEAD_ID'),
					$headerKeys['Город'] => $universal->get('GOROD'),
					$headerKeys['Страна'] => $universal->get('STRANA'),
					$headerKeys['Результат сделки'] => $universal->get('REZULTAT_SDELKI'),
					$headerKeys['Статус сделки'] => $universal->get('STATUS_SDELKI'),
					$headerKeys['Тип клиента'] => $universal->get('TIP_KLIENTA'),
					$headerKeys['Клиент'] => $universal->get('KLIENT'),
					$headerKeys['Партнёр'] => $universal->get('PARTNER'),
					$headerKeys['Дата оказания услуги'] => $universal->get('DATE_SERVICE_PROVISION'),
					$headerKeys['Тип оплаты'] => $universal->get('TYPE_PAYMENT'),
					$headerKeys['Дата оплаты Клиентом'] => $universal->get('DATA_OPLATY_KLIENTOM'),
					$headerKeys['Итого оплачено Клиентом'] => $universal->get('TOTAL_PAID_CLIENT'),
					$headerKeys['Дата создания фин.карты'] => $universal->get('DATA_SOZDANIYA_FIN_KARTY'),
					$headerKeys['Полное наименование организации'] => $universal->get('FULL_NAME_ORGANIZATION'),
					$headerKeys['Сумма продажи'] => $universal->get('SUMMA_PRODAZHI_VSEGO_K_OPLATE_KLIENTOM'),
					$headerKeys['Оплата поставщику'] => $universal->get('PAYMENT_SUPPLIER'),
					$headerKeys['Нетто в валюте поставщика'] => $universal->get('NET_SUPPLIER_CURRENCY'),
					$headerKeys['Нетто в рублях'] => $universal->get('NET_RUBLES'),
					$headerKeys['Прибыль'] => $universal->get('PRIBYL_SERVISNYY_SBOR_KOMISSIYA_DOPOLNITELNAYA_VYGODA'),
					$headerKeys['Дата отмены операции'] => $universal->get('DATA_OTMENY_OPERATSII_VOZVRAT'),
					$headerKeys['Статус карты возврата'] => $universal->get('STATUS_CARD_REFUND'),
					$headerKeys['Сумма возврата клиентом'] => $universal->get('REFOUND_AMOUNT_CLIENT'),
					$headerKeys['Прибыль РС ТЛС с учетом возврата'] => $universal->get('PROFIT_RSTLS_REFOUND'),
					$headerKeys['Дата создания сделки'] => $universal->get('DATA_SOZDANIYA_SDELKI'),
					$headerKeys['Участие агента'] => $universal->get('UCHASTIYA_AGENTA_V_PRODAZHE'),
					$headerKeys['Кросс-продажа'] => $universal->get('IS_CROSS_SELLING'),
					$headerKeys['Кросс-продажа причина'] => $universal->get('CROSS_SELLING_REASON'),
					$headerKeys['Дата отложенной оплаты'] => $universal->get('DEFERRED_DATE_ACTIVE_FINISH'),
					$headerKeys['Валюта отложенной оплаты'] => $universal->get('DEFERRED_CURRENCY'),
					$headerKeys['Сумма отложенной оплаты, руб'] => $universal->get('DEFERRED_AMOUNT'),
					$headerKeys['Сумма отложенной оплаты, валюта'] => $universal->get('DEFERRED_AMOUNT_CURRENCY'),
					$headerKeys['Дата начала'] => $universal->get('DATE_START'),
					$headerKeys['Дата окончания'] => $universal->get('DATE_FINISH'),
				];

				ksort($bodyRow);
				
				$bodyRows[$universal->getDealId()] = $bodyRow;

			}

			return array(
				'header' => $header,
				'body' => $bodyRows,
			);

		}

	}