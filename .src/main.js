
(function () {
  'use strict';
  (function protect() {
    function editable(el) {
      return !!(el && el.closest && el.closest('input, textarea, select, [contenteditable="true"]'));
    }
    function stop(e) { if (!editable(e.target)) { e.preventDefault(); return false; } }
    ['contextmenu', 'copy', 'cut', 'selectstart', 'dragstart'].forEach(function (ev) {
      document.addEventListener(ev, stop, true);
    });
    document.addEventListener('keydown', function (e) {
      var k = (e.key || '').toLowerCase(), mod = e.ctrlKey || e.metaKey;
      var blocked = e.key === 'F12'
        || (mod && (k === 'u' || k === 's' || k === 'p'))                         // kod, saqlash, chop etish
        || (mod && e.shiftKey && (k === 'i' || k === 'j' || k === 'c' || k === 'k')) // dasturchi oynasi
        || (e.metaKey && e.altKey && (k === 'i' || k === 'j' || k === 'c' || k === 'u'))
        || (mod && !editable(e.target) && (k === 'c' || k === 'x' || k === 'a'));
      if (blocked) { e.preventDefault(); e.stopPropagation(); }
    }, true);
    function lock(root) {
      Array.prototype.forEach.call((root || document).querySelectorAll('img, video, a'), function (el) {
        el.setAttribute('draggable', 'false');
        if (el.tagName === 'VIDEO') {
          el.setAttribute('controlslist', 'nodownload noplaybackrate noremoteplayback');
          el.disablePictureInPicture = true;
        }
      });
    }
    lock();
    new MutationObserver(function (list) {
      list.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) lock(n.parentNode || n); }); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  })();
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function store(kind, key, val) {
    try {
      var s = kind === 'session' ? sessionStorage : localStorage;
      if (val === undefined) return JSON.parse(s.getItem(key));
      s.setItem(key, JSON.stringify(val));
    } catch (e) { return null; }
  }
  function post(url, data) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { var e = new Error(r.status); e.data = j; throw e; } return j; }); });
  }
  var ASSETS = window.CITRIC_ASSETS || 'assets/';
  // Boshqa domendagi nusxa (Vercel): API va sertifikatlar asosiy hostingdan olinadi
  var API = window.CITRIC_API || '';
  var SITE = window.CITRIC_SITE || '';
  document.addEventListener('error', function (e) {
    var img = e.target;
    if (img.tagName === 'IMG' && img.getAttribute('data-fallback') && img.src.indexOf(img.getAttribute('data-fallback')) === -1) {
      img.src = img.getAttribute('data-fallback');
    }
  }, true);
  var CERT_FILES = ['sertfikat1.webp', 'serfikat2.webp', 'sertfikat3.webp', 'sertfikat4.webp', 'serfikat5.webp', 'sertfikat6.webp'];
  function certSrc(i) { return SITE + 'rasmlar/' + CERT_FILES[i]; }
  function certFallback(i) { return ASSETS + 'certificates/cert-' + (i + 1) + '.webp'; }
  var CERTS = [
    { type: { uz: 'Ekologik sertifikat', ru: 'Экологический сертификат', en: 'Environmental certificate' },
      name: { uz: 'Natriy benzoat — 16 000 kg', ru: 'Бензоат натрия — 16 000 кг', en: 'Sodium benzoate — 16,000 kg' } },
    { type: { uz: 'Sanitariya-epidemiologik xulosa', ru: 'Санитарно-эпидемиологическое заключение', en: 'Sanitary-epidemiological conclusion' },
      name: { uz: 'Makkajo\'xori kraxmali', ru: 'Крахмал кукурузный', en: 'Corn starch' } },
    { type: { uz: 'Sanitariya-epidemiologik xulosa', ru: 'Санитарно-эпидемиологическое заключение', en: 'Sanitary-epidemiological conclusion' },
      name: { uz: 'Maltodekstrin (DE 17-24)', ru: 'Мальтодекстрин (ДЕ 17-24)', en: 'Maltodextrin (DE 17-24)' } },
    { type: { uz: 'Sanitariya-epidemiologik xulosa', ru: 'Санитарно-эпидемиологическое заключение', en: 'Sanitary-epidemiological conclusion' },
      name: { uz: 'Kokos qirindisi', ru: 'Кокосовая стружка', en: 'Desiccated coconut' } },
    { type: { uz: 'Sanitariya-epidemiologik xulosa', ru: 'Санитарно-эпидемиологическое заключение', en: 'Sanitary-epidemiological conclusion' },
      name: { uz: 'Guar kamedi', ru: 'Гуаровая камедь', en: 'Guar gum' } },
    { type: { uz: 'Muvofiqlik sertifikati', ru: 'Сертификат соответствия', en: 'Certificate of conformity' },
      name: { uz: 'Vanilli aromatizator (Milk)', ru: 'Ванильный ароматизатор (Milk)', en: 'Vanilla flavoring (Milk)' } }
  ];
  var CU = 'https://www.citric.uz/uploads/';
  var CLIENTS = [
    { id: '1713517900833', file: 'navruz.webp',     name: 'Navruz International', bg: '#00a3d5', full: true },
    { id: '1713167303321', file: 'dena.webp',       name: 'Dena' },
    { id: '1713439597093', file: 'pepsi.webp',      name: 'Pepsi' },
    { id: '1713438619624', file: 'dinay.webp',      name: 'Dinay' },
    { id: '1713510574139', file: 'candy-gold.webp', name: 'Candy Gold' },
    { id: '1713439299320', file: 'chigatoy.webp',   name: "Chig'atoy" },
    { id: '1713438766022', file: 'monarx.webp',     name: 'Monarx' },
    { id: '1713166937136', file: 'tanho.webp',      name: 'Tanho' },
    { id: '1713166887062', file: 'elena.webp',      name: 'Elena plus' },
    { id: '1713166831438', file: 'al-imran.webp',   name: 'Al Imran' },
    { id: '1713439152151', file: 'agro-bravo.webp', name: 'Agro-Bravo' },
    { id: '1713439027066', file: 'ice-gold.webp',   name: 'Ice & Gold Exclusive' },
    { id: '1713438450097', file: 'vazira.webp',     name: 'Vazira Biznes' },
    { id: '1713437907673', file: 'isko.webp',       name: 'Isko' },
    { id: '1713166233909', file: 'mavis.webp',      name: 'Mavis' },
    { id: '1713182883777', file: 'aura.webp',       name: 'Aura Sweet Factory', bg: '#1e3667', full: true },
    { id: '1713165945485', file: 'zebiniso.webp',   name: 'Zebiniso Ona' },
    { id: '1713165867605', file: 'sfad.webp',       name: 'SFAD' },
    { id: '1713165807347', file: 'hassons.webp',    name: 'Hassons Chocolatier' },
    { id: '1713182664948', file: 'nmedov.webp',     name: "N'MEDOV" },
    { id: '1713165427387', file: 'deya.webp',       name: 'Deya', bg: '#dd000d', full: true },
    { id: '1713173712228', file: 'crafers.webp',    name: 'Crafers' }
  ];
  function renderClients() {
    function tile(c, dup) {
      return '<div class="logo-tile' + (c.full ? ' full' : '') + (dup ? ' dup' : '') + '"' +
        (c.bg ? ' style="background:' + c.bg + '"' : '') + (dup ? ' aria-hidden="true"' : '') + '>' +
        '<img src="' + CU + c.id + '_0.webp" data-fallback="' + ASSETS + 'clients/' + c.file + '" alt="' + (dup ? '' : esc(c.name)) + '" title="' + esc(c.name) + '" loading="lazy"></div>';
    }
    var half = Math.ceil(CLIENTS.length / 2);
    [CLIENTS.slice(0, half), CLIENTS.slice(half)].forEach(function (row, r) {
      $('#clientsRow' + (r + 1)).innerHTML =
        row.map(function (c) { return tile(c, false); }).join('') +
        row.map(function (c) { return tile(c, true); }).join('');
    });
    $('#clientsCount').textContent = CLIENTS.length - 1;
  }
  renderClients();
  var CATEGORIES = [{ id: 'all', uz: 'Hammasi', ru: 'Все', en: 'All' }].concat(window.CITRIC_CATEGORIES || []);
  var RU = {
    'nav.company': 'Компания', 'nav.catalog': 'Каталог', 'nav.certs': 'Сертификаты', 'nav.delivery': 'Доставка',
    'nav.contacts': 'Контакты', 'nav.partners': 'Наши клиенты',
    'cta.manager': 'Связаться с менеджером', 'cta.toCatalog': 'Перейти в каталог', 'cta.ai': 'AI-консультант',
    'cta.askAi': 'Спросить AI-консультанта', 'cta.catalog': 'Каталог продукции', 'cta.more': 'Подробнее',
    'hero.badge': 'С 2013 года · Ташкент',
    'hero.title': 'Поставка пищевых ингредиентов',
    'hero.text': 'Качественные ингредиенты и сырьё для кондитерских, напиточных и пищевых предприятий. Доставляем по всему Узбекистану.',
    'hero.s2': 'Компаний-клиентов', 'hero.s4': 'Крупных брендов выбрали нас',
    's.1': 'Год основания', 's.4': 'Видов ингредиентов',
    'v.eyebrow': 'Процесс работы', 'v.title': 'От склада — до вашего производства',
    'v.sub': 'Приёмка, хранение, сортировка и отгрузка клиенту — посмотрите весь рабочий процесс нашей компании своими глазами.',
    'v.play': 'Смотреть видео',
    'v.s1t': 'Импорт и приёмка', 'v.s1': 'Прямые поставки от надёжных производителей.',
    'v.s2t': 'Контроль качества', 'v.s2': 'Каждая партия проверяется по сертификатам и документам.',
    'v.s3t': 'Современный склад', 'v.s3': 'Хранение при правильной температуре и условиях.',
    'v.s4t': 'Быстрая доставка', 'v.s4': 'Своевременная отгрузка по всему Узбекистану.',
    'cert.eyebrow': 'Сертификаты', 'cert.title': 'Качество подтверждено документами',
    'cert.sub': 'Вся наша продукция импортируется в соответствии с требованиями государственной сертификации и санитарными нормами Республики Узбекистан.',
    'ct.route': 'Построить маршрут',
    'cart.title': 'Покупка', 'cart.sub': 'Чтобы купить выбранные товары, оставьте свои данные. Мы быстро свяжемся с вами.',
    'cart.empty': 'Ваша корзина пуста', 'cart.emptySub': 'Выберите нужные товары в каталоге.',
    'cart.name': 'Ваше имя', 'cart.namePh': 'Введите ваше имя', 'cart.phone': 'Ваш номер телефона',
    'cart.order': 'Оформить заказ', 'cart.okt': 'Ваш заказ принят!',
    'cart.ok': 'Спасибо! Наш менеджер скоро свяжется с вами для подтверждения заказа.',
    'cart.continue': 'Продолжить покупки',
    'cart.buy': 'Купить', 'cart.inCart': 'В корзине',
    'pm.details': 'Подробнее', 'pm.back': 'Назад в каталог', 'pm.more': 'Другие товары',
    'pm.pack': 'Упаковка', 'pm.origin': 'Производство', 'pm.code': 'Код / маркировка', 'pm.cat': 'Категория',
    'pm.desc': 'О продукте', 'pm.qty': 'Количество (уп.)', 'pm.update': 'Обновить корзину',
    'pm.ai': 'AI-консультант', 'pm.call': 'Позвонить',
    'pm.f1': 'Сертифицированное качество', 'pm.f2': 'Доставка по Узбекистану', 'pm.f3': 'Оптовые цены',
    'cart.items': 'Товаров', 'cart.packs': 'уп.', 'cart.total': 'Всего упаковок', 'cart.orderNo': 'Номер заказа',
    'cart.err': 'Ошибка отправки. Позвоните нам: +998 95 154 88 88',
    'f.1t': 'Продукция мировых лидеров', 'f.1': 'От лучших производителей мира.',
    'f.2t': 'Гарантия качества', 'f.2': 'Соответствует международным стандартам.',
    'f.3t': 'Развитая логистика', 'f.3': 'Консолидационные склады и дистрибуция.',
    'f.4t': 'Индивидуальный подход', 'f.4': 'Особое решение для каждого клиента.',
    'f.5t': 'Цифровые технологии', 'f.5': 'Современные IT-решения и поддержка.',
    'about.badge': 'лет опыта', 'about.eyebrow': 'О компании', 'about.title': 'О нас',
    'about.p1': 'CITRIC.UZ работает на рынке Узбекистана с 2013 года. Компания представляет мировых лидеров по производству пищевых ингредиентов.',
    'about.p2': 'Благодаря развитой дистрибьюторской сети и хорошо организованной логистике мы бесперебойно обеспечиваем пищевые предприятия высококачественными ингредиентами и сырьём.',
    'about.l1': 'Прямой импорт из Китая, Турции, Индии и Европы', 'about.l2': 'Быстрая отгрузка с собственного склада в Ташкенте',
    'about.l3': 'Сертификаты качества на каждую партию',
    'p.eyebrow': 'Продукция', 'p.title': 'Широкий ассортимент пищевых ингредиентов', 'p.all': 'Полный прайс-лист',
    'p.note': 'Не нашли нужный продукт? Напишите нам — мы подберём нужный ингредиент.',
    'p.onRequest': 'Цена по запросу', 'p.more': 'Показать ещё', 'p.loadErr': 'Не удалось загрузить каталог. Обновите страницу.',
    'ind.eyebrow': 'Сферы применения', 'ind.title': 'Для каких отраслей мы работаем',
    'ind.sub': 'Наша продукция применяется практически во всех отраслях пищевой промышленности.',
    'ind.1': 'Молочная продукция', 'ind.2': 'Хлебобулочные изделия', 'ind.3': 'Мясная продукция', 'ind.4': 'Напитки',
    'ind.5': 'Кондитерские изделия', 'ind.6': 'Фармацевтика и косметика',
    'w.eyebrow': 'Почему мы?', 'w.title': 'Почему именно CITRIC.UZ?',
    'w.1t': 'Качество и доверие', 'w.1': 'Только продукция от надёжных производителей.',
    'w.2t': 'Опытная команда', 'w.2': 'Профессионалы пищевой промышленности.',
    'w.3t': 'Конкурентные цены', 'w.3': 'Выгодные цены и гибкая оплата благодаря прямому импорту.',
    'w.4t': 'Устойчивое развитие', 'w.4': 'Будущее, связанное с благополучием наших партнёров.',
    'c.eyebrow': 'Наши клиенты', 'c.title': 'Нас выбирают',
    'c.sub': 'Ведущие кондитерские, напиточные и пищевые производители Узбекистана закупают ингредиенты у нас.',
    'c.stat': 'крупных брендов доверяют нам',
    'c.caption': 'Присоединяйтесь — качественное сырьё является основой стабильного производства.',
    't.eyebrow': 'Клиенты', 't.title': 'Что говорят наши клиенты?',
    't.1': 'Сотрудничество с CITRIC.UZ открыло большие возможности для нашего бизнеса. Благодаря качественной продукции и профессиональному подходу наше производство стало ещё эффективнее.',
    't.1a': 'Крупный производитель продуктов питания в Узбекистане',
    't.2': 'Поставки всегда вовремя, документы и сертификаты в полном порядке. Уже несколько лет берём лимонную кислоту и консерванты только у CITRIC.UZ.',
    't.2a': 'Производитель напитков',
    't.3': 'Менеджеры всегда на связи и помогают подобрать нужный ингредиент. Цены конкурентные, а качество стабильное.',
    't.3a': 'Кондитерская фабрика',
    'ct.eyebrow': 'Контакты', 'ct.title': 'Начнём сотрудничество',
    'ct.sub': 'Оставьте заявку — наш менеджер свяжется с вами и расскажет о ценах и условиях доставки.',
    'ct.phone': 'Телефон', 'ct.addr': 'Адрес', 'ct.addrv': 'Ташкент, Узбекистан', 'ct.hours': 'Время работы', 'ct.hoursv': 'Пн–Сб, 9:00 – 18:00',
    'ft.about': 'Лучшие ингредиенты для лучшей жизни. Надёжный поставщик для пищевой промышленности.',
    'ft.links': 'Быстрые ссылки', 'ft.contact': 'Связь',
    'ft.rights': 'Все права защищены.', 'ft.privacy': 'Политика конфиденциальности', 'ft.terms': 'Условия использования',
    'fm.title': 'Оставить заявку', 'fm.sub': 'Оставьте свои данные, мы свяжемся с вами в течение 1 рабочего дня.',
    'fm.name': 'Ваше имя *', 'fm.phone': 'Телефон *', 'fm.company': 'Компания', 'fm.product': 'Интересующий продукт',
    'fm.msg': 'Сообщение', 'fm.msgph': 'Объём, фасовка, адрес доставки...', 'fm.send': 'Отправить',
    'fm.okt': 'Спасибо! Ваша заявка принята', 'fm.ok': 'Наш менеджер скоро свяжется с вами.',
    'fm.any': 'Другое / несколько продуктов', 'fm.err': 'Ошибка отправки. Попробуйте ещё раз или позвоните: +998 95 154 88 88',
    'chat.title': 'AI-консультант', 'chat.status': 'Онлайн · отвечает за несколько секунд', 'chat.reset': 'Новый диалог',
    'chat.q1': 'Что нужно для сока?', 'chat.q2': 'Помогите выбрать консервант', 'chat.q3': 'Сколько стоит лимонная кислота?', 'chat.q4': 'Условия доставки',
    'chat.ph': 'Опишите нужный продукт своими словами...', 'chat.fab': 'Есть вопрос?',
    'chat.foot': 'AI-консультант отвечает только по продукции CITRIC.UZ. Точную цену подтверждает менеджер.',
    'chat.hello': 'Здравствуйте! Я AI-консультант **CITRIC.UZ**. Напишите, для какого продукта вам нужен ингредиент — подберу подходящие варианты, расскажу о применении и передам заявку менеджеру.',
    'chat.err': 'Не удалось получить ответ. Попробуйте ещё раз или позвоните менеджеру: **+998 95 154 88 88**',
    'chat.busy': 'Слишком много запросов. Подождите немного или позвоните: **+998 95 154 88 88**',
    'chat.done': 'Заявка отправлена операторам', 'chat.add': 'В корзину', 'chat.added': 'В корзине'
  };
  var EN = {
    'nav.company': 'Company', 'nav.catalog': 'Catalog', 'nav.certs': 'Certificates', 'nav.delivery': 'Delivery',
    'nav.contacts': 'Contacts', 'nav.partners': 'Our Clients',
    'cta.manager': 'Contact a manager', 'cta.toCatalog': 'Go to catalog', 'cta.ai': 'AI consultant',
    'cta.askAi': 'Ask the AI consultant', 'cta.catalog': 'Product catalog', 'cta.more': 'Learn more',
    'hero.badge': 'Since 2013 · Tashkent',
    'hero.title': 'Food Ingredients Supply',
    'hero.text': 'High-quality ingredients and raw materials for confectionery, beverage and food manufacturers. Delivered across Uzbekistan.',
    'hero.s2': 'Client companies', 'hero.s4': 'Major brands chose us',
    's.1': 'Year founded', 's.4': 'Types of ingredients',
    'v.eyebrow': 'How we work', 'v.title': 'From our warehouse to your production line',
    'v.sub': 'Receiving, storage, sorting and shipping to the customer — see our entire workflow with your own eyes.',
    'v.play': 'Watch video',
    'v.s1t': 'Import & receiving', 'v.s1': 'Direct supplies from trusted manufacturers.',
    'v.s2t': 'Quality control', 'v.s2': 'Every batch is checked against certificates and documents.',
    'v.s3t': 'Modern warehouse', 'v.s3': 'Stored at the right temperature and conditions.',
    'v.s4t': 'Fast delivery', 'v.s4': 'On-time shipping across Uzbekistan.',
    'cert.eyebrow': 'Certificates', 'cert.title': 'Quality confirmed by documents',
    'cert.sub': 'All our products are imported in compliance with state certification requirements and sanitary standards of the Republic of Uzbekistan.',
    'ct.route': 'Get directions',
    'cart.title': 'Purchase', 'cart.sub': 'To buy the selected products, leave your details. We will contact you shortly.',
    'cart.empty': 'Your cart is empty', 'cart.emptySub': 'Choose the products you need in the catalog.',
    'cart.name': 'Your name', 'cart.namePh': 'Enter your name', 'cart.phone': 'Your phone number',
    'cart.order': 'Place order', 'cart.okt': 'Your order has been received!',
    'cart.ok': 'Thank you! Our manager will contact you soon to confirm your order.',
    'cart.continue': 'Continue shopping',
    'cart.buy': 'Buy', 'cart.inCart': 'In cart',
    'pm.details': 'Details', 'pm.back': 'Back to catalog', 'pm.more': 'Other products',
    'pm.pack': 'Packaging', 'pm.origin': 'Made in', 'pm.code': 'Code / grade', 'pm.cat': 'Category',
    'pm.desc': 'About the product', 'pm.qty': 'Quantity (packs)', 'pm.update': 'Update cart',
    'pm.ai': 'AI consultant', 'pm.call': 'Call us',
    'pm.f1': 'Certified quality', 'pm.f2': 'Delivery across Uzbekistan', 'pm.f3': 'Wholesale prices',
    'cart.items': 'Products', 'cart.packs': 'packs', 'cart.total': 'Total packs', 'cart.orderNo': 'Order number',
    'cart.err': 'Sending failed. Please call us: +998 95 154 88 88',
    'f.1t': 'Products of global leaders', 'f.1': "From the world's best manufacturers.",
    'f.2t': 'Quality guarantee', 'f.2': 'Meets international standards.',
    'f.3t': 'Developed logistics', 'f.3': 'Consolidation warehouses and distribution.',
    'f.4t': 'Individual approach', 'f.4': 'A tailored solution for every client.',
    'f.5t': 'Digital technologies', 'f.5': 'Modern IT solutions and support.',
    'about.badge': 'years of experience', 'about.eyebrow': 'About the company', 'about.title': 'About Us',
    'about.p1': 'CITRIC.UZ has been operating in the Uzbekistan market since 2013. The company represents global leaders in the production of food ingredients.',
    'about.p2': 'Thanks to a developed distribution network and well-organized logistics, we reliably supply food manufacturers with high-quality ingredients and raw materials.',
    'about.l1': 'Direct imports from China, Turkey, India and Europe', 'about.l2': 'Fast shipping from our own warehouse in Tashkent',
    'about.l3': 'Quality certificates for every batch',
    'p.eyebrow': 'Products', 'p.title': 'A wide range of food ingredients', 'p.all': 'Full price list',
    'p.note': "Didn't find the product you need? Write to us — we will find the right ingredient.",
    'p.onRequest': 'Price on request', 'p.more': 'Show more', 'p.loadErr': 'Failed to load the catalog. Please refresh the page.',
    'ind.eyebrow': 'Applications', 'ind.title': 'Industries we serve',
    'ind.sub': 'Our products are used in almost every sector of the food industry.',
    'ind.1': 'Dairy products', 'ind.2': 'Bakery products', 'ind.3': 'Meat products', 'ind.4': 'Beverages',
    'ind.5': 'Confectionery', 'ind.6': 'Pharmaceuticals & cosmetics',
    'w.eyebrow': 'Why us?', 'w.title': 'Why CITRIC.UZ?',
    'w.1t': 'Quality and trust', 'w.1': 'Only products from reliable manufacturers.',
    'w.2t': 'Experienced team', 'w.2': 'Food industry professionals.',
    'w.3t': 'Competitive prices', 'w.3': 'Favorable prices and flexible payment thanks to direct imports.',
    'w.4t': 'Sustainable growth', 'w.4': 'A future tied to the success of our partners.',
    'c.eyebrow': 'Our clients', 'c.title': 'Trusted by',
    'c.sub': "Uzbekistan's leading confectionery, beverage and food manufacturers source their ingredients from us.",
    'c.stat': 'major brands trust us',
    'c.caption': 'Join them — quality raw materials are the foundation of stable production.',
    't.eyebrow': 'Testimonials', 't.title': 'What our clients say',
    't.1': 'Working with CITRIC.UZ opened up great opportunities for our business. Thanks to quality products and a professional approach, our production has become even more efficient.',
    't.1a': 'Major food manufacturer in Uzbekistan',
    't.2': 'Deliveries are always on time, and documents and certificates are always in order. For several years we have been buying citric acid and preservatives only from CITRIC.UZ.',
    't.2a': 'Beverage manufacturer',
    't.3': 'The managers are always available and help us choose the right ingredient. Prices are competitive and the quality is consistent.',
    't.3a': 'Confectionery factory',
    'ct.eyebrow': 'Contacts', 'ct.title': "Let's start working together",
    'ct.sub': 'Leave a request — our manager will contact you and tell you about prices and delivery terms.',
    'ct.phone': 'Phone', 'ct.addr': 'Address', 'ct.addrv': 'Tashkent, Uzbekistan', 'ct.hours': 'Working hours', 'ct.hoursv': 'Mon–Sat, 9:00 – 18:00',
    'ft.about': 'The best ingredients for a better life. A reliable supplier for the food industry.',
    'ft.links': 'Quick links', 'ft.contact': 'Contact',
    'ft.rights': 'All rights reserved.', 'ft.privacy': 'Privacy policy', 'ft.terms': 'Terms of use',
    'fm.title': 'Send a request', 'fm.sub': 'Leave your details and we will contact you within 1 business day.',
    'fm.name': 'Your name *', 'fm.phone': 'Phone *', 'fm.company': 'Company', 'fm.product': 'Product of interest',
    'fm.msg': 'Message', 'fm.msgph': 'Volume, packaging, delivery address...', 'fm.send': 'Send',
    'fm.okt': 'Thank you! Your request has been received', 'fm.ok': 'Our manager will contact you soon.',
    'fm.any': 'Other / several products', 'fm.err': 'Sending failed. Please try again or call: +998 95 154 88 88',
    'chat.title': 'AI consultant', 'chat.status': 'Online · replies in seconds', 'chat.reset': 'New chat',
    'chat.q1': 'What do I need for juice?', 'chat.q2': 'Help me choose a preservative', 'chat.q3': 'How much is citric acid?', 'chat.q4': 'Delivery terms',
    'chat.ph': 'Describe the product you need in your own words...', 'chat.fab': 'Have a question?',
    'chat.foot': 'The AI consultant answers only about CITRIC.UZ products. The exact price is confirmed by a manager.',
    'chat.hello': "Hello! I'm the **CITRIC.UZ** AI consultant. Tell me which product you need an ingredient for — I'll suggest suitable options, explain how they're used and pass your request to a manager.",
    'chat.err': "Couldn't get a reply. Please try again or call our manager: **+998 95 154 88 88**",
    'chat.busy': 'Too many requests. Please wait a moment or call: **+998 95 154 88 88**',
    'chat.done': 'Your request has been sent to our team', 'chat.add': 'Add to cart', 'chat.added': 'In cart'
  };
  // Qadoq turlari (bazada o'zbekcha saqlanadi)
  var PACK = {
    ru: { 'Bochka': 'Бочка', 'Baraban': 'Барабан', 'Kanistr': 'Канистра', 'kg': 'кг' },
    en: { 'Bochka': 'Drum', 'Baraban': 'Drum', 'Kanistr': 'Canister', 'kg': 'kg' }
  };
  function packText(v) {
    var m = PACK[lang];
    if (!m || !v) return v || '';
    return String(v).replace(/[A-Za-z]+/g, function (w) { return m[w] || w; });
  }
  var UZ_EXTRA = {
    'p.onRequest': "Narx so'rov bo'yicha", 'p.more': "Yana ko'rsatish", 'p.loadErr': "Katalogni yuklab bo'lmadi. Sahifani yangilang.",
    'fm.any': 'Boshqa / bir nechta mahsulot',
    'cart.buy': 'Sotib olish', 'cart.inCart': 'Savatda',
    'cart.items': 'Mahsulotlar', 'cart.packs': 'qadoq', 'cart.total': 'Jami qadoqlar', 'cart.orderNo': 'Buyurtma raqami',
    'cart.err': "Yuborishda xatolik. Bizga qo'ng'iroq qiling: +998 95 154 88 88",
    'chat.hello': "Assalomu alaykum! Men **CITRIC.UZ** AI maslahatchisiman. Qaysi mahsulot uchun ingrediyent kerakligini yozing — mos variantlarni tanlab, qo'llanilishini tushuntiraman va arizangizni menejerga yetkazaman.",
    'chat.err': "Javob olib bo'lmadi. Qayta urinib ko'ring yoki menejerga qo'ng'iroq qiling: **+998 95 154 88 88**",
    'chat.busy': "So'rovlar juda ko'p. Biroz kuting yoki qo'ng'iroq qiling: **+998 95 154 88 88**",
    'chat.done': 'Ariza operatorlarga yuborildi', 'chat.add': 'Savatga', 'chat.added': 'Savatda',
    'pm.details': 'Batafsil', 'pm.back': 'Katalogga qaytish', 'pm.more': "Boshqa mahsulotlar",
    'pm.pack': 'Qadoq', 'pm.origin': 'Ishlab chiqarilgan', 'pm.code': 'Kod / belgi', 'pm.cat': 'Kategoriya',
    'pm.desc': "Mahsulot haqida", 'pm.qty': 'Miqdor (qadoq)', 'pm.update': 'Savatni yangilash',
    'pm.ai': 'AI maslahatchi', 'pm.call': "Qo'ng'iroq qilish",
    'pm.f1': 'Sertifikatlangan sifat', 'pm.f2': "O'zbekiston bo'ylab yetkazib berish", 'pm.f3': 'Ulgurji narxlar'
  };
  var lang = store('local', 'citric-lang') || 'uz';
  if (lang !== 'ru' && lang !== 'en') lang = 'uz';
  var UZ = {}; // HTML'dagi asl o'zbekcha matnlar
  function t(key) {
    if (lang === 'ru' && RU[key]) return RU[key];
    if (lang === 'en' && EN[key]) return EN[key];
    return UZ[key] || UZ_EXTRA[key] || key;
  }
  $('#contactCard').appendChild($('#formTpl').content.cloneNode(true));
  $$('[data-i18n]').forEach(function (el) {
    var k = el.getAttribute('data-i18n');
    if (!(k in UZ)) UZ[k] = el.innerHTML;
  });
  $$('[data-i18n-ph]').forEach(function (el) {
    var k = el.getAttribute('data-i18n-ph');
    if (!(k in UZ)) UZ[k] = el.getAttribute('placeholder');
  });
  $$('[data-i18n-title]').forEach(function (el) {
    var k = el.getAttribute('data-i18n-title');
    if (!(k in UZ)) UZ[k] = el.getAttribute('title');
  });
  var PRODUCTS = [];
  var BY_ID = {};
  var productsLoaded = false;
  var activeCat = 'all';
  var PAGE = 15, showAll = false; // «Hammasi»da avval 15 ta, keyin «Yana ko'rsatish»
  function setProducts(list) {
    PRODUCTS = list;
    BY_ID = {};
    list.forEach(function (p) { BY_ID[p.id] = p; });
    productsLoaded = true;
  }
  function loadProducts() {
    setProducts(window.CITRIC_PRODUCTS || []);
    if (!API) return Promise.resolve();
    // Vercel nusxasi: admin paneldagi o'zgarishlar darhol ko'rinishi uchun ro'yxat asosiy saytdan olinadi
    return fetch(API + '?api=products').then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.products && j.products.length) setProducts(j.products);
    }).catch(function () {});
  }
  function renderFilters() {
    $('#filters').innerHTML = CATEGORIES.map(function (c) {
      return '<button type="button" class="filter' + (c.id === activeCat ? ' active' : '') + '" data-cat="' + c.id + '">' + c[lang] + '</button>';
    }).join('');
  }
  function renderProducts() {
    var grid = $('#productsGrid');
    if (!productsLoaded) {
      grid.innerHTML = new Array(5).join('<div class="product skeleton"><div class="product-img"></div><div class="product-body"><i></i><i></i><i></i></div></div>');
      return;
    }
    if (!PRODUCTS.length) {
      grid.innerHTML = '<p class="products-empty">' + t('p.loadErr') + '</p>';
      return;
    }
    var shown = 0, total = 0;
    var html = PRODUCTS.map(function (p) {
      var match = activeCat === 'all' || p.cat === activeCat;
      if (match) total++;
      var hidden = !match || (activeCat === 'all' && !showAll && shown >= PAGE);
      if (!hidden) shown++;
      return cardHtml(p, hidden);
    }).join('');
    grid.innerHTML = html;
    var more = $('#productsMore');
    more.hidden = shown >= total;
    more.querySelector('span').textContent = t('p.more') + ' (' + (total - shown) + ')';
    observeViews();
  }
  function cardHtml(p, hidden) {
    return '<article class="product' + (hidden ? ' hide' : '') + '" data-pid="' + p.id + '" tabindex="0" role="button" aria-label="' + esc(p[lang]) + '">' +
      '<div class="product-img"><img src="' + esc(p.img) + '" alt="' + esc(p[lang]) + '" loading="lazy">' +
      (p.code ? '<span class="product-tag">' + esc(p.code) + '</span>' : '') +
      '<span class="product-view"><svg class="i"><use href="#i-zoom"/></svg>' + t('pm.details') + '</span></div>' +
      '<div class="product-body"><h3>' + esc(p[lang]) + '</h3>' +
      (p.desc && p.desc[lang] ? '<p class="product-desc">' + esc(p.desc[lang]) + '</p>' : '') +
      '<div class="product-meta">' +
        '<span><svg class="i"><use href="#i-box"/></svg>' + esc(packText(p.pack)) + '</span>' +
        '<span><svg class="i"><use href="#i-globe"/></svg>' + esc(p.origin[lang]) + '</span>' +
      '</div>' +
      '<div class="product-price' + (p.price ? '' : ' on-request') + '">' + esc(p.price || t('p.onRequest')) + '</div>' +
      productBtn(p.id) + '</div></article>';
  }
  function catName(id) {
    var c = CATEGORIES.filter(function (x) { return x.id === id; })[0];
    return c ? c[lang] : '';
  }

  /* ---------- Mahsulot sahifasi (katta oyna): to'liq ma'lumot, sotib olish, pastda boshqa mahsulotlar ---------- */
  var pm = document.createElement('div');
  pm.className = 'pm';
  pm.id = 'pm';
  pm.setAttribute('aria-hidden', 'true');
  pm.setAttribute('role', 'dialog');
  pm.setAttribute('aria-modal', 'true');
  pm.innerHTML = '<div class="pm-backdrop" data-close-pm></div>' +
    '<div class="pm-sheet"><div class="pm-scroll" id="pmScroll">' +
      '<div class="pm-top"><button type="button" class="pm-back" data-close-pm><svg class="i"><use href="#i-left"/></svg><span></span></button>' +
      '<div class="pm-crumbs"></div>' +
      '<button type="button" class="pm-close" data-close-pm aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button></div>' +
      '<div class="pm-main" id="pmMain"></div>' +
      '<section class="pm-more"><h3 class="pm-more-title"></h3><div class="products-grid pm-grid" id="pmGrid"></div>' +
      '<div class="pm-sentinel" id="pmSentinel"><span class="pm-loader"></span></div></section>' +
    '</div></div>';
  document.body.appendChild(pm);
  var pmScroll = $('#pmScroll'), pmGrid = $('#pmGrid'), pmSentinel = $('#pmSentinel');
  var pmId = null, pmQty = 1, pmRest = [], PM_STEP = 12;

  function pmParagraphs(text) {
    text = String(text || '').trim();
    if (!text) return '';
    return text.split(/\n+/).map(function (s) { return '<p>' + esc(s.trim()) + '</p>'; }).join('');
  }
  function renderPm() {
    var p = BY_ID[pmId];
    if (!p) return;
    var inCart = cartQty(p.id);
    $('.pm-back span', pm).textContent = t('pm.back');
    $('.pm-crumbs', pm).innerHTML = '<span>' + t('nav.catalog') + '</span><svg class="i"><use href="#i-chev"/></svg><span>' + esc(catName(p.cat)) + '</span>';
    $('.pm-more-title', pm).textContent = t('pm.more');
    $('#pmMain').innerHTML =
      '<div class="pm-media"><div class="pm-img" id="pmImg"><img src="' + esc(p.img) + '" alt="' + esc(p[lang]) + '"></div>' +
        '<ul class="pm-perks">' +
          '<li><svg class="i"><use href="#i-shield"/></svg>' + t('pm.f1') + '</li>' +
          '<li><svg class="i"><use href="#i-truck"/></svg>' + t('pm.f2') + '</li>' +
          '<li><svg class="i"><use href="#i-award"/></svg>' + t('pm.f3') + '</li>' +
        '</ul></div>' +
      '<div class="pm-info">' +
        '<div class="pm-chips"><span class="pm-chip">' + esc(catName(p.cat)) + '</span>' + (p.code ? '<span class="pm-chip code">' + esc(p.code) + '</span>' : '') + '</div>' +
        '<h2 class="pm-title">' + esc(p[lang]) + '</h2>' +
        '<div class="pm-price' + (p.price ? '' : ' on-request') + '">' + esc(p.price || t('p.onRequest')) + (p.price ? '<small> / ' + esc(packText(p.pack)) + '</small>' : '') + '</div>' +
        '<dl class="pm-specs">' +
          (p.pack ? '<div><dt><svg class="i"><use href="#i-box"/></svg>' + t('pm.pack') + '</dt><dd>' + esc(packText(p.pack)) + '</dd></div>' : '') +
          (p.origin[lang] ? '<div><dt><svg class="i"><use href="#i-globe"/></svg>' + t('pm.origin') + '</dt><dd>' + esc(p.origin[lang]) + '</dd></div>' : '') +
          (p.code ? '<div><dt><svg class="i"><use href="#i-flask"/></svg>' + t('pm.code') + '</dt><dd>' + esc(p.code) + '</dd></div>' : '') +
          '<div><dt><svg class="i"><use href="#i-nav"/></svg>' + t('pm.cat') + '</dt><dd>' + esc(catName(p.cat)) + '</dd></div>' +
        '</dl>' +
        (p.desc && p.desc[lang] ? '<div class="pm-desc"><h4>' + t('pm.desc') + '</h4>' + pmParagraphs(p.desc[lang]) + '</div>' : '') +
        '<div class="pm-buy">' +
          '<div class="pm-qty"><span>' + t('pm.qty') + '</span><div class="qty">' +
            '<button type="button" data-pmq="-1" aria-label="-"><svg class="i"><use href="#i-minus"/></svg></button>' +
            '<input type="number" min="1" max="999" value="' + pmQty + '" id="pmQty" aria-label="qty">' +
            '<button type="button" data-pmq="1" aria-label="+"><svg class="i"><use href="#i-plus"/></svg></button></div></div>' +
          '<button type="button" class="btn btn-orange pm-buy-btn" id="pmBuy"><svg class="i"><use href="#i-bag"/></svg>' +
            (inCart ? t('pm.update') + ' · ' + inCart : t('cart.buy')) + '</button>' +
        '</div>' +
        '<div class="pm-actions">' +
          '<button type="button" class="pm-act" data-pm-ai><svg class="i"><use href="#i-spark"/></svg>' + t('pm.ai') + '</button>' +
          '<a class="pm-act" href="tel:+998951548888"><svg class="i"><use href="#i-phone"/></svg>' + t('pm.call') + '</a>' +
        '</div>' +
      '</div>';
  }
  function pmFillRest() {
    var p = BY_ID[pmId];
    var same = PRODUCTS.filter(function (x) { return x.id !== pmId && x.cat === p.cat; });
    var other = PRODUCTS.filter(function (x) { return x.id !== pmId && x.cat !== p.cat; });
    pmRest = same.concat(other);
    pmGrid.innerHTML = '';
    pmLoadMore();
  }
  function pmLoadMore() {
    if (!pmRest.length) { pmSentinel.hidden = true; return; }
    pmGrid.insertAdjacentHTML('beforeend', pmRest.splice(0, PM_STEP).map(function (x) { return cardHtml(x, false); }).join(''));
    pmSentinel.hidden = !pmRest.length;
  }
  var pmObs = 'IntersectionObserver' in window ? new IntersectionObserver(function (en) {
    if (en[0].isIntersecting && pm.classList.contains('open')) pmLoadMore();
  }, { root: pmScroll, rootMargin: '400px' }) : null;
  if (pmObs) pmObs.observe(pmSentinel);
  else pmSentinel.addEventListener('click', pmLoadMore);

  function showPm(id) {
    if (!BY_ID[id]) return;
    pmId = id;
    pmQty = Math.max(1, cartQty(id) || 1);
    renderPm();
    pmFillRest();
    pmScroll.scrollTop = 0;
    if (!pm.classList.contains('open')) {
      closeChat(); closeCart();
      pm.classList.add('open');
      pm.setAttribute('aria-hidden', 'false');
      lockScroll(true);
    }
    if (!seen[id]) { seen[id] = 1; store('session', 'citric-seen', seen); track('view', [id]); }
  }
  function hidePm() {
    if (!pm.classList.contains('open')) return;
    pm.classList.remove('open');
    pm.setAttribute('aria-hidden', 'true');
    pmId = null;
    if (!cartEl.classList.contains('open') && !chatEl.classList.contains('open')) lockScroll(false);
  }
  function openPm(id) {
    var hash = '#p-' + id;
    if (pm.classList.contains('open')) history.replaceState({ pm: id }, '', hash);
    else history.pushState({ pm: id }, '', hash);
    showPm(id);
  }
  function closePm() {
    if (!pm.classList.contains('open')) return;
    if (history.state && history.state.pm) history.back(); // popstate oynani yopadi
    else { hidePm(); history.replaceState(null, '', location.pathname + location.search); }
  }
  window.addEventListener('popstate', function () {
    var m = /^#p-(\d+)$/.exec(location.hash);
    if (m && BY_ID[+m[1]]) showPm(+m[1]); else hidePm();
  });
  pm.addEventListener('click', function (e) {
    if (e.target.closest('[data-close-pm]')) { closePm(); return; }
    var q = e.target.closest('[data-pmq]');
    if (q) {
      pmQty = Math.max(1, Math.min(999, pmQty + +q.getAttribute('data-pmq')));
      $('#pmQty').value = pmQty;
      return;
    }
    if (e.target.closest('#pmBuy')) {
      var id = pmId, isNew = cartQty(id) === 0;
      setQty(id, pmQty);
      if (isNew) track('cart', [id]);
      closePm();
      setTimeout(function () { openCart(); }, 60);
      return;
    }
    if (e.target.closest('[data-pm-ai]')) {
      closePm();
      setTimeout(function () { openChat(); }, 60);
      return;
    }
    var card = e.target.closest('.product[data-pid]');
    if (card && !e.target.closest('.product-btn')) {
      openPm(+card.getAttribute('data-pid'));
      pmScroll.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
  pm.addEventListener('change', function (e) {
    if (e.target.id === 'pmQty') { pmQty = Math.max(1, Math.min(999, parseInt(e.target.value, 10) || 1)); e.target.value = pmQty; }
  });

  function productBtn(id) {
    var q = cartQty(id);
    return '<button type="button" class="product-btn' + (q ? ' in-cart' : '') + '" data-product="' + id + '">' +
      (q ? t('cart.inCart') + ' · ' + q : t('cart.buy')) +
      '<svg class="i"><use href="#i-' + (q ? 'ok' : 'bag') + '"/></svg></button>';
  }
  function renderSelects() {
    $$('select[name="product"]').forEach(function (sel) {
      var val = sel.value;
      sel.innerHTML = '<option value="">—</option>' + PRODUCTS.map(function (p) {
        return '<option value="' + p.id + '">' + esc(p[lang]) + (p.code ? ' (' + esc(p.code) + ')' : '') + '</option>';
      }).join('') + '<option value="other">' + t('fm.any') + '</option>';
      sel.value = val;
    });
  }
  function renderCerts() {
    $('#certsGrid').innerHTML = CERTS.map(function (c, i) {
      return '<button type="button" class="cert reveal in" data-cert="' + i + '">' +
        '<div class="cert-img"><img src="' + certSrc(i) + '" data-fallback="' + certFallback(i) + '" alt="' + esc(c.type[lang] + ' — ' + c.name[lang]) + '" loading="lazy">' +
        '<span class="cert-zoom"><svg class="i"><use href="#i-zoom"/></svg></span></div>' +
        '<span class="cert-type"><svg class="i"><use href="#i-file"/></svg>' + c.type[lang] + '</span>' +
        '<strong>' + c.name[lang] + '</strong></button>';
    }).join('');
  }
  var seen = store('session', 'citric-seen') || {};
  var viewQueue = [], viewTimer;
  function track(type, ids) {
    ids = ids.filter(function (id) { return typeof id === 'number'; });
    if (!ids.length) return;
    post(API + '?api=track', { type: type, ids: ids }).catch(function () {});
  }
  var viewObs = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (!en.isIntersecting) return;
      viewObs.unobserve(en.target);
      var id = +en.target.getAttribute('data-pid');
      if (!id || seen[id]) return;
      seen[id] = 1;
      viewQueue.push(id);
    });
    store('session', 'citric-seen', seen);
    clearTimeout(viewTimer);
    viewTimer = setTimeout(function () { track('view', viewQueue.splice(0)); }, 1500);
  }, { threshold: 0.6 }) : null;
  function observeViews() {
    if (!viewObs) return;
    $$('.product[data-pid]').forEach(function (el) { if (!el.classList.contains('hide')) viewObs.observe(el); });
  }
  var cart = store('local', 'citric-cart2') || [];
  function saveCart() { store('local', 'citric-cart2', cart); }
  function cleanCart() {
    cart = cart.filter(function (c) { return BY_ID[c.id] && c.qty > 0; });
    saveCart();
  }
  function cartQty(id) {
    for (var k = 0; k < cart.length; k++) if (cart[k].id === id) return cart[k].qty;
    return 0;
  }
  function setQty(id, qty) {
    qty = Math.max(0, Math.min(999, parseInt(qty, 10) || 0));
    var item = cart.filter(function (c) { return c.id === id; })[0];
    if (!item && qty > 0) cart.push({ id: id, qty: qty });
    else if (item) item.qty = qty;
    cart = cart.filter(function (c) { return c.qty > 0; });
    saveCart();
    updateCartUI();
  }
  var cartEl = $('#cart');
  function updateCartUI() {
    var count = cart.reduce(function (s, c) { return s + c.qty; }, 0);
    var badge = $('#cartCount');
    badge.textContent = count;
    badge.classList.toggle('show', count > 0);
    cartEl.classList.toggle('is-empty', cart.length === 0);
    $('#cartList').innerHTML = cart.map(function (c) {
      var p = BY_ID[c.id];
      if (!p) return '';
      return '<li class="cart-item" data-id="' + esc(c.id) + '">' +
        '<img src="' + esc(p.img) + '" alt="">' +
        '<div><h4>' + esc(p[lang]) + (p.code ? ' ' + esc(p.code) : '') + '</h4>' +
        '<small>' + esc(packText(p.pack)) + ' / ' + t('cart.packs') + ' · ' + esc(p.price || t('p.onRequest')) + '</small>' +
        '<div class="qty"><button type="button" data-qty="-1" aria-label="-"><svg class="i"><use href="#i-minus"/></svg></button>' +
        '<input type="number" min="1" max="999" value="' + c.qty + '" aria-label="qty">' +
        '<button type="button" data-qty="1" aria-label="+"><svg class="i"><use href="#i-plus"/></svg></button></div></div>' +
        '<button type="button" class="cart-del" aria-label="Delete"><svg class="i"><use href="#i-trash"/></svg></button></li>';
    }).join('');
    $('#cartTotal').innerHTML = '<span>' + t('cart.items') + ': <b>' + cart.length + '</b></span><span>' + t('cart.total') + ': <b>' + count + '</b></span>';
    $$('.product-btn').forEach(function (b) {
      var raw = b.getAttribute('data-product');
      b.outerHTML = productBtn(isNaN(+raw) ? raw : +raw);
    });
    $$('.chat-prod-btn').forEach(function (b) {
      var q = cartQty(+b.getAttribute('data-add'));
      b.classList.toggle('added', q > 0);
      b.textContent = q ? t('chat.added') + ' ✓' : t('chat.add');
    });
  }
  function lockScroll(on) { document.body.style.overflow = on ? 'hidden' : ''; }
  function openCart() {
    closeChat();
    cartEl.classList.remove('done');
    cartEl.classList.add('open');
    cartEl.setAttribute('aria-hidden', 'false');
    lockScroll(true);
  }
  function closeCart() {
    cartEl.classList.remove('open');
    cartEl.setAttribute('aria-hidden', 'true');
    lockScroll(false);
  }
  function addToCart(id, open) {
    if (cartQty(id) === 0) {
      setQty(id, 1);
      track('cart', [id]);
    }
    var btn = $('.cart-btn');
    btn.classList.remove('bump'); void btn.offsetWidth; btn.classList.add('bump');
    if (open !== false) openCart();
  }
  $('#cartList').addEventListener('click', function (e) {
    var li = e.target.closest('.cart-item');
    if (!li) return;
    var raw = li.getAttribute('data-id'), id = isNaN(+raw) ? raw : +raw;
    var q = e.target.closest('[data-qty]');
    if (q) setQty(id, Math.max(1, cartQty(id) + +q.getAttribute('data-qty')));
    if (e.target.closest('.cart-del')) setQty(id, 0);
  });
  $('#cartList').addEventListener('change', function (e) {
    var li = e.target.closest('.cart-item');
    if (!li || e.target.tagName !== 'INPUT') return;
    var raw = li.getAttribute('data-id');
    setQty(isNaN(+raw) ? raw : +raw, Math.max(1, +e.target.value || 1));
  });
  function validName(v) { return v.trim().length > 1; }
  function validPhone(v) { return v.replace(/\D/g, '').length >= 9; }
  $('#orderForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    var okName = validName(form.name.value), okPhone = validPhone(form.phone.value);
    form.name.closest('.field').classList.toggle('error', !okName);
    form.phone.closest('.field').classList.toggle('error', !okPhone);
    if (!okName || !okPhone || !cart.length) return;
    var btn = $('#orderBtn');
    btn.classList.add('loading');
    post(API + '?api=lead', {
      type: 'order', lang: lang, name: form.name.value, phone: form.phone.value, website: form.website.value,
      items: cart.map(function (c) { return { id: c.id, qty: c.qty }; })
    }).then(function (r) {
      cart = [];
      saveCart();
      updateCartUI();
      form.reset();
      $('#orderNo').textContent = t('cart.orderNo') + ': #' + r.id;
      cartEl.classList.add('done');
    }).catch(function () {
      alert(t('cart.err'));
    }).then(function () { btn.classList.remove('loading'); });
  });
  $('#contactCard form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this, ok = true;
    $$('[required]', form).forEach(function (inp) {
      var valid = inp.type === 'tel' ? validPhone(inp.value) : validName(inp.value);
      inp.closest('.field').classList.toggle('error', !valid);
      if (!valid) ok = false;
    });
    if (!ok) return;
    var btn = $('button[type="submit"]', form);
    var err = $('.form-error', form);
    err.hidden = true;
    btn.classList.add('loading');
    var data = {};
    new FormData(form).forEach(function (v, k) { data[k] = v; });
    data.type = 'form';
    data.lang = lang;
    post(API + '?api=lead', data).then(function () {
      form.closest('.form-card').classList.add('sent');
      form.reset();
    }).catch(function () { err.hidden = false; })
      .then(function () { btn.classList.remove('loading'); });
  });
  document.addEventListener('input', function (e) {
    var f = e.target.closest('.field.error');
    if (f) f.classList.remove('error');
  });
  var chatEl = $('#chat');
  var chat = store('session', 'citric-chat') || { session: '', msgs: [], done: false };
  var chatBusy = false;
  function saveChat() { store('session', 'citric-chat', chat); }
  function md(text) {
    var lines = esc(text).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').split(/\n/);
    var out = '', inList = false;
    lines.forEach(function (line) {
      var m = line.match(/^\s*[-•*]\s+(.*)/);
      if (m) {
        if (!inList) { out += '<ul>'; inList = true; }
        out += '<li>' + m[1] + '</li>';
      } else {
        if (inList) { out += '</ul>'; inList = false; }
        if (line.trim()) out += '<p>' + line + '</p>';
      }
    });
    if (inList) out += '</ul>';
    return out.replace(/(\+998[\d\s]{9,14}\d)/g, function (p) { return '<a href="tel:' + p.replace(/\s/g, '') + '">' + p + '</a>'; });
  }
  function productCards(list) {
    if (!list || !list.length) return '';
    return '<div class="chat-prods">' + list.map(function (p) {
      var q = cartQty(p.id);
      return '<div class="chat-prod">' +
        '<img src="' + esc(p.img) + '" alt="">' +
        '<div class="chat-prod-info"><b>' + esc(p.name) + '</b>' +
        '<small>' + esc([p.code, packText(p.pack), p.origin].filter(Boolean).join(' · ')) + '</small>' +
        '<span class="chat-prod-price' + (p.price ? '' : ' muted') + '">' + esc(p.price || t('p.onRequest')) + '</span></div>' +
        (BY_ID[p.id] ? '<button type="button" class="chat-prod-btn' + (q ? ' added' : '') + '" data-add="' + p.id + '">' + (q ? t('chat.added') + ' ✓' : t('chat.add')) + '</button>' : '') +
        '</div>';
    }).join('') + '</div>';
  }
  function renderChat() {
    var html = '<div class="msg bot"><div class="bubble">' + md(t('chat.hello')) + '</div></div>';
    chat.msgs.forEach(function (m) {
      if (m.role === 'user') {
        html += '<div class="msg user"><div class="bubble">' + esc(m.text).replace(/\n/g, '<br>') + '</div></div>';
      } else {
        html += '<div class="msg bot' + (m.error ? ' error' : '') + '"><div class="bubble">' + md(m.text) + '</div>' + productCards(m.products) + '</div>';
      }
    });
    if (chat.done) html += '<div class="chat-done"><svg class="i"><use href="#i-ok"/></svg>' + t('chat.done') + '</div>';
    if (chatBusy) html += '<div class="msg bot typing"><div class="bubble"><span></span><span></span><span></span></div></div>';
    $('#chatMsgs').innerHTML = html;
    $('#chatChips').hidden = chat.msgs.length > 0;
    var body = $('#chatBody');
    body.scrollTop = body.scrollHeight;
  }
  function sendChat(text) {
    text = (text || '').trim();
    if (!text || chatBusy) return;
    chat.msgs.push({ role: 'user', text: text.slice(0, 1000) });
    chatBusy = true;
    saveChat();
    renderChat();
    $('#chatSend').disabled = true;
    var history = chat.msgs.filter(function (m) { return !m.error; }).map(function (m) { return { role: m.role, text: m.text }; });
    post(API + '?api=chat', { lang: lang, session: chat.session, messages: history })
      .then(function (r) {
        chat.session = r.session || chat.session;
        chat.msgs.push({ role: 'model', text: r.reply || '', products: r.products || [] });
        if (r.lead && r.lead.ok) chat.done = true;
      })
      .catch(function (e) {
        chat.msgs.push({ role: 'model', error: true, text: t(e && e.message === '429' ? 'chat.busy' : 'chat.err') });
      })
      .then(function () {
        chatBusy = false;
        $('#chatSend').disabled = false;
        saveChat();
        renderChat();
      });
  }
  function openChat() {
    closeCart();
    chatEl.classList.add('open');
    chatEl.setAttribute('aria-hidden', 'false');
    lockScroll(true);
    renderChat();
    setTimeout(function () { if (window.innerWidth > 640) $('#chatText').focus(); }, 350);
  }
  function closeChat() {
    if (!chatEl.classList.contains('open')) return;
    chatEl.classList.remove('open');
    chatEl.setAttribute('aria-hidden', 'true');
    lockScroll(false);
  }
  var chatText = $('#chatText');
  function autoGrow() { chatText.style.height = 'auto'; chatText.style.height = Math.min(chatText.scrollHeight, 140) + 'px'; }
  chatText.addEventListener('input', autoGrow);
  chatText.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#chatForm').requestSubmit ? $('#chatForm').requestSubmit() : $('#chatSend').click(); }
  });
  $('#chatForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var v = chatText.value;
    chatText.value = '';
    autoGrow();
    sendChat(v);
  });
  $('#chatChips').addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (b) sendChat(b.textContent);
  });
  $('#chatMsgs').addEventListener('click', function (e) {
    var b = e.target.closest('[data-add]');
    if (b) addToCart(+b.getAttribute('data-add'), false);
  });
  $('#chatReset').addEventListener('click', function () {
    chat = { session: '', msgs: [], done: false };
    saveChat();
    renderChat();
  });
  var lb = $('#lightbox'), lbIdx = 0;
  function showCert(i) {
    lbIdx = (i + CERTS.length) % CERTS.length;
    $('#lbImg').setAttribute('data-fallback', certFallback(lbIdx));
    $('#lbImg').src = certSrc(lbIdx);
    $('#lbImg').alt = CERTS[lbIdx].name[lang];
    $('#lbCap').textContent = CERTS[lbIdx].type[lang] + ' — ' + CERTS[lbIdx].name[lang];
  }
  function openLb(i) { showCert(i); lb.classList.add('open'); lb.setAttribute('aria-hidden', 'false'); lockScroll(true); }
  function closeLb() { if (!lb.classList.contains('open')) return; lb.classList.remove('open'); lb.setAttribute('aria-hidden', 'true'); lockScroll(false); }
  $('#certsGrid').addEventListener('click', function (e) {
    var c = e.target.closest('.cert');
    if (c) openLb(+c.getAttribute('data-cert'));
  });
  $('#lbPrev').addEventListener('click', function () { showCert(lbIdx - 1); });
  $('#lbNext').addEventListener('click', function () { showCert(lbIdx + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb || e.target.closest('[data-close-lb]')) closeLb(); });
  var video = $('#processVideo');
  $('#videoPlay').addEventListener('click', function () {
    video.controls = true;
    $('#videoWrap').classList.add('playing');
    var p = video.play();
    if (p && p.catch) p.catch(function () { video.muted = true; video.play(); });
  });
  video.addEventListener('ended', function () {
    video.controls = false;
    $('#videoWrap').classList.remove('playing');
  });
  var TITLE_UZ = document.title;
  var TITLES = { ru: 'CITRIC.UZ — Лучшие ингредиенты для лучшей жизни', en: 'CITRIC.UZ — The Best Ingredients for a Better Life' };
  function applyLang() {
    document.documentElement.lang = lang;
    document.title = TITLES[lang] || TITLE_UZ;
    $$('[data-i18n]').forEach(function (el) { el.innerHTML = t(el.getAttribute('data-i18n')); });
    $$('[data-i18n-ph]').forEach(function (el) { el.setAttribute('placeholder', t(el.getAttribute('data-i18n-ph'))); });
    $$('[data-i18n-title]').forEach(function (el) { el.setAttribute('title', t(el.getAttribute('data-i18n-title'))); });
    $('#langLabel').textContent = lang.toUpperCase();
    $$('.lang-menu [data-lang]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-lang') === lang); });
    renderFilters();
    renderProducts();
    renderSelects();
    renderCerts();
    updateCartUI();
    if (chatEl.classList.contains('open')) renderChat();
    if (pm.classList.contains('open')) { renderPm(); pmFillRest(); }
  }
  var langDd = $('#langDd');
  $('.lang-toggle', langDd).addEventListener('click', function (e) {
    e.stopPropagation();
    var open = langDd.classList.toggle('open');
    this.setAttribute('aria-expanded', open);
  });
  $$('.lang-menu [data-lang]').forEach(function (b) {
    b.addEventListener('click', function () {
      lang = b.getAttribute('data-lang');
      store('local', 'citric-lang', lang);
      langDd.classList.remove('open');
      applyLang();
    });
  });
  $('#filters').addEventListener('click', function (e) {
    var btn = e.target.closest('.filter');
    if (!btn) return;
    activeCat = btn.getAttribute('data-cat');
    renderFilters();
    renderProducts();
  });
  $('#productsMore').addEventListener('click', function () {
    showAll = true;
    renderProducts();
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#langDd')) langDd.classList.remove('open');
    var pb = e.target.closest('.product-btn');
    if (pb) {
      var raw = pb.getAttribute('data-product');
      if (pb.closest('#pm')) { closePm(); setTimeout(function () { addToCart(isNaN(+raw) ? raw : +raw); }, 60); }
      else addToCart(isNaN(+raw) ? raw : +raw);
    } else {
      var card = e.target.closest('#productsGrid .product[data-pid]');
      if (card) openPm(+card.getAttribute('data-pid'));
    }
    if (e.target.closest('[data-open-chat]')) { e.preventDefault(); openChat(); }
    if (e.target.closest('[data-close-chat]')) closeChat();
    if (e.target.closest('[data-open-cart]')) openCart();
    if (e.target.closest('[data-close-cart]')) closeCart();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closePm(); closeChat(); closeCart(); closeLb(); langDd.classList.remove('open'); }
    if (e.key === 'Enter' && e.target.matches && e.target.matches('.product[data-pid]')) {
      if (e.target.closest('#pm')) { openPm(+e.target.getAttribute('data-pid')); pmScroll.scrollTop = 0; }
      else openPm(+e.target.getAttribute('data-pid'));
    }
    if (lb.classList.contains('open')) {
      if (e.key === 'ArrowLeft') showCert(lbIdx - 1);
      if (e.key === 'ArrowRight') showCert(lbIdx + 1);
    }
  });
  var header = $('#header');
  var toTop = $('#toTop');
  function onScroll() {
    var y = window.scrollY;
    header.classList.toggle('scrolled', y > 10);
    toTop.classList.toggle('show', y > 600);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  var nav = $('#nav');
  var burger = $('#burger');
  burger.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    burger.innerHTML = '<svg class="i"><use href="#i-' + (open ? 'x' : 'menu') + '"/></svg>';
  });
  $$('.nav a').forEach(function (a) {
    a.addEventListener('click', function () {
      nav.classList.remove('open');
      burger.innerHTML = '<svg class="i"><use href="#i-menu"/></svg>';
    });
  });
  if ('IntersectionObserver' in window) {
    var navObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        $$('.nav > a').forEach(function (a) { a.classList.toggle('active', a.getAttribute('href') === '#' + en.target.id); });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    ['home', 'about', 'process', 'products', 'certificates', 'partners', 'contact'].forEach(function (id) {
      var s = document.getElementById(id);
      if (s) navObs.observe(s);
    });
    var revObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); revObs.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    $$('.reveal').forEach(function (el) { revObs.observe(el); });
  } else {
    $$('.reveal').forEach(function (el) { el.classList.add('in'); });
  }
  var cards = $$('.t-card');
  var dots = $('#tDots');
  var cur = 0, timer;
  dots.innerHTML = cards.map(function (_, i) { return '<button type="button" aria-label="' + (i + 1) + '"></button>'; }).join('');
  function go(i) {
    cur = (i + cards.length) % cards.length;
    cards.forEach(function (c, k) { c.classList.toggle('active', k === cur); });
    $$('button', dots).forEach(function (d, k) { d.classList.toggle('active', k === cur); });
  }
  function auto() { clearInterval(timer); timer = setInterval(function () { go(cur + 1); }, 6000); }
  $('#tPrev').addEventListener('click', function () { go(cur - 1); auto(); });
  $('#tNext').addEventListener('click', function () { go(cur + 1); auto(); });
  dots.addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (b) { go($$('button', dots).indexOf(b)); auto(); }
  });
  go(0); auto();
  $('#year').textContent = new Date().getFullYear();
  applyLang();
  loadProducts().then(function () {
    cleanCart();
    renderProducts();
    renderSelects();
    updateCartUI();
    // To'g'ridan-to'g'ri havola: index.php#p-12 — mahsulot oynasi ochilgan holda
    var m = /^#p-(\d+)$/.exec(location.hash);
    if (m && BY_ID[+m[1]]) showPm(+m[1]);
  });
})();
