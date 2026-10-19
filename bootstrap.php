<?php
// PHP 8.1+; PDO MySQL.
declare(strict_types=1);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Lax','path'=>'/']);
session_start();
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('X-Frame-Options: SAMEORIGIN');
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
function csrf():void { $t=$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf']??'';if(!is_string($t)||!hash_equals($_SESSION['csrf'],$t)){http_response_code(403);exit('درخواست نامعتبر است؛ صفحه را تازه کنید.');}}
function esc(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function db():PDO{if(!is_file(__DIR__.'/config.php'))throw new RuntimeException('نصب کامل نشده است');$c=require __DIR__.'/config.php';return new PDO('mysql:host='.$c['host'].';dbname='.$c['database'].';charset=utf8mb4',$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function currentUser(PDO $db):?array{if(empty($_SESSION['uid']))return null;$q=$db->prepare('SELECT id,name,email,role FROM karban_users WHERE id=?');$q->execute([$_SESSION['uid']]);return $q->fetch()?:null;}
function page(string $title,string $body):void{echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title><style>body{font-family:Tahoma,sans-serif;background:#f2f6fc;color:#26374e;margin:0;min-height:100vh;display:grid;place-items:center}main{box-sizing:border-box;background:white;border:1px solid #dfe7f3;padding:32px;border-radius:15px;width:min(460px,94vw);margin:30px auto}h1{font-size:25px;color:#2676e9}p{font-size:12px;line-height:2;color:#7c8ca0}label{display:block;font-size:12px;margin:17px 0}input,button{box-sizing:border-box;width:100%;padding:12px;border:1px solid #dce5f2;border-radius:7px;margin-top:7px}button{background:#2878ee;color:white;cursor:pointer;border:0;font:inherit}a{color:#2878ee;font-size:12px}.error{background:#fff0f0;color:#b35050;padding:12px;border-radius:7px}</style><main><h1>'.esc($title).'</h1>'.$body.'</main></html>';}
