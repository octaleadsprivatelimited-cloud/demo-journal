"""Collect the owner's public SJNR archive for a reviewed, separate-site import.

Only public HTTPS resources on sjnrjournal.com are fetched. No forms are submitted.
The output directory must be outside the repository and is not a database backup.
"""

import argparse
import hashlib
import json
import re
import subprocess
import tempfile
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import quote, unquote, urljoin, urlsplit


BASE = "https://sjnrjournal.com"
VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"}
PAGE_MAP = {
    "/aboutus": "about",
    "/author-guidelines": "author-guidelines",
    "/editor-guidelines": "editorial-policies",
    "/reviewer-guidelines": "reviewer-guidelines",
    "/manuscript-guidelines": "manuscript-preparation",
    "/publisher-guidelines": "publisher",
    "/processing-charges": "fees",
    "/advertise": "advertising",
    "/benefits-of-publishing": "open-access",
    "/peer-review": "peer-review",
    "/ethics": "publication-ethics",
    "/animal&humanrights": "human-animal-research",
    "/plagiarismpolicy": "plagiarism",
    "/privacy-policy": "privacy",
    "/copyright": "copyright",
    "/retraction-policy": "corrections-retractions",
    "/appeal": "complaints-appeals",
    "/indexing-listing": "indexing",
    "/offline-submission": "manuscript-submission",
}
TEMPLATES = {
    "/img/templates/Research.PDF": "research-template.pdf",
    "/img/templates/Review.PDF": "review-template.pdf",
    "/img/templates/Editorial.PDF": "editorial-template.pdf",
    "/img/templates/Case-Report.PDF": "case-report-template.pdf",
    "/img/templates/Others.PDF": "other-article-template.pdf",
}


class Node:
    def __init__(self, tag="", attrs=(), parent=None):
        self.tag, self.attrs, self.parent, self.children = tag, dict(attrs), parent, []

    def text(self):
        return " ".join(" ".join(child.text() if isinstance(child, Node) else child for child in self.children).split())

    def has(self, name):
        return name in self.attrs.get("class", "").split()

    def find(self, predicate):
        matches = []
        for child in self.children:
            if isinstance(child, Node):
                if predicate(child):
                    matches.append(child)
                matches.extend(child.find(predicate))
        return matches


class Parser(HTMLParser):
    def __init__(self, html):
        super().__init__(convert_charrefs=True)
        self.root, self.stack = Node(), []
        self.stack.append(self.root)
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        node = Node(tag, attrs, self.stack[-1])
        self.stack[-1].children.append(node)
        if tag not in VOID:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID:
            self.handle_endtag(tag)

    def handle_endtag(self, tag):
        for index in range(len(self.stack) - 1, 0, -1):
            if self.stack[index].tag == tag:
                self.stack = self.stack[:index]
                break

    def handle_data(self, data):
        self.stack[-1].children.append(data)


class Collector:
    def __init__(self, directory):
        self.directory = directory
        self.errors, self.warnings, self.sources = [], [], {}
        self.media = {}
        for folder in ["html", "pdfs", "images", "resources"]:
            (directory / folder).mkdir(parents=True, exist_ok=True)

    @staticmethod
    def normalized(url):
        absolute = urljoin(BASE + "/", url)
        parts = urlsplit(absolute)
        if parts.scheme != "https" or parts.hostname != "sjnrjournal.com" or parts.username or parts.password or parts.port:
            raise ValueError("Only public HTTPS resources on sjnrjournal.com are permitted")
        return quote(absolute, safe=":/?=&%()'!+,;@")

    def fetch(self, url, target, limit=30_000_000):
        url = self.normalized(url)
        if target.exists() and target.stat().st_size:
            return target.read_bytes()
        current = url
        for _ in range(5):
            with tempfile.TemporaryDirectory(prefix="sjnr-http-") as temporary:
                temp = Path(temporary)
                result = subprocess.run([
                    "curl", "-fsS", "--proto", "=https", "--max-time", "45",
                    "--retry", "1", "--max-filesize", str(limit),
                    "--dump-header", str(temp / "headers"), "--output", str(temp / "body"),
                    "--write-out", "%{http_code}", current,
                ], capture_output=True, text=True)
                if result.returncode:
                    raise RuntimeError("HTTP fetch failed: " + result.stderr.strip())
                code = int(result.stdout)
                if 300 <= code < 400:
                    headers = (temp / "headers").read_text(errors="replace")
                    locations = re.findall(r"^location:\s*(.+)$", headers, re.I | re.M)
                    if not locations:
                        raise RuntimeError("Redirect without a Location")
                    current = self.normalized(urljoin(current, locations[-1].strip()))
                    continue
                if code != 200:
                    raise RuntimeError("Unexpected HTTP status " + str(code))
                body = (temp / "body").read_bytes()
                if not body or len(body) > limit:
                    raise RuntimeError("Empty or oversized response")
                target.write_bytes(body)
                return body
        raise RuntimeError("Too many redirects")

    def html(self, url):
        url = self.normalized(url)
        target = self.directory / "html" / (hashlib.sha256(url.encode()).hexdigest() + ".html")
        raw = self.fetch(url, target, 3_000_000)
        self.sources[url] = {"file": str(target.relative_to(self.directory)), "sha256": hashlib.sha256(raw).hexdigest()}
        return Parser(raw.decode("utf-8", "replace")).root

    def image(self, url, category="editorial"):
        url = self.normalized(url)
        key = hashlib.sha256(url.encode()).hexdigest()
        cached = self.directory / "images" / (key + ".source")
        raw = self.fetch(url, cached, 10_000_000)
        if raw.startswith(b"\xff\xd8\xff"):
            extension = "jpg"
        elif raw.startswith(b"\x89PNG\r\n\x1a\n"):
            extension = "png"
        elif raw[:6] in [b"GIF87a", b"GIF89a"]:
            extension = "gif"
        elif raw.startswith(b"RIFF") and raw[8:12] == b"WEBP":
            extension = "webp"
        else:
            raise RuntimeError("Source is not an accepted raster image")
        sha = hashlib.sha256(raw).hexdigest()
        local = "images/" + sha + "." + extension
        path = "imports/sjnr/" + category + "/" + sha + "." + extension
        (self.directory / local).write_bytes(raw)
        cached.unlink(missing_ok=True)
        record = {"source_url": url, "file": local, "sha256": sha, "bytes": len(raw), "path": path}
        self.media[path] = record
        return record

    def pdf(self, url, file):
        raw = self.fetch(url, self.directory / file)
        if not raw.startswith(b"%PDF-"):
            raise RuntimeError("Source response is not a PDF")
        from pypdf import PdfReader
        pages = len(PdfReader(self.directory / file).pages)
        if not pages:
            raise RuntimeError("Source PDF has no readable pages")
        return {"source_url": self.normalized(url), "file": file, "sha256": hashlib.sha256(raw).hexdigest(), "bytes": len(raw), "pages": pages}

    def markdown(self, node):
        def render(child):
            if isinstance(child, str):
                return re.sub(r"\s+", " ", child)
            if child.tag in {"script", "style", "form", "button", "input", "iframe", "object", "embed"}:
                return ""
            inside = "".join(render(value) for value in child.children)
            if child.tag in {"h1", "h2", "h3", "h4"}:
                return "\n\n## " + inside.strip() + "\n\n"
            if child.tag in {"p", "div", "section", "article", "h5", "h6", "blockquote"}:
                return "\n\n" + inside.strip() + "\n\n"
            if child.tag == "li":
                return "\n- " + inside.strip() + "\n"
            if child.tag == "br":
                return "\n"
            if child.tag in {"strong", "b"}:
                return "**" + inside.strip() + "**"
            if child.tag in {"em", "i"}:
                return "*" + inside.strip() + "*"
            if child.tag == "a":
                if not inside.strip():
                    return ""
                href = child.attrs.get("href", "").strip()
                if href.startswith(("javascript:", "data:", "#")) or not href:
                    return inside
                absolute = urljoin(BASE + "/", href)
                if urlsplit(absolute).hostname == "sjnrjournal.com":
                    path = unquote(urlsplit(absolute).path)
                    if path in PAGE_MAP:
                        href = "/about" if PAGE_MAP[path] == "about" else "/policies/" + PAGE_MAP[path]
                    elif path in TEMPLATES:
                        href = "/storage/imports/sjnr/resources/" + TEMPLATES[path]
                    else:
                        href = {"/archives": "/archive", "/contact-us": "/contact", "/register": "/author/register", "/reviewer-registration": "/reviewer/register", "/editor-registration": "/editor/register"}.get(path, absolute)
                elif absolute.startswith(("https://", "http://", "mailto:", "tel:")):
                    href = absolute
                else:
                    return inside
                label = inside.strip()
                if not label.startswith("!["):
                    label = label.replace("]", "\\]")
                return "[" + label + "](" + href.replace(" ", "%20") + ")"
            if child.tag == "img":
                src = child.attrs.get("src", "")
                if not src or urlsplit(src).path.endswith("/img/pdf.svg"):
                    return ""
                try:
                    image = self.image(src, "pages")
                    return "\n\n![" + child.attrs.get("alt", "").replace("]", "") + "](/storage/" + image["path"] + ")\n\n"
                except Exception as error:
                    self.warnings.append({"image": urljoin(BASE, src), "error": str(error)})
                    return ""
            if child.tag == "table":
                rows = []
                for row in child.find(lambda n: n.tag == "tr"):
                    cells = row.find(lambda n: n.tag in {"td", "th"})
                    if cells:
                        rows.append([cell.text().replace("|", "\\|") for cell in cells])
                if not rows:
                    return inside
                width = max(map(len, rows))
                rows = [row + [""] * (width - len(row)) for row in rows]
                return "\n\n" + "\n".join(["| " + " | ".join(rows[0]) + " |", "| " + " | ".join(["---"] * width) + " |"] + ["| " + " | ".join(row) + " |" for row in rows[1:]]) + "\n\n"
            return inside

        result = "".join(render(child) for child in node.children)
        result = re.sub(r"\n[ \t]+", "\n", result)
        return re.sub(r"\n{3,}", "\n\n", result).strip()

    @staticmethod
    def main(root):
        about = root.find(lambda node: node.has("about-page"))
        scope = about[0] if about else root
        columns = scope.find(lambda node: node.has("col-lg-7") or node.has("col-lg-8"))
        return columns[0] if columns else None

    def articles(self, root, source, archive=None):
        rows = []
        for node in root.find(lambda value: value.has("journal")):
            titles, authors = node.find(lambda value: value.has("article-title")), node.find(lambda value: value.has("author-name"))
            links = node.find(lambda value: value.tag == "a")
            abstract = next((urljoin(BASE, a.attrs.get("href", "")) for a in links if "/abstract" in a.attrs.get("href", "")), None)
            if not titles or not abstract:
                continue
            match = re.search(r"/journals/(\d+)/", abstract)
            if not match:
                continue
            published = [value.text() for value in node.find(lambda value: value.has("published"))]
            date = re.search(r"Published on\s*:\s*([A-Za-z]+\s+\d+,\s*\d{4})", " ".join(published))
            volume = re.search(r"Volume:\s*(?:Volume\s*)?(\d+)", " ".join(published))
            issue = re.search(r"Issue:\s*(?:Issue\s*)?(\d+)", " ".join(published))
            pages = re.search(r"Pages\s*:\s*([\d -]+)", " ".join(published))
            doi_text = next((a.text() for a in links if re.search(r"10\.\d{4,9}/", a.text())), None)
            doi = re.search(r"10\.\d{4,9}/\S+", doi_text or "")
            pdf = next((urljoin(BASE, a.attrs["href"]) for a in links if ".pdf" in a.attrs.get("href", "").lower()), None)
            names = [name.strip(" *;") for name in re.split(r",|\band\b", authors[0].text() if authors else "")]
            rows.append({
                "id": match[1], "source_url": self.normalized(abstract), "slug": unquote(urlsplit(abstract).path).split("/")[-2], "title": re.sub(r"^\d+\.\s*", "", titles[0].text()),
                "authors": [name for name in names if name], "published_date": datetime.strptime(date[1], "%b %d, %Y").date().isoformat() if date else None,
                "doi": doi[0].rstrip(".,") if doi else None, "volume": volume[1] if volume else None,
                "issue": issue[1] if issue else None, "page_range": pages[1].strip() if pages else None,
                "pdf_source_url": self.normalized(pdf) if pdf else None, "archive_source": archive, "listed_at": [source],
            })
        return rows

    def collect(self):
        home, archive, board = self.html(BASE + "/"), self.html(BASE + "/archives"), self.html(BASE + "/editorial-board")
        issue_urls = sorted({self.normalized(n.attrs["href"]) for n in archive.find(lambda n: n.tag == "a" and "/archives/" in n.attrs.get("href", ""))})
        article_rows = {row["id"]: row for row in self.articles(home, BASE + "/")}
        issues = []

        def fetch_issue(url):
            return url, self.html(url)

        with ThreadPoolExecutor(max_workers=3) as pool:
            for url, root in pool.map(fetch_issue, issue_urls):
                rows = self.articles(root, url, url)
                parts = re.search(r"/archives/(\d{4})/(\d+)/(.+)$", unquote(url))
                issues.append({"source_url": url, "year": int(parts[1]), "source_id": parts[2], "label": parts[3], "article_ids": [row["id"] for row in rows]})
                for row in rows:
                    old = article_rows.get(row["id"])
                    if old:
                        row["listed_at"] = sorted(set(old["listed_at"] + row["listed_at"]))
                    article_rows[row["id"]] = row

        articles = sorted(article_rows.values(), key=lambda row: (row["published_date"] or "", int(row["id"])), reverse=True)
        for row in articles:
            root = self.html(row["source_url"])
            abstract = root.find(lambda n: n.has("single-abstract"))
            sections = {}
            if abstract:
                heading = None
                for child in abstract[0].children:
                    if not isinstance(child, Node):
                        continue
                    if child.tag == "h4":
                        heading = child.text().lower()
                    elif child.tag == "p" and heading:
                        sections[heading] = sections.get(heading, "") + " " + child.text()
            row["abstract"] = sections.get("abstract") or None
            row["keywords"] = [word.strip() for word in re.split(r"[,;]", sections.get("keywords", "")) if word.strip()]
            if row["abstract"]:
                row["abstract"] = re.sub(r"^Abstract\s*", "", row["abstract"], flags=re.I).strip()
            if not row["abstract"]:
                self.warnings.append({"article_id": row["id"], "note": "No source HTML abstract was found; preserve complete source PDF instead."})
            if not row["published_date"] or not row["pdf_source_url"]:
                self.errors.append({"article_id": row["id"], "error": "Missing publication date or PDF URL"})
                continue
            row["pdf"] = self.pdf(row["pdf_source_url"], "pdfs/" + row["id"] + ".pdf")

        members = []
        main = self.main(board)
        for index, node in enumerate((main or board).find(lambda n: n.has("editor-content"))):
            names = node.find(lambda n: n.tag == "h6" and n.has("bold"))
            details = node.find(lambda n: n.tag == "h6" and n.has("regular"))
            if not names:
                continue
            links = node.find(lambda n: n.tag == "a" and any(part in n.attrs.get("href", "") for part in ["/editors/", "/chief-editors/"]))
            url = self.normalized(links[0].attrs["href"]) if links else BASE + "/editorial-board"
            role = "Editor-in-Chief" if "/chief-editors/" in url else "Editor"
            member = {"name": names[0].text(), "role": role, "institution": details[0].text() if details else "", "source_url": url, "photo": None}
            biographies = node.find(lambda n: n.tag == "p" and n.has("regular"))
            member["biography"] = biographies[0].text() if biographies else None
            row = node.parent
            for _ in range(3):
                if row is None or row.has("row"):
                    break
                row = row.parent
            images = row.find(lambda n: n.tag == "img" and n.attrs.get("src")) if row else []
            if images:
                try:
                    member["photo"] = self.image(images[0].attrs["src"])
                except Exception as error:
                    self.warnings.append({"member": member["name"], "image": images[0].attrs["src"], "error": str(error)})
            if not any(existing["name"] == member["name"] and existing["role"] == member["role"] for existing in members):
                members.append(member)

        pages = []
        for path, slug in PAGE_MAP.items():
            url = BASE + path
            try:
                root = self.html(url)
            except Exception as error:
                self.warnings.append({"page": url, "note": "Public source page was unavailable and was not imported.", "error": str(error)})
                continue
            main = self.main(root)
            if main is None:
                self.warnings.append({"page": url, "error": "Main content column not found; page was not imported."})
                continue
            headings = main.find(lambda n: n.tag in {"h1", "h2", "h3", "h4"})
            title = headings[0].text() if headings else slug.replace("-", " ").title()
            markdown = self.markdown(main)
            if markdown.startswith("## " + title):
                markdown = markdown[len("## " + title):].strip()
            if len(markdown) < 30:
                self.warnings.append({"page": url, "note": "Source page has no substantive text; retained in manifest only."})
            pages.append({"slug": slug, "title": title, "source_url": url, "content": markdown, "published": len(markdown) >= 30})

        home_main = self.main(home)
        if home_main:
            # The journal's scope is the public introductory copy before Articles.
            content = []
            for child in home_main.children:
                if isinstance(child, Node) and (child.has("journals") or child.has("journal") or child.tag == "table"):
                    break
                content.append(child)
            intro = Node()
            intro.children = content
            markdown = self.markdown(intro)
            if "AIMS AND SCOPE" in markdown:
                scope = markdown.split("AIMS AND SCOPE", 1)[1]
                scope = scope.split("ARTICLES", 1)[0].strip("# \n")
                if len(scope) > 50:
                    pages.append({"slug": "aims-scope", "title": "Aims and Scope", "source_url": BASE + "/", "content": scope, "published": True})

        resources = []
        for name, url, file in [
            ("Guidelines", BASE + "/img/Larix Guidelines.pdf", "guidelines.pdf"),
            ("Copyright form", BASE + "/img/SJNR-Copyright-Transfer-and-Authorization-Form.pdf", "copyright-form.pdf"),
            ("Cover letter", BASE + "/img/SJNR-Cover-letter (1).pdf", "cover-letter.pdf"),
            *[(name.replace("-", " ").replace(".pdf", "").title(), BASE + url, name) for url, name in TEMPLATES.items()],
        ]:
            try:
                resource = self.pdf(url, "resources/" + file)
            except Exception as error:
                self.warnings.append({"resource": url, "error": str(error), "note": "Unavailable source form was not imported."})
                continue
            resource.update({"name": name, "path": "imports/sjnr/resources/" + file})
            resources.append(resource)

        legacy_routes = []
        for row in articles:
            legacy_routes.append({"source_path": unquote(urlsplit(row["source_url"]).path), "route": "articles.show", "parameters": {"slug": row["slug"]}})
            if row.get("pdf_source_url"):
                legacy_routes.append({"source_path": unquote(urlsplit(row["pdf_source_url"]).path), "route": "articles.pdf", "parameters": {"slug": row["slug"]}})
        for row in pages:
            source_path = urlsplit(row["source_url"]).path or "/"
            if source_path != "/":
                legacy_routes.append({"source_path": source_path, "route": "about" if row["slug"] == "about" else "policies.show", "parameters": {} if row["slug"] == "about" else {"page": row["slug"]}})
        legacy_routes.append({"source_path": "/archives", "route": "archive.index", "parameters": {}})
        legacy_routes.append({"source_path": "/current-issues", "route": "current-issue", "parameters": {}})
        legacy_routes.append({"source_path": "/editorial-board", "route": "editorial-board", "parameters": {}})
        for row in issues:
            legacy_routes.append({"source_path": unquote(urlsplit(row["source_url"]).path), "route": "archive.issue" if row["article_ids"] else "archive.index", "source_issue_id": row["source_id"], "article_ids": row["article_ids"]})
        for row in members:
            if urlsplit(row["source_url"]).path != "/editorial-board":
                legacy_routes.append({"source_path": urlsplit(row["source_url"]).path, "route": "editorial-board", "parameters": {}})
        for row in resources:
            legacy_routes.append({"source_path": unquote(urlsplit(row["source_url"]).path), "public_path": "/storage/" + row["path"]})
        for row in self.media.values():
            legacy_routes.append({"source_path": unquote(urlsplit(row["source_url"]).path), "public_path": "/storage/" + row["path"]})

        manifest = {
            "schema_version": 1, "source": BASE, "collected_at": datetime.now(timezone.utc).isoformat(),
            "articles": articles, "issues": issues, "editorial_members": members, "pages": pages,
            "media": list(self.media.values()), "resources": resources,
            "legacy_routes": legacy_routes,
            "sources": self.sources, "errors": self.errors, "warnings": self.warnings,
            "notes": ["Names, dates and DOI values retain the public source's spelling.", "Exact repeated author names remain in provenance but a single author relation is created per article.", "No accounts, private manuscripts, subscribers, review assignments, passwords or queues were collected.", "Legacy indexing text is source content, not an independently verified database membership."],
        }
        (self.directory / "manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False))
        print(json.dumps({"articles": len(articles), "issues_checked": len(issues), "populated_issues": sum(bool(i["article_ids"]) for i in issues), "editorial_members": len(members), "pages": len(pages), "pdfs": len([a for a in articles if a.get("pdf")]), "resources": len(resources), "images": len(self.media), "errors": len(self.errors), "warnings": len(self.warnings)}))


if __name__ == "__main__":
    arguments = argparse.ArgumentParser(description=__doc__)
    arguments.add_argument("--output", required=True, type=Path)
    directory = arguments.parse_args().output.resolve()
    repository = Path(__file__).resolve().parents[2]
    if directory == repository or repository in directory.parents:
        raise SystemExit("Keep collected public data outside the source repository")
    Collector(directory).collect()
