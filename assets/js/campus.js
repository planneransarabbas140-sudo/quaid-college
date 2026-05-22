(function () {
    const nav = document.querySelector('.navbar-campus');
    if (nav) {
        window.addEventListener('scroll', function () {
            nav.classList.toggle('scrolled', window.scrollY > 20);
        }, { passive: true });
    }

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        document.querySelectorAll('.reveal').forEach(function (el) {
            revealObserver.observe(el);
        });
    } else {
        document.querySelectorAll('.reveal').forEach(function (el) {
            el.classList.add('visible');
        });
    }

    const filterButtons = document.querySelectorAll('.filter-btn');
    const programItems = document.querySelectorAll('.program-item');
    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const filter = button.dataset.filter;
            filterButtons.forEach(function (btn) { btn.classList.remove('active'); });
            button.classList.add('active');
            programItems.forEach(function (item) {
                const visible = filter === 'all' || item.dataset.category === filter;
                item.classList.toggle('hidden', !visible);
            });
        });
    });
})();
