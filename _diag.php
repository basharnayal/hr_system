<?php
// صفحة تشخيص مؤقتة — تُحذف بعد حل المشكلة
if (($_GET['t'] ?? '') !== 'hrdiag7k2m9x') { http_response_code(404); exit('Not Found'); }
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "PHP " . PHP_VERSION . " / " . PHP_OS . "\n";
foreach (['mbstring', 'pdo_pgsql', 'pgsql', 'pdo_mysql', 'session', 'openssl'] as $ext) {
    echo str_pad($ext, 12) . ": " . (extension_loaded($ext) ? "yes" : "** MISSING **") . "\n";
}
echo "session.save_path: " . (ini_get('session.save_path') ?: '(default)') . "\n";
echo "DATABASE_URL set: " . (getenv('DATABASE_URL') ? 'yes' : 'no') . "\n";
echo "DB_PASS set: " . (getenv('DB_PASS') ? 'yes' : 'no') . "\n\n";

require_once __DIR__ . '/auth.php';

echo "initials('محمد أحمد'): " . initials('محمد أحمد') . "\n";

try {
    echo "db employees count: " . db()->query("SELECT COUNT(*) FROM employees")->fetchColumn() . "\n";
    $st = db()->prepare("SELECT u.*, e.full_name, e.status AS emp_status FROM users u LEFT JOIN employees e ON u.employee_id = e.id WHERE u.id = ?");
    $st->execute([1]);
    echo "current_user query: " . ($st->fetch() ? 'ok' : 'no row') . "\n";
    echo "settings: " . db()->query("SELECT company_name FROM settings WHERE id=1")->fetchColumn() . "\n";
    echo "departments: " . db()->query("SELECT COUNT(*) FROM departments")->fetchColumn() . "\n";
    echo "activity_log: " . db()->query("SELECT COUNT(*) FROM activity_log")->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}

echo "\nsession status: " . session_status() . " (2 = active)\n";
$_SESSION['diag_test'] = 'ok';
echo "session write: ok\n";
echo "\nDONE\n";
