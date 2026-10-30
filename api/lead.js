// CITRIC.UZ — Vercel serverless funksiya: savat buyurtmalari va aloqa formasi arizalarini Telegram guruhiga yuboradi.
// Maxfiy sozlamalar Vercel muhit o'zgaruvchilarida: TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID (kodda saqlanmaydi).

const TITLES = { order: '🛒 <b>YANGI BUYURTMA</b>', form: '📝 <b>YANGI ARIZA</b>' };
const LANGS = { uz: "O'zbekcha", ru: 'Ruscha', en: 'Inglizcha' };

function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}
function clean(s, max) {
  return String(s == null ? '' : s).replace(/\s+/g, ' ').trim().slice(0, max);
}
function normPhone(v) {
  const d = String(v || '').replace(/\D/g, '');
  if (d.length !== 12 || !d.startsWith('998')) return null;
  return `+998 ${d.slice(3, 5)} ${d.slice(5, 8)} ${d.slice(8, 10)} ${d.slice(10, 12)}`;
}
function tashkentNow() {
  return new Date().toLocaleString('ru-RU', { timeZone: 'Asia/Tashkent', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function orderCode() {
  const d = new Date(Date.now() + 5 * 3600 * 1000); // Toshkent vaqti
  const ymd = d.toISOString().slice(2, 10).replace(/-/g, '');
  return `CU${ymd}-${Math.random().toString(36).slice(2, 7).toUpperCase()}`;
}
async function tg(token, method, body) {
  const r = await fetch(`https://api.telegram.org/bot${token}/${method}`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
  });
  return r.json().catch(() => ({ ok: false, description: `HTTP ${r.status}` }));
}

module.exports = async (req, res) => {
  const token = process.env.TELEGRAM_BOT_TOKEN;
  const chat = process.env.TELEGRAM_CHAT_ID;
  res.setHeader('Cache-Control', 'no-store');

  // Holatni tekshirish (xabar yubormaydi): /api/lead?check=1
  if (req.method === 'GET' && req.query && req.query.check) {
    if (!token || !chat) return res.status(200).json({ ok: false, configured: false });
    const c = await tg(token, 'getChat', { chat_id: chat });
    return res.status(200).json({ ok: !!c.ok, configured: true, chat: c.ok ? c.result.title || c.result.type : c.description });
  }
  if (req.method !== 'POST') return res.status(405).json({ ok: false, error: 'method' });
  if (!token || !chat) return res.status(500).json({ ok: false, error: 'not_configured' });

  let b = req.body || {};
  if (typeof b === 'string') { try { b = JSON.parse(b); } catch (e) { b = {}; } }

  // Bot tuzog'i: yashirin maydon to'ldirilgan bo'lsa — jim qabul qilinadi
  if (b.website) return res.status(200).json({ ok: true, id: 'CU-0' });

  const type = b.type === 'order' ? 'order' : 'form';
  const lang = ['uz', 'ru', 'en'].includes(b.lang) ? b.lang : 'uz';
  const name = clean(b.name, 60);
  const phone = normPhone(b.phone);
  if (name.length < 2) return res.status(400).json({ ok: false, error: 'name' });
  if (!phone) return res.status(400).json({ ok: false, error: 'phone' });

  const items = (Array.isArray(b.items) ? b.items : []).slice(0, 50).map((it) => ({
    name: clean(it && it.name, 120), code: clean(it && it.code, 20), pack: clean(it && it.pack, 30),
    qty: Math.max(1, Math.min(9999, parseInt(it && it.qty, 10) || 1)), img: /^https:\/\//.test(String(it && it.img)) ? String(it.img) : '',
  })).filter((it) => it.name);
  if (type === 'order' && !items.length) return res.status(400).json({ ok: false, error: 'items' });

  const id = orderCode();
  const lines = [`${TITLES[type]}  ·  <code>#${id}</code>`, '',
    `👤 <b>Ism:</b> ${esc(name)}`, `📞 <b>Telefon:</b> ${esc(phone)}`];
  const company = clean(b.company, 100);
  if (company) lines.push(`🏢 <b>Kompaniya:</b> ${esc(company)}`);

  if (items.length) {
    const total = items.reduce((s, it) => s + it.qty, 0);
    lines.push('', `📦 <b>Mahsulotlar (${items.length} ta, ${total} qadoq):</b>`);
    items.forEach((it, i) => {
      lines.push(`${i + 1}. ${esc(it.name)}${it.code ? ` (${esc(it.code)})` : ''} — <b>${it.qty}</b> × ${esc(it.pack || 'qadoq')}`);
    });
  }
  const product = clean(b.product_name, 150);
  if (product) lines.push('', `📦 <b>Qiziqtirgan mahsulot:</b> ${esc(product)}`);
  const note = clean(b.message || b.note, 1000);
  if (note) lines.push('', `💬 <b>Izoh:</b> ${esc(note)}`);
  lines.push('', `🌐 Sayt · ${type === 'order' ? 'Savat' : 'Aloqa formasi'} · ${LANGS[lang]} · ${tashkentNow()}`);
  const text = lines.join('\n');

  let r;
  const photo = items.find((it) => it.img);
  if (photo && text.length <= 1024) {
    r = await tg(token, 'sendPhoto', { chat_id: chat, photo: photo.img, caption: text, parse_mode: 'HTML' });
  }
  if (!r || !r.ok) {
    r = await tg(token, 'sendMessage', { chat_id: chat, text, parse_mode: 'HTML', disable_web_page_preview: true });
  }
  if (!r.ok) {
    console.error('Telegram:', r.description);
    return res.status(502).json({ ok: false, error: 'telegram' });
  }
  return res.status(200).json({ ok: true, id });
};
