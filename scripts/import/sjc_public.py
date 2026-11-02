"""Snapshot every public SJC article and original PDF; no private records or forms."""
import argparse
import hashlib
import json
import re
import subprocess
import time
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin, urlsplit
from sjnr_public import Parser

BASE = 'https://sjcjournal.com'


def fetch(url, target):
    parts = urlsplit(url)
    if parts.scheme != 'https' or parts.hostname != 'sjcjournal.com' or parts.username or parts.password or parts.port:
        raise ValueError('Unexpected public source URL')
    if target.is_file():
        return target.read_bytes()
    target.parent.mkdir(parents=True, exist_ok=True)
    for attempt in range(3):
        result = subprocess.run(['curl', '--fail', '--silent', '--show-error', '--max-time', '90', '--max-filesize', '80000000', '--proto', '=https', url], capture_output=True)
        if result.returncode == 0:
            target.write_bytes(result.stdout)
            return result.stdout
        time.sleep(attempt + 1)
    raise RuntimeError('Public fetch failed: ' + url)


def collect(directory):
    if Path(__file__).resolve().parents[2] in directory.parents:
        raise ValueError('Snapshot must stay outside Git')
    paths = set()
    listing_counts = []
    for path in ['/archive', '/articles', '/articles?page=2', '/articles?page=3']:
        doc = fetch(BASE + path, directory / 'html' / (hashlib.sha256(path.encode()).hexdigest() + '.html')).decode()
        listing_counts += re.findall(r'(\d+) publications', Parser(doc).root.text())
        tree = Parser(doc).root
        for link in tree.find(lambda n: n.tag == 'a'):
            url = urljoin(BASE, link.attrs.get('href', ''))
            parts = urlsplit(url)
            if parts.hostname == 'sjcjournal.com' and re.fullmatch(r'/article/[a-z0-9]+(?:-[a-z0-9]+)*', parts.path):
                paths.add(parts.path)
    if not listing_counts or len(paths) != int(listing_counts[0]) or len(paths) != 39:
        raise ValueError(f'Incomplete public inventory: {len(paths)}, source totals {listing_counts}')

    def article(path):
        slug = path.rsplit('/', 1)[1]
        identity = hashlib.sha256(path.encode()).hexdigest()[:20]
        doc = fetch(BASE + path, directory / 'html' / (identity + '.html')).decode()
        tree = Parser(doc).root
        metas = {}
        for node in tree.find(lambda n: n.tag == 'meta'):
            metas.setdefault(node.attrs.get('name', node.attrs.get('property', '')), []).append(node.attrs.get('content', ''))
        metadata = next(json.loads(node.text()) for node in tree.find(lambda n: n.tag == 'script' and n.attrs.get('type') == 'application/ld+json'))
        title = metas['citation_title'][0]
        authors = metas.get('citation_author', [])
        published = metas['citation_publication_date'][0].replace('/', '-')
        pdf_url = metas['citation_pdf_url'][0]
        body = fetch(pdf_url, directory / 'pdfs' / (identity + '.pdf'))
        if not body.startswith(b'%PDF-'):
            raise ValueError('Invalid original PDF: ' + path)
        details = {}
        for group in tree.find(lambda n: n.tag == 'dl')[:1]:
            for div in group.find(lambda n: n.tag == 'div'):
                terms = div.find(lambda n: n.tag == 'dt')
                definitions = div.find(lambda n: n.tag == 'dd')
                if terms and definitions:
                    details[terms[0].text().lower()] = definitions[0].text()
        abstracts = tree.find(lambda n: n.has('article-abstract'))
        abstract = None
        if abstracts:
            paragraphs = abstracts[0].find(lambda n: n.tag == 'p')
            abstract = ' '.join(n.text() for n in paragraphs)
            if abstract.startswith('A separate abstract is not available'):
                abstract = None
        keyword_nodes = tree.find(lambda n: n.has('article-keywords'))
        keywords = [n.text() for group in keyword_nodes for n in group.find(lambda n: n.tag == 'a')]
        row = {'id': identity, 'source_url': BASE + path, 'slug': slug, 'title': title, 'authors': authors,
               'published_date': published, 'doi': (metas.get('citation_doi') or [None])[0], 'abstract': abstract,
               'keywords': keywords, 'category': metadata.get('articleSection') or 'Cardiology',
               'volume': details.get('volume'), 'issue': details.get('issue'),
               'pdf': {'source_url': pdf_url, 'file': 'pdfs/' + identity + '.pdf', 'sha256': hashlib.sha256(body).hexdigest(), 'bytes': len(body)},
               'source_journal': metas['citation_journal_title'][0], 'source_issn': (metas.get('citation_eissn') or [None])[0]}
        heroes = tree.find(lambda n: n.has('article-hero-image'))
        if heroes:
            images = heroes[0].find(lambda n: n.tag == 'img')
            if images:
                image_url = urljoin(BASE, images[0].attrs['src'])
                suffix = Path(urlsplit(image_url).path).suffix.lower()
                if suffix not in ['.jpg', '.jpeg', '.png', '.webp']:
                    raise ValueError('Unsupported public cover image')
                image = fetch(image_url, directory / 'images' / (identity + suffix))
                row['image'] = {'source_url': image_url, 'file': 'images/' + identity + suffix, 'sha256': hashlib.sha256(image).hexdigest(), 'bytes': len(image)}
        return row

    with ThreadPoolExecutor(max_workers=2) as pool:
        articles = list(pool.map(article, sorted(paths)))
    manifest = {'schema_version': 1, 'source': BASE, 'source_count': 39, 'collected_at': datetime.now(timezone.utc).isoformat(), 'articles': articles}
    (directory / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2))
    print(json.dumps({'articles': len(articles), 'pdfs': len(articles), 'images': sum('image' in a for a in articles), 'pdf_bytes': sum(a['pdf']['bytes'] for a in articles)}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', required=True, type=Path)
    collect(parser.parse_args().output.resolve())
