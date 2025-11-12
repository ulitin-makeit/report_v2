<?
$pagetitleFlexibleSpace = "lists-pagetitle-flexible-space";
$pagetitleAlignRightContainer = "lists-align-right-container";
?>
<div class="pagetitle-container pagetitle-flexible-space <?=$pagetitleFlexibleSpace?>">
    <div class="summury-table">
        <table class="main-grid-table">
            <thead class="main-grid-header">
                <tr  class="main-grid-row-head">
                    <th class="main-grid-cell-head main-grid-cell-static">
                        <span class="main-grid-cell-head-container"><span class="main-grid-head-title">ID</span></span>
                    </th>
                    <th class="main-grid-cell-head main-grid-cell-static" style="width:90%">
                        <span class="main-grid-cell-head-container"><span class="main-grid-head-title">Название</span></span>
                    </th>
                    <th class="main-grid-cell-head main-grid-cell-static">
                    </th>
                </tr>
            </thead>
            <tbody>
                <?foreach($arResult as $report){?>
                <tr class="main-grid-row main-grid-row-body" onclick="window.location.href='?report=<?=$report->getCode()?>'">
                    <td class="main-grid-cell main-grid-cell-left" data-editable="true">
                        <span class="main-grid-cell-content" data-prevent-default="true"><?=$report->getId()?></span>
                    </td>
                    <td class="main-grid-cell main-grid-cell-left" data-editable="true">
                        <span class="main-grid-cell-content" data-prevent-default="true"><a href="?report=<?=$report->getCode()?>"><?=$report->getTitle()?></a></span>
                    </td>
                    <td class="main-grid-cell main-grid-cell-left" data-editable="true">
                        <span class="main-grid-cell-content" data-prevent-default="true">
                            <a href="?report=<?=$report->getCode()?>" class="ui-btn ui-btn-lg brs-report-open-button">Сформировать</a>
                        </span>
                    </td>
                </tr>
                <?}?>
            </tbody>
        </table>
    </div>
</div>