(function(){
  function ready(fn){document.readyState==='loading'?document.addEventListener('DOMContentLoaded',fn):fn();}
  ready(function(){
    var sidebar=document.getElementById('sidebar');
    var content=document.getElementById('content');
    var toggle=document.getElementById('sidebarCollapse');
    var backdrop=document.querySelector('.sidebar-backdrop');
    if(!sidebar||!toggle)return;
    var collapsed=localStorage.getItem('qac-sidebar-collapsed')==='1';
    if(collapsed&&window.innerWidth>992){sidebar.classList.add('active');content&&content.classList.add('active');}
    toggle.addEventListener('click',function(){
      if(window.innerWidth<=992){sidebar.classList.toggle('active');backdrop&&backdrop.classList.toggle('visible',sidebar.classList.contains('active'));return;}
      sidebar.classList.toggle('active');content&&content.classList.toggle('active');
      localStorage.setItem('qac-sidebar-collapsed',sidebar.classList.contains('active')?'1':'0');
    });
    backdrop&&backdrop.addEventListener('click',function(){sidebar.classList.remove('active');backdrop.classList.remove('visible');});
    window.addEventListener('resize',function(){
      if(window.innerWidth>992){backdrop&&backdrop.classList.remove('visible');}
      if(window.innerWidth<=992){content&&content.classList.remove('active');}
    },{passive:true});
    var active=sidebar.querySelector('li.active'); if(active) active.scrollIntoView({block:'nearest'});
  });
})();
