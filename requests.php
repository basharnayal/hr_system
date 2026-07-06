<?php
require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
if ($user['role'] === 'admin') {
    header('Location: admin_requests.php');
    exit;
}

$emp_id = (int)$user['employee_id'];
$msg = ''; $msg_type = '';

$types = [
    'leave'        => 'إجازة',
    'permission'   => 'إذن خروج',
    'salary_cert'  => 'شهادة راتب',
    'intro_letter' => 'خطاب تعريف',
    'bonus'        => 'مكافأة / بدل',
    'other'        => 'أخرى',
];

$type_icons = [
    'leave'        => 'fa-umbrella-beach',
    'permission'   => 'fa-door-open',
    'salary_cert'  => 'fa-file-invoice-dollar',
    'intro_letter' => 'fa-envelope-open-text',
    'bonus'        => 'fa-gift',
    'other'        => 'fa-file',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $op = $_POST['op'] ?? 'submit';
    try {
        if ($op === 'cancel') {
            $req_id = (int)($_POST['req_id'] ?? 0);
            $stmt = db()->prepare("SELECT id FROM requests WHERE id=? AND employee_id=? AND status='pending'");
            $stmt->execute([$req_id, $emp_id]);
            if (!$stmt->fetch()) throw new Exception('لا يمكن إلغاء هذا الطلب');
            db()->prepare("DELETE FROM requests WHERE id=? AND employee_id=? AND status='pending'")
                ->execute([$req_id, $emp_id]);
            $msg = 'تم إلغاء الطلب بنجاح'; $msg_type = 'success';
        } else {
            $type    = $_POST['type'] ?? '';
            $title   = trim($_POST['title'] ?? '');
            $details = trim($_POST['details'] ?? '');
            $start   = $_POST['start_date'] ?: null;
            $end     = $_POST['end_date']   ?: null;

            if (!isset($types[$type]))           throw new Exception('نوع الطلب غير صحيح');
            if ($title === '')                   throw new Exception('عنوان الطلب مطلوب');
            if ($start && $end && $end < $start) throw new Exception('تاريخ النهاية قبل البداية');

            $stmt = db()->prepare("
                INSERT INTO requests (employee_id, type, title, details, start_date, end_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$emp_id, $type, $title, $details, $start, $end]);
            $msg = 'تم إرسال طلبك بنجاح. سيتم مراجعته من الإدارة'; $msg_type = 'success';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage(); $msg_type = 'error';
    }
}

$stmt = db()->prepare("
    SELECT r.*, u.username AS reviewer
    FROM requests r
    LEFT JOIN users u ON u.id = r.reviewed_by
    WHERE r.employee_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$emp_id]);
$rows = $stmt->fetchAll();

// عدد الطلبات المعلقة لشارة النفيقيشن
$_my_pending = 0;
try {
    $_st = db()->prepare("SELECT COUNT(*) FROM requests WHERE employee_id = ? AND status = 'pending'");
    $_st->execute([$emp_id]);
    $_my_pending = (int)$_st->fetchColumn();
} catch (Exception $e) {}

function status_badge_emp($s) {
    return match($s) {
        'pending'  => '<span class="badge pending"><i class="fa-solid fa-hourglass-half"></i> قيد المراجعة</span>',
        'approved' => '<span class="badge approved"><i class="fa-solid fa-check"></i> مقبول</span>',
        'rejected' => '<span class="badge rejected"><i class="fa-solid fa-xmark"></i> مرفوض</span>',
        default    => $s,
    };
}

$user_initials = initials($user['full_name'] ?: $user['username']);
$page_title = 'طلباتي';
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
        <a href="index.php">
            <i class="fa-solid fa-clock"></i> الحضور
        </a>
        <a href="requests.php" class="active">
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
    <?php if ($msg): ?>
        <div class="alert <?= e($msg_type) ?>">
            <i class="fa-solid fa-<?= $msg_type === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
            <span><?= e($msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- نموذج طلب جديد -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-paper-plane"></i> تقديم طلب جديد</h2>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>نوع الطلب
                <select name="type" required>
                    <?php foreach ($types as $k => $v): ?>
                        <option value="<?= e($k) ?>"><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>العنوان
                <input type="text" name="title" required maxlength="200" placeholder="عنوان مختصر للطلب">
            </label>
            <label>من تاريخ
                <input type="date" name="start_date">
            </label>
            <label>إلى تاريخ
                <input type="date" name="end_date">
            </label>
            <label style="grid-column:1/-1">التفاصيل
                <textarea name="details" rows="4" placeholder="اشرح طلبك بالتفصيل..."></textarea>
            </label>
            <div class="form-actions">
                <button class="btn primary">
                    <i class="fa-solid fa-paper-plane"></i> إرسال الطلب
                </button>
            </div>
        </form>
    </div>

    <!-- طلباتي السابقة -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> طلباتي السابقة (<?= count($rows) ?>)</h2>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>تاريخ التقديم</th>
                        <th>النوع</th>
                        <th>العنوان</th>
                        <th>الفترة</th>
                        <th>الحالة</th>
                        <th>ملاحظة الإدارة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                        <td>
                            <i class="fa-solid <?= e($type_icons[$r['type']] ?? 'fa-file') ?>" style="color:var(--accent);margin-left:5px"></i>
                            <?= e($types[$r['type']] ?? $r['type']) ?>
                        </td>
                        <td><strong><?= e($r['title']) ?></strong></td>
                        <td>
                            <?php if ($r['start_date'] || $r['end_date']): ?>
                                <span style="font-size:13px">
                                    <?= e($r['start_date']) ?> → <?= e($r['end_date']) ?>
                                </span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge_emp($r['status']) ?></td>
                        <td>
                            <?= $r['admin_note']
                                ? '<small>' . e($r['admin_note']) . '</small>'
                                : '<span class="muted">—</span>' ?>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <button type="button" class="btn danger small"
                                    onclick="confirmCancel(<?= (int)$r['id'] ?>, '<?= e(addslashes($r['title'])) ?>')">
                                    <i class="fa-solid fa-xmark"></i> إلغاء
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="muted center" style="padding:32px">
                            <i class="fa-solid fa-inbox" style="font-size:28px;opacity:.3"></i>
                            <div style="margin-top:8px">لا توجد طلبات بعد. قدّم طلبك من الأعلى.</div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal: تأكيد إلغاء الطلب -->
<div class="modal-backdrop" id="cancelModal" onclick="if(event.target===this) closeCancelModal()">
    <div class="modal">
        <div class="modal-icon" style="background:#FEF2F2;color:var(--danger)">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3>إلغاء الطلب</h3>
        <p>هل تريد إلغاء طلب: <strong id="cancelTitle"></strong>؟</p>
        <form method="post" class="actions">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="cancel">
            <input type="hidden" name="req_id" id="cancelReqId">
            <button type="button" class="btn ghost" onclick="closeCancelModal()">لا، رجوع</button>
            <button type="submit" class="btn danger"><i class="fa-solid fa-xmark"></i> نعم، إلغاء</button>
        </form>
    </div>
</div>

<script>
function confirmCancel(id, title) {
    document.getElementById('cancelReqId').value    = id;
    document.getElementById('cancelTitle').textContent = title;
    document.getElementById('cancelModal').classList.add('active');
}
function closeCancelModal() { document.getElementById('cancelModal').classList.remove('active'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCancelModal(); });
</script>
</body>
</html>
