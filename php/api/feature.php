<?php
require_once __DIR__.'/../features.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
try { $f=feature_get(); echo json_encode(['enabled'=>(bool)$f['enabled'],'title'=>$f['title']]); }
catch (Throwable $e) { http_response_code(503); echo json_encode(['enabled'=>false]); }
