<?php

namespace Brs\Report\Agent;

use Bitrix\Main\Loader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Агент для генерации Excel отчёта по сделкам.
 * 
 * Генерирует CSV через DealsReportGenerator, затем встраивает его в Excel шаблон.
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
            
            // Увеличиваем лимиты для больших файлов
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
            
            // Шаг 3: Создаём итоговый Excel файл
            $finalExcelPath = $reportDir . "universal_report.xlsx";
            
            self::mergeCsvIntoExcel($templatePath, $tempCsvPath, $finalExcelPath);
            
            // Удаляем временный CSV
            if (file_exists($tempCsvPath)) {
                unlink($tempCsvPath);
            }
            
            // Формируем прямую ссылку на файл
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $fileUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/upload/reports/universal_report.xlsx";
            
            // Отправляем email с ссылкой
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
            
            // Логируем ошибку
            $logMessage = date('Y-m-d H:i:s') . " - Ошибка генерации отчёта для {$userEmail}: " . $e->getMessage() . "\n";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/upload/reports/error.log", $logMessage, FILE_APPEND);
            
            // Отправляем email об ошибке
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
        
        // Возвращаем пустую строку - агент больше не повторяется
        return "";
    }
    
    /**
     * Встраивает CSV данные в Excel шаблон как новый лист.
     * 
     * @param string $templatePath Путь к шаблону Excel (ureport.xlsx с Листом 1)
     * @param string $csvPath Путь к CSV файлу
     * @param string $outputPath Путь к итоговому Excel файлу
     * @return void
     * @throws \Exception
     */
    private static function mergeCsvIntoExcel(string $templatePath, string $csvPath, string $outputPath): void {
        
        // Загружаем существующий Excel шаблон (с Листом 1)
        $spreadsheet = IOFactory::load($templatePath);
        
        // Читаем CSV файл
        $csvData = [];
        if (($handle = fopen($csvPath, "r")) !== false) {
            while (($row = fgetcsv($handle, 0, ";", '"')) !== false) {
                $csvData[] = $row;
            }
            fclose($handle);
        }
        
        if (empty($csvData)) {
            throw new \Exception('CSV файл пустой или не удалось прочитать');
        }
        
        // Создаём новый лист в конце (после Листа 1)
        $newSheet = $spreadsheet->createSheet();
        $newSheet->setTitle('Отчет по сделкам');
        
        // Записываем данные из CSV в новый лист
        $rowIndex = 1;
        foreach ($csvData as $rowData) {
            $columnIndex = 'A';
            foreach ($rowData as $cellValue) {
                $newSheet->setCellValue($columnIndex . $rowIndex, $cellValue);
                $columnIndex++;
            }
            $rowIndex++;
        }
        
        // Применяем автоширину для первых 50 колонок (для читаемости)
        $maxColumns = min(50, count($csvData[0] ?? []));
        for ($i = 0; $i < $maxColumns; $i++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $newSheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Сохраняем итоговый файл
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputPath);
        
        // Освобождаем память
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
    
    /**
     * Форматирует размер файла в читаемый вид.
     * 
     * @param int $bytes Размер в байтах
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