<?php
require_once __DIR__ . '/auth.php';
require_admin();

$user = current_user();
$today = date('Y-m-d');

// إحصائيات
$total_emp = (int)db()->query("SELECT COUNT(*) FROM employees")->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE work_date = ?");
$stmt->execute([$today]);
$present_today = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM attendance WHERE work_date = ? AND check_out IS NULL");
$stmt->execute([$today]);
$still_in = (int)$stmt->fetchColumn();

$absent_today = max(0, $total_emp - $present_today);

$pending_requests = 0;
try {
    $pending_requests = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {}

// آخر الطلبات المعلقة
$recent_requests = [];
try {
    $stmt = db()->query("
        SELECT r.id, r.type, r.title, r.created_at, e.id AS emp_id, e.full_name, e.gender
        FROM requests r
        JOIN employees e ON e.id = r.employee_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $recent_requests = $stmt->fetchAll();
} catch (Exception $e) {}

// حضور اليوم
$stmt = db()->prepare("
    SELECT e.id, e.full_name, e.national_id, e.gender, a.check_in, a.check_out
    FROM employees e
    LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ?
    ORDER BY (a.check_in IS NULL), a.check_in DESC
");
$stmt->execute([$today]);
$rows = $stmt->fetchAll();

$page_title = 'لوحة المدير';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_admin_nav.php'; ?>

<main class="container">
    <!-- بطاقات الإحصائيات — كل بطاقة رابط لصفحتها -->
    <div class="stats">
        <a class="stat-card" href="admin_employees.php">
            <div class="icon blue"><i class="fa-solid fa-users"></i></div>
            <div class="body">
                <div class="stat-label">إجمالي الموظفين</div>
                <div class="stat-value"><?= $total_emp ?></div>
            </div>
        </a>
        <a class="stat-card" href="admin_attendance.php">
            <div class="icon green"><i class="fa-solid fa-user-check"></i></div>
            <div class="body">
                <div class="stat-label">حضروا اليوم</div>
                <div class="stat-value"><?= $present_today ?></div>
                <div class="stat-sub">من <?= $total_emp ?> موظف</div>
            </div>
        </a>
        <a class="stat-card" href="admin_attendance.php">
            <div class="icon purple"><i class="fa-solid fa-business-time"></i></div>
            <div class="body">
                <div class="stat-label">حالياً في الدوام</div>
                <div class="stat-value"><?= $still_in ?></div>
            </div>
        </a>
        <a class="stat-card" href="admin_attendance.php">
            <div class="icon red"><i class="fa-solid fa-user-xmark"></i></div>
            <div class="body">
                <div class="stat-label">غائبون اليوم</div>
                <div class="stat-value"><?= $absent_today ?></div>
                <div class="stat-sub">من <?= $total_emp ?> موظف</div>
            </div>
        </a>
        <a class="stat-card" href="admin_requests.php?status=pending">
            <div class="icon orange"><i class="fa-solid fa-file-circle-exclamation"></i></div>
            <div class="body">
                <div class="stat-label">طلبات قيد المراجعة</div>
                <div class="stat-value"><?= $pending_requests ?></div>
            </div>
        </a>
    </div>

    <?php
    $req_types = [
        'leave' => 'إجازة', 'permission' => 'إذن', 'salary_cert' => 'شهادة راتب',
        'intro_letter' => 'خطاب تعريف', 'bonus' => 'مكافأة', 'other' => 'أخرى',
    ];
    ?>

    <!-- قسمان: اليمين للطلبات الوظيفية — اليسار للحضور والانصراف -->
    <div class="dash-split">

        <!-- اليمين: الطلبات الوظيفية -->
        <div class="card flush">
            <div class="card-header">
                <h2><i class="fa-solid fa-inbox"></i> طلبات بانتظار المراجعة<?php if ($pending_requests > 0): ?> (<?= (int)$pending_requests ?>)<?php endif; ?></h2>
                <a href="admin_requests.php?status=pending" class="btn ghost small">
                    <i class="fa-solid fa-list"></i> عرض الكل
                </a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>الموظف</th>
                            <th>النوع</th>
                            <th>العنوان</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($recent_requests): foreach ($recent_requests as $rr): ?>
                        <tr>
                            <td>
                                <a href="admin_employee_view.php?id=<?= (int)$rr['emp_id'] ?>" class="emp-link">
                                    <div class="avatar-cell">
                                        <span class="avatar <?= $rr['gender'] === 'female' ? 'female' : '' ?>"><?= e(initials($rr['full_name'])) ?></span>
                                        <div class="name"><?= e($rr['full_name']) ?></div>
                                    </div>
                                </a>
                            </td>
                            <td><?= e($req_types[$rr['type']] ?? $rr['type']) ?></td>
                            <td><?= e($rr['title']) ?></td>
                            <td>
                                <a href="admin_requests.php?status=pending" class="btn primary small">
                                    <i class="fa-solid fa-check"></i> مراجعة
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="4" class="muted center" style="padding:36px 20px">
                                <i class="fa-solid fa-inbox" style="font-size:26px;opacity:.3"></i>
                                <div style="margin-top:8px">لا توجد طلبات بانتظار المراجعة</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- اليسار: الحضور والانصراف -->
        <div class="card flush">
            <div class="card-header">
                <h2><i class="fa-solid fa-calendar-day"></i> حضور اليوم</h2>
                <a href="admin_attendance.php" class="btn ghost small">
                    <i class="fa-solid fa-list"></i> السجل الكامل
                </a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>الموظف</th>
                            <th>الحضور</th>
                            <th>الانصراف</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <a href="admin_employee_view.php?id=<?= (int)$r['id'] ?>" class="emp-link">
                                    <div class="avatar-cell">
                                        <span class="avatar <?= $r['gender'] === 'female' ? 'female' : '' ?>"><?= e(initials($r['full_name'])) ?></span>
                                        <div class="name"><?= e($r['full_name']) ?></div>
                                    </div>
                                </a>
                            </td>
                            <td><?= $r['check_in'] ? '<strong>' . e(date('H:i', strtotime($r['check_in']))) . '</strong>' : '<span class="muted">—</span>' ?></td>
                            <td><?= $r['check_out'] ? '<strong>' . e(date('H:i', strtotime($r['check_out']))) . '</strong>' : '<span class="muted">—</span>' ?></td>
                            <td>
                                <?php if ($r['check_in'] && !$r['check_out']): ?>
                                    <span class="badge in"><i class="fa-solid fa-circle"></i> في الدوام</span>
                                <?php elseif ($r['check_out']): ?>
                                    <span class="badge out"><i class="fa-solid fa-check"></i> انصرف</span>
                                <?php else: ?>
                                    <span class="badge neutral"><i class="fa-solid fa-minus"></i> غائب</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="4" class="muted center" style="padding:36px 20px">
                                <i class="fa-solid fa-users-slash" style="font-size:28px;opacity:.3"></i>
                                <div style="margin-top:8px">لا يوجد موظفون بعد. <a href="admin_employees.php">أضف موظفاً جديداً</a></div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
</body>
</html>
