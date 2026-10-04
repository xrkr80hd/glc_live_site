<?php
require_once __DIR__.'/../layout.php';
require_once __DIR__.'/../../features.php';
admin_require_login();admin_require_role('pastor','admin','media');
$pdo=admin_db_or_setup_page('Feature','seasonal-features');feature_schema($pdo);
$f=feature_get();
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf($_POST['csrf_token']??null);
 $f=['enabled'=>isset($_POST['enabled'])];
 foreach(['title','description','deadline','video','organizers','share_image'] as $key) {$f[$key]=trim((string)($_POST[$key]??''));}
 if (!preg_match('~^/(?:assets|uploads)/[A-Za-z0-9_./-]+\.(?:jpg|jpeg|png|webp)$~i',$f['share_image']) || str_contains($f['share_image'],'..')) {$f['share_image']='/assets/operation-christmas-child.jpg';}
 if ($f['title']==='' || $f['deadline']==='' || strlen($f['description'])>10000 || strlen($f['organizers'])>5000 || ($f['video']!=='' && feature_video($f['video'])==='')) {admin_flash('error','Add a title and deadline. Video must be a valid YouTube or Vimeo HTTPS link.');}
 else {try{
 if (isset($_FILES['meta_image']) && $_FILES['meta_image']['error']!==UPLOAD_ERR_NO_FILE) {
  $upload=$_FILES['meta_image'];
  if ($upload['error']!==UPLOAD_ERR_OK || $upload['size']>8*1024*1024) throw new RuntimeException('Image upload failed or exceeds 8 MB.');
  $info=getimagesize($upload['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if (!$info || !isset($extensions[$info['mime']])) throw new RuntimeException('Use a JPG, PNG, or WebP image.');
  $dir=dirname(__DIR__,3).'/uploads/features';
  if (!is_dir($dir) && !mkdir($dir,0755,true)) throw new RuntimeException('Upload directory unavailable.');
  $file=bin2hex(random_bytes(16)).'.'.$extensions[$info['mime']];
  if (!move_uploaded_file($upload['tmp_name'],$dir.'/'.$file)) throw new RuntimeException('Could not store image.');
  $f['share_image']='/uploads/features/'.$file;
 }
 $q=$pdo->prepare('INSERT INTO site_features (slug,settings) VALUES (?,?) ON DUPLICATE KEY UPDATE settings=VALUES(settings)');$q->execute(['operation-christmas-child',json_encode($f,JSON_THROW_ON_ERROR)]);admin_flash('success','Feature saved. Homepage, navigation, and page visibility are updated.');admin_redirect('/php/admin/features/index.php');}catch(Throwable $e){admin_flash('error','Could not save the feature. Please try again.');}}
}
$messages=$pdo->query('SELECT * FROM feature_contacts ORDER BY created_at DESC LIMIT 100')->fetchAll();
function fe($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
admin_page_start('Feature · Operation Christmas Child','seasonal-features');
?><section class="card"><h3><?= $f['enabled']?'Enabled on the live website':'Feature is off' ?></h3><p>Displays below Our Ministries and Service Times. Turning it off hides the homepage feature, navigation link, and public page. Content and contact messages remain saved.</p><a href="/operation-christmas-child" target="_blank" rel="noopener">View feature page ↗</a><img src="/assets/operation-christmas-child.jpg" alt="Feature graphic" style="width:100%;margin-top:20px;border-radius:12px"></section><section class="card"><h3>Edit Feature</h3><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=fe(csrf_token())?>"><label><input type="checkbox" name="enabled" <?= $f['enabled']?'checked':'' ?>> Enable feature on website</label>
<?php foreach(['title'=>'Page title','deadline'=>'Shoebox deadline','video'=>'YouTube or Vimeo video link'] as $key=>$label):?><div class="form-group"><label for="<?=fe($key)?>"><?=fe($label)?></label><input id="<?=fe($key)?>" name="<?=fe($key)?>" value="<?=fe($f[$key])?>" maxlength="500" <?= $key==='video'?'type="url"':'required' ?>></div><?php endforeach;?>
<div class="form-group"><label for="meta_image">Social sharing graphic</label><p>Upload the image people see when this page is shared. JPG, PNG, or WebP, up to 8 MB. Recommended: 1200 × 630 pixels.</p><input id="meta_image" name="meta_image" type="file" accept="image/jpeg,image/png,image/webp"><input type="hidden" name="share_image" value="<?=fe($f['share_image'])?>"><img src="<?=fe($f['share_image'])?>" alt="Current sharing preview" style="width:100%;max-width:500px;border-radius:10px"></div>
<div class="form-group"><label for="description">Information / introduction</label><textarea id="description" name="description" rows="5" maxlength="10000"><?=fe($f['description'])?></textarea></div><div class="form-group"><label for="organizers">Organizers’ names and phone numbers</label><p>One organizer per line: Name | Phone number</p><textarea id="organizers" name="organizers" rows="5" maxlength="5000" placeholder="Name | Phone number"><?=fe($f['organizers'])?></textarea></div><button class="btn btn-primary">Save Feature</button></form></section>
<section class="card"><h3>Contact Inbox</h3><p>Latest 100 messages. Messages are stored here; no email notification is configured.</p><?php if(!$messages):?><p>No messages yet.</p><?php endif;foreach($messages as $m):?><article style="border-bottom:1px solid #8885;padding:18px 0"><strong><?=fe($m['name'])?></strong> · <?=fe($m['created_at'])?><p><a href="mailto:<?=fe($m['email'])?>"><?=fe($m['email'])?></a> · <?=fe($m['phone'])?></p><p><?=nl2br(fe($m['message']))?></p></article><?php endforeach;?></section><?php admin_page_end(); ?>
