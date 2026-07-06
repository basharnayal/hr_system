<?php
// إعدادات قاعدة البيانات - عدّلها حسب بيئتك
define('DB_HOST', 'localhost');
define('DB_NAME', 'hr_system');
define('DB_USER', 'اسم_المستخدم');
define('DB_PASS', 'كلمة_المرور');
define('DB_CHARSET', 'utf8mb4');

// المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// الاتصال بقاعدة البيانات
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
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
