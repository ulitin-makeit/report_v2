<?php

namespace Brs\Report\Page;

/**
 * Обработчик страницы отчёта "Экспорт сделок".
 * 
 * Минимальный handler для страницы с кнопкой генерации CSV отчёта.
 * Отчёт генерируется асинхронно через агент и отправляется на email.
 */
class DealsExport extends AbstractPage {

    /**
     * Проверяет права на доступ пользователей к отчёту.
     * 
     * @return bool
     */
    public function checkRights(): bool {
        // Пока доступ для всех авторизованных пользователей
        // Можно добавить проверку прав через $USER->GetID() или роли
        return true;
    }

    /**
     * Метод формирует данные для отчёта и возвращает в "arResult".
     * 
     * @param object $reportObject ORM объект отчёта из таблицы brs_report
     * @return array Данные для шаблона
     */
    public function getData(object $reportObject): array {
        
        // Для этого отчёта нужен только заголовок
        // Остальная логика в шаблоне (кнопка) и контроллере (AJAX)
        return [
            'TITLE' => $reportObject->getTitle(),
            'DESCRIPTION' => 'Генерация полного отчёта по всем сделкам в формате CSV. ' .
                           'После нажатия кнопки отчёт будет сгенерирован в фоновом режиме, ' .
                           'и ссылка на скачивание придёт вам на email.'
        ];
    }
}