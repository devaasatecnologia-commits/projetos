# 🎁 Amigo Secreto Família Marcon

Sistema particular de Amigo Secreto com sorteio + desafio de entrega.
**Desenvolvimento AASA TECNOLOGIA**

## Como funciona
- Cada participante entra com um **código secreto individual** (ex.: `AB12-CD34`).
- O organizador entra no painel `/admin` com a senha e clica em **Realizar sorteio**.
- O sorteio é em **ciclo único**: todos dão e recebem, ninguém tira a si mesmo.
- Cada pessoa só vê **o próprio amigo secreto + o próprio desafio** (Música, Mímica, Poema, Dança, Desfile etc.).
- Nomes da foto já cadastrados: Alan, Sirlene, Azofe, Manua, Ronaldo, Monu, Marcos, Ammilee, Azonzinho, Tae Jeung, Mateus.

## Rodar no computador (teste)
```bash
cd amigo-secreto
ADMIN_PASSWORD='sua-senha' DEVELOPER_MODE=1 node server.js
# abra http://localhost:3000
```
Na primeira execução, os códigos iniciais são gerados em `data/initial-codes.txt`.
**Envie cada código PRIVADO para a pessoa e apague o arquivo depois.**

## Colocar no ar GRÁTIS (link público) — Render
1. Crie conta em https://render.com (grátis).
2. New + → **Web Service** → **Deploy from Git** OU suba este pacote.
   - Alternativa simples: **Blueprint** com o `render.yaml` deste projeto.
3. Settings:
   - Runtime: **Node**
   - Build Command: *(vazio)*
   - Start Command: `node server.js`
4. Environment Variables: adicione `ADMIN_PASSWORD` = sua senha forte.
5. Create Web Service → aguarde → copie o link `https://...onrender.com`
6. Envie esse link + o código individual para cada participante.

> **Atenção:** no plano gratuito o Render "dorme" após 15 min sem uso e acorda no próximo acesso (leva ~1 min). Os dados ficam no disco do serviço — para uso em festa, faça o sorteio e avise os participantes antes.

## Segurança
- Códigos e senha com hash `scrypt`, sessão `httpOnly` + `Secure`, rate-limit no login.
- Nunca compartilhe sua `ADMIN_PASSWORD` nem os códigos entre participantes.
- Troque a senha de teste se tiver usado uma fraca.

---
🎁 Amigo Secreto Família Marcon — Desenvolvimento AASA TECNOLOGIA
