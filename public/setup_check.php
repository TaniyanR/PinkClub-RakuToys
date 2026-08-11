<?php

declare(strict_types=1);
$configFile = dirname(__DIR__) . '/config/config.php';
$exampleFile = dirname(__DIR__) . '/config/config.example.php';
$error='';$done='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!is_file($configFile)){$error='先に config/config.example.php を config/config.php にコピーしてDB接続情報を設定してください。';}
    else{
        try{
            require dirname(__DIR__) . '/lib/bootstrap.php';
            $pdo=db();
            $sql=file_get_contents(dirname(__DIR__) . '/database/schema.sql');
            if($sql===false) throw new RuntimeException('schema.sqlを読み込めません。');
            foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\R|$)/',$sql)?:[])) as $statement){
                if(preg_match('/^(CREATE DATABASE|USE)\b/i',$statement)) continue;
                $pdo->exec($statement);
            }
            $count=(int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if($count===0){
                $username=trim((string)($_POST['username']??'admin')) ?: 'admin';
                $password=(string)($_POST['password']??'');
                if(strlen($password)<10) throw new RuntimeException('管理パスワードは10文字以上にしてください。');
                $stmt=$pdo->prepare('INSERT INTO admins(username,password_hash) VALUES(?,?)');
                $stmt->execute([$username,password_hash($password,PASSWORD_DEFAULT)]);
            }
            $done='セットアップが完了しました。管理画面へログインしてください。';
        }catch(Throwable $e){$error=$e->getMessage();}
    }
}
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>セットアップ</title><style>body{font-family:sans-serif;background:#f0f0f1}.box{max-width:620px;margin:8vh auto;background:#fff;padding:26px;border:1px solid #ddd}input,button{box-sizing:border-box;width:100%;padding:10px;margin:6px 0}.ok{color:#008a20}.err{color:#b32d2e}</style></head><body><main class="box"><h1>PinkClub-RakuToys セットアップ</h1><p>config/config.php のDB接続情報を使用して必要テーブルを作成します。</p><?php if($error):?><p class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif?><?php if($done):?><p class="ok"><?=htmlspecialchars($done,ENT_QUOTES,'UTF-8')?></p><p><a href="../admin/login.php">管理画面へ</a></p><?php else:?><form method="post"><label>管理ユーザー名<input name="username" value="admin" required></label><label>管理パスワード（10文字以上）<input type="password" name="password" minlength="10" required></label><button>セットアップを実行</button></form><?php endif?></main></body></html>