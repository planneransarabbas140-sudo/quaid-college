// Off-canvas overlay toggle and hero lazy-loader
(function(){
  // Navbar collapse overlay (Bootstrap events)
  try {
    var siteNav = document.getElementById('siteNav');
    if (siteNav) {
      siteNav.addEventListener('show.bs.collapse', function(){ document.body.classList.add('show-overlay'); });
      siteNav.addEventListener('hide.bs.collapse', function(){ document.body.classList.remove('show-overlay'); });
    }
  } catch (e) { console.warn('Navbar overlay binding failed', e); }

  // Hero lazy loader using IntersectionObserver
  function lazyLoadHero() {
    var slides = document.querySelectorAll('.qs-slide');
    if (!slides || slides.length === 0) return;
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        var slide = entry.target;
        var url = slide.dataset.bg;
        var bgEl = slide.querySelector('.qs-slide-bg');
        if (url && bgEl && !bgEl.dataset.loaded) {
          // set background-image and mark loaded
          bgEl.style.backgroundImage = "url('" + url + "')";
          bgEl.dataset.loaded = '1';
        }
        // if slide becomes active, ensure opacity transition
        if (entry.isIntersecting) {
          // nothing else
        }
        // unobserve once loaded
        io.unobserve(slide);
      });
    }, { root: null, rootMargin: '200px 0px' });

    slides.forEach(function(s){ io.observe(s); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', lazyLoadHero);
  } else {
    lazyLoadHero();
  }
})();
