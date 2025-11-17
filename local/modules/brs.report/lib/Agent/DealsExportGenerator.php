<?php

namespace Brs\Report\Agent;

use Bitrix\Main\Loader;

/**
 * Агент для генерации CSV отчёта по сделкам.
 * 
 * Запускается один раз через \CAgent::AddAgent() и удаляется после выполнения.
 */
class DealsExportGenerator {

    /**
     * Генерирует CSV отчёт и отправляет ссылку на email.
     * 
     * @param string $userEmail Email пользователя для отправки ссылки
     * @return string Пустая строка = агент выполнен и больше не повторяется
     */
    public static function generate(string $userEmail): string {
        
        try {
            
            // Подключаем модуль с генератором отчётов
            if (!Loader::includeModule('brs.reportuniversal')) {
                throw new \Exception('Модуль brs.reportuniversal не установлен');
            }
            
            // Создаём директорию для отчётов если её нет
            $reportDir = $_SERVER['DOCUMENT_ROOT'] . "/upload/reports/";
            if (!is_dir($reportDir)) {
                mkdir($reportDir, 0755, true);
            }
            
            // Путь к файлу отчёта (ОДИН файл на всю систему, перезаписывается)
            $filePath = $reportDir . "universal_report.csv";
            
            // Генерируем отчёт через класс из модуля brs.reportuniversal
            $generator = new \Brs\ReportUniversal\DealsReportGenerator($filePath);
            $generator->generate();
            
            // Формируем прямую ссылку на файл
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $fileUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/upload/reports/universal_report.csv";
            
            // Отправляем email с ссылкой
            \CEvent::Send(
                'DEALS_EXPORT_REPORT_READY',
                's1',
                [
                    'EMAIL' => $userEmail,
                    'FILE_URL' => $fileUrl,
                    'DATE' => date('d.m.Y H:i'),
                    'FILE_SIZE' => self::formatFileSize(filesize($filePath))
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