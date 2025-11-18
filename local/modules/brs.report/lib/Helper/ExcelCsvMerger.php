<?php

namespace Brs\Report\Helper;

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

/**
 * Класс для встраивания CSV данных в Excel файл.
 * 
 * Использует Box Spout для потоковой работы с XLSX файлами.
 * Потребляет минимум памяти - обрабатывает файлы построчно.
 */
class ExcelCsvMerger {
    
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
            
            // Читаем существующий Excel шаблон
            $reader = ReaderEntityFactory::createXLSXReader();
            $reader->open($templatePath);
            
            // Создаём writer для нового файла
            $writer = WriterEntityFactory::createXLSXWriter();
            $writer->openToFile($outputPath);
            
            // Шаг 1: Копируем все существующие листы из шаблона (включая Лист 1)
            self::copyExistingSheets($reader, $writer);
            
            // Шаг 2: Добавляем новый лист с данными из CSV
            self::addCsvSheet($writer, $csvPath, $newSheetName, $csvDelimiter, $csvEnclosure);
            
            // Закрываем все потоки
            $reader->close();
            $writer->close();
            
        } catch (\Exception $e) {
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
        
        // Итерируемся по всем листам шаблона
        foreach ($reader->getSheetIterator() as $sheet) {
            
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
            $currentSheet->setName($sheet->getName());
            
            // Копируем все строки построчно (потоковая обработка - минимум памяти!)
            foreach ($sheet->getRowIterator() as $row) {
                $writer->addRow($row);
            }
        }
    }
    
    /**
     * Добавляет новый лист с данными из CSV файла.
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
        
        // Создаём CSV Reader
        $csvReader = ReaderEntityFactory::createCSVReader();
        $csvReader->setFieldDelimiter($delimiter);
        $csvReader->setFieldEnclosure($enclosure);
        $csvReader->open($csvPath);
        
        // Читаем CSV и записываем построчно в Excel
        // Потоковая обработка - не загружаем весь файл в память!
        foreach ($csvReader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $writer->addRow($row);
            }
        }
        
        // Закрываем CSV Reader
        $csvReader->close();
    }
}