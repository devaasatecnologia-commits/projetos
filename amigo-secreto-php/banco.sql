-- ============================================================
-- Amigo Secreto Família Marcon — Desenvolvimento AASA TECNOLOGIA
-- Importe este arquivo no phpMyAdmin (ou rode o install.php)
-- ============================================================
CREATE TABLE IF NOT EXISTS participants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code_hash VARCHAR(255) DEFAULT NULL,
  code_plain VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS challenges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(80) NOT NULL,
  emoji VARCHAR(10) DEFAULT '🎲',
  description TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS draws (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  giver_id INT UNSIGNED NOT NULL,
  receiver_id INT UNSIGNED NOT NULL,
  challenge_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_giver (giver_id),
  CONSTRAINT fk_draw_giver FOREIGN KEY (giver_id) REFERENCES participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_draw_receiver FOREIGN KEY (receiver_id) REFERENCES participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_draw_challenge FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO challenges (label, emoji, description) VALUES
 ('Música','🎤','Cante uma música (ou um trecho) e só então revele quem é seu amigo secreto.'),
 ('Mímica','🎭','Faça uma mímica representando seu amigo secreto. Quem acertar, você entrega o presente!'),
 ('Poema','📜','Leia um poema dedicado ao seu amigo secreto antes de revelar.'),
 ('Dança','💃','Dance sua música favorita e revele o amigo secreto no final da dança.'),
 ('Desfile','🚶','Desfile como na passarela e revele o amigo secreto ao final do desfile.'),
 ('Imitação','🕵️','Imite uma pessoa famosa por 30 segundos e depois revele o amigo secreto.'),
 ('Show de Talentos','🌟','Mostre um talento (mágica, malabarismo, piada...) e revele o amigo secreto no final.'),
 ('História','📖','Conte uma história engraçada inventada sobre seu amigo secreto antes de revelar.'),
 ('Charada','🧩','Faça uma charada sobre seu amigo secreto. Quem acertar ganha o presente das suas mãos!'),
 ('Coroa','👑','Coroa seu amigo secreto como rei/rainha da festa e entregue o presente.');
