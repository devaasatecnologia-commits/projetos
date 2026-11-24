# 🎁 Amigo Secreto Família Marcon — versão PHP + MySQL (cPanel)
**Desenvolvimento AASA TECNOLOGIA**

## Instalação na sua hospedagem (cPanel)
1. **cPanel → MySQL® Databases**: crie um banco (ex.: `aasa_amigosecreto`), um usuário e dê TODOS os privilégios. Anote: host, nome do banco, usuário, senha.
2. **Edite `config.php`** com esses dados e troque a `$ADMIN_PASSWORD`.
3. **Envie por FTP** todos os arquivos desta pasta para `public_html/amigosecreto/` (substitua os antigos — pode apagar `server.js`, `package.json`, `Dockerfile`, `render.yaml`, `public/`).
4. Acesse **https://seu-dominio.com/amigosecreto/install.php** UMA VEZ — ele cria as tabelas, cadastra os 11 nomes e gera os códigos em `codigos-iniciais.txt`.
5. **APAGUE** `install.php` e `codigos-iniciais.txt` do servidor após anotar/enviar os códigos (privado!) para cada pessoa.
6. Acesse a pasta: login do participante com o código. Organizador: `index.php?page=admin` → senha → **Realizar sorteio**.

## Alternativa ao install.php
Importe `banco.sql` no phpMyAdmin e cadastre os participantes pelo painel admin.

## Observação
Esta é a versão correta para hospedagem cPanel/Apache (PHP). A versão Node (`../amigo-secreto`) só funciona em servidor com Node.js (ex.: Render).
