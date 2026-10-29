<?php
/* ============================================================================
   CITRIC.UZ — Boshqaruv paneli (bitta fayl)

   Sahifalar (admin.php?page=...):
     dashboard  — umumiy statistika, grafiklar, so'nggi arizalar
     analytics  — mahsulot tahlili: qaysi tovar yaxshi ketyapti, qaysi biri yo'q
     products   — mahsulotlar ro'yxati (qidiruv, filtr, yashirish, o'chirish)
     product    — mahsulot qo'shish / tahrirlash (rasm yuklash bilan)
     leads      — arizalar (holat, qidiruv, Excel eksport)
     settings   — profil, parol, administratorlar, Telegram bot
   Yadro (baza, Telegram, sozlamalar) index.php dan olinadi.
   ============================================================================ */

define('CITRIC_CORE_ONLY', true);
require __DIR__ . '/index.php';
remember_site_url();
maybe_daily_report(); // 20:00 dan keyin birinchi murojaatda kunlik hisobot (sahifa ochilgandan keyin)

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_name('citric_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');

const SESSION_IDLE = 8 * 3600;
define('CAT_NAMES', array_map(fn($v) => $v[0], CATEGORIES)); // index.php dagi kategoriyalar (o'zbekcha nomlar)
const LEAD_STATUSES = ['new' => ['Yangi', 'blue'], 'confirmed' => ['Tasdiqlandi', 'green'], 'done' => ['Sotildi', 'violet'], 'canceled' => ['Bekor qilindi', 'gray']];
const LEAD_SOURCES = ['chat' => 'AI maslahatchi', 'order' => 'Savat', 'form' => 'Aloqa formasi'];
const RATINGS = ['hot' => ['Yaxshi ketyapti', 'green'], 'mid' => ['Barqaror', 'blue'], 'low' => ['Past talab', 'red']];

/* ============================================================================
   Yordamchilar
   ============================================================================ */
function url(string $page, array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'admin.php?' . http_build_query(['page' => $page] + $params);
}
function redirect(string $u): void { header('Location: ' . $u); exit; }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fmt_num($n): string { return number_format((float)$n, 0, '.', ' '); }

function fmt_date(?string $dt, bool $time = true): string
{
    if (!$dt) return '—';
    $ts = strtotime($dt);
    $m = ['yan', 'fev', 'mar', 'apr', 'may', 'iyn', 'iyl', 'avg', 'sen', 'okt', 'noy', 'dek'];
    $s = date('j', $ts) . ' ' . $m[(int)date('n', $ts) - 1] . (date('Y', $ts) !== date('Y') ? ' ' . date('Y', $ts) : '');
    return $time ? $s . ', ' . date('H:i', $ts) : $s;
}

function current_admin(): ?array
{
    static $a = false;
    if ($a !== false) return $a;
    $a = null;
    if (!empty($_SESSION['admin_id']) && time() - (int)($_SESSION['last_seen'] ?? 0) < SESSION_IDLE) {
        $a = q('SELECT id, username, full_name FROM admins WHERE id = ?', [(int)$_SESSION['admin_id']])->fetch() ?: null;
        $_SESSION['last_seen'] = time();
    }
    return $a;
}

function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
/** php.ini dagi hajm ("8M", "2G") ni baytga o'giradi */
function ini_bytes(string $key): int
{
    $v = trim((string)ini_get($key));
    $n = (int)$v;
    $mult = ['g' => 30, 'm' => 20, 'k' => 10][strtolower(substr($v, -1))] ?? 0;
    return $n << $mult;
}

function upload_limit(): int
{
    $l = array_filter([ini_bytes('upload_max_filesize'), ini_bytes('post_max_size')]);
    return $l ? min($l) : 8 << 20;
}

function check_csrf(): void
{
    // Fayl server chegarasidan katta bo'lsa, PHP butun formani tashlab yuboradi — buni tushunarli qilib aytamiz
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('Yuborilgan fayl juda katta (server chegarasi: ' . round(upload_limit() / 1048576, 1) . " MB). Rasmni qayta tanlang — panel uni avtomatik kichraytiradi.", 'error');
        redirect($_SERVER['REQUEST_URI'] ?? 'admin.php');
    }
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit("Sessiya eskirgan. Sahifani yangilab, qayta urinib ko'ring.");
    }
}

function flash(?string $msg = null, string $type = 'success'): ?array
{
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function icon(string $name): string
{
    static $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'box' => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'plus' => '<path d="M5 12h14M12 5v14"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash' => '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'up' => '<path d="m18 15-6-6-6 6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
        'external' => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'flame' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

function chip(string $label, string $color, string $extra = ''): string
{
    return '<span class="chip chip-' . $color . '">' . $extra . e($label) . '</span>';
}

function delta_badge(?float $d, bool $points = false): string
{
    if ($d === null) return '<span class="delta neutral">—</span>';
    $cls = $d > 0 ? 'up' : ($d < 0 ? 'down' : 'neutral');
    return '<span class="delta ' . $cls . '">' . ($d > 0 ? icon('up') : ($d < 0 ? icon('down') : '')) . abs($d) . ($points ? ' p.p.' : '%') . '</span>';
}

/* ============================================================================
   Maket
   ============================================================================ */
function head_html(string $title, string $bodyClass = ''): void
{
    ?><!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — CITRIC.UZ Admin</title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= ASSETS ?>icons/icon-32.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ==========================================================
   CITRIC.UZ Admin — oq, shaffof (glassmorphism) dizayn
   ========================================================== */
:root {
  --bg: #eef1f6;
  --ink: #1b2533;
  --text: #445163;
  --muted: #7d8899;
  --line: rgba(27, 37, 51, .08);
  --glass: rgba(255, 255, 255, .62);
  --glass-strong: rgba(255, 255, 255, .82);
  --glass-border: rgba(255, 255, 255, .85);
  --primary: #70b32f;
  --primary-dark: #5e9a25;
  --blue: #2f5d9b;
  --blue-soft: rgba(47, 93, 155, .1);
  --green-soft: rgba(112, 179, 47, .1);
  --red: #d64545;
  --red-soft: rgba(214, 69, 69, .1);
  --amber: #c27c0e;
  --amber-soft: rgba(242, 162, 12, .14);
  --violet: #6b4fc9;
  --violet-soft: rgba(107, 79, 201, .1);
  --radius: 18px;
  --shadow: 0 1px 2px rgba(27, 37, 51, .04), 0 8px 28px rgba(27, 37, 51, .06);
  --shadow-lg: 0 24px 60px rgba(27, 37, 51, .14);
  --ease: cubic-bezier(.22, .8, .3, 1);
}

* { box-sizing: border-box; }
html, body { margin: 0; }
body {
  font-family: "Manrope", system-ui, -apple-system, "Segoe UI", sans-serif;
  font-size: 14.5px; line-height: 1.5; color: var(--text);
  background: linear-gradient(160deg, #f5f7fb 0%, #e9edf4 55%, #eef1f6 100%) fixed;
  min-height: 100vh; -webkit-font-smoothing: antialiased;
}
a { color: var(--blue); text-decoration: none; }
a:hover { text-decoration: underline; }
h1, h2, h3 { margin: 0; color: var(--ink); line-height: 1.25; }
p { margin: 0; }
img { max-width: 100%; }
button, input, select, textarea { font: inherit; color: inherit; }
code { font-family: ui-monospace, Consolas, monospace; font-size: 13px; background: var(--blue-soft); color: var(--blue); padding: 2px 6px; border-radius: 6px; }
.muted { color: var(--muted); }
.small { font-size: 13px; }
.block { display: block; }
.nowrap { white-space: nowrap; }
.ic { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

/* Orqa fondagi yumshoq rangli dog'lar — shisha effekti uchun */
.bg-blobs { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
.bg-blobs span { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .55; }
.bg-blobs span:nth-child(1) { width: 520px; height: 520px; left: -120px; top: -140px; background: #cfdcf0; }
.bg-blobs span:nth-child(2) { width: 460px; height: 460px; right: -100px; top: 30%; background: #d6ead9; }
.bg-blobs span:nth-child(3) { width: 380px; height: 380px; left: 35%; bottom: -160px; background: #f3e4c4; opacity: .4; }

.glass {
  background: var(--glass);
  backdrop-filter: blur(18px) saturate(160%); -webkit-backdrop-filter: blur(18px) saturate(160%);
  border: 1px solid var(--glass-border);
  box-shadow: var(--shadow);
}

/* ---------- Buttons ---------- */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 12px;
  border: 1px solid transparent; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none !important;
  transition: background .2s, box-shadow .2s, transform .2s var(--ease), color .2s, border-color .2s; white-space: nowrap;
}
.btn .ic { width: 16px; height: 16px; }
.btn-primary { background: var(--primary); color: #fff; box-shadow: 0 6px 16px rgba(112, 179, 47, .25); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
.btn-ghost { background: var(--glass-strong); color: var(--ink); border-color: var(--line); }
.btn-ghost:hover { background: #fff; border-color: rgba(27, 37, 51, .16); }
.btn-danger { background: var(--red); color: #fff; }
.btn-danger:hover { background: #bf3434; }
.btn-sm { padding: 7px 12px; font-size: 13px; border-radius: 10px; }
.btn-block { width: 100%; }
.danger-text { color: var(--red) !important; }
.icon-btn {
  width: 40px; height: 40px; border-radius: 12px; border: 1px solid var(--line); background: var(--glass-strong); color: var(--ink);
  display: inline-grid; place-items: center; cursor: pointer; position: relative; transition: background .2s, color .2s; text-decoration: none !important;
}
.icon-btn:hover { background: #fff; }
.icon-btn.sm { width: 34px; height: 34px; border-radius: 10px; }
.icon-btn.sm .ic { width: 16px; height: 16px; }
.icon-btn.danger:hover { background: var(--red-soft); color: var(--red); border-color: transparent; }

/* ---------- App layout ---------- */
.app { display: grid; grid-template-columns: 264px 1fr; min-height: 100vh; }
.sidebar {
  position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; padding: 26px 18px;
  background: linear-gradient(180deg, rgba(255,255,255,.72), rgba(236,241,248,.55));
  backdrop-filter: blur(24px) saturate(160%); -webkit-backdrop-filter: blur(24px) saturate(160%);
  border-right: 1px solid var(--glass-border); box-shadow: 4px 0 30px rgba(27, 37, 51, .05); z-index: 30;
}
.brand img { height: 42px; display: block; margin-left: 8px; }
.brand-sub { font-size: 11.5px; letter-spacing: .14em; text-transform: uppercase; font-weight: 800; color: var(--muted); margin: 10px 0 26px 10px; }
.side-nav { display: grid; gap: 4px; }
.side-nav a, .side-link {
  display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 12px; color: var(--text); font-weight: 600;
  text-decoration: none !important; transition: background .2s, color .2s; border: 1px solid transparent;
}
.side-nav a:hover, .side-link:hover { background: rgba(255,255,255,.7); color: var(--ink); }
.side-nav a.active { background: rgba(255,255,255,.95); color: var(--ink); border-color: var(--glass-border); box-shadow: 0 4px 14px rgba(27, 37, 51, .07); }
.side-nav a.active .ic { color: var(--primary); }
.side-nav .pill { margin-left: auto; background: var(--primary); color: #fff; font-size: 11.5px; padding: 1px 8px; border-radius: 99px; }
.side-foot { margin-top: auto; display: grid; gap: 4px; border-top: 1px solid var(--line); padding-top: 14px; }

.sidebar-backdrop { display: none; }
.main { min-width: 0; display: flex; flex-direction: column; }
.topbar {
  position: sticky; top: 0; z-index: 20; display: flex; align-items: center; gap: 14px; padding: 18px 32px;
  background: rgba(238, 241, 246, .72); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(255,255,255,.6);
}
.topbar h1 { font-size: 26px; font-weight: 800; letter-spacing: -.02em; margin-right: auto; }
.burger { display: none; }
.search { position: relative; width: min(340px, 34vw); }
.search .ic { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); }
.search input, .search-inline input {
  width: 100%; padding: 10px 14px 10px 40px; border-radius: 12px; border: 1px solid var(--line); background: var(--glass-strong); outline: none;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
.search input:focus, .search-inline input:focus { background: #fff; border-color: rgba(47,93,155,.35); box-shadow: 0 0 0 4px rgba(47, 93, 155, .1); }
.bell .dot {
  position: absolute; top: -5px; right: -5px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 99px; background: var(--red);
  color: #fff; font-size: 10.5px; font-weight: 800; display: grid; place-items: center; border: 2px solid #eef1f6;
}
.profile { position: relative; display: flex; align-items: center; gap: 10px; padding: 4px 10px 4px 4px; border-radius: 14px; cursor: pointer; outline: none; }
.profile:hover, .profile:focus-within { background: rgba(255,255,255,.7); }
.avatar {
  width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: #fff;
  background: linear-gradient(135deg, #2f5d9b, #70b32f); box-shadow: 0 4px 12px rgba(47, 93, 155, .3);
}
.avatar.sm { width: 34px; height: 34px; font-size: 13px; }
.profile-name { display: flex; flex-direction: column; font-weight: 700; color: var(--ink); font-size: 13.5px; line-height: 1.2; }
.profile-name small { color: var(--muted); font-weight: 500; font-size: 12px; }
.profile-menu {
  position: absolute; right: 0; top: calc(100% + 8px); min-width: 190px; padding: 6px; border-radius: 14px; background: #fff;
  box-shadow: var(--shadow-lg); border: 1px solid var(--line); opacity: 0; visibility: hidden; transform: translateY(-6px); transition: all .2s var(--ease);
}
.profile:focus-within .profile-menu, .profile:hover .profile-menu { opacity: 1; visibility: visible; transform: none; }
.profile-menu a { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; color: var(--ink); font-weight: 600; text-decoration: none; }
.profile-menu a:hover { background: var(--bg); }

.content { padding: 8px 32px 40px; display: grid; gap: 22px; }
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.back-link { font-weight: 700; color: var(--text); }

/* ---------- Cards ---------- */
.card { border-radius: var(--radius); padding: 22px; min-width: 0; }
.card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.card-head h2 { font-size: 17px; font-weight: 800; }
.card-head .muted { font-size: 13px; margin-top: 2px; }
.grid-2 { display: grid; grid-template-columns: 1.6fr 1fr; gap: 22px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
.settings-grid { grid-template-columns: 1fr 1fr; align-items: start; }

/* KPI */
.kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 22px; }
.kpi { padding: 18px 20px 12px; display: flex; flex-direction: column; transition: transform .3s var(--ease), box-shadow .3s; }
.kpi:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.kpi-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.kpi-label { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--ink); font-size: 14px; }
.kpi-label .ic { color: var(--muted); width: 16px; height: 16px; }
.kpi-value { font-size: 32px; font-weight: 800; color: var(--ink); letter-spacing: -.02em; margin: 6px 0 4px; }
.spark { width: 100% !important; height: 56px !important; }
.delta { display: inline-flex; align-items: center; gap: 2px; font-size: 12px; font-weight: 800; padding: 3px 8px; border-radius: 99px; white-space: nowrap; }
.delta .ic { width: 12px; height: 12px; stroke-width: 2.6; }
.delta.up { background: var(--green-soft); color: var(--primary); }
.delta.down { background: var(--red-soft); color: var(--red); }
.delta.neutral { background: rgba(27,37,51,.06); color: var(--muted); }

.chart-card .chart-wrap { position: relative; height: 300px; }
.legend { display: flex; gap: 16px; flex-wrap: wrap; font-size: 13px; font-weight: 600; }
.legend span { display: inline-flex; align-items: center; gap: 7px; }
.legend i { width: 10px; height: 10px; border-radius: 3px; }

.seg { display: inline-flex; background: rgba(255,255,255,.55); border: 1px solid var(--glass-border); padding: 4px; border-radius: 12px; gap: 2px; box-shadow: var(--shadow); }
.seg a { padding: 7px 14px; border-radius: 9px; color: var(--text); font-weight: 700; font-size: 13px; text-decoration: none; white-space: nowrap; }
.seg a b { margin-left: 4px; color: var(--muted); font-weight: 700; }
.seg a:hover { color: var(--ink); }
.seg a.active { background: #fff; color: var(--ink); box-shadow: 0 2px 8px rgba(27,37,51,.08); }
.seg-wrap { flex-wrap: wrap; }

/* Donut */
.donut-wrap { position: relative; height: 210px; }
.donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
.donut-center b { font-size: 28px; color: var(--ink); line-height: 1; }
.donut-center small { color: var(--muted); }
.donut-legend { list-style: none; padding: 0; margin: 18px 0 0; display: grid; gap: 10px; }
.donut-legend li { display: flex; align-items: center; gap: 10px; font-weight: 600; }
.donut-legend i { width: 10px; height: 10px; border-radius: 50%; }
.donut-legend b { margin-left: auto; color: var(--ink); }

/* ---------- Tables ---------- */
.table-wrap { overflow-x: auto; margin: 0 -6px; }
.table { width: 100%; border-collapse: collapse; }
.table th { text-align: left; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; padding: 10px 10px; border-bottom: 1px solid var(--line); white-space: nowrap; }
.table td { padding: 12px 10px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.table tr:last-child td { border-bottom: 0; }
.table tbody tr { transition: background .15s; }
.table tbody tr:hover { background: rgba(255,255,255,.55); }
.table .num { text-align: right; font-variant-numeric: tabular-nums; }
.table td small { color: var(--muted); font-size: 12.5px; }
.row-muted td { opacity: .55; }
.prod-cell { display: flex; align-items: center; gap: 12px; min-width: 200px; }
.prod-cell img { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; background: #fff; border: 1px solid var(--line); flex-shrink: 0; }
.prod-cell.lg img { width: 52px; height: 52px; border-radius: 12px; }
.prod-cell b { display: block; color: var(--ink); font-weight: 700; line-height: 1.3; }
.prod-cell small { display: block; }
.actions { white-space: nowrap; text-align: right; }
.actions .icon-btn { margin-left: 4px; }

.grid-2 .prod-cell { min-width: 170px; }
.grid-2 .table td, .grid-2 .table th { padding-left: 8px; padding-right: 8px; }
.bar { width: 90px; height: 7px; border-radius: 99px; background: rgba(47, 93, 155, .12); overflow: hidden; }
.bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #6f93c4, #2f5d9b); }

.chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 99px; font-size: 12.5px; font-weight: 700; white-space: nowrap; border: 0; }
.chip .ic { width: 13px; height: 13px; }
.chip-green { background: var(--green-soft); color: var(--primary); }
.chip-blue { background: var(--blue-soft); color: var(--blue); }
.chip-red { background: var(--red-soft); color: var(--red); }
.chip-amber { background: var(--amber-soft); color: var(--amber); }
.chip-violet { background: var(--violet-soft); color: var(--violet); }
.chip-gray { background: rgba(27,37,51,.07); color: var(--text); }
.chip-btn { cursor: pointer; opacity: .65; transition: opacity .2s, box-shadow .2s; }
.chip-btn:hover { opacity: 1; }
.chip-btn.current { opacity: 1; box-shadow: 0 0 0 2px currentColor inset; }

.src { font-size: 12.5px; font-weight: 700; padding: 4px 10px; border-radius: 8px; white-space: nowrap; background: rgba(27,37,51,.06); color: var(--text); }
.src-chat { background: var(--amber-soft); color: var(--amber); }
.src-order { background: var(--blue-soft); color: var(--blue); }
.src-form { background: var(--violet-soft); color: var(--violet); }

.empty { padding: 34px 20px; text-align: center; color: var(--muted); border: 1.5px dashed rgba(27,37,51,.12); border-radius: 14px; }

/* Toolbar */
.toolbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.search-inline { position: relative; flex: 1; min-width: 220px; }
.search-inline .ic { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); }
.toolbar select { padding: 10px 14px; border-radius: 12px; border: 1px solid var(--line); background: var(--glass-strong); }

/* Switch */
.switch { width: 44px; height: 26px; border-radius: 99px; border: 0; background: rgba(27,37,51,.15); position: relative; cursor: pointer; transition: background .25s; padding: 0; }
.switch span { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.18); transition: transform .25s var(--ease); }
.switch.on { background: var(--primary); }
.switch.on span { transform: translateX(18px); }
.switch-input { appearance: none; -webkit-appearance: none; width: 44px; height: 26px; border-radius: 99px; background: rgba(27,37,51,.15); position: relative; cursor: pointer; transition: background .25s; flex-shrink: 0; margin: 0; }
.switch-input::after { content: ""; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.18); transition: transform .25s var(--ease); }
.switch-input:checked { background: var(--primary); }
.switch-input:checked::after { transform: translateX(18px); }

/* ---------- Forms ---------- */
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.field > span { font-size: 13px; font-weight: 700; color: var(--ink); }
.field input, .field select, .field textarea {
  width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid rgba(27,37,51,.12); background: rgba(255,255,255,.75); outline: none;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
.field textarea { resize: vertical; min-height: 110px; line-height: 1.55; }
.field input:focus, .field select:focus, .field textarea:focus { background: #fff; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(112,179,47,.12); }
.field input:disabled { background: rgba(27,37,51,.04); color: var(--muted); }
.field.has-error input, .field.has-error textarea { border-color: var(--red); }
.field-error { color: var(--red); font-weight: 600; font-size: 12.5px; }
.hint { color: var(--muted); font-size: 12.5px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.row-3 { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 14px; }
.alert { padding: 12px 16px; border-radius: 12px; font-weight: 600; margin-bottom: 4px; }
.alert-error { background: var(--red-soft); color: var(--red); }
.alert-info { background: var(--blue-soft); color: var(--blue); font-weight: 500; margin: 14px 0; }

.edit-grid { display: grid; grid-template-columns: 1fr 360px; gap: 22px; align-items: start; }
.edit-main, .edit-side { display: grid; gap: 22px; min-width: 0; }
.edit-side { position: sticky; top: 96px; }
.tabs { display: inline-flex; gap: 4px; padding: 4px; border-radius: 12px; background: rgba(27,37,51,.05); margin-bottom: 18px; }
.tabs button { border: 0; background: transparent; padding: 7px 16px; border-radius: 9px; font-weight: 700; cursor: pointer; color: var(--text); }
.tabs button.active { background: #fff; color: var(--ink); box-shadow: 0 2px 8px rgba(27,37,51,.08); }
.dropzone {
  position: relative; display: grid; place-items: center; aspect-ratio: 1; border-radius: 16px; overflow: hidden; cursor: pointer;
  border: 2px dashed rgba(27,37,51,.15); background: rgba(255,255,255,.6); transition: border-color .2s, background .2s;
}
.dropzone:hover, .dropzone.drag { border-color: var(--primary); background: rgba(112,179,47,.05); }
.dropzone.has-error { border-color: var(--red); }
.dropzone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.dropzone img { width: 100%; height: 100%; object-fit: contain; background: #fff; }
.dz-text { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; padding: 20px; color: var(--muted); pointer-events: none; }
.dz-text .ic { width: 36px; height: 36px; color: var(--primary); stroke-width: 1.4; }
.dz-text b { color: var(--ink); }
.toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 4px 0 16px; cursor: pointer; }
.toggle-row b { display: block; color: var(--ink); }
.toggle-row small { color: var(--muted); font-size: 12.5px; }
.sticky-actions { display: flex; gap: 10px; justify-content: flex-end; }
.sticky-actions .btn { flex: 1; }

/* ---------- Leads ---------- */
.lead-list { display: grid; gap: 10px; }
.lead { border-radius: 14px; background: rgba(255,255,255,.55); border: 1px solid var(--glass-border); transition: box-shadow .2s, background .2s; }
.lead[open] { background: rgba(255,255,255,.88); box-shadow: var(--shadow); }
.lead summary { display: grid; grid-template-columns: minmax(170px, 1.1fr) 2fr auto auto 110px; align-items: center; gap: 14px; padding: 14px 16px; cursor: pointer; list-style: none; }
.lead summary::-webkit-details-marker { display: none; }
.lead-main b { display: block; color: var(--ink); }
.lead-phone { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; font-weight: 600; }
.lead-phone .ic { width: 13px; height: 13px; }
.lead-products { display: flex; flex-wrap: wrap; gap: 6px; min-width: 0; }
.mini-prod { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px 3px 3px; border-radius: 99px; background: #fff; border: 1px solid var(--line); font-size: 12.5px; font-weight: 600; color: var(--ink); }
.mini-prod img { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; }
.lead-date { text-align: right; font-size: 13px; }
.lead-body { padding: 4px 16px 16px; border-top: 1px solid var(--line); }
.lead-body dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin: 14px 0; }
.lead-body dt { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.lead-body dd { margin: 3px 0 0; color: var(--ink); font-weight: 600; }
.lead-note { background: rgba(47,93,155,.06); padding: 12px 14px; border-radius: 12px; color: var(--ink); margin-bottom: 14px; }
.lead-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.inline-form { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pager { display: flex; gap: 6px; justify-content: center; margin-top: 18px; }
.pager a { min-width: 36px; height: 36px; display: grid; place-items: center; border-radius: 10px; background: rgba(255,255,255,.6); color: var(--ink); font-weight: 700; text-decoration: none; }
.pager a.active { background: var(--ink); color: #fff; }

/* Analytics */
.insight { display: flex; gap: 14px; align-items: center; }
.insight h3 { font-size: 15px; margin-bottom: 3px; }
.insight p { font-size: 13.5px; }
.insight-ic { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; flex-shrink: 0; }
.insight-ic .ic { width: 22px; height: 22px; }
.insight-green .insight-ic { background: var(--green-soft); color: var(--primary); }
.insight-red .insight-ic { background: var(--red-soft); color: var(--red); }
.insight-blue .insight-ic { background: var(--blue-soft); color: var(--blue); }
.advice-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
.advice-list li { display: flex; align-items: center; gap: 14px; padding: 12px; border-radius: 14px; background: rgba(255,255,255,.6); }
.advice-list img { width: 46px; height: 46px; border-radius: 12px; object-fit: cover; }
.advice-list b { color: var(--ink); }
.advice-list p { font-size: 13.5px; color: var(--text); }
.advice-list .btn { margin-left: auto; }

/* Settings */
.status-list { list-style: none; padding: 0; margin: 0 0 14px; display: grid; gap: 10px; }
.status-list li { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--line); }
.admin-list { list-style: none; padding: 0; margin: 0 0 14px; display: grid; gap: 8px; }
.admin-list li { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 12px; background: rgba(255,255,255,.55); }
.admin-list li > span:nth-child(2) { flex: 1; }
.admin-list b { display: block; color: var(--ink); }
.admin-list small { color: var(--muted); font-size: 12.5px; }
.add-admin summary { list-style: none; display: inline-flex; }
.add-admin summary::-webkit-details-marker { display: none; }
.add-admin form { margin-top: 16px; }

/* Toast */
.toast {
  position: fixed; right: 28px; bottom: 28px; z-index: 100; display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-radius: 14px;
  background: var(--ink); color: #fff; font-weight: 600; box-shadow: var(--shadow-lg); animation: toastIn .4s var(--ease), toastOut .4s var(--ease) 4.2s forwards;
}
.toast .ic { width: 18px; height: 18px; }
.toast-success .ic { color: #7ee09a; }
.toast-error { background: #7a1f1f; }
@keyframes toastIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
@keyframes toastOut { to { opacity: 0; transform: translateY(14px); visibility: hidden; } }

/* Modal */
.modal { position: fixed; inset: 0; z-index: 90; display: grid; place-items: center; padding: 20px; background: rgba(27, 37, 51, .35); backdrop-filter: blur(4px); }
.modal[hidden] { display: none; }
.modal-card { width: 100%; max-width: 420px; padding: 28px; border-radius: 20px; text-align: center; background: rgba(255,255,255,.92); animation: toastIn .3s var(--ease); }
.modal-card h3 { font-size: 19px; margin: 14px 0 8px; }
.modal-card p { margin-bottom: 8px; }
.modal-ic { width: 58px; height: 58px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto; }
.modal-ic.danger { background: var(--red-soft); color: var(--red); }
.modal-ic .ic { width: 26px; height: 26px; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.modal-actions .btn { flex: 1; }

/* ---------- Login ---------- */
.login-page { display: grid; place-items: center; padding: 24px; }
.login-card { width: 100%; max-width: 420px; padding: 38px 34px; border-radius: 24px; text-align: center; background: rgba(255,255,255,.7); }
.login-logo { height: 54px; margin-bottom: 22px; }
.login-card h1 { font-size: 22px; }
.login-card > .muted { margin: 6px 0 24px; }
.login-form { text-align: left; }
.login-form .btn { padding: 13px; font-size: 15px; margin-top: 6px; }
.pass-wrap { position: relative; display: block; }
.pass-toggle { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: var(--blue); font-weight: 700; font-size: 12.5px; cursor: pointer; padding: 6px; }
.login-card .back-link { display: inline-block; margin-top: 22px; font-size: 13.5px; }
.login-card .alert { margin-bottom: 16px; text-align: left; }

/* ---------- Responsive ---------- */
@media (max-width: 1280px) {
  .kpis { grid-template-columns: repeat(2, 1fr); }
  .grid-2 { grid-template-columns: 1fr; }
  .lead summary { grid-template-columns: 1fr 1.6fr auto auto; }
  .lead-date { display: none; }
}
@media (max-width: 1024px) {
  .app { grid-template-columns: 1fr; }
  .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 270px; transform: translateX(-100%); transition: transform .3s var(--ease); background: rgba(246, 248, 252, .97); }
  .sidebar.open { transform: none; box-shadow: var(--shadow-lg); }
  .sidebar-backdrop { display: block; position: fixed; inset: 0; z-index: 25; background: rgba(27,37,51,.3); opacity: 0; visibility: hidden; transition: all .3s; }
  .sidebar.open + .sidebar-backdrop { opacity: 1; visibility: visible; }
  .burger { display: inline-grid; }
  .edit-grid { grid-template-columns: 1fr; }
  .edit-side { position: static; }
  .grid-3, .settings-grid { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  .topbar { padding: 12px 16px; gap: 10px; flex-wrap: wrap; }
  .topbar h1 { font-size: 20px; }
  .search { order: 5; width: 100%; }
  .profile-name, .profile > .ic { display: none; }
  .content { padding: 8px 16px 32px; gap: 16px; }
  .kpis { grid-template-columns: 1fr 1fr; gap: 12px; }
  .kpi-value { font-size: 24px; }
  .card { padding: 16px; }
  .row-2, .row-3 { grid-template-columns: 1fr; }
  .lead summary { grid-template-columns: 1fr auto; }
  .lead-products, .lead summary .src { display: none; }
  .chart-card .chart-wrap { height: 240px; }
}

.btn-row { display: flex; gap: 10px; flex-wrap: wrap; }

/* Rasm yuklash holati */
.upload-status { margin-top: 12px; padding: 10px 14px; border-radius: 12px; font-size: 13px; font-weight: 600; background: var(--blue-soft); color: var(--blue); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.upload-status[hidden] { display: none; }
.upload-status.ok { background: var(--green-soft); color: var(--primary); }
.upload-status.error { background: var(--red-soft); color: var(--red); }
.upload-status .muted { font-weight: 500; }
.spinner { width: 14px; height: 14px; border-radius: 50%; border: 2px solid currentColor; border-right-color: transparent; animation: spin .7s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.dropzone.processing { opacity: .7; pointer-events: none; }
.btn.loading { opacity: .75; pointer-events: none; }
.btn:disabled { opacity: .6; cursor: wait; }

/* Kunlik hisobot sozlamalari */
.report-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.report-row .field { margin-bottom: 0; }
.report-row input[type="time"] { width: 140px; }
.cron-box { margin-top: 12px; padding: 12px 14px; border-radius: 12px; background: rgba(27,37,51,.04); font-size: 12.5px; word-break: break-all; }

</style>
</head>
<body class="<?= e($bodyClass) ?>">
<div class="bg-blobs" aria-hidden="true"><span></span><span></span><span></span></div>
<?php
}

function layout_start(string $title, string $active, array $admin): void
{
    $newLeads = (int)q("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
    $nav = [
        'dashboard' => ['Boshqaruv paneli', 'dashboard'],
        'analytics' => ['Mahsulot tahlili', 'chart'],
        'products' => ['Mahsulotlar', 'box'],
        'leads' => ['Arizalar', 'inbox'],
        'settings' => ['Sozlamalar', 'settings'],
    ];
    $flash = flash();
    head_html($title);
    ?>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <a href="<?= url('dashboard') ?>" class="brand"><img src="<?= ASSETS ?>logo.png" alt="CITRIC.UZ"></a>
    <div class="brand-sub">Boshqaruv tizimi</div>
    <nav class="side-nav">
      <?php foreach ($nav as $key => [$label, $ic]): ?>
        <a href="<?= url($key) ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= icon($ic) ?><span><?= $label ?></span>
          <?php if ($key === 'leads' && $newLeads > 0): ?><b class="pill"><?= $newLeads ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="./" target="_blank" class="side-link"><?= icon('external') ?><span>Saytni ochish</span></a>
      <a href="<?= url('logout') ?>" class="side-link"><?= icon('logout') ?><span>Chiqish</span></a>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <main class="main">
    <header class="topbar">
      <button class="icon-btn burger" id="burger" type="button" aria-label="Menyu"><?= icon('menu') ?></button>
      <h1><?= e($title) ?></h1>
      <form class="search" action="admin.php" method="get" role="search">
        <input type="hidden" name="page" value="leads"><?= icon('search') ?>
        <input type="search" name="q" placeholder="Mijoz, telefon yoki ariza raqami..." value="<?= e($_GET['q'] ?? '') ?>">
      </form>
      <a href="<?= url('leads', ['status' => 'new']) ?>" class="icon-btn bell" title="Yangi arizalar"><?= icon('bell') ?><?php if ($newLeads > 0): ?><span class="dot"><?= $newLeads > 99 ? '99+' : $newLeads ?></span><?php endif; ?></a>
      <div class="profile" tabindex="0">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($admin['full_name'] ?: $admin['username'], 0, 1))) ?></span>
        <span class="profile-name"><?= e($admin['full_name'] ?: $admin['username']) ?><small>Administrator</small></span>
        <?= icon('down') ?>
        <div class="profile-menu">
          <a href="<?= url('settings') ?>"><?= icon('settings') ?>Sozlamalar</a>
          <a href="<?= url('logout') ?>"><?= icon('logout') ?>Chiqish</a>
        </div>
      </div>
    </header>
    <?php if ($flash): ?><div class="toast toast-<?= e($flash[1]) ?>" role="status"><?= icon($flash[1] === 'error' ? 'x' : 'check') ?><span><?= e($flash[0]) ?></span></div><?php endif; ?>
    <div class="content">
<?php
}

function layout_end(bool $charts = false): void
{
    ?>
    </div>
  </main>
</div>
<?php if ($charts): ?><script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script><?php endif; ?>
<script>
/* CITRIC.UZ Admin — interaktivlik va grafiklar */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- Mobil sidebar ---------- */
  var sidebar = $('#sidebar');
  if ($('#burger')) $('#burger').addEventListener('click', function () { sidebar.classList.add('open'); });
  if ($('#sidebarBackdrop')) $('#sidebarBackdrop').addEventListener('click', function () { sidebar.classList.remove('open'); });

  /* ---------- Parolni ko'rsatish ---------- */
  $$('[data-toggle-pass]').forEach(function (b) {
    b.addEventListener('click', function () {
      var inp = document.getElementById(b.getAttribute('data-toggle-pass'));
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      b.textContent = show ? 'Yashirish' : "Ko'rsatish";
    });
  });

  /* ---------- Til tablari (mahsulot formasi) ---------- */
  $$('[data-tabs]').forEach(function (tabs) {
    var card = tabs.parentElement;
    tabs.addEventListener('click', function (e) {
      var b = e.target.closest('[data-tab]');
      if (!b) return;
      $$('[data-tab]', tabs).forEach(function (x) { x.classList.toggle('active', x === b); });
      $$('[data-pane]', card).forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-tab'); });
    });
  });

  /* ---------- Rasm yuklash: istalgan hajmdagi rasm brauzerda optimallashtiriladi ----------
     Katta rasm (masalan 20 MB telefon surati) serverga yuborilishidan oldin 1600 px gacha
     kichraytiriladi va WebP (bo'lmasa JPEG) ga o'giriladi — server limitiga tushmaydi. */
  var dz = $('#dropzone'), input = $('#imageInput'), preview = $('#imagePreview'), status = $('#uploadStatus');
  if (dz && input) {
    var MAX_SIDE = 1600, TARGET = 900 * 1024;
    var form = dz.closest('form'), submitBtn = form && $('button[type="submit"]', form);
    var busy = false;
    var fmt = function (b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; };
    var setStatus = function (html, type) {
      if (!status) return;
      status.className = 'upload-status' + (type ? ' ' + type : '');
      status.innerHTML = html;
      status.hidden = !html;
    };
    var webpOk = (function () {
      try { var c = document.createElement('canvas'); c.width = c.height = 1; return c.toDataURL('image/webp').indexOf('data:image/webp') === 0; } catch (e) { return false; }
    })();
    var loadImage = function (file) {
      if (window.createImageBitmap) return createImageBitmap(file).catch(function () { return viaImg(file); });
      return viaImg(file);
    };
    var viaImg = function (file) {
      return new Promise(function (res, rej) {
        var url = URL.createObjectURL(file), im = new Image();
        im.onload = function () { res(im); };
        im.onerror = function () { URL.revokeObjectURL(url); rej(new Error('decode')); };
        im.src = url;
      });
    };
    var toBlob = function (canvas, type, q) {
      return new Promise(function (res) { canvas.toBlob(res, type, q); });
    };
    var optimize = function (file) {
      if (!/^image\/(jpeg|png|webp|gif|bmp)$/i.test(file.type)) {
        return Promise.reject(new Error(/hei[cf]/i.test(file.type + file.name)
          ? "HEIC (iPhone) formati brauzerda ochilmaydi. Iltimos, rasmni JPG ga o'tkazib yuklang (iPhone: Sozlamalar → Kamera → Formatlar → «Eng mos»)."
          : "Bu fayl rasm emas yoki formati qo'llab-quvvatlanmaydi. JPG, PNG yoki WEBP yuklang."));
      }
      return loadImage(file).then(function (img) {
        var w = img.width, h = img.height, scale = Math.min(1, MAX_SIDE / Math.max(w, h));
        // Kichik va allaqachon siqilgan rasm — o'zgarishsiz
        if (scale === 1 && file.size <= TARGET && /jpeg|webp/i.test(file.type)) return { file: file, w: w, h: h, changed: false };
        var cw = Math.round(w * scale), ch = Math.round(h * scale);
        var canvas = document.createElement('canvas');
        canvas.width = cw; canvas.height = ch;
        var ctx = canvas.getContext('2d');
        var type = webpOk ? 'image/webp' : 'image/jpeg';
        if (type === 'image/jpeg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cw, ch); } // JPEG'da shaffof joy oq bo'lsin
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, 0, 0, cw, ch);
        return toBlob(canvas, type, 0.86).then(function (blob) {
          return blob && blob.size > TARGET ? toBlob(canvas, type, 0.74) : blob;
        }).then(function (blob) {
          if (!blob) throw new Error("Rasmni qayta ishlab bo'lmadi. Boshqa rasm tanlang.");
          var name = (file.name.replace(/\.[^.]+$/, '') || 'rasm') + (type === 'image/webp' ? '.webp' : '.jpg');
          return { file: new File([blob], name, { type: type, lastModified: Date.now() }), w: cw, h: ch, changed: true };
        });
      });
    };
    var handle = function (file) {
      if (!file) return;
      busy = true;
      if (submitBtn) submitBtn.disabled = true;
      dz.classList.add('processing');
      setStatus('<span class="spinner"></span> Rasm tayyorlanmoqda… (' + fmt(file.size) + ')');
      optimize(file).then(function (r) {
        var dt = new DataTransfer();
        dt.items.add(r.file);
        input.files = dt.files;
        preview.src = URL.createObjectURL(r.file);
        preview.hidden = false;
        var t = $('.dz-text', dz); if (t) t.hidden = true;
        setStatus('✓ Tayyor: ' + r.w + '×' + r.h + ' px, ' + (r.changed ? fmt(file.size) + ' → <b>' + fmt(r.file.size) + '</b>' : '<b>' + fmt(r.file.size) + '</b>') +
          (r.h / r.w < 1.15 ? ' <span class="muted">· Tavsiya: vertikal 4:5 rasm (masalan 800×1000)</span>' : ''), 'ok');
      }).catch(function (err) {
        input.value = '';
        setStatus('⚠ ' + (err && err.message && err.message.length > 12 ? err.message : "Rasmni o'qib bo'lmadi. Boshqa rasm tanlang."), 'error');
      }).then(function () {
        busy = false;
        dz.classList.remove('processing');
        if (submitBtn) submitBtn.disabled = false;
      });
    };
    input.addEventListener('change', function () { if (input.files[0]) handle(input.files[0]); });
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function () { dz.classList.remove('drag'); }); });
    dz.addEventListener('drop', function (e) {
      e.preventDefault();
      if (e.dataTransfer.files.length) handle(e.dataTransfer.files[0]);
    });
    if (form) form.addEventListener('submit', function (e) {
      if (busy) { e.preventDefault(); return; }
      if (submitBtn) { submitBtn.classList.add('loading'); submitBtn.lastChild.textContent = ' Saqlanmoqda…'; }
    });
  }

  /* ---------- O'chirishni tasdiqlash oynasi ---------- */
  var modal = $('#deleteModal');
  if (modal) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-delete]');
      if (b) {
        $('#deleteId').value = b.getAttribute('data-delete');
        $('#deleteName').textContent = '«' + b.getAttribute('data-name') + '»';
        modal.hidden = false;
      }
      if (e.target === modal || e.target.closest('[data-close-modal]')) modal.hidden = true;
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.hidden = true; });
  }

  /* ---------- Grafiklar ---------- */
  if (!window.Chart) return;
  Chart.defaults.font.family = 'Manrope, system-ui, sans-serif';
  Chart.defaults.color = '#7d8899';
  var BLUE = '#2f5d9b', GREEN = '#70b32f', AMBER = '#ffa800';

  function gradient(ctx, color, h) {
    var g = ctx.createLinearGradient(0, 0, 0, h || 300);
    g.addColorStop(0, color + '40');
    g.addColorStop(1, color + '00');
    return g;
  }
  var tooltip = {
    backgroundColor: 'rgba(255,255,255,.96)', titleColor: '#1b2533', bodyColor: '#445163', borderColor: 'rgba(27,37,51,.1)',
    borderWidth: 1, padding: 12, cornerRadius: 12, boxPadding: 4, usePointStyle: true
  };

  var data = $('#chartData') ? JSON.parse($('#chartData').textContent) : null;
  if (data) {
    // KPI mini grafiklari
    $$('canvas[data-spark]').forEach(function (c) {
      var key = c.getAttribute('data-spark');
      var color = key === 'confirmed' ? GREEN : key === 'rate' ? AMBER : BLUE;
      var ctx = c.getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: { labels: data.labels, datasets: [{ data: data.spark[key], borderColor: color, borderWidth: 2, fill: true, backgroundColor: gradient(ctx, color, 56), tension: .4, pointRadius: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } }, animation: { duration: 900 } }
      });
    });

    // Asosiy grafik
    var mc = $('#mainChart');
    if (mc) {
      var ctx = mc.getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [
            { label: 'Jami arizalar', data: data.leads, borderColor: BLUE, backgroundColor: gradient(ctx, BLUE), fill: true, tension: .4, borderWidth: 2.5,
              pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#fff', pointBorderWidth: 2.5 },
            { label: 'Tasdiqlangan', data: data.confirmed, borderColor: GREEN, backgroundColor: 'transparent', fill: false, tension: .4, borderWidth: 2,
              borderDash: [6, 5], pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: '#fff', pointBorderWidth: 2 }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
          plugins: { legend: { display: false }, tooltip: tooltip },
          scales: {
            x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
            y: { beginAtZero: true, grid: { color: 'rgba(27,37,51,.06)' }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 6 } }
          }
        }
      });
    }

    // Manbalar donut
    var sc = $('#sourceChart');
    if (sc && data.sources.values.length) {
      new Chart(sc, {
        type: 'doughnut',
        data: { labels: data.sources.labels, datasets: [{ data: data.sources.values, backgroundColor: [BLUE, GREEN, AMBER], borderColor: 'rgba(255,255,255,.9)', borderWidth: 3, hoverOffset: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false }, tooltip: tooltip } }
      });
    }
  }

  // Mahsulot tahlili — gorizontal ustunlar
  var pd = $('#productsData'), pc = $('#productsChart');
  if (pd && pc) {
    var p = JSON.parse(pd.textContent);
    new Chart(pc, {
      type: 'bar',
      data: {
        labels: p.labels,
        datasets: [
          { label: 'Arizalar', data: p.leads, backgroundColor: GREEN, borderRadius: 6, barThickness: 10 },
          { label: 'Savatga', data: p.carts, backgroundColor: BLUE, borderRadius: 6, barThickness: 10 },
          { label: 'AI tavsiya', data: p.chats, backgroundColor: AMBER, borderRadius: 6, barThickness: 10 }
        ]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: tooltip },
        scales: {
          x: { beginAtZero: true, grid: { color: 'rgba(27,37,51,.06)' }, border: { display: false }, ticks: { precision: 0 } },
          y: { grid: { display: false }, border: { display: false }, ticks: { color: '#1b2533', font: { weight: 600 } } }
        }
      }
    });
  }
})();

</script>
</body>
</html>
<?php
}

function seg_days(int $days, string $page): string
{
    $out = '<div class="seg">';
    foreach ([7 => '7 kun', 30 => '30 kun', 90 => '90 kun', 365 => '1 yil'] as $d => $l) {
        $out .= '<a href="' . e(url($page, ['days' => $d])) . '" class="' . ($d === $days ? 'active' : '') . '">' . $l . '</a>';
    }
    return $out . '</div>';
}

/* ============================================================================
   Statistika
   ============================================================================ */
function period_days(): int
{
    $d = (int)($_GET['days'] ?? 30);
    return in_array($d, [7, 30, 90, 365], true) ? $d : 30;
}

function period_bounds(int $days): array
{
    return [date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')), date('Y-m-d 00:00:00', strtotime('-' . (2 * $days - 1) . ' days'))];
}

function daily(string $sql, array $params, int $days): array
{
    $s = [];
    for ($i = $days - 1; $i >= 0; $i--) $s[date('Y-m-d', strtotime("-$i days"))] = 0;
    foreach (q($sql, $params)->fetchAll() as $r) if (isset($s[$r['d']])) $s[$r['d']] = (int)$r['c'];
    return $s;
}

function delta(float $now, float $prev): ?float
{
    if ($prev == 0) return $now > 0 ? 100.0 : null;
    return round(($now - $prev) / $prev * 100, 1);
}

function dashboard_stats(int $days): array
{
    [$start, $prev] = period_bounds($days);
    $ok = "status IN ('confirmed','done')";
    $leads = daily('SELECT DATE(created_at) d, COUNT(*) c FROM leads WHERE created_at >= ? GROUP BY DATE(created_at)', [$start], $days);
    $conf = daily("SELECT DATE(created_at) d, COUNT(*) c FROM leads WHERE created_at >= ? AND $ok GROUP BY DATE(created_at)", [$start], $days);
    $views = daily("SELECT DATE(created_at) d, COUNT(*) c FROM events WHERE type = 'view' AND created_at >= ? GROUP BY DATE(created_at)", [$start], $days);
    $count = fn(string $w, array $p) => (int)q("SELECT COUNT(*) FROM leads WHERE $w", $p)->fetchColumn();

    $total = array_sum($leads);
    $pTotal = $count('created_at >= ? AND created_at < ?', [$prev, $start]);
    $confirmed = array_sum($conf);
    $pConf = $count("created_at >= ? AND created_at < ? AND $ok", [$prev, $start]);
    $v = array_sum($views);
    $pv = (int)q("SELECT COUNT(*) FROM events WHERE type = 'view' AND created_at >= ? AND created_at < ?", [$prev, $start])->fetchColumn();
    $rate = $total ? round($confirmed / $total * 100, 1) : 0.0;
    $pRate = $pTotal ? round($pConf / $pTotal * 100, 1) : 0.0;
    $rateSeries = [];
    foreach ($leads as $d => $c) $rateSeries[$d] = $c ? round($conf[$d] / $c * 100) : 0;
    $sources = [];
    foreach (q('SELECT source, COUNT(*) c FROM leads WHERE created_at >= ? GROUP BY source', [$start])->fetchAll() as $r) $sources[$r['source']] = (int)$r['c'];

    return [
        'series' => ['leads' => $leads, 'confirmed' => $conf, 'views' => $views, 'rate' => $rateSeries],
        'kpi' => ['total' => [$total, delta($total, $pTotal)], 'confirmed' => [$confirmed, delta($confirmed, $pConf)],
                  'rate' => [$rate, round($rate - $pRate, 1)], 'views' => [$v, delta($v, $pv)]],
        'sources' => $sources,
        'new' => $count("status = 'new'", []),
    ];
}

/** Har bir mahsulot: ko'rish, savat, AI tavsiya, arizalar, qadoq, sotuv, trend va «talab balli» */
function product_stats(int $days): array
{
    [$start, $prev] = period_bounds($days);
    $ev = fn($t, $from, $to = null) => "(SELECT COUNT(*) FROM events e WHERE e.product_id = p.id AND e.type = '$t' AND e.created_at >= $from" . ($to ? " AND e.created_at < $to" : '') . ')';
    $ld = fn($from, $to = null) => "(SELECT COUNT(DISTINCT li.lead_id) FROM lead_items li JOIN leads l ON l.id = li.lead_id WHERE li.product_id = p.id AND l.created_at >= $from" . ($to ? " AND l.created_at < $to" : '') . ')';
    $sql = 'SELECT p.id, p.name_uz, p.code, p.image, p.is_active, p.category, '
        . $ev('view', ':s1') . ' views, ' . $ev('cart', ':s2') . ' carts, ' . $ev('chat', ':s3') . ' chats, ' . $ld(':s4') . ' leads, '
        . '(SELECT COALESCE(SUM(li.qty),0) FROM lead_items li JOIN leads l ON l.id = li.lead_id WHERE li.product_id = p.id AND l.created_at >= :s5) qty, '
        . "(SELECT COUNT(DISTINCT li.lead_id) FROM lead_items li JOIN leads l ON l.id = li.lead_id WHERE li.product_id = p.id AND l.created_at >= :s6 AND l.status IN ('confirmed','done')) sold, "
        . $ld(':p1', ':s7') . ' prev_leads, ' . $ev('cart', ':p2', ':s8') . ' prev_carts FROM products p ORDER BY p.sort_order, p.id';
    $params = ['p1' => $prev, 'p2' => $prev];
    foreach (range(1, 8) as $i) $params["s$i"] = $start;
    $rows = q($sql, $params)->fetchAll();
    foreach ($rows as &$r) {
        foreach (['views', 'carts', 'chats', 'leads', 'qty', 'sold', 'prev_leads', 'prev_carts'] as $k) $r[$k] = (int)$r[$k];
        $r['score'] = $r['leads'] * 10 + $r['sold'] * 5 + $r['carts'] * 3 + $r['chats'] * 1.5 + $r['views'] * 0.2;
        $r['conversion'] = $r['views'] ? round($r['leads'] / $r['views'] * 100, 1) : null;
        $r['trend'] = delta($r['leads'] * 10 + $r['carts'] * 3, $r['prev_leads'] * 10 + $r['prev_carts'] * 3);
    }
    unset($r);
    usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);
    $max = max(array_column($rows, 'score') ?: [0]);
    $n = count($rows);
    foreach ($rows as $i => &$r) {
        if ($r['score'] <= 0) $r['rating'] = 'low';
        elseif ($i < min(3, $n) && $r['score'] >= $max * 0.35) $r['rating'] = 'hot';
        elseif ($i >= (int)floor($n * 0.75)) $r['rating'] = 'low';
        else $r['rating'] = 'mid';
        $r['bar'] = $max > 0 ? round($r['score'] / $max * 100) : 0;
    }
    unset($r);
    return $rows;
}

function attach_items(array $leads): array
{
    if (!$leads) return [];
    $ids = array_column($leads, 'id');
    $items = [];
    foreach (q('SELECT li.*, p.image FROM lead_items li LEFT JOIN products p ON p.id = li.product_id WHERE li.lead_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids)->fetchAll() as $it) {
        $items[$it['lead_id']][] = $it;
    }
    foreach ($leads as &$l) $l['items'] = $items[$l['id']] ?? [];
    return $leads;
}

/* ============================================================================
   Sahifalar
   ============================================================================ */
function page_login(): void
{
    if (current_admin()) redirect(url('dashboard'));
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        if (!rate_limit('login', 8, 900, true, false)) {
            $error = "Juda ko'p urinish. 15 daqiqadan keyin qayta urinib ko'ring.";
        } else {
            $row = q('SELECT * FROM admins WHERE username = ?', [trim((string)($_POST['username'] ?? ''))])->fetch();
            $pass = (string)($_POST['password'] ?? '');
            if ($row && password_verify($pass, $row['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$row['id'];
                $_SESSION['last_seen'] = time();
                q('UPDATE admins SET last_login = ? WHERE id = ?', [now(), $row['id']]);
                if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                    q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
                }
                redirect(url('dashboard'));
            }
            rate_limit('login', 1000, 900, true, true); // xato urinishni yozib qo'yamiz
            $error = "Login yoki parol noto'g'ri.";
        }
    }
    head_html('Kirish', 'login-page');
    ?>
<main class="login-card glass">
  <img src="<?= ASSETS ?>logo.png" alt="CITRIC.UZ" class="login-logo">
  <h1>Boshqaruv paneliga kirish</h1>
  <p class="muted">Davom etish uchun login va parolingizni kiriting</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= url('login') ?>" class="login-form">
    <?= csrf_field() ?>
    <label class="field"><span>Login</span><input name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></label>
    <label class="field"><span>Parol</span>
      <span class="pass-wrap"><input name="password" type="password" required autocomplete="current-password" id="pass">
      <button type="button" class="pass-toggle" data-toggle-pass="pass">Ko'rsatish</button></span></label>
    <button class="btn btn-primary btn-block" type="submit">Kirish</button>
  </form>
  <a href="./" class="back-link">← Saytga qaytish</a>
</main>
<script>
/* CITRIC.UZ Admin — interaktivlik va grafiklar */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- Mobil sidebar ---------- */
  var sidebar = $('#sidebar');
  if ($('#burger')) $('#burger').addEventListener('click', function () { sidebar.classList.add('open'); });
  if ($('#sidebarBackdrop')) $('#sidebarBackdrop').addEventListener('click', function () { sidebar.classList.remove('open'); });

  /* ---------- Parolni ko'rsatish ---------- */
  $$('[data-toggle-pass]').forEach(function (b) {
    b.addEventListener('click', function () {
      var inp = document.getElementById(b.getAttribute('data-toggle-pass'));
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      b.textContent = show ? 'Yashirish' : "Ko'rsatish";
    });
  });

  /* ---------- Til tablari (mahsulot formasi) ---------- */
  $$('[data-tabs]').forEach(function (tabs) {
    var card = tabs.parentElement;
    tabs.addEventListener('click', function (e) {
      var b = e.target.closest('[data-tab]');
      if (!b) return;
      $$('[data-tab]', tabs).forEach(function (x) { x.classList.toggle('active', x === b); });
      $$('[data-pane]', card).forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-tab'); });
    });
  });

  /* ---------- Rasm yuklash: istalgan hajmdagi rasm brauzerda optimallashtiriladi ----------
     Katta rasm (masalan 20 MB telefon surati) serverga yuborilishidan oldin 1600 px gacha
     kichraytiriladi va WebP (bo'lmasa JPEG) ga o'giriladi — server limitiga tushmaydi. */
  var dz = $('#dropzone'), input = $('#imageInput'), preview = $('#imagePreview'), status = $('#uploadStatus');
  if (dz && input) {
    var MAX_SIDE = 1600, TARGET = 900 * 1024;
    var form = dz.closest('form'), submitBtn = form && $('button[type="submit"]', form);
    var busy = false;
    var fmt = function (b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; };
    var setStatus = function (html, type) {
      if (!status) return;
      status.className = 'upload-status' + (type ? ' ' + type : '');
      status.innerHTML = html;
      status.hidden = !html;
    };
    var webpOk = (function () {
      try { var c = document.createElement('canvas'); c.width = c.height = 1; return c.toDataURL('image/webp').indexOf('data:image/webp') === 0; } catch (e) { return false; }
    })();
    var loadImage = function (file) {
      if (window.createImageBitmap) return createImageBitmap(file).catch(function () { return viaImg(file); });
      return viaImg(file);
    };
    var viaImg = function (file) {
      return new Promise(function (res, rej) {
        var url = URL.createObjectURL(file), im = new Image();
        im.onload = function () { res(im); };
        im.onerror = function () { URL.revokeObjectURL(url); rej(new Error('decode')); };
        im.src = url;
      });
    };
    var toBlob = function (canvas, type, q) {
      return new Promise(function (res) { canvas.toBlob(res, type, q); });
    };
    var optimize = function (file) {
      if (!/^image\/(jpeg|png|webp|gif|bmp)$/i.test(file.type)) {
        return Promise.reject(new Error(/hei[cf]/i.test(file.type + file.name)
          ? "HEIC (iPhone) formati brauzerda ochilmaydi. Iltimos, rasmni JPG ga o'tkazib yuklang (iPhone: Sozlamalar → Kamera → Formatlar → «Eng mos»)."
          : "Bu fayl rasm emas yoki formati qo'llab-quvvatlanmaydi. JPG, PNG yoki WEBP yuklang."));
      }
      return loadImage(file).then(function (img) {
        var w = img.width, h = img.height, scale = Math.min(1, MAX_SIDE / Math.max(w, h));
        // Kichik va allaqachon siqilgan rasm — o'zgarishsiz
        if (scale === 1 && file.size <= TARGET && /jpeg|webp/i.test(file.type)) return { file: file, w: w, h: h, changed: false };
        var cw = Math.round(w * scale), ch = Math.round(h * scale);
        var canvas = document.createElement('canvas');
        canvas.width = cw; canvas.height = ch;
        var ctx = canvas.getContext('2d');
        var type = webpOk ? 'image/webp' : 'image/jpeg';
        if (type === 'image/jpeg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cw, ch); } // JPEG'da shaffof joy oq bo'lsin
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, 0, 0, cw, ch);
        return toBlob(canvas, type, 0.86).then(function (blob) {
          return blob && blob.size > TARGET ? toBlob(canvas, type, 0.74) : blob;
        }).then(function (blob) {
          if (!blob) throw new Error("Rasmni qayta ishlab bo'lmadi. Boshqa rasm tanlang.");
          var name = (file.name.replace(/\.[^.]+$/, '') || 'rasm') + (type === 'image/webp' ? '.webp' : '.jpg');
          return { file: new File([blob], name, { type: type, lastModified: Date.now() }), w: cw, h: ch, changed: true };
        });
      });
    };
    var handle = function (file) {
      if (!file) return;
      busy = true;
      if (submitBtn) submitBtn.disabled = true;
      dz.classList.add('processing');
      setStatus('<span class="spinner"></span> Rasm tayyorlanmoqda… (' + fmt(file.size) + ')');
      optimize(file).then(function (r) {
        var dt = new DataTransfer();
        dt.items.add(r.file);
        input.files = dt.files;
        preview.src = URL.createObjectURL(r.file);
        preview.hidden = false;
        var t = $('.dz-text', dz); if (t) t.hidden = true;
        setStatus('✓ Tayyor: ' + r.w + '×' + r.h + ' px, ' + (r.changed ? fmt(file.size) + ' → <b>' + fmt(r.file.size) + '</b>' : '<b>' + fmt(r.file.size) + '</b>') +
          (r.h / r.w < 1.15 ? ' <span class="muted">· Tavsiya: vertikal 4:5 rasm (masalan 800×1000)</span>' : ''), 'ok');
      }).catch(function (err) {
        input.value = '';
        setStatus('⚠ ' + (err && err.message && err.message.length > 12 ? err.message : "Rasmni o'qib bo'lmadi. Boshqa rasm tanlang."), 'error');
      }).then(function () {
        busy = false;
        dz.classList.remove('processing');
        if (submitBtn) submitBtn.disabled = false;
      });
    };
    input.addEventListener('change', function () { if (input.files[0]) handle(input.files[0]); });
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function () { dz.classList.remove('drag'); }); });
    dz.addEventListener('drop', function (e) {
      e.preventDefault();
      if (e.dataTransfer.files.length) handle(e.dataTransfer.files[0]);
    });
    if (form) form.addEventListener('submit', function (e) {
      if (busy) { e.preventDefault(); return; }
      if (submitBtn) { submitBtn.classList.add('loading'); submitBtn.lastChild.textContent = ' Saqlanmoqda…'; }
    });
  }

  /* ---------- O'chirishni tasdiqlash oynasi ---------- */
  var modal = $('#deleteModal');
  if (modal) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-delete]');
      if (b) {
        $('#deleteId').value = b.getAttribute('data-delete');
        $('#deleteName').textContent = '«' + b.getAttribute('data-name') + '»';
        modal.hidden = false;
      }
      if (e.target === modal || e.target.closest('[data-close-modal]')) modal.hidden = true;
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.hidden = true; });
  }

  /* ---------- Grafiklar ---------- */
  if (!window.Chart) return;
  Chart.defaults.font.family = 'Manrope, system-ui, sans-serif';
  Chart.defaults.color = '#7d8899';
  var BLUE = '#2f5d9b', GREEN = '#70b32f', AMBER = '#ffa800';

  function gradient(ctx, color, h) {
    var g = ctx.createLinearGradient(0, 0, 0, h || 300);
    g.addColorStop(0, color + '40');
    g.addColorStop(1, color + '00');
    return g;
  }
  var tooltip = {
    backgroundColor: 'rgba(255,255,255,.96)', titleColor: '#1b2533', bodyColor: '#445163', borderColor: 'rgba(27,37,51,.1)',
    borderWidth: 1, padding: 12, cornerRadius: 12, boxPadding: 4, usePointStyle: true
  };

  var data = $('#chartData') ? JSON.parse($('#chartData').textContent) : null;
  if (data) {
    // KPI mini grafiklari
    $$('canvas[data-spark]').forEach(function (c) {
      var key = c.getAttribute('data-spark');
      var color = key === 'confirmed' ? GREEN : key === 'rate' ? AMBER : BLUE;
      var ctx = c.getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: { labels: data.labels, datasets: [{ data: data.spark[key], borderColor: color, borderWidth: 2, fill: true, backgroundColor: gradient(ctx, color, 56), tension: .4, pointRadius: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } }, animation: { duration: 900 } }
      });
    });

    // Asosiy grafik
    var mc = $('#mainChart');
    if (mc) {
      var ctx = mc.getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [
            { label: 'Jami arizalar', data: data.leads, borderColor: BLUE, backgroundColor: gradient(ctx, BLUE), fill: true, tension: .4, borderWidth: 2.5,
              pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#fff', pointBorderWidth: 2.5 },
            { label: 'Tasdiqlangan', data: data.confirmed, borderColor: GREEN, backgroundColor: 'transparent', fill: false, tension: .4, borderWidth: 2,
              borderDash: [6, 5], pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: '#fff', pointBorderWidth: 2 }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
          plugins: { legend: { display: false }, tooltip: tooltip },
          scales: {
            x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
            y: { beginAtZero: true, grid: { color: 'rgba(27,37,51,.06)' }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 6 } }
          }
        }
      });
    }

    // Manbalar donut
    var sc = $('#sourceChart');
    if (sc && data.sources.values.length) {
      new Chart(sc, {
        type: 'doughnut',
        data: { labels: data.sources.labels, datasets: [{ data: data.sources.values, backgroundColor: [BLUE, GREEN, AMBER], borderColor: 'rgba(255,255,255,.9)', borderWidth: 3, hoverOffset: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false }, tooltip: tooltip } }
      });
    }
  }

  // Mahsulot tahlili — gorizontal ustunlar
  var pd = $('#productsData'), pc = $('#productsChart');
  if (pd && pc) {
    var p = JSON.parse(pd.textContent);
    new Chart(pc, {
      type: 'bar',
      data: {
        labels: p.labels,
        datasets: [
          { label: 'Arizalar', data: p.leads, backgroundColor: GREEN, borderRadius: 6, barThickness: 10 },
          { label: 'Savatga', data: p.carts, backgroundColor: BLUE, borderRadius: 6, barThickness: 10 },
          { label: 'AI tavsiya', data: p.chats, backgroundColor: AMBER, borderRadius: 6, barThickness: 10 }
        ]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: tooltip },
        scales: {
          x: { beginAtZero: true, grid: { color: 'rgba(27,37,51,.06)' }, border: { display: false }, ticks: { precision: 0 } },
          y: { grid: { display: false }, border: { display: false }, ticks: { color: '#1b2533', font: { weight: 600 } } }
        }
      }
    });
  }
})();

</script>
</body>
</html>
<?php
}

function page_dashboard(array $admin): void
{
    $days = period_days();
    $s = dashboard_stats($days);
    $products = product_stats($days);
    $recent = attach_items(q('SELECT * FROM leads ORDER BY created_at DESC, id DESC LIMIT 6')->fetchAll());
    $chart = [
        'labels' => array_map(fn($d) => fmt_date($d, false), array_keys($s['series']['leads'])),
        'leads' => array_values($s['series']['leads']),
        'confirmed' => array_values($s['series']['confirmed']),
        'spark' => array_map('array_values', ['total' => $s['series']['leads'], 'confirmed' => $s['series']['confirmed'], 'rate' => $s['series']['rate'], 'views' => $s['series']['views']]),
        'sources' => ['labels' => array_map(fn($k) => LEAD_SOURCES[$k] ?? $k, array_keys($s['sources'])), 'values' => array_values($s['sources'])],
    ];
    layout_start('Boshqaruv paneli', 'dashboard', $admin);
    ?>
<div class="page-head">
  <p class="muted">Xush kelibsiz, <b><?= e($admin['full_name'] ?: $admin['username']) ?></b>. So'nggi <?= $days ?> kunlik ko'rsatkichlar.</p>
  <?= seg_days($days, 'dashboard') ?>
</div>

<section class="kpis">
  <?php foreach ([
      ['total', 'Jami arizalar', fmt_num($s['kpi']['total'][0]), delta_badge($s['kpi']['total'][1]), 'inbox'],
      ['confirmed', 'Tasdiqlangan', fmt_num($s['kpi']['confirmed'][0]), delta_badge($s['kpi']['confirmed'][1]), 'check'],
      ['rate', 'Konversiya', $s['kpi']['rate'][0] . '%', delta_badge($s['kpi']['rate'][1], true), 'chart'],
      ['views', "Mahsulot ko'rishlari", fmt_num($s['kpi']['views'][0]), delta_badge($s['kpi']['views'][1]), 'eye'],
  ] as [$key, $label, $value, $badge, $ic]): ?>
    <article class="card kpi glass">
      <div class="kpi-head"><span class="kpi-label"><?= icon($ic) ?><?= $label ?></span><?= $badge ?></div>
      <div class="kpi-value"><?= $value ?></div>
      <canvas class="spark" data-spark="<?= $key ?>" height="56"></canvas>
    </article>
  <?php endforeach; ?>
</section>

<section class="card glass chart-card">
  <div class="card-head">
    <div><h2>Arizalar dinamikasi</h2><p class="muted">Kunlik kelgan va tasdiqlangan arizalar</p></div>
    <div class="legend"><span><i style="background:#2f5d9b"></i>Jami arizalar</span><span><i style="background:#70b32f"></i>Tasdiqlangan</span></div>
  </div>
  <div class="chart-wrap"><canvas id="mainChart"></canvas></div>
</section>

<section class="grid-2">
  <article class="card glass">
    <div class="card-head">
      <div><h2>Mahsulotlar reytingi</h2><p class="muted">Qaysi tovar yaxshi ketyapti, qaysi biri yo'q</p></div>
      <a href="<?= e(url('analytics', ['days' => $days])) ?>" class="btn btn-ghost btn-sm">Batafsil tahlil</a>
    </div>
    <?php if (!$products): ?>
      <div class="empty">Hali mahsulotlar yo'q. <a href="<?= url('product') ?>">Mahsulot qo'shing</a>.</div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Mahsulot</th><th class="num">Ko'rish</th><th class="num">Savat</th><th class="num">Ariza</th><th>Talab</th><th>Holat</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($products, 0, 6) as $p): [$rl, $rc] = RATINGS[$p['rating']]; ?>
        <tr>
          <td><div class="prod-cell"><img src="<?= e($p['image']) ?>" alt=""><div><b><?= e($p['name_uz']) ?></b><small><?= e($p['code'] ?: '—') ?></small></div></div></td>
          <td class="num"><?= fmt_num($p['views']) ?></td>
          <td class="num"><?= fmt_num($p['carts']) ?></td>
          <td class="num"><b><?= fmt_num($p['leads']) ?></b></td>
          <td><div class="bar"><span style="width:<?= $p['bar'] ?>%"></span></div></td>
          <td><?= chip($rl, $rc, $p['rating'] === 'hot' ? icon('flame') : '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </article>

  <article class="card glass">
    <div class="card-head"><div><h2>Arizalar manbai</h2><p class="muted">Mijozlar qayerdan kelyapti</p></div></div>
    <?php if (!array_sum($s['sources'])): ?>
      <div class="empty">Bu davrda arizalar yo'q</div>
    <?php else: ?>
      <div class="donut-wrap"><canvas id="sourceChart"></canvas><div class="donut-center"><b><?= fmt_num(array_sum($s['sources'])) ?></b><small>ariza</small></div></div>
      <ul class="donut-legend">
        <?php $colors = ['#2f5d9b', '#70b32f', '#ffa800']; $i = 0; foreach ($s['sources'] as $k => $v): ?>
          <li><i style="background:<?= $colors[$i++ % 3] ?>"></i><?= e(LEAD_SOURCES[$k] ?? $k) ?><b><?= fmt_num($v) ?></b></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </article>
</section>

<section class="card glass">
  <div class="card-head">
    <div><h2>So'nggi arizalar</h2><p class="muted"><?= $s['new'] ?> ta yangi ariza javob kutmoqda</p></div>
    <a href="<?= url('leads') ?>" class="btn btn-ghost btn-sm">Barchasi</a>
  </div>
  <?php if (!$recent): ?>
    <div class="empty">Hali arizalar yo'q. Saytdagi AI maslahatchi, savat yoki forma orqali kelgan arizalar shu yerda ko'rinadi.</div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Mijoz</th><th>Mahsulot</th><th>Manba</th><th>Holat</th><th>Sana</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $l): [$sl, $sc] = LEAD_STATUSES[$l['status']] ?? [$l['status'], 'gray']; ?>
      <tr>
        <td><b><?= e($l['name']) ?></b><small class="block"><a href="tel:<?= e(preg_replace('/\s/', '', $l['phone'])) ?>"><?= e($l['phone']) ?></a></small></td>
        <td><?= $l['items'] ? e(implode(', ', array_map(fn($i) => $i['product_name'] . ($i['qty'] > 1 ? ' ×' . $i['qty'] : ''), $l['items']))) : '<span class="muted">—</span>' ?></td>
        <td><span class="src src-<?= e($l['source']) ?>"><?= e(LEAD_SOURCES[$l['source']] ?? $l['source']) ?></span></td>
        <td><?= chip($sl, $sc) ?></td>
        <td class="muted nowrap"><?= fmt_date($l['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<script type="application/json" id="chartData"><?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php
    layout_end(true);
}

function advice(array $r): string
{
    if (!$r['is_active']) return "Mahsulot saytda yashirilgan — kerak bo'lsa faollashtiring.";
    if ($r['views'] === 0) return "Saytda deyarli ko'rilmayapti — katalogda yuqoriroqqa qo'ying (tartib raqamini kichraytiring).";
    if ($r['carts'] === 0 && $r['leads'] === 0) return "Ko'rilyapti, lekin savatga qo'shilmayapti — narx, tavsif va rasmni tekshiring.";
    if ($r['leads'] === 0) return "Savatga qo'shilyapti, lekin ariza kelmayapti — narxni ko'rsating yoki aksiya qo'shing.";
    return "Talab pasaygan — oldingi davrga nisbatan kamroq so'ralmoqda.";
}

function page_analytics(array $admin): void
{
    $days = period_days();
    $rows = product_stats($days);
    $hot = array_values(array_filter($rows, fn($r) => $r['rating'] === 'hot'));
    $low = array_values(array_filter($rows, fn($r) => $r['rating'] === 'low'));
    $top = array_slice(array_values(array_filter($rows, fn($r) => $r['score'] > 0)), 0, 8);
    $chart = ['labels' => array_map(fn($r) => mb_strimwidth($r['name_uz'], 0, 26, '…'), $top),
        'leads' => array_column($top, 'leads'), 'carts' => array_column($top, 'carts'), 'chats' => array_column($top, 'chats')];
    layout_start('Mahsulot tahlili', 'analytics', $admin);
    ?>
<div class="page-head">
  <p class="muted">Har bir mahsulot bo'yicha mijozlar qiziqishi: ko'rish → savat → AI tavsiyasi → ariza → sotuv.</p>
  <?= seg_days($days, 'analytics') ?>
</div>

<section class="grid-3">
  <article class="card glass insight insight-green"><div class="insight-ic"><?= icon('flame') ?></div>
    <div><h3>Yaxshi ketyapti</h3><?= $hot ? '<p>' . e(implode(', ', array_column($hot, 'name_uz'))) . '</p>' : '<p class="muted">Hali yetarli ma\'lumot yo\'q</p>' ?></div></article>
  <article class="card glass insight insight-red"><div class="insight-ic"><?= icon('down') ?></div>
    <div><h3>Past talab</h3><p><?= $low ? count($low) . " ta mahsulotga e'tibor kerak" : 'Barcha mahsulotlarga talab bor' ?></p></div></article>
  <article class="card glass insight insight-blue"><div class="insight-ic"><?= icon('inbox') ?></div>
    <div><h3>Jami so'rovlar</h3><p><?= fmt_num(array_sum(array_column($rows, 'leads'))) ?> ta ariza, <?= fmt_num(array_sum(array_column($rows, 'qty'))) ?> ta qadoq</p></div></article>
</section>

<section class="card glass chart-card">
  <div class="card-head">
    <div><h2>Eng ko'p so'ralgan mahsulotlar</h2><p class="muted">Arizalar, savatga qo'shishlar va AI tavsiyalari</p></div>
    <div class="legend"><span><i style="background:#70b32f"></i>Arizalar</span><span><i style="background:#2f5d9b"></i>Savatga</span><span><i style="background:#ffa800"></i>AI tavsiya</span></div>
  </div>
  <?php if (!$top): ?><div class="empty">Bu davrda faollik bo'lmagan. Saytga mijozlar kirishi bilan statistika shu yerda paydo bo'ladi.</div>
  <?php else: ?><div class="chart-wrap" style="height:<?= max(220, count($top) * 46) ?>px"><canvas id="productsChart"></canvas></div><?php endif; ?>
</section>

<section class="card glass">
  <div class="card-head"><div><h2>Barcha mahsulotlar ko'rsatkichlari</h2><p class="muted">Talab balli bo'yicha saralangan</p></div></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Mahsulot</th><th class="num">Ko'rish</th><th class="num">Savat</th><th class="num">AI tavsiya</th><th class="num">Ariza</th><th class="num">Qadoq</th><th class="num">Sotildi</th><th class="num">Konversiya</th><th>Trend</th><th>Holat</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): [$rl, $rc] = RATINGS[$r['rating']]; ?>
      <tr class="<?= $r['is_active'] ? '' : 'row-muted' ?>">
        <td class="muted"><?= $i + 1 ?></td>
        <td><div class="prod-cell"><img src="<?= e($r['image']) ?>" alt=""><div><b><?= e($r['name_uz']) ?></b><small><?= e(CAT_NAMES[$r['category']] ?? '') ?><?= $r['is_active'] ? '' : ' · yashirin' ?></small></div></div></td>
        <td class="num"><?= fmt_num($r['views']) ?></td><td class="num"><?= fmt_num($r['carts']) ?></td><td class="num"><?= fmt_num($r['chats']) ?></td>
        <td class="num"><b><?= fmt_num($r['leads']) ?></b></td><td class="num"><?= fmt_num($r['qty']) ?></td><td class="num"><?= fmt_num($r['sold']) ?></td>
        <td class="num"><?= $r['conversion'] === null ? '—' : $r['conversion'] . '%' ?></td>
        <td><?= delta_badge($r['trend']) ?></td>
        <td><?= chip($rl, $rc, $r['rating'] === 'hot' ? icon('flame') : '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<?php if ($low): ?>
<section class="card glass">
  <div class="card-head"><div><h2>Tavsiyalar</h2><p class="muted">Past talabdagi mahsulotlar va nima qilish mumkin</p></div></div>
  <ul class="advice-list">
    <?php foreach ($low as $r): ?>
      <li><img src="<?= e($r['image']) ?>" alt=""><div><b><?= e($r['name_uz']) ?></b><p><?= e(advice($r)) ?></p></div>
        <a href="<?= e(url('product', ['id' => $r['id']])) ?>" class="btn btn-ghost btn-sm"><?= icon('edit') ?>Tahrirlash</a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<script type="application/json" id="productsData"><?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php
    layout_end(true);
}

function page_products(array $admin): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $id = (int)($_POST['id'] ?? 0);
        $p = q('SELECT * FROM products WHERE id = ?', [$id])->fetch();
        if (!$p) {
            flash('Mahsulot topilmadi', 'error');
        } elseif (($_POST['action'] ?? '') === 'toggle') {
            q('UPDATE products SET is_active = ?, updated_at = ? WHERE id = ?', [$p['is_active'] ? 0 : 1, now(), $id]);
            flash($p['is_active'] ? "«{$p['name_uz']}» saytdan yashirildi" : "«{$p['name_uz']}» saytda ko'rsatilmoqda");
        } elseif (($_POST['action'] ?? '') === 'delete') {
            q('DELETE FROM products WHERE id = ?', [$id]);
            if (str_starts_with($p['image'], 'uploads/products/')) @unlink(__DIR__ . '/' . $p['image']);
            flash("«{$p['name_uz']}» o'chirildi");
        }
        redirect(url('products', ['q' => $_POST['q'] ?? '', 'cat' => $_POST['cat'] ?? '']));
    }

    $search = trim((string)($_GET['q'] ?? ''));
    $cat = isset(CAT_NAMES[$_GET['cat'] ?? '']) ? $_GET['cat'] : '';
    $where = [];
    $params = [];
    if ($search !== '') { $where[] = '(name_uz LIKE ? OR name_ru LIKE ? OR name_en LIKE ? OR code LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%", "%$search%"); }
    if ($cat) { $where[] = 'category = ?'; $params[] = $cat; }
    $rows = q('SELECT p.*, (SELECT COUNT(DISTINCT li.lead_id) FROM lead_items li WHERE li.product_id = p.id) leads FROM products p'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY sort_order, id', $params)->fetchAll();
    $total = (int)q('SELECT COUNT(*) FROM products')->fetchColumn();
    $active = (int)q('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();

    layout_start('Mahsulotlar', 'products', $admin);
    ?>
<div class="page-head">
  <p class="muted">Jami <b><?= $total ?></b> ta mahsulot, saytda <b><?= $active ?></b> tasi ko'rinadi.</p>
  <a href="<?= url('product') ?>" class="btn btn-primary"><?= icon('plus') ?>Yangi mahsulot</a>
</div>
<section class="card glass">
  <form class="toolbar" method="get" action="admin.php">
    <input type="hidden" name="page" value="products"><?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
    <div class="search-inline"><?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Nomi yoki E-kodi bo'yicha qidirish"></div>
    <div class="seg seg-wrap">
      <a href="<?= e(url('products', ['q' => $search])) ?>" class="<?= $cat === '' ? 'active' : '' ?>">Hammasi</a>
      <?php foreach (CAT_NAMES as $k => $l): ?><a href="<?= e(url('products', ['q' => $search, 'cat' => $k])) ?>" class="<?= $cat === $k ? 'active' : '' ?>"><?= $l ?></a><?php endforeach; ?>
    </div>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><?= $search || $cat ? 'Hech narsa topilmadi.' : "Hali mahsulot yo'q." ?> <a href="<?= url('product') ?>">Yangi mahsulot qo'shish</a></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table products-table">
    <thead><tr><th>Mahsulot</th><th>Kategoriya</th><th>Qadoq</th><th>Kelib chiqishi</th><th class="num">Narx</th><th class="num">Arizalar</th><th>Saytda</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr class="<?= $p['is_active'] ? '' : 'row-muted' ?>">
        <td><div class="prod-cell lg"><img src="<?= e($p['image']) ?>" alt=""><div><b><?= e($p['name_uz']) ?></b><small><?= e($p['code'] ?: '—') ?><?= $p['name_ru'] ? ' · ' . e($p['name_ru']) : '' ?></small></div></div></td>
        <td><?= chip(CAT_NAMES[$p['category']] ?? $p['category'], 'gray') ?></td>
        <td class="nowrap"><?= e($p['pack']) ?></td>
        <td><?= e($p['origin_uz']) ?></td>
        <td class="num nowrap"><?= $p['price'] !== null ? '<b>' . e(format_price((float)$p['price'], $p['price_unit'])) . '</b>' : '<span class="muted">So\'rov bo\'yicha</span>' ?></td>
        <td class="num"><?= fmt_num($p['leads']) ?></td>
        <td><form method="post" action="<?= url('products') ?>"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="cat" value="<?= e($cat) ?>">
          <button type="submit" class="switch <?= $p['is_active'] ? 'on' : '' ?>" title="<?= $p['is_active'] ? 'Yashirish' : "Ko'rsatish" ?>"><span></span></button></form></td>
        <td class="actions">
          <a href="<?= e(url('product', ['id' => $p['id']])) ?>" class="icon-btn sm" title="Tahrirlash"><?= icon('edit') ?></a>
          <button type="button" class="icon-btn sm danger" title="O'chirish" data-delete="<?= (int)$p['id'] ?>" data-name="<?= e($p['name_uz']) ?>"><?= icon('trash') ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<div class="modal" id="deleteModal" hidden>
  <div class="modal-card glass">
    <div class="modal-ic danger"><?= icon('trash') ?></div>
    <h3>Mahsulotni o'chirasizmi?</h3>
    <p class="muted"><b id="deleteName"></b> butunlay o'chiriladi. Uning statistikasi ham o'chadi; eski arizalarda nomi saqlanib qoladi. Bu amalni qaytarib bo'lmaydi.</p>
    <p class="muted small">Faqat vaqtincha olib tashlamoqchi bo'lsangiz, «Saytda» tugmasi bilan yashiring.</p>
    <form method="post" action="<?= url('products') ?>" class="modal-actions"><?= csrf_field() ?>
      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="deleteId"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="cat" value="<?= e($cat) ?>">
      <button type="button" class="btn btn-ghost" data-close-modal>Bekor qilish</button>
      <button type="submit" class="btn btn-danger">Ha, o'chirish</button>
    </form>
  </div>
</div>
<?php
    layout_end();
}

/** Rasmni tekshirib uploads/products/ ga saqlaydi */
function save_upload(array $f, array &$errors): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) {
        switch ($f['error']) {
            case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE:
                $errors['image'] = 'Rasm server chegarasidan katta (' . round(upload_limit() / 1048576, 1) . " MB). Rasmni qayta tanlang — panel uni avtomatik kichraytiradi."; break;
            case UPLOAD_ERR_PARTIAL:
                $errors['image'] = "Rasm to'liq yuklanmadi (internet uzildi). Qayta urinib ko'ring."; break;
            case UPLOAD_ERR_NO_TMP_DIR: case UPLOAD_ERR_CANT_WRITE:
                $errors['image'] = "Serverda vaqtinchalik papka yoki yozish ruxsati yo'q. Hosting sozlamalarini tekshiring."; break;
            default:
                $errors['image'] = 'Rasm yuklanmadi (xato kodi ' . $f['error'] . ')';
        }
        return null;
    }
    if ($f['size'] > 25 * 1024 * 1024) { $errors['image'] = 'Rasm hajmi 25 MB dan oshmasligi kerak'; return null; }
    $dir = __DIR__ . '/uploads/products';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        // Bu papkada hech qanday skript ishga tushmasin
        @file_put_contents(__DIR__ . '/uploads/.htaccess', "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh|html?)$\">\n  Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
    }
    // Hostingda /tmp ga kirish taqiqlangan bo'lishi mumkin (open_basedir) — shuning uchun avval o'z papkamizga
    // kengaytmasiz nom bilan ko'chiramiz, keyin tekshiramiz
    $base = date('Ymd') . '-' . bin2hex(random_bytes(6));
    $tmp = "$dir/$base.upload";
    if (!move_uploaded_file($f['tmp_name'], $tmp)) { $errors['image'] = "Rasmni saqlab bo'lmadi (uploads papkasiga yozish ruxsatini tekshiring)"; return null; }
    $info = @getimagesize($tmp);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$info || !$ext) { @unlink($tmp); $errors['image'] = 'Faqat JPG, PNG yoki WEBP rasm yuklang'; return null; }
    if (!@rename($tmp, "$dir/$base.$ext")) { @unlink($tmp); $errors['image'] = "Rasmni saqlab bo'lmadi"; return null; }
    return "uploads/products/$base.$ext";
}

function page_product(array $admin): void
{
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $product = $id ? q('SELECT * FROM products WHERE id = ?', [$id])->fetch() : null;
    if ($id && !$product) { flash('Mahsulot topilmadi', 'error'); redirect(url('products')); }

    $v = array_merge([
        'name_uz' => '', 'name_ru' => '', 'name_en' => '', 'desc_uz' => '', 'desc_ru' => '', 'desc_en' => '', 'category' => 'other', 'code' => '', 'pack' => '25 kg',
        'origin_uz' => '', 'origin_ru' => '', 'origin_en' => '', 'price' => '', 'price_unit' => "so'm/kg", 'image' => '', 'is_active' => 1,
        'sort_order' => (int)q('SELECT COALESCE(MAX(sort_order),0) + 1 FROM products')->fetchColumn(),
    ], $product ?: []);
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $s = fn($k, $max = 190) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
        $v = array_merge($v, [
            'name_uz' => $s('name_uz'), 'name_ru' => $s('name_ru'), 'desc_uz' => $s('desc_uz', 3000), 'desc_ru' => $s('desc_ru', 3000),
            'name_en' => $s('name_en'), 'desc_en' => $s('desc_en', 3000), 'origin_en' => $s('origin_en', 60),
            'category' => isset(CAT_NAMES[$_POST['category'] ?? '']) ? $_POST['category'] : 'other',
            'code' => $s('code', 30), 'pack' => $s('pack', 50), 'origin_uz' => $s('origin_uz', 60), 'origin_ru' => $s('origin_ru', 60),
            'price' => str_replace([' ', ','], ['', '.'], $s('price', 20)), 'price_unit' => $s('price_unit', 30),
            'is_active' => isset($_POST['is_active']) ? 1 : 0, 'sort_order' => (int)($_POST['sort_order'] ?? 0),
        ]);
        // Oldingi urinishda yuklangan (hali saqlanmagan) rasm
        $cur = (string)($_POST['image_current'] ?? '');
        if (!$product && !$v['image'] && preg_match('~^uploads/products/[\w.-]+$~', $cur) && is_file(__DIR__ . '/' . $cur)) $v['image'] = $cur;

        if (mb_strlen($v['name_uz']) < 2) $errors['name_uz'] = 'Mahsulot nomini kiriting';
        if ($v['price'] !== '' && (!is_numeric($v['price']) || (float)$v['price'] < 0)) $errors['price'] = "Narx musbat son bo'lishi kerak";
        $uploaded = save_upload($_FILES['image'] ?? [], $errors);
        if ($uploaded) {
            if ($v['image'] && str_starts_with($v['image'], 'uploads/products/') && $v['image'] !== $cur) @unlink(__DIR__ . '/' . $v['image']);
            $v['image'] = $uploaded;
        }
        if (!$v['image'] && !$errors) $errors['image'] = 'Mahsulot rasmini yuklang';

        if (!$errors) {
            $cols = [$v['name_uz'], $v['name_ru'], $v['desc_uz'], $v['desc_ru'], $v['category'], $v['code'], $v['pack'], $v['origin_uz'], $v['origin_ru'],
                $v['price'] === '' ? null : (float)$v['price'], $v['price_unit'], $v['image'], $v['is_active'], $v['sort_order'], now(),
                $v['name_en'], $v['desc_en'], $v['origin_en']];
            if ($product) {
                q('UPDATE products SET name_uz=?, name_ru=?, desc_uz=?, desc_ru=?, category=?, code=?, pack=?, origin_uz=?, origin_ru=?,
                   price=?, price_unit=?, image=?, is_active=?, sort_order=?, updated_at=?, name_en=?, desc_en=?, origin_en=? WHERE id=?', [...$cols, $id]);
                flash("«{$v['name_uz']}» saqlandi");
            } else {
                q('INSERT INTO products (name_uz,name_ru,desc_uz,desc_ru,category,code,pack,origin_uz,origin_ru,price,price_unit,image,is_active,sort_order,updated_at,name_en,desc_en,origin_en,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$cols, now()]);
                flash("«{$v['name_uz']}» qo'shildi");
            }
            redirect(url('products'));
        }
    }

    $err = fn($k) => isset($errors[$k]) ? '<small class="field-error">' . e($errors[$k]) . '</small>' : '';
    $priceVal = $v['price'] === null || $v['price'] === '' ? '' : rtrim(rtrim((string)$v['price'], '0'), '.');
    layout_start($product ? 'Mahsulotni tahrirlash' : 'Yangi mahsulot', 'products', $admin);
    ?>
<div class="page-head"><a href="<?= url('products') ?>" class="back-link">← Mahsulotlar ro'yxati</a></div>
<?php if ($errors): ?><div class="alert alert-error">Formada xatolar bor — quyida belgilangan maydonlarni tekshiring.</div><?php endif; ?>
<form method="post" action="<?= e(url('product', ['id' => $product ? $id : null])) ?>" enctype="multipart/form-data" class="edit-grid" novalidate>
  <?= csrf_field() ?>
  <?php if ($product): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
  <input type="hidden" name="image_current" value="<?= e($v['image']) ?>">
  <div class="edit-main">
    <section class="card glass">
      <div class="card-head"><h2>Asosiy ma'lumotlar</h2></div>
      <div class="tabs" data-tabs><button type="button" class="active" data-tab="uz">O'zbekcha</button><button type="button" data-tab="ru">Ruscha</button><button type="button" data-tab="en">Inglizcha</button></div>
      <div data-pane="uz">
        <label class="field <?= isset($errors['name_uz']) ? 'has-error' : '' ?>"><span>Mahsulot nomi *</span>
          <input name="name_uz" value="<?= e($v['name_uz']) ?>" required maxlength="190" placeholder="Masalan: Limon kislotasi (anhidrid)"><?= $err('name_uz') ?></label>
        <label class="field"><span>Tavsif</span><textarea name="desc_uz" rows="5" maxlength="3000" placeholder="Qo'llanilish sohasi, xususiyatlari, qadoqlash..."><?= e($v['desc_uz']) ?></textarea>
          <small class="hint">Bu matnni AI maslahatchi ham mijozlarga tushuntirishda ishlatadi.</small></label>
        <label class="field"><span>Kelib chiqishi</span><input name="origin_uz" value="<?= e($v['origin_uz']) ?>" maxlength="60" placeholder="Xitoy" list="originsUz"></label>
      </div>
      <div data-pane="ru" hidden>
        <label class="field"><span>Название</span><input name="name_ru" value="<?= e($v['name_ru']) ?>" maxlength="190" placeholder="Лимонная кислота (безводная)"></label>
        <label class="field"><span>Описание</span><textarea name="desc_ru" rows="5" maxlength="3000"><?= e($v['desc_ru']) ?></textarea></label>
        <label class="field"><span>Страна происхождения</span><input name="origin_ru" value="<?= e($v['origin_ru']) ?>" maxlength="60" placeholder="Китай" list="originsRu"></label>
      </div>
      <div data-pane="en" hidden>
        <label class="field"><span>Product name</span><input name="name_en" value="<?= e($v['name_en'] ?? '') ?>" maxlength="190" placeholder="Citric Acid Anhydrous"></label>
        <label class="field"><span>Description</span><textarea name="desc_en" rows="5" maxlength="3000"><?= e($v['desc_en'] ?? '') ?></textarea></label>
        <label class="field"><span>Country of origin</span><input name="origin_en" value="<?= e($v['origin_en'] ?? '') ?>" maxlength="60" placeholder="China"></label>
        <small class="hint">Bo'sh qolsa, ingliz tilidagi saytda o'zbekcha nomi ko'rsatiladi.</small>
      </div>
    </section>
    <section class="card glass">
      <div class="card-head"><h2>Narx va qadoq</h2></div>
      <div class="row-3">
        <label class="field <?= isset($errors['price']) ? 'has-error' : '' ?>"><span>Narx</span><input name="price" inputmode="decimal" value="<?= e($priceVal) ?>" placeholder="Bo'sh — so'rov bo'yicha"><?= $err('price') ?></label>
        <label class="field"><span>Narx birligi</span><input name="price_unit" value="<?= e($v['price_unit']) ?>" list="units" maxlength="30"></label>
        <label class="field"><span>Qadoq</span><input name="pack" value="<?= e($v['pack']) ?>" list="packs" maxlength="50"></label>
      </div>
      <small class="hint">Narx bo'sh qolsa, saytda «Narx so'rov bo'yicha» deb ko'rsatiladi va AI aniq narxni menejer aytishini tushuntiradi.</small>
      <div class="row-2" style="margin-top:14px">
        <label class="field"><span>Kategoriya</span><select name="category"><?php foreach (CAT_NAMES as $k => $l): ?><option value="<?= $k ?>" <?= $v['category'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>E-kod</span><input name="code" value="<?= e($v['code']) ?>" maxlength="30" placeholder="E330"></label>
      </div>
    </section>
  </div>
  <aside class="edit-side">
    <section class="card glass">
      <div class="card-head"><h2>Rasm</h2></div>
      <label class="dropzone <?= isset($errors['image']) ? 'has-error' : '' ?>" id="dropzone">
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" id="imageInput">
        <img id="imagePreview" src="<?= e($v['image']) ?>" alt="" <?= $v['image'] ? '' : 'hidden' ?>>
        <span class="dz-text" <?= $v['image'] ? 'hidden' : '' ?>><?= icon('image') ?><b>Rasmni shu yerga tashlang</b><small>yoki bosib tanlang · JPG, PNG, WEBP · istalgan hajm — avtomatik optimallashtiriladi</small></span>
      </label>
      <?= $err('image') ?>
      <div class="upload-status" id="uploadStatus" hidden></div>
      <small class="hint" style="display:block;margin-top:8px"><?= $v['image'] ? 'Almashtirish uchun yangi rasm tanlang. ' : '' ?>Tavsiya: vertikal 4:5 rasm (masalan 800×1000). Katta rasm 1600 px gacha kichraytirilib, WebP ga o'giriladi.</small>
    </section>
    <section class="card glass">
      <div class="card-head"><h2>Ko'rinish</h2></div>
      <label class="toggle-row"><span><b>Saytda ko'rsatish</b><small>O'chirilsa, mahsulot katalogda va AI javoblarida ko'rinmaydi</small></span>
        <input type="checkbox" name="is_active" <?= $v['is_active'] ? 'checked' : '' ?> class="switch-input"></label>
      <label class="field"><span>Tartib raqami</span><input type="number" name="sort_order" value="<?= (int)$v['sort_order'] ?>"><small class="hint">Kichik raqam — katalogda yuqorida</small></label>
    </section>
    <div class="sticky-actions">
      <a href="<?= url('products') ?>" class="btn btn-ghost">Bekor qilish</a>
      <button type="submit" class="btn btn-primary"><?= icon('check') ?><?= $product ? 'Saqlash' : "Qo'shish" ?></button>
    </div>
  </aside>
</form>
<datalist id="units"><option value="so'm/kg"><option value="so'm/qop"><option value="so'm/bochka"><option value="so'm/tonna"><option value="$/kg"><option value="$/tonna"></datalist>
<datalist id="packs"><option value="25 kg"><option value="20 kg"><option value="50 kg"><option value="215 kg"><option value="300 kg"><option value="1000 kg (big-bag)"></datalist>
<datalist id="originsUz"><option value="Xitoy"><option value="Turkiya"><option value="Hindiston"><option value="Yevropa"><option value="Rossiya"><option value="O'zbekiston"></datalist>
<datalist id="originsRu"><option value="Китай"><option value="Турция"><option value="Индия"><option value="Европа"><option value="Россия"><option value="Узбекистан"></datalist>
<?php
    layout_end();
}

function page_leads(array $admin): void
{
    $status = isset(LEAD_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
    $source = isset(LEAD_SOURCES[$_GET['source'] ?? '']) ? $_GET['source'] : '';
    $search = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $back = ['status' => $status, 'source' => $source, 'q' => $search, 'p' => $page > 1 ? $page : null];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $id = (int)($_POST['id'] ?? 0);
        $st = $_POST['status'] ?? '';
        if (($_POST['action'] ?? '') === 'status' && isset(LEAD_STATUSES[$st])) {
            // Telegram guruhidagi xabar ham yangilanadi
            $code = (string)q('SELECT code FROM leads WHERE id = ?', [$id])->fetchColumn();
            [$ok, $info] = $code ? set_lead_status($code, $st, ($admin['full_name'] ?: $admin['username']) . ' (admin panel)') : [false, 'Ariza topilmadi'];
            flash($ok ? 'Ariza holati yangilandi: ' . LEAD_STATUSES[$st][0] : $info, $ok ? 'success' : 'error');
        } elseif (($_POST['action'] ?? '') === 'delete') {
            q('DELETE FROM leads WHERE id = ?', [$id]);
            flash("Ariza o'chirildi");
        }
        redirect(url('leads', $back));
    }

    $where = [];
    $params = [];
    if ($status) { $where[] = 'l.status = ?'; $params[] = $status; }
    if ($source) { $where[] = 'l.source = ?'; $params[] = $source; }
    if ($search !== '') {
        $w = '(l.name LIKE ? OR l.code LIKE ? OR l.company LIKE ? OR EXISTS (SELECT 1 FROM lead_items li WHERE li.lead_id = l.id AND li.product_name LIKE ?)';
        array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
        $digits = preg_replace('/\D/', '', $search);
        if (strlen($digits) >= 3) { $w .= " OR REPLACE(REPLACE(l.phone, ' ', ''), '+', '') LIKE ?"; $params[] = "%$digits%"; }
        $where[] = $w . ')';
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    if (($_GET['export'] ?? '') === 'csv') {
        $rows = attach_items(q("SELECT l.* FROM leads l $whereSql ORDER BY l.created_at DESC", $params)->fetchAll());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="citric-arizalar-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        // Excel formula in'ektsiyasidan himoya
        $safe = fn(array $c) => array_map(fn($x) => preg_match('/^[=+\-@\t\r]/', (string)$x) ? "'" . $x : (string)$x, $c);
        fputcsv($out, ['Raqam', 'Sana', 'Ism', 'Telefon', 'Kompaniya', 'Mahsulotlar', 'Hajm', 'Izoh', 'Manba', 'Holat', 'Tasdiqlagan'], ';');
        foreach ($rows as $l) {
            fputcsv($out, $safe([$l['code'], $l['created_at'], $l['name'], $l['phone'], $l['company'],
                implode(', ', array_map(fn($i) => $i['product_name'] . ' ×' . $i['qty'], $l['items'])), $l['volume'], $l['note'],
                LEAD_SOURCES[$l['source']] ?? $l['source'], LEAD_STATUSES[$l['status']][0] ?? $l['status'], $l['confirmed_by']]), ';');
        }
        exit;
    }

    $perPage = 20;
    $total = (int)q("SELECT COUNT(*) FROM leads l $whereSql", $params)->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($pages, $page);
    $rows = attach_items(q("SELECT l.* FROM leads l $whereSql ORDER BY l.created_at DESC, l.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params)->fetchAll());
    $counts = ['' => (int)q('SELECT COUNT(*) FROM leads')->fetchColumn()];
    foreach (q('SELECT status, COUNT(*) c FROM leads GROUP BY status')->fetchAll() as $r) $counts[$r['status']] = (int)$r['c'];

    layout_start('Arizalar', 'leads', $admin);
    ?>
<div class="page-head">
  <div class="seg seg-wrap">
    <a href="<?= e(url('leads', ['source' => $source, 'q' => $search])) ?>" class="<?= $status === '' ? 'active' : '' ?>">Hammasi <b><?= $counts[''] ?></b></a>
    <?php foreach (LEAD_STATUSES as $k => [$l]): ?><a href="<?= e(url('leads', ['status' => $k, 'source' => $source, 'q' => $search])) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $l ?> <b><?= $counts[$k] ?? 0 ?></b></a><?php endforeach; ?>
  </div>
  <a href="<?= e(url('leads', ['status' => $status, 'source' => $source, 'q' => $search, 'export' => 'csv'])) ?>" class="btn btn-ghost">Excel'ga yuklash (CSV)</a>
</div>
<section class="card glass">
  <form class="toolbar" method="get" action="admin.php">
    <input type="hidden" name="page" value="leads"><?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="search-inline"><?= icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Ism, telefon, mahsulot yoki ariza raqami"></div>
    <select name="source" onchange="this.form.submit()"><option value="">Barcha manbalar</option>
      <?php foreach (LEAD_SOURCES as $k => $l): ?><option value="<?= $k ?>" <?= $source === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><?= $search || $status || $source ? "Filtr bo'yicha ariza topilmadi." : "Hali arizalar yo'q. Saytdagi AI maslahatchi, savat yoki aloqa formasi orqali kelgan arizalar shu yerda paydo bo'ladi." ?></div>
  <?php else: ?>
  <div class="lead-list">
    <?php foreach ($rows as $l): [$sl, $sc] = LEAD_STATUSES[$l['status']] ?? [$l['status'], 'gray']; ?>
      <details class="lead">
        <summary>
          <span class="lead-main"><b><?= e($l['name']) ?></b><a href="tel:<?= e(preg_replace('/\s/', '', $l['phone'])) ?>" class="lead-phone" onclick="event.stopPropagation()"><?= icon('phone') ?><?= e($l['phone']) ?></a></span>
          <span class="lead-products">
            <?php foreach (array_slice($l['items'], 0, 3) as $it): ?><span class="mini-prod"><?php if ($it['image']): ?><img src="<?= e($it['image']) ?>" alt=""><?php endif; ?><?= e($it['product_name']) ?><?= $it['qty'] > 1 ? ' ×' . (int)$it['qty'] : '' ?></span><?php endforeach; ?>
            <?php if (!$l['items']): ?><span class="muted">Mahsulot ko'rsatilmagan</span><?php endif; ?>
          </span>
          <span class="src src-<?= e($l['source']) ?>"><?= e(LEAD_SOURCES[$l['source']] ?? $l['source']) ?></span>
          <?= chip($sl, $sc) ?>
          <span class="muted nowrap lead-date"><?= fmt_date($l['created_at']) ?></span>
        </summary>
        <div class="lead-body">
          <dl>
            <div><dt>Ariza raqami</dt><dd><code>#<?= e($l['code']) ?></code></dd></div>
            <?php if ($l['company']): ?><div><dt>Kompaniya</dt><dd><?= e($l['company']) ?></dd></div><?php endif; ?>
            <?php if ($l['volume']): ?><div><dt>Hajm</dt><dd><?= e($l['volume']) ?></dd></div><?php endif; ?>
            <div><dt>Til</dt><dd><?= e(strtoupper($l['lang'])) ?></dd></div>
            <div><dt>Telegram</dt><dd><?= $l['tg_status'] === 'sent' ? chip('Guruhga yuborildi', 'green') : '<span class="chip chip-red" title="' . e($l['tg_status']) . '">Yuborilmadi</span>' ?></dd></div>
            <?php if ($l['confirmed_by']): ?><div><dt>Tasdiqlagan</dt><dd><?= e($l['confirmed_by']) ?> · <?= fmt_date($l['confirmed_at']) ?></dd></div><?php endif; ?>
          </dl>
          <?php if ($l['note']): ?><p class="lead-note"><?= nl2br(e($l['note'])) ?></p><?php endif; ?>
          <div class="lead-actions">
            <form method="post" action="<?= e(url('leads', $back)) ?>" class="inline-form"><?= csrf_field() ?>
              <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <span class="muted">Holatni o'zgartirish:</span>
              <?php foreach (LEAD_STATUSES as $k => [$lab, $c]): ?><button type="submit" name="status" value="<?= $k ?>" class="chip chip-btn chip-<?= $c ?> <?= $l['status'] === $k ? 'current' : '' ?>"><?= $lab ?></button><?php endforeach; ?>
            </form>
            <form method="post" action="<?= e(url('leads', $back)) ?>" onsubmit="return confirm('Bu arizani butunlay o\'chirasizmi?')"><?= csrf_field() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm danger-text"><?= icon('trash') ?>O'chirish</button>
            </form>
          </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php if ($pages > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="<?= e(url('leads', ['status' => $status, 'source' => $source, 'q' => $search, 'p' => $i > 1 ? $i : null])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
  <?php endif; ?>
</section>
<?php
    layout_end();
}

function page_settings(array $admin): void
{
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        switch ($_POST['action'] ?? '') {
            case 'profile':
                q('UPDATE admins SET full_name = ? WHERE id = ?', [mb_substr(trim((string)($_POST['full_name'] ?? '')), 0, 100), $admin['id']]);
                flash('Profil saqlandi');
                redirect(url('settings'));
            case 'password':
                $row = q('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']])->fetch();
                $new = (string)($_POST['new'] ?? '');
                if (!password_verify((string)($_POST['current'] ?? ''), $row['password_hash'])) $errors['current'] = "Joriy parol noto'g'ri";
                elseif (mb_strlen($new) < 8) $errors['new'] = "Yangi parol kamida 8 belgidan iborat bo'lsin";
                elseif ($new !== ($_POST['confirm'] ?? '')) $errors['confirm'] = 'Parollar mos kelmadi';
                if (!$errors) {
                    q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
                    session_regenerate_id(true);
                    flash("Parol o'zgartirildi");
                    redirect(url('settings'));
                }
                break;
            case 'add_admin':
                $u = trim((string)($_POST['username'] ?? ''));
                $pw = (string)($_POST['password'] ?? '');
                if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $u)) $errors['username'] = "Login 3–40 ta lotin harf, raqam yoki _ . - dan iborat bo'lsin";
                elseif (q('SELECT 1 FROM admins WHERE username = ?', [$u])->fetch()) $errors['username'] = 'Bunday login allaqachon bor';
                elseif (mb_strlen($pw) < 8) $errors['password'] = "Parol kamida 8 belgidan iborat bo'lsin";
                if (!$errors) {
                    q('INSERT INTO admins (username, password_hash, full_name, created_at) VALUES (?,?,?,?)',
                        [$u, password_hash($pw, PASSWORD_DEFAULT), mb_substr(trim((string)($_POST['full_name'] ?? '')), 0, 100), now()]);
                    flash("«{$u}» administrator sifatida qo'shildi");
                    redirect(url('settings'));
                }
                break;
            case 'delete_admin':
                if ((int)($_POST['id'] ?? 0) === (int)$admin['id']) flash("O'zingizni o'chira olmaysiz", 'error');
                else { q('DELETE FROM admins WHERE id = ?', [(int)$_POST['id']]); flash("Administrator o'chirildi"); }
                redirect(url('settings'));
            case 'tg_test':
                $chat = group_chat_id();
                $r = $chat ? tg('sendMessage', ['chat_id' => $chat, 'parse_mode' => 'HTML', 'text' => "🧪 <b>Sinov xabari</b>\nCITRIC.UZ admin panelidan yuborildi — bot guruhda to'g'ri ishlayapti."])
                    : ['ok' => false, 'description' => 'guruh ulanmagan'];
                flash(($r['ok'] ?? false) ? 'Sinov xabari guruhga yuborildi' : 'Yuborilmadi: ' . ($r['description'] ?? '?'), ($r['ok'] ?? false) ? 'success' : 'error');
                redirect(url('settings'));
            case 'report_save':
                $time = (string)($_POST['report_time'] ?? '20:00');
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) { flash("Vaqt noto'g'ri (masalan 20:00)", 'error'); redirect(url('settings')); }
                save_setting('report_enabled', isset($_POST['report_enabled']) ? '1' : '0');
                save_setting('report_time', $time);
                flash(isset($_POST['report_enabled']) ? "Kunlik hisobot har kuni soat $time da yuboriladi" : "Kunlik hisobot o'chirildi");
                redirect(url('settings'));
            case 'report_send':
                $kind = in_array($_POST['kind'] ?? '', ['day', 'yday', 'week', 'month'], true) ? $_POST['kind'] : 'day';
                $r = send_report($kind, null, true);
                flash(($r['ok'] ?? false) ? 'Hisobot (PDF) guruhga yuborildi' : 'Yuborilmadi: ' . ($r['description'] ?? '?'), ($r['ok'] ?? false) ? 'success' : 'error');
                redirect(url('settings'));
            case 'tg_webhook':
                $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
                $base = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $r = tg('setWebhook', ['url' => $base . '/index.php?api=telegram', 'secret_token' => cfg('webhook_secret'),
                    'allowed_updates' => ['message', 'callback_query', 'my_chat_member'], 'drop_pending_updates' => false]);
                setup_bot_commands(); // /hisobot, /yangi ... menyusi
                flash(($r['ok'] ?? false) ? 'Webhook ulandi: ' . $base . '/index.php?api=telegram' : 'Ulanmadi: ' . ($r['description'] ?? '?') . ' (sayt HTTPS bilan ochilishi kerak)',
                    ($r['ok'] ?? false) ? 'success' : 'error');
                redirect(url('settings'));
        }
    }

    $me = q('SELECT * FROM admins WHERE id = ?', [$admin['id']])->fetch();
    $admins = q('SELECT id, username, full_name, last_login FROM admins ORDER BY id')->fetchAll();
    $chatId = group_chat_id();
    $bot = tg('getMe');
    $hook = tg('getWebhookInfo');
    $err = fn($k) => isset($errors[$k]) ? '<small class="field-error">' . e($errors[$k]) . '</small>' : '';
    layout_start('Sozlamalar', 'settings', $admin);
    ?>
<div class="grid-2 settings-grid">
  <section class="card glass">
    <div class="card-head"><h2>Profil</h2></div>
    <form method="post" action="<?= url('settings') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <label class="field"><span>Login</span><input value="<?= e($me['username']) ?>" disabled></label>
      <label class="field"><span>To'liq ism</span><input name="full_name" value="<?= e($me['full_name']) ?>" maxlength="100"></label>
      <p class="muted small" style="margin-bottom:14px">Oxirgi kirish: <?= fmt_date($me['last_login']) ?></p>
      <button class="btn btn-primary">Saqlash</button>
    </form>
  </section>
  <section class="card glass">
    <div class="card-head"><h2>Parolni o'zgartirish</h2></div>
    <form method="post" action="<?= url('settings') ?>" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label class="field <?= isset($errors['current']) ? 'has-error' : '' ?>"><span>Joriy parol</span><input type="password" name="current" required autocomplete="current-password"><?= $err('current') ?></label>
      <div class="row-2">
        <label class="field <?= isset($errors['new']) ? 'has-error' : '' ?>"><span>Yangi parol</span><input type="password" name="new" required minlength="8" autocomplete="new-password"><?= $err('new') ?></label>
        <label class="field <?= isset($errors['confirm']) ? 'has-error' : '' ?>"><span>Takrorlang</span><input type="password" name="confirm" required autocomplete="new-password"><?= $err('confirm') ?></label>
      </div>
      <button class="btn btn-primary">Parolni yangilash</button>
    </form>
  </section>
  <section class="card glass">
    <div class="card-head"><h2>Telegram bot</h2></div>
    <ul class="status-list">
      <li><span>Bot</span><b><?= ($bot['ok'] ?? false) ? chip('@' . $bot['result']['username'] . ' · ishlayapti', 'green') : chip('Ulanmadi', 'red') ?></b></li>
      <li><span>Operatorlar guruhi</span><b><?= $chatId ? chip('Ulangan · ' . $chatId, 'green') : chip('Ulanmagan', 'amber') ?></b></li>
      <li><span>«Tasdiqlandi» tugmasi (webhook)</span><b><?= !empty($hook['result']['url']) ? chip('Ulangan', 'green') : chip('Ulanmagan', 'amber') ?></b></li>
    </ul>
    <?php if (!$chatId): ?>
      <div class="alert alert-info">Telegram'da operatorlar guruhini oching va <b>@<?= e($bot['result']['username'] ?? 'citricuz_bot') ?></b> ni guruhga qo'shing. Webhook ulangan bo'lsa, bot guruhni o'zi aniqlaydi.</div>
    <?php endif; ?>
    <div class="btn-row">
      <form method="post" action="<?= url('settings') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="tg_webhook"><button class="btn btn-ghost"><?= icon('link') ?>Webhookni ulash</button></form>
      <?php if ($chatId): ?><form method="post" action="<?= url('settings') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="tg_test"><button class="btn btn-ghost"><?= icon('send') ?>Sinov xabari</button></form><?php endif; ?>
    </div>
    <p class="hint" style="margin-top:12px">Saytni hostingga joylagandan keyin bir marta «Webhookni ulash» tugmasini bosing — shundan keyin guruhdagi barcha tugmalar (✅ Tasdiqlandi, 💰 Sotildi, ✖️ Bekor qilish, hisobot tugmalari) va /hisobot, /yangi buyruqlari ishlaydi.</p>
  </section>
  <?php $rs = report_settings(); $last = setting('report_last_result'); $site = (string)setting('site_url'); ?>
  <section class="card glass">
    <div class="card-head"><div><h2>Kunlik hisobot (PDF)</h2><p class="muted">Har kuni belgilangan vaqtda Telegram guruhiga PDF hisobot yuboriladi</p></div></div>
    <form method="post" action="<?= url('settings') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="report_save">
      <label class="toggle-row"><span><b>Avtomatik yuborish</b><small>Arizalar, tasdiqlanganlar, top mahsulotlar, grafik va arizalar ro'yxati</small></span>
        <input type="checkbox" name="report_enabled" class="switch-input" <?= $rs['enabled'] ? 'checked' : '' ?>></label>
      <div class="report-row">
        <label class="field"><span>Yuborish vaqti (Toshkent)</span><input type="time" name="report_time" value="<?= e($rs['time']) ?>" required></label>
        <button class="btn btn-primary" style="align-self:flex-end">Saqlash</button>
      </div>
    </form>
    <ul class="status-list">
      <li><span>Oxirgi yuborish</span><b><?= $last ? e($last) : '<span class="muted">hali yuborilmagan</span>' ?></b></li>
      <li><span>Bugun yuborilganmi</span><b><?= $rs['last'] === date('Y-m-d') ? chip('Ha', 'green') : chip('Yo\'q — ' . $rs['time'] . ' dan keyin', 'amber') ?></b></li>
    </ul>
    <form method="post" action="<?= url('settings') ?>" class="btn-row"><?= csrf_field() ?><input type="hidden" name="action" value="report_send">
      <button class="btn btn-ghost" name="kind" value="day"><?= icon('send') ?>Bugungi hisobot</button>
      <button class="btn btn-ghost" name="kind" value="week">7 kunlik</button>
      <button class="btn btn-ghost" name="kind" value="month">30 kunlik</button>
    </form>
    <p class="hint" style="margin-top:12px">Hisobot vaqti kelgach saytga yoki botga birinchi murojaatda o'zi yuboriladi. Aynan belgilangan daqiqada kelishi uchun hostingda (ISPmanager → Cron) shu havolani har kuni <?= e($rs['time']) ?> da ochadigan vazifa qo'shing:</p>
    <div class="cron-box"><code><?= e(($site ?: 'https://SAYT/') . 'index.php?cron=report&key=' . cron_key()) ?></code></div>
    <p class="hint" style="margin-top:8px">Cron buyrug'i: <code>wget -q -O /dev/null "<?= e(($site ?: 'https://SAYT/') . 'index.php?cron=report&key=' . cron_key()) ?>"</code></p>
  </section>
  <section class="card glass">
    <div class="card-head"><h2>Administratorlar</h2></div>
    <ul class="admin-list">
      <?php foreach ($admins as $a): ?>
        <li><span class="avatar sm"><?= e(mb_strtoupper(mb_substr($a['full_name'] ?: $a['username'], 0, 1))) ?></span>
          <span><b><?= e($a['full_name'] ?: $a['username']) ?></b><small><?= e($a['username']) ?> · oxirgi kirish: <?= fmt_date($a['last_login']) ?></small></span>
          <?php if ((int)$a['id'] !== (int)$admin['id']): ?>
            <form method="post" action="<?= url('settings') ?>" onsubmit="return confirm('Administratorni o\'chirasizmi?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_admin"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="icon-btn sm danger" title="O'chirish"><?= icon('trash') ?></button></form>
          <?php else: ?><?= chip('Siz', 'blue') ?><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <details class="add-admin" <?= isset($errors['username']) || isset($errors['password']) ? 'open' : '' ?>>
      <summary class="btn btn-ghost btn-sm"><?= icon('plus') ?>Yangi administrator</summary>
      <form method="post" action="<?= url('settings') ?>" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
        <div class="row-2">
          <label class="field <?= isset($errors['username']) ? 'has-error' : '' ?>"><span>Login</span><input name="username" required value="<?= e($_POST['username'] ?? '') ?>"><?= $err('username') ?></label>
          <label class="field"><span>To'liq ism</span><input name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>"></label>
        </div>
        <label class="field <?= isset($errors['password']) ? 'has-error' : '' ?>"><span>Parol</span><input type="password" name="password" required minlength="8" autocomplete="new-password"><?= $err('password') ?></label>
        <button class="btn btn-primary">Qo'shish</button>
      </form>
    </details>
  </section>
</div>
<?php
    layout_end();
}

/* ============================================================================
   Marshrutlash
   ============================================================================ */
$page = (string)($_GET['page'] ?? 'dashboard');
if ($page === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirect(url('login'));
}
if ($page === 'login') { page_login(); exit; }

$admin = current_admin();
if (!$admin) redirect(url('login'));

switch ($page) {
    case 'analytics': page_analytics($admin); break;
    case 'products': page_products($admin); break;
    case 'product': page_product($admin); break;
    case 'leads': page_leads($admin); break;
    case 'settings': page_settings($admin); break;
    default: page_dashboard($admin);
}
