<?php
require __DIR__.'/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function reply(array $data,int $status=200):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function fail(string $message,int $status=400):never{global $pdo;if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();reply(['error'=>$message],$status);}
function uid():string{return bin2hex(random_bytes(16));}
function getProject(array $data,string $id):?array{foreach($data['projects'] as $p)if($p['id']===$id)return $p;return null;}
function manages(array $me,?array $p):bool{return $me['role']==='admin'||($p&&in_array($me['email'],$p['managers'],true));}
function sees(array $me,array $p):bool{return $me['role']==='admin'||in_array($me['email'],$p['members'],true);}
function validDate($v):bool {if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))return false;[$y,$m,$d]=array_map('intval',explode('-',$v));return checkdate($m,$d,$y);}
function notesFor(array $t,array $logs):array{if(isset($t['notes'])&&is_array($t['notes']))return $t['notes'];if(empty($t['description']))return [];foreach(array_reverse($logs) as $l)if($l['entity']===$t['id'])return [['id'=>'initial-'.$t['id'],'text'=>$t['description'],'deadline'=>$t['deadline'],'actor'=>$l['actor'],'at'=>$l['at']]];return [['id'=>'initial-'.$t['id'],'text'=>$t['description'],'deadline'=>$t['deadline'],'actor'=>'ثبت اولیه','at'=>null]];}
try{
$pdo=db();$me=currentUser($pdo);if(!$me)fail('برای ادامه وارد حساب کاربری شوید.',401);
$method=$_SERVER['REQUEST_METHOD'];if(!in_array($method,['GET','POST'],true))fail('روش درخواست مجاز نیست',405);
if($method==='POST')csrf();
if($method==='POST')$pdo->beginTransaction();
$row=$pdo->query('SELECT data FROM karban_workspace WHERE id=1'.($method==='POST'?' FOR UPDATE':''))->fetch();if(!$row)fail('دیتابیس نصب نشده است',503);$d=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR);
if($method==='GET'){
$visible=fn($p)=>empty($p['deletedAt'])&&sees($me,$p);
$projects=array_values(array_filter($d['projects'],$visible));$ids=array_column($projects,'id');
$tasks=array_values(array_filter($d['tasks'],fn($t)=>empty($t['deletedAt'])&&in_array($t['project'],$ids,true)));
$logProjects=array_column(array_filter($d['projects'],fn($p)=>sees($me,$p)),'id');$logs=array_values(array_filter($d['logs'],fn($l)=>$me['role']==='admin'||in_array($l['project'],$logProjects,true)));
reply(['version'=>$d['version'],'users'=>$d['users'],'projects'=>$projects,'tasks'=>$tasks,'logs'=>$logs,'me'=>$me]);}
if((int)($_SERVER['CONTENT_LENGTH']??0)>1000000)fail('درخواست بیش از حد بزرگ است');
$b=json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR);$kind=$b['kind']??'';$v=$b['value']??null;if(!is_array($v)||!in_array($kind,['user','project','task','tasknote','delete-project','delete-task'],true))fail('درخواست نامعتبر است');if(($b['version']??null)!==$d['version'])fail('اطلاعات تغییر کرده است؛ دوباره تلاش کنید',409);
$before=null;$after=null;$project='';$title='';$now=gmdate('Y-m-d\TH:i:s\Z');
if($kind==='user'){
if($me['role']!=='admin')fail('مجوز مدیریت کاربران ندارید',403);
$name=trim((string)($v['name']??''));$email=strtolower(trim((string)($v['email']??'')));$role=$v['role']??'';$password=$v['password']??'';
if(!$name||strlen($name)>600||!filter_var($email,FILTER_VALIDATE_EMAIL)||!is_string($password)||strlen($password)<10||!in_array($role,['admin','manager','member'],true))fail('نام، ایمیل و رمز حداقل ۱۰ کاراکتری وارد کنید');
foreach($d['users'] as $u)if(strtolower($u['email'])===$email)fail('این ایمیل قبلاً ثبت شده است');
$after=['id'=>uid(),'name'=>$name,'email'=>$email,'role'=>$role];$q=$pdo->prepare('INSERT INTO karban_users VALUES(?,?,?,?,?)');$q->execute([$after['id'],$name,$email,$role,password_hash($password,PASSWORD_DEFAULT)]);$d['users'][]=$after;$title='افزودن عضو تیم';
}elseif(str_starts_with($kind,'delete-')){
$type=$kind==='delete-project'?'projects':'tasks';$index=null;foreach($d[$type] as $i=>$item)if($item['id']===($v['id']??'')&&empty($item['deletedAt'])){$index=$i;break;}if($index===null)fail('این مورد موجود نیست یا قبلاً حذف شده است',404);$target=$d[$type][$index];$p=$type==='projects'?$target:getProject($d,$target['project']);if(!$p||!empty($p['deletedAt']))fail('پروژه موجود نیست',404);if(!manages($me,$p))fail('فقط مدیر کل یا مدیر پروژه می‌تواند حذف کند',403);$before=$target;$after=array_merge($target,['deletedAt'=>$now,'deletedBy'=>$me['name']]);$d[$type][$index]=$after;$project=$p['id'];$count=0;
if($type==='projects')foreach($d['tasks'] as &$t){if($t['project']===$project&&empty($t['deletedAt'])){$t['deletedAt']=$now;$t['deletedBy']=$me['name'];$count++;}}unset($t);
$title='حذف '.($type==='projects'?'پروژه: ':'تسک: ').$target['title'].($type==='projects'?' (همراه '.$count.' تسک)':'');
}elseif($kind==='tasknote'){
$index=null;foreach($d['tasks'] as $i=>$t)if($t['id']===($v['id']??'')&&empty($t['deletedAt'])){$index=$i;break;}if($index===null)fail('تسک موجود نیست',404);$t=$d['tasks'][$index];$p=getProject($d,$t['project']);if(!$p||!empty($p['deletedAt']))fail('پروژه موجود نیست',404);if(!manages($me,$p)&&!(sees($me,$p)&&$t['assignee']===$me['email']))fail('مجوز ثبت توضیح ندارید',403);
$text=trim((string)($v['text']??''));if(!$text||strlen($text)>40000||!validDate($v['deadline']??null))fail('توضیح و ددلاین معتبر وارد کنید');$before=$t;$after=$t;$after['notes']=notesFor($t,$d['logs']);$after['notes'][]=['id'=>uid(),'text'=>$text,'deadline'=>$v['deadline'],'actor'=>$me['name'],'at'=>$now];$d['tasks'][$index]=$after;$project=$t['project'];$title='ثبت توضیح جدید برای تسک: '.$t['title'];
}else{
$type=$kind==='project'?'projects':'tasks';$index=null;foreach($d[$type] as $i=>$item)if($item['id']===($v['id']??'')){$index=$i;break;}$before=$index!==null?$d[$type][$index]:null;if($before&&!empty($before['deletedAt']))fail('این مورد حذف شده است',404);
$p=$kind==='project'?$before:getProject($d,(string)($before['project']??$v['project']??''));
if($kind==='task'&&(!$p||!empty($p['deletedAt'])))fail('پروژه موجود نیست',404);
$canManage=manages($me,$p)||($kind==='project'&&!$before&&$me['role']==='manager');
if(!$canManage){if(!($kind==='task'&&$before&&sees($me,$p)&&$before['assignee']===$me['email']))fail('مجوز ویرایش ندارید',403);foreach(['title','project','assignee','deadline','priority'] as $key)$v[$key]=$before[$key];}
$taskTitle=trim((string)($v['title']??''));$description=(string)($v['description']??'');$deadline=$v['deadline']??'';
if(!$taskTitle||strlen($taskTitle)>800||strlen($description)>40000||($deadline!==''&&!validDate($deadline)))fail('عنوان، توضیحات و موعد را بررسی کنید');
$id=$before['id']??uid();
if($kind==='project'){
$emails=array_column($d['users'],'email');$members=array_values(array_intersect(is_array($v['members']??null)?$v['members']:[],$emails));$managerEmails=array_column(array_filter($d['users'],fn($u)=>$u['role']!=='member'),'email');$managers=array_values(array_intersect(is_array($v['managers']??null)?$v['managers']:[],$managerEmails));$members=array_values(array_unique(array_merge($members,$managers,[$me['email']])));
$status=$v['status']??'active';if(!in_array($status,['active','planned','paused','completed'],true))$status='active';$after=['id'=>$id,'title'=>$taskTitle,'description'=>$description,'deadline'=>$deadline,'members'=>$members,'managers'=>$managers,'status'=>$status];$project=$id;
}else{
if($before&&($v['project']??$before['project'])!==$before['project'])fail('جابجایی تسک به پروژه دیگر پشتیبانی نمی‌شود');$assignee=$v['assignee']??'';if($assignee!==''&&!in_array($assignee,$p['members'],true))fail('مسئول باید عضو پروژه باشد');$status=$v['status']??'todo';if(!in_array($status,['todo','doing','review','done','blocked'],true))fail('وضعیت نامعتبر است');$priority=$v['priority']??'medium';if(!in_array($priority,['low','medium','high','urgent'],true))fail('اولویت نامعتبر است');
$after=['id'=>$id,'project'=>$p['id'],'title'=>$taskTitle,'description'=>$description,'deadline'=>$deadline,'assignee'=>$assignee,'priority'=>$priority,'status'=>$status];$after['notes']=$before?notesFor($before,$d['logs']):($description!==''?[['id'=>uid(),'text'=>$description,'deadline'=>$deadline,'actor'=>$me['name'],'at'=>$now]]:[]);$project=$p['id'];
}
if($index===null)$d[$type][]=$after;else $d[$type][$index]=$after;$title=($before?'ویرایش ':'ایجاد ').($kind==='project'?'پروژه: ':'تسک: ').$taskTitle;
}
array_unshift($d['logs'],['id'=>uid(),'project'=>$project,'entity'=>$after['id'],'actor'=>$me['name'],'at'=>$now,'title'=>$title,'before'=>$before,'after'=>$after,'note'=>substr((string)($b['note']??''),0,8000)]);$d['version']++;$q=$pdo->prepare('UPDATE karban_workspace SET data=? WHERE id=1');$q->execute([json_encode($d,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$pdo->commit();reply(['ok'=>true]);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();reply(['error'=>'عملیات انجام نشد. تنظیمات دیتابیس و اطلاعات ورودی را بررسی کنید.'],500);}
