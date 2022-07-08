/**
 * Main "admin" page
 */

define([
    'jquery',
    'app/init',
    'app/util',
    'datatables.loader'
], ($, Init, Util, dtLoader) => {

    'use strict';

    let config = {
        splashOverlayClass: 'pf-splash',                                        // class for "splash" overlay
        triggerOverlayClass: 'pf-overlay-trigger'                               // class for
    };


    /**
     * set page observer
     */
    let setPageObserver = () => {
        $('.' + config.triggerOverlayClass).on('click', function(e){
            $('.' + config.splashOverlayClass).showSplashOverlay();
        });

        $('body').initTooltips();

        // set fieldset toggled by checkbox ---------------------------------------------------------------------------
        $('input[type="checkbox"][data-target]').on('change', function(){
            let targetId = $(this).attr('data-target');
            if(targetId){
                let targetElement = $('[data-id="' + targetId + '"]');
                let targetFormFields = targetElement.find('input[type="radio"]');
                let checkFormFields = [];
                for(let formField of targetFormFields){
                    if(this.checked){
                        if(formField.hasAttribute('data-default') || formField.getAttribute('data-default-value')){
                            checkFormFields.push(formField);
                        }
                    }else{
                        formField.setAttribute('data-default-value', formField.checked ? 'checked' : '');
                        if(formField.hasAttribute('data-default')){
                            checkFormFields.push(formField);
                        }
                    }
                }

                for(let checkFormField of checkFormFields){
                    checkFormField.checked = true;
                }
            }
        });

        $('input[type="radio"]').on('change', function(){
            if(this.checked){
                let targetId = $(this).parents('fieldset').attr('data-id');
                $('input[type="checkbox"][data-target="' + targetId + '"]').prop('checked', true);
            }
        });
    };

    /**
     * main init "admin" page
     */
    $(() => {
        // set Dialog default config
        Util.initDefaultBootboxConfig();

        // hide splash loading animation
        $('.' + config.splashOverlayClass + '[data-status="ok"]').hideSplashOverlay();

        setPageObserver();

        dtLoader.initDefaultConfig({
            breakpoints: Init.breakpoints
        }).then(() => {
            let temp = $('.dataTable').dataTable({
                pageLength: 100,
                paging: true,
                ordering: true,
                autoWidth: false,
                hover: false,
                language: {
                    emptyTable:  __('No entries'),
                    zeroRecords: __('No entries found'),
                    lengthMenu:  __('Show _MENU_ entries'),
                    info:        __('Showing _START_ to _END_ of _TOTAL_ entries')
                },
                data: null      // use DOM data overwrites [] default -> data.loader.js
            });
        });
    });
});