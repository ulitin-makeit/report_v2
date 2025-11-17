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
        
        // Добавляем разовый агент на генерацию отчёта
        \CAgent::AddAgent(
            "\\Brs\\Report\\Agent\\DealsExportGenerator::generate('{$userEmail}');",
            "brs.report",           // модуль
            "N",                     // не проверять существование модуля
            60,                      // выполнить через 60 секунд
            "",                      // дата первой проверки
            "Y",                     // активен
            "",                      // дата первого запуска
            30                       // сортировка
        );
        
 return AjaxJson::createSuccess([
            'message' => 'Генерация отчёта запущена. Ссылка на скачивание придёт на ваш email в течение нескольких минут.',
            'email' => $userEmail
        ]);
    }
}