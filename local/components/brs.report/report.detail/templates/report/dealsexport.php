<?php

// Блокируем прямой доступ к файлу
if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true){
    die(); 
}

\CJSCore::Init(['jquery']);
\Bitrix\Main\UI\Extension::load('ui.dialogs.messagebox');
\Bitrix\Main\UI\Extension::load('ui.buttons');
\Bitrix\Main\UI\Extension::load('ui.alerts');
\Bitrix\Main\UI\Extension::load('ui.forms');

$APPLICATION->SetTitle($arResult['TITLE']);

// Устанавливаем контентную часть внутрь заголовка
$this->SetViewTarget('inside_pagetitle');

?>
<div class="ui-alert ui-alert-xs ui-alert-icon-info">
    <span class="ui-alert-message">
        <strong>Внимание!</strong> 
        <?=$arResult['DESCRIPTION']?>
    </span>
</div>
<?

$this->EndViewTarget();

// Устанавливаем кнопку в заголовок
$this->SetViewTarget('pagetitle');

?>
<a href="#" id="deals-export-generate-btn" class="ui-btn ui-btn-primary">Сгенерировать отчёт</a>
<?

$this->EndViewTarget();

?>

<div class="deals-export-content" style="padding: 20px;">
    
    <!-- Блок фильтрации по датам -->
    <div class="deals-export-filter-section">
        <h2>Настройки отчёта</h2>
        
        <div class="ui-alert ui-alert-xs ui-alert-icon-info" style="margin-bottom: 20px;">
            <span class="ui-alert-message">
                💡 <strong>Подсказка:</strong> Оставьте поля дат пустыми для выгрузки всех сделок. 
                Укажите только одну дату для односторонней фильтрации.
            </span>
        </div>
        
        <div class="deals-export-date-filter">
            <div class="ui-ctl-label-text" style="font-weight: 600; margin-bottom: 15px; color: #535c69;">
                📅 Фильтр по дате создания сделок
            </div>
            
            <div class="date-inputs-wrapper">
                <div class="date-field-block">
                    <label class="ui-ctl-label-text">Дата начала (от)</label>
                    <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                        <input type="text" 
                               id="dateFrom" 
                               name="dateFrom" 
                               class="ui-ctl-element" 
                               placeholder="ДД.ММ.ГГГГ"
                               maxlength="10">
                    </div>
                    <div class="date-hint">Например: 01.01.2024</div>
                </div>
                
                <div class="date-field-block">
                    <label class="ui-ctl-label-text">Дата окончания (до)</label>
                    <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                        <input type="text" 
                               id="dateTo" 
                               name="dateTo" 
                               class="ui-ctl-element" 
                               placeholder="ДД.ММ.ГГГГ"
                               maxlength="10">
                    </div>
                    <div class="date-hint">Например: 31.12.2024</div>
                </div>
            </div>
        </div>
    </div>
    
    <hr style="margin: 30px 0; border: none; border-top: 1px solid #e0e8ef;">
    
    <h2>Описание отчёта</h2>
    
    <p>Универсальный отчёт по сделкам включает в себя:</p>
    
    <ul style="line-height: 1.8;">
        <li>Основные данные сделки (ID, название, дата создания, статус)</li>
        <li>Информацию о клиенте и ответственном менеджере</li>
        <li>Категорию и стадию сделки</li>
        <li>Связанные финансовые карты</li>
        <li>Данные о возвратах</li>
        <li>И другие поля из системы</li>
    </ul>
    
    <div class="ui-alert ui-alert-warning" style="margin-top: 20px;">
        <span class="ui-alert-message">
            <strong>Важно:</strong> 
            Генерация отчёта может занять несколько минут в зависимости от количества сделок и выбранного периода. 
            Файл отчёта будет перезаписан при каждой новой генерации.
        </span>
    </div>
    
</div>

<script>
$(document).ready(function(){
    
    // Форматирование ввода даты (автоматические точки)
    function formatDateInput(input) {
        $(input).on('input', function(e) {
            var value = e.target.value.replace(/\D/g, '');
            
            if (value.length >= 2) {
                value = value.substring(0, 2) + '.' + value.substring(2);
            }
            if (value.length >= 5) {
                value = value.substring(0, 5) + '.' + value.substring(5, 9);
            }
            
            e.target.value = value;
        });
    }
    
    // Применяем форматирование к полям дат
    formatDateInput('#dateFrom');
    formatDateInput('#dateTo');
    
    // Валидация формата даты
    function validateDateFormat(dateString) {
        if (!dateString) return true; // Пустая дата допустима
        
        var regex = /^\d{2}\.\d{2}\.\d{4}$/;
        if (!regex.test(dateString)) {
            return false;
        }
        
        var parts = dateString.split('.');
        var day = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var year = parseInt(parts[2], 10);
        
        if (month < 1 || month > 12) return false;
        if (day < 1 || day > 31) return false;
        if (year < 1900 || year > 2100) return false;
        
        // Проверка корректности даты
        var date = new Date(year, month - 1, day);
        return date.getFullYear() === year && 
               date.getMonth() === month - 1 && 
               date.getDate() === day;
    }
    
    // Обработчик клика по кнопке
    $('#deals-export-generate-btn').click(function(event){
        
        event.preventDefault();
        
        // Получаем значения дат
        var dateFrom = $('#dateFrom').val().trim();
        var dateTo = $('#dateTo').val().trim();
        
        // Валидация дат
        if (!validateDateFormat(dateFrom)) {
            BX.UI.Dialogs.MessageBox.alert(
                'Неверный формат даты "От". Используйте формат ДД.ММ.ГГГГ (например: 01.01.2024)',
                'Ошибка валидации'
            );
            $('#dateFrom').focus();
            return false;
        }
        
        if (!validateDateFormat(dateTo)) {
            BX.UI.Dialogs.MessageBox.alert(
                'Неверный формат даты "До". Используйте формат ДД.ММ.ГГГГ (например: 31.12.2024)',
                'Ошибка валидации'
            );
            $('#dateTo').focus();
            return false;
        }
        
        // Формируем текст подтверждения с информацией о периоде
        var confirmText = 'Запустить генерацию отчёта?';
        if (dateFrom || dateTo) {
            confirmText += '\n\nПериод: ';
            if (dateFrom) {
                confirmText += 'с ' + dateFrom + ' ';
            }
            if (dateTo) {
                confirmText += 'по ' + dateTo;
            }
        } else {
            confirmText += '\n\nБудут выгружены все сделки (без ограничения по датам).';
        }
        confirmText += '\n\nСсылка на скачивание придёт вам на email.';
        
        // Показываем подтверждение
        BX.UI.Dialogs.MessageBox.confirm(
            confirmText,
            function(messageBox, button) {
                
                // Показываем прелоадер
                BX.showWait();
                
                // AJAX запрос к контроллеру с параметрами дат
                BX.ajax.runAction('brs:report.api.DealsExportController.generate', {
                    data: {
                        dateFrom: dateFrom,
                        dateTo: dateTo
                    }
                }).then(function(response) {
                    
                    BX.closeWait();
                    messageBox.close();
                    
                    // Показываем успешное сообщение
                    BX.UI.Dialogs.MessageBox.alert(
                        response.data.message,
                        'Отчёт поставлен в очередь'
                    );
                    
                }, function(response) {
                    
                    BX.closeWait();
                    messageBox.close();
                    
                    // Показываем ошибку
                    var errorMessage = 'Произошла ошибка при запуске генерации';
                    
                    if (response.errors && response.errors.length > 0) {
                        errorMessage = response.errors[0].message;
                    }
                    
                    BX.UI.Dialogs.MessageBox.alert(
                        errorMessage,
                        'Ошибка'
                    );
                    
                });
                
            },
            'Да, запустить',
            function(messageBox) {
                messageBox.close();
            },
            'Отмена'
        );
        
        return false;
    });
    
});
</script>

<style>
.deals-export-content h2 {
    margin-bottom: 15px;
    color: #535c69;
    font-size: 18px;
    font-weight: 600;
}

.deals-export-content p {
    color: #535c69;
    line-height: 1.6;
}

.deals-export-content ul {
    padding-left: 25px;
    color: #535c69;
}

.deals-export-content ul li {
    margin-bottom: 5px;
}

.deals-export-filter-section {
    background: #f5f9fc;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border: 1px solid #e0e8ef;
}

.deals-export-date-filter {
    margin-top: 15px;
}

.date-inputs-wrapper {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.date-field-block {
    flex: 1;
    min-width: 250px;
}

.date-field-block .ui-ctl-label-text {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #535c69;
}

.date-field-block .ui-ctl {
    margin-bottom: 5px;
}

.date-hint {
    font-size: 12px;
    color: #959ca4;
    margin-top: 5px;
    font-style: italic;
}

/* Стили для полей ввода в фокусе */
.ui-ctl-textbox .ui-ctl-element:focus {
    border-color: #2fc6f6;
    box-shadow: 0 0 0 1px rgba(47, 198, 246, 0.3);
}

/* Адаптивность для мобильных */
@media (max-width: 768px) {
    .date-inputs-wrapper {
        flex-direction: column;
    }
    
    .date-field-block {
        min-width: 100%;
    }
}
</style>