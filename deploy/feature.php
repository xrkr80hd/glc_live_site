<?php
declare(strict_types=1);
// CLI-only website deployment; preserve the host's current site and config.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath($argv[1] ?? '');
if (!$root || !is_file($root.'/index.html') || !is_file($root.'/php/config.php') || !is_file($root.'/php/admin/layout.php')) {
 fwrite(STDERR, "Deployment stopped: live index.html and PHP admin files are required.\n"); exit(1);
}
$repo = dirname(__DIR__);
$backup = dirname($root).'/deployment-backups/website-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
if (!mkdir($backup,0700,true)) { throw new RuntimeException('Could not create backup.'); }
$writes=[];
function add_write(string $path, string $content): void { global $writes; $writes[$path]=$content; }
$index = file_get_contents($root.'/index.html');
if (!str_contains($index,'id="church-feature"')) {
 $heading=strpos($index,'Our Ministries and Service Times');
 $end=$heading===false?false:strpos($index,'</section>',$heading);
 if ($end===false) throw new RuntimeException('Could not locate the Ministries section. No live files changed.');
 $end+=strlen('</section>');
 $index=substr_replace($index,"\n<section id=\"church-feature\" class=\"occ-feature\" aria-label=\"Featured mission\" hidden></section>\n",$end,0);
}
if (!str_contains($index,'/assets/occ.css')) $index=str_replace('</head>',"<link rel=\"stylesheet\" href=\"/assets/occ.css\">\n</head>",$index);
if (!str_contains($index,'/assets/js/feature.js')) $index=str_replace('</body>',"<script src=\"/assets/js/feature.js\"></script>\n</body>",$index);
add_write('index.html',$index);
foreach (glob($root.'/*.html') as $page) {
 if (basename($page)==='index.html') continue;
 $html=file_get_contents($page);
 if (!str_contains($html,'/assets/js/feature.js') && str_contains($html,'</body>')) add_write(basename($page),str_replace('</body>',"<script src=\"/assets/js/feature.js\"></script>\n</body>",$html));
}
$layout=file_get_contents($root.'/php/admin/layout.php');
foreach (['admin_page_start', 'admin_page_end', 'admin_db_or_setup_page'] as $helper) {
 if (!preg_match('/function\s+'.preg_quote($helper, '/').'\s*\(/', $layout)) {
  throw new RuntimeException('Live admin shell is missing '.$helper.'. No live files changed.');
 }
}
$bootstrap = file_get_contents($root.'/php/admin/bootstrap.php');
foreach (['admin_require_login', 'admin_current_user', 'admin_has_role', 'admin_can_manage_users', 'admin_redirect'] as $helper) {
 if (!preg_match('/function\s+'.preg_quote($helper, '/').'\s*\(/', $bootstrap)) {
  throw new RuntimeException('Live authentication helpers differ from the inspected backend. No live files changed.');
 }
}
if (!str_contains($layout,'/php/admin/features/index.php')) {
 $old="'label' => 'Seasonal Features', 'href' => '#admin-coming-soon'";
 if (str_contains($layout,$old)) $layout=str_replace($old,"'label' => 'Feature', 'href' => '/php/admin/features/index.php'",$layout);
 else {
  $layout=preg_replace('~(<aside\\b[^>]*>)~','$1<a class="resource-link" href="/php/admin/features/index.php">Feature</a>',$layout,1,$count);
  if ($count!==1) throw new RuntimeException('Could not locate the admin menu. No live files changed.');
 }
}
// Integrate into the host's current shell, without replacing its menu or auth.
if (!str_contains($layout, 'admin_workspace_groups($resourceGroups)')) {
 if (!str_contains($layout, '$resourceGroups') || !str_contains($layout, 'function admin_page_start')) {
  throw new RuntimeException('Live admin shell is incompatible with the inspected PHP shell. No live files changed.');
 }
 $integration = "\n    require_once __DIR__ . '/workspace-navigation.php';\n    \$resourceGroups = admin_workspace_groups(\$resourceGroups);\n    \$active = admin_workspace_active(\$active);\n";
 $layout = preg_replace('~(\n[ \t]*\?>\s*<!DOCTYPE html>)~', $integration.'$1', $layout, 1, $count);
 if ($count !== 1) throw new RuntimeException('Could not integrate admin navigation. No live files changed.');
}
if (!str_contains($layout, '/assets/admin-workspace.css')) {
 $layout = str_replace('</head>', '<link rel="stylesheet" href="/assets/admin-workspace.css?v=3">'."\n</head>", $layout);
}
if (!str_contains($layout, 'admin_workspace_navigation($active)')) {
 $menuLoop = '<?php foreach ($resourceGroups as $group): ?>';
 if (substr_count($layout, $menuLoop) !== 1) throw new RuntimeException('Could not locate the existing resource menu loop. No live files changed.');
 $layout = str_replace($menuLoop, '<?php admin_workspace_navigation($active); ?>'."\n".$menuLoop, $layout);
}
$layout = str_replace('<a class="resource-link" href="/php/admin/features/index.php">Feature</a>', '', $layout);
// Keep overrides after the host stylesheet, and expire cached planner assets.
$layout=preg_replace('~<link\b[^>]*href="/assets/admin-workspace\.css[^"\s]*"[^>]*>~','',$layout);
$layout=str_replace('</head>','<link rel="stylesheet" href="/assets/admin-workspace.css?v=3">'."\n</head>",$layout);
add_write('php/admin/layout.php',$layout);
$ht=is_file($root.'/.htaccess')?file_get_contents($root.'/.htaccess'):'';
if (!str_contains($ht,'RewriteRule ^operation-christmas-child')) $ht="<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^operation-christmas-child/?$ operation-christmas-child.php [L]\n</IfModule>\n".$ht;
add_write('.htaccess',$ht);
foreach (['api/feature/index.php','assets/js/feature.js','assets/occ.css','assets/ui-preferences.css','assets/operation-christmas-child.jpg','assets/operation-christmas-child-share.png','operation-christmas-child.php','php/features.php','php/api/feature.php','php/admin/features/index.php','php/admin/service-planner/access.php','php/admin/workspace-navigation.php','php/admin/media/index.php','php/admin/service-planner/presentation.php','php/admin/service-planner/checklists.php','php/admin/service-planner/foh.php','php/admin/service-planner/guide.php','php/admin/service-planner/service-sheet.php','php/admin/service-planner/model.php','php/admin/service-planner/index.php','php/admin/service-planner/state.php','php/admin/service-planner/default-tasks.json','database/service_planner.sql','assets/admin-workspace.css','assets/js/service-planner.js','assets/js/service-guide.js','php/admin/announcements/index.php','php/admin/announcements/new.php'] as $path) {
 $source=$repo.'/'.$path;
 if (!is_file($source)) throw new RuntimeException('Missing feature file: '.$path);
 add_write($path,file_get_contents($source));
}
// The existing deployment uses the host's existing DB configuration. Only add
// planner tables; do not touch user/role/media tables or config.php.
require_once $root.'/php/config.php';
require_once $repo.'/php/admin/service-planner/model.php';
// db() in the legacy web bootstrap exits on connection failures with status 0.
// A CLI connection with the same host configuration makes cPanel failures explicit.
$deploymentPort = defined('DB_PORT') && DB_PORT !== '' ? ';port='.DB_PORT : '';
$deploymentCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
try {
 $deploymentPdo = new PDO('mysql:host='.DB_HOST.$deploymentPort.';dbname='.DB_NAME.';charset='.$deploymentCharset, DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
 sp_schema($deploymentPdo);
} catch (Throwable $e) {
 fwrite(STDERR, "Deployment stopped: could not connect to the existing database or install planner tables. No live files changed.\n");
 exit(1);
}
// Back up every existing destination before any writes occur.
foreach ($writes as $path=>$content) {
 if (is_file($root.'/'.$path)) {
  $dir=dirname($backup.'/'.$path);if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Backup directory failed.');
  if(!copy($root.'/'.$path,$backup.'/'.$path))throw new RuntimeException('Backup failed. No deployment started.');
 }
}
$applied=[];
$layoutWrite=$writes['php/admin/layout.php'];unset($writes['php/admin/layout.php']);
$writes['php/admin/layout.php']=$layoutWrite;
try {
 foreach ($writes as $path=>$content) {
  $dest=$root.'/'.$path;$dir=dirname($dest);
  if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Could not create '.$dir);
  $tmp=tempnam($dir,'.occ-');if($tmp===false)throw new RuntimeException('Could not create temporary file.');
  if(file_put_contents($tmp,$content)===false || !chmod($tmp,0644) || !rename($tmp,$dest)) { @unlink($tmp);throw new RuntimeException('Could not deploy '.$path); }
  $applied[]=$path;
 }
} catch(Throwable $e) {
 foreach(array_reverse($applied) as $path) {
  if(is_file($backup.'/'.$path))copy($backup.'/'.$path,$root.'/'.$path);else unlink($root.'/'.$path);
 }
 throw $e;
}
echo "Website feature, Media Management, and Service Planner deployed. Backup: ".$backup."\n";
