<?php

namespace Brs\Report\Helper;

/**
 * Класс для встраивания CSV данных в Excel файл.
 * 
 * Использует ZipArchive + XML для работы с XLSX без дополнительных библиотек.
 * XLSX файл - это ZIP архив с XML файлами внутри.
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
        
        // Копируем шаблон в итоговый файл
        if (!copy($templatePath, $outputPath)) {
            throw new \Exception('Не удалось скопировать шаблон Excel');
        }
        
        $zip = new \ZipArchive();
        
        if ($zip->open($outputPath) !== true) {
            throw new \Exception('Не удалось открыть Excel файл как ZIP архив');
        }
        
        try {
            
            // Получаем информацию о существующих листах
            $sheetInfo = self::getSheetInfo($zip);
            
            // Создаём XML для нового листа из CSV
            $sheetXml = self::createSheetXmlFromCsv($csvPath, $csvDelimiter, $csvEnclosure);
            
            // Добавляем новый лист в архив
            self::addNewSheet($zip, $sheetInfo, $sheetXml, $newSheetName);
            
        } catch (\Exception $e) {
            $zip->close();
            throw $e;
        }
        
        // Закрываем архив
        $zip->close();
    }
    
    /**
     * Получает информацию о существующих листах в Excel файле.
     * 
     * @param \ZipArchive $zip Открытый ZIP архив Excel файла
     * @return array Массив с данными о листах ['count' => количество, 'lastRId' => последний ID связи]
     * @throws \Exception
     */
    private static function getSheetInfo(\ZipArchive $zip): array {
        
        // Читаем workbook.xml
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            throw new \Exception('Не удалось прочитать xl/workbook.xml');
        }
        
        $workbook = simplexml_load_string($workbookXml);
        if ($workbook === false) {
            throw new \Exception('Не удалось распарсить xl/workbook.xml');
        }
        
        // Регистрируем namespace
        $workbook->registerXPathNamespace('ns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        
        $sheets = $workbook->xpath('//ns:sheet');
        
        return [
            'count' => count($sheets),
            'workbook' => $workbook
        ];
    }
    
    /**
     * Добавляет новый лист в Excel файл.
     * 
     * @param \ZipArchive $zip Открытый ZIP архив
     * @param array $sheetInfo Информация о существующих листах
     * @param string $sheetXml XML содержимое нового листа
     * @param string $sheetName Название нового листа
     * @return void
     * @throws \Exception
     */
    private static function addNewSheet(\ZipArchive $zip, array $sheetInfo, string $sheetXml, string $sheetName): void {
        
        $workbook = $sheetInfo['workbook'];
        $existingSheetsCount = $sheetInfo['count'];
        
        $newSheetId = $existingSheetsCount + 1;
        $newSheetRId = 'rId' . ($newSheetId + 1);
        
        // Шаг 1: Добавляем файл нового листа
        $zip->addFromString("xl/worksheets/sheet{$newSheetId}.xml", $sheetXml);
        
        // Шаг 2: Обновляем xl/workbook.xml
        $workbook->registerXPathNamespace('ns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        
        $sheetsNode = $workbook->xpath('//ns:sheets')[0];
        
        $newSheet = $sheetsNode->addChild('sheet', '', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $newSheet->addAttribute('name', $sheetName);
        $newSheet->addAttribute('sheetId', $newSheetId);
        $newSheet->addAttribute('r:id', $newSheetRId, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        
        $zip->addFromString('xl/workbook.xml', $workbook->asXML());
        
        // Шаг 3: Обновляем xl/_rels/workbook.xml.rels
        self::updateWorkbookRels($zip, $newSheetId, $newSheetRId);
        
        // Шаг 4: Обновляем [Content_Types].xml
        self::updateContentTypes($zip, $newSheetId);
    }
    
    /**
     * Обновляет файл связей workbook.xml.rels.
     * 
     * @param \ZipArchive $zip Открытый ZIP архив
     * @param int $sheetId ID нового листа
     * @param string $rId Relationship ID
     * @return void
     * @throws \Exception
     */
    private static function updateWorkbookRels(\ZipArchive $zip, int $sheetId, string $rId): void {
        
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml === false) {
            throw new \Exception('Не удалось прочитать xl/_rels/workbook.xml.rels');
        }
        
        $rels = simplexml_load_string($relsXml);
        if ($rels === false) {
            throw new \Exception('Не удалось распарсить xl/_rels/workbook.xml.rels');
        }
        
        $newRel = $rels->addChild('Relationship', '', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $newRel->addAttribute('Id', $rId);
        $newRel->addAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet');
        $newRel->addAttribute('Target', "worksheets/sheet{$sheetId}.xml");
        
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels->asXML());
    }
    
    /**
     * Обновляет файл типов содержимого [Content_Types].xml.
     * 
     * @param \ZipArchive $zip Открытый ZIP архив
     * @param int $sheetId ID нового листа
     * @return void
     * @throws \Exception
     */
    private static function updateContentTypes(\ZipArchive $zip, int $sheetId): void {
        
        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        if ($contentTypesXml === false) {
            throw new \Exception('Не удалось прочитать [Content_Types].xml');
        }
        
        $contentTypes = simplexml_load_string($contentTypesXml);
        if ($contentTypes === false) {
            throw new \Exception('Не удалось распарсить [Content_Types].xml');
        }
        
        $newOverride = $contentTypes->addChild('Override', '', 'http://schemas.openxmlformats.org/package/2006/content-types');
        $newOverride->addAttribute('PartName', "/xl/worksheets/sheet{$sheetId}.xml");
        $newOverride->addAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml');
        
        $zip->addFromString('[Content_Types].xml', $contentTypes->asXML());
    }
    
    /**
     * Создаёт XML содержимое листа Excel из CSV файла.
     * 
     * @param string $csvPath Путь к CSV файлу
     * @param string $delimiter Разделитель CSV
     * @param string $enclosure Символ обрамления CSV
     * @return string XML содержимое листа
     * @throws \Exception
     */
    private static function createSheetXmlFromCsv(string $csvPath, string $delimiter, string $enclosure): string {
        
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ';
        $xml .= 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
        $xml .= '<sheetData>' . "\n";
        
        $handle = fopen($csvPath, "r");
        if ($handle === false) {
            throw new \Exception('Не удалось открыть CSV файл');
        }
        
        $rowIndex = 1;
        
        while (($data = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false) {
            
            $xml .= '<row r="' . $rowIndex . '">' . "\n";
            
            $colIndex = 0;
            foreach ($data as $cellValue) {
                
                $colLetter = self::getColumnLetter($colIndex);
                $cellRef = $colLetter . $rowIndex;
                
                // Определяем тип данных
                if (is_numeric($cellValue) && strpos($cellValue, '.') !== false) {
                    // Число с плавающей точкой
                    $cellValue = str_replace(',', '.', $cellValue);
                    $xml .= '<c r="' . $cellRef . '"><v>' . htmlspecialchars($cellValue, ENT_XML1, 'UTF-8') . '</v></c>' . "\n";
                } elseif (is_numeric($cellValue)) {
                    // Целое число
                    $xml .= '<c r="' . $cellRef . '"><v>' . htmlspecialchars($cellValue, ENT_XML1, 'UTF-8') . '</v></c>' . "\n";
                } else {
                    // Текст (inlineStr)
                    $escapedValue = htmlspecialchars($cellValue, ENT_XML1, 'UTF-8');
                    $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $escapedValue . '</t></is></c>' . "\n";
                }
                
                $colIndex++;
            }
            
            $xml .= '</row>' . "\n";
            $rowIndex++;
        }
        
        fclose($handle);
        
        $xml .= '</sheetData>' . "\n";
        $xml .= '</worksheet>';
        
        return $xml;
    }
    
    /**
     * Преобразует числовой индекс колонки в буквенное обозначение Excel.
     * 
     * @param int $index Индекс колонки (0 = A, 1 = B, 26 = AA и т.д.)
     * @return string Буквенное обозначение (A, B, C, ..., Z, AA, AB, ...)
     */
    private static function getColumnLetter(int $index): string {
        
        $letter = '';
        
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = floor($index / 26) - 1;
        }
        
        return $letter;
    }
}