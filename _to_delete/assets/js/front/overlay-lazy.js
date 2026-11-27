(function(){
if(window.__zppOvLazy){return;}window.__zppOvLazy=true;
function sync(){
var f=document.querySelectorAll('iframe.zappoppop-ov-bg[data-srcdoc]');
for(var i=0;i<f.length;i++){
var el=f[i],vis=el.offsetWidth>0&&el.offsetHeight>0,on=el.getAttribute('data-on')==='1';
if(vis&&!on){el.setAttribute('srcdoc',el.getAttribute('data-srcdoc'));el.setAttribute('data-on','1');}
else if(!vis&&on){el.removeAttribute('srcdoc');el.setAttribute('src','about:blank');el.setAttribute('data-on','0');}
}}
function boot(){sync();
if(window.MutationObserver){new MutationObserver(sync).observe(document.documentElement,{attributes:true,subtree:true,attributeFilter:['style','class']});}
else{setInterval(sync,400);}}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
})();
