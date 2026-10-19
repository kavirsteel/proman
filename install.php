<?php
require __DIR__.'/bootstrap.php';
if(is_file(__DIR__.'/config.php')){http_response_code(403);page('نصب قبلاً انجام شده','<p>برای امنیت، فایل install.php را از هاست حذف کنید.</p><a href="login.php">ورود به برنامه</a>');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf();try{
 $host=trim($_POST['host']??'localhost');$database=trim($_POST['database']??'');$user=trim($_POST['user']??'');$pass=$_POST['db_password']??'';$name=trim($_POST['name']??'');$email=strtolower(trim($_POST['email']??''));$password=$_POST['password']??'';
 if(!preg_match('/^[a-zA-Z0-9_.:-]+$/',$host)||!preg_match('/^[a-zA-Z0-9_]+$/',$database)||!$user||!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10)throw new RuntimeException('اطلاعات معتبر و رمز عبور حداقل ۱۰ کاراکتری وارد کنید.');
 $db=new PDO('mysql:host='.$host.';dbname='.$database.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('CREATE TABLE IF NOT EXISTS karban_users (id VARCHAR(64) PRIMARY KEY,name VARCHAR(200) NOT NULL,email VARCHAR(254) NOT NULL UNIQUE,role VARCHAR(20) NOT NULL,password_hash VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE IF NOT EXISTS karban_workspace (id INT PRIMARY KEY,data LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $db->exec('CREATE TABLE IF NOT EXISTS karban_login_attempts (ip_hash VARCHAR(64) PRIMARY KEY,attempts INT NOT NULL,window_start BIGINT NOT NULL) ENGINE=InnoDB');
 $db->beginTransaction();$q=$db->query('SELECT COUNT(*) FROM karban_users');if((int)$q->fetchColumn()>0)throw new RuntimeException('این دیتابیس قبلاً نصب شده است؛ تنظیمات اتصال قبلی را بازیابی کنید.');
 $id=bin2hex(random_bytes(16));$q=$db->prepare('INSERT INTO karban_users VALUES(?,?,?,?,?)');$q->execute([$id,$name,$email,'admin',password_hash($password,PASSWORD_DEFAULT)]);
 $data=['version'=>0,'users'=>[['id'=>$id,'name'=>$name,'email'=>$email,'role'=>'admin']],'projects'=>[],'tasks'=>[],'logs'=>[]];$q=$db->prepare('INSERT INTO karban_workspace VALUES(1,?)');$q->execute([json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
 $config=['host'=>$host,'database'=>$database,'user'=>$user,'password'=>$pass];$path=__DIR__.'/config.php';$handle=@fopen($path,'x');if(!$handle)throw new RuntimeException('امکان ایجاد config.php وجود ندارد. دسترسی نوشتن پوشه را بررسی کنید.');$written=fwrite($handle,"<?php\nreturn ".var_export($config,true).";\n");fclose($handle);if(!$written){@unlink($path);throw new RuntimeException('ذخیره تنظیمات ناموفق بود.');}@chmod($path,0600);try{$db->commit();}catch(Throwable $e){@unlink($path);throw $e;}
 session_regenerate_id(true);$_SESSION['uid']=$id;page('نصب انجام شد','<p>پیش از استفاده، فایل install.php را از File Manager حذف کنید و HTTPS را فعال کنید.</p><a href="index.php">ورود به کاربان</a>');exit;
 }catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();$error='نصب انجام نشد؛ اطلاعات اتصال، مجوز دیتابیس و دسترسی پوشه را بررسی کنید. از دیتابیس خالی و اختصاصی استفاده کنید.';}
}
$body='<p>ابتدا در cPanel دیتابیس و کاربر MySQL بسازید و همه دسترسی‌های آن دیتابیس را به کاربر بدهید. نصب را فقط خودتان انجام دهید.</p>'.($error?'<p class="error">'.esc($error).'</p>':'').'<form method="post"><input type="hidden" name="csrf" value="'.esc($_SESSION['csrf']).'">';
foreach(['host'=>'آدرس دیتابیس','database'=>'نام کامل دیتابیس','user'=>'نام کامل کاربر دیتابیس','db_password'=>'رمز دیتابیس','name'=>'نام مدیر کل','email'=>'ایمیل مدیر کل','password'=>'رمز مدیر کل (حداقل ۱۰ کاراکتر)'] as $k=>$label){$type=in_array($k,['password','db_password'])?'password':($k==='email'?'email':'text');$body.='<label>'.$label.'<input required name="'.$k.'" type="'.$type.'" '.($k==='host'?'value="localhost"':'').' '.($k==='password'?'minlength="10"':'').'></label>';}
page('نصب کاربان',$body.'<button>نصب برنامه</button></form>');
