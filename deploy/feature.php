<?php
declare(strict_types=1);
// CLI-only deployment of the feature; preserve the host's current site and config.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath($argv[1] ?? '');
if (!$root || !is_file($root.'/index.html') || !is_file($root.'/php/config.php') || !is_file($root.'/php/admin/layout.php')) {
 fwrite(STDERR, "Deployment stopped: live index.html and PHP admin files are required.\n"); exit(1);
}
$repo = dirname(__DIR__);
$backup = dirname($root).'/deployment-backups/occ-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
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
if (!str_contains($layout,'/php/admin/features/index.php')) {
 $old="'label' => 'Seasonal Features', 'href' => '#admin-coming-soon'";
 if (str_contains($layout,$old)) $layout=str_replace($old,"'label' => 'Feature', 'href' => '/php/admin/features/index.php'",$layout);
 else {
  $layout=preg_replace('~(<aside\\b[^>]*>)~','$1<a class="resource-link" href="/php/admin/features/index.php">Feature</a>',$layout,1,$count);
  if ($count!==1) throw new RuntimeException('Could not locate the admin menu. No live files changed.');
 }
}
add_write('php/admin/layout.php',$layout);
$config=file_get_contents($root.'/php/config.php');
$config=str_replace("config_bool('ADMIN_LOGIN_DISABLED', true)","config_bool('ADMIN_LOGIN_DISABLED', false)",$config);
add_write('php/config.php',$config);
$ht=is_file($root.'/.htaccess')?file_get_contents($root.'/.htaccess'):'';
if (!str_contains($ht,'RewriteRule ^operation-christmas-child')) $ht="<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^operation-christmas-child/?$ operation-christmas-child.php [L]\n</IfModule>\n".$ht;
add_write('.htaccess',$ht);
foreach (['api/feature/index.php','assets/js/feature.js','assets/occ.css','assets/operation-christmas-child.jpg','assets/operation-christmas-child-share.png','operation-christmas-child.php','php/features.php','php/api/feature.php','php/admin/features/index.php'] as $path) {
 $source=$repo.'/'.$path;
 if (!is_file($source)) throw new RuntimeException('Missing feature file: '.$path);
 add_write($path,file_get_contents($source));
}
// Back up every existing destination before any writes occur.
foreach ($writes as $path=>$content) {
 if (is_file($root.'/'.$path)) {
  $dir=dirname($backup.'/'.$path);if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Backup directory failed.');
  if(!copy($root.'/'.$path,$backup.'/'.$path))throw new RuntimeException('Backup failed. No deployment started.');
 }
}
$applied=[];
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
echo "Operation Christmas Child feature deployed. Backup: ".$backup."\n";
