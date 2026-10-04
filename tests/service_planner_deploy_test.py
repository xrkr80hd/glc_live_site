from pathlib import Path
import tempfile,shutil,subprocess,os,json
if os.environ.get('DB_NAME')!='church_test':raise RuntimeError('Use the isolated church_test database only.')
r=Path(__file__).resolve().parent.parent;tmp=Path(tempfile.mkdtemp(prefix='church-deploy-check-'));target=tmp/'public_html'
shutil.copytree(r,target,ignore=shutil.ignore_patterns('.git','node_modules','uploads'))
marker='<!-- QA custom host content retained -->';p=target/'index.html';p.write_text(p.read_text().replace('</body>',marker+'\n</body>'))
host_layout=target/'php/admin/layout.php'
host_layout.write_text(host_layout.read_text().replace('<header class="admin-content-head">','<div class="admin-mobile-row"><button type="button" class="btn btn-secondary resource-menu-inline" aria-controls="adminResourceMenu">Content Menu</button></div><header class="admin-content-head">'))
config=(target/'php/config.php').read_bytes();auth=(target/'php/admin/bootstrap.php').read_bytes();toggle=(target/'php/admin/users/toggle.php').read_bytes()
php=[os.environ.get('PLANNER_TEST_PHP','php')]+json.loads(os.environ.get('PLANNER_TEST_PHP_ARGUMENTS','[]'))
for i in range(2):
 result=subprocess.run(php+[str(r/'deploy/feature.php'),str(target)],capture_output=True,text=True);assert result.returncode==0,result.stderr
 assert marker in (target/'index.html').read_text();assert config==(target/'php/config.php').read_bytes();assert auth==(target/'php/admin/bootstrap.php').read_bytes()
 layout=(target/'php/admin/layout.php').read_text();assert layout.count('admin_workspace_navigation($active)')==1;assert 'admin-workspace.css?v=4' in layout
 assert toggle==(target/'php/admin/users/toggle.php').read_bytes();assert 'Content Menu</button>' not in layout
 for file in ['index.php','edit.php','new.php']:assert (target/'php/admin/users'/file).read_bytes()==(r/'php/admin/users'/file).read_bytes()
 for file in ['access.php','guide.php','checklists.php','foh.php','presentation.php','service-sheet.php']:assert (target/'php/admin/service-planner'/file).exists()
 assert subprocess.run(php+['-l',str(target/'php/admin/layout.php')],capture_output=True).returncode==0
before=(target/'index.html').read_bytes();p=target/'php/admin/layout.php';p.write_text('<?php function admin_page_start() {} ?>');bad=subprocess.run(php+[str(r/'deploy/feature.php'),str(target)],capture_output=True,text=True);assert bad.returncode!=0;assert before==(target/'index.html').read_bytes()
shutil.rmtree(tmp)
print('Repeat deployment, all new dependencies, unchanged config/auth/custom host content, shell syntax, and incompatible-shell refusal checked.')
