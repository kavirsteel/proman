<?php
require __DIR__.'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']==='POST'){csrf();$_SESSION=[];session_destroy();header('Location: login.php');exit;}
page('خروج از حساب','<form method="post"><input type="hidden" name="csrf" value="'.esc($_SESSION['csrf']).'"><p>از حساب خود خارج می‌شوید؟</p><button>خروج</button></form>');
