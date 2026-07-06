<?php
require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();

// إذا كان مديراً يحوّل للوحة التحكم
if ($user['role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$emp_id = (int)$user['employee_id'];
$today  = date('Y-m-d');
$msg    = '';
$msg_type = '';

function today_record($emp_id, $today) {
    $stmt = db()->prepare("SELECT * FROM attendance WHERE employee_id = ? AND work_date = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$emp_id, $today]);
    return $stmt->fetch();
}

$record = today_record($emp_id, $today);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $now = date('Y-m-d H:i:s');

    if ($action === 'check_in') {
        if ($record && $record['check_out'] === null) {
            $msg = 'أنت مسجل حضور بالفعل ولم تسجل انصراف بعد';
            $msg_type = 'error';
        } else {
            $stmt = db()->prepare("INSERT INTO attendance (employee_id, check_in, work_date) VALUES (?, ?, ?)");
            $stmt->execute([$emp_id, $now, $today]);
            $msg = 'تم تسجيل الحضور بنجاح';
            $msg_type = 'success';
        }
    } elseif ($action === 'check_out') {
        if (!$record || $record['check_out'] !== null) {
            $msg = 'لا يوجد حضور مفتوح لتسجيل انصراف عليه';
            $msg_type = 'error';
        } else {
            $stmt = db()->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
            $stmt->execute([$now, $record['id']]);
            $msg = 'تم تسجيل الانصراف بنجاح';
            $msg_type = 'success';
        }
    }
    $record = today_record($emp_id, $today);
}

// آخر ٧ تسجيلات
$stmt = db()->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY work_date DESC, id DESC LIMIT 7");
$stmt->execute([$emp_id]);
$history = $stmt->fetchAll();

$can_check_in  = !$record || $record['check_out'] !== null;
$can_check_out = $record && $record['check_out'] === null;

// عدد الطلبات المعلقة لشارة النفيقيشن
$_my_pending = 0;
try {
    $_st = db()->prepare("SELECT COUNT(*) FROM requests WHERE employee_id = ? AND status = 'pending'");
    $_st->execute([$emp_id]);
    $_my_pending = (int)$_st->fetchColumn();
} catch (Exception $e) {}

$user_initials = initials($user['full_name'] ?: $user['username']);
$page_title = 'الحضور والانصراف';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
<header class="topbar">
    <div class="topbar-main">
        <div class="brand">
            <i class="fa-solid fa-briefcase"></i>
            <span><?= e(company_name()) ?></span>
        </div>
        <button class="nav-toggle" type="button" onclick="document.getElementById('mainNav').classList.toggle('open')" aria-label="القائمة">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="user-area">
            <span class="user-name">
                <span class="avatar"><?= e($user_initials) ?></span>
                <span class="username-text"><?= e($user['full_name'] ?: $user['username']) ?></span>
            </span>
            <a href="logout.php" class="btn ghost small">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="logout-text">خروج</span>
            </a>
        </div>
    </div>
    <nav class="nav" id="mainNav">
        <a href="index.php" class="active">
            <i class="fa-solid fa-clock"></i> الحضور
        </a>
        <a href="requests.php">
            <i class="fa-solid fa-file-lines"></i> طلباتي
            <?php if ($_my_pending > 0): ?>
                <span class="nav-badge warn"><?= $_my_pending ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php">
            <i class="fa-solid fa-id-badge"></i> ملفي
        </a>
    </nav>
</header>

<main class="container">
    <!-- بطاقة الحضور الرئيسية -->
    <div class="card big-center">
        <div class="day-label">
            <i class="fa-solid fa-calendar-day"></i>
            <?= e(date('l, d F Y')) ?>
        </div>
        <div class="big-clock" id="clock"><?= date('H:i:s') ?></div>

        <?php if ($msg): ?>
            <div class="alert <?= e($msg_type) ?>" style="margin-left:auto;margin-right:auto;max-width:480px">
                <i class="fa-solid fa-<?= $msg_type === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                <span><?= e($msg) ?></span>
            </div>
        <?php endif; ?>

        <div class="status-box">
            <?php if ($record && $record['check_out'] === null): ?>
                <div class="badge in">
                    <i class="fa-solid fa-play"></i>
                    حالة اليوم: حاضر منذ <?= e(date('H:i', strtotime($record['check_in']))) ?>
                </div>
            <?php elseif ($record && $record['check_out']): ?>
                <div class="badge out">
                    <i class="fa-solid fa-check"></i>
                    تم الانصراف اليوم في <?= e(date('H:i', strtotime($record['check_out']))) ?>
                </div>
            <?php else: ?>
                <div class="badge neutral">
                    <i class="fa-solid fa-circle-info"></i>
                    لم تسجل حضور اليوم بعد
                </div>
            <?php endif; ?>
        </div>

        <form method="post" class="actions">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" name="action" value="check_in"
                    class="btn primary huge" <?= $can_check_in ? '' : 'disabled' ?>>
                <i class="fa-solid fa-right-to-bracket"></i>
                تسجيل حضور
            </button>
            <button type="submit" name="action" value="check_out"
                    class="btn danger huge" <?= $can_check_out ? '' : 'disabled' ?>>
                <i class="fa-solid fa-right-from-bracket"></i>
                تسجيل انصراف
            </button>
        </form>
    </div>

    <!-- آخر التسجيلات -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-list-ul"></i> آخر التسجيلات</h2>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>التاريخ</th><th>الحضور</th><th>الانصراف</th><th>المدة</th></tr>
                </thead>
                <tbody>
                <?php if (!$history): ?>
                    <tr>
                        <td colspan="4" class="muted center" style="padding:32px">
                            <i class="fa-solid fa-clock" style="font-size:28px;opacity:.3"></i>
                            <div style="margin-top:8px">لا توجد تسجيلات بعد</div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($history as $r): ?>
                    <tr>
                        <td><?= e($r['work_date']) ?></td>
                        <td><strong><?= e(date('H:i', strtotime($r['check_in']))) ?></strong></td>
                        <td><?= $r['check_out'] ? '<strong>' . e(date('H:i', strtotime($r['check_out']))) . '</strong>' : '<span class="muted">—</span>' ?></td>
                        <td>
                            <?php
                            if ($r['check_out']) {
                                $sec = strtotime($r['check_out']) - strtotime($r['check_in']);
                                $h = floor($sec / 3600);
                                $m = floor(($sec % 3600) / 60);
                                echo "<span class=\"badge neutral small\">{$h}س {$m}د</span>";
                            } else {
                                echo '<span class="muted">—</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function tick() {
    const d = new Date();
    const pad = n => String(n).padStart(2,'0');
    document.getElementById('clock').textContent =
        pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
}
setInterval(tick, 1000);
</script>
</body>
</html>
