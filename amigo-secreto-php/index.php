<?php
// Amigo Secreto Familia Marcon - Natal Premium - AASA TECNOLOGIA
// FLUXO: Admin LIBERA o sorteio. Participante entra com nome+codigo proprio,
// ve listas (participantes + brincadeiras) e CLICA para conhecer o amigo.
// Antes de liberar: sem botao de revelacao. Sorteio em ciclo unico.
require __DIR__ . '/config.php';
session_start();
function db(): PDO { global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS; static $p=null;
  if($p===null){
    $p=new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",$DB_USER,$DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    // migracao automatica p/ banco ja existente (cPanel)
    $p->exec("CREATE TABLE IF NOT EXISTS settings (id INT PRIMARY KEY, draw_enabled TINYINT(1) NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("INSERT IGNORE INTO settings (id,draw_enabled) VALUES (1,0)");
    $col=$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='draws' AND COLUMN_NAME='revealed_at'")->fetchColumn();
    if(!$col) $p->exec("ALTER TABLE draws ADD COLUMN revealed_at TIMESTAMP NULL DEFAULT NULL");
  } return $p; }
function e($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function norm($s){ return mb_strtolower(trim(preg_replace('/\s+/u',' ',$s)),'UTF-8'); }
function page($t,$b){ global $BRAND,$DEV; echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($t).'</title><link rel="stylesheet" href="style.css"></head><body><div class="card">'.$b.'</div><footer><p>🎁 <b>'.e($BRAND).'</b></p><p>'.e($DEV).'</p></footer></body></html>'; exit; }
function genCode(){ $c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s=''; for($i=0;$i<8;$i++) $s.=$c[random_int(0,strlen($c)-1)]; return substr($s,0,4).'-'.substr($s,4); }
function findByName($pdo,$name){ $all=$pdo->query("SELECT * FROM participants")->fetchAll(PDO::FETCH_ASSOC); foreach($all as $r){ if(norm($r['name'])===norm($name)) return $r; } return null; }
function drawEnabled($pdo){ return (bool)$pdo->query("SELECT draw_enabled FROM settings WHERE id=1")->fetchColumn(); }
function listsHtml($pdo){
  $names=$pdo->query("SELECT name FROM participants ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
  $ph='<h3>👥 Participantes ('.count($names).')</h3><ul>'; foreach($names as $n) $ph.='<li>🎄 '.e($n).'</li>'; $ph.='</ul>';
  $chs=$pdo->query("SELECT * FROM challenges ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
  $ch='<h3>🎭 Brincadeiras possiveis ('.count($chs).')</h3><ul>'; foreach($chs as $c) $ch.='<li>'.e($c['emoji']).' <b>'.e($c['label']).'</b> — '.e($c['description']).'</li>'; $ch.='</ul>';
  return $ph.$ch;
}
function showResult($pdo,$me){
  $d=$pdo->prepare("SELECT * FROM draws WHERE giver_id=?"); $d->execute([$me['id']]); $d=$d->fetch(PDO::FETCH_ASSOC);
  if(!$d) return null;
  $r=$pdo->prepare("SELECT name FROM participants WHERE id=?"); $r->execute([$d['receiver_id']]); $recv=$r->fetchColumn(); $ch=null;
  if($d['challenge_id']){ $c=$pdo->prepare("SELECT * FROM challenges WHERE id=?"); $c->execute([$d['challenge_id']]); $ch=$c->fetch(PDO::FETCH_ASSOC); }
  return '<span class="tag">🎄 NATAL - FAMILIA MARCON</span><h2 class="big">🎉 '.e($me['name']).', seu amigo secreto e:</h2><div class="receiver">'.e($recv).'</div>'.($ch?'<div class="challenge"><h3>'.e($ch['emoji']).' Na hora de entregar, faca: '.e($ch['label']).'</h3><p>'.e($ch['description']).'</p></div>':'').'<p class="muted">Guarde segredo! So revele na hora com o desafio. Voce pode voltar aqui quando quiser usando seu codigo.</p>';
}
if(($_POST['action']??'')==='generate'){
  $name=trim($_POST['name']??''); if($name==='') page('Erro','<p>Digite seu nome.</p><p><a href="index.php">Voltar</a></p>');
  $pdo=db(); $me=findByName($pdo,$name);
  if(!$me) page('Nome nao encontrado','<p><b>'.e($name).'</b> nao esta na lista. Fale com o organizador.</p><p><a href="index.php">Voltar</a></p>');
  if(!empty($me['code_hash'])) page('Codigo ja gerado','<p>Ola <b>'.e($me['name']).'</b>! Voce <b>ja gerou</b> um codigo.</p><p>Esqueceu? Peca ao organizador para <b>resetar</b>.</p><p><a href="index.php">Voltar</a></p>');
  $code=genCode(); $pdo->prepare("UPDATE participants SET code_hash=?, code_plain=NULL WHERE id=?")->execute([password_hash($code,PASSWORD_DEFAULT),$me['id']]);
  $_SESSION['user_id']=$me['id'];
  page('Seu codigo','<h2 class="big">🔐 Anote seu codigo secreto:</h2><p class="code-big">'.e($code).'</p><p class="muted"><b>Guarde bem!</b> So voce vera. Nenhum organizador enxerga.</p><p><a href="index.php?page=sorteio">Continuar</a></p>');
}
if(($_POST['action']??'')==='login'){
  $name=trim($_POST['name']??''); $code=strtoupper(trim($_POST['code']??'')); $pdo=db(); $me=findByName($pdo,$name);
  if(!$me) page('Erro','<p>Nome nao encontrado. Confira ou fale com o organizador.</p><p><a href="index.php">Voltar</a></p>');
  if(empty($me['code_hash'])) page('Info','<p>Voce ainda nao gerou codigo. Gere na tela inicial.</p><p><a href="index.php">Gerar</a></p>');
  if(!password_verify($code,$me['code_hash'])) page('Erro','<p>Codigo incorreto.</p><p class="muted">Esqueceu? Peca reset ao organizador.</p><p><a href="index.php">Voltar</a></p>');
  $_SESSION['user_id']=$me['id']; header('Location: index.php?page=sorteio'); exit;
}
if(($_POST['action']??'')==='reveal' && !empty($_SESSION['user_id'])){
  $pdo=db(); if(!drawEnabled($pdo)) page('Aguardando','<p>O sorteio ainda nao foi liberado.</p><p><a href="index.php?page=sorteio">Voltar</a></p>');
  $me=$pdo->prepare("SELECT * FROM participants WHERE id=?"); $me->execute([$_SESSION['user_id']]); $me=$me->fetch(PDO::FETCH_ASSOC);
  $d=$pdo->prepare("SELECT * FROM draws WHERE giver_id=?"); $d->execute([$me['id']]); $d=$d->fetch(PDO::FETCH_ASSOC);
  if(!$d){
    $ids=$pdo->query("SELECT id FROM participants")->fetchAll(PDO::FETCH_COLUMN);
    if(count($ids)<2) page('Erro','<p>E preciso pelo menos 2 participantes.</p>');
    shuffle($ids); $ch=$pdo->query("SELECT id FROM challenges")->fetchAll(PDO::FETCH_COLUMN); shuffle($ch);
    $ins=$pdo->prepare("INSERT INTO draws (giver_id,receiver_id,challenge_id) VALUES (?,?,?)");
    foreach($ids as $i=>$g) $ins->execute([$g,$ids[($i+1)%count($ids)],$ch[$i%count($ch)]??null]);
  }
  $pdo->prepare("UPDATE draws SET revealed_at=NOW() WHERE giver_id=? AND revealed_at IS NULL")->execute([$me['id']]);
  header('Location: index.php?page=sorteio'); exit;
}
if(($_POST['action']??'')==='admin_login'){ if(!empty($ADMIN_PASSWORD)&&($_POST['pw']??'')===$ADMIN_PASSWORD){ $_SESSION['admin']=true; header('Location: index.php?page=admin'); exit; } page('Erro','<p>Senha incorreta.</p>'); }
if(($_POST['action']??'')==='logout'){ session_destroy(); header('Location: index.php'); exit; }
if(($_POST['action']??'')==='release'&&!empty($_SESSION['admin'])){ $pdo=db(); $pdo->exec("DELETE FROM draws"); $pdo->exec("UPDATE settings SET draw_enabled=1 WHERE id=1"); header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='lock'&&!empty($_SESSION['admin'])){ $pdo=db(); $pdo->exec("UPDATE settings SET draw_enabled=0 WHERE id=1"); $pdo->exec("DELETE FROM draws"); header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='add'&&!empty($_SESSION['admin'])){ $n=trim($_POST['name']??''); if($n!==''){ $pdo=db(); if(!findByName($pdo,$n)) $pdo->prepare("INSERT INTO participants (name,code_hash,code_plain) VALUES (?,NULL,NULL)")->execute([$n]); } header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='reset'&&!empty($_SESSION['admin'])){ $id=(int)($_POST['id']??0); if($id) db()->prepare("UPDATE participants SET code_hash=NULL, code_plain=NULL WHERE id=?")->execute([$id]); header('Location: index.php?page=admin'); exit; }
    if(($_POST['action']??'')==='delete_participant'&&!empty($_SESSION['admin'])){ $id=(int)($_POST['id']??0); if($id) db()->prepare("DELETE FROM participants WHERE id=?")->execute([$id]); db()->prepare("DELETE FROM draws WHERE giver_id=? OR receiver_id=?")->execute([$id,$id]); header('Location: index.php?page=admin'); exit; }
    if(($_POST['action']??'')==='edit_participant'&&!empty($_SESSION['admin'])){ $id=(int)($_POST['id']??0); $n=trim($_POST['name']??''); if($id&&$n!==''){ $count=$pdo->query("SELECT COUNT(*) FROM participants WHERE name='".e($n)."' AND id!=$id")->fetchColumn(); if($count==0){ db()->prepare("UPDATE participants SET name=? WHERE id=?")->execute([$n,$id]); } } header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='add_challenge'&&!empty($_SESSION['admin'])){ $l=trim($_POST['label']??''); $dd=trim($_POST['desc']??''); if($l&&$dd) db()->prepare("INSERT INTO challenges (label,emoji,description) VALUES (?,'🎲',?)")->execute([$l,$dd]); header('Location: index.php?page=admin'); exit; }
    if(($_POST['action']??'')==='delete_challenge'&&!empty($_SESSION['admin'])){ $id=(int)($_POST['id']??0); if($id) db()->prepare("DELETE FROM challenges WHERE id=?")->execute([$id]); header('Location: index.php?page=admin'); exit; }
    if(($_POST['action']??'')==='edit_challenge'&&!empty($_SESSION['admin'])){ $id=(int)($_POST['id']??0); $l=trim($_POST['label']??''); $dd=trim($_POST['desc']??''); if($id&&$l&&$dd) db()->prepare("UPDATE challenges SET label=?,description=? WHERE id=?")->execute([$l,$dd,$id]); header('Location: index.php?page=admin'); exit; }
$pg=$_GET['page']??'';
if(!empty($_SESSION['user_id'])&&$pg===''){ header('Location: index.php?page=sorteio'); exit; }
if($pg==='admin'){ if(empty($_SESSION['admin'])) page('Organizador','<h2>🔐 Area do organizador</h2><form method="POST"><input type="hidden" name="action" value="admin_login"><input type="password" name="pw" placeholder="Senha do organizador" required><button>Entrar</button></form>');
  $pdo=db(); $on=drawEnabled($pdo);
  $list=''; foreach($pdo->query("SELECT * FROM participants ORDER BY name") as $p){ $status=!empty($p['code_hash'])?'✅ codigo gerado':'⏳ aguardando gerar'; $btn=!empty($p['code_hash'])?'<form method="POST" style="display:inline"><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="'.(int)$p['id'].'"><button onclick="return confirm(\'Resetar codigo de '.e(addslashes($p['name'])).'?\')">Resetar codigo</button></form>':''; $list.='<li><b>'.e($p['name']).'</b> — '.$status.' '.$btn.'</li>'; }
  $chs=''; foreach($pdo->query("SELECT * FROM challenges ORDER BY id") as $c) $chs.='<li>'.e($c['emoji']).' '.e($c['label']).' — '.e($c['description']).'</li>';
  $revealed=(int)$pdo->query("SELECT COUNT(*) FROM draws WHERE revealed_at IS NOT NULL")->fetchColumn();
  page('Admin','<h2>🎉 Painel — '.e($BRAND).'</h2><p class="muted">Status do sorteio: <b>'.($on?'✅ LIBERADO':'🔒 BLOQUEADO').'</b> | Pessoas que ja revelaram: <b>'.$revealed.'</b></p>'.($on?'<form method="POST"><input type="hidden" name="action" value="lock"><button onclick="return confirm(\'Bloquear e apagar revelacoes?\')">🔒 Bloquear sorteio</button></form>':'<form method="POST"><input type="hidden" name="action" value="release"><button>✅ Liberar sorteio para participantes</button></form><p class="muted">Ao liberar, o botao "Clique aqui para conhecer seu amigo secreto" aparece para cada participante. O sorteio (quem tira quem + brincadeira) e gerado junto, sem ninguem tirar a si mesmo.</p>').'<h3>Participantes</h3><ul>'.$list.'</ul><form method="POST"><input type="hidden" name="action" value="add"><input name="name" placeholder="Novo participante" required><button>+ Adicionar nome</button></form><h3>Brincadeiras</h3><ul>'.$chs.'</ul><form method="POST"><input type="hidden" name="action" value="add_challenge"><input name="label" placeholder="Brincadeira (ex.: Karaoke)" required><input name="desc" placeholder="Como fazer" required><button>+ Adicionar brincadeira</button></form><form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>'); }
if($pg==='sorteio'){ if(empty($_SESSION['user_id'])){ header('Location: index.php'); exit; } $pdo=db();
  $me=$pdo->prepare("SELECT * FROM participants WHERE id=?"); $me->execute([$_SESSION['user_id']]); $me=$me->fetch(PDO::FETCH_ASSOC);
  $d=$pdo->prepare("SELECT * FROM draws WHERE giver_id=? AND revealed_at IS NOT NULL"); $d->execute([$me['id']]); $d=$d->fetch(PDO::FETCH_ASSOC);
  if($d){ page('Seu amigo secreto', '<div class="dash-head"><div class="avatar">🎁</div></div>'.showResult($pdo,$me).'<form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form><hr>'.listsHtml($pdo)); }
  if(!drawEnabled($pdo)){
    page('Aguardando 🎄','<span class="tag">🎄 NATAL - FAMILIA MARCON</span><div class="dash-head"><div class="avatar">🎄</div><h2 class="big">Ola, '.e($me['name']).'! 🎁</h2></div><div class="status wait">🔒 Sorteio ainda nao liberado</div><p class="muted">O sorteio <b>ainda nao foi liberado</b> pelo organizador. Assim que ele liberar, aparece aqui o botao <b>"Clique aqui para conhecer seu amigo secreto"</b>.</p><p class="muted">Enquanto isso, conheca quem vai participar e as brincadeiras que voce pode pegar na entrega:</p><hr>'.listsHtml($pdo).'<form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>');
  }
  page('Sorteio liberado!','<span class="tag">🎄 NATAL - FAMILIA MARCON</span><div class="dash-head"><div class="avatar">🎉</div><h2 class="big">Ola, '.e($me['name']).'! Chegou a hora! 🎉</h2></div><div class="status go">✅ Sorteio liberado!</div><p class="muted">Clique no botao abaixo para conhecer <b>seu amigo secreto</b> e a <b>brincadeira</b> que voce fara na entrega do presente.</p><form method="POST"><input type="hidden" name="action" value="reveal"><button class="reveal-btn">🎁 Clique aqui para conhecer seu amigo secreto</button></form><hr>'.listsHtml($pdo).'<form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>');
}
$home='<span class="tag">🎄 NATAL - FAMILIA MARCON</span><h1 class="big">🎁 '.e($BRAND).'</h1><p class="muted" style="text-align:center">Escolha como quer entrar:</p><div class="choice"><a class="choice-card" href="#ja-tenho"><span class="ico">🔐</span><b>Ja tenho codigo</b><span>Entrar e ver meu amigo</span></a><a class="choice-card" href="#primeira-vez"><span class="ico">🆕</span><b>Primeira vez</b><span>Gerar meu codigo gratis</span></a></div><div class="steps"><div><b>1</b>Digite seu nome</div><div><b>2</b>Gere seu codigo</div><div><b>3</b>Descubra seu amigo</div></div><h3 id="ja-tenho">🔐 Ja tenho meu codigo</h3><div class="form-box"><form method="POST"><input type="hidden" name="action" value="login"><input name="name" placeholder="Seu nome" required><input name="code" placeholder="Seu codigo secreto (ex.: AB12-CD34)" autocomplete="off" required><button>Entrar</button></form></div><hr><h3 id="primeira-vez">🆕 Primeira vez? Gere seu codigo</h3><p class="muted">Digite seu nome: o sistema gera um codigo <b>so para voce</b>.</p><div class="form-box"><form method="POST"><input type="hidden" name="action" value="generate"><input name="name" placeholder="Seu nome (como esta na lista)" required><button class="btn-green">Gerar meu codigo</button></form></div><p class="muted">Esqueceu? Peca reset ao organizador.</p>';
try{ $home.='<hr>'.listsHtml(db()); }catch(Exception $ex){}
page($BRAND,$home);
