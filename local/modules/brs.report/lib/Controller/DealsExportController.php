<?php

namespace Brs\Report\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\Response\AjaxJson;

/**
 * Контроллер для работы с экспортом сделок.
 * 
 * Обрабатывает AJAX запросы со страницы отчёта.
 */
class DealsExportController extends Controller {

    /**
     * Запускает генерацию отчёта через агент.
     * 
     * Вызывается при клике на кнопку "Сгенерировать отчёт".
     * Создаёт разовый агент, который выполнится через ~1 минуту.
     * 
     * @return AjaxJson
     */
    public function generateAction(): AjaxJson {
        
        global $USER;
        
        // Проверяем авторизацию
        if (!$USER->IsAuthorized()) {
            return AjaxJson::createError([
                'message' => 'Необходима авторизация'
            ]);
        }
        
        // Получаем email пользователя
        $userEmail = $USER->GetEmail();
        
        if (empty($userEmail)) {
            return AjaxJson::createError([
                'message' => 'У вашего профиля не указан email'
            ]);
        }
        
        // Получаем параметры дат из запроса
        $dateFrom = $this->request->getPost('dateFrom');
        $dateTo = $this->request->getPost('dateTo');
        
        // Валидация дат
        if (!empty($dateFrom) && !$this->validateDate($dateFrom)) {
            return AjaxJson::createError([
                'message' => 'Неверный формат даты "От". Используйте формат ДД.ММ.ГГГГ'
            ]);
        }
        
        if (!empty($dateTo) && !$this->validateDate($dateTo)) {
            return AjaxJson::createError([
                'message' => 'Неверный формат даты "До". Используйте формат ДД.ММ.ГГГГ'
            ]);
        }
        
        // Формируем параметры для агента
        $dateFromParam = !empty($dateFrom) ? $dateFrom : '';
        $dateToParam = !empty($dateTo) ? $dateTo : '';
        
        // Добавляем разовый агент на генерацию отчёта с параметрами дат
        \CAgent::AddAgent(
            "\\Brs\\Report\\Agent\\DealsExportGenerator::generate('{$userEmail}', '{$dateFromParam}', '{$dateToParam}');",
            "brs.report",           // модуль
            "N",                     // не проверять существование модуля
            60,                      // выполнить через 60 секунд
            "",                      // дата первой проверки
            "Y",                     // активен
            "",                      // дата первого запуска
            30                       // сортировка
        );
        
        $dateInfo = '';
        if (!empty($dateFromParam) || !empty($dateToParam)) {
            $dateInfo = ' за период';
            if (!empty($dateFromParam)) {
                $dateInfo .= ' с ' . $dateFromParam;
            }
            if (!empty($dateToParam)) {
                $dateInfo .= ' по ' . $dateToParam;
            }
        }
        
        return AjaxJson::createSuccess([
            'message' => 'Генерация отчёта' . $dateInfo . ' запущена. Ссылка на скачивание придёт на ваш email в течение нескольких минут.',
            'email' => $userEmail
        ]);
    }
    
    /**
     * Валидирует дату в формате ДД.ММ.ГГГГ
     * 
     * @param string $date Дата для проверки
     * @return bool
     */
    private function validateDate(string $date): bool {
        // Проверяем формат ДД.ММ.ГГГГ
        if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            return false;
        }
        
        // Проверяем что дата корректна
        $parts = explode('.', $date);
        return checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2]);
    }
}