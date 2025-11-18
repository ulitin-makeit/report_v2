<?php

namespace Brs\Report\Agent;

use Bitrix\Main\Loader;
use Brs\Report\Helper\ExcelCsvMerger;

/**
 * Агент для генерации Excel отчёта по сделкам.
 *
 * Генерирует CSV через DealsReportGenerator,
 * затем встраивает его в Excel через ExcelCsvMerger.
 */
class DealsExportGenerator {

	/**
	 * Генерирует Excel отчёт и отправляет ссылку на email.
	 *
	 * @param string $userEmail Email пользователя для отправки ссылки
	 * @param string $dateFrom Дата начала периода в формате ДД.ММ.ГГГГ (опционально)
	 * @param string $dateTo Дата окончания периода в формате ДД.ММ.ГГГГ (опционально)
	 * @return string Пустая строка = агент выполнен и больше не повторяется
	 */
	public static function generate(string $userEmail, string $dateFrom = '', string $dateTo = ''): string {

		try {

			// Увеличиваем лимиты для больших файлов
			ini_set('memory_limit', '1G');
			set_time_limit(600);

			// Список необходимых модулей
			$requiredModules = [
				'brs.reportuniversal',
				'brs.report',
				'brs.financialcard',
				'brs.incomingpaymentecomm',
				'crm',
				'main',
			];

			// Проходим по списку и подключаем каждый модуль
			foreach ($requiredModules as $module) {
				if (!Loader::includeModule($module)) {
					// Если хотя бы один модуль не подключился, выбрасываем исключение
					throw new \Exception("Не удалось загрузить обязательный модуль: '{$module}'");
				}
			}

			// Путь к директории отчётов
			$reportDir = $_SERVER['DOCUMENT_ROOT'] . "/upload/reports/";
			if (!is_dir($reportDir)) {
				mkdir($reportDir, 0755, true);
			}

			// Шаг 1: Генерируем временный CSV файл
			$tempCsvPath = $reportDir . "temp_report.csv";

			// Создаем генератор с параметрами дат
			$generator = new \Brs\ReportUniversal\DealsReportGenerator($tempCsvPath);
			
			// Устанавливаем фильтр по датам если они переданы
			if (!empty($dateFrom) || !empty($dateTo)) {
				$generator->setDateFilter($dateFrom, $dateTo);
			}
			
			$generator->generate();

			// Шаг 2: Проверяем наличие шаблона Excel
			$templatePath = $reportDir . "ureport.xlsx";

			if (!file_exists($templatePath)) {
				throw new \Exception('Шаблон Excel не найден: ' . $templatePath);
			}

			// Шаг 3: Встраиваем CSV в Excel через helper класс
			$finalExcelPath = $reportDir . "universal_report.xlsx";

			// Используем ExcelCsvMerger для объединения шаблона и CSV
			ExcelCsvMerger::merge(
				$templatePath,           // Шаблон Excel с существующими листами (Лист 1)
				$tempCsvPath,            // Сгенерированный CSV файл с данными
				$finalExcelPath,         // Итоговый Excel файл
				'Отчет по сделкам',      // Название нового листа
				';',                     // Разделитель CSV (точка с запятой)
				'"'                      // Символ обрамления в CSV (двойные кавычки)
			);

			// Удаляем временный CSV файл
			if (file_exists($tempCsvPath)) {
				unlink($tempCsvPath);
			}

			// Формируем прямую ссылку на файл
			$fileUrl = "https://crm.rstls.ru/upload/reports/universal_report.xlsx";

			// Формируем информацию о периоде для письма
			$periodInfo = '';
			if (!empty($dateFrom) || !empty($dateTo)) {
				$periodInfo = 'Период: ';
				if (!empty($dateFrom)) {
					$periodInfo .= 'с ' . $dateFrom . ' ';
				}
				if (!empty($dateTo)) {
					$periodInfo .= 'по ' . $dateTo;
				}
			} else {
				$periodInfo = 'Все сделки';
			}

			// Отправляем email с ссылкой на готовый файл
			\CEvent::Send(
				'DEALS_EXPORT_REPORT_READY',
				's1',
				[
					'EMAIL' => $userEmail,
					'FILE_URL' => $fileUrl,
					'DATE' => date('d.m.Y H:i'),
					'FILE_SIZE' => self::formatFileSize(filesize($finalExcelPath)),
					'PERIOD' => $periodInfo
				]
			);

		} catch (\Exception $e) {

			// Логируем ошибку в файл
			$logMessage = date('Y-m-d H:i:s') . " - Ошибка генерации отчёта для {$userEmail}: " . $e->getMessage() . "\n";
			file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/upload/reports/error.log", $logMessage, FILE_APPEND);

			// Отправляем email об ошибке пользователю
			\CEvent::Send(
				'DEALS_EXPORT_REPORT_ERROR',
				's1',
				[
					'EMAIL' => $userEmail,
					'ERROR_MESSAGE' => $e->getMessage(),
					'DATE' => date('d.m.Y H:i')
				]
			);
		}

		// Возвращаем пустую строку - агент выполняется один раз и удаляется
		return "";
	}

	/**
	 * Форматирует размер файла в читаемый вид.
	 *
	 * @param int $bytes Размер файла в байтах
	 * @return string Отформатированная строка (например "2.5 MB")
	 */
	private static function formatFileSize(int $bytes): string {

		$units = ['B', 'KB', 'MB', 'GB'];
		$i = 0;

		while ($bytes >= 1024 && $i < count($units) - 1) {
			$bytes /= 1024;
			$i++;
		}

		return round($bytes, 2) . ' ' . $units[$i];
	}
}