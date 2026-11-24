'use strict';
/**
 * Amigo Secreto — servidor zero-dependências (só Node core).
 * Segurança: senha/códigos com scrypt, sessão httpOnly, rate-limit no login,
 * sorteio em ciclo único (derangement) + desafio sorteado junto.
 */
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = process.env.PORT || 3000;
const BRAND = 'Amigo Secreto Família Marcon';
const DEV = 'Desenvolvimento AASA TECNOLOGIA';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || '';
const PUBLIC_DIR = path.join(__dirname, 'public');
const DATA_DIR = path.join(__dirname, 'data');
const DB_FILE = path.join(DATA_DIR, 'db.json');
const CODES_FILE = path.join(DATA_DIR, 'initial-codes.txt');
const IS_HTTPS = process.env.DEVELOPER_MODE !== '1'; // cookie Secure em produção

if (!ADMIN_PASSWORD) console.warn('[AVISO] ADMIN_PASSWORD não definida — painel admin desativado! Defina a variável de ambiente.');

// ---------- helpers ----------
const uid = () => crypto.randomUUID();
const now = () => new Date().toISOString();
function hashSecret(secret) {
  const salt = crypto.randomBytes(16).toString('hex');
  const hash = crypto.scryptSync(secret, salt, 64).toString('hex');
  return `${salt}:${hash}`;
}
function verifySecret(secret, stored) {
  try {
    const [salt, hash] = stored.split(':');
    const calc = crypto.scryptSync(secret, salt, 64).toString('hex');
    return crypto.timingSafeEqual(Buffer.from(hash, 'hex'), Buffer.from(calc, 'hex'));
  } catch { return false; }
}
function genCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let c = '';
  for (let i = 0; i < 8; i++) c += chars[crypto.randomInt(chars.length)];
  return c.slice(0, 4) + '-' + c.slice(4);
}
function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = crypto.randomInt(i + 1);
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// ---------- banco (arquivo JSON local) ----------
const SEED_NAMES = ['Alan','Sirlene','Azofe','Manua','Ronaldo','Monu','Marcos','Ammilee','Azonzinho','Tae Jeung','Mateus'];
const SEED_CHALLENGES = [
  { label: 'Música', emoji: '🎤', desc: 'Cante uma música (ou um trecho) e só então revele quem é seu amigo secreto.' },
  { label: 'Mímica', emoji: '🎭', desc: 'Faça uma mímica representando seu amigo secreto. Quem acertar, você entrega o presente!' },
  { label: 'Poema', emoji: '📜', desc: 'Leia um poema dedicado ao seu amigo secreto antes de revelar.' },
  { label: 'Dança', emoji: '💃', desc: 'Dance sua música favorita e revele o amigo secreto no final da dança.' },
  { label: 'Desfile', emoji: '🚶', desc: 'Desfile como na passarela e revele o amigo secreto ao final do desfile.' },
  { label: 'Imitação', emoji: '🕵️', desc: 'Imite uma pessoa famosa por 30 segundos e depois revele o amigo secreto.' },
  { label: 'Show de Talentos', emoji: '🌟', desc: 'Mostre um talento (mágica, malabarismo, piada...) e revele o amigo secreto no final.' },
  { label: 'História', emoji: '📖', desc: 'Conte uma história engraçada inventada sobre seu amigo secreto antes de revelar.' },
  { label: 'Charada', emoji: '🧩', desc: 'Faça uma charada sobre seu amigo secreto. Quem acertar ganha o presente das suas mãos!' },
  { label: 'Coroa', emoji: '👑', desc: 'Coroa seu amigo secreto como rei/rainha da festa e entregue o presente.' },
];
function loadDB() {
  if (fs.existsSync(DB_FILE)) {
    const existing = JSON.parse(fs.readFileSync(DB_FILE, 'utf8'));
    // migração: participantes antigos sem 'code' em texto — recupera do txt inicial
    if (fs.existsSync(CODES_FILE)) {
      const map = {};
      for (const line of fs.readFileSync(CODES_FILE, 'utf8').split('\n')) {
        const m = line.match(/^(.+?)\s+— código: (\S+)$/);
        if (m) map[m[1]] = m[2];
      }
      let changed = false;
      for (const p of existing.participants || []) if (!p.code && map[p.name]) { p.code = map[p.name]; changed = true; }
      if (changed) fs.writeFileSync(DB_FILE, JSON.stringify(existing, null, 2));
    }
    return existing;
  }
  const db = { participants: [], challenges: SEED_CHALLENGES.map(c => ({ id: uid(), ...c })), draws: [] };
  const codes = [];
  for (const name of SEED_NAMES) {
    const code = genCode();
    db.participants.push({ id: uid(), name, codeHash: hashSecret(code), code, createdAt: now() });
    codes.push(`${name} — código: ${code}`);
  }
  fs.mkdirSync(DATA_DIR, { recursive: true });
  fs.writeFileSync(DB_FILE, JSON.stringify(db, null, 2));
  fs.writeFileSync(CODES_FILE, 'CÓDIGOS INICIAIS — envie cada um PRIVADO para a pessoa. APAGUE este arquivo depois.\n\n' + codes.join('\n') + '\n');
  console.log('[OK] Banco criado com', db.participants.length, 'participantes. Códigos em data/initial-codes.txt');
  return db;
}
let db = loadDB();
const save = () => { fs.mkdirSync(DATA_DIR, { recursive: true }); fs.writeFileSync(DB_FILE, JSON.stringify(db, null, 2)); };

// ---------- sessões ----------
const sessions = new Map();
function newSession(type, participantId) {
  const token = crypto.randomBytes(32).toString('hex');
  sessions.set(token, { type, participantId, exp: Date.now() + 1000*60*60*12 });
  return token;
}
function getSession(req) {
  const m = (req.headers.cookie||'').match(/session=([a-f0-9]+)/);
  if (!m) return null;
  const s = sessions.get(m[1]);
  if (!s || s.exp < Date.now()) { sessions.delete(m[1]); return null; }
  return s;
}
const cookieFor = t => `session=${t}; HttpOnly; Path=/; SameSite=Lax; Max-Age=43200${IS_HTTPS?'; Secure':''}`;

// ---------- rate-limit ----------
const attempts = new Map();
function rateLimited(ip) {
  const t = Date.now(); let a = attempts.get(ip);
  if (!a || t > a.resetAt) { a = { count:0, resetAt: t+15*60*1000 }; attempts.set(ip,a); }
  a.count++; return a.count > 10;
}

// ---------- sorteio em ciclo ----------
function doDraw() {
  const ids = db.participants.map(p=>p.id);
  if (ids.length < 2) return { error:'É preciso pelo menos 2 participantes.' };
  const order = shuffle(ids);
  const ch = shuffle(db.challenges);
  db.draws = order.map((giver,i)=>({ id:uid(), giverId:giver, receiverId:order[(i+1)%order.length], challengeId: ch[i%ch.length]?.id||null, createdAt:now() }));
  save(); return { ok:true, count:db.draws.length };
}

// ---------- http ----------
function send(res,status,body,headers={}){ res.writeHead(status,{'Content-Type':'text/html; charset=utf-8','X-Content-Type-Options':'nosniff',...headers}); res.end(body); }
function page(title,body){ return `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(title)}</title><style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;max-width:560px;margin:40px auto;padding:0 16px}.card{background:#1e293b;border-radius:16px;padding:24px}input,button{width:100%;padding:12px;border-radius:10px;border:1px solid #334155;margin:6px 0;font-size:16px;box-sizing:border-box}button{background:#22c55e;border:0;font-weight:700;cursor:pointer}.muted{color:#94a3b8;font-size:14px}.big{font-size:26px;text-align:center}</style></head><body><div class="card">${body}</div><footer style="text-align:center;margin-top:24px;color:#94a3b8;font-size:13px"><p>🎁 <b>Amigo Secreto Família Marcon</b></p><p>Desenvolvimento AASA TECNOLOGIA</p></footer></body></html>`; }
function readBody(req){ return new Promise(res=>{ let d=''; req.on('data',c=>{d+=c; if(d.length>1e5) req.destroy();}); req.on('end',()=>res(new URLSearchParams(d))); }); }
const ipOf = req => (req.headers['x-forwarded-for']||req.socket.remoteAddress||'unknown').split(',')[0].trim();
const byId = id => db.participants.find(p=>p.id===id);
const challengeById = id => db.challenges.find(c=>c.id===id);

// ---------- rotas ----------
const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://x');
  const sess = getSession(req);
  const isAdmin = sess && sess.type === 'admin';
  const isUser = sess && sess.type === 'user';

  // estáticos
  if (url.pathname.startsWith('/static/')) {
    const f = path.join(PUBLIC_DIR, decodeURIComponent(url.pathname.replace('/static/','')));
    if (!f.startsWith(PUBLIC_DIR) || !fs.existsSync(f) || !fs.statSync(f).isFile()) return send(res,404,'não encontrado');
    const ext = path.extname(f);
    const types = { '.css':'text/css', '.js':'text/javascript', '.png':'image/png', '.jpg':'image/jpeg', '.svg':'image/svg+xml' };
    res.writeHead(200,{'Content-Type':types[ext]||'application/octet-stream','Cache-Control':'public,max-age=3600'});
    return res.end(fs.readFileSync(f));
  }

  if (url.pathname === '/' && req.method === 'GET') {
    if (isAdmin) return send(res,302,'',{Location:'/admin'});
    if (isUser) return send(res,302,'',{Location:'/sorteio'});
    return send(res,200, page(BRAND, `<h1 class="big">🎁 Amigo Secreto Família Marcon</h1><p class="muted">Entre com seu código secreto (ex.: AB12-CD34) para ver seu amigo e seu desafio.</p><form method="POST" action="/login"><input name="code" placeholder="Código secreto" autocomplete="off" required><button>Entrar</button></form><p class="muted"><a style="color:#94a3b8" href="/admin">Sou o organizador →</a></p>`));
  }

  if (url.pathname === '/login' && req.method === 'POST') {
    if (rateLimited(ipOf(req))) return send(res,429, page('Bloqueado','<p>Muitas tentativas. Aguarde 15 minutos.</p>'));
    const b = await readBody(req);
    const code = (b.get('code')||'').trim().toUpperCase();
    const p = db.participants.find(x => verifySecret(code, x.codeHash));
    if (!p) return send(res,401, page('Erro','<p>Código inválido. Confira e tente novamente.</p><p><a href="/">← voltar</a></p>'));
    attempts.delete(ipOf(req));
    return send(res,302,'',{'Set-Cookie':cookieFor(newSession('user',p.id)), Location:'/sorteio'});
  }

  if (url.pathname === '/sorteio' && req.method === 'GET') {
    if (!isUser) return send(res,302,'',{Location:'/'});
    const me = byId(sess.participantId);
    if (!me) return send(res,302,'',{Location:'/'});
    const d = db.draws.find(x=>x.giverId===me.id);
    if (!d) return send(res,200, page(BRAND + ' — Aguardando','<h2>⏳ Sorteio ainda não realizado</h2><p class="muted">O organizador precisa liberar o sorteio. Volte em instantes!</p><form method="POST" action="/logout"><button>Sair</button></form>'));
    const receiver = byId(d.receiverId); const ch = challengeById(d.challengeId);
    return send(res,200, page(BRAND + ' — Seu amigo secreto', `<h2 class="big">🎉 ${esc(me.name)}, seu amigo secreto é:</h2><p class="big">${esc(receiver?receiver.name:'???')}</p>${ch?`<hr><h3>${esc(ch.emoji)} Seu desafio na entrega: ${esc(ch.label)}</h3><p>${esc(ch.desc)}</p>`:''}<p class="muted">Guarde segredo! Só revele na hora com o desafio. 🤫</p><form method="POST" action="/logout"><button>Sair</button></form>`));
  }

  if (url.pathname === '/logout' && req.method === 'POST') {
    const m=(req.headers.cookie||'').match(/session=([a-f0-9]+)/); if(m) sessions.delete(m[1]);
    return send(res,302,'',{'Set-Cookie':'session=; HttpOnly; Path=/; Max-Age=0','Location':'/'});
  }

  if (url.pathname === '/admin' && req.method === 'GET') {
    if (!isAdmin) return send(res,200, page('Organizador','<h2>🔐 Área do organizador</h2><form method="POST" action="/admin/login"><input type="password" name="pw" placeholder="Senha do organizador" required><button>Entrar</button></form>'));
    const list = db.participants.map(p=>`<li>${esc(p.name)} — código: <b>${esc(p.code)}</b></li>`).join('');
    const chs = db.challenges.map(c=>`<li>${esc(c.emoji)} ${esc(c.label)} — ${esc(c.desc)}</li>`).join('');
    return send(res,200, page('Admin', `<h2>🎉 Painel — Amigo Secreto Família Marcon</h2><p class="muted">Desenvolvimento AASA TECNOLOGIA</p><p class="muted">Gerado em: ${db.draws.length?new Date(db.draws[0].createdAt).toLocaleString('pt-BR'):'ainda não sorteado'}</p><form method="POST" action="/admin/draw"><button>🎲 Realizar sorteio agora</button></form><h3>Participantes (${db.participants.length})</h3><ul>${list}</ul><form method="POST" action="/admin/add"><input name="name" placeholder="Novo participante" required><button>+ Adicionar (gera novo código)</button></form><h3>Desafios (${db.challenges.length})</h3><ul>${chs}</ul><form method="POST" action="/admin/add-challenge"><input name="label" placeholder="Desafio (ex.: Karaokê)" required><input name="desc" placeholder="Descrição" required><button>+ Adicionar desafio</button></form><form method="POST" action="/logout"><button>Sair</button></form>`));
  }

  if (url.pathname === '/admin/login' && req.method === 'POST') {
    const b = await readBody(req);
    if (ADMIN_PASSWORD && (b.get('pw')||'') === ADMIN_PASSWORD) return send(res,302,'',{'Set-Cookie':cookieFor(newSession('admin')),'Location':'/admin'});
    return send(res,401, page('Erro','<p>Senha incorreta.</p><p><a href="/admin">← voltar</a></p>'));
  }
  if (url.pathname === '/admin/draw' && req.method === 'POST') { if(!isAdmin) return send(res,403,'proibido'); const r=doDraw(); if(r.error) return send(res,400,page('Erro',`<p>${esc(r.error)}</p><p><a href="/admin">← voltar</a></p>`)); return send(res,302,'',{Location:'/admin'}); }
  if (url.pathname === '/admin/add' && req.method === 'POST') { if(!isAdmin) return send(res,403,'proibido'); const b=await readBody(req); const name=(b.get('name')||'').trim(); if(name){ const code=genCode(); db.participants.push({id:uid(),name,codeHash:hashSecret(code),code}); save(); } return send(res,302,'',{Location:'/admin'}); }
  if (url.pathname === '/admin/add-challenge' && req.method === 'POST') { if(!isAdmin) return send(res,403,'proibido'); const b=await readBody(req); const label=(b.get('label')||'').trim(), desc=(b.get('desc')||'').trim(); if(label&&desc){ db.challenges.push({id:uid(),label,emoji:'🎲',desc}); save(); } return send(res,302,'',{Location:'/admin'}); }

  // fallback: espelha public/ (index.html etc.)
  const f = path.join(PUBLIC_DIR, url.pathname === '/' ? 'index.html' : decodeURIComponent(url.pathname));
  if (f.startsWith(PUBLIC_DIR) && fs.existsSync(f) && fs.statSync(f).isFile()) {
    res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'}); return res.end(fs.readFileSync(f));
  }
  return send(res,404, page('Não encontrado','<p>404</p><p><a href="/">← início</a></p>'));
});

server.listen(PORT, () => console.log(`[OK] Amigo Secreto Família Marcon no ar (AASA TECNOLOGIA) em http://localhost:${PORT}`));
