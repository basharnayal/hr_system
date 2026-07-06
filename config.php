<?php
// إعدادات قاعدة البيانات - تُقرأ من DATABASE_URL (DigitalOcean App Platform)
$dbUrl = getenv('DATABASE_URL');
if ($dbUrl) {
    $p = parse_url($dbUrl);
    define('DB_HOST', $p['host']);
    define('DB_PORT', $p['port'] ?? '25060');
    define('DB_NAME', ltrim($p['path'], '/'));
    define('DB_USER', urldecode($p['user']));
    define('DB_PASS', urldecode($p['pass']));
} else {
    // احتياط: الاتصال المباشر — كلمة المرور تُقرأ من متغير البيئة DB_PASS
    define('DB_HOST', getenv('DB_HOST') ?: 'app-d904e414-7f1b-463e-9c57-e80d90aca0eb-do-user-19804508-0.d.db.ondigitalocean.com');
    define('DB_PORT', getenv('DB_PORT') ?: '25060');
    define('DB_NAME', getenv('DB_NAME') ?: 'db-almutrfi');
    define('DB_USER', getenv('DB_USER') ?: 'db-almutrfi');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

// المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// الاتصال بقاعدة البيانات
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET client_encoding TO 'UTF8'");
        } catch (PDOException $e) {
            die('خطأ في الاتصال بقاعدة البيانات: ' . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// جلب اسم الشركة
function company_name() {
    try {
        $stmt = db()->query("SELECT company_name FROM settings WHERE id = 1");
        $row = $stmt->fetch();
        return $row ? $row['company_name'] : 'شركتنا';
    } catch (Exception $e) {
        return 'شركتنا';
    }
}

// مساعد للهروب من HTML
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
