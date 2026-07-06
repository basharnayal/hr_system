<?php
require_once __DIR__ . '/auth.php';
require_admin();

$msg = ''; $msg_type = '';
$user = current_user();

// قراءة إعدادات أوقات الدوام
$work_start = '08:00'; $work_end = '17:00';
try {
    $ws = db()->query("SELECT work_start, work_end FROM settings WHERE id = 1")->fetch();
    if ($ws) {
        $work_start = substr($ws['work_start'], 0, 5);
        $work_end   = substr($ws['work_end'],   0, 5);
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $op = $_POST['op'] ?? '';
    try {
        if ($op === 'company') {
            $name = trim($_POST['company_name'] ?? '');
            if ($name === '') throw new Exception('اسم الشركة مطلوب');
            $stmt = db()->prepare("UPDATE settings SET company_name = ? WHERE id = 1");
            $stmt->execute([$name]);
            log_activity('update_settings', 'settings', 1, "تغيير اسم الشركة إلى: {$name}");
            $msg = 'تم تحديث اسم الشركة بنجاح';
            $msg_type = 'success';
        }
        elseif ($op === 'workhours') {
            $ws = trim($_POST['work_start'] ?? '');
            $we = trim($_POST['work_end']   ?? '');
            if (!preg_match('/^\d{2}:\d{2}$/', $ws) || !preg_match('/^\d{2}:\d{2}$/', $we)) {
                throw new Exception('صيغة الوقت غير صحيحة، استخدم HH:MM');
            }
            $stmt = db()->prepare("UPDATE settings SET work_start = ?, work_end = ? WHERE id = 1");
            $stmt->execute([$ws . ':00', $we . ':00']);
            $work_start = $ws; $work_end = $we;
            log_activity('update_settings', 'settings', 1, "تحديث أوقات الدوام: {$ws} - {$we}");
            $msg = 'تم حفظ أوقات الدوام بنجاح';
            $msg_type = 'success';
        }
        elseif ($op === 'password') {
            $old = $_POST['old'] ?? '';
            $new = $_POST['new'] ?? '';
            if (strlen($new) < 6) throw new Exception('كلمة المرور الجديدة يجب ٦ أحرف فأكثر');
            $stmt = db()->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($old, $row['password'])) {
                throw new Exception('كلمة المرور الحالية غير صحيحة');
            }
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user['id']]);
            $msg = 'تم تحديث كلمة المرور بنجاح';
            $msg_type = 'success';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msg_type = 'error';
    }
}
$page_title = 'الإعدادات';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_admin_nav.php'; ?>

<main class="container">
    <?php if ($msg): ?>
        <div class="alert <?= e($msg_type) ?>">
            <i class="fa-solid fa-<?= $msg_type === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
            <span><?= e($msg) ?></span>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-building"></i> اسم الشركة</h2>
        </div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="company">
            <label>الاسم الظاهر للموظفين
                <input type="text" name="company_name" value="<?= e(company_name()) ?>" required>
            </label>
            <button class="btn primary">
                <i class="fa-solid fa-save"></i> حفظ
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-clock"></i> أوقات الدوام</h2>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="workhours">
            <label>وقت بداية الدوام
                <input type="time" name="work_start" value="<?= e($work_start) ?>" required>
            </label>
            <label>وقت نهاية الدوام
                <input type="time" name="work_end" value="<?= e($work_end) ?>" required>
            </label>
            <div class="form-actions">
                <button class="btn primary">
                    <i class="fa-solid fa-save"></i> حفظ
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-key"></i> تغيير كلمة المرور</h2>
        </div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="password">
            <label>كلمة المرور الحالية
                <input type="password" name="old" required>
            </label>
            <label>كلمة المرور الجديدة (٦ أحرف فأكثر)
                <input type="password" name="new" required minlength="6">
            </label>
            <button class="btn primary">
                <i class="fa-solid fa-rotate"></i> تحديث
            </button>
        </form>
    </div>
</main>
</body>
</html>
