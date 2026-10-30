// CITRIC.UZ — savat buyurtmalari va aloqa formasi arizalarini Telegram guruhiga yuboradi.
const { clean, normPhone, sendLead, tg } = require('./_telegram');

module.exports = async (req, res) => {
  res.setHeader('Cache-Control', 'no-store');

  // Holatni tekshirish (xabar yubormaydi): /api/lead?check=1
  if (req.method === 'GET' && req.query && req.query.check) {
    const chat = process.env.TELEGRAM_CHAT_ID;
    const c = chat ? await tg('getChat', { chat_id: chat }) : { ok: false, description: 'TELEGRAM_CHAT_ID yo\'q' };
    return res.status(200).json({ ok: !!c.ok, chat: c.ok ? c.result.title || c.result.type : c.description, gemini: !!process.env.GEMINI_API_KEY });
  }
  if (req.method !== 'POST') return res.status(405).json({ ok: false, error: 'method' });

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
    qty: Math.max(1, Math.min(9999, parseInt(it && it.qty, 10) || 1)),
    img: /^https:\/\//.test(String(it && it.img)) ? String(it.img) : '',
  })).filter((it) => it.name);
  if (type === 'order' && !items.length) return res.status(400).json({ ok: false, error: 'items' });

  const r = await sendLead({
    type, lang, name, phone, items,
    company: clean(b.company, 100), product: clean(b.product_name, 150), note: clean(b.message || b.note, 1000),
  });
  return r.ok ? res.status(200).json({ ok: true, id: r.id }) : res.status(502).json({ ok: false, error: 'telegram' });
};
