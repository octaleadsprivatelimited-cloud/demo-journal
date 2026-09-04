(() => {
    let dialog;
    let previousFocus;
    window.JournalErrors = {
        show(errors) {
            if (!errors?.length) return;
            if (!dialog) {
                dialog = document.createElement('dialog');
                dialog.setAttribute('aria-labelledby', 'journal-error-title');
                dialog.style.cssText = 'max-width:520px;width:calc(100% - 48px);border:1px solid #d9d3c8;border-radius:12px;padding:24px;color:#173b33;background:#fffdf8;box-shadow:0 20px 80px #0005;font:16px/1.5 system-ui;max-height:80vh;overflow:auto';
                const heading = document.createElement('h2'); heading.id = 'journal-error-title'; heading.textContent = 'Unable to complete this action'; heading.style.cssText = 'font:600 23px/1.25 system-ui;margin:0 0 18px';
                const list = document.createElement('ul'); list.dataset.errorList = ''; list.style.cssText = 'padding-left:20px;margin:0 0 20px';
                const close = document.createElement('button'); close.type = 'button'; close.textContent = 'Close'; close.autofocus = true;
                close.style.cssText = 'min-height:44px;padding:8px 24px;background:#173b33;color:white;border:0;border-radius:5px;cursor:pointer';
                close.addEventListener('click', () => dialog.close());
                dialog.addEventListener('close', () => previousFocus?.focus());
                dialog.append(heading, list, close); document.body.append(dialog);
            }
            const list = dialog.querySelector('[data-error-list]'); list.replaceChildren();
            for (const error of errors) {
                const item = document.createElement('li'); item.style.marginBottom = '14px';
                const message = document.createElement('div'); message.textContent = error.message;
                const code = document.createElement('small'); code.textContent = `Error code: ${error.code || 'request_failed'}`;
                item.append(message, code); list.append(item);
            }
            if (typeof dialog.showModal !== 'function') {
                window.alert(errors.map((e) => `${e.message}\nError code: ${e.code || 'request_failed'}`).join('\n\n')); return;
            }
            if (!dialog.open) { previousFocus = document.activeElement; dialog.showModal(); }
        }
    };
    document.addEventListener('invalid', (event) => {
        const field = event.target;
        if (!field.form) return;
        event.preventDefault();
        const invalid = [...field.form.elements].filter((input) => input.willValidate && !input.validity.valid);
        window.JournalErrors.show(invalid.map((input) => ({
            code: input.validity.valueMissing ? 'required_field' : input.validity.typeMismatch ? 'invalid_format' : 'invalid_value',
            message: `${input.labels?.[0]?.textContent.trim().slice(0, 80) || input.name || 'Field'}: ${input.validationMessage}`,
        })));
    }, true);
    const initial = document.querySelector('[data-journal-errors]');
    if (initial) {
        try { window.JournalErrors.show(JSON.parse(initial.textContent)); } catch (_) { /* Inline field errors remain available. */ }
    }
})();
