<?php
if(getenv('DB_NAME')!=='church_test')throw new RuntimeException('Isolated DB only');
require getcwd().'/php/config.php';
require getcwd().'/php/admin/service-planner/model.php';
$pdo=db();$pdo->exec(file_get_contents(getcwd().'/database/glc_cpanel_full_schema.sql'));sp_schema($pdo);
foreach(['pastor','admin','music_minister','media','sound','worship_team','youth_minister'] as $role){$s=$pdo->prepare('INSERT INTO admin_users(username,password_hash,role,is_active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),role=VALUES(role),is_active=1');$s->execute(['qa_'.$role,password_hash('local-test-only',PASSWORD_DEFAULT),$role]);}
$pdo->exec('DELETE FROM service_task_completions');$pdo->exec('DELETE FROM service_plans');
sp_mutate($pdo,['action'=>'create','service_date'=>'2026-10-04'],'qa_pastor');
$id=(int)$pdo->query("SELECT id FROM service_plans WHERE service_date='2026-10-04'")->fetchColumn();
sp_mutate($pdo,['action'=>'sermon','service_id'=>$id,'revision'=>0,'title'=>'Hope for the Journey','primary_scripture'=>'Psalm 121:1–8','additional_scriptures'=>'John 3:16','media_notes'=>'Prepare the opening scripture.','ready'=>'1'],'qa_pastor');
sp_mutate($pdo,['action'=>'worship','service_id'=>$id,'revision'=>1,'songs'=>[['title'=>'Goodness of God','key'=>'G','lead'=>'Worship leader','notes'=>'Acoustic opening'],['title'=>'Great Are You Lord','key'=>'D','lead'=>'Worship team']],'ready'=>'1'],'qa_music_minister');
$pdo->exec("DELETE FROM announcements WHERE title LIKE 'QA %'");
$s=$pdo->prepare('INSERT INTO announcements(category,title,body,start_date,end_date,is_published,sort_order) VALUES(?,?,?,?,?,1,0)');
$s->execute(['main','QA Upcoming outreach','Prepare the announcement graphic.','2026-10-18','2026-10-18']);$s->execute(['main','QA Expired event','Should not appear.','2026-09-01','2026-09-02']);$s->execute(['youth','QA Youth night','Youth announcement.','2026-10-10','2026-10-10']);
echo "Fixture Sunday ID: $id\n";
