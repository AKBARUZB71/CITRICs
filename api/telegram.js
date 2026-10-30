// CITRIC.UZ — Telegram webhook: ariza xabaridagi tugmalar (✅ Qabul qilish, 💰 Sotildi, ✖️ Bekor qilish, ↩️ Qayta ochish).
// Tugma bosilganda xabarning o'ziga "✅ Qabul qilindi — @username · vaqt" qatori qo'shiladi va tugmalar keyingi bosqichga almashadi.
// Holat xabarning o'zida saqlanadi (baza kerak emas). Himoya: TELEGRAM_WEBHOOK_SECRET va faqat operatorlar guruhi.
const { tg, keyboard, STATUS, tashkentNow } = require('./_telegram');

// Xabardagi oxirgi holat (qo'shilgan qatorlar bo'yicha)
function currentStatus(text) {
  let st = 'new';
  String(text || '').split('\n').forEach((line) => {
    for (const [key, s] of Object.entries(STATUS)) {
      if (line.startsWith(`${s.icon} ${s.label} — `)) st = key;
    }
  });
  return st;
}

module.exports = async (req, res) => {
  if (req.method !== 'POST') return res.status(405).end();
  const secret = process.env.TELEGRAM_WEBHOOK_SECRET;
  if (!secret || req.headers['x-telegram-bot-api-secret-token'] !== secret) return res.status(401).end();

  let u = req.body || {};
  if (typeof u === 'string') { try { u = JSON.parse(u); } catch (e) { u = {}; } }
  const cq = u.callback_query;
  if (!cq) return res.status(200).json({ ok: true });

  const answer = (text, alert) => tg('answerCallbackQuery', { callback_query_id: cq.id, text, show_alert: !!alert });
  const msg = cq.message;
  const m = /^st:(new|confirmed|done|canceled)$/.exec(String(cq.data || ''));
  if (!msg || !m || String(msg.chat.id) !== String(process.env.TELEGRAM_CHAT_ID)) {
    await answer("Bu tugma faol emas", true);
    return res.status(200).json({ ok: true });
  }

  const isCaption = typeof msg.caption === 'string';
  const text = isCaption ? msg.caption : String(msg.text || '');
  const entities = ((isCaption ? msg.caption_entities : msg.entities) || []).slice();
  const status = m[1];
  if (currentStatus(text) === status) {
    await answer(`Holat allaqachon: ${STATUS[status].label}`, true);
    return res.status(200).json({ ok: true });
  }

  // Kim bosdi: @username yoki ism (ism bo'lsa — profilga havola)
  const f = cq.from || {};
  const who = f.username ? `@${f.username}` : (`${f.first_name || ''} ${f.last_name || ''}`.trim() || 'Operator');
  const s = STATUS[status];
  const hasLog = String(text).split('\n').some((ln) => Object.values(STATUS).some((x) => ln.startsWith(`${x.icon} ${x.label} — `)));
  const prefix = hasLog ? '\n' : '\n\n';
  const head = `${s.icon} ${s.label} — `;
  const line = `${head}${who} · ${tashkentNow()}`;
  const newText = text + prefix + line;

  // Yangi qator uchun formatlash (offsetlar UTF-16 birliklarida — JS satr uzunligi bilan mos)
  const start = text.length + prefix.length;
  entities.push({ type: 'bold', offset: start, length: head.length - 3 });
  if (f.username) entities.push({ type: 'mention', offset: start + head.length, length: who.length });
  else entities.push({ type: 'text_mention', offset: start + head.length, length: who.length, user: f });

  const base = { chat_id: msg.chat.id, message_id: msg.message_id, reply_markup: keyboard(status) };
  let r;
  if (isCaption && newText.length <= 1024) {
    r = await tg('editMessageCaption', { ...base, caption: newText, caption_entities: entities });
  } else if (!isCaption && newText.length <= 4096) {
    r = await tg('editMessageText', { ...base, text: newText, entities, link_preview_options: { is_disabled: true } });
  } else {
    // Matn juda uzun — tugmalarni yangilab, holatni javob xabari bilan yozamiz
    r = await tg('editMessageReplyMarkup', base);
    if (r.ok) await tg('sendMessage', { chat_id: msg.chat.id, text: line, reply_parameters: { message_id: msg.message_id } });
  }
  if (!r.ok) {
    console.error('Telegram edit:', r.description);
    await answer("Holatni yangilab bo'lmadi, qayta urinib ko'ring", true);
  } else {
    await answer(s.answer);
  }
  return res.status(200).json({ ok: true });
};
