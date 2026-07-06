<?php
require_once __DIR__ . '/auth.php';
require_admin();

$user = current_user();
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
    try {
        $id     = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $note   = trim($_POST['admin_note'] ?? '');

        if (!in_array($action, ['approve', 'reject', 'reset'], true)) {
            throw new Exception('إجراء غير صحيح');
        }
        $newStatus = ['approve'=>'approved','reject'=>'rejected','reset'=>'pending'][$action];

        $stmt = db()->prepare("
            UPDATE requests
            SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $note, $user['id'], $id]);

        $action_map = ['approved' => 'approve_request', 'rejected' => 'reject_request', 'pending' => 'reset_request'];
        log_activity($action_map[$newStatus] ?? 'update_request', 'request', $id, "تغيير حالة الطلب #{$id} إلى: {$newStatus}");

        $msg = 'تم تحديث حالة الطلب بنجاح';
        $msg_type = 'success';
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msg_type = 'error';
    }
}

// فلاتر
$f_status = $_GET['status'] ?? '';
$f_emp    = (int)($_GET['emp'] ?? 0);
$f_type   = $_GET['type'] ?? '';

$where = []; $params = [];
if ($f_status && in_array($f_status, ['pending','approved','rejected'], true)) {
    $where[] = "r.status = ?"; $params[] = $f_status;
}
if ($f_emp)  { $where[] = "r.employee_id = ?"; $params[] = $f_emp; }
if ($f_type) { $where[] = "r.type = ?"; $params[] = $f_type; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("
    SELECT r.*, e.full_name, e.national_id, e.gender, u.username AS reviewer
    FROM requests r
    JOIN employees e ON e.id = r.employee_id
    LEFT JOIN users u ON u.id = r.reviewed_by
    $whereSql
    ORDER BY (r.status = 'pending') DESC, r.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = db()->query("
    SELECT status, COUNT(*) c FROM requests GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$employees = db()->query("SELECT id, full_name FROM employees ORDER BY full_name")->fetchAll();

function status_badge_admin($s) {
    return match($s) {
        'pending'  => '<span class="badge pending"><i class="fa-solid fa-hourglass-half"></i> قيد المراجعة</span>',
        'approved' => '<span class="badge approved"><i class="fa-solid fa-check"></i> مقبول</span>',
        'rejected' => '<span class="badge rejected"><i class="fa-solid fa-xmark"></i> مرفوض</span>',
        default    => $s,
    };
}
$page_title = 'الطلبات';
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

    <div class="stats">
        <div class="stat-card">
            <div class="icon orange"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="body">
                <div class="stat-label">قيد المراجعة</div>
                <div class="stat-value"><?= (int)($counts['pending'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="body">
                <div class="stat-label">مقبول</div>
                <div class="stat-value"><?= (int)($counts['approved'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon red"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="body">
                <div class="stat-label">مرفوض</div>
                <div class="stat-value"><?= (int)($counts['rejected'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-file-lines"></i> إدارة الطلبات</h2>
        </div>

        <form method="get" class="filters">
            <label>الحالة
                <select name="status">
                    <option value="">— الكل —</option>
                    <option value="pending"  <?= $f_status==='pending'  ?'selected':'' ?>>قيد المراجعة</option>
                    <option value="approved" <?= $f_status==='approved' ?'selected':'' ?>>مقبول</option>
                    <option value="rejected" <?= $f_status==='rejected' ?'selected':'' ?>>مرفوض</option>
                </select>
            </label>
            <label>الموظف
                <select name="emp">
                    <option value="0">— الكل —</option>
                    <?php foreach ($employees as $em): ?>
                        <option value="<?= $em['id'] ?>" <?= $f_emp==$em['id']?'selected':'' ?>>
                            <?= e($em['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>النوع
                <select name="type">
                    <option value="">— الكل —</option>
                    <?php foreach ($types as $k=>$v): ?>
                        <option value="<?= e($k) ?>" <?= $f_type===$k?'selected':'' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn primary">
                <i class="fa-solid fa-filter"></i> عرض
            </button>
            <a class="btn ghost" href="admin_requests.php">
                <i class="fa-solid fa-rotate-right"></i> إعادة تعيين
            </a>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الموظف</th>
                        <th>النوع</th>
                        <th>العنوان / التفاصيل</th>
                        <th>الفترة</th>
                        <th>الحالة</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="req-row">
                        <td><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                        <td>
                            <a href="admin_employee_view.php?id=<?= (int)$r['employee_id'] ?>" class="emp-link">
                                <div class="avatar-cell">
                                    <span class="avatar <?= ($r['gender'] ?? '') === 'female' ? 'female' : '' ?>"><?= e(initials($r['full_name'])) ?></span>
                                    <div>
                                        <div class="name"><?= e($r['full_name']) ?></div>
                                        <div class="sub"><?= e($r['national_id']) ?></div>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td>
                            <i class="fa-solid <?= e($type_icons[$r['type']] ?? 'fa-file') ?>" style="color:var(--accent);margin-left:5px"></i>
                            <?= e($types[$r['type']] ?? $r['type']) ?>
                        </td>
                        <td>
                            <strong><?= e($r['title']) ?></strong>
                            <?php if ($r['details']): ?>
                                <details>
                                    <summary>عرض التفاصيل</summary>
                                    <div><?= nl2br(e($r['details'])) ?></div>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['start_date'] || $r['end_date']): ?>
                                <span style="font-size:13px">
                                    <i class="fa-solid fa-calendar-plus" style="color:var(--success)"></i> <?= e($r['start_date']) ?>
                                    <br>
                                    <i class="fa-solid fa-calendar-minus" style="color:var(--danger)"></i> <?= e($r['end_date']) ?>
                                </span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= status_badge_admin($r['status']) ?>
                            <?php if ($r['reviewer']): ?>
                                <br><small class="muted"><i class="fa-solid fa-user-check"></i> <?= e($r['reviewer']) ?></small>
                            <?php endif; ?>
                            <?php if ($r['admin_note']): ?>
                                <br><small class="muted"><i class="fa-solid fa-comment"></i> <?= e($r['admin_note']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" class="req-actions">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="text" name="admin_note" placeholder="ملاحظة (اختياري)"
                                       value="<?= e($r['admin_note']) ?>">
                                <?php if ($r['status'] !== 'approved'): ?>
                                    <button name="action" value="approve" class="btn success small" title="موافقة">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($r['status'] !== 'rejected'): ?>
                                    <button name="action" value="reject" class="btn danger small" title="رفض">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($r['status'] !== 'pending'): ?>
                                    <button name="action" value="reset" class="btn ghost small" title="إعادة للمراجعة">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="7" class="muted center" style="padding:32px">
                            <i class="fa-solid fa-inbox" style="font-size:28px;opacity:.3"></i>
                            <div style="margin-top:8px">لا توجد طلبات</div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
