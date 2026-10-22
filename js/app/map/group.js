/**
 * Map group (subgraph container) functions
 */

define([
    'jquery',
    'app/init',
    'app/util',
    'bootbox',
    'app/map/util'
], ($, Init, Util, bootbox, MapUtil) => {
    'use strict';

    const GROUP_DEBUG = false;
    const glog = (...args) => { if(GROUP_DEBUG) console.log('[GROUP]', ...args); };

    let config = {
        groupClass:         'pf-map-group',
        groupHeaderClass:   'pf-map-group-header',
        groupBodyClass:     'pf-map-group-body',
        groupLabelClass:    'pf-map-group-label',
        groupActionsClass:  'pf-map-group-actions',
        groupHandleClass:   'pf-map-group-handle',
        groupCollapseClass: 'pf-map-group-collapse',
        groupCogClass:      'pf-map-group-cog',
        groupCogMenuClass:  'pf-map-group-cog-menu',
        groupDeleteClass:   'pf-map-group-delete',
        groupCollapsedClass:'pf-map-group-collapsed',
        groupResizeClass:   'pf-map-group-resize',
        groupIdPrefix:      'pf-map-group-'
    };

    /**
     * get the DOM id for a group
     * @param {number} mapId
     * @param {number} groupId
     * @returns {string}
     */
    let getGroupId = (mapId, groupId) => config.groupIdPrefix + mapId + '-' + groupId;

    /**
     * build the group DOM element (does not append to DOM)
     * @param {number} mapId
     * @param {object} groupData
     * @returns {jQuery}
     */
    let buildGroupElement = (mapId, groupData) => {
        glog('buildGroupElement <- server', {id: groupData.id, posX: groupData.posX, posY: groupData.posY, width: groupData.width, height: groupData.height, isCollapsed: groupData.isCollapsed});
        let groupId = getGroupId(mapId, groupData.id);

        let handle = $('<i>', {
            class: ['fas', 'fa-grip-vertical', 'fa-fw', config.groupHandleClass].join(' '),
            title: 'drag to move'
        });

        let collapseIcon = $('<i>', {
            class: ['fas', 'fa-compress', 'fa-fw', config.groupCollapseClass].join(' '),
            title: 'toggle collapse'
        });

        let cogMenu = $('<div>', {
            class: config.groupCogMenuClass
        }).append(
            $('<a>', {
                class: 'pf-map-group-cog-item',
                text: 'Rename'
            })
        );

        let cogWrapper = $('<span>', {
            class: 'pf-map-group-cog-wrapper'
        }).append(
            $('<i>', {
                class: ['fas', 'fa-cog', 'fa-fw', config.groupCogClass].join(' '),
                title: 'options'
            }),
            cogMenu
        );

        let deleteIcon = $('<i>', {
            class: ['fas', 'fa-times', 'fa-fw', config.groupDeleteClass].join(' '),
            title: 'delete group'
        });

        let label = $('<span>', {
            class: config.groupLabelClass,
            text: groupData.label
        });

        let actions = $('<span>', {
            class: config.groupActionsClass
        }).append(collapseIcon, cogWrapper, deleteIcon);

        let header = $('<div>', {
            class: config.groupHeaderClass
        }).append(handle, label, actions);

        let body = $('<div>', {
            class: config.groupBodyClass
        }).attr('jtk-group-content', '');

        let resizeHandle = $('<div>', {
            class: config.groupResizeClass,
            title: 'drag to resize'
        });

        let groupEl = $('<div>', {
            id: groupId,
            class: config.groupClass
        }).css({
            left:   groupData.posX + 'px',
            top:    groupData.posY + 'px',
            width:  groupData.width + 'px',
            height: groupData.height + 'px'
        }).data('id', groupData.id)
          .data('mapId', mapId)
          .data('updated', groupData.updated.updated)
          .append(header, body, resizeHandle);

        return groupEl;
    };

    /**
     * persist group position/size to server
     * @param {jQuery} groupEl
     */
    let saveGroupPosition = groupEl => {
        let groupId = groupEl.data('id');
        let pos = groupEl.position();
        let isCollapsed = groupEl.hasClass('jtk-group-collapsed');

        // when collapsed, outerHeight() returns the header-only height (~22px) due to
        // `height: auto !important` in CSS — never save that as the group's real size
        let payload = {
            posX: Math.round(pos.left),
            posY: Math.round(pos.top)
        };
        if(!isCollapsed){
            payload.width  = Math.round(groupEl.outerWidth());
            payload.height = Math.round(groupEl.outerHeight());
        }

        glog('saveGroupPosition', groupEl.attr('id'), {isCollapsed, payload,
            outerWidth: groupEl.outerWidth(), outerHeight: groupEl.outerHeight(),
            inlineHeight: groupEl[0].style.height,
            computedHeight: getComputedStyle(groupEl[0]).height
        });

        Util.request('PATCH', 'MapGroup', groupId, payload).catch(console.warn);
    };

    /**
     * persist collapsed state to server
     * @param {jQuery} groupEl
     * @param {boolean} isCollapsed
     */
    let saveGroupCollapsed = (groupEl, isCollapsed) => {
        let groupId = groupEl.data('id');
        Util.request('PATCH', 'MapGroup', groupId, {
            isCollapsed: isCollapsed
        }).catch(console.warn);
    };

    /**
     * set collapsed state on a group element (UI + persist)
     * @param {object} jsPlumbInstance
     * @param {jQuery} groupEl
     * @param {boolean} collapsed
     */
    let setCollapsed = (jsPlumbInstance, groupEl, collapsed) => {
        let groupDomId = groupEl.attr('id');
        glog('setCollapsed BEFORE', groupDomId, {collapsed, inlineHeight: groupEl[0].style.height, outerHeight: groupEl.outerHeight()});

        if(collapsed){
            jsPlumbInstance.collapseGroup(groupDomId);
        }else{
            jsPlumbInstance.expandGroup(groupDomId);
        }

        glog('setCollapsed AFTER', groupDomId, {collapsed, inlineHeight: groupEl[0].style.height, outerHeight: groupEl.outerHeight()});
        saveGroupCollapsed(groupEl, collapsed);
    };

    /**
     * open rename prompt for a group
     * @param {jQuery} groupEl
     */
    let promptRename = groupEl => {
        let labelEl = groupEl.find('.' + config.groupLabelClass);
        let groupId = groupEl.data('id');

        bootbox.prompt({
            title: 'Rename group',
            value: labelEl.text(),
            callback: result => {
                if(result !== null && result.trim() !== ''){
                    labelEl.text(result.trim());
                    Util.request('PATCH', 'MapGroup', groupId, {label: result.trim()}).catch(console.warn);
                }
            }
        });
    };

    /**
     * wire up resize handle on bottom-right corner
     * @param {object} jsPlumbInstance
     * @param {jQuery} groupEl
     */
    let bindResizeHandle = (jsPlumbInstance, groupEl) => {
        groupEl.find('.' + config.groupResizeClass).on('mousedown', function(e){
            e.preventDefault();
            e.stopPropagation();

            let scale   = jsPlumbInstance.getZoom ? jsPlumbInstance.getZoom() : 1;
            let startX  = e.clientX;
            let startY  = e.clientY;
            let startW  = groupEl.outerWidth();
            let startH  = groupEl.outerHeight();
            let rafId   = null;

            let onMouseMove = function(e){
                let newW = Math.max(120, startW + (e.clientX - startX) / scale);
                let newH = Math.max(60,  startH + (e.clientY - startY) / scale);
                groupEl.css({ width: newW + 'px', height: newH + 'px' });
                if(rafId){ cancelAnimationFrame(rafId); }
                rafId = requestAnimationFrame(() => jsPlumbInstance.repaintEverything());
            };

            let onMouseUp = function(){
                $(document).off('mousemove.groupResize mouseup.groupResize');
                if(rafId){ cancelAnimationFrame(rafId); }
                jsPlumbInstance.repaintEverything();
                glog('resizeHandle mouseup', groupEl.attr('id'), {finalW: groupEl.outerWidth(), finalH: groupEl.outerHeight()});
                saveGroupPosition(groupEl);
            };

            $(document).on('mousemove.groupResize', onMouseMove)
                       .on('mouseup.groupResize', onMouseUp);
        });
    };

    /**
     * wire up header interactions
     * @param {object} jsPlumbInstance
     * @param {jQuery} groupEl
     * @param {jQuery} mapContainer
     */
    let bindGroupEvents = (jsPlumbInstance, groupEl, mapContainer) => {

        // ---- collapse toggle ----
        groupEl.find('.' + config.groupCollapseClass).on('click', function(e){
            e.stopPropagation();
            let isNowCollapsed = !groupEl.hasClass('jtk-group-collapsed');
            setCollapsed(jsPlumbInstance, groupEl, isNowCollapsed);
        });

        // ---- cog menu toggle ----
        groupEl.find('.' + config.groupCogClass).on('click', function(e){
            e.stopPropagation();
            let menu = $(this).siblings('.' + config.groupCogMenuClass);
            let isOpen = menu.is(':visible');
            // close any other open menus first
            $('.' + config.groupCogMenuClass + ':visible').hide();
            menu.toggle(!isOpen);
        });

        // ---- cog menu: rename ----
        groupEl.find('.' + config.groupCogMenuClass).on('click', '.pf-map-group-cog-item', function(e){
            e.stopPropagation();
            groupEl.find('.' + config.groupCogMenuClass).hide();
            promptRename(groupEl);
        });

        // close cog menu on outside click
        $(document).on('click.groupCog' + groupEl.attr('id'), function(){
            groupEl.find('.' + config.groupCogMenuClass).hide();
        });

        // ---- delete ----
        groupEl.find('.' + config.groupDeleteClass).on('click', function(e){
            e.stopPropagation();
            let groupId = groupEl.data('id');
            let mapId   = groupEl.data('mapId');

            bootbox.dialog({
                title:   'Delete Group',
                message: 'How would you like to delete this group?',
                buttons: {
                    cancel: {
                        label:     'Cancel',
                        className: 'btn-default pull-left'
                    },
                    keepSystems: {
                        label:     '<i class="fas fa-sign-out-alt fa-fw"></i> Delete group, keep systems',
                        className: 'btn-warning',
                        callback:  function(){
                            removeGroup(jsPlumbInstance, groupEl);
                            Util.request('DELETE', 'MapGroup', groupId, {}).catch(console.warn);
                        }
                    },
                    deleteSystems: {
                        label:     '<i class="fas fa-trash fa-fw"></i> Delete group and systems',
                        className: 'btn-danger',
                        callback:  function(){
                            let group;
                            try{ group = jsPlumbInstance.getGroup(groupEl.attr('id')); }catch(e){}
                            if(group){
                                let members   = group.getMembers();
                                let systemIds = members.map(el => $(el).data('id')).filter(Boolean);
                                if(systemIds.length){
                                    Util.request('DELETE', 'System', systemIds, {mapId: mapId}).catch(console.warn);
                                }
                                // mark members so group:removeMember skips the PATCH
                                group.getMembers().forEach(el => el.dataset.pfDeleting = '1');
                                // pass true so jsPlumb removes members cleanly before orphaning;
                                // calling jsPlumbInstance.remove(el) first then removeGroup(false)
                                // causes parentNode=null errors when jsPlumb tries to re-orphan
                                $(document).off('click.groupCog' + groupEl.attr('id'));
                                jsPlumbInstance.removeGroup(group, true);
                            } else {
                                removeGroup(jsPlumbInstance, groupEl);
                            }
                            Util.request('DELETE', 'MapGroup', groupId, {}).catch(console.warn);
                        }
                    }
                }
            });
        });

        // drag stop → persist position
        jsPlumbInstance.bind('groupDragStop', function(params){
            if(params.group && params.group.getEl() === groupEl[0]){
                groupEl.css('z-index', '');
                glog('groupDragStop', groupEl.attr('id'), {
                    isCollapsed: groupEl.hasClass('jtk-group-collapsed'),
                    outerHeight: groupEl.outerHeight(),
                    inlineHeight: groupEl[0].style.height
                });
                saveGroupPosition(groupEl);
            }
        });

        // resize handle
        bindResizeHandle(jsPlumbInstance, groupEl);
    };

    /**
     * initialise a group element and register it with jsPlumb
     * @param {object} jsPlumbInstance
     * @param {jQuery} mapContainer
     * @param {object} groupData
     * @returns {jQuery} the group element
     */
    let initGroup = (jsPlumbInstance, mapContainer, groupData) => {
        let mapId = mapContainer.data('id');
        let groupDomId = getGroupId(mapId, groupData.id);

        // skip if already on DOM
        if(document.getElementById(groupDomId)){
            return $('#' + groupDomId);
        }

        let groupEl = buildGroupElement(mapId, groupData);
        mapContainer.append(groupEl);

        jsPlumbInstance.addGroup({
            el:          groupEl[0],
            id:          groupDomId,
            droppable:   true,
            collapsed:   Boolean(groupData.isCollapsed),
            constrain:   Boolean(groupData.constrain),
            orphan:      true,
            dropOverride: Boolean(groupData.dropOverride),
            dragOptions: {
                handle:      '.' + config.groupHandleClass,
                containment: 'parent',
                start:       function(){ groupEl.css('z-index', 50); }
            }
        });
        glog('initGroup post-addGroup', groupDomId, {
            styleLeft: groupEl[0].style.left, styleTop: groupEl[0].style.top,
            styleWidth: groupEl[0].style.width, styleHeight: groupEl[0].style.height,
            outerWidth: groupEl.outerWidth(), outerHeight: groupEl.outerHeight()
        });

        bindGroupEvents(jsPlumbInstance, groupEl, mapContainer);

        return groupEl;
    };

    /**
     * update an existing group element from fresh server data (live-sync)
     * @param {object} jsPlumbInstance
     * @param {jQuery} groupEl
     * @param {object} groupData
     */
    let updateGroup = (jsPlumbInstance, groupEl, groupData) => {
        let labelEl = groupEl.find('.' + config.groupLabelClass);
        if(labelEl.text() !== groupData.label){
            labelEl.text(groupData.label);
        }

        let isCollapsed = groupEl.hasClass('jtk-group-collapsed');
        if(isCollapsed !== Boolean(groupData.isCollapsed)){
            setCollapsed(jsPlumbInstance, groupEl, groupData.isCollapsed);
        }

        groupEl.data('updated', groupData.updated.updated);
    };

    /**
     * remove a group from the map (leaves child systems in place)
     * @param {object} jsPlumbInstance
     * @param {jQuery} groupEl
     */
    let removeGroup = (jsPlumbInstance, groupEl) => {
        // clean up document-level click handler
        $(document).off('click.groupCog' + groupEl.attr('id'));

        let groupDomId = groupEl.attr('id');
        let group = jsPlumbInstance.getGroup(groupDomId);
        if(group){
            jsPlumbInstance.removeGroup(group, false);
        }
        groupEl.remove();
    };

    /**
     * show "add group" dialog, then PUT to server and init group
     * @param {object} jsPlumbInstance
     * @param {jQuery} mapContainer
     * @param {{x: number, y: number}} position - where the right-click happened
     */
    let showNewGroupDialog = (jsPlumbInstance, mapContainer, position) => {
        let mapId = mapContainer.data('id');

        bootbox.prompt({
            title: 'New group label',
            value: 'Group',
            callback: result => {
                if(result === null || result.trim() === '') return;

                Util.request('PUT', 'MapGroup', '', {
                    mapId:  mapId,
                    label:  result.trim(),
                    posX:   Math.round(position.x),
                    posY:   Math.round(position.y),
                    width:  300,
                    height: 200
                }).then(payload => {
                    let groupData = payload.data;
                    if(groupData && groupData.id){
                        initGroup(jsPlumbInstance, mapContainer, groupData);
                    }
                }).catch(console.warn);
            }
        });
    };

    return {
        config,
        getGroupId,
        initGroup,
        updateGroup,
        removeGroup,
        showNewGroupDialog
    };
});
