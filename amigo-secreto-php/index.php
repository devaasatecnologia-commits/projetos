<?php
// Amigo Secreto Familia Marcon - Natal Premium - AASA TECNOLOGIA
// Fluxo: participante digita NOME -> gera o PROPRIO codigo (so ele ve) ->
// entra quando quiser so com esse codigo. Admin NAO ve o codigo.
// Sorteio em CICLO UNICO: ninguem tira a si mesmo, todos dao e recebem.
require __DIR__ . '/config.php';
session_start();
function db(): PDO { global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS; static $p=null;
  if($p===null) $p=new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",$DB_USER,$DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); return $p; }
function e($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function norm($s){ return mb_strtolower(trim(preg_replace('/\s+/u',' ',$s)),'UTF-8'); }
function page($t,$b){ global $BRAND,$DEV; echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($t).'</title><link rel="stylesheet" href="style.css"></head><body><div class="card">'.$b.'</div><footer><p>🎁 <b>'.e($BRAND).'</b></p><p>'.e($DEV).'</p></footer></body></html>'; exit; }
function genCode(){ $c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s=''; for($i=0;$i<8;$i++) $s.=$c[random_int(0,strlen($c)-1)]; return substr($s,0,4).'-'.substr($s,4); }
function findByName($pdo,$name){ $all=$pdo->query("SELECT * FROM participants")->fetchAll(PDO::FETCH_ASSOC); foreach($all as $r){ if(norm($r['name'])===norm($name)) return $r; } return null; }
if(($_POST['action']??'')==='generate'){
  $name=trim($_POST['name']??''); if($name==='') page('Erro','<p>Digite seu nome.</p><p><a href="index.php">Voltar</a></p>');
  $pdo=db(); $me=findByName($pdo,$name);
  if(!$me) page('Nome nao encontrado','<p><b>'.e($name).'</b> nao esta na lista do Amigo Secreto. Fale com o organizador para adicionar seu nome.</p><p><a href="index.php">Voltar</a></p>');
  if(!empty($me['code_hash'])) page('Codigo ja gerado','<p>Ola <b>'.e($me['name']).'</b>! Voce <b>ja gerou</b> um codigo secreto.</p><p>Entre com esse codigo quando quiser. Se esqueceu, peca para o organizador <b>resetar seu codigo</b> - depois voce gera um novo aqui.</p><p><a href="index.php">Voltar para entrar</a></p>');
  $code=genCode(); $pdo->prepare("UPDATE participants SET code_hash=?, code_plain=NULL WHERE id=?")->execute([password_hash($code,PASSWORD_DEFAULT),$me['id']]);
  $_SESSION['user_id']=$me['id'];
  page('Seu codigo secreto','<h2 class="big">🔐 Anote seu codigo secreto:</h2><p class="code-big">'.e($code).'</p><p class="muted"><b>Guarde bem!</b> So voce vera esse codigo. Use ele para entrar quando quiser e ver seu amigo secreto. Se perder, peca ao organizador para resetar.</p><form method="POST"><input type="hidden" name="action" value="logout"><button>Ja anotei - entrar</button></form><p><a href="index.php?page=sorteio">Ver meu amigo secreto</a></p>');
}
if(($_POST['action']??'')==='login'){
  $name=trim($_POST['name']??''); $code=strtoupper(trim($_POST['code']??'')); $pdo=db(); $me=findByName($pdo,$name);
  if(!$me) page('Erro','<p>Nome nao encontrado na lista. Confira a ortografia ou fale com o organizador.</p><p><a href="index.php">Voltar</a></p>');
  if(empty($me['code_hash'])) page('Informacao','<p>Ola <b>'.e($me['name']).'</b>! Voce ainda <b>nao gerou</b> seu codigo secreto.</p><p>Gere agora na tela inicial (so seu nome) - leva 1 segundo.</p><p><a href="index.php">Gerar meu codigo</a></p>');
  if(!password_verify($code,$me['code_hash'])) page('Erro','<p>Codigo incorreto. Confira e tente novamente.</p><p class="muted">Esqueceu? Peca ao organizador para <b>resetar seu codigo</b>. Depois voce gera um novo.</p><p><a href="index.php">Voltar</a></p>');
  $_SESSION['user_id']=$me['id']; header('Location: index.php?page=sorteio'); exit;
}
if(($_POST['action']??'')==='admin_login'){ if(!empty($ADMIN_PASSWORD)&&($_POST['pw']??'')===$ADMIN_PASSWORD){ $_SESSION['admin']=true; header('Location: index.php?page=admin'); exit; } page('Erro','<p>Senha incorreta.</p>'); }
if(($_POST['action']??'')==='logout'){ session_destroy(); header('Location: index.php'); exit; }
if(($_POST['action']??'')==='draw'&&!empty($_SESSION['admin'])){ $pdo=db();
  $ids=$pdo->query("SELECT id FROM participants")->fetchAll(PDO::FETCH_COLUMN); if(count($ids)<2) page('Erro','<p>E preciso pelo menos 2 participantes.</p>');
  shuffle($ids); $ch=$pdo->query("SELECT id FROM challenges")->fetchAll(PDO::FETCH_COLUMN); shuffle($ch);
  $pdo->exec("DELETE FROM draws"); $ins=$pdo->prepare("INSERT INTO draws (giver_id,receiver_id,challenge_id) VALUES (?,?,?)");
  foreach($ids as $i=>$g) $ins->execute([$g,$ids[($i+1)%count($ids)],$ch[$i%count($ch)]??null]);
  header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='add'&&!empty($_SESSION['admin'])){ $n=trim($_POST['name']??''); if($n!==''){ $pdo=db(); if(!findByName($pdo,$n)) $pdo->prepare("INSERT INTO participants (name,code_hash,code_plain) VALUES (?,NULL,NULL)")->execute([$n]); } header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='reset'&&!empty($_SESSION['admin'])){ $id=(int)($_POST['id']??0); if($id) db()->prepare("UPDATE participants SET code_hash=NULL, code_plain=NULL WHERE id=?")->execute([$id]); header('Location: index.php?page=admin'); exit; }
if(($_POST['action']??'')==='add_challenge'&&!empty($_SESSION['admin'])){ $l=trim($_POST['label']??''); $d=trim($_POST['desc']??''); if($l&&$d) db()->prepare("INSERT INTO challenges (label,emoji,description) VALUES (?,'🎲',?)")->execute([$l,$d]); header('Location: index.php?page=admin'); exit; }
$pg=$_GET['page']??'';
if(!empty($_SESSION['admin'])&&$pg!=='admin'){ header('Location: index.php?page=admin'); exit; }
if(!empty($_SESSION['user_id'])&&$pg===''){ header('Location: index.php?page=sorteio'); exit; }
if($pg==='admin'){ if(empty($_SESSION['admin'])) page('Organizador','<h2>🔐 Area do organizador</h2><form method="POST"><input type="hidden" name="action" value="admin_login"><input type="password" name="pw" placeholder="Senha do organizador" required><button>Entrar</button></form>');
  $pdo=db(); $list=''; foreach($pdo->query("SELECT * FROM participants ORDER BY name") as $p){ $status=!empty($p['code_hash'])?'✅ codigo gerado':'⏳ aguardando gerar'; $btn=!empty($p['code_hash'])?'<form method="POST" style="display:inline"><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="'.(int)$p['id'].'"><button onclick="return confirm(\'Resetar o codigo de '.e(addslashes($p['name'])).'? Ele vai gerar um novo.\')">Resetar codigo</button></form>':''; $list.='<li><b>'.e($p['name']).'</b> - '.$status.' '.$btn.'</li>'; }
  $chs=''; foreach($pdo->query("SELECT * FROM challenges") as $c) $chs.='<li>'.e($c['emoji']).' '.e($c['label']).' — '.e($c['description']).'</li>';
  $dr=$pdo->query("SELECT created_at FROM draws ORDER BY created_at DESC LIMIT 1")->fetchColumn();
  page('Admin','<h2>🎉 Painel — '.e($BRAND).'</h2><p class="muted">Sorteio: '.($dr?e($dr):'ainda nao realizado').' | Os codigos sao <b>secretos</b> (voce nao ve).</p><form method="POST"><input type="hidden" name="action" value="draw"><button>🎲 Realizar sorteio agora</button></form><h3>Participantes</h3><ul>'.$list.'</ul><p class="muted">Esqueceu o codigo? Clique em <b>Resetar codigo</b> e a pessoa gera um novo no site.</p><form method="POST"><input type="hidden" name="action" value="add"><input name="name" placeholder="Novo participante" required><button>+ Adicionar nome (sem codigo)</button></form><h3>Desafios</h3><ul>'.$chs.'</ul><form method="POST"><input type="hidden" name="action" value="add_challenge"><input name="label" placeholder="Desafio (ex.: Karaoke)" required><input name="desc" placeholder="Descricao" required><button>+ Adicionar desafio</button></form><form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>'); }
if($pg==='sorteio'){ if(empty($_SESSION['user_id'])){ header('Location: index.php'); exit; } $pdo=db();
  $me=$pdo->prepare("SELECT * FROM participants WHERE id=?"); $me->execute([$_SESSION['user_id']]); $me=$me->fetch(PDO::FETCH_ASSOC);
  $d=$pdo->prepare("SELECT * FROM draws WHERE giver_id=?"); $d->execute([$me['id']]); $d=$d->fetch(PDO::FETCH_ASSOC);
  if(!$d) page('Aguardando','<h2>⏳ Sorteio ainda nao realizado</h2><p class="muted">O organizador precisa liberar o sorteio. Volte em instantes! Seu codigo continua valendo - guarde ele.</p><form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>');
  $r=$pdo->prepare("SELECT name FROM participants WHERE id=?"); $r->execute([$d['receiver_id']]); $recv=$r->fetchColumn(); $ch=null;
  if($d['challenge_id']){ $c=$pdo->prepare("SELECT * FROM challenges WHERE id=?"); $c->execute([$d['challenge_id']]); $ch=$c->fetch(PDO::FETCH_ASSOC); }
  page('Seu amigo secreto','<span class="tag">🎄 NATAL - FAMILIA MARCON</span><h2 class="big">🎉 '.e($me['name']).', seu amigo secreto e:</h2><div class="receiver">'.e($recv).'</div>'.($ch?'<div class="challenge"><h3>'.e($ch['emoji']).' Na hora de entregar, faca: '.e($ch['label']).'</h3><p>'.e($ch['description']).'</p></div>':'').'<p class="muted">Guarde segredo! So revele na hora com o desafio. Voce pode voltar aqui quando quiser usando seu codigo.</p><form method="POST"><input type="hidden" name="action" value="logout"><button>Sair</button></form>'); }
page($BRAND,'<span class="tag">🎄 NATAL - FAMILIA MARCON</span><h1 class="big">🎁 '.e($BRAND).'</h1><h3>🔐 Ja tenho meu codigo</h3><form method="POST"><input type="hidden" name="action" value="login"><input name="name" placeholder="Seu nome" required><input name="code" placeholder="Seu codigo secreto (ex.: AB12-CD34)" autocomplete="off" required><button>Entrar e ver meu amigo secreto</button></form><hr><h3>🆕 Primeira vez? Gere seu codigo</h3><p class="muted">Digite seu nome e o sistema gera um codigo <b>so para voce</b> (nem o organizador ve).</p><form method="POST"><input type="hidden" name="action" value="generate"><input name="name" placeholder="Seu nome (como esta na lista)" required><button>Gerar meu codigo</button></form><p class="muted">Esqueceu o codigo? Peca ao organizador para resetar - depois voce gera um novo aqui.</p>');
