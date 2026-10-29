# CITRIC.UZ — oziq-ovqat ingrediyentlari sayti

Sayt, AI maslahatchi, Telegram bot va admin panel. PHP + MySQL, bitta faylli tuzilma.

## Imkoniyatlar

- **Sayt** — katalog (43 mahsulot, kategoriyalar), to'liq mahsulot sahifasi, savat, aloqa formasi, sertifikatlar, mijozlar, video, Yandex xarita. O'zbek va rus tillari.
- **AI maslahatchi** — Google Gemini. Faqat CITRIC mahsulotlari bo'yicha javob beradi, mos mahsulotni tavsiya qiladi, ism va telefonni olib arizani Telegramga yuboradi.
- **Telegram bot** — har bir ariza mahsulot rasmi bilan guruhga keladi. Tugmalar: ✅ Tasdiqlandi, 💰 Sotildi, ✖️ Bekor qilish, ↩️ Qayta ochish.
  Buyruqlar: `/hisobot`, `/kecha`, `/hafta`, `/oy`, `/yangi`, `/yordam`.
- **Kunlik PDF hisobot** — har kuni 20:00 da guruhga: arizalar, manbalar, grafik, top mahsulotlar, arizalar ro'yxati.
- **Admin panel** — statistika, mahsulot tahlili, mahsulotlar (qo'shish/tahrirlash/rasm yuklash — katta rasmlar avtomatik siqiladi), arizalar (holat, qidiruv, CSV eksport), sozlamalar.
- **Himoya** — nusxa olish, o'ng tugma, kodni ko'rish va rasm/videoni yuklab olish cheklangan; JS kodi berkitilgan.

## Fayllar

| Fayl | Vazifasi |
|---|---|
| `index.php` | Sayt + API (`?api=chat\|lead\|track\|telegram`) + bot + hisobotlar |
| `admin.php` | Boshqaruv paneli |
| `citric.sql` | MySQL jadvallari va 43 mahsulot (phpMyAdmin → Import) |
| `config.example.php` | Maxfiy sozlamalar namunasi |
| `.src/` | Sayt JS va mahsulot sahifasi CSS manbasi + yig'ish skriptlari |

Rasmlar va video [AKBARUZB71/CITRIC](https://github.com/AKBARUZB71/CITRIC) ommaviy reposidan jsDelivr CDN orqali yuklanadi.

## O'rnatish (hosting)

1. `index.php`, `admin.php` ni hostingga yuklang.
2. `config.example.php` → `config.php` nusxasini yarating va to'ldiring: MySQL, Telegram token, Gemini kaliti, admin paroli. **`config.php` GitHubga yuklanmaydi.**
3. phpMyAdmin → `citric.sql` ni import qiling (yoki sayt birinchi ochilganda jadvallar o'zi yaratiladi).
4. `admin.php` ga kiring → **Sozlamalar → Webhookni ulash** (bot tugmalari va buyruqlari uchun).
5. (Ixtiyoriy) Hosting cron: Sozlamalardagi havolani har kuni 20:00 da chaqirish.

Talablar: PHP 7.4+ (8.2+ tavsiya), `pdo_mysql`, `curl`, `mbstring`, `zlib`.

## Lokal ishga tushirish

```bash
php -d extension=pdo_sqlite -S localhost:5501
```

Lokalda SQLite (`citric-local.sqlite`) avtomatik ishlatiladi.

## JS ni o'zgartirish

Sayt JS kodi `index.php` ichida shifrlangan holda turadi. O'zgartirish uchun:

```bash
php .src/unpack.php   # index.php → .src/main.js
php .src/pack.php     # .src/main.js → index.php
php .src/css.php      # .src/product-modal.css → index.php
```
