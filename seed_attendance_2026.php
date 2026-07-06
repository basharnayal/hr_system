<?php
// =============================================================
// seed_attendance_2026.php
// توليد بيانات حضور واقعية لموظفين محددين
// الموظفون: 51, 52, 53, 54, 55, 56, 57
// الفترة: 2026-01-01 إلى 2026-06-25
// أيام العمل: الأحد - الخميس، 8:00 ص - 5:00 م
//
// ⚠️ يحذف الحضور السابق للموظفين المحددين في نفس الفترة فقط
// ⚠️ لا يمسّ جداول users أو employees أو insurance
// شغّله مرة واحدة من المتصفح ثم احذف الملف
// =============================================================

require_once __DIR__ . '/config.php';

// ==========================================
// الإعدادات الأساسية
// ==========================================
$target_ids   = [51, 52, 53, 54, 55, 56, 57];
$period_start = '2026-01-01';
$period_end   = '2026-06-25';   // شامل هذا اليوم

// ==========================================
// التحقق من الجدول
// ==========================================
try {
    db()->query("SELECT 1 FROM attendance LIMIT 1");
} catch (Exception $e) {
    die('❌ جدول الحضور غير موجود.');
}

// ==========================================
// العطل الرسمية 2026
// ==========================================
$holidays = [
    '2026-02-22', // يوم التأسيس
    // عيد الفطر 1447هـ (تقريبي)
    '2026-03-20', '2026-03-21', '2026-03-22', '2026-03-23', '2026-03-24',
    // عيد الأضحى 1447هـ (تقريبي)
    '2026-06-06', '2026-06-07', '2026-06-08', '2026-06-09', '2026-06-10',
];
$holidaysSet = array_flip($holidays);

// رمضان 1447هـ — دوام مخفف (مدة أقصر لا غياب)
$ramadan_start = '2026-02-18';
$ramadan_end   = '2026-03-19';

// ==========================================
// بناء شخصيات الموظفين (ثابتة حسب ID)
// ==========================================
$personalities = [];
foreach ($target_ids as $eid) {
    mt_srand(crc32('emp_' . $eid));
    $personalities[$eid] = [
        'absence_rate'    => mt_rand(40, 110) / 1000,   // 4%-11% غياب
        'lateness_chance' => mt_rand(50, 250) / 1000,   // 5%-25% احتمال تأخر
        'early_leave'     => mt_rand(20, 70)  / 1000,   // 2%-7%  خروج مبكر
        'overtime_rate'   => mt_rand(30, 120) / 1000,   // 3%-12% بقاء إضافي
        'in_offset'       => mt_rand(-10, 12),           // تفضيل الحضور (دقيقة)
        'out_offset'      => mt_rand(-5,  15),           // تفضيل الانصراف (دقيقة)
        'vacation_days'   => [],
    ];
}

// إجازات مخططة (2-3 إجازات في الفترة)
foreach ($personalities as $eid => &$p) {
    mt_srand(crc32('vac26_' . $eid));
    $numVacs = mt_rand(2, 3);
    for ($v = 0; $v < $numVacs; $v++) {
        $month = mt_rand(1, 6);
        $day   = mt_rand(1, 22);
        $len   = mt_rand(2, 5);
        $start = strtotime("2026-{$month}-{$day}");
        for ($d = 0; $d < $len; $d++) {
            $vDate = date('Y-m-d', $start + $d * 86400);
            if ($vDate >= $period_start && $vDate <= $period_end) {
                $p['vacation_days'][$vDate] = true;
            }
        }
    }
}
unset($p);
mt_srand(); // إعادة ضبط RNG

// ==========================================
// صفحة التأكيد (GET)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // تقدير عدد أيام العمل في الفترة
    $est_days = 0;
    $d = new DateTime($period_start);
    $e = new DateTime($period_end);
    while ($d <= $e) {
        $dow = (int)$d->format('w');
        if ($dow !== 5 && $dow !== 6 && !isset($holidaysSet[$d->format('Y-m-d')])) $est_days++;
        $d->modify('+1 day');
    }
    $est_records = round($est_days * count($target_ids) * 0.92);
    ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>توليد حضور 2026</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-bg">
<div class="card" style="max-width:600px;margin:60px auto;">
    <h1 style="font-size:22px;margin-bottom:6px">
        <i class="fa-solid fa-calendar-check" style="color:var(--accent)"></i>
        توليد بيانات حضور — 2026
    </h1>
    <div class="alert info" style="margin-bottom:18px">
        <i class="fa-solid fa-circle-info"></i>
        <span>سيتم حذف السجلات السابقة لهؤلاء الموظفين في نفس الفترة فقط، ثم إعادة التوليد.</span>
    </div>

    <table class="data" style="margin-bottom:18px">
        <tr><th>الفترة</th><td><strong><?= $period_start ?></strong> إلى <strong><?= $period_end ?></strong></td></tr>
        <tr><th>الموظفون</th><td><?= implode('، ', array_map(fn($i) => "#{$i}", $target_ids)) ?></td></tr>
        <tr><th>عدد الموظفين</th><td><?= count($target_ids) ?></td></tr>
        <tr><th>أيام العمل المتوقعة</th><td><?= $est_days ?> يوم لكل موظف</td></tr>
        <tr><th>السجلات المتوقعة (تقريباً)</th><td>~<?= number_format($est_records) ?> سجل</td></tr>
        <tr><th>أيام العمل</th><td>الأحد – الخميس</td></tr>
        <tr><th>ساعات الدوام</th><td>8:00 ص — 5:00 م (مع تفاوت واقعي)</td></tr>
        <tr><th>لن يُمَسّ</th><td><code>users</code>, <code>employees</code>, <code>insurance</code></td></tr>
    </table>

    <details style="margin-bottom:18px">
        <summary style="cursor:pointer;color:#2563eb;font-weight:600">العطل المستثناة في الفترة</summary>
        <ul style="margin-top:8px;font-size:14px">
            <li>يوم التأسيس: 2026-02-22</li>
            <li>عيد الفطر: 2026-03-20 إلى 2026-03-24</li>
            <li>عيد الأضحى: 2026-06-06 إلى 2026-06-10</li>
            <li>رمضان (دوام مخفف 5 ساعات): 2026-02-18 إلى 2026-03-19</li>
        </ul>
    </details>

    <form method="post">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn primary">
                <i class="fa-solid fa-play"></i> ابدأ التوليد
            </button>
            <a href="admin.php" class="btn ghost">إلغاء</a>
        </div>
    </form>
</div>
</body>
</html>
    <?php
    exit;
}

// ==========================================
// التنفيذ (POST)
// ==========================================
set_time_limit(180);
ini_set('memory_limit', '128M');

// حذف السجلات السابقة لهؤلاء الموظفين في الفترة المحددة فقط
$id_placeholders = implode(',', array_fill(0, count($target_ids), '?'));
$del_params = array_merge($target_ids, [$period_start, $period_end]);
$deleted = db()->prepare(
    "DELETE FROM attendance WHERE employee_id IN ({$id_placeholders}) AND work_date BETWEEN ? AND ?"
);
$deleted->execute($del_params);
$deleted_count = $deleted->rowCount();

// إدخال السجلات الجديدة
db()->beginTransaction();
$stmt = db()->prepare(
    "INSERT INTO attendance (employee_id, check_in, check_out, work_date) VALUES (?, ?, ?, ?)"
);

$count          = 0;
$per_emp_count  = [];
$start_dt       = new DateTime($period_start);
$end_dt         = new DateTime($period_end);

foreach ($target_ids as $eid) {
    $p         = $personalities[$eid];
    $emp_count = 0;
    $current   = clone $start_dt;

    while ($current <= $end_dt) {
        $dow     = (int)$current->format('w');   // 0=Sun, 5=Fri, 6=Sat
        $dateStr = $current->format('Y-m-d');

        // تخطي الجمعة والسبت
        if ($dow === 5 || $dow === 6) { $current->modify('+1 day'); continue; }

        // تخطي العطل الرسمية
        if (isset($holidaysSet[$dateStr])) { $current->modify('+1 day'); continue; }

        // تخطي الإجازات المخططة
        if (isset($p['vacation_days'][$dateStr])) { $current->modify('+1 day'); continue; }

        // غياب عشوائي
        if ((mt_rand(0, 10000) / 10000) < $p['absence_rate']) { $current->modify('+1 day'); continue; }

        // رمضان؟
        $is_ramadan = ($dateStr >= $ramadan_start && $dateStr <= $ramadan_end);

        // ============================================
        // وقت الحضور (check_in)
        // الأساس: 8:00 + تفضيل الموظف + تذبذب عشوائي
        // ============================================
        $base_in = 8 * 60 + $p['in_offset'];      // دقائق من منتصف الليل
        $jitter  = mt_rand(-12, 12);               // تذبذب عادي ±12 دقيقة
        $r       = mt_rand(0, 10000) / 10000;

        if ($r < $p['lateness_chance']) {
            // تأخر بسيط أو متوسط
            $jitter += mt_rand(15, 40);
            // تأخر شديد نادر جداً (~8%)
            if (mt_rand(0, 100) < 8) $jitter += mt_rand(20, 55);
        } elseif ($r > 0.90) {
            // وصول مبكر
            $jitter -= mt_rand(5, 15);
        }

        // الأحد أصعب (بعد الإجازة)
        if ($dow === 0 && mt_rand(0, 100) < 30) $jitter += mt_rand(5, 20);

        $in_total = $base_in + $jitter;
        $in_total = max(7 * 60 + 30, min(10 * 60 + 30, $in_total));  // [7:30, 10:30]

        // ============================================
        // مدة العمل → وقت الانصراف (check_out)
        // ============================================
        if ($is_ramadan) {
            // رمضان: ~5 ساعات ± 15 دقيقة
            $duration = 5 * 60 + mt_rand(-15, 15);
        } else {
            $roll = mt_rand(0, 10000) / 10000;
            if ($roll < $p['early_leave']) {
                // خروج مبكر (نادر): 6 – 7.5 ساعة
                $duration = mt_rand(6 * 60, 7 * 60 + 30);
            } elseif ($roll > 1 - $p['overtime_rate']) {
                // بقاء إضافي (نادر): 8.5 – 9.25 ساعة
                $duration = mt_rand(8 * 60 + 30, 9 * 60 + 15);
                if (mt_rand(0, 100) < 8) $duration = mt_rand(9 * 60 + 15, 9 * 60 + 45);
            } else {
                // الحالة الغالبة: ~8 ساعات بتذبذب خفيف
                $duration = mt_rand(7 * 60 + 45, 8 * 60 + 15);
            }
        }

        $out_total = $in_total + $duration;
        $out_total = min(19 * 60, $out_total);   // لا يتجاوز 7:00 م

        // بناء التواريخ مع ثواني عشوائية للواقعية
        $in_h  = intdiv($in_total, 60);  $in_m  = $in_total  % 60; $in_s  = mt_rand(0, 59);
        $out_h = intdiv($out_total, 60); $out_m = $out_total % 60; $out_s = mt_rand(0, 59);

        $check_in  = sprintf('%s %02d:%02d:%02d', $dateStr, $in_h,  $in_m,  $in_s);
        $check_out = sprintf('%s %02d:%02d:%02d', $dateStr, $out_h, $out_m, $out_s);

        $stmt->execute([$eid, $check_in, $check_out, $dateStr]);
        $count++;
        $emp_count++;

        // commit جزئي كل 1000 سجل
        if ($count % 1000 === 0) {
            db()->commit();
            db()->beginTransaction();
        }

        $current->modify('+1 day');
    }

    $per_emp_count[$eid] = $emp_count;
}
db()->commit();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تم التوليد</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
<div class="card" style="max-width:640px;margin:40px auto;">
    <h1 style="font-size:22px;margin-bottom:16px">
        <i class="fa-solid fa-circle-check" style="color:#10B981"></i>
        تم توليد بيانات الحضور بنجاح
    </h1>

    <table class="data" style="margin-bottom:18px">
        <tr><th>الفترة</th><td><?= $period_start ?> إلى <?= $period_end ?></td></tr>
        <tr><th>السجلات المحذوفة (القديمة)</th><td><?= number_format($deleted_count) ?></td></tr>
        <tr><th>السجلات المُنشأة</th><td><strong><?= number_format($count) ?></strong></td></tr>
    </table>

    <details open>
        <summary style="cursor:pointer;color:#2563eb;font-weight:600;margin-bottom:8px">تفاصيل لكل موظف</summary>
        <table class="data">
            <thead>
                <tr>
                    <th>ID الموظف</th>
                    <th>السجلات</th>
                    <th>نسبة الحضور التقريبية</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // حساب أيام العمل الفعلية في الفترة (استثناء عطل رسمية)
            $work_days = 0;
            $d = new DateTime($period_start);
            while ($d <= new DateTime($period_end)) {
                $dow = (int)$d->format('w');
                if ($dow !== 5 && $dow !== 6 && !isset($holidaysSet[$d->format('Y-m-d')])) $work_days++;
                $d->modify('+1 day');
            }
            foreach ($per_emp_count as $eid => $cnt):
                $rate = $work_days > 0 ? round($cnt / $work_days * 100) : 0;
            ?>
                <tr>
                    <td><code>#<?= (int)$eid ?></code></td>
                    <td><?= number_format($cnt) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="flex:1;height:8px;background:#e5e7eb;border-radius:4px">
                                <div style="width:<?= min(100,$rate) ?>%;height:8px;background:<?= $rate >= 80 ? '#10B981' : ($rate >= 60 ? '#F59E0B' : '#EF4444') ?>;border-radius:4px"></div>
                            </div>
                            <span style="font-size:13px;font-weight:600;min-width:38px"><?= $rate ?>%</span>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>

    <div class="alert error" style="margin-top:20px">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>احذف الآن ملف <code>seed_attendance_2026.php</code> من السيرفر — لا تتركه منشوراً.</span>
    </div>

    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
        <a class="btn primary" href="admin.php">
            <i class="fa-solid fa-gauge-high"></i> لوحة المدير
        </a>
        <a class="btn ghost" href="admin_attendance.php">
            <i class="fa-solid fa-clock"></i> عرض سجل الحضور
        </a>
        <a class="btn ghost" href="admin_reports.php">
            <i class="fa-solid fa-chart-bar"></i> التقارير
        </a>
    </div>
</div>
</div>
</body>
</html>
