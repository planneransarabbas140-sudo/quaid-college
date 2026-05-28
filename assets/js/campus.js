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
    const programSearch = document.getElementById('campusProgramSearch');
    const programCount = document.getElementById('campusProgramCount');
    const programEmpty = document.getElementById('campusProgramEmpty');

    function applyCampusProgramFilters() {
        const active = document.querySelector('.filter-btn.active');
        const filter = active ? active.dataset.filter : 'all';
        const query = programSearch ? programSearch.value.trim().toLowerCase() : '';
        let visible = 0;

        programItems.forEach(function (item) {
            const categoryMatch = filter === 'all' || item.dataset.category === filter;
            const textMatch = !query || item.textContent.toLowerCase().includes(query);
            const shouldShow = categoryMatch && textMatch;
            item.classList.toggle('hidden', !shouldShow);
            if (shouldShow) {
                visible += 1;
            }
        });

        if (programCount) {
            programCount.textContent = visible + ' program' + (visible === 1 ? '' : 's');
        }
        if (programEmpty) {
            programEmpty.classList.toggle('hidden', visible !== 0);
        }
    }

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            filterButtons.forEach(function (btn) { btn.classList.remove('active'); });
            button.classList.add('active');
            applyCampusProgramFilters();
        });
    });

    if (programSearch) {
        programSearch.addEventListener('input', applyCampusProgramFilters);
    }
    applyCampusProgramFilters();
})();
