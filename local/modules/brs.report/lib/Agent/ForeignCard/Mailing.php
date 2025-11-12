<?php

	namespace Brs\Report\Agent\ForeignCard;

	use Brs\Report\Page\ForeignCard;
	use Brs\Report\Agent\ForeignCard as ForeignCardAgent;

	use Bitrix\Main\Mail\Event;

	/**
	 * Рассылка с отчетом "Консультации по иностранным картам клиентов РСБ"
	 * почтовый шабон id = 132, тип шаблона [REPORT]
	 * */
	class Mailing {

		// поля для шапки документа xls
		private static $headerArr=[
			'Категория',
			'Номер сделки',
			'Дата создания сделки',
			'Ответственное лицо',
			'Менеджер',
			'ID клиента',
			'Клиент',
			'Результат сделки',
			'Дата завершения',
			'Город',
			'Дата оплаты Клиентом',
			'Итого оплачено клиентом',
			'Дата возврата',
			'Статус карты возврата',
			'Сумма возврата клиенту'
		];

		private static $dateReport = 1; // день месяца когда необходимо отправить отчет

		public static $fileName ;

		public static function init() {

			write_log('reportMountForeignCardAction', 'actionExportXls');

			\CModule::IncludeModule('brs.report');

			// обновление отчета для выгрузки актуальных данных в файл xls
			ForeignCardAgent::init('all');

			$dateNow = date('d');

			// если день отчета и сегодняшний день не совпадают код не выполняется
			 if($dateNow != self::$dateReport) {
				 return  __METHOD__ . '();';
			 }

			self::$fileName = './www/upload/Консультации по иностранным картам клиентов РСБ - ' . date('Y-m-d') . '.xls';

			$document = self::getDataReport();

			// создание и заполнение файла
			$saveDocument = self::saveDocument($document);

			// если файл создан отправляем email
			if($saveDocument) {
				self::sendEmailReport();
			}
			return __METHOD__ . '();';
		}

		//

		/**
		 * Получение и фильтрация данных из отчета "Консультации по иностранным картам клиентов РСБ" за предыдущий месяц
		 *
		 * @return array - массив с табличными даными
		 */
		private static function getDataReport() {

			$getListParams = [
				'offset' => 0,
				'limit' => '',
				'order' => [],
				'filter' =>[
					'!STATUS_SDELKI'=>'Удалена администратором',
					'!MANAGER'=>'',
					'!DATA_OPLATY_KLIENTOM'=>''
				],
				'select'=>[
					'KATEGORIYA','NOMER_SDELKI','DATA_SOZDANIYA_SDELKI',
					'OTVETSTVENNOE_LITSO','MANAGER','ID_KLIENTA','KLIENT',
					'REZULTAT_SDELKI','DATE_CLOSE','GOROD','DATA_OPLATY_KLIENTOM',
					'TOTAL_PAID_CLIENT','DATA_VOZVRATA','STATUS_CARD_REFUND',
					'REFOUND_AMOUNT_CLIENT'
				]
			];

			// получение массива даных из отчета "Консультации по иностранным картам клиентов РСБ"
			$ForeignCard = new ForeignCard;
			$document = $ForeignCard->getDocument($getListParams, 'false');
			// удаление не нужных колонок
			foreach($document['header'] as $key => $header) {
				if(!in_array($header, self::$headerArr)) {
					unset($document['header'][$key]);
					foreach($document['body'] as &$body) {
						unset($body[$key]);
					}
				}
			}
			return $document;

		}

		/**
		 * Метод сохраняет документ xls
		 *
		 * @param $document - массив с табличными даными
		 * @array $document['header'] - поля для шапки документа
		 * @array $document['body'] - масив со строками
		 * @return bool true - при успешном создании файла
		 */
		private static function saveDocument($document) {

			file_put_contents(self::$fileName,''); // очищение файла если он уже есть
			$file = fopen(self::$fileName,'a+');

			// формируем шапку таблицы
			fwrite($file,'<table border="1"><thead><tr>');

			foreach($document['header'] as $headerName) {
				fwrite($file,'<th>' . $headerName . '</th>');
			}

			fwrite($file, '</tr></thead>');

			// формируем тело таблицы
			fwrite($file, '<tbody>');
			foreach($document['body'] as $row) {

				fwrite($file, '<tr>');
				foreach($row as $columnText) {
					fwrite($file, '<td>' . mb_eregi_replace("\n", '<br>', $columnText) . '</td>');
				}
				fwrite($file, '</tr>');
			}
			fwrite($file, '</tbody></table>');
			if(file_exists(self::$fileName)) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Отправка email с помощью почтового шаблона с вложеной таблицей.xls
		 */
		private static function sendEmailReport(): void {
			\CEvent::Send('BRS_REPORT_FOREIGN_CARD_MONTHLY_LIST_DEAL', 's1', array(), 'Y', '', array(self::$fileName));
		}
	}