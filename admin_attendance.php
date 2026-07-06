<?php
require_once __DIR__ . '/auth.php';
require_admin();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$emp  = (int)($_GET['emp'] ?? 0);

$where = "a.work_date BETWEEN ? AND ?";
$params = [$from, $to];
if ($emp) { $where .= " AND a.employee_id = ?"; $params[] = $emp; }

$stmt = db()->prepare("
    SELECT a.*, e.full_name, e.national_id, e.gender
    FROM attendance a
    JOIN employees e ON e.id = a.employee_id
    WHERE $where
    ORDER BY a.work_date DESC, a.check_in DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// تصدير CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_'.$from.'_'.$to.'.csv"');
    echo "\xEF\xBB\xBF"; // BOM for Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['التاريخ','الاسم','الهوية','الحضور','الانصراف','المدة (دقيقة)']);
    foreach ($rows as $r) {
        $dur = '';
        if ($r['check_out']) {
            $dur = round((strtotime($r['check_out']) - strtotime($r['check_in'])) / 60);
        }
        fputcsv($out, [
            $r['work_date'],
            $r['full_name'],
            $r['national_id'],
            date('H:i', strtotime($r['check_in'])),
            $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '',
            $dur,
        ]);
    }
    fclose($out);
    exit;
}

$employees = db()->query("SELECT id, full_name FROM employees ORDER BY full_name")->fetchAll();
$page_title = 'سجل الحضور';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_admin_nav.php'; ?>

<main class="container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> سجل الحضور (<?= count($rows) ?> سجل)</h2>
        </div>

        <form method="get" class="filters">
            <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
            <label>الموظف
                <select name="emp">
                    <option value="0">— الكل —</option>
                    <?php foreach ($employees as $em): ?>
                        <option value="<?= $em['id'] ?>" <?= $emp == $em['id'] ? 'selected' : '' ?>><?= e($em['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn primary">
                <i class="fa-solid fa-filter"></i> عرض
            </button>
            <a class="btn success" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&emp=<?= $emp ?>&export=1">
                <i class="fa-solid fa-file-csv"></i> تصدير CSV
            </a>
            <a class="btn ghost" href="admin_attendance.php">
                <i class="fa-solid fa-rotate-right"></i> إعادة تعيين
            </a>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الموظف</th>
                        <th>الهوية</th>
                        <th>الحضور</th>
                        <th>الانصراف</th>
                        <th>المدة</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['work_date']) ?></td>
                        <td>
                            <a href="admin_employee_view.php?id=<?= (int)$r['employee_id'] ?>" class="emp-link">
                                <div class="avatar-cell">
                                    <span class="avatar <?= ($r['gender'] ?? '') === 'female' ? 'female' : '' ?>"><?= e(initials($r['full_name'])) ?></span>
                                    <div class="name"><?= e($r['full_name']) ?></div>
                                </div>
                            </a>
                        </td>
                        <td><code><?= e($r['national_id']) ?></code></td>
                        <td><strong><?= e(date('H:i', strtotime($r['check_in']))) ?></strong></td>
                        <td><?= $r['check_out'] ? '<strong>' . e(date('H:i', strtotime($r['check_out']))) . '</strong>' : '<span class="muted">—</span>' ?></td>
                        <td>
                            <?php
                            if ($r['check_out']) {
                                $sec = strtotime($r['check_out']) - strtotime($r['check_in']);
                                $h = floor($sec/3600);
                                $m = floor(($sec%3600)/60);
                                echo "<span class=\"badge neutral small\">{$h}س {$m}د</span>";
                            } else {
                                echo '<span class="badge in small"><i class="fa-solid fa-circle"></i> مازال في الدوام</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="muted center" style="padding:32px">
                            <i class="fa-solid fa-folder-open" style="font-size:28px;opacity:.3"></i>
                            <div style="margin-top:8px">لا توجد سجلات في هذه الفترة</div>
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
