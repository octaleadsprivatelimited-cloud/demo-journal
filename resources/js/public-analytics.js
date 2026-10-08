(() => {
    'use strict';
    const element = document.getElementById('journal-analytics-config');
    if (!element) return;
    const config = JSON.parse(element.textContent);
    const banner = document.querySelector('[data-analytics-consent]');
    const key = 'sjc.analytics.consent.v1';
    const expires = 180 * 24 * 60 * 60 * 1000;
    let started = false;
    let acceptedInMemory = false;
    const choice = () => {
        try {
            const value = JSON.parse(localStorage.getItem(key));
            return value && Date.now() - value.at < expires ? value.accepted : null;
        } catch (_) { return null; }
    };
    const script = (src) => {
        const tag = document.createElement('script');
        tag.async = true;
        tag.src = src;
        tag.referrerPolicy = 'strict-origin';
        document.head.appendChild(tag);
    };
    const start = () => {
        if (started) return;
        started = true;
        acceptedInMemory = true;
        if (config.ga) {
            window['ga-disable-' + config.ga] = false;
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { window.dataLayer.push(arguments); };
            window.gtag('consent', 'default', {
                analytics_storage: 'granted', ad_storage: 'denied',
                ad_user_data: 'denied', ad_personalization: 'denied'
            });
            window.gtag('js', new Date());
            let referrer = '';
            try { referrer = new URL(document.referrer).origin + '/'; } catch (_) {}
            window.gtag('config', config.ga, {
                ...(config.campaign || {}),
                send_page_view: false, page_location: config.page, page_referrer: referrer,
                allow_google_signals: false, allow_ad_personalization_signals: false
            });
            window.gtag('event', 'page_view', {
                page_location: config.page, page_referrer: referrer, page_title: document.title
            });
            script('https://www.googletagmanager.com/gtag/js?id=' + config.ga);
        }
        if (config.clarity) {
            window.clarity = window.clarity || function () {
                (window.clarity.q = window.clarity.q || []).push(arguments);
            };
            window.clarity('consentv2', { analytics_Storage: 'granted', ad_Storage: 'denied' });
            script('https://www.clarity.ms/tag/' + config.clarity);
        }
    };
    const clearCookies = () => {
        const hosts = [location.hostname, '.' + location.hostname];
        document.cookie.split(';').forEach((cookie) => {
            const name = cookie.split('=')[0].trim();
            if (!/^(_ga(?:_|$)|_clck$|_clsk$)/.test(name)) return;
            document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax';
            hosts.forEach((host) => {
                document.cookie = name + '=; Max-Age=0; path=/; domain=' + host + '; SameSite=Lax';
            });
        });
    };
    const save = (accepted) => {
        try { localStorage.setItem(key, JSON.stringify({ accepted, at: Date.now() })); } catch (_) {}
        acceptedInMemory = accepted;
        banner.hidden = true;
        if (accepted) { start(); return; }
        if (config.ga) window['ga-disable-' + config.ga] = true;
        if (window.gtag) window.gtag('consent', 'update', {
            analytics_storage: 'denied', ad_storage: 'denied',
            ad_user_data: 'denied', ad_personalization: 'denied'
        });
        if (window.clarity) window.clarity('consentv2', { analytics_Storage: 'denied', ad_Storage: 'denied' });
        clearCookies();
        // Reload removes recording scripts immediately after withdrawing consent.
        if (started) location.reload();
    };
    document.querySelector('[data-analytics-accept]').addEventListener('click', () => save(true));
    document.querySelector('[data-analytics-reject]').addEventListener('click', () => save(false));
    document.querySelector('[data-analytics-preferences]').addEventListener('click', () => {
        banner.hidden = false;
        document.querySelector('[data-analytics-accept]').focus();
    });
    document.addEventListener('click', (event) => {
        if (!started || !acceptedInMemory || !window.gtag) return;
        const link = event.target.closest('a[href]');
        if (!link) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin) return;
        if (/^\/article\/[a-z0-9-]+\/pdf$/.test(url.pathname) || /^\/author-resources\/[a-z0-9-]+\.pdf$/.test(url.pathname)) {
            window.gtag('event', 'file_download', {
                file_extension: 'pdf', link_url: url.origin + url.pathname,
                file_name: url.pathname.split('/').pop(), page_location: config.page
            });
        }
    });
    if (choice() === true) start();
    else if (choice() === null) banner.hidden = false;
})();
