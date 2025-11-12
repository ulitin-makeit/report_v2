<?php

	namespace Brs\Report\Agent;

	use Bitrix\Main\Application;
	use Bitrix\Main\Config\Option;
	use Bitrix\Main\UserTable;
	use Brs\FinancialCard\Models\FinancialCardTable;
	use Brs\Main\Model\Orm\Crm\Deal\DealPropertyTable;
	use Brs\Report\Model\Orm\ForeignCardTable;
	use Brs\Report\Model\Orm\UniversalTable;

	// ОРМ таблицы отчёта
	// свойства сделки
	// ОРМ таблицы отчёта



/**
 * Агент отчёта, перезаписывает данные в таблицу по нему (чтобы можно было фильтровать и список использовать на странице отчёта).
 */
	class ForeignCard {

		// Схема работы фин карты
		static array $financialCardSchemeWork = [
			FinancialCardTable::SCHEME_BUYER_AGENT => 'Агент покупателя',
			FinancialCardTable::SCHEME_SR_SUPPLIER_AGENT => 'Агент Поставщика&nbsp;SR',
			FinancialCardTable::SCHEME_LR_SUPPLIER_AGENT => 'Агент Поставщика&nbsp;LR',
			FinancialCardTable::SCHEME_PROVISION_SERVICES => 'Оказание услуг',
			FinancialCardTable::SCHEME_RS_TLS_SERVICE_FEE => 'Сервисный сбор РС&nbsp;ТЛС'
		];

		static array $nds = array(
			'VAT_10' => 'CalculatedVat10110', // налог на добавленную стоимость (НДС) 10%;
			'VAT_20' => 'CalculatedVat20120', // НДС 20%
			'VAT_0' => 0, // НДС 0%;
			'VAT_NO' => 0, // НДС не облагается;
			'VAT_10_110' => 'CalculatedVat10110', // вычисленный НДС 10% от 110% суммы;
			'VAT_18_118' => 'CalculatedVat18118', // вычисленный НДС 18% от 118% суммы;
			'VAT_20_120' => 'CalculatedVat20120'
		);

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
			\CModule::IncludeModule('brs.currency');
			\CModule::IncludeModule('brs.report');
			\CModule::IncludeModule('brs.financialcard');
			\CModule::IncludeModule('brs.incomingpaymentecomm');

			// генерируем сам отчёт
			$document = self::generateDocumentReport($typeRefresh);

			if($typeRefresh == 'all'){ // заполняем таблицу (стираем все предыдущие данные и заполняем полностью)
				self::fillReportTable($document); 
			} else { // изменяем в таблице только те сделки, которые были изменены или добавлены
				self::updateReportTable($document);
			}

		    Option::set('brs.report', 'BRS_REPORT_FOREIGN_CARD_REFRESH', (new \DateTime())->format('d.m.Y H:i:s'), SITE_ID); // сохраняем дату последнего обновления отчёта

			return '\\Brs\\Report\\Agent\\ForeignCard::init('.($typeRefresh != 'all') ? '\''.$typeRefresh.'\'' : ''.');';

		}

		/**
		 * Метод заполняет таблицу отчётов.
		 *
		 * @param array $document
		 */
		private function fillReportTable(array $document){

			// шапка документа
			$header = array();

			foreach(ForeignCardTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header); // переворачиваем массив и ищем по ключам

			$ForeignCardNameToCode = array_flip(ForeignCardTable::$codeHeaderFields); // массив соответствий названий колонок и кодов

			// очищаем таблицу
			Application::getConnection()->truncateTable(ForeignCardTable::getTableName());

			$columnList = Application::getConnection()->getTableFields(ForeignCardTable::getTableName());

			$columnDateList = [];

			foreach($columnList as $name => $column){

				if($column instanceof \Bitrix\Main\ORM\Fields\DatetimeField){
					$columnDateList[$name] = 'datetime';
				} else if($column instanceof \Bitrix\Main\ORM\Fields\DateField){
					$columnDateList[$name] = 'date';
				}

			}

			$dataListMulti = [];

			$split = 0;
			$splitIndex = 0;

			foreach($document['body'] as $row){

				if($splitIndex == 4000){

					$split++;

					$splitIndex = 0;

				}

				$data = [];

				$data['DEAL_ID'] = $row[$headerKeys['Номер сделки']];

				// обходим данные в строке и заполняем ORM объект
				foreach($row as $columnId => $columnValue){

					if(empty($columnValue)){
						$columnValue = '';
					} else if(array_key_exists($ForeignCardNameToCode[$header[$columnId]], $columnDateList)) {
						$columnValue = new \Bitrix\Main\Type\DateTime($columnValue);
					} else if($ForeignCardNameToCode[$header[$columnId]] == 'DATA_VYLETA' && !empty($columnValue)){
						$columnValue = explode(', ', $columnValue);
					}

					$data[$ForeignCardNameToCode[$header[$columnId]]] = $columnValue;

				}

				$dataListMulti[$split][] = $data;

				$splitIndex++;

			}

			foreach($dataListMulti as $dataList){
				Application::getConnection()->addMulti(ForeignCardTable::getTableName(), $dataList);
			}

		}

		/**
		 * Метод изменяет в таблице отчёта только те сделки, котороые были изменены или добавлены.
		 *
		 * @param array $document
		 */
		private function updateReportTable(array $document){

			if(count($document['dealIdList']) == 0){
				return;
			}

			$foreignCardCollection = ForeignCardTable::getList([
				'filter' => [
					'DEAL_ID' => $document['dealIdList']
				]
			]);

			if($foreignCardCollection->getSelectedRowsCount() == 0){
				return;
			}

			// шапка документа
			$header = array();

			foreach(ForeignCardTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header); // переворачиваем массив и ищем по ключам

			$ForeignCardNameToCode = array_flip(ForeignCardTable::$codeHeaderFields); // массив соответствий названий колонок и кодов

			// получаем коллекцию строк в отчёте, которые нужно изменить
			$foreignCardCollection = $foreignCardCollection->fetchCollection();

			foreach($foreignCardCollection as $universal){

				$document['dealIdList'] = array_diff($document['dealIdList'], [$universal->getDealId()]);

				$row = $document['body'][$universal->getDealId()];

				// обходим данные в строке и заполняем ORM объект
				foreach($row as $columnId => $columnValue){

					if($ForeignCardNameToCode[$header[$columnId]] == 'DATA_VYLETA' && !empty($columnValue)){
						$columnValue = explode(', ', $columnValue);
					}

					$universal->set($ForeignCardNameToCode[$header[$columnId]], $columnValue);

				}

				$foreignCardCollection->add($universal); // добавляем сформированный объект в коллекцию

			}

			@$foreignCardCollection->save(); // сохраняем коллекцию

			// создаём коллекцию
			$foreignCardCollection = ForeignCardTable::createCollection();

			// обходим строки документа и записываем в коллекцию
			foreach($document['dealIdList'] as $dealId){

				$row = $document['body'][$dealId];

				$dealId = (int) str_replace('Сделка №', '', $row[$headerKeys['Номер сделки']]);

				// создаём объект отчёта
				$universal = ForeignCardTable::createObject();

				// получаем и заполняем идентификатор сделки
				$universal->setDealId((int) str_replace('Сделка №', '', $row[$headerKeys['Номер сделки']]));

				// обходим данные в строке и заполняем ORM объект
				foreach($row as $columnId => $columnValue){

					if($ForeignCardNameToCode[$header[$columnId]] == 'DATA_VYLETA' && !empty($columnValue)){
						$columnValue = explode(', ', $columnValue);
					}

					$universal->set($ForeignCardNameToCode[$header[$columnId]], $columnValue);

				}

				$foreignCardCollection->add($universal); // добавляем сформированный объект в коллекцию

			}

			@$foreignCardCollection->save(); // сохраняем коллекцию

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

			foreach(ForeignCardTable::$codeHeaderFields as $code => $ruLang){
				$header[] = $ruLang;
			}

			$headerKeys = array_flip($header);

			// принудительно обновляем отчёт
			Universal::init('changedDeal');
			// тело документа
			$bodyRows = array();

			$foreignCardCollection = UniversalTable::getList([
				'filter'=>[
					'KATEGORIYA' => ["Консультация по картам МБ", "Консультация по картам БК"]
				]
			])->fetchAll();

			foreach($foreignCardCollection as $universal){

				$bodyRows[] = [
					$headerKeys['Номер сделки'] => $universal['NOMER_SDELKI'],
					$headerKeys['Название сделки'] => $universal['TITLE_DEAL'],
					$headerKeys['Менеджер'] => $universal['MANAGER'],
					$headerKeys['Дата оплаты Клиентом'] => $universal['DATA_OPLATY_KLIENTOM'],
					$headerKeys['Дата отмены операции (возврат)'] => $universal['DATA_OTMENY_OPERATSII_VOZVRAT'],
					$headerKeys['Дата возврата'] => $universal['DATA_VOZVRATA'],
					$headerKeys['Дата создания сделки'] => $universal['DATA_SOZDANIYA_SDELKI'],
					$headerKeys['Номер счёта'] => $universal['ACCOUNT_NUMBER'],
					$headerKeys['Ответственное лицо'] => $universal['OTVETSTVENNOE_LITSO'],
					$headerKeys['% участия агента в продаже'] => $universal['UCHASTIYA_AGENTA_V_PRODAZHE'],
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
					$headerKeys['Кросс-продажа'] => $universal['IS_CROSS_SELLING'],
					$headerKeys['Кросс-продажа причина'] => $universal['CROSS_SELLING_REASON'],
					$headerKeys['Количество номеров'] => $universal['NUMBER_ROOMS'],
					$headerKeys['Дата начала'] => $universal['DATE_START'],
					$headerKeys['Дата окончания'] => $universal['DATE_FINISH'],
					$headerKeys['Дата завершения'] => $universal['DATE_CLOSE'],
					$headerKeys['Создатель карты'] => $universal['SOZDATEL_KARTY'],
					$headerKeys['Количество броней (1)'] =>$universal['KOLICHESTVO_BRONEY_1'], // пока всегда 1
					$headerKeys['Тип брони'] => $universal['TIP_BRONI'],
					$headerKeys['Ресторан'] => $universal['RESTAURANT'],
					$headerKeys['Полное наименование организации'] => $universal['FULL_NAME_ORGANIZATION'],
					$headerKeys['Дата заезда'] => $universal['DATA_ZAEZDA'],
					$headerKeys['Дата выезда'] => $universal['DATA_VYEZDA'],
					$headerKeys['Количество ночей'] => $universal['KOLICHESTVO_NOCHEY'],
					$headerKeys['Оплата поставщику'] => $universal['PAYMENT_SUPPLIER'],
					$headerKeys['Сумма возврата поставщиком'] => $universal['REFOUND_AMOUNT_SUPPLIER'],
					$headerKeys['Штраф от поставщика'] => $universal['FINE_SUPPLIER'],
					$headerKeys['Сбор поставщика на возврат'] =>$universal ['SUPPLIER_FEE_REFOUND'],
					$headerKeys['Продукты за сбор возврата'] => $universal['PRODUCTS_FEE_REFOUND'],
					$headerKeys['Штраф клиенту РС ТЛС'] => $universal['FINE_CLIENT_RSTLS'],
					$headerKeys['Возврат сбора РС ТЛС'] =>$universal['RSTLS_FEE_REFOUND'],
					$headerKeys['Остаток сбора РС ТЛС'] =>$universal ['REMAINDER_COLLECTION_RSTLS'],
					$headerKeys['Прибыль РС ТЛС с учетом возврата'] => $universal['PROFIT_RSTLS_REFOUND'],
					$headerKeys['Удержал поставщик'] =>$universal ['WITHHELD_SUPPLIER'],
					$headerKeys['Сумма возврата клиенту'] => $universal['REFOUND_AMOUNT_CLIENT'],
					$headerKeys['Сумма прибыли с учетом возврата без НДС'] => $universal['THE_AMOUNT_OF_PROFIT_INCLUDING_REFUND_WITHOUT_VAT'],
					$headerKeys['Комиссия'] => $universal['KOMISSIYA'],
					$headerKeys['Комиссия без НДС'] =>$universal['KOMISSIYA_BEZ_NDS_RAZMER_NDS'],
					$headerKeys['Дополнительная выгода без НДС'] => $universal['DOPOLNITELNAYA_VYGODA_BEZ_NDS_RAZMER_NDS'],
					$headerKeys['Сервисный сбор без НДС'] => $universal['SERVISNYY_SBOR_BEZ_NDS_RAZMER_NDS'],
					$headerKeys['Нетто в Валюте поставщика'] => $universal['NET_SUPPLIER_CURRENCY'],
					$headerKeys['Класс'] => $universal['KLASS'],
					$headerKeys['Пассажир'] => $universal['PASSAZHIR'],
					$headerKeys['Дата вылета'] => $universal['DATA_VYLETA'],
					$headerKeys['Дата прилета'] =>$universal['DATA_PRILETA'], // пока пустое
					$headerKeys['Авиакомпания'] => $universal['AVIAKOMPANIYA'],
					$headerKeys['Страна прилета (Конечная точка)'] => $universal['STRANA_PRILETA_KONECHNAYA_TOCHKA'], // пока пустое
					$headerKeys['Город прилета  (Конечная точка)'] => $universal['GOROD_PRILETA_KONECHNAYA_TOCHKA'], // пока пустое
					$headerKeys['Привилегии'] => $universal['PRIVILEGII'],
					$headerKeys['Наличие договора'] => $universal['NALICHIE_DOGOVORA'],
					$headerKeys['Количество сегментов'] => $universal['KOLICHESTVO_SEGMENTOV'],
					$headerKeys['Комментарий Тимлидеру'] => $universal['COMMENT_TEAMLEADER'],
					$headerKeys['Брутто в Валюте поставщика'] => $universal['GROSS_SUPPLIER_CURRENCY'],
					$headerKeys['Комиссия поставщика в Валюте'] => $universal['COMMISION_SUPPLIER_CURRENCY'],
					$headerKeys['Название валюты сделки'] => $universal['CURRENCY_ORIGINAL'],
					$headerKeys['Путешественник'] => $universal['TRAVELER'],
					$headerKeys['Сумма НДС'] => $universal['SUM_NDS'],
					$headerKeys['Сумма продажи после возврата'] => $universal['SALE_AMOUNT_AFTER_REFOUND'],
					$headerKeys['Депозит'] => $universal['DEPOZIT'], // пока пустое
					$headerKeys['Баллы AX'] => $universal['BALLY_AX'], // пока пустое
					$headerKeys['Код FHR'] => $universal['KOD_FHR'],
				];

				$isModifiedRowAgent = false;
				$isAddRefund = false;

			}

			return array(
				'header' => $header,
				'body' => $bodyRows,
			);

		}

		/**
		 * Метод устанавливает сделкам значение "Да" в свойство "Обновить в универсальном отчёте"
		 *
		 * @param array $dealIdList
		 * @return void
		 */
		private function updatePropertyDeal(array $dealIdList = []): void {

			if(count($dealIdList) == 0){
				return;
			}

			$propertyCollection = DealPropertyTable::getList([
				'filter' => [
					'DEAL_ID' => $dealIdList
				]
			]);

			if($propertyCollection->getSelectedRowsCount() == 0){
				return;
			}

			$propertyCollection = $propertyCollection->fetchCollection();

			foreach($propertyCollection as $property){
				$property->set(Deal::FOREIGN_CARD_REPORT_UPDATE, false);
			}

			$propertyCollection->save(); // сохраняем коллекцию

		}

		/**
		 * Отдаёт название статуса.
		 *
		 * @param string $statusCode
		 * @return string
		 */
		private function getLangRefundStatus(string $statusCode){

			$lang = \Brs\FinancialCard\Repository\RefundCard::AUDITION_STATUS;

			if(array_key_exists($statusCode, $lang)){
				return $lang[$statusCode];
			} else {
				return $statusCode;
			}

		}

	}