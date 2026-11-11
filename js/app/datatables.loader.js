define([
    'jquery',
    'app/promises/promise.deferred',
    'app/promises/promise.timeout',
    'datatables.net',
    'datatables.net-select',
    'datatables.net-buttons',
    'datatables.net-buttons-html',
    'datatables.net-responsive',
    'datatables.net-rowgroup'
], ($, DeferredPromise, TimeoutPromise) => {
    'use strict';

    /**
     * DataTable dependencies loaded
     * -> init default DataTables config (e.g. custom plugins)
     * @param options
     * @returns {Promise}
     */
    let initDefaultConfig = options => new Promise(resolve => {

        $.extend(true, $.fn.dataTable.defaults, {
            pageLength: -1,
            pagingType: 'simple_numbers',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
            order: [],              // no default order because columnDefs is empty
            autoWidth: false,
            language: {
                info: '_START_ - _END_ of _TOTAL_ entries'
            },
            responsive: {
                breakpoints: options.breakpoints,
                details: false
            },
            columnDefs: [],
            data: []
        });

        // global destroy event
        $(document).on('destroy.dt', '.dataTable ', function(e, settings){
            let table = $(this);
            let tableApi = new $.fn.dataTable.Api(settings);
            // end all active processes (e.g. table lock) of this table
            // -> does nothing if the table has no "StatusTable" feature
            tableApi.endProcesses();

            if(typeof options.onDestroy === 'function'){
                options.onDestroy(table);
            }
        });

        // Status Plugin ==============================================================================================
        // -> each table with the "StatusTable" feature ("S" in dom) owns one StatusTable instance,
        //    stored in its settings object (settings._statusTable)
        // -> the process API (newProcess(), hasProcesses(), endProcess(), endProcesses()) is registered once
        //    and resolves the StatusTable of the table it is called on
        const DEFAULT_PROCESS_NAME = 'default';

        /**
         * create a process handle
         * @param name
         * @returns {DeferredPromise}
         */
        let createProcess = name => {
            let deferredPromise = new DeferredPromise();
            deferredPromise.data = {name: name};
            return deferredPromise;
        };

        let StatusTable = function(){
            this.statusContainer = $('<div>', {
                class: 'dt-stats pull-right'
            });

            this.statusLock = $('<span>', {
                class: ['dt-stat', 'disabled', 'fade'].join(' ')
            }).append($('<i>', {class: ['fas', 'fa-fw', 'fa-lock'].join(' ')}));

            this.statusRequest = $('<span>', {
                class: ['dt-stat', 'disabled', 'fade'].join(' ')
            }).append($('<i>', {class: ['fas', 'fa-fw', 'fa-sync', 'fa-spin'].join(' ')}));

            this.statusContainer.append(this.statusLock, this.statusRequest);

            // processStore holds "unfulfilled" promises
            this.processStore = {};
        };

        StatusTable.prototype.getContainer = function(){
            return this.statusContainer;
        };

        StatusTable.prototype.lock = function(){
            this.statusLock.addClass('in');
        };

        StatusTable.prototype.unlock = function(){
            this.statusLock.removeClass('in');
        };

        StatusTable.prototype.request = function(){
            this.statusRequest.addClass('in');
        };

        StatusTable.prototype.unrequest = function(){
            this.statusRequest.removeClass('in');
        };

        /**
         * check if this table has active processes for name
         * @param name
         * @returns {boolean}
         */
        StatusTable.prototype.hasProcesses = function(name){
            let hasProcesses = false;
            if(this.processStore[name] instanceof Map){
                hasProcesses = this.processStore[name].size > 0;
            }
            return hasProcesses;
        };

        /**
         * check if deferredPromise is an active process of this table
         * @param deferredPromise
         * @returns {boolean}
         */
        StatusTable.prototype.ownsProcess = function(deferredPromise){
            return Boolean(deferredPromise && deferredPromise.data) &&
                this.processStore[deferredPromise.data.name] instanceof Map &&
                this.processStore[deferredPromise.data.name].has(deferredPromise);
        };

        /**
         * show/hide the status icon for process name
         * -> only "lock" and "request" have a status icon
         * @param name
         */
        StatusTable.prototype.updateProcessStatus = function(name){
            let active = this.hasProcesses(name);
            if(name === 'lock'){
                if(active){
                    this.lock();
                }else{
                    this.unlock();
                }
            }else if(name === 'request'){
                if(active){
                    this.request();
                }else{
                    this.unrequest();
                }
            }
        };

        /**
         * remove deferredPromise from processStore
         * @param deferredPromise
         */
        StatusTable.prototype.removeProcess = function(deferredPromise){
            let name = deferredPromise.data.name;
            if(this.processStore[name] instanceof Map){
                this.processStore[name].delete(deferredPromise);
            }
            // update table processStatus
            this.updateProcessStatus(name);
        };

        /**
         * start a new process (e.g. table lock) for this table
         * -> ends by itself after the TimeoutPromise timeout
         * @param name
         * @returns {DeferredPromise}
         */
        StatusTable.prototype.newProcess = function(name){
            // new DeferredPromise for process
            let deferredPromise = createProcess(name);

            // reject DeferredPromise after timeout (if not resolved)
            let timeoutPromise = new TimeoutPromise(deferredPromise);
            timeoutPromise
                .then(() => {
                    // resolved within timeout -> wait for finally() block
                })
                .catch(error => {
                    if(error instanceof Error){
                        // either timeout error or error from rejected deferredPromise
                        console.warn(error);
                    }
                })
                .finally(() => {
                    // no matter if TimeoutPromise is resolved or rejected
                    // -> remove from processStore
                    this.removeProcess(deferredPromise);
                    // settle DeferredPromise if it timed out (no-op if already settled)
                    deferredPromise.resolve();
                });

            // store TimeoutPromise -----------------------------------------------------------------------------------
            if(!(this.processStore[name] instanceof Map)){
                this.processStore[name] = new Map();
            }
            this.processStore[name].set(deferredPromise);

            // update table processStatus
            this.updateProcessStatus(name);

            return deferredPromise;
        };

        /**
         * end a process of this table
         * @param deferredPromise
         */
        StatusTable.prototype.endProcess = function(deferredPromise){
            // ignore null and promises of other tables
            if(this.ownsProcess(deferredPromise)){
                this.removeProcess(deferredPromise);
                deferredPromise.resolve();
            }
        };

        /**
         * end all processes of this table
         */
        StatusTable.prototype.endProcesses = function(){
            for(let store of Object.values(this.processStore)){
                for(let deferredPromise of [...store.keys()]){
                    this.endProcess(deferredPromise);
                }
            }
        };

        /**
         * get StatusTable of the (first) table of a DataTables API instance
         * @param tableApi
         * @returns {StatusTable|null}
         */
        let getStatusTable = tableApi => {
            let settings = tableApi.context[0];
            return (settings && settings._statusTable instanceof StatusTable) ? settings._statusTable : null;
        };

        $.fn.dataTable.Api.register('newProcess()', function(name = DEFAULT_PROCESS_NAME){
            let statusTable = getStatusTable(this);
            if(statusTable){
                return statusTable.newProcess(name);
            }

            // table without "StatusTable" feature -> nothing to track, return a fulfilled process
            let deferredPromise = createProcess(name);
            deferredPromise.resolve();
            return deferredPromise;
        });

        $.fn.dataTable.Api.register('hasProcesses()', function(name = DEFAULT_PROCESS_NAME){
            let statusTable = getStatusTable(this);
            return statusTable ? statusTable.hasProcesses(name) : false;
        });

        $.fn.dataTable.Api.register('endProcess()', function(deferredPromise){
            let statusTable = getStatusTable(this);
            if(statusTable){
                statusTable.endProcess(deferredPromise);
            }
        });

        $.fn.dataTable.Api.register('endProcesses()', function(){
            let statusTable = getStatusTable(this);
            if(statusTable){
                statusTable.endProcesses();
            }
        });

        $.fn.dataTable.ext.feature.push({
            fnInit: settings => {
                let oStatusTable = new StatusTable();
                settings._statusTable = oStatusTable;
                return oStatusTable.getContainer();
            },
            cFeature: 'S',
            sFeature: 'StatusTable'
        });

        resolve();
    });

    return {
        initDefaultConfig
    };
});