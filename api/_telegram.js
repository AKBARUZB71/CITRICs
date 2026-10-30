// CITRIC.UZ — umumiy yordamchilar: telefon tekshiruvi va arizani Telegram guruhiga yuborish.
// Maxfiy sozlamalar Vercel muhit o'zgaruvchilarida: TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID.

const TITLES = { order: '🛒 <b>YANGI BUYURTMA</b>', form: '📝 <b>YANGI ARIZA</b>', chat: '🤖 <b>AI MASLAHATCHIDAN ARIZA</b>' };
const SOURCES = { order: 'Savat', form: 'Aloqa formasi', chat: 'AI maslahatchi' };
const LANGS = { uz: "O'zbekcha", ru: 'Ruscha', en: 'Inglizcha' };

function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}
function clean(s, max) {
  return String(s == null ? '' : s).replace(/\s+/g, ' ').trim().slice(0, max);
}
// +998 XX XXX XX XX; 9 xonali mahalliy raqam ham qabul qilinadi
function normPhone(v) {
  let d = String(v || '').replace(/\D/g, '');
  if (d.length === 9) d = '998' + d;
  if (d.length !== 12 || !d.startsWith('998')) return null;
  return `+998 ${d.slice(3, 5)} ${d.slice(5, 8)} ${d.slice(8, 10)} ${d.slice(10, 12)}`;
}
function tashkentNow() {
  return new Date().toLocaleString('ru-RU', { timeZone: 'Asia/Tashkent', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function leadCode() {
  const d = new Date(Date.now() + 5 * 3600 * 1000); // Toshkent vaqti
  return `CU${d.toISOString().slice(2, 10).replace(/-/g, '')}-${Math.random().toString(36).slice(2, 7).toUpperCase()}`;
}
async function tg(method, body) {
  const token = process.env.TELEGRAM_BOT_TOKEN;
  if (!token) return { ok: false, description: 'TELEGRAM_BOT_TOKEN yo\'q' };
  try {
    const r = await fetch(`https://api.telegram.org/bot${token}/${method}`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
    });
    return await r.json().catch(() => ({ ok: false, description: `HTTP ${r.status}` }));
  } catch (e) {
    return { ok: false, description: e.message };
  }
}

/** Ariza holatlari va har bir holatdagi tugmalar */
const STATUS = {
  confirmed: { icon: '✅', label: 'Qabul qilindi', answer: 'Ariza qabul qilindi ✅' },
  done:      { icon: '💰', label: 'Sotildi',       answer: 'Sotuv belgilandi 💰' },
  canceled:  { icon: '✖️', label: 'Bekor qilindi', answer: 'Ariza bekor qilindi' },
  new:       { icon: '↩️', label: 'Qayta ochildi', answer: 'Ariza qayta ochildi' },
};
function keyboard(status) {
  const b = (text, st) => ({ text, callback_data: `st:${st}` });
  const rows = {
    new:       [[b('✅ Qabul qilish', 'confirmed'), b('✖️ Bekor qilish', 'canceled')]],
    confirmed: [[b('💰 Sotildi', 'done'), b('✖️ Bekor qilish', 'canceled')]],
    done:      [[b('↩️ Qayta ochish', 'confirmed')]],
    canceled:  [[b('↩️ Qayta ochish', 'new')]],
  };
  return { inline_keyboard: rows[status] || [] };
}

/**
 * Arizani guruhga yuboradi.
 * lead: { type: order|form|chat, lang, name, phone (normallashgan), company, items:[{name,code,pack,qty,img}], product, volume, note }
 */
async function sendLead(lead) {
  const chat = process.env.TELEGRAM_CHAT_ID;
  if (!chat) return { ok: false, error: 'TELEGRAM_CHAT_ID yo\'q' };
  const id = leadCode();
  const items = lead.items || [];
  const lines = [`${TITLES[lead.type] || TITLES.form}  ·  <code>#${id}</code>`, '',
    `👤 <b>Ism:</b> ${esc(lead.name)}`, `📞 <b>Telefon:</b> ${esc(lead.phone)}`];
  if (lead.company) lines.push(`🏢 <b>Kompaniya:</b> ${esc(lead.company)}`);
  if (items.length) {
    const total = items.reduce((s, it) => s + (it.qty || 0), 0);
    lines.push('', total ? `📦 <b>Mahsulotlar (${items.length} ta, ${total} qadoq):</b>` : '📦 <b>Qiziqqan mahsulotlar:</b>');
    items.forEach((it, i) => {
      const tail = it.qty ? ` — <b>${it.qty}</b> × ${esc(it.pack || 'qadoq')}` : (it.pack ? ` — ${esc(it.pack)}` : '');
      lines.push(`${i + 1}. ${esc(it.name)}${it.code ? ` (${esc(it.code)})` : ''}${tail}`);
    });
  }
  if (lead.product) lines.push('', `📦 <b>Qiziqtirgan mahsulot:</b> ${esc(lead.product)}`);
  if (lead.volume) lines.push(`⚖️ <b>Hajm:</b> ${esc(lead.volume)}`);
  if (lead.note) lines.push('', `💬 <b>Izoh:</b> ${esc(lead.note)}`);
  lines.push('', `🌐 Sayt · ${SOURCES[lead.type] || 'Sayt'} · ${LANGS[lead.lang] || LANGS.uz} · ${tashkentNow()}`);
  const text = lines.join('\n');

  let r = { ok: false };
  const reply_markup = keyboard('new');
  const photo = items.find((it) => it.img);
  if (photo && text.length <= 1024) {
    r = await tg('sendPhoto', { chat_id: chat, photo: photo.img, caption: text, parse_mode: 'HTML', reply_markup });
  }
  if (!r.ok) r = await tg('sendMessage', { chat_id: chat, text, parse_mode: 'HTML', disable_web_page_preview: true, reply_markup });
  if (!r.ok) console.error('Telegram:', r.description);
  return r.ok ? { ok: true, id } : { ok: false, error: r.description };
}

module.exports = { esc, clean, normPhone, sendLead, tg, keyboard, STATUS, tashkentNow };
