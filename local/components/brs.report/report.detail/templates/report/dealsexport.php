<?php

// Блокируем прямой доступ к файлу
if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true){
    die(); 
}

\CJSCore::Init(['jquery']);
\Bitrix\Main\UI\Extension::load('ui.dialogs.messagebox');
\Bitrix\Main\UI\Extension::load('ui.buttons');
\Bitrix\Main\UI\Extension::load('ui.alerts');

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
            Генерация отчёта может занять несколько минут в зависимости от количества сделок. 
            Файл отчёта будет перезаписан при каждой новой генерации.
        </span>
    </div>
    
</div>

<script>
$(document).ready(function(){
    
    $('#deals-export-generate-btn').click(function(event){
        
        event.preventDefault();
        
        // Показываем подтверждение
        BX.UI.Dialogs.MessageBox.confirm(
            'Запустить генерацию отчёта? Ссылка на скачивание придёт вам на email.',
            function(messageBox, button) {
                
                // Показываем прелоадер
                BX.showWait();
                
                // AJAX запрос к контроллеру
                BX.ajax.runAction('brs:report.api.DealsExportController.generate', {
                    data: {}
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

.deals-export-content ul {
    padding-left: 25px;
    color: #535c69;
}

.deals-export-content ul li {
    margin-bottom: 5px;
}
</style>