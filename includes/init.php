<?php
// แกนกลางของระบบ: session, ฐานข้อมูล, ตัวช่วย, สิทธิ์การใช้งาน, Green Point และ CO2e
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

define('ROOT_PATH', str_replace('\\', '/', realpath(__DIR__ . '/..')));
$__doc = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
define('BASE_URL', ($__doc && stripos(ROOT_PATH, $__doc) === 0) ? substr(ROOT_PATH, strlen($__doc)) : '');

const SITE_NAME = 'GreenLoop Net Zero';
const SESSION_IDLE = 1800;       // ไม่ใช้งานเกิน 30 นาที ต้องเข้าสู่ระบบใหม่

const CONDITIONS = [
    'like_new' => 'เหมือนใหม่',
    'very_good' => 'ดีมาก',
    'good' => 'ดี',
    'fair' => 'พอใช้',
];
const STATUS_LABELS = [
    'pending' => ['รอตรวจสอบ', 'warning'],
    'approved' => ['อนุมัติแล้ว', 'success'],
    'rejected' => ['ไม่อนุมัติ', 'danger'],
    'sold' => ['ขายแล้ว', 'secondary'],
    'hidden' => ['ซ่อน', 'secondary'],
    'accepted' => ['รับคำขอแล้ว', 'info'],
    'completed' => ['สำเร็จ', 'success'],
    'cancelled' => ['ยกเลิก', 'danger'],
    'delivered' => ['ส่งมอบแล้ว', 'success'],
    'active' => ['ใช้งานได้', 'success'],
    'suspended' => ['ระงับสิทธิ์', 'danger'],
    'scheduled' => ['รอเผยแพร่', 'info'],
    'live' => ['กำลังแสดง', 'success'],
    'expired' => ['หมดอายุ', 'secondary'],
    'off' => ['ปิดการแสดง', 'dark'],
];
const FACTOR_GROUPS = [
    'energy' => ['ไฟฟ้าและพลังงาน', '⚡'],
    'transport' => ['การเดินทาง', '🚗'],
    'food' => ['อาหาร', '🍛'],
    'waste' => ['ขยะ', '🗑️'],
    'device' => ['อุปกรณ์ดิจิทัล', '📱'],
];
const PERIODS = [12 => 'ต่อเดือน', 52 => 'ต่อสัปดาห์', 1 => 'ต่อปี'];
const ACTIVITY_TYPES = [
    'footprint' => ['คำนวณฟุตพริ้นท์', '🧮'],
    'sell' => ['ขายมือสอง', '🏷️'],
    'buy' => ['ซื้อมือสอง', '🛒'],
    'recycle' => ['รีไซเคิล', '♻️'],
    'redeem' => ['แลกของรางวัล', '🎁'],
    'bonus' => ['โบนัส', '⭐'],
];
const CONTENT_TYPES = ['news' => 'ข่าว / บทความ', 'banner' => 'แบนเนอร์', 'campaign' => 'แคมเปญแต้มคูณ'];
const NOTI_TYPES = [
    'member' => ['สมาชิกใหม่', '👤'],
    'product' => ['สินค้ารออนุมัติ', '📦'],
    'order' => ['คำสั่งซื้อ', '🛒'],
    'recycle' => ['คำขอรีไซเคิล', '♻️'],
    'redeem' => ['แลกของรางวัล', '🎁'],
    'issue' => ['รายงานปัญหา', '⚠️'],
];
const RECYCLE_METHODS = ['dropoff' => 'นำมาส่งที่จุดรับ', 'parcel' => 'ส่งพัสดุมาที่ศูนย์', 'pickup' => 'นัดรับถึงที่'];
// ระดับสมาชิกคิดจากแต้มสะสมตลอดการใช้งาน: [แต้มขั้นต่ำ, ชื่อ, ไอคอน]
const LEVELS = [
    [0, 'เมล็ดพันธุ์', '🌰'],
    [100, 'ต้นกล้า', '🌱'],
    [300, 'ต้นไม้', '🌳'],
    [600, 'ผืนป่า', '🌲'],
    [1000, 'Net Zero Hero', '🌍'],
];
const SECRET_QUESTIONS = ['ชื่อสัตว์เลี้ยงตัวแรกของคุณ', 'ชื่อโรงเรียนประถมของคุณ', 'อาหารจานโปรดของคุณ', 'ชื่อโครงการของเราคืออะไร'];
// เงื่อนไขเนื้อหาที่อยู่ในช่วงเวลาแสดงผล (ระบบกำหนดวันเวลาโพสต์)
const CONTENT_LIVE = 'is_active = 1 AND publish_at <= NOW() AND (expire_at IS NULL OR expire_at > NOW())';

// ---------------------------------------------------------------- ฐานข้อมูล
function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require ROOT_PATH . '/config.php';
        try {
            $pdo = new PDO(
                "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4",
                $c['db_user'],
                $c['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('<h3>เชื่อมต่อฐานข้อมูลไม่ได้</h3><p>กรุณานำเข้าไฟล์ <code>database.sql</code> และตรวจสอบการตั้งค่าใน <code>config.php</code></p>');
        }
    }
    return $pdo;
}

function q($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row($sql, $params = [])
{
    $r = q($sql, $params)->fetch();
    return $r ?: null;
}

function rows($sql, $params = [])
{
    return q($sql, $params)->fetchAll();
}

function val($sql, $params = [])
{
    return q($sql, $params)->fetchColumn();
}

function in_tx(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $r === false ? $pdo->rollBack() : $pdo->commit();
        return $r;
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// ---------------------------------------------------------------- ตัวช่วยทั่วไป
function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

function back($fallback = 'index.php')
{
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if ($ref && parse_url($ref, PHP_URL_HOST) . (parse_url($ref, PHP_URL_PORT) ? ':' . parse_url($ref, PHP_URL_PORT) : '') === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function flash($msg, $type = 'success')
{
    $_SESSION['flash'][] = [$type, $msg];
}

function post($key, $default = '')
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get($key, $default = '')
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function baht($n)
{
    $n = (float) $n;
    return '฿' . number_format($n, floor($n) == $n ? 0 : 2);
}

// แสดงตัวเลข โดยตัดทศนิยมที่เป็นศูนย์ออก
function num($n, $dec = 2)
{
    $s = number_format((float) $n, $dec);
    return $dec > 0 ? rtrim(rtrim($s, '0'), '.') : $s;
}

function kg($n)
{
    return num($n) . ' kgCO₂e';
}

function thai_date($dt, $time = true)
{
    if (!$dt) {
        return '-';
    }
    $t = strtotime($dt);
    $m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return date('j', $t) . ' ' . $m[(int) date('n', $t)] . ' ' . (date('Y', $t) + 543) . ($time ? ' ' . date('H:i', $t) : '');
}

function badge($status)
{
    $s = isset(STATUS_LABELS[$status]) ? STATUS_LABELS[$status] : [$status, 'secondary'];
    return '<span class="badge rounded-pill bg-' . $s[1] . ($s[1] === 'warning' || $s[1] === 'info' ? ' text-dark' : '') . '">' . e($s[0]) . '</span>';
}

// ค่าจากฟอร์ม datetime-local (2026-01-31T09:30) -> DATETIME ของ MySQL หรือ null เมื่อว่าง/ผิดรูปแบบ
function parse_datetime($s)
{
    $t = $s === '' ? false : strtotime($s);
    return $t === false ? null : date('Y-m-d H:i:s', $t);
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function setting($name, $default = '')
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (rows('SELECT name, value FROM settings') as $r) {
            $all[$r['name']] = $r['value'];
        }
    }
    return isset($all[$name]) && $all[$name] !== '' ? $all[$name] : $default;
}

// ---------------------------------------------------------------- Session และ CSRF
if (!empty($_SESSION['uid']) && time() - (isset($_SESSION['last_seen']) ? $_SESSION['last_seen'] : time()) > SESSION_IDLE) {
    session_unset();
    session_regenerate_id(true);
    flash('หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบอีกครั้ง', 'warning');
}
$_SESSION['last_seen'] = time();

if (is_post() && !hash_equals(csrf_token(), isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '')) {
    flash('คำขอไม่ถูกต้อง หรือไฟล์ที่อัปโหลดมีขนาดใหญ่เกินไป กรุณาลองใหม่', 'danger');
    back();
}

// ---------------------------------------------------------------- ผู้ใช้และสิทธิ์
function user()
{
    static $u = false;
    if ($u === false) {
        $u = empty($_SESSION['uid']) ? null : row('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
        if ($u && $u['status'] !== 'active') {
            unset($_SESSION['uid']);
            $u = null;
        }
    }
    return $u;
}

function require_login()
{
    if (!user()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
        flash('กรุณาเข้าสู่ระบบก่อนใช้งาน', 'warning');
        redirect('login.php');
    }
    return user();
}

function require_admin()
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        flash('หน้านี้สำหรับผู้ดูแลระบบเท่านั้น', 'danger');
        redirect('index.php');
    }
    return $u;
}

// คืนข้อความผิดพลาดของรหัสผ่าน หรือ '' เมื่อผ่านเงื่อนไข
function password_error($pw, $confirm)
{
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร และมีทั้งตัวอักษรภาษาอังกฤษและตัวเลข';
    }
    return $pw === $confirm ? '' : 'ยืนยันรหัสผ่านไม่ตรงกัน';
}

// ตรวจสอบชื่อ อีเมล เบอร์โทรจากฟอร์ม คืน array ของข้อผิดพลาดรายช่อง ($selfId = สมาชิกที่กำลังแก้ไข)
function profile_errors($selfId = 0)
{
    $err = [];
    if (mb_strlen(post('name')) < 2 || mb_strlen(post('name')) > 100) {
        $err['name'] = 'กรุณากรอกชื่อ-นามสกุล 2-100 ตัวอักษร';
    }
    if (!filter_var(post('email'), FILTER_VALIDATE_EMAIL) || strlen(post('email')) > 120) {
        $err['email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [post('email'), $selfId])) {
        $err['email'] = 'อีเมลนี้ถูกใช้สมัครสมาชิกแล้ว';
    }
    if (!preg_match('/^0\d{8,9}$/', post('phone'))) {
        $err['phone'] = 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก ขึ้นต้นด้วย 0';
    }
    if (mb_strlen(post('address')) > 255) {
        $err['address'] = 'ที่อยู่ยาวเกิน 255 ตัวอักษร';
    }
    return $err;
}

// ตัวช่วยแสดงผลการตรวจสอบรายช่องของฟอร์ม
function invalid($errors, $key)
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

function feedback($errors, $key, $default)
{
    return '<div class="invalid-feedback">' . e(isset($errors[$key]) ? $errors[$key] : $default) . '</div>';
}

function secret_normalize($s)
{
    return mb_strtolower(trim($s));
}

// ---------------------------------------------------------------- อัปโหลดรูปภาพ
// คืนค่า path ของไฟล์, null เมื่อไม่ได้เลือกไฟล์, false เมื่อไฟล์ไม่ถูกต้อง
function upload_image($field, $dir)
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    $info = $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) ? @getimagesize($f['tmp_name']) : false;
    $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($exts[$info[2]]) || $f['size'] > 5 * 1024 * 1024) {
        flash('รูปภาพไม่ถูกต้อง (รองรับ JPG, PNG, GIF, WEBP ขนาดไม่เกิน 5MB)', 'danger');
        return false;
    }
    $rel = 'uploads/' . $dir;
    if (!is_dir(ROOT_PATH . '/' . $rel)) {
        mkdir(ROOT_PATH . '/' . $rel, 0775, true);
    }
    $rel .= '/' . bin2hex(random_bytes(16)) . '.' . $exts[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], ROOT_PATH . '/' . $rel)) {
        flash('บันทึกไฟล์ไม่สำเร็จ', 'danger');
        return false;
    }
    return $rel;
}

// ---------------------------------------------------------------- แจ้งเตือนผู้ดูแลระบบ
function notify_admin($type, $title, $message = '', $link = '', $uid = null)
{
    q('INSERT INTO notifications (type, user_id, title, message, link) VALUES (?,?,?,?,?)', [$type, $uid, mb_substr($title, 0, 150), mb_substr($message, 0, 500), $link]);
}

// ---------------------------------------------------------------- Green Point และ CO2e
// แคมเปญแต้มคูณที่อยู่ในช่วงเวลาแสดงผล (เลือกตัวคูณสูงสุด)
function active_campaign()
{
    static $c = false;
    if ($c === false) {
        $c = row("SELECT * FROM contents WHERE type = 'campaign' AND " . CONTENT_LIVE . ' ORDER BY point_multiplier DESC, id DESC LIMIT 1');
    }
    return $c;
}

// บันทึกกิจกรรมลงบัญชีกลาง: แต้ม (ค่าลบ = ใช้แต้ม) และ CO2e ที่ลดได้ คืน false เมื่อแต้มไม่พอ
function add_activity($uid, $type, $refId, $desc, $points, $co2 = 0)
{
    $points = (int) $points;
    if ($points > 0 && ($c = active_campaign())) {
        $points = (int) round($points * $c['point_multiplier']);
        $desc .= ' (แคมเปญ x' . num($c['point_multiplier'], 1) . ')';
    }
    if ($points < 0) {
        $st = q('UPDATE users SET green_points = green_points - ? WHERE id = ? AND green_points >= ?', [-$points, $uid, -$points]);
        if (!$st->rowCount()) {
            return false;
        }
    } elseif ($points > 0) {
        q('UPDATE users SET green_points = green_points + ? WHERE id = ?', [$points, $uid]);
    }
    q('INSERT INTO green_activities (user_id, type, ref_id, description, points, co2_saved_kg) VALUES (?,?,?,?,?,?)', [$uid, $type, $refId, mb_substr($desc, 0, 255), $points, round($co2, 2)]);
    return true;
}

// ระดับสมาชิก: คืน [ระดับปัจจุบัน, ระดับถัดไปหรือ null, แต้มสะสมตลอดการใช้งาน]
function user_level($uid)
{
    $earned = (int) val('SELECT COALESCE(SUM(points), 0) FROM green_activities WHERE user_id = ? AND points > 0', [$uid]);
    $cur = LEVELS[0];
    $next = null;
    foreach (LEVELS as $lv) {
        if ($earned >= $lv[0]) {
            $cur = $lv;
        } elseif ($next === null) {
            $next = $lv;
        }
    }
    return [$cur, $next, $earned];
}

// CO2e ที่ลดได้จากกิจกรรมของสมาชิก (ระบุปี ค.ศ. หรือ null = ทั้งหมด)
function co2_saved($uid, $year = null)
{
    if ($year === null) {
        return (float) val('SELECT COALESCE(SUM(co2_saved_kg), 0) FROM green_activities WHERE user_id = ?', [$uid]);
    }
    return (float) val('SELECT COALESCE(SUM(co2_saved_kg), 0) FROM green_activities WHERE user_id = ? AND YEAR(created_at) = ?', [$uid, $year]);
}

function trees($kg)
{
    return $kg / max(0.1, (float) setting('tree_kg_per_year', 9.5));
}

// สถานะของเนื้อหาตามช่วงเวลาที่ตั้งไว้
function content_status($c)
{
    if (!$c['is_active']) {
        return 'off';
    }
    $now = time();
    if (strtotime($c['publish_at']) > $now) {
        return 'scheduled';
    }
    return $c['expire_at'] && strtotime($c['expire_at']) <= $now ? 'expired' : 'live';
}

// ---------------------------------------------------------------- คำสั่งซื้อและรีไซเคิล
// ปิดคำสั่งซื้อ: แจกแต้มทั้งสองฝ่าย และแบ่ง CO2e ที่ลดได้จากการใช้ซ้ำคนละครึ่ง
function complete_order($id)
{
    return in_tx(function () use ($id) {
        $o = row('SELECT o.*, p.title, c.co2_reuse_kg FROM orders o JOIN products p ON p.id = o.product_id JOIN categories c ON c.id = p.category_id WHERE o.id = ? FOR UPDATE', [$id]);
        if (!$o || $o['status'] !== 'pending') {
            return false;
        }
        q("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ?", [$id]);
        $half = $o['co2_reuse_kg'] / 2;
        add_activity($o['buyer_id'], 'buy', $id, 'ซื้อมือสอง: ' . $o['title'], setting('points_buy', 10), $half);
        add_activity($o['seller_id'], 'sell', $id, 'ขายมือสอง: ' . $o['title'], setting('points_sell', 20), $half);
        return true;
    });
}

function cancel_order($id)
{
    return in_tx(function () use ($id) {
        $o = row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]);
        if (!$o || $o['status'] !== 'pending') {
            return false;
        }
        q("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$id]);
        q("UPDATE products SET status = 'approved' WHERE id = ? AND status = 'sold'", [$o['product_id']]);
        return true;
    });
}

// ปิดคำขอรีไซเคิลด้วยน้ำหนักที่ชั่งจริง: ให้แต้มและ CO2e ตามค่าที่ตั้งไว้
function complete_recycle($id, $weight)
{
    return in_tx(function () use ($id, $weight) {
        $r = row('SELECT r.*, c.co2_recycle_per_kg FROM recycle_requests r JOIN categories c ON c.id = r.category_id WHERE r.id = ? FOR UPDATE', [$id]);
        if (!$r || !in_array($r['status'], ['pending', 'accepted'], true) || $weight <= 0) {
            return false;
        }
        $points = max(10, (int) round($weight * (float) setting('points_recycle_per_kg', 20)));
        $co2 = round($weight * $r['co2_recycle_per_kg'], 2);
        add_activity($r['user_id'], 'recycle', $id, 'รีไซเคิล: ' . $r['item_name'], $points, $co2);
        // เก็บแต้มที่ได้จริง (รวมตัวคูณแคมเปญ) ไว้กับคำขอ
        $given = (int) val("SELECT points FROM green_activities WHERE type = 'recycle' AND ref_id = ? ORDER BY id DESC LIMIT 1", [$id]);
        q("UPDATE recycle_requests SET status = 'completed', weight_kg = ?, points_awarded = ?, co2_saved_kg = ?, completed_at = NOW() WHERE id = ?", [$weight, $given, $co2, $id]);
        return true;
    });
}

// ---------------------------------------------------------------- ส่วนแสดงผลที่ใช้ร่วมกัน
function page_header($title, $subtitle = '')
{
    return '<div class="page-head"><h1>' . e($title) . '</h1>' . ($subtitle ? '<p class="text-muted mb-0">' . e($subtitle) . '</p>' : '') . '</div>';
}

function product_thumb($p)
{
    if (!empty($p['image'])) {
        return '<img src="' . e(url($p['image'])) . '" alt="' . e($p['title']) . '" loading="lazy">';
    }
    return '<div class="ph">' . e($p['cat_icon']) . '</div>';
}

function product_card($p)
{
    $href = e(url('product.php?id=' . $p['id']));
    return '<div class="card product-card h-100"><a href="' . $href . '" class="thumb">' . product_thumb($p) . '</a>'
        . '<div class="card-body"><a class="title stretched-link" href="' . $href . '">' . e($p['title']) . '</a>'
        . '<div class="small text-muted">' . e($p['cat_name']) . ' · สภาพ' . CONDITIONS[$p['item_condition']] . '</div>'
        . '<div class="d-flex justify-content-between align-items-end mt-2"><span class="price">' . baht($p['price']) . '</span>'
        . '<span class="co2-tag">🌿 ลด ' . num($p['co2_reuse_kg'], 0) . ' kg</span></div></div></div>';
}

function avatar_html($u, $size = 36)
{
    if (!empty($u['avatar'])) {
        return '<img src="' . e(url($u['avatar'])) . '" class="avatar" style="width:' . $size . 'px;height:' . $size . 'px" alt="">';
    }
    return '<span class="avatar avatar-text" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size * .42) . 'px">' . e(mb_substr($u['name'], 0, 1)) . '</span>';
}

// แนบข้อมูลกราฟให้ <canvas> โดย assets/js/app.js จะสร้าง Chart.js ให้เอง
function chart_canvas($type, $labels, $data, $label = '', $height = 260)
{
    $cfg = ['type' => $type, 'labels' => array_values($labels), 'data' => array_map('floatval', array_values($data)), 'label' => $label];
    return '<div class="chart-box" style="height:' . (int) $height . 'px"><canvas data-chart="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '"></canvas></div>';
}

// '2026-05' -> 'พ.ค. 69'
function month_label($ym)
{
    $m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return $m[(int) substr($ym, 5, 2)] . ' ' . substr((int) substr($ym, 0, 4) + 543, 2);
}

// ป้ายเดือนย้อนหลัง n เดือน: ['2026-05' => 'พ.ค. 69', ...]
function month_keys($n = 6)
{
    $out = [];
    $first = strtotime(date('Y-m-01'));
    for ($i = $n - 1; $i >= 0; $i--) {
        $t = strtotime("-$i month", $first);
        $out[date('Y-m', $t)] = month_label(date('Y-m', $t));
    }
    return $out;
}
