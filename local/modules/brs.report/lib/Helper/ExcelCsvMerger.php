<?php

namespace Brs\Report\Helper;

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;

/**
 * Класс для встраивания CSV данных в Excel файл.
 * 
 * Использует Box Spout для потоковой работы с XLSX файлами.
 * Включает очистку данных для предотвращения повреждения файла.
 * 
 * Совместимо с: box/spout ^2.7 || ^3.0
 */
class ExcelCsvMerger {
    
    /** @var string|null Путь к файлу логов */
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
        
        // Очищаем старый лог
        if (file_exists(self::$logFile)) {
            file_put_contents(self::$logFile, '');
        }
        
        self::log("=== Начало объединения Excel и CSV ===");
        self::log("Шаблон: {$templatePath}");
        self::log("CSV: {$csvPath}");
        self::log("Результат: {$outputPath}");
        self::log("PHP версия: " . PHP_VERSION);
        self::log("Memory limit: " . ini_get('memory_limit'));
        
        // Увеличиваем лимиты
        $oldMemoryLimit = ini_get('memory_limit');
        $oldTimeLimit = ini_get('max_execution_time');
        
        ini_set('memory_limit', '1G');
        set_time_limit(600);
        
        self::log("Лимиты изменены: memory_limit {$oldMemoryLimit} -> 1G, time_limit {$oldTimeLimit} -> 600");
        
        // Проверяем существование файлов
        if (!file_exists($templatePath)) {
            throw new \Exception("Шаблон Excel не найден: {$templatePath}");
        }
        
        if (!file_exists($csvPath)) {
            throw new \Exception("CSV файл не найден: {$csvPath}");
        }
        
        // Проверяем размеры файлов
        $templateSize = filesize($templatePath);
        $csvSize = filesize($csvPath);
        self::log("Размер шаблона: " . self::formatBytes($templateSize));
        self::log("Размер CSV: " . self::formatBytes($csvSize));
        
        // Проверяем что Box Spout установлен
        if (!class_exists('Box\Spout\Reader\Common\Creator\ReaderEntityFactory')) {
            throw new \Exception('Box Spout не установлен. Выполните: composer require box/spout');
        }
        
        self::log("Box Spout загружен успешно");
        
        try {
            
            self::log("Открытие шаблона Excel...");
            
            // Читаем существующий Excel шаблон
            $reader = ReaderEntityFactory::createXLSXReader();
            $reader->open($templatePath);
            
            self::log("Создание нового Excel файла...");
            
            // Создаём writer для нового файла
            $writer = WriterEntityFactory::createXLSXWriter();
            $writer->openToFile($outputPath);
            
            self::log("Копирование существующих листов из шаблона...");
            
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
            
            // Проверяем что размер адекватный (не 0 байт)
            if ($fileSize < 1000) {
                throw new \Exception("Файл слишком маленький ({$fileSize} байт), возможно он повреждён");
            }
            
            self::log("=== Объединение завершено успешно ===");
            
        } catch (\Exception $e) {
            self::log("!!! ОШИБКА: " . $e->getMessage());
            self::log("Файл: " . $e->getFile());
            self::log("Строка: " . $e->getLine());
            self::log("Трейс: " . $e->getTraceAsString());
            throw new \Exception('Ошибка при объединении Excel и CSV: ' . $e->getMessage());
        }
    }
    
    /**
     * Копирует все листы из исходного Excel в новый файл.
     * 
     * @param mixed $reader Reader исходного файла (Box\Spout\Reader\XLSX\Reader)
     * @param mixed $writer Writer нового файла (Box\Spout\Writer\XLSX\Writer)
     * @return void
     */
    private static function copyExistingSheets($reader, $writer): void {
        
        $isFirstSheet = true;
        $sheetIndex = 0;
        
        // Итерируемся по всем листам шаблона
        foreach ($reader->getSheetIterator() as $sheet) {
            
            $sheetIndex++;
            $sheetName = $sheet->getName();
            self::log("  Лист #{$sheetIndex}: '{$sheetName}'");
            
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
            
            // Копируем все строки построчно (потоковая обработка!)
            foreach ($sheet->getRowIterator() as $row) {
                $writer->addRow($row);
                $rowCount++;
                
                // Логируем прогресс для больших листов
                if ($rowCount % 5000 === 0) {
                    self::log("    Скопировано строк: {$rowCount}");
                }
            }
            
            self::log("  Итого скопировано строк: {$rowCount}");
        }
        
        self::log("Все листы из шаблона скопированы. Всего листов: {$sheetIndex}");
    }
    
    /**
     * Добавляет новый лист с данными из CSV файла.
     * Применяет санитизацию данных для предотвращения повреждения файла.
     * 
     * @param mixed $writer Writer для Excel файла (Box\Spout\Writer\XLSX\Writer)
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
        
        // Пытаемся установить кодировку UTF-8 (не все версии Spout поддерживают)
        if (method_exists($csvReader, 'setEncoding')) {
            $csvReader->setEncoding('UTF-8');
            self::log("Установлена кодировка UTF-8 для CSV");
        }
        
        $csvReader->open($csvPath);
        
        $rowCount = 0;
        $errorCount = 0;
        $startTime = microtime(true);
        
        self::log("Начало обработки CSV данных...");
        
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
                        $elapsed = round(microtime(true) - $startTime, 2);
                        $speed = round($rowCount / $elapsed, 0);
                        self::log("  Обработано строк: {$rowCount} (скорость: {$speed} строк/сек)");
                    }
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    self::log("  !!! Ошибка в строке #{$rowCount}: " . $e->getMessage());
                    
                    // Записываем пустую строку вместо проблемной
                    $emptyRow = WriterEntityFactory::createRowFromArray(['[ERROR]'])
                    $writer->addRow($emptyRow);
                    
                    // Если слишком много ошибок - прерываем
                    if ($errorCount > 100) {
                        self::log("  !!! Слишком много ошибок ({$errorCount}), прерываем обработку");
                        throw new \Exception("Обработка прервана: слишком много ошибок в CSV файле");
                    }
                }
            }
        }
        
        $totalTime = round(microtime(true) - $startTime, 2);
        
        self::log("Обработка CSV завершена:");
        self::log("  Всего обработано строк: {$rowCount}");
        self::log("  Время обработки: {$totalTime} сек");
        self::log("  Средняя скорость: " . round($rowCount / $totalTime, 0) . " строк/сек");
        
        if ($errorCount > 0) {
            self::log("  !!! Строк с ошибками: {$errorCount}");
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
    
        // В Box Spout получаем массив значений ячеек через toArray()
        $cells = $row->toArray();
        $cleanedCells = [];
        
        foreach ($cells as $cellValue) {
            $cleanedValue = self::sanitizeCell($cellValue);
            $cleanedCells[] = $cleanedValue;
        }
        
        // В Box Spout создаём строку через WriterEntityFactory
        return WriterEntityFactory::createRowFromArray($cleanedCells);
    }
    
    /**
     * Очищает значение ячейки от проблемных символов.
     * 
     * @param mixed $value Исходное значение
     * @return mixed Очищенное значение
     */
    private static function sanitizeCell($value) {
        
        // Если не строка - возвращаем как есть (числа, даты, boolean и т.д.)
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
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1251');
            if ($converted !== false && $converted !== '') {
                $value = $converted;
            } else {
                // Если не удалось конвертировать - удаляем некорректные символы
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }
        
        // Шаг 2: Удаляем BOM (Byte Order Mark) если есть
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        
        // Шаг 3: Удаляем нулевые байты
        $value = str_replace("\0", '', $value);
        
        // Шаг 4: Удаляем невидимые управляющие символы (кроме \n, \r, \t)
        // \x00-\x08 - управляющие символы до табуляции
        // \x0B - вертикальная табуляция
        // \x0C - перевод страницы  
        // \x0E-\x1F - остальные управляющие символы
        // \x7F - DEL
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        
        // Шаг 5: Удаляем невидимые Unicode символы
        // Zero Width Space, Zero Width Non-Joiner и т.д.
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value);
        
        // Шаг 6: Заменяем множественные пробелы на одинарные
        $value = preg_replace('/[ \t]+/', ' ', $value);
        
        // Шаг 7: Удаляем пробелы в начале и конце
        $value = trim($value);
        
        // Шаг 8: Ограничиваем длину (Excel имеет лимит 32767 символов на ячейку)
        if (mb_strlen($value) > 32000) {
            $value = mb_substr($value, 0, 32000) . '... [обрезано]';
        }
        
        // Шаг 9: Защита от CSV Injection
        // Если строка начинается с =, +, -, @ - добавляем одинарную кавычку
        // Excel не будет интерпретировать как формулу
        if (strlen($value) > 0) {
            $firstChar = $value[0];
            if (in_array($firstChar, ['=', '+', '-', '@'], true)) {
                $value = "'" . $value;
            }
        }
        
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
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $logMessage = "[{$timestamp}] [{$memoryUsage}MB] {$message}\n";
        
        @file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
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