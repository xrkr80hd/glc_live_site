<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
require_once __DIR__.'/model.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!admin_current_user()) {http_response_code(401);echo sp_json(['error'=>'Sign in again before continuing.']);exit;}
try {
    $pdo=db();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        verify_csrf($_POST['csrf_token']??'');
        sp_mutate($pdo,$_POST,(string)admin_current_user()['username']);
    } elseif ($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);exit;}
    session_write_close();
    $id=(int)($_POST['service_id']??$_GET['service_id']??0);
    $pdo->beginTransaction();
    $service=sp_service($pdo,$id);$tasks=sp_tasks($pdo,$service);$completions=sp_completions($pdo,$id);
    $pdo->commit();
    echo sp_json(['revision'=>(int)$service['revision'],'archived'=>(bool)$service['is_archived'],
        'statuses'=>sp_feed($service,$tasks,$completions),'completions'=>$completions,
        'sermon'=>$service['sermon'],'worship'=>$service['worship'],'updated_by'=>$service['updated_by'],
        'updated_at'=>$service['updated_at']]);
} catch(Throwable $e) {
    if(isset($pdo) && $pdo->inTransaction())$pdo->rollBack();
    $known=$e instanceof InvalidArgumentException || $e->getCode()===409;
    http_response_code($e->getCode()===409?409:($known?422:500));
    if(!$known)error_log('Service planner: '.$e->getMessage());
    echo sp_json(['error'=>$known?$e->getMessage():'Planner could not save or load. Try again; contact the site administrator if it continues.']);
}
