from pathlib import Path
import urllib.request,subprocess,json,time
R=Path(__file__).resolve().parent
ASINS=['B01N074ELB','B076ZR13BJ','B07HDGLR6N','B0FP1YYNP9']
result={'generated_at':int(time.time()),'prices':{},'errors':{}}
for asin in ASINS:
 try:
  req=urllib.request.Request('https://www.amazon.fr/dp/'+asin,headers={'User-Agent':'MonLutinFarceurPriceCheck/1.0 (+https://monlutinfarceur.fr)','Accept-Language':'fr-FR,fr;q=0.9'})
  with urllib.request.urlopen(req,timeout=20) as r:
   if r.url!=req.full_url:raise ValueError('Redirection inattendue')
   body=r.read(4000001)
  if len(body)>4000000:raise ValueError('Page trop volumineuse')
  p=subprocess.run(['php',str(R/'parse-public-cli.php'),asin],input=body,capture_output=True,timeout=20)
  if p.returncode:raise ValueError(p.stderr.decode('utf8','replace'))
  result['prices'][asin]=json.loads(p.stdout)
  print(asin,result['prices'][asin]['display'])
 except Exception as e:
  result['errors'][asin]=str(e)[:600];print(asin,'ECHEC',str(e)[:300])
(R/'prices.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf8')
print(str(len(result['prices']))+'/4 offres validées')
raise SystemExit(0 if len(result['prices'])==4 else 1)
