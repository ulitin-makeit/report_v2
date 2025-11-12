<?php

	namespace Brs\Report\Agent;

	use Bitrix\Main\Application;
	use Bitrix\Main\Config\Option;
	use Bitrix\Main\UserTable;

	use Brs\Report\Model\Orm\SaleTable; // ОРМ таблицы отчёта
	use Brs\Report\Model\Orm\UniversalTable; // ОРМ таблицы отчёта
	use Brs\Main\Model\Orm\Crm\Deal\DealPropertyTable; // свойства сделки
	use Brs\FinancialCard\Models\RefundCardTable;
	use Brs\FinancialCard\Models\FinancialCardTable;

	/**
	 * Агент отчёта, перезаписывает данные в таблицу по нему (чтобы можно было фильтровать и список использовать на странице отчёта).
	 */
	class Sale {

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

			Option::set('brs.report', 'BRS_REPORT_SALE_DATE_REFRESH', (new \DateTime())->format('d.m.Y H:i:s'), SITE_ID); // сохраняем дату последнего обновления отчёта

			return '\\Brs\\Report\\Agent\\Sale::init();';

		}

		/**
		 * Метод заполняет таблицу отчётов.
		 * 
		 * @param array $document
		 */
		private function fillReportTable(array $document){

			// шапка документа
			$header = array();

			foreach(SaleTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header); // переворачиваем массив и ищем по ключам
			
			$agentNameToCode = array_flip(SaleTable::$codeHeaderFields); // массив соответствий названий колонок и кодов

			// очищаем таблицу
			Application::getConnection()->truncateTable(SaleTable::getTableName());

			// создаём коллекцию
			$agentCollection = SaleTable::createCollection();

			// обходим строки документа и записываем в коллекцию
			foreach($document['body'] as $row){
				
				// создаём объект отчёта
				$agent = SaleTable::createObject();
				
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

			foreach(SaleTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header);

			// принудительно обновляем универсальный отчёт
			Universal::init('changedDeal');

			// тело документа
			$bodyRows = array();

			$universalCollection = UniversalTable::getList([])->fetchAll();

			foreach($universalCollection as $universal){

				$agent = '';
				$percent = '';

				if(!empty($universal['UCHASTIYA_AGENTA_V_PRODAZHE'])){

					$fieldAgent = explode('=', $universal['UCHASTIYA_AGENTA_V_PRODAZHE']);

					$agent = $fieldAgent[0];

					$percent = '100.00';

				}

				$bodyRow = [
					$headerKeys['Номер сделки'] => $universal['NOMER_SDELKI'],
					$headerKeys['Название сделки'] => $universal['TITLE_DEAL'],
					$headerKeys['Тип'] => 'Продажа',
					$headerKeys['Дата оплаты Клиентом'] => $universal['DATA_OPLATY_KLIENTOM'],
					$headerKeys['Дата отмены операции (возврат)'] => $universal['DATA_OTMENY_OPERATSII_VOZVRAT'],
					$headerKeys['Дата возврата'] => $universal['DATA_VOZVRATA'],
					$headerKeys['Дата создания сделки'] => $universal['DATA_SOZDANIYA_SDELKI'],
					$headerKeys['Номер счёта'] => $universal['ACCOUNT_NUMBER'],
					$headerKeys['Ответственное лицо'] => $universal['OTVETSTVENNOE_LITSO'],
					$headerKeys['% участия агента в продаже'] => $percent,
					$headerKeys['Участие агента'] => $agent,
					$headerKeys['Тип клиента'] => $universal['TIP_KLIENTA'],
					$headerKeys['ID клиента'] => $universal['ID_KLIENTA'],
					$headerKeys['Тип карты'] => $universal['TIP_KARTY'],
					$headerKeys['Маркетинговый канал'] => $universal['MARKETINGOVIY_KANAL'],
					$headerKeys['Страна'] => $universal['STRANA'],
					$headerKeys['Город'] => $universal['GOROD'],
					$headerKeys['Категория'] => $universal['KATEGORIYA'],
					$headerKeys['Гостиница'] => $universal['GOSTINITSA'],
                    $headerKeys['Общее количество ночей'] => $universal['FULL_NUMBER_OF_NIGHTS'],
					$headerKeys['Партнер'] => $universal['PARTNER'],
					$headerKeys['Полное наименование поставщика'] => $universal['FULL_NAME_ORGANIZATION'],
					$headerKeys['Дата оплаты партнеру (поставщику)'] => $universal['DATA_OPLATY_PARTNERU_POSTAVSHCHIKU'],
					$headerKeys['Дата оказания услуги'] => $universal['DATE_SERVICE_PROVISION'],
					$headerKeys['Сумма продажи'] => $universal['SUMMA_PRODAZHI_VSEGO_K_OPLATE_KLIENTOM'],
					$headerKeys['Прибыль'] => $universal['PRIBYL_SERVISNYY_SBOR_KOMISSIYA_DOPOLNITELNAYA_VYGODA'],
					$headerKeys['Прибыль без НДС'] => $universal['PRIBYL_BEZ_NDS_RAZMER_NDS'],
					$headerKeys['Комиссия ПАРТНЕРА'] => $universal['COMMISION_SUPPLIER_CURRENCY'],
					$headerKeys['Дополнительная выгода'] => $universal['DOPOLNITELNAYA_VYGODA'],
					$headerKeys['Сервисный сбор'] => $universal['SERVISNYY_SBOR'],
					$headerKeys['SR'] => $universal['SR'],
					$headerKeys['LR'] => $universal['LR'],
					$headerKeys['Баллы MR'] => $universal['BALLY_MR'],
					$headerKeys['Баллы IMP'] => $universal['BALLY_IMP'],
					$headerKeys['Безналичный расчет'] => $universal['BEZNAL'],
					$headerKeys['Наличные'] => '',
					$headerKeys['Карта'] => $universal['KARTA'],
					$headerKeys['Сертификат'] => $universal['SERTIFIKAT'],
					$headerKeys['Убыток на компанию'] => $universal['UBYTOK_NA_KOMPANIYU'],
					$headerKeys['Убыток на сотрудника'] => $universal['UBYTOK_NA_SOTRUDNIKA'],
					$headerKeys['Сумма TID'] => $universal['SUMMA_TID'],
					$headerKeys['Канал связи'] => $universal['KANAL_SVYAZI'],
					$headerKeys['Тип запроса'] => $universal['TYPE_REQUEST'],
					$headerKeys['Статус сделки'] => $universal['STATUS_SDELKI'],
					$headerKeys['Результат сделки'] => $universal['REZULTAT_SDELKI'],
					$headerKeys['Связанные сделки'] => $universal['BIND_DEAL'],
					$headerKeys['Лид'] => $universal['LEAD_ID'],
					$headerKeys['Тур'] => $universal['TOUR'],
					$headerKeys['Нетто в рублях'] => $universal['NET_RUBLES'],
					$headerKeys['Причина стадии Сделка проиграна'] => $universal['PRICHINA_STADII_SDELKA_PROIGRANA'],
					$headerKeys['Цепочка'] => $universal['TSEPOCHKA'],
					$headerKeys['Валюта сделки'] => $universal['CURRENCY'],
					$headerKeys['Дата создания фин.карты'] => $universal['DATA_SOZDANIYA_FIN_KARTY'],
					$headerKeys['Клиент'] => $universal['KLIENT'],
					$headerKeys['Курс оплаты'] => $universal['RATE_PAYMENT'],
					$headerKeys['Курс оплаты ЦБ'] => $universal['RATE_PAYMENT_CENTRAL_BANK'],
					$headerKeys['Статус карты возврата'] => $universal['STATUS_CARD_REFUND'],
					$headerKeys['Схема финансовой карты'] => $universal['FINANCIAL_CARD_SCHEME_WORK'],
					$headerKeys['Дата отложенной оплаты'] => $universal['DEFERRED_DATE_ACTIVE_FINISH'],
					$headerKeys['Валюта отложенной оплаты'] => $universal['DEFERRED_CURRENCY'],
					$headerKeys['Сумма отложенной оплаты, руб'] => $universal['DEFERRED_AMOUNT'],
					$headerKeys['Сумма отложенной оплаты, валюта'] => $universal['DEFERRED_AMOUNT_CURRENCY'],
					$headerKeys['Тип оплаты'] => $universal['TYPE_PAYMENT'],
					$headerKeys['Итого оплачено клиентом'] => $universal['TOTAL_PAID_CLIENT'],
					$headerKeys['Нетто в Валюте поставщика'] => $universal['NET_SUPPLIER_CURRENCY'],
					$headerKeys['Брутто в Валюте поставщика'] => $universal['GROSS_SUPPLIER_CURRENCY'],
					$headerKeys['Комиссия поставщика в Валюте'] => $universal['COMMISION_SUPPLIER_CURRENCY'],
					$headerKeys['Сумма возврата клиенту'] => $universal['REFOUND_AMOUNT_CLIENT'],
					$headerKeys['Дата начала'] => $universal['DATE_START'],
					$headerKeys['Дата завершения'] => $universal['DATE_CLOSE'],
					$headerKeys['Сбор РС ТЛС за возврат'] => $universal['PRODUCTS_FEE_REFOUND'],
					$headerKeys['Средний курс для возврата'] => $universal['AVERAGE_RATE'],
				];

				$isModifiedRowAgent = false;
				$isAddRefund = false;

				$isAddCorrection = self::splitDealOfCorrection($bodyRows, $bodyRow, $headerKeys); // разбиваем строку по коррекциям

				if(!empty($universal['DATA_VOZVRATA'])){
					$isAddRefund = self::splitDealOfRefund($bodyRows, $bodyRow, $headerKeys); // разбиваем строку по возвратам
				}

				if(!$isAddCorrection && !$isAddRefund){
					$isModifiedRowAgent = self::splitDealOfAgent($bodyRows, $bodyRow, $headerKeys); // разбиваем строку по каждому из агентов
				}

				if(!$isModifiedRowAgent && !$isAddCorrection && !$isAddRefund){

					ksort($bodyRow);

					$bodyRows[] = $bodyRow;

				}

			}

			return array(
				'header' => $header,
				'body' => $bodyRows,
			);

		}

		/**
		 * Разбивает строку (сделку) по агентам.
		 * 
		 * @return array
		 */
		private function splitDealOfAgent(array &$bodyRows, array $bodyRow, array $headerKeys, string $type = 'Агент'): bool {

			$financialFieldList = SaleTable::$financialFields; // получаем список кодов финансовых полей

			$financialFieldCodeList = [];

			foreach($financialFieldList as $financialFieldCode){
				$financialFieldCodeList[$financialFieldCode] = SaleTable::$codeHeaderFields[$financialFieldCode];
			}

			// получаем агентов из инфоблока
			$agents = \CIblockElement::GetList([], [
				'=IBLOCK_ID' => PARTICIPATION_AGENT_IBLOCK_ID,
				'=PROPERTY_DEAL' => $bodyRow[$headerKeys['Номер сделки']]
			], false, false, ['NAME', 'PROPERTY_AGENT', 'PROPERTY_DEAL', 'PROPERTY_PERCENT_PARTICIPATION']);

			$percentAgentsSum = [];
			$userAgentIds = [];

			// обходим всех агентов и высчитываем сумму процентов
			while($agent = $agents->Fetch()){

				$userAgentIds[] = $agent['PROPERTY_AGENT_VALUE'];

				$percentAgentsSum[$agent['PROPERTY_AGENT_VALUE']] = $agent['PROPERTY_PERCENT_PARTICIPATION_VALUE'];

			}

			$percentAgentList = [];

			if(count($userAgentIds) > 0){

				$userAgents = UserTable::getList([
					'filter' => [
						'ID' => $userAgentIds
					]
				]);

				if($userAgents->getSelectedRowsCount() > 0){

					while($agent = $userAgents->Fetch()){
						$percentAgentList[trim(implode(' ', [ $agent['LAST_NAME'], $agent['NAME'] ]))] = (float) $percentAgentsSum[$agent['ID']];
					}

				}

			}

			// формируем строки на вывод
			foreach($percentAgentList as $percentAgent => $percent){

				$row = $bodyRow;

				$row[$headerKeys['% участия агента в продаже']] = $percent;
				$row[$headerKeys['Участие агента']] = $percentAgent;
				$row[$headerKeys['Тип']] = $type;

				foreach($financialFieldCodeList as $financialFieldRu){

					$row[$headerKeys[$financialFieldRu]] = (float) str_replace(',', '.', $row[$headerKeys[$financialFieldRu]]);

					$row[$headerKeys[$financialFieldRu]] = number_format($row[$headerKeys[$financialFieldRu]]/100*$percent, 4, ',', '');

				}

				ksort($row);
				$bodyRows[] = $row;

			}

			if(count($percentAgentList) > 0){
				return true;
			} else {
				return false;
			}

		}

		/**
		 * Считаем строки с возвратами и разбиваем их на агентов.
		 * 
		 * @param array $bodyRows
		 * @param array $bodyRow
		 * @param array $headerKeys
		 * 
		 * @return bool
		 */
		private function splitDealOfRefund(array &$bodyRows, array $bodyRow, array $headerKeys): bool {

			$refundRow = $bodyRow;

			$refundRow[$headerKeys['Тип']] = 'Возврат';

			// получаем возвраты
			$refund = RefundCardTable::getList([
				'filter' => [
					'DEAL_ID' => $bodyRow[$headerKeys['Номер сделки']]
				],
				'order' => [
					'ID' => 'DESC'
				]
			]);

			// если были найдены возвраты
			if($refund->getSelectedRowsCount() > 0){

				$columnNameList = ['Сумма продажи', 'Прибыль', 'Прибыль без НДС', 'Нетто в рублях', 'Баллы MR', 'Баллы IMP', 'Безналичный расчет'];

				$refund = $refund->fetchObject();

				$returnCash = ($refund->getReturnCash()*-1); // сумма продажи из суммы возврата клиенту
				$returnSum = $refund->getReturnSum(); // сумма возврата
				$returnSpliter = $refund-> getReturnSupplier();// сумма возврата поставщику

				$refundRow[$headerKeys['Сумма продажи']] = $returnCash;

				if(in_array($bodyRow[$headerKeys['Схема финансовой карты']], [ 'Оказание услуг', 'Агент покупателя', 'Сервисный сбор' ])){
					$refundRow[$headerKeys['Прибыль']] = $returnCash + $returnSpliter * str_replace(',','.', $bodyRow[$headerKeys['Курс оплаты ЦБ']]) ;
				} else if(in_array($bodyRow[$headerKeys['Схема финансовой карты']], [ 'Агент Поставщика&nbsp;SR', 'Агент Поставщика&nbsp;LR' ])) {
					$refundRow[$headerKeys['Прибыль']] = $returnCash + $refund->getReturnSupplier();
				}

				if($bodyRow[$headerKeys['Схема финансовой карты']] === 'Оказание услуг'){
					$refundRow[$headerKeys['Прибыль без НДС']] = $refundRow[$headerKeys['Прибыль']];
				} else {
					$refundRow[$headerKeys['Прибыль без НДС']] = $refundRow[$headerKeys['Прибыль']]/1.2;
				}

				$refundRow[$headerKeys['Нетто в рублях']] = $refundRow[$headerKeys['Нетто в рублях']] + $refundRow[$headerKeys['Сумма продажи']];

				if(!empty($refundRow[$headerKeys['Баллы MR']])){
					$refundRow[$headerKeys['Баллы MR']] = $returnSum;
				} else if(!empty($refundRow[$headerKeys['Баллы IMP']])){
					$refundRow[$headerKeys['Баллы IMP']] = $returnSum;
				}
//				else {
//					$refundRow[$headerKeys['Безналичный расчет']] = $returnSum;
//				}

				foreach($columnNameList as $columnName){
					$refundRow[$headerKeys[$columnName]] = number_format($refundRow[$headerKeys[$columnName]], 2, '.', '');
				}

				$total = $bodyRow[$headerKeys['Итого оплачено клиентом']];

				$bodyRow[$headerKeys['Итого оплачено клиентом']] = $bodyRow[$headerKeys['Сумма продажи']]; // полная сумма продажи для разбиения по агентам

				self::splitDealOfAgent($bodyRows, $bodyRow, $headerKeys, 'Продажа по агенту'); // разбиваем строку продаж по каждому из агентов
				self::splitDealOfAgent($bodyRows, $refundRow, $headerKeys, 'Возврат по агенту'); // разбиваем строку возвратов по каждому из агентов

				return true;

			} else {
				return false;
			}

		}

		/**
		 * Считаем строки с коррекциями и разбиваем их на агентов.
		 * 
		 * @param array $bodyRows
		 * @param array $bodyRow
		 * @param array $headerKeys
		 * 
		 * @return bool
		 */
		private function splitDealOfCorrection(array &$bodyRows, array $bodyRow, array $headerKeys): bool {

			// получаем коррекции
			$correctionList = FinancialCardTable::getList([
				'select' => [
					'ID', 'DEAL_ID', 'PRICE_' => 'FINANCIAL_CARD_PRICE.*'
				],
				'filter' => [
					'CORRECTION_DEAL_ID' => $bodyRow[$headerKeys['Номер сделки']],
				],
				'order' => [
					'ID' => 'DESC'
				]
			]);

			// если были найдены карты коррекции
			if($correctionList->getSelectedRowsCount() == 0){
				return false;
			}

			// получаем коррекции
			$financialCard = FinancialCardTable::getList([
				'select' => [
					'ID', 'DEAL_ID', 'PRICE_' => 'FINANCIAL_CARD_PRICE.*'
				],
				'filter' => [
					'DEAL_ID' => $bodyRow[$headerKeys['Номер сделки']],
				],
				'order' => [
					'ID' => 'DESC'
				]
			])->fetch();

			$columnNameList = ['Сумма продажи', 'Прибыль', 'Прибыль без НДС', 'Нетто в рублях', 'Баллы MR', 'Баллы IMP', 'Безналичный расчет', 'Комиссия ПАРТНЕРА', 'Сервисный сбор'];

			$correctionList = $correctionList->fetchAll();

			// формируем строки коррекций
			foreach($correctionList as $correction){

				$correctionRow = $bodyRow;

				$isTypePriceFinancialCard = 'equal'; // по умолчанию совпадают суммы в финансовой карте с картой коррекции
				
				// проверяем, совпадают ли суммы в финансовой карте с картой коррекции
				if($correction['PRICE_RESULT'] > $financialCard['PRICE_RESULT']){
					$isTypePriceFinancialCard = 'additionalPayment'; // доплата
				} else if($correction['PRICE_RESULT'] < $financialCard['PRICE_RESULT']){
					$isTypePriceFinancialCard = 'refund'; // частичный возврат
				}

				$correctionRow[$headerKeys['Тип']] = 'Коррекция';

				$netto = $correction['PRICE_SUPPLIER_TOTAL_PAID']; // нетто в рублях
				$totalPaidClient = $correction['PRICE_RESULT']; // всего к оплате клиенту
				$service = $correction['PRICE_SERVICE']; // сервисный сбор

				if($correctionRow[$headerKeys['Валюта сделки']] != 'RUB'){

					$rate = (float) str_replace(',', '.', $correctionRow[$headerKeys['Курс оплаты']]);

					$netto = $netto*$rate; // получаем нетто в рублях из поля оплата поставщику
					
					// если есть сбор поставщика
					if($correction['PRICE_SUPPLIER'] > 0){
						$netto += $correction['PRICE_SUPPLIER']*$rate; // прибавляем сбор поставщика
					}

					// если есть дополнительная выгода, то значит в этой схеме именно она в поле "COMISSION"
					if($bodyRow[$headerKeys['Дополнительная выгода']] > 0){

						$additionalBenefit = $correction['PRICE_COMMISSION']*$rate;

						$comission = 0;

					} else {

						$additionalBenefit = 0; // дополнительная выгода

						$comission = $correction['PRICE_COMMISSION']*$rate;

					}

					$service = $service*$rate;

				} else {
					
					// если есть сбор поставщика
					if($correction['PRICE_SUPPLIER'] > 0){
						$netto += $correction['PRICE_SUPPLIER']; // прибавляем сбор поставщика
					}

					// если есть дополнительная выгода, то значит в этой схеме именно она в поле "COMISSION"
					if($bodyRow[$headerKeys['Дополнительная выгода']] > 0){

						$additionalBenefit = $correction['PRICE_COMMISSION'];

						$comission = 0;

					} else {

						$additionalBenefit = 0;

						$comission = $correction['PRICE_COMMISSION'];

					}

				}

				$correctionRow[$headerKeys['Сумма продажи']] = $totalPaidClient;
				$correctionRow[$headerKeys['Прибыль']] = $totalPaidClient - $netto;

				if($bodyRow[$headerKeys['Схема финансовой карты']] === 'Оказание услуг'){
					$correctionRow[$headerKeys['Прибыль без НДС']] = $correctionRow[$headerKeys['Прибыль']];
				} else {
					$correctionRow[$headerKeys['Прибыль без НДС']] = $correctionRow[$headerKeys['Прибыль']]/1.2;
				}

				$correctionRow[$headerKeys['Комиссия ПАРТНЕРА']] = $comission - $bodyRow[$headerKeys['Комиссия ПАРТНЕРА']];
				$correctionRow[$headerKeys['Дополнительная выгода']] = $additionalBenefit - $bodyRow[$headerKeys['Дополнительная выгода']];
				$correctionRow[$headerKeys['Сервисный сбор']] = $service;

				if(str_replace('SR', '', $bodyRow[$headerKeys['Схема финансовой карты']]) != $bodyRow[$headerKeys['Схема финансовой карты']]){
					$correctionRow[$headerKeys['SR']] = $totalPaidClient;
				} else if(str_replace('LR', '', $bodyRow[$headerKeys['Схема финансовой карты']]) != $bodyRow[$headerKeys['Схема финансовой карты']]){
					$correctionRow[$headerKeys['LR']] = $totalPaidClient;
				}

				if(!empty($correctionRow[$headerKeys['Баллы MR']])){
					$correctionRow[$headerKeys['Баллы MR']] = $totalPaidClient;
				} else if(!empty($correctionRow[$headerKeys['Баллы IMP']])){
					$correctionRow[$headerKeys['Баллы IMP']] = $totalPaidClient;
				}
//				else {
//					$correctionRow[$headerKeys['Безналичный расчет']] = $totalPaidClient;
//				}

				foreach($columnNameList as $columnName){
					$correctionRow[$headerKeys[$columnName]] = number_format($correctionRow[$headerKeys[$columnName]], 2, '.', '');
				}

				if($isTypePriceFinancialCard === 'refund'){
					self::splitDealOfRefund($bodyRows, $correctionRow, $headerKeys); // разбиваем строку коррекций по каждому из агентов
				} else {

					$isSplit = self::splitDealOfAgent($bodyRows, $correctionRow, $headerKeys, 'Коррекция по агентам'); // разбиваем строку коррекций по каждому из агентов

					if($isSplit){ // если разбили коррекцию по агентам, то не добавляем строку с коррекцией
						continue;
					}

				}

				ksort($correctionRow);

				$bodyRows[] = $correctionRow;

			}

			return true;

		}

	}