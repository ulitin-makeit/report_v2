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
    
        $logFile = $_SERVER['DOCUMENT_ROOT'] . "/upload/reports/agent_debug.log";
        
        // Функция для логирования
        $log = function($message) use ($logFile) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
        };
        
        $log("=== СТАРТ АГЕНТА ===");
        $log("Email: {$userEmail}");
        
        try {
            
            $log("Установка лимитов...");
            ini_set('memory_limit', '512M');
            set_time_limit(300);
            
            $log("Подключение модуля...");
            if (!Loader::includeModule('brs.reportuniversal')) {
                throw new \Exception('Модуль brs.reportuniversal не установлен');
            }
            
            $log("Создание директории...");
            $reportDir = $_SERVER['DOCUMENT_ROOT'] . "/upload/reports/";
        
            
            $log("Генерация CSV...");
            $tempCsvPath = $reportDir . "temp_report_" . time() . ".csv";
            
            $generator = new \Brs\ReportUniversal\DealsReportGenerator($tempCsvPath);
            $generator->generate();
            
            $log("CSV создан: " . filesize($tempCsvPath) . " bytes");
            
            $log("Проверка шаблона Excel...");
            $templatePath = $reportDir . "ureport.xlsx";
            
            if (!file_exists($templatePath)) {
                throw new \Exception('Шаблон Excel не найден: ' . $templatePath);
            }
            
            $log("Встраивание CSV в Excel...");
            $finalExcelPath = $reportDir . "universal_report.xlsx";
            
            ExcelCsvMerger::merge(
                $templatePath,
                $tempCsvPath,
                $finalExcelPath,
                'Отчет по сделкам',
                ';',
                '"'
            );
            
            $log("Excel создан: " . filesize($finalExcelPath) . " bytes");
            
            $log("Удаление временного CSV...");
            if (file_exists($tempCsvPath)) {
                unlink($tempCsvPath);
            }
            
            $log("Формирование ссылки...");
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $fileUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/upload/reports/universal_report.xlsx";
            
            $log("Отправка email на {$userEmail}...");
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
            
            $log("=== АГЕНТ ЗАВЕРШЁН УСПЕШНО ===");
            
        } catch (\Exception $e) {
            
            $log("!!! ОШИБКА: " . $e->getMessage());
            $log("Stack trace: " . $e->getTraceAsString());
            
            $logMessage = date('Y-m-d H:i:s') . " - Ошибка генерации отчёта для {$userEmail}: " . $e->getMessage() . "\n";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/upload/reports/error.log", $logMessage, FILE_APPEND);
            
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
        
        $log("Возврат пустой строки для завершения агента");
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