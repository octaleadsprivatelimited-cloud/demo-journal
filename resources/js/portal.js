(() => {
    const shell = document.querySelector('[data-portal-shell]');
    const open = document.querySelector('[data-sidebar-open]');
    const closeSidebar = () => { shell?.classList.remove('sidebar-open'); open?.setAttribute('aria-expanded', 'false'); };
    open?.addEventListener('click', () => { shell?.classList.add('sidebar-open'); open.setAttribute('aria-expanded', 'true'); });
    document.querySelectorAll('[data-sidebar-close]').forEach((node) => node.addEventListener('click', closeSidebar));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSidebar(); });

    document.querySelectorAll('[data-toast-close]').forEach((button) => button.addEventListener('click', () => button.closest('[data-toast]')?.remove()));
    const toast = document.querySelector('[data-toast]'); if (toast) window.setTimeout(() => toast.remove(), 6500);

    document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm || 'Are you sure?')) event.preventDefault();
    }));
    document.querySelectorAll('form[data-loading]').forEach((form) => form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]'); if (button) { button.disabled = true; button.dataset.originalText = button.textContent; button.textContent = button.dataset.loadingText || 'Working…'; }
    }));

    const autosave = document.querySelector('form[data-autosave]');
    if (autosave) {
        let timer; let saving = false; let queued = false; const status = autosave.querySelector('[data-autosave-status]'); const timestamp = autosave.querySelector('[name="updated_at"]');
        const save = async () => {
            if (saving) { queued = true; return; } saving = true; queued = false; status?.classList.add('is-saving'); if (status) status.textContent = 'Saving draft…';
            const data = Object.fromEntries(new FormData(autosave).entries()); ['keywords','references'].forEach((key) => { if (typeof data[key] === 'string') data[key] = data[key].split(key === 'keywords' ? ',' : '\n').map((v) => v.trim()).filter(Boolean); });
            try { const response = await fetch(autosave.dataset.autosave, { method: 'PATCH', headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content}, body: JSON.stringify(data) }); const result = await response.json();
                if (response.status === 409) { if (status) { status.textContent = result.message; status.style.color = 'var(--portal-danger)'; } return; }
                if (!response.ok) throw new Error(result.message || 'Autosave failed'); if (timestamp) timestamp.value = result.updated_at; if (status) { status.textContent = `Saved ${new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}`; status.style.color = ''; }
            } catch (error) { if (status) { status.textContent = 'Could not autosave. Your text remains in this browser.'; status.style.color = 'var(--portal-danger)'; } }
            finally { saving = false; status?.classList.remove('is-saving'); if (queued) save(); }
        };
        autosave.addEventListener('input', () => { clearTimeout(timer); if (status) status.textContent = 'Unsaved changes'; timer = setTimeout(() => { timer = null; save(); }, 1800); });
        window.addEventListener('beforeunload', (event) => { if (timer) { event.preventDefault(); event.returnValue = ''; } });
    }

    document.querySelectorAll('[data-check-all]').forEach((master) => master.addEventListener('change', () => document.querySelectorAll(`[data-check-group="${master.dataset.checkAll}"]`).forEach((box) => box.checked = master.checked)));
    document.querySelectorAll('[data-add-review-comment]').forEach((button) => button.addEventListener('click', () => {
        const container = document.querySelector(button.dataset.addReviewComment); const template = document.querySelector(button.dataset.template); if (!container || !template) return;
        const index = container.children.length; container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
    }));

    document.querySelectorAll('[data-rich-editor]').forEach((root) => {
        const canvas = root.querySelector('.rich-editor-canvas'); const field = root.querySelector('textarea');
        const sync = () => { field.value = canvas.innerHTML; field.dispatchEvent(new Event('input', {bubbles:true})); };
        const command = (name, value = null) => { canvas.focus(); document.execCommand(name, false, value); sync(); };
        root.querySelectorAll('[data-editor-command]').forEach((button) => button.addEventListener('click', () => command(button.dataset.editorCommand)));
        root.querySelector('[data-editor-block]')?.addEventListener('change', (event) => command('formatBlock', event.target.value));
        root.querySelector('[data-editor-block-command]')?.addEventListener('click', (event) => command('formatBlock', event.currentTarget.dataset.editorBlockCommand));
        root.querySelector('[data-editor-link]')?.addEventListener('click', () => { const url = window.prompt('Paste an https:// link'); if (url && /^https:\/\//i.test(url)) command('createLink', url); });
        root.querySelector('[data-editor-table]')?.addEventListener('click', () => command('insertHTML', '<table><thead><tr><th>Heading</th><th>Heading</th></tr></thead><tbody><tr><td>Cell</td><td>Cell</td></tr></tbody></table><p><br></p>'));
        root.querySelector('[data-editor-image]')?.addEventListener('click', () => { const url = window.prompt('Image URL (https://)'); if (!url || !/^https:\/\//i.test(url)) return; const alt = window.prompt('Describe the image for readers') || ''; const caption = window.prompt('Caption (optional)') || ''; const escape = (text) => text.replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char])); command('insertHTML', `<figure><img src="${escape(url)}" alt="${escape(alt)}"><figcaption>${escape(caption)}</figcaption></figure><p><br></p>`); });
        root.querySelector('[data-editor-video]')?.addEventListener('click', () => { const input = window.prompt('YouTube or Vimeo URL'); if (!input) return; let src = ''; try { const url = new URL(input); if (['youtube.com','www.youtube.com','youtu.be'].includes(url.hostname)) { const id = url.hostname === 'youtu.be' ? url.pathname.slice(1) : url.searchParams.get('v'); if (/^[\w-]{6,20}$/.test(id || '')) src = `https://www.youtube-nocookie.com/embed/${id}`; } if (['vimeo.com','www.vimeo.com','player.vimeo.com'].includes(url.hostname)) { const id = url.pathname.split('/').filter(Boolean).pop(); if (/^\d+$/.test(id || '')) src = `https://player.vimeo.com/video/${id}`; } } catch (_) {} if (!src) { window.alert('Use a valid YouTube or Vimeo video URL.'); return; } command('insertHTML', `<figure><iframe src="${src}" title="Embedded video" loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></figure><p><br></p>`); });
        canvas.addEventListener('input', sync); canvas.addEventListener('blur', sync); root.closest('form')?.addEventListener('submit', sync);
    });
})();
