<?php
// Amigo Secreto Família Marcon — Desenvolvimento AASA TECNOLOGIA
// Versão PHP + MySQL (cPanel/Apache) — sem Node.js
require __DIR__ . '/config.php';
session_start();
function db(): PDO { global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS; static $p=null;
  if($p===null) $p=new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",$DB_USER,$DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); return $p; }
function e($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function page($t,$b){ global $BRAND,$DEV; echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($t).'</title><link rel="stylesheet" href="style.css"></head><body><div class="card">'.$b.'</div><footer><p>🎁 <b>'.e($BRAND).'</b></p><p>'.e($DEV).'</p></footer></body></html>'; exit; }
function genCode(){ $c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s=''; for($i=0;$i<8;$i++) $s.=$c[random_int(0,strlen($c)-1)]; return substr($s,0,4).'-'.substr($s,4); }
if(($_POST['action']??'')==='login'){ $code=strtoupper(trim($_POST['code']??''));
  foreach(db()->query("SELECT * FROM participants") as $p){ if(password_verify($code,$p['code_hash'])){ $_SESSION['user_id']=$p['id']; header('Location: index.php?page=sorteio'); exit; } }
  page('Erro','<p>Código inválido. Confira e tente novamente.</p>'); }
if(($_POST['action']??'')==='admin_login'){ if(!empty($ADMIN_PASSWORD)&&($_POST['pw']??'')===$ADMIN_PASSWORD){ $_SESSION['admin']=true; header('Location: index.php?page=admin'); exit; } page('Erro','<p>Senha incorreta.</p>'); }
if(($_POST['action']??'')==='logout'){ session_destroy(); header('Location: index.php'); exit; }
if(($_POST['action']??'')==='draw'&&!empty($_SESSION['admin'])){ $pdo=db();
  $ids=$pdo->query("SELECT id FROM participants")->fetchAll(PDO::FETCH_COLUMN); if(count($ids)<2) page('Erro','<p>É preciso pelo menos 2 participantes.</p>');
  shuffle($ids); $ch=$pdo->query("SELECT id FROM challenges")->fetchAll(PDO::FETCH_COLUMN); shuffle($ch);
  $pdo->exec("DELETE FROM draws"); $ins=$pdo->prepare("INSERT INTO draws (giver_id,receiver_id,challenge_id) VALUES (?,?,?)");
  foreach($ids as $i=>$g) $ins->execute([$g,$ids[($i+1)%count($ids)],$ch[$i%count($ch)]??null]);
  header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='add'&&!empty($_SESSION['admin'])){ $n=trim($_POST['name']??''); if($n!==''){ $code=genCode(); db()->prepare("INSERT INTO participants (name,code_hash,code_plain) VALUES (?,?,?)")->execute([$n,password_hash($code,PASSWORD_DEFAULT),$code]); } header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='add_challenge'&&!empty($_SESSION['admin'])){ $l=trim($_POST['label']??''); $d=trim($_POST['desc']??''); if($l&&$d) db()->prepare("INSERT INTO challenges (label,emoji,description) VALUES (?,'🎲',?)")->execute([$l,$d]); header('Location: index.php?page=admin'); exit; }
$pg=$_GET['page']??'';
if(!empty($_SESSION['admin'])&&$pg!=='admin'){ header('Location: index.php?page=admin'); exit; }
if(!empty($_SESSION['user_id'])&&$pg===''){ header('Location: index.php?page=sorteio'); exit; }
if($pg==='admin'){ if(empty($_SESSION['admin'])) page('Organizador','<h2>🔐 Área do organizador</h2><form method="POST"><input type="hidden" name="action" value="admin_login"><input type="password" name="pw" placeholder="Senha do organizador" required><button>Entrar</button></form>');
  $pdo=db(); $list=''; foreach($pdo->query("SELECT * FROM participants ORDER BY name") as $p) $list.='<li>'.e($p['name']).' — código: <b>'.e($p['code_plain']).'</b></li>';
  $chs=''; foreach($pdo->query("SELECT * FROM challenges") as $c) $chs.='<li>'.e($c['emoji']).' '.e($c['label']).' — '.e($c['description']).'</li>';
  $dr=$pdo->query("SELECT created_at FROM draws ORDER BY created_at DESC LIMIT 1")->fetchColumn();
  page('Admin','<h2>🎉 Painel — '.e($BRAND).'</h2><p class="muted">Sorteio: '.($dr?e($dr):'ainda não realizado').'</p><form method="POST"><input type="hidden" name="action" value="draw"><button>🎲 Realizar sorteio agora</button></form><h3>Participantes</h3><ul>'.$list.'</ul><form method="POST"><input type="hidden" name="action" value="add"><input name="name" placeholder="Novo participante" required><button>+ Adicionar (gera novo código)</button></form><h3>Desafios</h3><ul>'.$chs.'</ul><form method="POST"><input type="hidden" name="action" value="add_challenge"><input name="label" placeholder="Desafio (ex.: Karaokê)" required><input name="desc" placeholder="Descrição" required><button>+ Adicionar desafio</button></form><form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>'); }
if($pg==='sorteio'){ if(empty($_SESSION['user_id'])){ header('Location: index.php'); exit; } $pdo=db();
  $me=$pdo->prepare("SELECT * FROM participants WHERE id=?"); $me->execute([$_SESSION['user_id']]); $me=$me->fetch(PDO::FETCH_ASSOC);
  $d=$pdo->prepare("SELECT * FROM draws WHERE giver_id=?"); $d->execute([$me['id']]); $d=$d->fetch(PDO::FETCH_ASSOC);
  if(!$d) page('Aguardando','<h2>⏳ Sorteio ainda não realizado</h2><p class="muted">O organizador precisa liberar o sorteio. Volte em instantes!</p><form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>');
  $r=$pdo->prepare("SELECT name FROM participants WHERE id=?"); $r->execute([$d['receiver_id']]); $recv=$r->fetchColumn(); $ch=null;
  if($d['challenge_id']){ $c=$pdo->prepare("SELECT * FROM challenges WHERE id=?"); $c->execute([$d['challenge_id']]); $ch=$c->fetch(PDO::FETCH_ASSOC); }
  page('Seu amigo secreto','<h2 class="big">🎉 '.e($me['name']).', seu amigo secreto é:</h2><p class="big">'.e($recv).'</p>'.($ch?'<hr><h3>'.e($ch['emoji']).' Seu desafio na entrega: '.e($ch['label']).'</h3><p>'.e($ch['description']).'</p>':'').'<p class="muted">Guarde segredo! Só revele na hora com o desafio. 🤫</p><form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>'); }
page($BRAND,'<h1 class="big">🎁 '.e($BRAND).'</h1><p class="muted">Entre com seu código secreto (ex.: AB12-CD34) para ver seu amigo e seu desafio.</p><form method="POST"><input type="hidden" name="action" value="login"><input name="code" placeholder="Código secreto" autocomplete="off" required><button>Entrar</button></form>');
