<?php
/**
 * Скрипт для безопасного скачивания файла отчета по сделкам
 * 
 * Путь установки: /local/tools/download_deals_report.php
 * 
 * Функционал:
 * - Проверка авторизации пользователя
 * - Проверка существования файла
 * - Логирование скачиваний
 * - Установка корректных заголовков для скачивания
 * - Защита от несанкционированного доступа
 */

// Подключаем ядро Битрикс
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Application;

// Проверяем авторизацию пользователя
global $USER;

if (!$USER->IsAuthorized()) {
    // Перенаправляем на страницу авторизации
    LocalRedirect('/auth/?backurl=' . urlencode($_SERVER['REQUEST_URI']));
    die();
}

// Получаем параметры из запроса
$fileName = isset($_GET['file']) ? $_GET['file'] : '';
$reportType = isset($_GET['type']) ? $_GET['type'] : 'deals';

// Валидация имени файла (защита от path traversal)
if (empty($fileName) || preg_match('/[^a-zA-Z0-9_\-\.]/', $fileName)) {
    ShowError('Некорректное имя файла');
    die();
}

// Белый список разрешенных файлов для скачивания
$allowedFiles = [
    'universal_report.xlsx',
    'deals_report.xlsx',
    'export_report.xlsx'
];

if (!in_array($fileName, $allowedFiles)) {
    ShowError('Файл не разрешен для скачивания');
    die();
}

// Путь к директории с отчетами
$reportsDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/reports/';

// Полный путь к файлу
$filePath = $reportsDir . $fileName;

// Проверяем существование файла
if (!file_exists($filePath)) {
    ShowError('Файл не найден. Возможно, отчет еще не сгенерирован или был удален.');
    die();
}

// Проверяем права доступа к файлу
if (!is_readable($filePath)) {
    ShowError('Нет доступа к файлу');
    die();
}

// Получаем информацию о файле
$fileSize = filesize($filePath);
$fileTime = filemtime($filePath);

// Проверяем актуальность файла (не старше 7 дней)
$maxFileAge = 7 * 24 * 60 * 60; // 7 дней в секундах
if (time() - $fileTime > $maxFileAge) {
    ShowError('Файл устарел. Пожалуйста, сгенерируйте новый отчет.');
    die();
}

// Логируем скачивание
logDownload($USER->GetID(), $USER->GetEmail(), $fileName, $fileSize);

// Формируем имя файла для скачивания с текущей датой
$downloadFileName = 'deals_export_' . date('Y-m-d_H-i') . '.xlsx';

// Устанавливаем заголовки для скачивания файла
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

// Очищаем буфер вывода
if (ob_get_level()) {
    ob_clean();
}
flush();

// Отдаем файл порциями для экономии памяти
$handle = fopen($filePath, 'rb');
if ($handle === false) {
    ShowError('Ошибка открытия файла');
    die();
}

// Читаем и отдаем файл по 8 КБ
while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}

fclose($handle);

// Завершаем выполнение скрипта
exit;

/**
 * Функция для логирования скачиваний
 * 
 * @param int $userId ID пользователя
 * @param string $userEmail Email пользователя
 * @param string $fileName Имя файла
 * @param int $fileSize Размер файла
 */
function logDownload(int $userId, string $userEmail, string $fileName, int $fileSize): void
{
    $logDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/reports/';
    $logFile = $logDir . 'downloads.log';
    
    // Формируем запись лога
    $logEntry = sprintf(
        "[%s] User ID: %d | Email: %s | File: %s | Size: %s | IP: %s\n",
        date('Y-m-d H:i:s'),
        $userId,
        $userEmail,
        $fileName,
        formatFileSize($fileSize),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    );
    
    // Записываем в лог
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Форматирует размер файла в читаемый вид
 * 
 * @param int $bytes Размер в байтах
 * @return string Отформатированный размер
 */
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}