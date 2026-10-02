import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?worker&url';
GlobalWorkerOptions.workerSrc = workerUrl;

export async function mountReader(root) {
    const status = root.querySelector('[data-pdf-status]');
    const canvas = root.querySelector('canvas');
    const text = root.querySelector('[data-pdf-text]');
    const previous = root.querySelector('[data-pdf-previous]');
    const next = root.querySelector('[data-pdf-next]');
    const zoom = root.querySelector('[data-pdf-zoom]');
    let current = 1, busy = false, pdf;
    async function render() {
        if (busy) return;
        busy = true;
        previous.disabled = next.disabled = zoom.disabled = true;
        status.textContent = `Loading page ${current}…`;
        try {
            const page = await pdf.getPage(current);
            const original = page.getViewport({scale: 1});
            const scale = Math.max(0.25, (root.clientWidth - 24) / original.width) * Number(zoom.value);
            const viewport = page.getViewport({scale});
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.round(viewport.width * ratio);
            canvas.height = Math.round(viewport.height * ratio);
            canvas.style.width = `${viewport.width}px`;
            canvas.style.height = `${viewport.height}px`;
            canvas.setAttribute('aria-label', `Article page ${current} of ${pdf.numPages}`);
            await page.render({canvasContext: canvas.getContext('2d'), viewport, transform: ratio === 1 ? null : [ratio,0,0,ratio,0,0]}).promise;
            const content = await page.getTextContent();
            text.textContent = content.items.map(item => item.str + (item.hasEOL ? '\n' : ' ')).join('');
            status.textContent = `Page ${current} of ${pdf.numPages}`;
            root.dataset.renderedPage = String(current);
        } catch {
            status.textContent = 'This page could not load. Use the PDF download below to read the article.';
        } finally {
            busy = false;
            previous.disabled = current <= 1;
            next.disabled = current >= pdf.numPages;
            zoom.disabled = false;
        }
    }
    try {
        pdf = await getDocument({url: root.dataset.pdfReader, isEvalSupported: false}).promise;
        previous.addEventListener('click', () => {if (!busy && current > 1) { current--; render(); }});
        next.addEventListener('click', () => {if (!busy && current < pdf.numPages) { current++; render(); }});
        zoom.addEventListener('change', render);
        let timer;
        window.addEventListener('resize', () => {clearTimeout(timer);timer = setTimeout(render,200);});
        await render();
    } catch {
        status.textContent = 'The reader could not load. Open or download the PDF below to read the full article.';
    }
}
