import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readPageText } from '../../resources/js/pdf-reader-text.js';

const root = fileURLToPath(new URL('../../', import.meta.url));
const readerCode = readFileSync(new URL('../../resources/js/article-reader.js', import.meta.url), 'utf8');
const mainModule = readerCode.match(/import \{ getDocument, GlobalWorkerOptions \} from '([^']+)'/)[1];
const workerModule = readerCode.match(/import workerUrl from '([^']+)'/)[1].replace('?worker&url', '');
const textModule = new URL('../../resources/js/pdf-reader-text.js', import.meta.url).href;

function isolated(script) {
    const result = spawnSync(process.execPath, ['--input-type=module', '-e', script], {
        cwd: root, encoding: 'utf8', timeout: 30_000,
    });
    assert.ifError(result.error);
    assert.equal(result.status, 0, result.stderr || result.stdout);
    return result.stdout;
}

// Separate processes ensure the browser entry and worker must each provide
// their own compatibility support instead of borrowing the other one's globals.
const withoutRecentMethods = `
    delete Uint8Array.prototype.toHex;
    delete Map.prototype.getOrInsertComputed;
    delete Map.prototype.getOrInsert;
    delete WeakMap.prototype.getOrInsertComputed;
    delete WeakMap.prototype.getOrInsert;
`;

test('reader renders and extracts both pages without recent methods or stream async iteration', () => {
    const output = isolated(String.raw`
        import assert from 'node:assert/strict';
        import { fileURLToPath } from 'node:url';
        import { createCanvas, DOMMatrix, ImageData, Path2D } from '@napi-rs/canvas';
        ${withoutRecentMethods}
        delete ReadableStream.prototype[Symbol.asyncIterator];
        Object.assign(globalThis, { DOMMatrix, ImageData, Path2D });
        const { getDocument, GlobalWorkerOptions } = await import(${JSON.stringify(mainModule)});
        const { readPageText } = await import(${JSON.stringify(textModule)});
        GlobalWorkerOptions.workerSrc = import.meta.resolve(${JSON.stringify(workerModule)});
        assert.equal(ReadableStream.prototype[Symbol.asyncIterator], undefined);

        // An original, self-contained PDF fixture, with different text on two
        // pages, exercises the installed parser, worker, font and paint code.
        const firstText = 'Nursing research page one';
        const secondText = 'Patient care page two';
        const stream = text => 'BT /F1 18 Tf 25 95 Td (' + text + ') Tj ET';
        const firstStream = stream(firstText);
        const secondStream = stream(secondText);
        const objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 320 144] /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 320 144] /Resources << /Font << /F1 5 0 R >> >> /Contents 7 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length ' + firstStream.length + ' >>\nstream\n' + firstStream + '\nendstream',
            '<< /Length ' + secondStream.length + ' >>\nstream\n' + secondStream + '\nendstream',
        ];
        let source = '%PDF-1.4\n';
        const offsets = [0];
        objects.forEach((object, index) => {
            offsets.push(Buffer.byteLength(source));
            source += (index + 1) + ' 0 obj\n' + object + '\nendobj\n';
        });
        const xref = Buffer.byteLength(source);
        source += 'xref\n0 8\n0000000000 65535 f \n';
        source += offsets.slice(1).map(offset => String(offset).padStart(10, '0') + ' 00000 n \n').join('');
        source += 'trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF';
        const loading = getDocument({
            data: new Uint8Array(Buffer.from(source)), isEvalSupported: false,
            standardFontDataUrl: fileURLToPath(new URL('./standard_fonts/', import.meta.resolve('pdfjs-dist/package.json'))),
        });
        try {
            const pdf = await loading.promise;
            assert.equal(pdf.numPages, 2);
            const pictures = [];
            for (const [index, expected] of [firstText, secondText].entries()) {
                const page = await pdf.getPage(index + 1);
                const viewport = page.getViewport({ scale: 1 });
                const canvas = createCanvas(viewport.width, viewport.height);
                const context = canvas.getContext('2d');
                await page.render({ canvasContext: context, viewport }).promise;
                const text = await readPageText(page);
                assert.equal(text.trim(), expected);
                const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
                assert.ok(pixels.some((value, position) => position % 4 !== 3 && value < 128), 'page text must actually be painted');
                pictures.push(canvas.toBuffer('image/png').toString('base64'));
            }
            assert.notEqual(pictures[0], pictures[1], 'moving to the next page must render the new content');
            console.log('two pages rendered and extracted');
        } finally {
            await loading.destroy();
        }
    `);
    assert.match(output, /two pages rendered and extracted/);
});

test('reader text preserves line endings across chunks and releases the stream lock', async () => {
    const stream = new ReadableStream({
        start(controller) {
            controller.enqueue({ items: [{ str: 'First line', hasEOL: true }, { type: 'beginMarkedContent' }] });
            controller.enqueue({ items: [{ str: 'Second' }, { str: 'line', hasEOL: true }] });
            controller.close();
        },
    });
    assert.equal(await readPageText({ streamTextContent: () => stream }), 'First line\nSecond line\n');
    assert.equal(stream.locked, false);
});

test('reader text rejects real extraction errors and releases its stream lock', async () => {
    const failure = new Error('PDF text stream failed');
    const stream = new ReadableStream({ start(controller) { controller.error(failure); } });
    await assert.rejects(readPageText({ streamTextContent: () => stream }), failure);
    assert.equal(stream.locked, false);
});

test('reader worker supplies compatibility methods in its separate JavaScript context', () => {
    isolated(`
        import assert from 'node:assert/strict';
        ${withoutRecentMethods}
        await import(${JSON.stringify(workerModule)});
        assert.equal(new Uint8Array([0, 15, 255]).toHex(), '000fff');
        const cached = new Map();
        assert.equal(cached.getOrInsertComputed('font', () => 'loaded'), 'loaded');
        assert.equal(cached.getOrInsertComputed('font', () => { throw new Error('must reuse cached font'); }), 'loaded');
        const objects = new WeakMap();
        const page = {};
        assert.equal(objects.getOrInsertComputed(page, () => 2), 2);
        assert.equal(objects.getOrInsertComputed(page, () => 3), 2);
    `);
});
