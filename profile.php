<?php
require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
if ($user['role'] === 'admin') {
    header('Location: admin.php'); exit;
}

$emp_id = (int)$user['employee_id'];

// بيانات الموظف الكاملة
$stmt = db()->prepare("
    SELECT e.*, d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE e.id = ?
");
$stmt->execute([$emp_id]);
$emp = $stmt->fetch();

if (!$emp) { http_response_code(403); die('لم يتم العثور على بيانات الموظف'); }

$statuses = ['active' => 'نشط', 'inactive' => 'موقوف', 'terminated' => 'منتهي الخدمة'];
$status_badge_map = ['active' => 'approved', 'inactive' => 'pending', 'terminated' => 'rejected'];
$emp_status = $emp['status'] ?? 'active';

// ملخص الحضور — الشهر الحالي
$month_start = date('Y-m-01');
$today       = date('Y-m-d');

$att_stmt = db()->prepare("
    SELECT COUNT(*) AS days,
           SUM(CASE WHEN check_out IS NOT NULL
               THEN TIMESTAMPDIFF(MINUTE, check_in, check_out)
               ELSE 0 END) AS total_min
    FROM attendance
    WHERE employee_id = ? AND work_date BETWEEN ? AND ?
");
$att_stmt->execute([$emp_id, $month_start, $today]);
$att_month = $att_stmt->fetch();

// إجمالي الحضور
$t_stmt = db()->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id=?");
$t_stmt->execute([$emp_id]);
$total_days_all = (int)$t_stmt->fetchColumn();

// آخر ٧ تسجيلات
$hist = db()->prepare("SELECT * FROM attendance WHERE employee_id=? ORDER BY work_date DESC, id DESC LIMIT 7");
$hist->execute([$emp_id]);
$history = $hist->fetchAll();

// آخر ٥ طلبات
$req_stmt = db()->prepare("SELECT * FROM requests WHERE employee_id=? ORDER BY created_at DESC LIMIT 5");
$req_stmt->execute([$emp_id]);
$recent_reqs = $req_stmt->fetchAll();

// عدد الطلبات المعلقة لشارة النفيقيشن
$_st = db()->prepare("SELECT COUNT(*) FROM requests WHERE employee_id=? AND status='pending'");
$_st->execute([$emp_id]);
$_my_pending = (int)$_st->fetchColumn();

$days_this_month  = (int)$att_month['days'];
$hours_this_month = round(($att_month['total_min'] ?? 0) / 60, 1);

$user_initials = initials($emp['full_name'] ?: $user['username']);
$page_title = 'ملفي الشخصي';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head><?php include __DIR__ . '/_head.php'; ?></head>
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
                <span class="username-text"><?= e($emp['full_name'] ?: $user['username']) ?></span>
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
        <a href="requests.php">
            <i class="fa-solid fa-file-lines"></i> طلباتي
            <?php if ($_my_pending > 0): ?>
                <span class="nav-badge warn"><?= $_my_pending ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="active">
            <i class="fa-solid fa-id-badge"></i> ملفي
        </a>
    </nav>
</header>

<main class="container">

    <!-- رأس الملف الشخصي -->
    <div class="card">
        <div class="profile-header">
            <div class="avatar-lg <?= ($emp['gender'] ?? '') === 'female' ? 'female' : '' ?>">
                <?= e(initials($emp['full_name'])) ?>
            </div>
            <div class="info">
                <h1><?= e($emp['full_name']) ?></h1>
                <div class="role">
                    <i class="fa-solid fa-briefcase"></i>
                    <?= e($emp['profession'] ?: '—') ?>
                    <?php if (!empty($emp['job_number'])): ?>
                        · <span class="muted">رقم وظيفي:</span> <code><?= e($emp['job_number']) ?></code>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">
                    <span class="badge <?= $status_badge_map[$emp_status] ?? 'approved' ?> small">
                        <?= e($statuses[$emp_status] ?? 'نشط') ?>
                    </span>
                    <?php if (!empty($emp['department_name'])): ?>
                        <span class="badge neutral small">
                            <i class="fa-solid fa-building"></i> <?= e($emp['department_name']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($emp['hire_date'])): ?>
                        <span class="badge neutral small">
                            <i class="fa-solid fa-calendar-check"></i> منذ <?= e($emp['hire_date']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ملخص الشهر الحالي -->
    <div class="stats">
        <div class="stat-card">
            <div class="icon green"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="body">
                <div class="stat-label">أيام حضور هذا الشهر</div>
                <div class="stat-value"><?= $days_this_month ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon blue"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="body">
                <div class="stat-label">ساعات العمل هذا الشهر</div>
                <div class="stat-value"><?= $hours_this_month ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon orange"><i class="fa-solid fa-chart-line"></i></div>
            <div class="body">
                <div class="stat-label">إجمالي أيام الحضور</div>
                <div class="stat-value"><?= $total_days_all ?></div>
            </div>
        </div>
    </div>

    <!-- بيانات الموظف -->
    <div class="info-grid">
        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-id-card"></i> البيانات الشخصية</h3>
            <table class="info-table">
                <tr><th>الاسم</th><td><?= e($emp['full_name']) ?></td></tr>
                <tr><th>الجنس</th><td><?= ($emp['gender'] ?? '') === 'male' ? 'ذكر' : 'أنثى' ?></td></tr>
                <tr><th>الجنسية</th><td><?= e($emp['nationality'] ?? '—') ?></td></tr>
                <tr><th>الحالة الاجتماعية</th><td><?= e($emp['marital_status'] ?: '—') ?></td></tr>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-briefcase"></i> البيانات الوظيفية</h3>
            <table class="info-table">
                <tr><th>الرقم الوظيفي</th><td><?= e($emp['job_number'] ?: '—') ?></td></tr>
                <tr><th>المهنة</th><td><?= e($emp['profession'] ?: '—') ?></td></tr>
                <tr><th>القسم</th><td><?= e($emp['department_name'] ?: '—') ?></td></tr>
                <tr><th>تاريخ التعيين</th><td><?= e($emp['hire_date'] ?: '—') ?></td></tr>
                <tr><th>التأمين الطبي</th><td><?= !empty($emp['medical_insurance']) ? 'نعم' : 'لا' ?></td></tr>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-address-book"></i> بيانات التواصل</h3>
            <table class="info-table">
                <tr><th>رقم الجوال</th><td><?= e($emp['phone'] ?: '—') ?></td></tr>
                <tr><th>البريد الإلكتروني</th><td><?= e($emp['email'] ?: '—') ?></td></tr>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-graduation-cap"></i> المؤهلات</h3>
            <table class="info-table">
                <tr><th>المؤهل العلمي</th><td><?= e($emp['education'] ?: '—') ?></td></tr>
                <tr><th>التخصص</th><td><?= e($emp['specialization'] ?: '—') ?></td></tr>
            </table>
        </div>
    </div>

    <!-- آخر التسجيلات -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-clock"></i> آخر تسجيلات الحضور</h2>
            <a href="index.php" class="btn ghost small"><i class="fa-solid fa-clock"></i> تسجيل حضور</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>التاريخ</th><th>الحضور</th><th>الانصراف</th><th>المدة</th></tr>
                </thead>
                <tbody>
                <?php foreach ($history as $r): ?>
                    <tr>
                        <td><?= e($r['work_date']) ?></td>
                        <td><strong><?= e(date('H:i', strtotime($r['check_in']))) ?></strong></td>
                        <td><?= $r['check_out'] ? '<strong>' . e(date('H:i', strtotime($r['check_out']))) . '</strong>' : '<span class="muted">—</span>' ?></td>
                        <td>
                            <?php if ($r['check_out']): ?>
                                <?php
                                $sec = strtotime($r['check_out']) - strtotime($r['check_in']);
                                $h = floor($sec / 3600); $m = floor(($sec % 3600) / 60);
                                ?>
                                <span class="badge neutral small"><?= "{$h}س {$m}د" ?></span>
                            <?php else: ?>
                                <span class="badge pending small">مفتوح</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$history): ?>
                    <tr><td colspan="4" class="muted center" style="padding:24px">لا توجد تسجيلات بعد</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- آخر الطلبات -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-file-lines"></i> آخر الطلبات</h2>
            <a href="requests.php" class="btn ghost small"><i class="fa-solid fa-plus"></i> طلب جديد</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>التاريخ</th><th>العنوان</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent_reqs as $r): ?>
                    <tr>
                        <td><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                        <td><?= e($r['title']) ?></td>
                        <td>
                            <?php
                            $badges = [
                                'pending'  => '<span class="badge pending small"><i class="fa-solid fa-hourglass-half"></i> قيد المراجعة</span>',
                                'approved' => '<span class="badge approved small"><i class="fa-solid fa-check"></i> مقبول</span>',
                                'rejected' => '<span class="badge rejected small"><i class="fa-solid fa-xmark"></i> مرفوض</span>',
                            ];
                            echo $badges[$r['status']] ?? e($r['status']);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent_reqs): ?>
                    <tr><td colspan="3" class="muted center" style="padding:24px">لا توجد طلبات بعد</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</body>
</html>
