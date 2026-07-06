<?php
require_once __DIR__ . '/auth.php';
require_admin();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$emp  = (int)($_GET['emp'] ?? 0);

// الفترة الزمنية بالأيام
$days_in_range = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);

// استعلام ملخص الحضور
$where = "a.work_date BETWEEN ? AND ?";
$params = [$from, $to];
if ($emp) { $where .= " AND e.id = ?"; $params[] = $emp; }

$stmt = db()->prepare("
    SELECT
        e.id,
        e.full_name,
        e.gender,
        e.profession,
        COUNT(a.id)                                                          AS days_present,
        COALESCE(SUM(CASE WHEN a.check_out IS NOT NULL
                     THEN FLOOR(EXTRACT(EPOCH FROM (a.check_out - a.check_in)) / 60)
                     ELSE 0 END), 0)                                         AS total_minutes,
        MAX(a.work_date)                                                     AS last_day
    FROM employees e
    LEFT JOIN attendance a ON a.employee_id = e.id AND {$where}
    GROUP BY e.id, e.full_name, e.gender, e.profession
    ORDER BY days_present DESC, e.full_name
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// تصدير CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $from . '_' . $to . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['الموظف', 'المهنة', 'أيام الحضور', 'إجمالي الساعات', 'متوسط ساعات/يوم', 'آخر حضور']);
    foreach ($rows as $r) {
        $total_h = $r['total_minutes'] > 0 ? round($r['total_minutes'] / 60, 1) : 0;
        $avg_h   = ($r['days_present'] > 0 && $r['total_minutes'] > 0)
                   ? round($r['total_minutes'] / $r['days_present'] / 60, 1) : 0;
        fputcsv($out, [
            $r['full_name'],
            $r['profession'],
            $r['days_present'],
            $total_h,
            $avg_h,
            $r['last_day'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$employees_list = db()->query("SELECT id, full_name FROM employees ORDER BY full_name")->fetchAll();

// إجماليات
$grand_days   = array_sum(array_column($rows, 'days_present'));
$grand_minutes = array_sum(array_column($rows, 'total_minutes'));

$page_title = 'تقارير الحضور';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_admin_nav.php'; ?>

<main class="container">

    <!-- إجماليات سريعة -->
    <div class="stats">
        <div class="stat-card">
            <div class="icon blue"><i class="fa-solid fa-calendar-days"></i></div>
            <div class="body">
                <div class="stat-label">الفترة المحددة</div>
                <div class="stat-value" style="font-size:20px"><?= e($from) ?></div>
                <div class="stat-sub">حتى <?= e($to) ?> (<?= $days_in_range ?> يوم)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon green"><i class="fa-solid fa-user-check"></i></div>
            <div class="body">
                <div class="stat-label">إجمالي أيام الحضور</div>
                <div class="stat-value"><?= number_format($grand_days) ?></div>
                <div class="stat-sub">لجميع الموظفين</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon purple"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="body">
                <div class="stat-label">إجمالي ساعات العمل</div>
                <div class="stat-value"><?= number_format($grand_minutes / 60, 0) ?></div>
                <div class="stat-sub">ساعة عمل موثقة</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-chart-bar"></i> تقرير الحضور التفصيلي</h2>
        </div>

        <form method="get" class="filters">
            <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
            <label>الموظف
                <select name="emp">
                    <option value="0">— الكل —</option>
                    <?php foreach ($employees_list as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= $emp == $el['id'] ? 'selected' : '' ?>>
                            <?= e($el['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn primary"><i class="fa-solid fa-filter"></i> عرض</button>
            <a class="btn success" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&emp=<?= $emp ?>&export=1">
                <i class="fa-solid fa-file-csv"></i> تصدير CSV
            </a>
            <a class="btn ghost" href="admin_reports.php"><i class="fa-solid fa-rotate-right"></i> إعادة تعيين</a>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>المهنة</th>
                        <th>أيام الحضور</th>
                        <th>إجمالي الساعات</th>
                        <th>متوسط يومي</th>
                        <th>نسبة الحضور</th>
                        <th>آخر حضور</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $total_h    = $r['total_minutes'] > 0 ? $r['total_minutes'] / 60 : 0;
                    $avg_min    = $r['days_present']  > 0 ? $r['total_minutes'] / $r['days_present'] : 0;
                    $avg_h      = floor($avg_min / 60);
                    $avg_m      = (int)round(fmod($avg_min, 60));
                    $rate       = $days_in_range > 0 ? min(100, round($r['days_present'] / $days_in_range * 100)) : 0;
                    $rate_class = $rate >= 80 ? 'approved' : ($rate >= 50 ? 'pending' : 'rejected');
                ?>
                    <tr>
                        <td>
                            <a href="admin_employee_view.php?id=<?= $r['id'] ?>" style="text-decoration:none;color:inherit">
                                <div class="avatar-cell">
                                    <span class="avatar <?= $r['gender'] === 'female' ? 'female' : '' ?>"><?= e(initials($r['full_name'])) ?></span>
                                    <div class="name"><?= e($r['full_name']) ?></div>
                                </div>
                            </a>
                        </td>
                        <td><?= e($r['profession'] ?? '—') ?></td>
                        <td><strong><?= $r['days_present'] ?></strong></td>
                        <td>
                            <?php if ($r['total_minutes'] > 0): ?>
                                <span class="badge neutral small"><?= number_format($total_h, 1) ?> س</span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['days_present'] > 0 && $avg_min > 0): ?>
                                <?= $avg_h ?>س <?= $avg_m ?>د
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['days_present'] > 0): ?>
                                <div class="progress-bar">
                                    <div class="track">
                                        <div class="fill" style="width:<?= $rate ?>%;background:<?= $rate >= 80 ? 'var(--success)' : ($rate >= 50 ? 'var(--warning)' : 'var(--danger)') ?>"></div>
                                    </div>
                                    <span class="badge <?= $rate_class ?> small"><?= $rate ?>%</span>
                                </div>
                            <?php else: ?>
                                <span class="badge rejected small">0%</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['last_day'] ? e($r['last_day']) : '<span class="muted">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="7" class="muted center" style="padding:40px">
                            <i class="fa-solid fa-folder-open" style="font-size:32px;opacity:.3"></i>
                            <div style="margin-top:8px">لا توجد بيانات في هذه الفترة</div>
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
