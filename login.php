<?php
require_once __DIR__ . '/auth.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = '';
// رسالة إيقاف الحساب (من require_login)
if (isset($_GET['msg']) && $_GET['msg'] === 'blocked') {
    $error = 'تم إيقاف حسابك أو انتهت خدمتك. يرجى التواصل مع الإدارة.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $login    = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        // قبول اسم المستخدم أو البريد الإلكتروني
        $stmt = db()->prepare("
            SELECT u.*
            FROM users u
            LEFT JOIN employees e ON e.id = u.employee_id
            WHERE u.username = ? OR e.email = ?
            LIMIT 1
        ");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // منع الموظفين الموقوفين أو المنتهية خدمتهم من الدخول
            if ($user['role'] !== 'admin' && $user['employee_id']) {
                try {
                    $s = db()->prepare("SELECT status FROM employees WHERE id=?");
                    $s->execute([$user['employee_id']]);
                    $row = $s->fetch();
                    if ($row && $row['status'] !== 'active') {
                        $error = 'تم إيقاف حسابك. يرجى التواصل مع الإدارة.';
                        goto end_login;
                    }
                } catch (Exception $e) {}
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
        end_login:
    }
}
$page_title = 'تسجيل الدخول';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body class="auth-bg">
<div class="login-card">
    <div class="logo">
        <i class="fa-solid fa-building-user"></i>
    </div>
    <div class="brand"><?= e(company_name()) ?></div>
    <h1>نظام إدارة الموارد البشرية</h1>

    <?php if ($error): ?>
        <div class="alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>اسم المستخدم أو البريد الإلكتروني
            <div class="input-with-icon">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" required autofocus
                       value="<?= e($_POST['username'] ?? '') ?>"
                       placeholder="مثال: almatrafi.i">
            </div>
        </label>

        <label>كلمة المرور
            <div class="input-with-icon">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" required
                       placeholder="••••••••">
            </div>
        </label>

        <button type="submit" class="btn primary block big">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            دخول
        </button>
    </form>
</div>
</body>
</html>
