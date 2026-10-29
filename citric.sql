-- ============================================================
-- CITRIC.UZ ma'lumotlar bazasi (MySQL / MariaDB)
-- phpMyAdmin: bazani tanlang -> «Import» -> shu faylni yuklang -> «Go».
-- Eslatma: index.php birinchi ochilganda jadvallarni o'zi ham yaratadi;
-- bu fayl phpMyAdmin orqali qo'lda o'rnatish uchun.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+05:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(60) NOT NULL PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name_uz VARCHAR(190) NOT NULL,
  name_ru VARCHAR(190) NOT NULL DEFAULT '',
  desc_uz TEXT NULL,
  desc_ru TEXT NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'other',
  code VARCHAR(30) NOT NULL DEFAULT '',
  pack VARCHAR(50) NOT NULL DEFAULT '',
  origin_uz VARCHAR(60) NOT NULL DEFAULT '',
  origin_ru VARCHAR(60) NOT NULL DEFAULT '',
  price DECIMAL(14,2) NULL,
  price_unit VARCHAR(30) NOT NULL DEFAULT '',
  image VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  source VARCHAR(10) NOT NULL,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  company VARCHAR(150) NOT NULL DEFAULT '',
  lang CHAR(2) NOT NULL DEFAULT 'uz',
  volume VARCHAR(100) NOT NULL DEFAULT '',
  note TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'new',
  confirmed_by VARCHAR(150) NULL,
  confirmed_at DATETIME NULL,
  tg_status VARCHAR(255) NULL,
  session VARCHAR(40) NULL,
  tg_text TEXT NULL,
  tg_msg_id BIGINT NULL,
  status_by VARCHAR(150) NULL,
  status_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_code (code),
  KEY idx_created (created_at),
  KEY idx_status (status),
  KEY idx_session (session)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(190) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  KEY idx_product (product_id),
  CONSTRAINT fk_items_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  type VARCHAR(10) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_product_type (product_id, type),
  KEY idx_created (created_at),
  CONSTRAINT fk_events_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL DEFAULT '',
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_hits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(20) NOT NULL,
  ip VARCHAR(64) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_lookup (bucket, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eski 10 ta namuna mahsulot (bo'lsa) olib tashlanadi
DELETE FROM products WHERE image LIKE 'assets/products/%';

-- Mahsulotlar katalogi (43 ta). Rasm manzili bo'yicha takrorlanmaydi.
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Trinatriy sitrat','Тринатрий цитрат','Kislotalik regulyatori, stabilizator va antioksidant sinergisti. Ichimliklar, sut mahsulotlari, eritilgan pishloq va farmatsevtikada ishlatiladi.','Регулятор кислотности, стабилизатор и синергист антиоксидантов. Напитки, молочная продукция, плавленые сыры, фармацевтика.','acid','E331','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/01-trinatriy-sitrat.webp','1','1','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/01-trinatriy-sitrat.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Natriy glutamat','Глутамат натрия','Ta\'m kuchaytiruvchi. Kolbasa, souslar, ziravorlar, chipslar va tayyor taomlar ishlab chiqarishda keng qo\'llaniladi.','Усилитель вкуса. Колбасы, соусы, приправы, чипсы и готовые блюда.','flavor','E621','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/02-natriy-glutamat.webp','1','2','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/02-natriy-glutamat.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Agar-agar','Агар-агар','Tabiiy quyuqlashtiruvchi va jele hosil qiluvchi. Marmelad, zefir, sufle, desert va qandolatchilik mahsulotlari uchun.','Натуральный загуститель и желирующий агент. Мармелад, зефир, суфле, десерты и кондитерские изделия.','thick','E406','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/03-agar-agar.webp','1','3','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/03-agar-agar.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Glitserin','Глицерин','Namlik ushlagich va erituvchi. Oziq-ovqat, farmatsevtika, kosmetika va maishiy kimyo mahsulotlarida ishlatiladi.','Влагоудерживающий агент и растворитель. Пищевая промышленность, фармацевтика, косметика и бытовая химия.','other','E422','Bochka','Malayziya','Малайзия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/04-glitserin.webp','1','4','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/04-glitserin.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Suvsiz limon kislotasi','Лимонная кислота безводная','Kislotalik regulyatori va antioksidant. Ichimliklar, sharbatlar, qandolatchilik, konservalash va sut mahsulotlari uchun.','Регулятор кислотности и антиоксидант. Напитки, соки, кондитерские изделия, консервация и молочная продукция.','acid','E330','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/05-limon-kislotasi-anhidrid.webp','1','5','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/05-limon-kislotasi-anhidrid.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Kaustik soda granulalangan 99%','Каустическая сода гранулированная 99%','Natriy gidroksidi (NaOH). Sovun, yuvish vositalari, tozalash, kimyo va to\'qimachilik sanoati uchun.','Гидроксид натрия (NaOH). Мыловарение, моющие средства, очистка, химическая и текстильная промышленность.','tech','','25 kg','Rossiya','Россия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/06-kaustik-soda-granula.webp','1','6','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/06-kaustik-soda-granula.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Atsesulfam K','Ацесульфам K','Intensiv shirinlashtiruvchi (shakardan ~200 marta shirin). Shakarsiz ichimliklar, saqich va dietik mahsulotlar uchun.','Интенсивный подсластитель (~в 200 раз слаще сахара). Напитки без сахара, жевательная резинка, диетические продукты.','sweet','E950','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/07-atsesulfam-k.webp','1','7','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/07-atsesulfam-k.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Maltodekstrin','Мальтодекстрин','Universal ingrediyent: to\'ldiruvchi va tekstura beruvchi. Yogurt, muzqaymoq, quruq aralashmalar va sport ozuqasi uchun.','Универсальный ингредиент: наполнитель и текстуратор. Йогурты, мороженое, сухие смеси и спортивное питание.','thick','','25 kg','Rossiya','Россия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/08-maltodekstrin.webp','1','8','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/08-maltodekstrin.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Natriy perkarbonat','Перкарбонат натрия','Kislorodli oqartiruvchi. Kir yuvish kukunlari, dog\' ketkazgichlar va tozalash vositalari ishlab chiqarish uchun.','Кислородный отбеливатель. Стиральные порошки, пятновыводители и чистящие средства.','tech','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/09-natriy-perkarbonat.webp','1','9','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/09-natriy-perkarbonat.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Makkajo\'xori kraxmali «Rustark»','Кукурузный крахмал «Рустарк»','Oliy navli makkajo\'xori kraxmali. Souslar, qandolatchilik, non va go\'sht mahsulotlarida quyuqlashtiruvchi sifatida.','Кукурузный крахмал высшего сорта. Загуститель для соусов, кондитерских, хлебобулочных и мясных изделий.','thick','','25 kg','Rossiya','Россия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/10-makkajoxori-kraxmali-rustark.webp','1','10','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/10-makkajoxori-kraxmali-rustark.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Fenolftalein','Фенолфталеин','Kislota-asos indikatori. Laboratoriyalar va ishlab chiqarishda pH nazorati hamda titrlash uchun.','Кислотно-основный индикатор. Контроль pH и титрование в лабораториях и на производстве.','tech','','Baraban','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/11-fenolftalein.webp','1','11','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/11-fenolftalein.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Aspartam','Аспартам','Shakarning ishonchli o\'rnini bosuvchi intensiv shirinlashtiruvchi. Ichimliklar, saqich va farmatsevtika uchun.','Надёжный заменитель сахара, интенсивный подсластитель. Напитки, жевательная резинка и фармацевтика.','sweet','E951','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/12-aspartam.webp','1','12','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/12-aspartam.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Ribonukleotid (I+G)','Рибонуклеотид (I+G)','Ta\'m kuchaytiruvchi, natriy glutamat bilan birga sinergik ta\'sir beradi. Chips, ziravorlar, sho\'rva va kolbasalar uchun.','Усилитель вкуса, синергист глутамата натрия. Чипсы, приправы, супы и колбасы.','flavor','E635','10 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/13-ribonukleotid-ig.webp','1','13','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/13-ribonukleotid-ig.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'SLES 70% (natriy lauril efir sulfat)','SLES 70% (лаурет сульфат натрия)','Ko\'pik hosil qiluvchi sirt-faol modda. Shampun, suyuq sovun, idish yuvish va kir yuvish vositalari uchun.','Пенообразующее ПАВ. Шампуни, жидкое мыло, средства для посуды и стирки.','tech','','170 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/14-sles-70.webp','1','14','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/14-sles-70.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Kaliy sorbat','Сорбат калия','Oziq-ovqat konservanti: mog\'or va achitqilarga qarshi. Ichimliklar, qandolatchilik, pishloq va souslar uchun.','Пищевой консервант против плесени и дрожжей. Напитки, кондитерские изделия, сыры и соусы.','preserv','E202','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/15-kaliy-sorbat.webp','1','15','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/15-kaliy-sorbat.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Sorbitol kukuni','Сорбитол порошок','Shakar o\'rnini bosuvchi va namlik ushlagich. Diabetik mahsulotlar, saqich, qandolatchilik va farmatsevtika uchun.','Заменитель сахара и влагоудерживающий агент. Диабетические продукты, жевательная резинка, кондитерка, фармацевтика.','sweet','E420','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/16-sorbitol-kukuni.webp','1','16','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/16-sorbitol-kukuni.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Agar (oziq-ovqat)','Агар пищевой','Tabiiy quyuqlashtiruvchi. Marmelad, jele, desert va qandolatchilik mahsulotlarida barqaror tekstura beradi.','Натуральный загуститель. Стабильная текстура мармелада, желе, десертов и кондитерских изделий.','thick','E406','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/17-agar-oziq-ovqat.webp','1','17','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/17-agar-oziq-ovqat.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Qandolatchilik mak (ko\'knori urug\'i)','Мак кондитерский','Bulochka, rulet va pishiriqlar uchun saralangan oziq-ovqat maki.','Отборный пищевой мак для булочек, рулетов и выпечки.','other','','25 kg','Chexiya','Чехия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/18-qandolatchilik-mak.webp','1','18','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/18-qandolatchilik-mak.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Kakao kukuni','Какао-порошок','Boy shokolad ta\'mi va to\'q rang. Shokolad, konditer mahsulotlari, pishiriqlar va ichimliklar uchun.','Насыщенный шоколадный вкус и цвет. Шоколад, кондитерские изделия, выпечка и напитки.','flavor','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/19-kakao-kukuni.webp','1','19','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/19-kakao-kukuni.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Makkajo\'xori kraxmali «Cargill»','Кукурузный крахмал «Cargill»','Oliy navli kraxmal (GOST 32159-2013). Oziq-ovqat, to\'qimachilik va quruq qurilish aralashmalari sanoati uchun.','Крахмал высшего сорта (ГОСТ 32159-2013). Пищевая, текстильная промышленность и сухие строительные смеси.','thick','','25 kg','Rossiya','Россия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/20-makkajoxori-kraxmali-cargill.webp','1','20','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/20-makkajoxori-kraxmali-cargill.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Ichimlik sodasi (natriy bikarbonat)','Пищевая сода (бикарбонат натрия)','Qabartiruvchi va kislotalik regulyatori. Non, pechenye, qandolatchilik va ichimliklar ishlab chiqarish uchun.','Разрыхлитель и регулятор кислотности. Хлеб, печенье, кондитерские изделия и напитки.','other','E500','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/21-ichimlik-sodasi.webp','1','21','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/21-ichimlik-sodasi.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Maltodekstrin (Rossiya)','Мальтодекстрин (Россия)','Yogurt va muzqaymoq ishlab chiqarish uchun ideal mahsulot: tana va barqaror tekstura beradi.','Идеальный продукт для йогуртов и мороженого: придаёт тело и стабильную текстуру.','thick','','25 kg','Rossiya','Россия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/22-maltodekstrin-2.webp','1','22','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/22-maltodekstrin-2.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Tokoferol (Vitamin E)','Токоферол (Витамин E)','Kuchli tabiiy antioksidant. Yog\'lar, chips, margarin va kosmetika mahsulotlarining saqlanish muddatini uzaytiradi.','Мощный натуральный антиоксидант. Продлевает срок хранения масел, чипсов, маргарина и косметики.','preserv','E306','Bochka','Ispaniya','Испания',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/23-tokoferol-vitamin-e.webp','1','23','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/23-tokoferol-vitamin-e.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'EDTA-4Na (Trilon B)','EDTA-4Na (Трилон Б)','Suvni yumshatuvchi va metall ionlarini bog\'lovchi stabilizator. Yuvish vositalari va texnik jarayonlar uchun.','Умягчитель воды и комплексообразователь. Моющие средства и технологические процессы.','tech','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/24-edta-4na.webp','1','24','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/24-edta-4na.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'AOS 92% (alfa-olefin sulfonat)','AOS 92% (альфа-олефинсульфонат)','Kuchli ko\'pik hosil qiluvchi komponent. Kir yuvish kukunlari, shampun va tozalash vositalari uchun.','Сильный пенообразующий компонент. Стиральные порошки, шампуни и чистящие средства.','tech','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/25-aos-92.webp','1','25','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/25-aos-92.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Dekstroza monogidrat','Декстроза моногидрат','Yuqori sifatli glyukoza. Qandolatchilik, ichimliklar, non va farmatsevtika mahsulotlarida shirinlik va energiya manbai.','Глюкоза высокого качества. Кондитерские изделия, напитки, хлеб и фармацевтика.','sweet','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/26-dekstroza-monogidrat.webp','1','26','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/26-dekstroza-monogidrat.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Gummi arabik','Гуммиарабик','Konfet va saqich uchun quyuqlashtiruvchi va yaltiroq qoplama beruvchi tabiiy smola. Ichimlik emulsiyalarida ham ishlatiladi.','Натуральная камедь: загуститель и глазирователь для конфет и жевательной резинки, эмульсии для напитков.','thick','E414','25 kg','Fransiya','Франция',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/27-gummi-arabik.webp','1','27','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/27-gummi-arabik.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Karamel kolor','Карамельный колер','To\'yingan shokolad-jigarrang rang beruvchi tabiiy bo\'yoq. Ichimliklar, qandolatchilik va souslar uchun.','Насыщенный шоколадно-коричневый краситель. Напитки, кондитерские изделия и соусы.','flavor','E150','Kanistr','Import','Импорт',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/28-karamel-kolor.webp','1','28','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/28-karamel-kolor.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Kaustik soda tangacha 98%','Каустическая сода чешуированная 98%','Natriy gidroksidi (flakes). Sovun, yuvish vositalari, tozalash va kimyo sanoati uchun.','Гидроксид натрия (чешуйки). Мыловарение, моющие средства, очистка и химическая промышленность.','tech','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/29-kaustik-soda-tangacha.webp','1','29','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/29-kaustik-soda-tangacha.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Limon kislotasi monogidrat','Лимонная кислота моногидрат','Kislotalik regulyatori. Ichimliklar, qandolatchilik, konservalash va farmatsevtika uchun.','Регулятор кислотности. Напитки, кондитерские изделия, консервация и фармацевтика.','acid','E330','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/30-limon-kislotasi-monogidrat.webp','1','30','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/30-limon-kislotasi-monogidrat.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Limon kislotasi monogidrat (Made in China)','Лимонная кислота моногидрат (Made in China)','Kislotalik regulyatori va antioksidant. Ichimliklar, sharbatlar, qandolatchilik va sut mahsulotlari uchun.','Регулятор кислотности и антиоксидант. Напитки, соки, кондитерские изделия и молочная продукция.','acid','E330','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/31-limon-kislotasi-monogidrat-2.webp','1','31','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/31-limon-kislotasi-monogidrat-2.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Mentol','Ментол','Sovutuvchi ta\'m va aroma beruvchi modda. Saqich, farmatsevtika, kosmetika va konfetlar uchun.','Охлаждающий вкус и аромат. Жевательная резинка, фармацевтика, косметика и конфеты.','flavor','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/32-mentol.webp','1','32','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/32-mentol.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'OEDF (HEDP)','ОЭДФ (HEDP)','Kuchli tozalovchi va kompleks hosil qiluvchi komponent. Suvni tayyorlash, yuvish vositalari va texnik tozalash uchun.','Сильный комплексообразователь. Водоподготовка, моющие средства и техническая очистка.','tech','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/33-oedf.webp','1','33','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/33-oedf.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Letsitin LASENOR','Лецитин LASENOR','Funksional emulgator. Shokolad, margarin, pishiriqlar va qandolatchilik mahsulotlarining barqaror sifatini ta\'minlaydi.','Функциональный эмульгатор для стабильного качества шоколада, маргарина, выпечки и кондитерских изделий.','other','E322','200 kg','Rossiya','Россия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/34-letsitin-lasenor.webp','1','34','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/34-letsitin-lasenor.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'ROKO Agar','ROKO Агар','Ispaniyaning yuqori sifatli agar quyuqlashtiruvchisi. Marmelad, desert va sut mahsulotlari uchun.','Высококачественный испанский агар-загуститель для мармелада, десертов и молочной продукции.','thick','E406','25 kg','Ispaniya','Испания',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/35-roko-agar.webp','1','35','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/35-roko-agar.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'SAPP 28 (natriy pirofosfat)','SAPP 28 (пирофосфат натрия)','Qandolatchilik va non ishlab chiqarish uchun qabartiruvchi. Pechenye, keks va pishiriqlar uchun.','Разрыхлитель для кондитерского и хлебопекарного производства: печенье, кексы, выпечка.','other','E450','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/36-sapp-28.webp','1','36','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/36-sapp-28.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Atsesulfam K (JinHeSweet)','Ацесульфам K (JinHeSweet)','Intensiv shirinlashtiruvchi. Ichimliklar, saqich va shakarsiz mahsulotlar uchun.','Интенсивный подсластитель для напитков, жевательной резинки и продуктов без сахара.','sweet','E950','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/37-atsesulfam-k-2.webp','1','37','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/37-atsesulfam-k-2.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Natriy benzoat','Бензоат натрия','Ichimliklar uchun ishonchli konservant. Kislotali muhitda samarali: sharbatlar, gazli ichimliklar, souslar.','Надёжный консервант для напитков. Эффективен в кислой среде: соки, газированные напитки, соусы.','preserv','E211','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/38-natriy-benzoat.webp','1','38','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/38-natriy-benzoat.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Xantal kukuni «Gorlinka»','Горчичный порошок «Горлинка»','Oziq-ovqat sanoati uchun tabiiy ingrediyent. Xantal, souslar, mayonez va ziravorlar ishlab chiqarish uchun.','Натуральный ингредиент для производства горчицы, соусов, майонеза и приправ.','flavor','','20 kg','Rossiya','Россия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/39-xantal-kukuni.webp','1','39','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/39-xantal-kukuni.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Vanilin flavor','Ванилин flavor','Yoqimli aroma va shirin ta\'m. Qandolatchilik, pishiriqlar, shokolad va muzqaymoq uchun.','Приятный аромат и сладкий вкус. Кондитерские изделия, выпечка, шоколад и мороженое.','flavor','','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/40-vanilin.webp','1','40','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/40-vanilin.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Askorbin kislotasi (Vitamin C)','Аскорбиновая кислота (Витамин C)','Antioksidant va vitamin C manbai. Sharbatlar, ichimliklar, non va go\'sht mahsulotlari uchun.','Антиоксидант и источник витамина C. Соки, напитки, хлеб и мясная продукция.','acid','E300','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/41-askorbin-kislotasi.webp','1','41','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/41-askorbin-kislotasi.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Kraxmal patokasi (glyukoza siropi)','Патока крахмальная','Qandolatchilik, muzqaymoq va ichimliklar uchun shirinlashtiruvchi va kristallanishga qarshi komponent.','Подсластитель и антикристаллизатор для кондитерских изделий, мороженого и напитков.','sweet','','280 kg','Rossiya','Россия',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/42-kraxmal-patokasi.webp','1','42','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/42-kraxmal-patokasi.webp');
INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,created_at,updated_at)
  SELECT 'Askorbin kislotasi (Витамин C)','Аскорбиновая кислота (Витамин C)','Vitamin C, antioksidant. Ichimliklarni boyitish, rangni saqlash va un sifatini yaxshilash uchun.','Витамин C, антиоксидант. Обогащение напитков, стабилизация цвета, улучшение муки.','acid','E300','25 kg','Xitoy','Китай',NULL,'so\'m/kg','https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/43-askorbin-kislotasi-2.webp','1','43','2026-10-20 18:49:19','2026-10-20 18:49:19' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE image = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/mahsulotlar/43-askorbin-kislotasi-2.webp');

-- Birinchi administrator (login va parol: index.php -> admin_login / admin_password). Bor bo'lsa — o'tkazib yuboriladi.
-- Administrator jadvalga avtomatik qo'shiladi: sayt birinchi ochilganda config.php dagi admin_login / admin_password bilan.

INSERT INTO settings (k, v) VALUES ('schema_version', '4') ON DUPLICATE KEY UPDATE v = VALUES(v);

SET FOREIGN_KEY_CHECKS = 1;
