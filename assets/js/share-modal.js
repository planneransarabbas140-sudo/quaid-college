(function(){
  try {
    // Safe stub for share-modal functionality. Attach only if elements exist.
    var shareButtons = document.querySelectorAll('.share-btn, .share-action, [data-share]');
    if (!shareButtons || shareButtons.length === 0) return;

    shareButtons.forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        var url = btn.getAttribute('data-share') || window.location.href;
        // Minimal share behavior: try navigator.share if available, otherwise copy to clipboard
        if (navigator.share) {
          navigator.share({ title: document.title, url: url }).catch(function(err){ console.warn('Share failed', err); });
        } else if (navigator.clipboard) {
          navigator.clipboard.writeText(url).then(function(){
            btn.classList.add('copied');
            setTimeout(function(){ btn.classList.remove('copied'); }, 1500);
          }).catch(function(){ console.warn('Clipboard write failed'); });
        } else {
          // fallback: open a new window with the url
          window.open(url, '_blank');
        }
      });
    });
  } catch (err) {
    console.warn('share-modal stub error', err);
  }
})();
