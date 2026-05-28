(function () {
    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }
        callback();
    };

    ready(() => {
        const nav = document.querySelector('.navbar-campus');
        const syncNav = () => {
            if (!nav) {
                return;
            }
            nav.classList.toggle('scrolled', window.scrollY > 18);
        };
        syncNav();
        window.addEventListener('scroll', syncNav, { passive: true });

        const revealTargets = document.querySelectorAll(
            'main section, .program-card, .track-card, .step-card, .soon-card, .news-action-card, .premium-reveal, .premium-news-card, .premium-feature-card'
        );

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

            revealTargets.forEach((target, index) => {
                target.classList.add('premium-reveal');
                target.style.transitionDelay = `${Math.min(index % 6, 5) * 45}ms`;
                observer.observe(target);
            });
        } else {
            revealTargets.forEach((target) => target.classList.add('is-visible'));
        }

        const tabs = Array.from(document.querySelectorAll('.program-tab'));
        const items = Array.from(document.querySelectorAll('.program-item'));
        const search = document.querySelector('#programSearch');
        const count = document.querySelector('#programMatchCount');
        const empty = document.querySelector('#programNoResults');

        const applyProgramFilters = () => {
            if (!items.length) {
                return;
            }

            const activeTab = tabs.find((tab) => tab.classList.contains('active'));
            const filter = activeTab ? activeTab.dataset.filter : 'all';
            const query = search ? search.value.trim().toLowerCase() : '';
            let visible = 0;

            items.forEach((item) => {
                const categoryMatch = filter === 'all' || item.dataset.category === filter;
                const textMatch = !query || item.textContent.toLowerCase().includes(query);
                const shouldShow = categoryMatch && textMatch;
                item.classList.toggle('d-none', !shouldShow);
                if (shouldShow) {
                    visible += 1;
                }
            });

            if (count) {
                count.textContent = `${visible} program${visible === 1 ? '' : 's'} found`;
            }
            if (empty) {
                empty.classList.toggle('d-none', visible !== 0);
            }
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                tabs.forEach((button) => button.classList.remove('active'));
                tab.classList.add('active');
                applyProgramFilters();
            });
        });

        if (search) {
            search.addEventListener('input', applyProgramFilters);
        }
        applyProgramFilters();

        if (window.matchMedia('(pointer: fine)').matches) {
            document.querySelectorAll('.program-card, .track-card, .premium-news-card').forEach((card) => {
                card.addEventListener('mousemove', (event) => {
                    const rect = card.getBoundingClientRect();
                    const x = ((event.clientX - rect.left) / rect.width - 0.5) * 5;
                    const y = ((event.clientY - rect.top) / rect.height - 0.5) * -5;
                    card.style.transform = `translateY(-6px) rotateX(${y}deg) rotateY(${x}deg)`;
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = '';
                });
            });
        }
    });
})();
