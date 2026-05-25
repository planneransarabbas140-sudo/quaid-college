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

  // Tap-to-open campus menu on phones. Desktop keeps the normal hover menu.
  function bindCampusDropdown() {
    var dropdown = document.querySelector('.campus-dropdown');
    if (!dropdown) return;

    var toggle = dropdown.querySelector('.nav-link-campus');
    if (!toggle) return;

    var mobileQuery = window.matchMedia('(max-width: 991px)');

    function setOpen(isOpen) {
      dropdown.classList.toggle('is-open', isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    toggle.setAttribute('aria-haspopup', 'true');
    toggle.setAttribute('aria-expanded', 'false');

    toggle.addEventListener('click', function(event) {
      if (!mobileQuery.matches) return;
      event.preventDefault();
      setOpen(!dropdown.classList.contains('is-open'));
    });

    dropdown.querySelectorAll('.campus-dropdown-menu a').forEach(function(link) {
      link.addEventListener('click', function() {
        setOpen(false);
      });
    });

    function handleViewportChange() {
      if (!mobileQuery.matches) setOpen(false);
    }

    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', handleViewportChange);
    } else if (typeof mobileQuery.addListener === 'function') {
      mobileQuery.addListener(handleViewportChange);
    }
  }

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
    document.addEventListener('DOMContentLoaded', function() {
      bindCampusDropdown();
      lazyLoadHero();
    });
  } else {
    bindCampusDropdown();
    lazyLoadHero();
  }
})();
