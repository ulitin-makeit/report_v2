<?php

namespace Brs\Report\Helper;

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;
use Box\Spout\Common\Entity\Cell;

/**
 * Класс для встраивания CSV данных в Excel файл.
 * 
 * Использует Box Spout для потоковой работы с XLSX файлами.
 * Включает очистку данных для предотвращения повреждения файла.
 */
class ExcelCsvMerger {
    
    /** @var string Путь к файлу логов */
    private static $logFile = null;
    
    /** @var bool Включить подробное логирование */
    private static $enableLogging = true;
    
    /**
     * Встраивает CSV данные в Excel шаблон как новый лист.
     * 
     * @param string $templatePath Путь к шаблону Excel (с существующими листами)
     * @param string $csvPath Путь к CSV файлу
     * @param string $outputPath Путь к итоговому Excel файлу
     * @param string $newSheetName Название нового листа (по умолчанию "Отчет по сделкам")
     * @param string $csvDelimiter Разделитель в CSV (по умолчанию ";")
     * @param string $csvEnclosure Символ обрамления в CSV (по умолчанию '"')
     * @return void
     * @throws \Exception
     */
    public static function merge(
        string $templatePath, 
        string $csvPath, 
        string $outputPath,
        string $newSheetName = 'Отчет по сделкам',
        string $csvDelimiter = ';',
        string $csvEnclosure = '"'
    ): void {
        
        // Инициализируем логирование
        self::$logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/reports/excel_merge.log';
        self::log("=== Начало объединения Excel и CSV ===");
        self::log("Шаблон: {$templatePath}");
        self::log("CSV: {$csvPath}");
        self::log("Результат: {$outputPath}");
        
        // Увеличиваем лимиты
        ini_set('memory_limit', '1G');
        set_time_limit(600);
        self::log("Лимиты установлены: memory_limit=1G, time_limit=600");
        
        // Проверяем существование файлов
        if (!file_exists($templatePath)) {
            throw new \Exception("Шаблон Excel не найден: {$templatePath}");
        }
        
        if (!file_exists($csvPath)) {
            throw new \Exception("CSV файл не найден: {$csvPath}");
        }
        
        // Проверяем что Box Spout установлен
        if (!class_exists('Box\Spout\Reader\Common\Creator\ReaderEntityFactory')) {
            throw new \Exception('Box Spout не установлен. Выполните: composer require box/spout');
        }
        
        try {
            
            self::log("Открытие шаблона Excel...");
            
            // Читаем существующий Excel шаблон
            $reader = ReaderEntityFactory::createXLSXReader();
            $reader->open($templatePath);
            
            // Создаём writer для нового файла
            $writer = WriterEntityFactory::createXLSXWriter();
            $writer->openToFile($outputPath);
            
            self::log("Копирование существующих листов...");
            
            // Шаг 1: Копируем все существующие листы из шаблона
            self::copyExistingSheets($reader, $writer);
            
            self::log("Добавление нового листа с данными из CSV...");
            
            // Шаг 2: Добавляем новый лист с данными из CSV
            self::addCsvSheet($writer, $csvPath, $newSheetName, $csvDelimiter, $csvEnclosure);
            
            self::log("Закрытие файлов...");
            
            // Закрываем все потоки
            $reader->close();
            $writer->close();
            
            // Проверяем что файл создался
            if (!file_exists($outputPath)) {
                throw new \Exception("Итоговый файл не был создан: {$outputPath}");
            }
            
            $fileSize = filesize($outputPath);
            self::log("Файл успешно создан. Размер: " . self::formatBytes($fileSize));
            self::log("=== Объединение завершено успешно ===");
            
        } catch (\Exception $e) {
            self::log("ОШИБКА: " . $e->getMessage());
            self::log("Трейс: " . $e->getTraceAsString());
            throw new \Exception('Ошибка при объединении Excel и CSV: ' . $e->getMessage());
        }
    }
    
    /**
     * Копирует все листы из исходного Excel в новый файл.
     * 
     * @param \Box\Spout\Reader\XLSX\Reader $reader Reader исходного файла
     * @param \Box\Spout\Writer\XLSX\Writer $writer Writer нового файла
     * @return void
     */
    private static function copyExistingSheets($reader, $writer): void {
        
        $isFirstSheet = true;
        $sheetIndex = 0;
        
        // Итерируемся по всем листам шаблона
        foreach ($reader->getSheetIterator() as $sheet) {
            
            $sheetIndex++;
            $sheetName = $sheet->getName();
            self::log("Копирование листа #{$sheetIndex}: '{$sheetName}'");
            
            // Для первого листа не создаём новый (он уже есть по умолчанию)
            if ($isFirstSheet) {
                $currentSheet = $writer->getCurrentSheet();
                $isFirstSheet = false;
            } else {
                // Для остальных листов создаём новые
                $writer->addNewSheetAndMakeItCurrent();
                $currentSheet = $writer->getCurrentSheet();
            }
            
            // Устанавливаем название листа
            $currentSheet->setName($sheetName);
            
            $rowCount = 0;
            
            // Копируем все строки построчно
            foreach ($sheet->getRowIterator() as $row) {
                $writer->addRow($row);
                $rowCount++;
            }
            
            self::log("Скопировано строк: {$rowCount}");
        }
    }
    
    /**
     * Добавляет новый лист с данными из CSV файла.
     * Применяет санитизацию данных для предотвращения повреждения файла.
     * 
     * @param \Box\Spout\Writer\XLSX\Writer $writer Writer для Excel файла
     * @param string $csvPath Путь к CSV файлу
     * @param string $sheetName Название нового листа
     * @param string $delimiter Разделитель CSV
     * @param string $enclosure Символ обрамления CSV
     * @return void
     * @throws \Exception
     */
    private static function addCsvSheet($writer, string $csvPath, string $sheetName, string $delimiter, string $enclosure): void {
        
        // Создаём новый лист
        $writer->addNewSheetAndMakeItCurrent();
        $currentSheet = $writer->getCurrentSheet();
        $currentSheet->setName($sheetName);
        
        self::log("Создан новый лист: '{$sheetName}'");
        
        // Создаём CSV Reader
        $csvReader = ReaderEntityFactory::createCSVReader();
        $csvReader->setFieldDelimiter($delimiter);
        $csvReader->setFieldEnclosure($enclosure);
        
        // Устанавливаем кодировку UTF-8
        $csvReader->setEncoding('UTF-8');
        
        $csvReader->open($csvPath);
        
        $rowCount = 0;
        $errorCount = 0;
        
        // Читаем CSV и записываем построчно в Excel
        foreach ($csvReader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                
                $rowCount++;
                
                try {
                    // Очищаем данные строки перед записью
                    $cleanedRow = self::sanitizeRow($row);
                    
                    // Записываем очищенную строку
                    $writer->addRow($cleanedRow);
                    
                    // Логируем прогресс каждые 10000 строк
                    if ($rowCount % 10000 === 0) {
                        self::log("Обработано строк: {$rowCount}");
                    }
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    self::log("Ошибка в строке #{$rowCount}: " . $e->getMessage());
                    
                    // Записываем пустую строку вместо проблемной
                    $writer->addRow(Row::fromValues([]));
                }
            }
        }
        
        self::log("Всего обработано строк: {$rowCount}");
        if ($errorCount > 0) {
            self::log("Строк с ошибками: {$errorCount}");
        }
        
        // Закрываем CSV Reader
        $csvReader->close();
    }
    
    /**
     * Очищает строку от проблемных данных.
     * 
     * @param Row $row Исходная строка
     * @return Row Очищенная строка
     */
    private static function sanitizeRow(Row $row): Row {
        
        $cells = $row->getCells();
        $cleanedCells = [];
        
        foreach ($cells as $cell) {
            $value = $cell->getValue();
            $cleanedValue = self::sanitizeCell($value);
            $cleanedCells[] = Cell::fromValue($cleanedValue);
        }
        
        return new Row($cleanedCells);
    }
    
    /**
     * Очищает значение ячейки от проблемных символов.
     * 
     * @param mixed $value Исходное значение
     * @return mixed Очищенное значение
     */
    private static function sanitizeCell($value) {
        
        // Если не строка - возвращаем как есть
        if (!is_string($value)) {
            return $value;
        }
        
        // Пустая строка - возвращаем как есть
        if (trim($value) === '') {
            return $value;
        }
        
        // Шаг 1: Проверяем корректность UTF-8 и исправляем
        if (!mb_check_encoding($value, 'UTF-8')) {
            // Пытаемся конвертировать из Windows-1251
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1251');
        }
        
        // Шаг 2: Удаляем BOM (Byte Order Mark) если есть
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        
        // Шаг 3: Удаляем нулевые байты
        $value = str_replace("\0", '', $value);
        
        // Шаг 4: Удаляем невидимые управляющие символы (кроме \n, \r, \t)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        
        // Шаг 5: Заменяем множественные пробелы на одинарные
        $value = preg_replace('/[ \t]+/', ' ', $value);
        
        // Шаг 6: Ограничиваем длину (Excel имеет лимит 32767 символов на ячейку)
        if (mb_strlen($value) > 32000) {
            $value = mb_substr($value, 0, 32000) . '... [обрезано]';
        }
        
        // Шаг 7: Экранируем XML-специальные символы (на всякий случай, хотя Spout должен это делать)
        // НЕ применяем htmlspecialchars - Spout сам обрабатывает
        
        return $value;
    }
    
    /**
     * Записывает сообщение в лог.
     * 
     * @param string $message Сообщение для лога
     * @return void
     */
    private static function log(string $message): void {
        
        if (!self::$enableLogging || !self::$logFile) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Форматирует размер файла в читаемый вид.
     * 
     * @param int $bytes Размер в байтах
     * @return string Отформатированная строка
     */
    private static function formatBytes(int $bytes): string {
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}