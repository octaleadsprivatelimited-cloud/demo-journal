"""Run on the journal VM; credentials never leave its own sign-in endpoint."""
import http.cookiejar, urllib.request, urllib.parse, re, json, getpass
from pathlib import Path
base='https://journal-sjc-bb469c5c.centralindia.cloudapp.azure.com'
results={}
for path in ['/','/articles','/archive','/categories','/authors','/up','/admin','/author/dashboard','/reviewer/dashboard']:
    r=urllib.request.urlopen(base+path,timeout=60)
    results[path]={'status':r.status,'path':urllib.parse.urlparse(r.url).path}
jar=http.cookiejar.CookieJar()
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
r=client.open(base+'/admin/login',timeout=60)
html=r.read().decode()
token=re.search(r'name="_token"\s+value="([^"]+)"',html).group(1)
data=urllib.parse.urlencode({'_token':token,'email':'info@octaleads.com','password':getpass.getpass('Production administrator password: ')}).encode()
r=client.open(urllib.request.Request(base+'/admin/login',data=data),timeout=60)
assert urllib.parse.urlparse(r.url).path=='/admin', 'Administrator sign-in failed'
for path in ['/admin','/admin/articles','/admin/submissions','/admin/reviews','/admin/uploads','/admin/users','/admin/settings']:
    r=client.open(base+path,timeout=60)
    assert urllib.parse.urlparse(r.url).path==path, 'Unexpected redirect'
    results['authenticated '+path]={'status':r.status}
print(json.dumps(results,indent=2))
