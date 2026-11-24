<?php
// ============================================================
// TESTE DE CONEXÃO COM O BANCO — Amigo Secreto Família Marcon
// Acesse UMA VEZ: https://aasatecnologia.com.br/amigosecreto/teste-conexao.php
// Depois que funcionar, APAGUE este arquivo do servidor!
// ============================================================
require __DIR__ . '/config.php';
echo "<h1>🔎 Teste de conexão — Amigo Secreto Família Marcon</h1>";
echo "<p><b>Valores que o servidor está usando (vindos do config.php):</b></p><ul>";
echo "<li>Host: <b>" . htmlspecialchars($DB_HOST) . "</b></li>";
echo "<li>Banco: <b>" . htmlspecialchars($DB_NAME) . "</b></li>";
echo "<li>Usuário: <b>" . htmlspecialchars($DB_USER) . "</b></li>";
echo "<li>Senha: <b>(" . (strlen($DB_PASS) ? strlen($DB_PASS) . " caracteres" : "VAZIA!") . ")</b></li>";
echo "</ul><hr>";
try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $t = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
  echo "<p style='color:green;font-size:20px'>✅ CONEXÃO OK!</p>";
  echo "<p>Tabelas encontradas: <b>" . implode(', ', $t) . "</b></p>";
  echo "<p>Agora rode o <b>install.php</b> e depois APAGUE o teste-conexao.php e o install.php.</p>";
} catch (Exception $e) {
  echo "<p style='color:red;font-size:20px'>❌ FALHOU: " . htmlspecialchars($e->getMessage()) . "</p>";
  echo "<p><b>1045 Access denied</b> = usuário ou senha errados. Confira no cPanel → MySQL Databases → Usuários Atuais (o usuário costuma ser <b>aasa_amigosecreto</b>, NÃO só <b>aasa</b>) e edite o <b>config.php</b> no FTP.</p>";
  echo "<p>Se o usuário mostrado acima NÃO for <b>aasa_amigosecreto</b>, é porque o config.php no servidor ainda não foi salvo/atualizado — edite de novo no WinSCP e clique em Salvar (Ctrl+S).</p>";
}
