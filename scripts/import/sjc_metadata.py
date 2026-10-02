"""Extract factual public metadata only; no full text, biographies, images or PDFs."""
import json,re,subprocess
from html.parser import HTMLParser
from urllib.parse import urljoin,quote,urlsplit
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
class Node:
 def __init__(self,tag='',attrs=()): self.tag=tag;self.attrs=dict(attrs);self.children=[]
 def text(self): return ' '.join(' '.join(c.text() if isinstance(c,Node) else c for c in self.children).split())
 def find(self,p):
  out=[]
  for c in self.children:
   if isinstance(c,Node):
    if p(c):out.append(c)
    out.extend(c.find(p))
  return out
 def has(self,c):return c in self.attrs.get('class','').split()
class Parser(HTMLParser):
 def __init__(self,s):super().__init__();self.root=Node();self.stack=[self.root];self.feed(s)
 def handle_starttag(self,t,a):
  n=Node(t,a);self.stack[-1].children.append(n)
  if t not in ['img','br','hr','meta','link','input','source','wbr','area','base','embed','param','track']:self.stack.append(n)
 def handle_endtag(self,t):
  for i in range(len(self.stack)-1,0,-1):
   if self.stack[i].tag==t:self.stack=self.stack[:i];break
 def handle_data(self,d):self.stack[-1].children.append(d)
def fetch(url):
 url=quote(url,safe=':/?=&%')
 raw=subprocess.check_output(['curl','-fsSL','--max-time','30',url],text=True)
 return Parser(raw).root
base='https://sjcjournal.com'
home=fetch(base+'/');archive=fetch(base+'/archives');board=fetch(base+'/editorial-board')
urls=sorted({urljoin(base,n.attrs['href']) for n in archive.find(lambda n:n.tag=='a' and '/archives/' in n.attrs.get('href',''))})
articles={};failures=[]
def parse_articles(root,source):
 for n in root.find(lambda n:n.has('journal')):
  titles=n.find(lambda n:n.has('article-title'));authors=n.find(lambda n:n.has('author-name'));pub=n.find(lambda n:n.has('published'));links=n.find(lambda n:n.tag=='a')
  abstract=next((urljoin(base,a.attrs.get('href','')) for a in links if '/abstract' in a.attrs.get('href','')),None)
  if not abstract or not titles:continue
  doi=next((a.attrs['href'].split('doi.org/')[-1] for a in links if 'doi.org/' in a.attrs.get('href','')),None)
  pdf=next((urljoin(base,a.attrs['href']) for a in links if '.pdf' in a.attrs.get('href','').lower()),None)
  names=[re.sub(r'\s+',' ',v).strip(' *;') for v in re.split(r',|\band\b',authors[0].text() if authors else '')]
  date=pub[0].text().split('|')[0].replace('Published on :','').strip() if pub else None
  articles[abstract]={'source_url':abstract,'title':re.sub(r'^\d+\.\s*','',titles[0].text()),'authors':[x for x in names if x],'published_date':date,'doi':doi,'pdf_source_url':pdf,'archive_source':source}
parse_articles(home,base+'/')
def read(url):
 try:return url,fetch(url),None
 except Exception as e:return url,None,str(e)
with ThreadPoolExecutor(max_workers=3) as pool:
 for url,root,error in pool.map(read,urls):
  if error:failures.append({'url':url,'error':error})
  else:parse_articles(root,url)
members=[]
for node in board.find(lambda n:n.has('editor-content')):
 names=node.find(lambda n:n.tag=='h6' and n.has('bold'));details=node.find(lambda n:n.tag=='h6' and n.has('regular'))
 if not names:continue
 chief=bool(node.find(lambda n:n.tag=='a' and '/chief-editors/' in n.attrs.get('href','')))
 members.append({'name':names[0].text(),'role':'Editor-in-Chief' if chief else 'Editor','institution':details[0].text() if details else '', 'source_url':base+'/editorial-board'})
members=list({m['name']: next(x for x in members if x['name']==m['name']) for m in members}.values())
out={'source':base,'articles':list(articles.values()),'editorial_members':members,'archive_pages_checked':urls,'failures':failures,'reviewers':[],'reviewer_note':'No named reviewer directory was present on the public editorial board; reviewer links lead to guidelines or authentication.'}
Path('storage/app/imports/sjc/metadata.json').write_text(json.dumps(out,indent=2,ensure_ascii=False))
print(json.dumps({'articles':len(articles),'editorial_members':len(members),'archive_pages':len(urls),'failures':len(failures)}))
