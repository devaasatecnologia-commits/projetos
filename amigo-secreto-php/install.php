<?php
// ============================================================
// Amigo Secreto Família Marcon — Desenvolvimento AASA TECNOLOGIA
// Rode UMA VEZ: acesse  https://seu-dominio.com/amigosecreto/install.php
// Ele cria as tabelas, cadastra os 11 nomes da foto e GERA os
// códigos. Anote os códigos, envie cada um PRIVADO e APAGUE este arquivo.
// ============================================================
require __DIR__ . '/config.php';

$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS participants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  code_plain VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS challenges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(80) NOT NULL,
  emoji VARCHAR(10) DEFAULT '🎲',
  description TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS draws (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  giver_id INT UNSIGNED NOT NULL,
  receiver_id INT UNSIGNED NOT NULL,
  challenge_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_giver (giver_id),
  CONSTRAINT fk_draw_giver FOREIGN KEY (giver_id) REFERENCES participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_draw_receiver FOREIGN KEY (receiver_id) REFERENCES participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_draw_challenge FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$challenges = [
  ['Música','🎤','Cante uma música (ou um trecho) e só então revele quem é seu amigo secreto.'],
  ['Mímica','🎭','Faça uma mímica representando seu amigo secreto. Quem acertar, você entrega o presente!'],
  ['Poema','📜','Leia um poema dedicado ao seu amigo secreto antes de revelar.'],
  ['Dança','💃','Dance sua música favorita e revele o amigo secreto no final da dança.'],
  ['Desfile','🚶','Desfile como na passarela e revele o amigo secreto ao final do desfile.'],
  ['Imitação','🕵️','Imite uma pessoa famosa por 30 segundos e depois revele o amigo secreto.'],
  ['Show de Talentos','🌟','Mostre um talento (mágica, malabarismo, piada...) e revele o amigo secreto no final.'],
  ['História','📖','Conte uma história engraçada inventada sobre seu amigo secreto antes de revelar.'],
  ['Charada','🧩','Faça uma charada sobre seu amigo secreto. Quem acertar ganha o presente das suas mãos!'],
  ['Coroa','👑','Coroa seu amigo secreto como rei/rainha da festa e entregue o presente.'],
];
if ((int)$pdo->query("SELECT COUNT(*) FROM challenges")->fetchColumn() === 0) {
  $st = $pdo->prepare("INSERT INTO challenges (label, emoji, description) VALUES (?,?,?)");
  foreach ($challenges as $c) $st->execute($c);
}

$names = ['Alan','Sirlene','Azofe','Manua','Ronaldo','Monu','Marcos','Ammilee','Azonzinho','Tae Jeung','Mateus'];
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$codes = [];
$st = $pdo->prepare("INSERT INTO participants (name, code_hash, code_plain) VALUES (?,?,?)");
foreach ($names as $n) {
  $code = '';
  for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars)-1)];
  $code = substr($code,0,4).'-'.substr($code,4);
  $st->execute([$n, password_hash($code, PASSWORD_DEFAULT), $code]);
  $codes[] = "$n — código: $code";
}
file_put_contents(__DIR__.'/codigos-iniciais.txt', "CÓDIGOS INICIAIS — envie cada um PRIVADO e APAGUE este arquivo depois.\n\n".implode("\n",$codes)."\n");
echo "<h1>✅ Instalação concluída!</h1><p>Códigos gerados em <b>codigos-iniciais.txt</b> (na mesma pasta) e também no banco de dados.</p><p><b>APAGUE o install.php e o codigos-iniciais.txt do servidor após anotar tudo!</b></p>";
