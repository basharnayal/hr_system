<?php
require_once __DIR__ . '/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("
    SELECT e.*, u.username, d.name AS department_name
    FROM employees e
    LEFT JOIN users u ON u.employee_id = e.id
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { http_response_code(404); die('الموظف غير موجود'); }

$statuses    = ['active' => 'نشط', 'inactive' => 'موقوف', 'terminated' => 'منتهي الخدمة'];
$status_badge_map = ['active' => 'approved', 'inactive' => 'pending', 'terminated' => 'rejected'];
$emp_status  = $emp['status'] ?? 'active';
$sbadge      = $status_badge_map[$emp_status] ?? 'approved';
$slabel      = $statuses[$emp_status] ?? 'نشط';

// بيانات التأمين الصحي
$ins = null;
try {
    $stmt = db()->prepare("SELECT * FROM insurance WHERE employee_id = ?");
    $stmt->execute([$id]);
    $ins = $stmt->fetch() ?: null;
} catch (Exception $e) {}

// إحصائيات الحضور
$stmt = db()->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = ?");
$stmt->execute([$id]);
$total_days = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT MIN(work_date) AS first_day, MAX(work_date) AS last_day FROM attendance WHERE employee_id = ?");
$stmt->execute([$id]);
$att_range = $stmt->fetch();

function row($label, $value) {
    $value = trim((string)$value);
    if ($value === '') $value = '—';
    return '<tr><th>' . e($label) . '</th><td>' . e($value) . '</td></tr>';
}

$page_title = $emp['full_name'];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_admin_nav.php'; ?>

<main class="container">
    <!-- رأس صفحة الملف الشخصي -->
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
                    <span class="badge <?= $sbadge ?> small">
                        <i class="fa-solid fa-circle<?= $emp_status === 'active' ? '-check' : ($emp_status === 'terminated' ? '-xmark' : '-pause') ?>"></i>
                        <?= e($slabel) ?>
                    </span>
                    <?php if (!empty($emp['department_name'])): ?>
                        <span class="badge neutral small">
                            <i class="fa-solid fa-building"></i> <?= e($emp['department_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row-actions">
                <a href="admin_employees.php" class="btn ghost">
                    <i class="fa-solid fa-arrow-right"></i> القائمة
                </a>
                <a href="admin_employees.php?edit=<?= $emp['id'] ?>" class="btn primary">
                    <i class="fa-solid fa-pen"></i> تعديل
                </a>
                <a href="admin_attendance.php?emp=<?= $emp['id'] ?>" class="btn primary">
                    <i class="fa-solid fa-clock"></i> سجل الحضور
                </a>
            </div>
        </div>
    </div>

    <div class="info-grid">
        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-id-card"></i> البيانات الشخصية</h3>
            <table class="info-table">
                <?= row('الاسم', $emp['full_name']) ?>
                <?= row('الجنس', ($emp['gender'] ?? '') === 'male' ? 'ذكر' : 'أنثى') ?>
                <?= row('الجنسية', $emp['nationality'] ?? '') ?>
                <?= row('الديانة', $emp['religion'] ?? '') ?>
                <?= row('الحالة الاجتماعية', $emp['marital_status'] ?? '') ?>
                <?= row('تاريخ الميلاد', $emp['birth_date'] ?? '') ?>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-passport"></i> بيانات الهوية</h3>
            <table class="info-table">
                <?= row('رقم الهوية', $emp['national_id']) ?>
                <?= row('نوع الهوية', $emp['id_type'] ?? '') ?>
                <?= row('تاريخ الانتهاء', $emp['id_expiry'] ?? '') ?>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-briefcase"></i> البيانات الوظيفية</h3>
            <table class="info-table">
                <?= row('الرقم الوظيفي', $emp['job_number'] ?? '') ?>
                <?= row('المهنة', $emp['profession'] ?? '') ?>
                <?= row('القسم', $emp['department_name'] ?? '') ?>
                <?= row('تاريخ التعيين', $emp['hire_date'] ?? '') ?>
                <?= row('التأمين الطبي', !empty($emp['medical_insurance']) ? 'نعم' : 'لا') ?>
                <tr>
                    <th>الحالة الوظيفية</th>
                    <td><span class="badge <?= $sbadge ?> small"><?= e($slabel) ?></span></td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-graduation-cap"></i> المؤهلات</h3>
            <table class="info-table">
                <?= row('المؤهل العلمي', $emp['education'] ?? '') ?>
                <?= row('التخصص', $emp['specialization'] ?? '') ?>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-address-book"></i> بيانات الاتصال</h3>
            <table class="info-table">
                <?= row('رقم الجوال', $emp['phone']) ?>
                <?= row('البريد الإلكتروني', $emp['email']) ?>
                <?= row('اسم المستخدم', $emp['username']) ?>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title"><i class="fa-solid fa-landmark"></i> البيانات البنكية</h3>
            <table class="info-table">
                <?= row('اسم البنك', $emp['bank_name'] ?? '') ?>
                <?= row('رقم الآيبان', $emp['iban'] ?? '') ?>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 class="section-title"><i class="fa-solid fa-shield-heart"></i> التأمين الصحي</h3>
        <?php if ($ins): ?>
            <?php
            $today = date('Y-m-d');
            $is_expired = $ins['expiry_date'] && $ins['expiry_date'] < $today;
            $days_left = $ins['expiry_date'] ? (strtotime($ins['expiry_date']) - strtotime($today)) / 86400 : null;
            ?>
            <table class="info-table">
                <?= row('شركة التأمين', $ins['insurance_company']) ?>
                <?= row('رقم البوليصة', $ins['policy_number']) ?>
                <?= row('رقم المشترك', $ins['subscriber_number']) ?>
                <?= row('نوع المشترك', $ins['subscriber_type']) ?>
                <?= row('الشبكة الطبية', $ins['medical_network']) ?>
                <?= row('الفئة', $ins['class']) ?>
                <?= row('نسبة التحمل', $ins['deductible_percent'] . '%') ?>
                <?= row('حدود التغطية', $ins['coverage_limit']) ?>
                <?= row('تاريخ الرفع', $ins['upload_date']) ?>
                <tr>
                    <th>تاريخ الانتهاء</th>
                    <td>
                        <?= e($ins['expiry_date']) ?>
                        <?php if ($is_expired): ?>
                            <span class="badge rejected small" style="margin-right:8px">
                                <i class="fa-solid fa-circle-xmark"></i> منتهي
                            </span>
                        <?php elseif ($days_left !== null && $days_left < 60): ?>
                            <span class="badge pending small" style="margin-right:8px">
                                <i class="fa-solid fa-triangle-exclamation"></i> ينتهي خلال <?= (int)$days_left ?> يوم
                            </span>
                        <?php else: ?>
                            <span class="badge approved small" style="margin-right:8px">
                                <i class="fa-solid fa-circle-check"></i> ساري
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        <?php else: ?>
            <p class="muted center" style="padding:20px">
                <i class="fa-solid fa-shield-halved" style="font-size:24px;opacity:.3"></i><br>
                لا توجد بيانات تأمين مسجلة
            </p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 class="section-title"><i class="fa-solid fa-chart-simple"></i> ملخص الحضور</h3>
        <table class="info-table">
            <?= row('إجمالي أيام الحضور', number_format($total_days)) ?>
            <?= row('أول يوم حضور', $att_range['first_day'] ?? '—') ?>
            <?= row('آخر يوم حضور', $att_range['last_day'] ?? '—') ?>
        </table>
    </div>
</main>
</body>
</html>
