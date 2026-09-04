const ready = (callback) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, { once: true });
    else callback();
};

ready(() => {
    const header = document.querySelector('[data-site-header]');
    const menuButton = document.querySelector('[data-menu-toggle]');
    const mobileMenu = document.querySelector('[data-mobile-menu]');
    const accountButton = document.querySelector('[data-account-toggle]');
    const accountMenu = document.querySelector('[data-account-menu]');
    const searchDialog = document.querySelector('[data-search-dialog]');
    const searchInput = document.querySelector('[data-search-input]');

    const closeMobileMenu = () => {
        if (!menuButton || !mobileMenu) return;
        menuButton.setAttribute('aria-expanded', 'false');
        mobileMenu.hidden = true;
    };
    const closeAccountMenu = () => {
        if (!accountButton || !accountMenu) return;
        accountButton.setAttribute('aria-expanded', 'false');
        accountMenu.hidden = true;
    };
    menuButton?.addEventListener('click', () => {
        const opening = menuButton.getAttribute('aria-expanded') !== 'true';
        closeAccountMenu();
        menuButton.setAttribute('aria-expanded', String(opening));
        mobileMenu.hidden = !opening;
    });
    accountButton?.addEventListener('click', () => {
        const opening = accountButton.getAttribute('aria-expanded') !== 'true';
        closeMobileMenu();
        accountButton.setAttribute('aria-expanded', String(opening));
        accountMenu.hidden = !opening;
    });
    window.addEventListener('resize', () => { if (window.innerWidth > 860) { closeMobileMenu(); closeAccountMenu(); } }, { passive: true });

    let searchCloseTimer;
    const openSearch = () => {
        closeMobileMenu();
        closeAccountMenu();
        if (!searchDialog) return;
        window.clearTimeout(searchCloseTimer);
        searchDialog.classList.remove('is-closing');
        if (!searchDialog.open && typeof searchDialog.showModal === 'function') searchDialog.showModal();
        else searchDialog.setAttribute('open', '');
        window.setTimeout(() => searchInput?.focus(), 30);
    };
    const closeSearch = () => {
        if (!searchDialog?.open) return;
        searchDialog.classList.add('is-closing');
        searchCloseTimer = window.setTimeout(() => {
            if (typeof searchDialog.close === 'function') searchDialog.close();
            else searchDialog.removeAttribute('open');
            searchDialog.classList.remove('is-closing');
        }, 220);
    };
    document.querySelectorAll('[data-search-open]').forEach((button) => button.addEventListener('click', openSearch));
    document.querySelectorAll('[data-search-close]').forEach((button) => button.addEventListener('click', closeSearch));
    searchDialog?.addEventListener('click', (event) => { if (event.target === searchDialog) closeSearch(); });
    searchDialog?.addEventListener('cancel', (event) => { event.preventDefault(); closeSearch(); });
    document.addEventListener('keydown', (event) => {
        const target = event.target;
        const typing = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement || target?.isContentEditable;
        if (event.key === '/' && !typing && !event.metaKey && !event.ctrlKey && !event.altKey) {
            event.preventDefault(); openSearch();
        }
        if (event.key === 'Escape') closeMobileMenu();
    });

    const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 8);
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const toast = document.querySelector('[data-toast]');
    const dismissToast = () => toast?.remove();
    document.querySelector('[data-toast-close]')?.addEventListener('click', dismissToast);
    if (toast) window.setTimeout(dismissToast, 7000);

    document.querySelectorAll('[data-submitting-form]').forEach((form) => {
        form.addEventListener('submit', () => { if (form.checkValidity()) form.classList.add('is-submitting'); });
    });
    const messageField = document.querySelector('#contact-message');
    const characterCount = document.querySelector('[data-character-count]');
    if (messageField && characterCount) {
        const updateCount = () => { characterCount.textContent = `${messageField.value.length.toLocaleString()} / 10,000`; };
        updateCount(); messageField.addEventListener('input', updateCount);
    }
    document.querySelector('[data-form-errors]')?.focus();

    const article = document.querySelector('[data-reading-article]');
    const progress = document.querySelector('[data-reading-progress]');
    if (article && progress) {
        let ticking = false;
        const updateProgress = () => {
            const rect = article.getBoundingClientRect();
            const articleTop = window.scrollY + rect.top;
            const distance = Math.max(1, article.offsetHeight - window.innerHeight);
            progress.style.transform = `scaleX(${Math.min(1, Math.max(0, (window.scrollY - articleTop) / distance))})`;
            ticking = false;
        };
        window.addEventListener('scroll', () => {
            if (!ticking) { requestAnimationFrame(updateProgress); ticking = true; }
        }, { passive: true });
        updateProgress();
    }

    const content = document.querySelector('[data-article-content]');
    const toc = document.querySelector('[data-toc]');
    const tocWrapper = document.querySelector('[data-toc-wrapper]');
    if (content && toc && tocWrapper) {
        const headings = [...content.querySelectorAll('h2, h3')];
        const usedIds = new Set();
        const slugify = (text) => text.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section';
        headings.forEach((heading) => {
            let id = heading.id || slugify(heading.textContent.trim());
            const originalId = id;
            let suffix = 2;
            while (usedIds.has(id)) id = `${originalId}-${suffix++}`;
            usedIds.add(id); heading.id = id;
            const item = document.createElement('li');
            if (heading.tagName === 'H3') item.classList.add('is-subheading');
            const link = document.createElement('a');
            link.href = `#${encodeURIComponent(id)}`; link.textContent = heading.textContent.trim();
            item.append(link); toc.append(item);
        });
        if (headings.length > 1) {
            tocWrapper.hidden = false;
            if ('IntersectionObserver' in window) {
                const links = new Map([...toc.querySelectorAll('a')].map((link) => [decodeURIComponent(link.hash.slice(1)), link]));
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        links.forEach((link) => link.classList.remove('is-active'));
                        links.get(entry.target.id)?.classList.add('is-active');
                    });
                }, { rootMargin: '-18% 0px -70% 0px', threshold: 0 });
                headings.forEach((heading) => observer.observe(heading));
            }
        }
    }

    const announce = (message) => {
        const region = document.createElement('div');
        region.className = 'sr-only'; region.setAttribute('role', 'status'); region.textContent = message;
        document.body.append(region); window.setTimeout(() => region.remove(), 1800);
    };
    const copyText = async (value, message) => {
        try { await navigator.clipboard.writeText(value); announce(message); }
        catch {
            const area = document.createElement('textarea');
            area.value = value; area.setAttribute('readonly', ''); area.style.position = 'fixed'; area.style.opacity = '0';
            document.body.append(area); area.select(); document.execCommand('copy'); area.remove(); announce(message);
        }
    };
    document.querySelectorAll('[data-copy-url]').forEach((button) => button.addEventListener('click', () => copyText(button.dataset.copyUrl, 'Article link copied.')));
    document.querySelector('[data-copy-citation]')?.addEventListener('click', () => {
        const citation = document.querySelector('[data-citation-text]')?.textContent?.trim();
        if (citation) copyText(citation, 'Citation copied.');
    });
    document.querySelectorAll('[data-share-native]').forEach((button) => {
        button.addEventListener('click', async () => {
            const shareData = { title: button.dataset.shareTitle, url: button.dataset.shareUrl };
            if (navigator.share) {
                try { await navigator.share(shareData); }
                catch (error) { if (error?.name !== 'AbortError') await copyText(shareData.url, 'Article link copied.'); }
            } else await copyText(shareData.url, 'Article link copied.');
        });
    });
});

ready(() => {
    document.querySelectorAll('[data-hero-slider]').forEach((slider) => {
        const slides = [...slider.querySelectorAll('[data-hero-slide]')];
        if (slides.length < 2) return;
        const controls = slider.querySelector('[data-hero-controls]');
        const pause = slider.querySelector('[data-hero-pause]');
        const count = slider.querySelector('[data-hero-count]');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let current = 0;
        let paused = motion.matches;
        let hovering = false;
        let timer;
        const show = (index) => {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, i) => { slide.hidden = i !== current; });
            count.textContent = `${current + 1} / ${slides.length}`;
        };
        const schedule = () => {
            window.clearInterval(timer);
            pause.textContent = paused ? 'Play slideshow' : 'Pause slideshow';
            if (!paused && !hovering && !document.hidden) timer = window.setInterval(() => show(current + 1), 7000);
        };
        controls.hidden = false;
        slider.querySelector('[data-hero-prev]').addEventListener('click', () => { show(current - 1); schedule(); });
        slider.querySelector('[data-hero-next]').addEventListener('click', () => { show(current + 1); schedule(); });
        pause.addEventListener('click', () => { paused = !paused; schedule(); });
        slider.addEventListener('mouseenter', () => { hovering = true; schedule(); });
        slider.addEventListener('mouseleave', () => { hovering = false; schedule(); });
        // Keyboard focus stops rotation until the reader explicitly restarts it.
        slider.addEventListener('focusin', () => { paused = true; schedule(); });
        document.addEventListener('visibilitychange', schedule);
        motion.addEventListener('change', () => { if (motion.matches) paused = true; schedule(); });
        schedule();
    });
});

ready(() => {
    const fallback = (image) => {
        const src = image.dataset.imageFallback;
        if (!src) return;
        delete image.dataset.imageFallback;
        image.removeAttribute('srcset');
        image.src = src;
    };
    document.querySelectorAll('img[data-image-fallback]').forEach((image) => {
        image.addEventListener('error', () => fallback(image));
        if (image.complete && image.naturalWidth === 0) fallback(image);
    });
});
