define(["module/base","app/render","i18n!"],(e,t,s)=>{"use strict";let a=class DemoModule extends e{constructor(e={}){super(Object.assign({},new.target.defaultConfig,e))}newHeaderElement(e){let t=super.newHeaderElement(e),s=this.newHeadlineToolbarElement(),a=this.newIconElement(["fa-play","fa-fw","txt-color","txt-color-success",this._config.moduleHeadlineIconClass]);return a.setAttribute("title","pause update()"),a.onclick=(e=>this.toggleUpdates(e.target)),s.append(a),t.append(s),t}logHandler(t,s=1){super.logHandler(t,s);let a=e=>{let t,s=this._config.logHandler[e]||0,a=!!s&&"success",n="fa-circle";this[`_${e}Queue`]&&(a=(t=this[`_${e}Queue`].filterQueue(e=>"upd"===e.data).length)?"warning":a,n=t?"fa-sync fa-spin":n);let r=Object.assign(document.createElement("span"),{className:"fa-li"});return r.append(this.newIconElement([n,"fa-fw","txt-color",a?`txt-color-${a}`:""])),[r,Object.assign(document.createElement("span"),{textContent:`${e} [${s}]${Number.isInteger(t)?`[${t}]`:""}`,className:a?`pf-animation-pulse-${a}`:""})]},n=this.queryGridItem("info").querySelector(".fa-ul");if(n)n.querySelector(`[data-handler="${t}"]`).innerHTML=a(t).map(e=>e.outerHTML).join("");else{n=Object.assign(document.createElement("ul"),{className:"fa-ul"});let t=e.handler.map(e=>{let t=document.createElement("li");return t.dataset.handler=e,t.prepend(...a(e)),t});n.append(...t),this.queryGridItem("info").querySelector("code").insertAdjacentElement("beforebegin",n)}}render(t,s){this._systemData=s;let a=Object.assign(document.createElement("div"),{className:[this._config.bodyClassName,"grid"].join(" ")}),n=[];for(let[e,t]of Object.entries(this._config.gridItems))n.push(this.newGridItemEl(e,t.label));a.append(...n),this.moduleElement.append(a),this.renderJson("_config",this._config,"info"),this.renderJson("render()",{mapId:t,systemData:s});let{config:r,data:i}=e.Util.getCurrentMapData(this._systemData.mapId);return this.renderJson("currentMapData",{config:r,data:i},"mapData"),this.moduleElement}update(e){return super.update(e).then(e=>new Promise(t=>{this.renderJson("update()",{systemData:e}),t({action:"update",data:{module:this}})}))}init(){super.init(),this.renderJson("init()",null),this.renderJson("currentUserData",e.Util.getCurrentUserData(),"userData")}beforeHide(){super.beforeHide(),this.renderJson("beforeHide()",null)}beforeDestroy(){super.beforeDestroy(),this.renderJson("beforeDestroy()",null)}onSortableEvent(e,t){super.onSortableEvent(e,t),this.renderJson(`${e}()`,t,"sortableJs")}renderJson(e,s,a="trigger"){let n=new Date,r=this.queryGridItem(a).querySelector("code");r.prepend(Object.assign(document.createElement("section"),{className:this._config.highlightClassName,innerHTML:`${++this._config.counter}. ${n.toLocaleTimeString("en-GB")}.${String(n.getMilliseconds()).padStart(3,"0")} ${e} \n`+`${t.highlightJson(s,this._config.gridItems[a].jsonConf)}`})),r.childElementCount>this._config.maxCodeSections&&r.removeChild(r.lastChild)}toggleUpdates(t){t.classList.toggle("fa-pause"),t.classList.toggle("txt-color-danger"),t.classList.toggle("fa-play"),t.classList.toggle("txt-color-success"),this._pauseUpdatesPromise?this._pauseUpdatesPromise.resolve():(this._pauseUpdatesPromise=e.newDeferredPromise(),this._updateQueue.enqueue(()=>this._pauseUpdatesPromise.then(()=>{this._pauseUpdatesPromise=null}),"start"))}newGridItemEl(e,t){this._gridItemEl||(this._gridItemEl=Object.assign(document.createElement("pre"),{className:this._config.gridItemClassName,innerHTML:"<code></code>"}));let a=this.newIconElement(["fa-trash","fa-fw","pull-right",this._config.moduleHeadlineIconClass]);a.setAttribute("title",s("clear output")),a.onclick=(e=>e.target.closest(`.${this._config.gridItemClassName}`).querySelector("code").innerHTML="");let n=this.newHeadlineToolbarElement();n.append(a);let r=this._gridItemEl.cloneNode(!0);return r.dataset.area=e,r.prepend(n,this.newHeadlineElement(t)),r}queryGridItem(e){return this.moduleElement.querySelector(`.${this._config.bodyClassName} .${this._config.gridItemClassName}[data-area="${e}"]`)}};return a.isPlugin=!0,a.scope="system",a.sortArea="a",a.position=10,a.label=s("Demo"),a.fullDataUpdate=!0,a.defaultConfig={className:"pf-system-demo-module",sortTargetAreas:["a","b","c"],headline:s("Demo Module"),gridItemClassName:"pf-dynamic-area",highlightClassName:"pf-animation-pulse-success",counter:0,maxCodeSections:8,gridItems:{info:{label:s("handler/config"),jsonConf:{collapseDepth:1,maxDepth:3}},trigger:{label:s("trigger"),jsonConf:{collapseDepth:1,maxDepth:5}},userData:{label:"user/char data",jsonConf:{collapseDepth:1,maxDepth:8}},mapData:{label:s("map data"),jsonConf:{collapseDepth:2,maxDepth:8,maxLinesFunctions:2}},sortableJs:{label:s("drag&drop events"),jsonConf:{collapseDepth:0,maxDepth:4,maxLinesFunctions:2}}}},a});
//# sourceMappingURL=demo.js.map
