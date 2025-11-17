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
        
        try {
            
            // Увеличиваем лимиты для работы с большими файлами
            ini_set('memory_limit', '512M');
            set_time_limit(300);
            
            // Подключаем модуль с генератором отчётов
            if (!Loader::includeModule('brs.reportuniversal')) {
                throw new \Exception('Модуль brs.reportuniversal не установлен');
            }
            
            // Путь к директории отчётов
            $reportDir = $_SERVER['DOCUMENT_ROOT'] . "/upload/reports/";
            if (!is_dir($reportDir)) {
                mkdir($reportDir, 0755, true);
            }
            
            // Шаг 1: Генерируем временный CSV файл
            $tempCsvPath = $reportDir . "temp_report_" . time() . ".csv";
            
            $generator = new \Brs\ReportUniversal\DealsReportGenerator($tempCsvPath);
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
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $fileUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/upload/reports/universal_report.xlsx";
            
            // Отправляем email с ссылкой на готовый файл
            \CEvent::Send(
                'DEALS_EXPORT_REPORT_READY',
                's1',
                [
                    'EMAIL' => $userEmail,
                    'FILE_URL' => $fileUrl,
                    'DATE' => date('d.m.Y H:i'),
                    'FILE_SIZE' => self::formatFileSize(filesize($finalExcelPath))
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