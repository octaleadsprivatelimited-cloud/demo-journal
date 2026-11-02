export async function readPageText(page) {
    // PDF.js getTextContent uses ReadableStream async iteration, which is not
    // available in every supported browser. Read the same chunks explicitly.
    const reader = page.streamTextContent().getReader();
    const parts = [];
    try {
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            for (const item of value.items) {
                if (typeof item.str === 'string') {
                    parts.push(item.str, item.hasEOL ? '\n' : ' ');
                }
            }
        }
    } finally {
        reader.releaseLock();
    }
    return parts.join('');
}
