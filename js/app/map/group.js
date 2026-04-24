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

    let config = {
        groupClass:         'pf-map-group',
        groupHeaderClass:   'pf-map-group-header',
        groupBodyClass:     'pf-map-group-body',
        groupLabelClass:    'pf-map-group-label',
        groupActionsClass:  'pf-map-group-actions',
        groupCollapseClass: 'pf-map-group-collapse',
        groupDeleteClass:   'pf-map-group-delete',
        groupCollapsedClass:'pf-map-group-collapsed',
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
        let groupId = getGroupId(mapId, groupData.id);

        let collapseIcon = $('<i>', {
            class: ['fas', 'fa-compress', 'fa-fw', config.groupCollapseClass].join(' '),
            title: 'toggle collapse'
        });

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
        }).append(collapseIcon, deleteIcon);

        let header = $('<div>', {
            class: config.groupHeaderClass
        }).append(label, actions);

        let body = $('<div>', {
            class: config.groupBodyClass
        }).attr('jtk-group-content', '');

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
          .append(header, body);

        if(groupData.isCollapsed){
            groupEl.addClass(config.groupCollapsedClass);
        }

        return groupEl;
    };

    /**
     * persist group position/size to server
     * @param {jQuery} groupEl
     */
    let saveGroupPosition = groupEl => {
        let groupId = groupEl.data('id');
        let pos = groupEl.position();

        Util.request('PATCH', 'MapGroup', groupId, {
            posX:   Math.round(pos.left),
            posY:   Math.round(pos.top),
            width:  Math.round(groupEl.outerWidth()),
            height: Math.round(groupEl.outerHeight())
        }).catch(console.warn);
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

        if(collapsed){
            groupEl.addClass(config.groupCollapsedClass);
            // tell jsPlumb to repaint connections — proxy connections appear automatically
            jsPlumbInstance.repaintEverything();
        }else{
            groupEl.removeClass(config.groupCollapsedClass);
            jsPlumbInstance.repaintEverything();
        }

        saveGroupCollapsed(groupEl, collapsed);
    };

    /**
     * wire up header interactions (drag, collapse, label edit, delete)
     * @param {object} jsPlumbInstance
     * @param {jQuery} groupEl
     * @param {jQuery} mapContainer
     */
    let bindGroupEvents = (jsPlumbInstance, groupEl, mapContainer) => {
        let header = groupEl.find('.' + config.groupHeaderClass);

        // ---- collapse toggle ----
        groupEl.find('.' + config.groupCollapseClass).on('click', function(e){
            e.stopPropagation();
            let isNowCollapsed = !groupEl.hasClass(config.groupCollapsedClass);
            setCollapsed(jsPlumbInstance, groupEl, isNowCollapsed);
        });

        // ---- delete ----
        groupEl.find('.' + config.groupDeleteClass).on('click', function(e){
            e.stopPropagation();
            let groupId = groupEl.data('id');
            bootbox.confirm('Delete this group? Systems inside will remain on the map.', result => {
                if(result){
                    removeGroup(jsPlumbInstance, groupEl);
                    Util.request('DELETE', 'MapGroup', groupId, {}).catch(console.warn);
                }
            });
        });

        // ---- inline label edit (double-click) ----
        groupEl.find('.' + config.groupLabelClass).on('dblclick', function(e){
            e.stopPropagation();
            let labelEl = $(this);
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
        });

        // drag stop → persist position (groupDragStop fires from addGroup's built-in stop handler)
        jsPlumbInstance.bind('groupDragStop', function(params){
            if(params.group && params.group.getEl() === groupEl[0]){
                groupEl.css('z-index', '');
                saveGroupPosition(groupEl);
            }
        });
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

        // register with jsPlumb group manager
        // dragOptions merges into the built-in drag setup (which keeps GROUP_DRAG_SCOPE and the drag repaint handler)
        jsPlumbInstance.addGroup({
            el:          groupEl[0],
            id:          groupDomId,
            droppable:   true,
            constrain:   Boolean(groupData.constrain),
            orphan:      false,
            dropOverride: Boolean(groupData.dropOverride),
            dragOptions: {
                handle:      '.' + config.groupHeaderClass,
                containment: 'parent',
                start:       function(){ groupEl.css('z-index', 50); }
            }
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

        let isCollapsed = groupEl.hasClass(config.groupCollapsedClass);
        if(isCollapsed !== groupData.isCollapsed){
            setCollapsed(jsPlumbInstance, groupEl, groupData.isCollapsed);
        }

        // update stored updated timestamp
        groupEl.data('updated', groupData.updated.updated);
    };

    /**
     * remove a group from the map (leaves child systems in place)
     * @param {object} jsPlumbInstance
     * @param {jQuery} groupEl
     */
    let removeGroup = (jsPlumbInstance, groupEl) => {
        let groupDomId = groupEl.attr('id');
        let group = jsPlumbInstance.getGroup(groupDomId);
        if(group){
            // false = do NOT remove child elements when group is removed
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
