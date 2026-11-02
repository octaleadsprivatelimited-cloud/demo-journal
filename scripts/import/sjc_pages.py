"""Collect SJC's public journal information, editorial board and contributor profiles."""
import argparse
import hashlib
import json
import re
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin, urlsplit
from sjnr_public import Parser, Node
from sjc_public import fetch

BASE = 'https://sjcjournal.com'


def collect(directory):
    repository = Path(__file__).resolve().parents[2]
    if directory == repository or repository in directory.parents:
        raise ValueError('Keep public snapshots outside Git')
    directory.mkdir(parents=True, exist_ok=True)
    source_config = (repository / 'config/journal_pages.php').read_text()
    definitions = json.loads(source_config.split("<<<'JSON'\n", 1)[1].split('\nJSON,', 1)[0])
    assets, inventory, warnings = {}, [], []

    def document(path):
        url = urljoin(BASE, path)
        body = fetch(url, directory / 'html' / (hashlib.sha256(url.encode()).hexdigest() + '.html')).decode()
        inventory.append(url)
        return Parser(body).root

    def asset(url):
        url = urljoin(BASE, url)
        if url in assets:
            return assets[url]
        suffix = Path(urlsplit(url).path).suffix.lower()
        if suffix not in ['.pdf', '.jpg', '.jpeg', '.png', '.webp', '.svg']:
            raise ValueError('Unsupported source file: ' + url)
        name = hashlib.sha256(url.encode()).hexdigest() + suffix
        body = fetch(url, directory / 'assets' / name)
        if suffix == '.pdf' and not body.startswith(b'%PDF-'):
            raise ValueError('Invalid public PDF')
        row = {'source_url': url, 'file': 'assets/' + name, 'path': 'imports/sjc-pages/' + name,
               'sha256': hashlib.sha256(body).hexdigest(), 'bytes': len(body)}
        assets[url] = row
        return row

    def markdown(node):
        def render(n):
            if isinstance(n, str):
                return n
            if n.tag in ['script', 'style', 'form', 'button', 'nav']:
                return ''
            inside = ''.join(render(c) for c in n.children)
            if n.tag in ['h1', 'h2', 'h3', 'h4']:
                return '\n\n' + '#' * min(4, int(n.tag[1])) + ' ' + inside.strip() + '\n\n'
            if n.tag in ['p', 'div', 'section', 'article', 'dl']:
                return '\n\n' + inside.strip() + '\n\n'
            if n.tag == 'li':
                return '\n- ' + inside.strip()
            if n.tag == 'br':
                return '\n'
            if n.tag in ['strong', 'b']:
                return '**' + inside.strip() + '**'
            if n.tag in ['em', 'i']:
                return '*' + inside.strip() + '*'
            if n.tag == 'img':
                src = n.attrs.get('src', '')
                if src:
                    image = asset(src)
                    return '\n\n![' + n.attrs.get('alt', '').replace(']', '') + '](/storage/' + image['path'] + ')\n\n'
                return ''
            if n.tag == 'a':
                href = urljoin(BASE, n.attrs.get('href', ''))
                parts = urlsplit(href)
                if parts.scheme not in ['https', 'mailto', 'tel']:
                    return inside
                if parts.hostname == 'sjcjournal.com':
                    if Path(parts.path).suffix.lower() == '.pdf':
                        href = '/storage/' + asset(href)['path']
                    else:
                        href = parts.path + ('?' + parts.query if parts.query else '')
                return '[' + inside.strip().replace(']', '\\]') + '](' + href.replace(' ', '%20') + ')'
            return inside
        return re.sub(r'\n{3,}', '\n\n', ''.join(render(c) for c in node.children)).strip()

    pages = []
    for slug, definition in definitions.items():
        if definition['route']:
            continue
        tree = document('/policies/' + slug)
        bodies = tree.find(lambda n: n.tag == 'article' and n.has('article-body'))
        if not bodies:
            raise ValueError('Policy content container absent: ' + slug)
        available = 'Published information for this topic is not yet available.' not in bodies[0].text()
        content = markdown(bodies[0]) if available else ''
        facts = tree.find(lambda n: n.has('journal-facts'))
        if facts and slug not in ['issn', 'publication-frequency', 'journal-history', 'publisher']:
            content += '\n\n' + markdown(facts[0])
        pages.append({'slug': slug, 'title': definition['title'], 'source_url': BASE + '/policies/' + slug,
                      'content': content.strip(), 'published': bool(content.strip()), 'source_available': available})

    about = document('/about')
    wrapper = Node()
    for section in about.find(lambda n: n.tag == 'section' and any(n.has(c) for c in ['about-hero', 'about-story', 'principles-section', 'about-cta'])):
        wrapper.children.append(section)
    # Also support a saved public About body instead of the standard template.
    if not wrapper.children:
        wrapper.children += about.find(lambda n: n.tag == 'article' and n.has('article-body'))
    pages.append({'slug': 'about', 'title': 'About the Journal', 'source_url': BASE + '/about',
                  'content': markdown(wrapper), 'published': True, 'source_available': True})

    board = document('/editorial-board')
    editors = []
    for card in board.find(lambda n: n.has('editor-card')):
        heading = card.find(lambda n: n.tag == 'h3')[0]
        roles = card.find(lambda n: n.has('editor-role'))
        details = card.find(lambda n: n.has('editor-details'))[0]
        paragraphs = [n.text() for n in details.children if isinstance(n, Node) and n.tag == 'p']
        role = roles[0].text() if roles else paragraphs[0]
        images = card.find(lambda n: n.tag == 'img')
        photo = asset(images[0].attrs['src']) if images and 'placeholder' not in images[0].attrs.get('src', '') else None
        biographies = card.find(lambda n: n.has('editor-biography'))
        bios = biographies[0].text() if biographies else None
        institution = next((p for p in paragraphs if p != role and p != bios), None)
        editors.append({'name': heading.text(), 'role': role, 'group': 'editorial_board', 'institution': institution,
                        'biography': bios, 'photo': photo, 'source_url': BASE + '/editorial-board'})
    if not editors:
        raise ValueError('No public editorial profiles collected')

    resource_tree = document('/resources')
    resources = []
    for card in resource_tree.find(lambda n: n.has('download-row')):
        title = card.find(lambda n: n.tag == 'h3')[0].text()
        description = next((n.text() for n in card.find(lambda n: n.tag == 'p')), '')
        link = next(n for n in card.find(lambda n: n.tag == 'a') if '.pdf' in n.attrs.get('href', '').lower())
        source = asset(link.attrs['href'])
        resources.append({'title': title, 'description': description, 'filename': link.attrs.get('download') or title + '.pdf', 'asset': source})

    # The existing article snapshot contains every public contributor URL in its HTML.
    author_urls = set()
    article_cache = directory.parent / 'sjc-copy-20261102/html'
    for file in article_cache.glob('*.html'):
        for link in Parser(file.read_text()).root.find(lambda n: n.tag == 'a'):
            url = urljoin(BASE, link.attrs.get('href', ''))
            if urlsplit(url).hostname == 'sjcjournal.com' and re.fullmatch(r'/author/[a-z0-9-]+', urlsplit(url).path) and urlsplit(url).path.rsplit('/', 1)[1] not in ['register', 'login', 'dashboard', 'articles', 'submissions', 'reviews', 'profile', 'settings']:
                author_urls.add(url)

    def author(url):
        tree = document(url)
        names = tree.find(lambda n: n.tag == 'h1')
        bios = tree.find(lambda n: n.has('profile-bio'))
        roles = tree.find(lambda n: n.has('profile-role'))
        portraits = tree.find(lambda n: n.has('profile-portrait'))
        images = portraits[0].find(lambda n: n.tag == 'img') if portraits else []
        role = roles[0].text().split(' · ', 1) if roles else []
        return {'slug': urlsplit(url).path.rsplit('/', 1)[1], 'name': names[0].text(), 'source_url': url,
                'biography': bios[0].text() if bios else None, 'designation': role[0] if role else None,
                'organization': role[1] if len(role) > 1 else None,
                'photo': asset(images[0].attrs['src']) if images else None}
    with ThreadPoolExecutor(max_workers=2) as pool:
        authors = list(pool.map(author, sorted(author_urls)))

    contact = document('/contact')
    contact_fields = {}
    for dl in contact.find(lambda n: n.tag == 'dl'):
        for row in dl.find(lambda n: n.tag == 'div'):
            terms, values = row.find(lambda n: n.tag == 'dt'), row.find(lambda n: n.tag == 'dd')
            if terms and values and terms[0].text() in ['WhatsApp', 'Telephone', 'Correspondence']:
                contact_fields[{'WhatsApp': 'contact.whatsapp', 'Telephone': 'contact.phone', 'Correspondence': 'contact.address'}[terms[0].text()]] = values[0].text()

    home = document('/')
    hero_paths = []
    for card in home.find(lambda n: n.has('lead-story') or n.has('featured-card')):
        for link in card.find(lambda n: n.tag == 'a'):
            path = urlsplit(urljoin(BASE, link.attrs.get('href', ''))).path
            if re.fullmatch(r'/article/[a-z0-9-]+', path) and path not in hero_paths:
                hero_paths.append(path)
    # Featured cards share the article-card component in the current source.
    featured = []
    for section in home.find(lambda n: n.has('home-selections')):
        for link in section.find(lambda n: n.tag == 'a'):
            path = urlsplit(urljoin(BASE, link.attrs.get('href', ''))).path
            if re.fullmatch(r'/article/[a-z0-9-]+', path) and path not in featured:
                featured.append(path)
    categories = []
    for card in document('/categories').find(lambda n: n.has('category-card')):
        headings = card.find(lambda n: n.tag == 'strong')
        paragraphs = card.find(lambda n: n.tag in ['p', 'small'])
        path = urlsplit(urljoin(BASE, card.attrs.get('href', ''))).path
        if headings and path.startswith('/category/'):
            categories.append({'slug': path.rsplit('/', 1)[1], 'name': headings[0].text(),
                               'description': paragraphs[0].text() if paragraphs else None, 'source_url': BASE + path})
    for path in ['/policies', '/journals', '/downloads', '/authors', '/archive', '/articles', '/current-issue', '/corrections-retractions']:
        document(path)
    manifest = {'schema_version': 1, 'source': BASE, 'collected_at': datetime.now(timezone.utc).isoformat(),
                'pages': pages, 'editorial_members': editors, 'authors': authors, 'resources': resources,
                'assets': list(assets.values()), 'public_contact_fields': contact_fields,
                'categories': categories, 'featured_article_paths': featured,
                'homepage_latest_paths': [path for path in hero_paths if path not in featured],
                'source_urls': sorted(set(inventory)), 'warnings': warnings,
                'notes': ['Generic listing/navigation templates already match the shared codebase; article data was copied separately.',
                          'Source unpublished policy pages remain unpublished; no policy text is invented.',
                          'Journal identity, ISSN, mailboxes, SMTP, authentication and analytics configuration are not copied.']}
    (directory / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2))
    print(json.dumps({'pages': len(pages), 'published_pages': sum(p['published'] for p in pages), 'editors': len(editors), 'authors': len(authors), 'resources': len(resources), 'assets': len(assets), 'source_urls': len(manifest['source_urls'])}))


if __name__ == '__main__':
    arguments = argparse.ArgumentParser(description=__doc__)
    arguments.add_argument('--output', required=True, type=Path)
    collect(arguments.parse_args().output.resolve())
