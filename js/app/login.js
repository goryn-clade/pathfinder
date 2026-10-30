/**
 * Main loginPage application
 */

define([
    'jquery',
    'app/init',
    'app/util',
    'blueImpGallery',
    'layout/header_login',
    'lazyload',
    'bootbox',
    'dialog/account_settings',
    'dialog/notification',
    'dialog/manual',
    'dialog/changelog',
    'dialog/credit',
    'dialog/api_status',
    'mustache'                                                                  // bundled here: dialogs and Util load it with requirejs()
], ($, Init, Util, Gallery, HeaderLogin, LazyLoad, bootbox) => {

    'use strict';

    let config = {
        splashOverlayClass: 'pf-splash',                                        // class for "splash" overlay

        // header
        headerId: 'pf-landing-top',                                             // id for header
        headerContainerId: 'pf-header-container',                               // id for header container

        // navigation
        navigationElementId: 'pf-navbar',                                       // id for navbar element
        navigationLinkManualClass: 'pf-navbar-manual',                          // class for "manual" trigger link
        navigationLinkLicenseClass: 'pf-navbar-license',                        // class for "license" trigger link
        navigationVersionLinkClass: 'pf-navbar-version-info',                   // class for "version information"

        // cookie hint
        cookieHintId: 'pf-cookie-hint',                                         // id for "cookie hint" element

        // login
        ssoButtonClass: 'pf-sso-login-button',                                  // class for SSO login button

        // character select
        characterSelectionClass: 'pf-character-selection',                      // class for character boxes
        characterBoxClass: 'pf-character-box',                                  // class for outer character box
        characterImageWrapperClass: 'pf-character-image-wrapper',               // class for image wrapper (animated)
        characterImageInfoClass: 'pf-character-info',                           // class for character info layer (visible on hover)
        dynamicMessageContainerClass: 'pf-dynamic-message-container',           // class for "dynamic" (JS) message container

        // gallery
        galleryId: 'pf-gallery',                                                // id for gallery container
        galleryThumbImageClass: 'pf-landing-image-preview',                     // class for gallery thumb images
        galleryThumbContainerId: 'pf-landing-gallery-thumb-container',          // id for gallery thumb images

        // notification panel
        notificationPanelId: 'pf-notification-panel',                           // id for "notification panel" (e.g. last update information)

        // sticky panel
        stickyPanelClass: 'pf-landing-sticky-panel',                            // class for sticky panels
        stickyPanelServerId: 'pf-landing-server-panel',                         // id for EVE Online server status panel
        stickyPanelAdminId: 'pf-landing-admin-panel',                           // id for admin login panel

        apiStatusTriggerClass: 'pf-api-status-trigger',                         // class for "api status" dialog trigger elements

        // animation
        animateElementClass: 'pf-animate-on-visible',                           // class for elements that will be animated to show

        defaultAcceptCookieExpire: 365                                          // default expire for "accept coolies" cookie
    };

    /**
     * set link observer for "version info" dialog
     */
    let setVersionLinkObserver = () => {
        $('.' + config.navigationVersionLinkClass).off('click').on('click', function(e){
            $.fn.changelogsDialog();
        });
    };

    /**
     * move panel out of "cookie" accept hint
     * @param direction
     */
    let moveAdminPanel = (direction) => {
        let adminPanel = $('#' + config.stickyPanelAdminId);
        adminPanel.css({bottom: ((direction === 'up') ? '+' : '-') + '=35px'});
    };

    let setAcceptCookie = () => {
        Util.setCookie('cookie', 1, config.defaultAcceptCookieExpire, 'd');
    };

    /**
     * set page observer
     */
    let setPageObserver = () => {
        let ssoButtonElement = $('.' + config.ssoButtonClass);
        let cookieHintElement = $('#' + config.cookieHintId);

        // character switch -> send as POST with cookie name + token (server refuses GET)
        $(document).on('click', '.' + config.characterSelectionClass + ' a[data-method="post"]', function(e){
            e.preventDefault();
            $('.' + config.splashOverlayClass).showSplashOverlay();
            let form = $('<form>', {method: 'post', action: this.getAttribute('href')})
                .append($('<input>', {type: 'hidden', name: 'cookie', value: this.dataset.cookie}))
                .append($('<input>', {type: 'hidden', name: 'token', value: this.dataset.token}));
            form.appendTo('body')[0].submit();
        });

        $(document).on('click', '.' + config.ssoButtonClass , function(){
            if(Util.getCookie('cookie') === '1'){
                // ... cookies accepted no "confirm" shown
                $('.' + config.splashOverlayClass).showSplashOverlay();
            }
        });

        // cookie hint --------------------------------------------------------
        cookieHintElement.find('.btn-success').on('click', function(){
            setAcceptCookie();
            // confirmation no longer needed on SSO login button
            ssoButtonElement.confirmation('destroy');
        });

        cookieHintElement.on('show.bs.collapse', function(){
            // move admin panel upwards (prevents overlapping with cookie notice)
            moveAdminPanel('up');
        });

        cookieHintElement.on('hidden.bs.collapse', function(){
            moveAdminPanel('down');
        });

        if(Util.getCookie('cookie') !== '1'){
            // hint not excepted
            cookieHintElement.collapse('show');

            // show Cookie accept hint on SSO login button
            let confirmationSettings = {
                placement: 'bottom',
                title: 'Accept cookies',
                btnCancelClass: 'btn btn-sm btn-success',
                btnCancelLabel: 'accept',
                btnCancelIcon: 'fas fa-fw fa-check',
                btnOkClass: 'btn btn-sm btn-default',
                btnOkLabel: 'dismiss',
                btnOkIcon: 'fas fa-fw fa-sign-in-alt',
                onCancel: function(e, target){
                    // "Accept cookies"
                    setAcceptCookie();

                    // set "default" href
                    let href = $(target).data('bs.confirmation').getHref();
                    $(e.target).attr('href', href);
                },
                onConfirm : function(e, target){
                    // "NO cookies" => trigger "default" href link action
                },
                href: function(target){
                    return $(target).attr('href');
                }
            };

            ssoButtonElement.confirmation(confirmationSettings);
        }

        // manual -------------------------------------------------------------
        $('.' + config.navigationLinkManualClass).on('click', function(e){
            e.preventDefault();
            $.fn.showMapManual();
        });

        // license ------------------------------------------------------------
        $('.' + config.navigationLinkLicenseClass).on('click', function(e){
            e.preventDefault();
            $.fn.showCreditsDialog();
        });

        // releases -----------------------------------------------------------
        setVersionLinkObserver();

        // tooltips -----------------------------------------------------------
        let mapTooltipOptions = {
            toggle: 'tooltip',
            delay: 150
        };

        let tooltipElements = $('[title]').not('.slide img');
        tooltipElements.tooltip(mapTooltipOptions);

        // initial show some tooltips
        tooltipElements.filter('[data-show="1"]').tooltip('show');
    };

    /**
     * get all thumbnail elements
     * @returns {*|jQuery|HTMLElement}
     */
    let getThumbnailElements = () => {
        return $('a[data-gallery="#' + config.galleryId + '"]').not('.disabled');
    };

    /**
     * init gallery for thumbnail elements
     * @param newElements
     */
    let initGallery = (newElements) => {
        if( newElements.length > 0){
            // We have to add ALL thumbnail elements to the gallery!
            // -> even those which are invisible (not lazyLoaded) now!
            // -> This is required for "swipe" through all images
            let allThumbLinks = getThumbnailElements();

            requirejs(['blueImpGalleryBootstrap'], () => {
                $(newElements).each(function(){
                    let borderless = false;

                    let galleryElement = $('#' + config.galleryId);
                    galleryElement.data('useBootstrapModal', !borderless);
                    galleryElement.toggleClass('blueimp-gallery-controls', borderless);

                    $(this).on('click', function(e){
                        e.preventDefault();

                        e = e || window.event;
                        let target = e.target || e.srcElement;
                        let link = target.src ? target.parentNode : target;

                        let options = {
                            index: link,
                            event: e,
                            container: '#' + config.galleryId,
                            titleProperty: 'description'
                        };

                        new Gallery(allThumbLinks, options);
                    });
                });
            });
        }
    };

    /**
     * init "YouTube" video preview
     */
    let initYoutube = () => {

        $('.youtube').each(function(){
            // Based on the YouTube ID, we can easily find the thumbnail image
            $(this).css('background-image', 'url(//i.ytimg.com/vi/' + this.id + '/sddefault.jpg)');

            // Overlay the Play icon to make it look like a video player
            $(this).append($('<div/>', {'class': 'play'}));

            $(document).delegate('#' + this.id, 'click', function(){
                // Create an iFrame with autoplay set to true
                let iFrameUrl = '//www.youtube.com/embed/' + this.id + '?autoplay=1&autohide=1';
                if( $(this).data('params') ){
                    iFrameUrl += '&'+$(this).data('params');
                }

                // The height and width of the iFrame should be the same as parent
                let iFrame = $('<iframe/>', {
                    frameborder: '0',
                    src: iFrameUrl,
                    width: $(this).width(),
                    height: $(this).height(),
                    class: 'pricing-big'
                });

                // Replace the YouTube thumbnail with YouTube HTML5 Player
                $(this).replaceWith(iFrame);
            });
        });
    };


    /**
     * init scrollSpy for navigation bar
     */
    let initScrollSpy = () => {
        let scrollElement = document;
        let timeout;

        // show elements that are currently in the viewport
        let showVisibleElements = () => {
            // find all elements that should be animated
            let visibleElements = Util.findInViewport($('.' + config.animateElementClass));
            $(visibleElements).removeClass( config.animateElementClass );

            $(visibleElements).velocity('transition.flipXIn', {
                duration: 600,
                stagger: 60,
                delay: 500,
                complete: function(element){
                    // show "fade" modules (e.g. ribbons)
                    $(element).find('.fade').addClass('in');

                    // init gallery for "now" visible elements
                    let newGalleryElements = $(element).filter('[data-gallery="#' + config.galleryId + '"]');
                    initGallery(newGalleryElements);
                },
                visibility: 'visible'
            });
        };

        let scrollHandler = () => {
            // If there's a timer, cancel it
            if(timeout){
                window.cancelAnimationFrame(timeout);
            }
            timeout = window.requestAnimationFrame(showVisibleElements);
        };

        scrollElement.addEventListener('scroll', scrollHandler, false);

        // initial check for visible elements
        showVisibleElements();


        Util.initScrollSpy(document.getElementById(config.navigationElementId), scrollElement, {
            offset: 150
        });
    };

    /**
     * get current EVE-Online server status
     * -> show "server panel"
     */
    let initServerStatus = () => {
        $.ajax({
            type: 'GET',
            url: Init.path.getServerStatus,
            dataType: 'json'
        }).done(function(responseData, textStatus, request){

            let dateLastModified = new Date(request.getResponseHeader('Last-Modified') || Date.now());
            let dateExpires = new Date(request.getResponseHeader('Expires') || Date.now());

            var options = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'UTC', timeZoneName: 'short' };
            responseData.api.cache = dateLastModified.toLocaleTimeString('en-US', options);
            responseData.api.cacheExpire = 'TTL ' + (dateExpires - dateLastModified) / 1000 + 's';

            let data = {
                stickyPanelServerId: config.stickyPanelServerId,
                stickyPanelClass: config.stickyPanelClass,
                apiStatusTriggerClass: config.apiStatusTriggerClass,
                server: responseData.server,
                api: responseData.api,
                statusFormat: () => {
                    return (val, render) => {
                        switch(render(val)){
                            case 'online':
                            case 'green':   return 'txt-color-green';
                            case 'vip':
                            case 'yellow':  return 'txt-color-yellow';
                            case 'orange':  return 'txt-color-orange';
                            case 'offline':
                            case 'red':     return 'txt-color-red';
                            default:        return '';
                        }
                    };
                }
            };

            requirejs(['text!templates/ui/server_panel.html', 'mustache'], function(template, Mustache){
                let content = Mustache.render(template, data);
                $('#' + config.headerId).prepend(content);
                let stickyPanelServer = $('#' + config.stickyPanelServerId);
                stickyPanelServer.velocity('transition.slideRightBigIn', {
                    duration: 240
                });

                // set observer for api status dialog
                stickyPanelServer.on('pointerdown', '.' + config.apiStatusTriggerClass, e => {
                    e.stopPropagation();
                    $.fn.apiStatusDialog(data.api);
                });
            });

        }).fail(handleAjaxErrorResponse);
    };

    /**
     * show "notification panel" to user
     * -> checks if panel not already shown
     */
    let initNotificationPanel = () => {
        let storageKey = 'notification_panel';
        let currentVersion = Util.getVersion();

        let showNotificationPanel = () => {
            let data = {
                version: currentVersion
            };

            requirejs(['text!templates/ui/notice.html', 'mustache'], (template, Mustache) => {
                let content = Mustache.render(template, data);

                let notificationPanel = $('#' + config.notificationPanelId);
                notificationPanel.html(content);
                notificationPanel.velocity('transition.slideUpIn', {
                    duration: 300,
                    complete: function(){
                        setVersionLinkObserver();

                        // mark panel as "shown"
                        Util.getLocalStore('default').setItem(storageKey, currentVersion);
                    }
                });
            });
        };

        Util.getLocalStore('default').getItem(storageKey).then(data => {
            // check if panel was shown before
            if(data){
                if(data !== currentVersion){
                    // show current panel
                    showNotificationPanel();
                }
            }else{
                // show current panel
                showNotificationPanel();
            }
        });
    };

    /**
     * load character data from cookie information
     * -> all validation is done server side!
     */
    let initCharacterSelect = () => {

        /**
         * init panel animation for an element
         * @param imageWrapperElement
         */
        let initCharacterAnimation = imageWrapperElement => {
            $(imageWrapperElement).velocity('stop').velocity('transition.flipBounceXIn', {
                display: 'block',
                drag: true,
                duration: 500
            });

            // Hover effect for character info layer
            $(imageWrapperElement).hoverIntent(function(e){
                let characterInfoElement = $(this).find('.' + config.characterImageInfoClass);

                characterInfoElement.velocity('finish').velocity({
                    width: ['100%', [ 400, 15 ] ]
                },{
                    easing: 'easeOutSine'
                });
            }, function(e){
                let characterInfoElement = $(this).find('.' + config.characterImageInfoClass);

                characterInfoElement.velocity('finish').velocity({
                    width: 0
                },{
                    duration: 150,
                    easing: 'easeOutSine'
                });
            });
        };

        // --------------------------------------------------------------------
        let removeCharacterPanel = panelElement => {
            let charBox = panelElement.closest(`.${config.characterBoxClass}`);
            charBox.classList.add('remove');
            charBox.addEventListener('animationend', () => charBox.parentNode.removeChild(charBox));
        };

        // --------------------------------------------------------------------
        let getCharacterAuthLabel = authStatus => {
            let label = '';
            switch(authStatus){
                case 'UNKNOWN':
                    label = 'ERROR';
                    break;
                case 'CHARACTER':
                case 'CORPORATION':
                case 'ALLIANCE':
                    label = 'INVALID';
                    break;
                default:
                    label = authStatus;
                    break;
            }
            return label;
        };

        // --------------------------------------------------------------------
        // request character data for each character panel
        requirejs(['text!templates/ui/character_panel.html', 'mustache'], (template, Mustache) => {

            [...document.querySelectorAll(`.${config.characterSelectionClass} .${Util.config.dynamicAreaClass}`)].forEach(charEl => {
                $(charEl).showLoadingAnimation();

                let requestData = {
                    cookie: charEl.dataset.cookie
                };

                $.ajax({
                    type: 'POST',
                    url: Init.path.getCookieCharacterData,
                    data: requestData,
                    dataType: 'json',
                    context: {
                        cookieName: requestData.cookie,
                        charEl: charEl
                    }
                }).done(function(responseData, textStatus, request){
                    $(this.charEl).hideLoadingAnimation();

                    if(
                        responseData.error &&
                        responseData.error.length > 0
                    ){
                        $('.' + config.dynamicMessageContainerClass).showMessage({
                            dismissible: false,
                            type: responseData.error[0].type,
                            title: 'Character verification failed',
                            text: responseData.error[0].message
                        });
                    }

                    if(responseData.hasOwnProperty('character')){
                        let data = {
                            link: this.charEl.dataset.href,
                            cookieName: this.cookieName,
                            token: this.charEl.dataset.token,
                            ccpImageServer: responseData.ccpImageServer,
                            character: responseData.character,
                            isManager: Util.getObjVal(responseData, 'character.role.name') === 'CORPORATION',
                            isAdmin: Util.getObjVal(responseData, 'character.role.name') === 'SUPER',
                            authLabel: getCharacterAuthLabel(responseData.character.authStatus),
                            authOK: responseData.character.authStatus === 'OK',
                            hasActiveSession: responseData.character.hasActiveSession === true
                        };

                        this.charEl.innerHTML = Mustache.render(template, data);

                        // show character panel (animation settings)
                        initCharacterAnimation(this.charEl.querySelector(`.${config.characterImageWrapperClass}`));
                    }else{
                        // character data not available -> remove panel
                        removeCharacterPanel(this.charEl);
                    }
                }).fail(function(jqXHR, status, error){
                    $(this.charEl).hideLoadingAnimation();

                    // character data not available -> remove panel
                    removeCharacterPanel(this.charEl);
                });
            });
        });
    };

    /**
     * default ajax error handler
     * -> show user notifications
     * @param jqXHR
     * @param status
     * @param error
     */
    let handleAjaxErrorResponse = (jqXHR, status, error) => {

        let type = status;
        let title = 'Status ' + jqXHR.status + ': ' + error;
        let message = '';

        if(jqXHR.responseText){
            let errorObj = $.parseJSON(jqXHR.responseText);

            if(
                errorObj.error &&
                errorObj.error.length > 0
            ){
                for(let i = 0; i < errorObj.error.length; i++){
                    let errorData = errorObj.error[i];
                    type = errorData.type;
                    title = 'Status  ' + errorData.code + ': ' + errorData.status;
                    message = errorData.message;

                    Util.showNotify({title: title, text: message, type: type});
                }
            }
        }else{
            Util.showNotify({title: title, text: message, type: type});
        }
    };

    /**
     * main init "landing" page
     */
    $(() => {
        // passive event listener
        Util.initPassiveEvents();

        // clear sessionStorage
        Util.clearSessionStorage();

        // set default AJAX config
        Util.ajaxSetup();

        // set Dialog default config
        Util.initDefaultBootboxConfig();

        // show app information in browser console
        Util.showVersionInfo();

        // show log off message
        let searchParams = new URLSearchParams(location.search); // jshint ignore:line
        if(
            searchParams.has('logout') ||
            searchParams.has('logoutGraceful')
        ){
            let cls = 'txt-color-warning';
            let text = [
                'For security reasons, you were logged out automatically',
                'Please log in again'
            ];

            if(searchParams.has('logoutGraceful')){
                cls = 'txt-color-success';
                text = ['You have successfully logged out'];
            }

            // show logout dialog
            let options = {
                buttons: {
                    close: {
                        label: 'close',
                        className: 'btn-default'
                    }
                },
                content: {
                    icon: 'fa-sign-out-alt',
                    class: cls,
                    title: 'Logout',
                    headline: 'Logout',
                    text: text
                }
            };

            $.fn.showNotificationDialog(options);

            // change url (remove logout parameter)
            if(history.pushState){
                history.pushState({}, '', location.protocol + '//' + location.host + location.pathname);
            }
        }

        // "Lock" default link action (=> open in new tab)!
        // -> until "gallery" is initialized (=> wait animation complete!)
        getThumbnailElements().on('click', function(e){
            e.preventDefault();
        });

        // init "lazy loading" for images
        let lazyLoadInstance = new LazyLoad({
            elements_selector: `.${config.galleryThumbImageClass}`,
            use_native: true
        });

        // hide splash loading animation
        $('.' + config.splashOverlayClass + '[data-status="ok"]').hideSplashOverlay();

        // init server status information
        initServerStatus();

        // init notification panel
        initNotificationPanel();

        // init character select
        initCharacterSelect();

        // init page observer
        setPageObserver();

        // init scrollSpy
        initScrollSpy();

        // init youtube videos
        initYoutube();

        // draw header logo
        document.querySelector(`.logo-ploygon-top-right`).addEventListener('animationend', () => {
            HeaderLogin.init();
        });
    });
});