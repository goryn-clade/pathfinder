/**
 *  user settings/share dialog
 */

define([
    'jquery',
    'app/init',
    'app/util',
    'bootbox'
], ($, Init, Util, bootbox) => {
    'use strict';

    let config = {
        settingsDialogId: 'pf-settings-dialog',
        settingsAccountContainerId: 'pf-settings-dialog-account',
        settingsShareContainerId: 'pf-settings-dialog-share',
        settingsCharacterContainerId: 'pf-settings-dialog-character',

        loadingOptions: {
            icon: {
                size: 'fa-2x'
            }
        }
    };

    /**
     * show "register/settings" dialog
     * @returns {boolean}
     */
    $.fn.showSettingsDialog = function(){

        // check if there are other dialogs open
        let openDialogs = Util.getOpenDialogs();
        if(openDialogs.length > 0){
            return false;
        }

        requirejs(['text!templates/dialog/settings.html', 'mustache'], function(template, Mustache){

            let data = {
                id: config.settingsDialogId,
                settingsAccountContainerId: config.settingsAccountContainerId,
                settingsShareContainerId: config.settingsShareContainerId,
                settingsCharacterContainerId: config.settingsCharacterContainerId,
                userData: Init.currentUserData,
                formErrorContainerClass: Util.config.formErrorContainerClass,
                ccpImageServer: Init.url.ccpImageServer,
                roleLabel: Util.getLabelByRole(Util.getCurrentCharacterData('role')).prop('outerHTML'),
                characterAutoLocationSelectEnabled: Boolean(Util.getObjVal(Init, 'character.autoLocationSelect')),
                hasRightCorporationShare: Util.hasRight('map_share', 'corporation')
            };

            let content = Mustache.render(template, data);

            let accountSettingsDialog = bootbox.dialog({
                title: 'Account settings',
                message: content,
                show: false,
                buttons: {
                    close: {
                        label: 'cancel',
                        className: 'btn-default'
                    },
                    success: {
                        label: '<i class="fas fa-check fa-fw"></i>&nbsp;save',
                        className: 'btn-success',
                        callback: function(){

                            // get the current active form
                            let form = $('#' + config.settingsDialogId).find('form').filter(':visible');

                            // validate form
                            form.validator('validate');

                            // check whether the form is valid
                            let formValid = form.isValidForm();

                            if(formValid === true){
                                let tabFormValues = form.getFormValues();

                                // send Tab data and store values
                                let requestData = {
                                    formData: tabFormValues
                                };

                                accountSettingsDialog.find('.modal-content').showLoadingAnimation();

                                $.ajax({
                                    type: 'POST',
                                    url: Init.path.saveUserConfig,
                                    data: requestData,
                                    dataType: 'json'
                                }).done(function(responseData){
                                    accountSettingsDialog.find('.modal-content').hideLoadingAnimation();

                                    if(responseData.error && responseData.error.length > 0){
                                        form.showFormMessage(responseData.error);
                                    }else{
                                        if(responseData.userData){
                                            Util.setCurrentUserData(responseData.userData);
                                        }

                                        Util.showNotify({title: 'Account saved', type: 'success'});

                                        Util.triggerMenuAction(document, 'Close');
                                        accountSettingsDialog.modal('hide');
                                    }
                                }).fail(function(jqXHR, status, error){
                                    accountSettingsDialog.find('.modal-content').hideLoadingAnimation();

                                    let reason = status + ' ' + error;
                                    Util.showNotify({title: jqXHR.status + ': saveAccountSettings', text: reason, type: 'error'});

                                    if(jqXHR.status === 500 && jqXHR.responseText){
                                        let errorObj = $.parseJSON(jqXHR.responseText);
                                        if(errorObj.error && errorObj.error.length > 0){
                                            form.showFormMessage(errorObj.error);
                                        }
                                    }

                                    $(document).setProgramStatus('problem');
                                });
                            }

                            return false;
                        }
                    }
                }
            });

            // after modal is shown =======================================================================
            accountSettingsDialog.on('shown.bs.modal', function(e){
                let dialogElement = $(this);
                let form = dialogElement.find('form');

                dialogElement.initTooltips();

                form.initFormValidation();

                // init "toggle" switches
                dialogElement.find('input[type="checkbox"][data-toggle="toggle"]').bootstrapToggle({
                    on: '<i class="fas fa-fw fa-check"></i>&nbsp;Enable',
                    off: 'Disable&nbsp;<i class="fas fa-fw fa-ban"></i>',
                    onstyle: 'success',
                    offstyle: 'warning',
                    width: 100,
                    height: 30
                });
            });

            // show dialog
            accountSettingsDialog.modal('show');
        });
    };
});