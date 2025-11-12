class Universal {
	
	/**
	 * Выводит форму с параметрами обновления отчёта.
	 * 
	 * @returns void
	 */
	renderUpdatePopup(){
		// выводим на экран форму
		const messageBox = new BX.UI.Dialogs.MessageBox({
			
			message: `
<!-- .ui-ctl.ui-ctl-after-icon.ui-ctl-dropdown > 
		div.ui-ctl-after.ui-ctl-icon-angle + 
		select.ui-ctl > option -->
	<div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100">
		<div class="ui-ctl-after ui-ctl-icon-angle"></div>
		<select id="brsUniversalUpdateType" class="ui-ctl-element">
			<option value="changedDeal">Обновить изменённые сделки в отчёте</option>
			<option value="all">Обновить всё</option>
		</select>
	</div>

`,
			title: 'Выберите параметры обновления отчёта',
			modal: true,
			minWidth: 480,
			maxWidth: 480,
			buttons: BX.UI.Dialogs.MessageBoxButtons.OK_CANCEL,
			okCaption: 'Обновить',
			cancelCaption: 'Отменить',
			
			onOk: (function(messageBox, button){
				
				var operationType = $('#brsUniversalUpdateType').val();
				
				this.updatePopupAjax(messageBox, operationType);
				
				button.setDisabled(false);
				
			}).bind(this),
			onCancel: (function(){
				
				messageBox.close();
				
			}).bind(this)
			
		});

		messageBox.show();
		
	}
	
	/**
	 * Обновляем отчёт.
	 * 
	 * @param object messageBox
	 * @param string operationType
	 * @returns void
	 */
	updatePopupAjax(messageBox, operationType){
		
		BX.showWait();
		
		let request = BX.ajax.runAction('brs:report.api.UniversalController.update', {
			data: {
				operationType: operationType
			}
		});

		request.then((response) => {

			messageBox.close();

			BX.closeWait();
			
			BX.Main.gridManager.getById('brsReportUniversalList').instance.reloadTable('POST', { apply_filter: 'Y', clear_nav: 'Y' });

		}, this.moveSectionAjaxError);
		
	}
	
}

class ForeignCard {

    /**
     * Выводит форму с параметрами обновления отчёта.
     *
     * @returns void
     */
    renderUpdatePopup() {
        // выводим на экран форму
        const messageBox = new BX.UI.Dialogs.MessageBox({

            message: `
<!-- .ui-ctl.ui-ctl-after-icon.ui-ctl-dropdown > 
		div.ui-ctl-after.ui-ctl-icon-angle + 
		select.ui-ctl > option -->
	<div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100">
		<div class="ui-ctl-after ui-ctl-icon-angle"></div>
		<select id="brsForeignCardUpdateType" class="ui-ctl-element">
			<option value="changedDeal">Обновить изменённые сделки в отчёте</option>
			<option value="all">Обновить всё</option>
		</select>
	</div>

`,
            title: 'Выберите параметры обновления отчёта',
            modal: true,
            minWidth: 480,
            maxWidth: 480,
            buttons: BX.UI.Dialogs.MessageBoxButtons.OK_CANCEL,
            okCaption: 'Обновить',
            cancelCaption: 'Отменить',

            onOk: (function (messageBox, button) {

                var operationType = $('#brsForeignCardUpdateType').val();

                this.updatePopupAjax(messageBox, operationType);

                button.setDisabled(false);

            }).bind(this),
            onCancel: (function () {

                messageBox.close();

            }).bind(this)

        });

        messageBox.show();

    }

    /**
     * Обновляем отчёт.
     *
     * @param object messageBox
     * @param string operationType
     * @returns void
     */
    updatePopupAjax(messageBox, operationType) {
        BX.showWait();

        let request = BX.ajax.runAction('brs:report.api.ForeignCardController.update', {
            data: {
                operationType: operationType
            }
        });

        request.then((response) => {

            messageBox.close();

            BX.closeWait();
            console.log('3')
            BX.Main.gridManager.getById('brsForeignCardList').instance.reloadTable('POST', {
                apply_filter: 'Y',
                clear_nav: 'Y'
            });

        }, this.moveSectionAjaxError);

    }

}