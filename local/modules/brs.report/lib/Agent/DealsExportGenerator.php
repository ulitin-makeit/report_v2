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
	 * @return string Пустая строка = агент выполнен и больше не повторяется
	 */
	public static function generate(string $userEmail): string {

		$logFile = $_SERVER['DOCUMENT_ROOT'] . "/upload/reports/agent_detailed.log";
		$startTime = microtime(true);
		
		// Функция для безопасного логирования
		$log = function($message) use ($logFile) {
			$timestamp = date('Y-m-d H:i:s');
			$memoryUsage = round(memory_get_usage() / 1024 / 1024, 2) . ' MB';
			$logMessage = "[{$timestamp}] [Memory: {$memoryUsage}] {$message}\n";
			file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
			// Принудительная запись на диск
			if (function_exists('opcache_invalidate')) {
				opcache_invalidate($logFile, true);
			}
		};

		try {
			$log("========== СТАРТ АГЕНТА ==========");
			$log("Email пользователя: {$userEmail}");
			$log("PHP версия: " . phpversion());
			$log("Текущая память: " . ini_get('memory_limit'));
			$log("Текущий time_limit: " . ini_get('max_execution_time'));

			// Увеличиваем лимиты для больших файлов
			$log("Попытка увеличить лимиты...");
			$memorySet = ini_set('memory_limit', '1G');
			$timeSet = set_time_limit(600);
			$log("Memory limit установлен: " . ($memorySet !== false ? 'ДА' : 'НЕТ') . " (новое значение: " . ini_get('memory_limit') . ")");
			$log("Time limit установлен: " . ($timeSet !== false ? 'ДА' : 'НЕТ') . " (новое значение: " . ini_get('max_execution_time') . ")");

			// Список необходимых модулей
			$requiredModules = [
				'brs.reportuniversal',
				'brs.report',
				'brs.financialcard',
				'brs.incomingpaymentecomm',
				'crm',
				'main',
			];

			$log("Начинаем подключение модулей...");
			// Проходим по списку и подключаем каждый модуль
			foreach ($requiredModules as $module) {
				$log("Попытка подключить модуль: {$module}");
				$moduleLoadTime = microtime(true);
				
				if (!Loader::includeModule($module)) {
					$log("ОШИБКА: Не удалось загрузить модуль '{$module}'");
					throw new \Exception("Не удалось загрузить обязательный модуль: '{$module}'");
				}
				
				$moduleLoadDuration = round(microtime(true) - $moduleLoadTime, 3);
				$log("Модуль '{$module}' успешно подключен за {$moduleLoadDuration} сек");
			}

			// Путь к директории отчётов
			$reportDir = $_SERVER['DOCUMENT_ROOT'] . "/upload/reports/";
			$log("Директория отчётов: {$reportDir}");
			
			if (!is_dir($reportDir)) {
				$log("Директория не существует, создаем...");
				$created = mkdir($reportDir, 0755, true);
				$log("Директория создана: " . ($created ? 'ДА' : 'НЕТ'));
			} else {
				$log("Директория существует");
			}
			
			// Проверяем права на запись
			$log("Проверка прав на запись в директорию...");
			if (!is_writable($reportDir)) {
				$log("ОШИБКА: Нет прав на запись в директорию {$reportDir}");
				throw new \Exception("Нет прав на запись в директорию: {$reportDir}");
			}
			$log("Права на запись есть");

			// Шаг 1: Генерируем временный CSV файл
			$tempCsvPath = $reportDir . "temp_report.csv";
			$log("========== ШАГ 1: ГЕНЕРАЦИЯ CSV ==========");
			$log("Путь к временному CSV: {$tempCsvPath}");

			try {
				$log("Создаем экземпляр DealsReportGenerator...");
				$generator = new \Brs\ReportUniversal\DealsReportGenerator($tempCsvPath);
				$log("DealsReportGenerator создан успешно");
				
				$log("Начинаем генерацию отчёта...");
				$csvGenerateTime = microtime(true);
				$generator->generate();
				$csvGenerateDuration = round(microtime(true) - $csvGenerateTime, 3);
				
				$log("Генерация CSV завершена за {$csvGenerateDuration} сек");
				
				// Проверяем, что файл создан
				if (!file_exists($tempCsvPath)) {
					$log("ОШИБКА: CSV файл не был создан!");
					throw new \Exception("CSV файл не был создан по пути: {$tempCsvPath}");
				}
				
				$csvSize = filesize($tempCsvPath);
				$log("CSV файл создан успешно, размер: " . self::formatFileSize($csvSize));
				
			} catch (\Exception $e) {
				$log("ИСКЛЮЧЕНИЕ при генерации CSV: " . $e->getMessage());
				$log("Trace: " . $e->getTraceAsString());
				throw $e;
			}

			// Шаг 2: Проверяем наличие шаблона Excel
			$templatePath = $reportDir . "ureport.xlsx";
			$log("========== ШАГ 2: ПРОВЕРКА ШАБЛОНА ==========");
			$log("Путь к шаблону: {$templatePath}");

			if (!file_exists($templatePath)) {
				$log("ОШИБКА: Шаблон Excel не найден!");
				$log("Содержимое директории: " . implode(', ', scandir($reportDir)));
				throw new \Exception('Шаблон Excel не найден: ' . $templatePath);
			}
			
			$templateSize = filesize($templatePath);
			$log("Шаблон найден, размер: " . self::formatFileSize($templateSize));

			// Шаг 3: Встраиваем CSV в Excel через helper класс
			$finalExcelPath = $reportDir . "universal_report.xlsx";
			$log("========== ШАГ 3: ОБЪЕДИНЕНИЕ С ШАБЛОНОМ ==========");
			$log("Итоговый файл будет: {$finalExcelPath}");

			try {
				$log("Вызываем ExcelCsvMerger::merge...");
				$log("Параметры: шаблон={$templatePath}, csv={$tempCsvPath}, результат={$finalExcelPath}");
				
				$mergeTime = microtime(true);
				
				// Используем ExcelCsvMerger для объединения шаблона и CSV
				ExcelCsvMerger::merge(
					$templatePath,           // Шаблон Excel с существующими листами (Лист 1)
					$tempCsvPath,            // Сгенерированный CSV файл с данными
					$finalExcelPath,         // Итоговый Excel файл
					'Отчет по сделкам',      // Название нового листа
					';',                     // Разделитель CSV (точка с запятой)
					'"'                      // Символ обрамления в CSV (двойные кавычки)
				);
				
				$mergeDuration = round(microtime(true) - $mergeTime, 3);
				$log("ExcelCsvMerger::merge завершен за {$mergeDuration} сек");
				
			} catch (\Exception $e) {
				$log("ИСКЛЮЧЕНИЕ при объединении: " . $e->getMessage());
				$log("Trace: " . $e->getTraceAsString());
				throw $e;
			}

			// Проверяем, что итоговый файл создан
			if (!file_exists($finalExcelPath)) {
				$log("ОШИБКА: Итоговый Excel файл не был создан!");
				throw new \Exception("Итоговый Excel файл не был создан: {$finalExcelPath}");
			}
			
			$finalSize = filesize($finalExcelPath);
			$log("Итоговый файл создан, размер: " . self::formatFileSize($finalSize));

			// Удаляем временный CSV файл
			$log("========== ШАГ 4: ОЧИСТКА ==========");
			if (file_exists($tempCsvPath)) {
				$log("Удаляем временный CSV файл...");
				$deleted = unlink($tempCsvPath);
				$log("Временный файл удален: " . ($deleted ? 'ДА' : 'НЕТ'));
			}

			// Формируем прямую ссылку на файл
			$fileUrl = "https://crm.rstls.ru/upload/reports/universal_report.xlsx";
			$log("========== ШАГ 5: ОТПРАВКА EMAIL ==========");
			$log("URL файла: {$fileUrl}");
			$log("Отправляем письмо на: {$userEmail}");

			// Отправляем email с ссылкой на готовый файл
			$emailSent = \CEvent::Send(
				'DEALS_EXPORT_REPORT_READY',
				's1',
				[
					'EMAIL' => $userEmail,
					'FILE_URL' => $fileUrl,
					'DATE' => date('d.m.Y H:i'),
					'FILE_SIZE' => self::formatFileSize($finalSize)
				]
			);
			
			$log("Email отправлен: " . ($emailSent ? 'ДА' : 'НЕТ (возможно шаблон не настроен)'));

			$totalDuration = round(microtime(true) - $startTime, 3);
			$log("========== АГЕНТ ЗАВЕРШЕН УСПЕШНО ==========");
			$log("Общее время выполнения: {$totalDuration} сек");
			$log("Пиковое использование памяти: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB');

		} catch (\Exception $e) {

			$log("========== КРИТИЧЕСКАЯ ОШИБКА ==========");
			$log("Тип исключения: " . get_class($e));
			$log("Сообщение: " . $e->getMessage());
			$log("Файл: " . $e->getFile() . " (строка " . $e->getLine() . ")");
			$log("Trace:\n" . $e->getTraceAsString());

			// Логируем ошибку в файл
			$logMessage = date('Y-m-d H:i:s') . " - Ошибка генерации отчёта для {$userEmail}: " . $e->getMessage() . "\n";
			file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/upload/reports/error.log", $logMessage, FILE_APPEND);

			// Отправляем email об ошибке пользователю
			$log("Отправляем email об ошибке...");
			\CEvent::Send(
				'DEALS_EXPORT_REPORT_ERROR',
				's1',
				[
					'EMAIL' => $userEmail,
					'ERROR_MESSAGE' => $e->getMessage(),
					'DATE' => date('d.m.Y H:i')
				]
			);
			$log("Email об ошибке отправлен");
		}

		$log("Возвращаем пустую строку для завершения агента");
		$log("========================================\n");
		
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