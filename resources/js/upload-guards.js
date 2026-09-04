(() => {
    // Runs before form loading/autosave listeners; server validation remains authoritative.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.querySelector('input[type="file"]')) return;
        const entries = [...new FormData(form).values()];
        const files = entries.filter((value) => value instanceof File && value.name);
        const bytes = entries.reduce((total, value) => total + (value instanceof File ? value.size : new Blob([value]).size), 0);
        let message; let code;
        const image = files.find((file) => (file.type.startsWith('image/') || /\.(jpe?g|png|webp|svg|gif|avif|heic|tiff?|bmp)$/i.test(file.name)) && file.size > 5 * 1024 * 1024);
        if (image) { code = 'image_too_large'; message = `${image.name}: image upload failed. The original image must be 5 MB or smaller. Choose a smaller image and try again.`; }
        else if (files.length > 20) { code = 'too_many_files'; message = 'Select no more than 20 files per submission. Upload supplementary files in smaller batches.'; }
        else if (files.some((file) => !file.type.startsWith('image/') && !/\.(jpe?g|png|webp|svg)$/i.test(file.name) && file.size > 20 * 1024 * 1024)) { code = 'document_too_large'; message = 'Document upload failed. Each document must be 20 MB or smaller. Select a smaller document.'; }
        else if (bytes > 32 * 1024 * 1024 - 65536) { code = 'upload_too_large'; message = 'This submission exceeds the 32 MB request limit. Select fewer or smaller files before submitting.'; }
        if (message) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.JournalErrors ? window.JournalErrors.show([{code, message}]) : window.alert(`${message}\nError code: ${code}`);
        }
    }, true);
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-loading] button[data-original-text]').forEach((button) => {
            button.disabled = false;
            button.textContent = button.dataset.originalText;
        });
    });
})();
