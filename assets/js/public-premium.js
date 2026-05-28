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

        const newsTabs = Array.from(document.querySelectorAll('[data-news-filter]'));
        const newsItems = Array.from(document.querySelectorAll('.news-item'));
        const newsSearch = document.querySelector('#newsSearch');
        const newsCount = document.querySelector('#newsMatchCount');
        const newsEmpty = document.querySelector('#newsNoResults');

        const applyNewsFilters = () => {
            if (!newsItems.length) {
                return;
            }

            const activeTab = newsTabs.find((tab) => tab.classList.contains('active'));
            const filter = activeTab ? activeTab.dataset.newsFilter : 'all';
            const query = newsSearch ? newsSearch.value.trim().toLowerCase() : '';
            let visible = 0;

            newsItems.forEach((item) => {
                const categoryMatch = filter === 'all' || item.dataset.newsCategory === filter;
                const textMatch = !query || item.textContent.toLowerCase().includes(query);
                const shouldShow = categoryMatch && textMatch;
                item.classList.toggle('d-none', !shouldShow);
                if (shouldShow) {
                    visible += 1;
                }
            });

            if (newsCount) {
                newsCount.textContent = `${visible} update${visible === 1 ? '' : 's'} found`;
            }
            if (newsEmpty) {
                newsEmpty.classList.toggle('d-none', visible !== 0);
            }
        };

        newsTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                newsTabs.forEach((button) => button.classList.remove('active'));
                tab.classList.add('active');
                applyNewsFilters();
            });
        });

        if (newsSearch) {
            newsSearch.addEventListener('input', applyNewsFilters);
        }
        applyNewsFilters();

        const counters = document.querySelectorAll('.stat-number, .qs-stat-num, .hero-bridge-stat strong, [data-count]');
        const parseCount = (value) => {
            const match = String(value).replace(/,/g, '').match(/[\d.]+/);
            return match ? Number(match[0]) : null;
        };
        const formatCount = (target, original) => {
            if (/%/.test(original)) {
                return `${Math.round(target)}%`;
            }
            if (/\+/.test(original)) {
                return `${Math.round(target).toLocaleString()}+`;
            }
            return Number.isInteger(target) ? Math.round(target).toLocaleString() : target.toLocaleString();
        };
        const animateCounter = (element) => {
            if (element.dataset.counted === '1') {
                return;
            }
            const original = element.textContent.trim();
            const target = parseCount(original);
            if (!target) {
                return;
            }
            element.dataset.counted = '1';
            const start = performance.now();
            const duration = 1000;
            const tick = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                element.textContent = formatCount(target * eased, original);
                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    element.textContent = original;
                }
            };
            requestAnimationFrame(tick);
        };

        if ('IntersectionObserver' in window) {
            const counterObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        counterObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.35 });
            counters.forEach((counter) => counterObserver.observe(counter));
        } else {
            counters.forEach(animateCounter);
        }

        if (window.matchMedia('(pointer: fine)').matches) {
            document.querySelectorAll('.program-card, .track-card, .premium-news-card, .premium-bento-card, .campus-story-card').forEach((card) => {
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
