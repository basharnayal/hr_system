<?php
require_once __DIR__ . '/config.php';

function current_user() {
    if (!isset($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        try {
            // يحاول تضمين حالة الموظف (بعد تشغيل migrate_006)
            $stmt = db()->prepare("
                SELECT u.*, e.full_name, e.status AS emp_status
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {
            // قبل تشغيل migrate_006 — العمود غير موجود بعد
            $stmt = db()->prepare("SELECT u.*, e.full_name FROM users u LEFT JOIN employees e ON u.employee_id = e.id WHERE u.id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login() {
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    // تسجيل خروج فوري إذا تغيرت حالة الموظف إلى موقوف/منتهي
    if ($u['role'] !== 'admin' && isset($u['emp_status']) && $u['emp_status'] !== 'active') {
        logout();
        header('Location: login.php?msg=blocked');
        exit;
    }
}

function require_admin() {
    require_login();
    $u = current_user();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        die('غير مصرح لك بالدخول لهذه الصفحة');
    }
}

function logout() {
    $_SESSION = [];
    session_destroy();
}

// حماية CSRF بسيطة
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
            http_response_code(400);
            die('رمز الحماية غير صالح، يرجى المحاولة مجدداً');
        }
    }
}

// إرجاع أول حرفين من الاسم لاستخدامها في Avatar
function initials($name) {
    $name = trim((string)$name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/u', $name);
    if (count($parts) >= 2) {
        return mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1);
    }
    return mb_substr($parts[0], 0, 2);
}

// تسجيل نشاط المدير في جدول activity_log
function log_activity(string $action, ?string $entity_type = null, ?int $entity_id = null, ?string $details = null): void {
    $u = current_user();
    if (!$u) return;
    try {
        $stmt = db()->prepare(
            "INSERT INTO activity_log (admin_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$u['id'], $action, $entity_type, $entity_id, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {
        // الجدول غير موجود بعد (قبل تشغيل migrate_005.sql) - تجاهل
    }
}
