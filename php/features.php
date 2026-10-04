<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
function feature_defaults(): array {
 return ['enabled'=>true,'title'=>'Operation Christmas Child','description'=>'Pack a shoebox. Share the love of Christ. Join Liberty Church in bringing joy and the Good News of Jesus to children through Samaritan’s Purse.','deadline'=>'Sunday, November 15, 2026','video'=>'https://www.youtube.com/watch?v=KOKtonyYIFs','share_image'=>'/assets/operation-christmas-child-share.png','organizers'=>"Ellen Winegaert | 318-613-6318\nBridget Simmons | 318-792-9597"];
}
function feature_schema(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS site_features (slug VARCHAR(100) PRIMARY KEY, settings TEXT NOT NULL)");
 $pdo->exec("CREATE TABLE IF NOT EXISTS feature_contacts (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL, phone VARCHAR(40), message TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
}
function feature_get(): array {
 $pdo=db(); feature_schema($pdo);
 $q=$pdo->prepare('SELECT settings FROM site_features WHERE slug=?'); $q->execute(['operation-christmas-child']);
 $data=json_decode((string)($q->fetchColumn() ?: '{}'),true);
 return array_replace(feature_defaults(),is_array($data)?$data:[]);
}
function feature_video(string $url): string {
 $p=parse_url($url); if (!$p || ($p['scheme']??'')!=='https') return '';
 $host=strtolower($p['host']??''); $path=$p['path']??''; $id='';
 if ($host==='youtu.be') $id=trim($path,'/');
 if (in_array($host,['youtube.com','www.youtube.com','www.youtube-nocookie.com'],true)) {
  parse_str($p['query']??'', $query); $id=$query['v']??'';
  if (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})~',$path,$m)) $id=$m[1];
 }
 if (preg_match('/^[A-Za-z0-9_-]{11}$/',$id)) return 'https://www.youtube-nocookie.com/embed/'.$id;
 if (in_array($host,['vimeo.com','www.vimeo.com','player.vimeo.com'],true) && preg_match('~^/(?:video/)?([0-9]+)$~',$path,$m)) return 'https://player.vimeo.com/video/'.$m[1];
 return '';
}
