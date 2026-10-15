<?php
// Mini-repro: simula request a /students autenticado SIN tocar la app real
define('APP_ROOT', '/workspace/public_html');
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/students'; $_SERVER['REMOTE_ADDR']='1.1.1.1'; $_SERVER['HTTP_USER_AGENT']='test';
require APP_ROOT.'/app/config/autoload.php';
$_SESSION['user_id']=1;
try { $c=new App\Controllers\StudentController(); ob_start(); $c->index(); $h=ob_get_clean(); echo "LEN=".strlen($h)."\n"; }
catch (Throwable $e){ echo get_class($e).": ".$e->getMessage()."\n@".$e->getFile().":".$e->getLine()."\n"; }
