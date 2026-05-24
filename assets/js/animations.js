(function(){
  function ready(fn){document.readyState==='loading'?document.addEventListener('DOMContentLoaded',fn):fn();}
  function formatValue(value,currency){return (currency?'Rs. ':'')+Math.round(value).toLocaleString('en-PK');}
  window.animateCounter=function(el,target,duration){
    var start=0,startTime=null,currency=/rs\.?/i.test(el.textContent);
    function tick(ts){if(!startTime)startTime=ts;var p=Math.min((ts-startTime)/(duration||1500),1);var eased=1-Math.pow(1-p,3);el.textContent=formatValue(start+(target-start)*eased,currency);if(p<1)requestAnimationFrame(tick);}
    requestAnimationFrame(tick);
  };
  window.Toast={show:function(message,type,duration){var c=document.querySelector('.toast-container-modern');if(!c)return;var t=document.createElement('div');t.className='toast-modern toast-'+(type||'success');t.innerHTML='<span>'+message+'</span><button type="button" aria-label="Close">&times;</button>';c.appendChild(t);t.querySelector('button').onclick=function(){t.remove();};setTimeout(function(){t.remove();},duration||4000);},success:function(m){this.show(m,'success');},error:function(m){this.show(m,'error');},warning:function(m){this.show(m,'warning');}};
  ready(function(){
    var counterObserver=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){var el=entry.target;var target=parseFloat(el.dataset.count||el.textContent.replace(/[^0-9.]/g,''))||0;animateCounter(el,target,1500);counterObserver.unobserve(el);}});},{threshold:.35});
    document.querySelectorAll('[data-count], .mini-card strong').forEach(function(el){counterObserver.observe(el);});
    var revealObserver=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){entry.target.classList.add('is-visible');revealObserver.unobserve(entry.target);}});},{threshold:.12});
    document.querySelectorAll('.reveal,.card,.mini-card,.page-section').forEach(function(el){revealObserver.observe(el);});
    document.addEventListener('click',function(e){var btn=e.target.closest('.btn');if(!btn)return;var r=document.createElement('span');r.className='btn-ripple';btn.appendChild(r);setTimeout(function(){r.remove();},450);});
    document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();document.body.classList.add('search-open');var input=document.getElementById('globalSearchInput');if(input)input.focus();}if(e.key==='Escape')document.body.classList.remove('search-open');});
    document.querySelectorAll('[data-open-search]').forEach(function(el){el.addEventListener('click',function(){document.body.classList.add('search-open');var input=document.getElementById('globalSearchInput');if(input)input.focus();});});
    var overlay=document.querySelector('.search-modal-overlay'); if(overlay)overlay.addEventListener('click',function(e){if(e.target===overlay)document.body.classList.remove('search-open');});
  });
})();
