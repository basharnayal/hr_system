<?php
// =============================================================
// seed_new_employees_2026.php
// إضافة 4 موظفين جدد + توليد حضورهم الواقعي
// الفترة: 2026-01-01 إلى 2026-07-20 | الأحد–الخميس | 8:00ص–5:00م
//
// ما يفعله السكربت:
//   1) يضيف الموظفين الأربعة (جدول employees) + حساب دخول لكل منهم (جدول users)
//      - آمن للتكرار: إن وُجد الموظف مسبقاً (بنفس رقم الهوية) يُعاد استخدامه ولا يُكرَّر
//   2) يحذف حضورهم السابق في نفس الفترة فقط ثم يولّد حضوراً واقعياً
//
// لا يمسّ جدول insurance إطلاقاً — الإدخال في employees / users / attendance فقط.
// شغّله مرة واحدة من المتصفح ثم احذف الملف من السيرفر.
// =============================================================

require_once __DIR__ . '/config.php';

$period_start = '2026-01-01';
$period_end   = '2026-07-20';   // شامل هذا اليوم (تاريخ اليوم)

// دومين البريد الإلكتروني — يُبنى تلقائياً من اسم الموظف
$email_domain = 'almatrafi.website';

/**
 * يبني بريداً إلكترونياً من اسم الموظف على الدومين المحدد.
 * يحوّل العربية إلى لاتيني، يأخذ الاسم الأول + الأخير مفصولين بنقطة.
 * إن تعذّر التحويل يستخدم البديل (رقم الهوية).
 */
function name_to_email(string $full_name, string $domain, string $fallback): string {
    static $map = [
        'ا'=>'a','أ'=>'a','إ'=>'e','آ'=>'a','ٱ'=>'a','ب'=>'b','ت'=>'t','ث'=>'th',
        'ج'=>'j','ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'th','ر'=>'r','ز'=>'z','س'=>'s',
        'ش'=>'sh','ص'=>'s','ض'=>'d','ط'=>'t','ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f',
        'ق'=>'q','ك'=>'k','ل'=>'l','م'=>'m','ن'=>'n','ه'=>'h','و'=>'w','ي'=>'y',
        'ى'=>'a','ة'=>'h','ئ'=>'y','ؤ'=>'w','ء'=>'','ٰ'=>'a',
        // تشكيل يُحذف
        'ً'=>'','ٌ'=>'','ٍ'=>'','َ'=>'','ُ'=>'','ِ'=>'','ّ'=>'','ْ'=>'',
    ];
    $translit = function (string $token) use ($map): string {
        $out = '';
        $len = mb_strlen($token, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch  = mb_substr($token, $i, 1, 'UTF-8');
            $out .= $map[$ch] ?? (preg_match('/[a-zA-Z0-9]/', $ch) ? strtolower($ch) : '');
        }
        return $out;
    };

    $tokens = preg_split('/\s+/u', trim($full_name), -1, PREG_SPLIT_NO_EMPTY);
    $parts  = [];
    if ($tokens) {
        $parts[] = $translit($tokens[0]);                       // الاسم الأول
        if (count($tokens) > 1) $parts[] = $translit(end($tokens)); // الاسم الأخير
    }
    $parts  = array_filter($parts, fn($x) => $x !== '');
    $handle = implode('.', $parts);
    $handle = preg_replace('/[^a-z0-9.]/', '', $handle);
    $handle = trim($handle, '.');

    if ($handle === '') $handle = $fallback;   // بديل عند تعذّر التحويل
    return $handle . '@' . $domain;
}

// كلمة المرور الافتراضية لكل حساب = رقم الهوية (يمكن تغييرها لاحقاً من لوحة المدير)
// اسم المستخدم الافتراضي = "e" + رقم الهوية

// =============================================================
// بيانات الموظفين المستخرجة من الملفات
// ⚠️ عبّئ الاسم الكامل (full_name) لكل موظف قبل التشغيل — 4 أسماء فقط
// =============================================================
$new_employees = [
    [
        'full_name'      => ' رﻏﺪ ﺳﻠﻴﻤﺎن ﺑﻦ ﺳﺎﻟﻢ اﻟﻤﻄﺮﻓي',                 // ← اكتب الاسم الرباعي (هوية 1165723311)
        'national_id'    => '1165723311',
        'gender'         => 'female',
        'phone'          => '0598044748',
        'email'          => '',                 // يُبنى تلقائياً من الاسم (أو اكتبه يدوياً)
        'profession'     => 'أخصائي تسويق',
        'medical_insurance' => 1,
        'job_number'     => '85',
        'hire_date'      => '2025-05-05',
        'nationality'    => 'سعودي',
        'religion'       => 'مسلم',
        'marital_status' => 'أعزب',
        'birth_date'     => '1428-08-17',       // هجري كما ورد في العقد
        'id_type'        => 'رقم الهوية',
        'id_expiry'      => '1447-11-05',       // هجري
        'education'      => 'ثانوية عامة',
        'specialization' => 'التعليم الثانوي (عام)',
        'bank_name'      => null,
        'iban'           => null,
    ],
    [
        'full_name'      => 'ﻓﺎﻳﺰه ﻣﺤﻤﺪ اﺑﻦ ﻋﺎﻳﺶ اﻟﻬﺬﻟﻲ',                 // ← اكتب الاسم الرباعي (هوية 1024233791)
        'national_id'    => '1024233791',
        'gender'         => 'female',
        'phone'          => '0594804641',
        'email'          => '',                 // يُبنى تلقائياً من الاسم (أو اكتبه يدوياً)
        'profession'     => 'أخصائي تسويق',
        'medical_insurance' => 1,
        'job_number'     => '88',
        'hire_date'      => '2025-03-09',
        'nationality'    => 'سعودي',
        'religion'       => 'مسلم',
        'marital_status' => 'متزوج',
        'birth_date'     => '1399-07-01',       // هجري
        'id_type'        => 'رقم الهوية',
        'id_expiry'      => '1452-11-13',       // هجري
        'education'      => 'ثانوية عامة',
        'specialization' => 'التعليم المتوسط (عام)',
        'bank_name'      => null,
        'iban'           => null,
    ],
    [
        'full_name'      => ' ﻋﻤﺎد ﻣﻔﺮح ﻋﺒﻴﺪ اﻟﻤﻄﺮﻓﻲ',                 // ← اكتب الاسم الرباعي (هوية 1118420130)
        'national_id'    => '1118420130',
        'gender'         => 'male',
        'phone'          => '0595300268',
        'email'          => '',                 // يُبنى تلقائياً من الاسم (أو اكتبه يدوياً)
        'profession'     => 'أخصائي مبيعات',
        'medical_insurance' => 1,
        'job_number'     => '84',
        'hire_date'      => '2025-03-09',
        'nationality'    => 'سعودي',
        'religion'       => 'مسلم',
        'marital_status' => 'أعزب',
        'birth_date'     => '1423-08-11',       // هجري
        'id_type'        => 'رقم الهوية',
        'id_expiry'      => '1451-03-23',       // هجري
        'education'      => 'ثانوية عامة',
        'specialization' => 'التعليم المتوسط (عام)',
        'bank_name'      => null,
        'iban'           => null,
    ],
    [
        'full_name'      => 'سامي مسلم المطرفي',                 // ← اكتب الاسم الرباعي (هوية 1127516498)
        'national_id'    => '1127516498',
        'gender'         => 'male',
        'phone'          => '0597407090',
        'email'          => '',                 // يُبنى تلقائياً من الاسم (أو اكتبه يدوياً)
        'profession'     => null,               // لا يوجد عقد لهذا الموظف — بيانات ناقصة
        'medical_insurance' => 1,
        'job_number'     => null,
        'hire_date'      => null,
        'nationality'    => 'سعودي',
        'religion'       => 'مسلم',
        'marital_status' => null,
        'birth_date'     => null,
        'id_type'        => 'رقم الهوية',
        'id_expiry'      => null,
        'education'      => null,
        'specialization' => null,
        'bank_name'      => null,
        'iban'           => null,
    ],
];

// =============================================================
// التحقق من الجداول
// =============================================================
try {
    db()->query("SELECT 1 FROM attendance LIMIT 1");
    db()->query("SELECT 1 FROM employees LIMIT 1");
    db()->query("SELECT 1 FROM users LIMIT 1");
} catch (Exception $e) {
    die('❌ أحد الجداول المطلوبة (attendance / employees / users) غير موجود.');
}

// =============================================================
// العطل الرسمية 2026 (نفس منطق seed_attendance_2026)
// =============================================================
$holidays = [
    '2026-02-22', // يوم التأسيس
    '2026-03-20', '2026-03-21', '2026-03-22', '2026-03-23', '2026-03-24', // عيد الفطر (تقريبي)
    '2026-06-06', '2026-06-07', '2026-06-08', '2026-06-09', '2026-06-10', // عيد الأضحى (تقريبي)
];
$holidaysSet = array_flip($holidays);

// رمضان 1447هـ — دوام مخفف
$ramadan_start = '2026-02-18';
$ramadan_end   = '2026-03-19';

// =============================================================
// صفحة التأكيد (GET)
// =============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // تقدير أيام العمل
    $est_days = 0;
    $d = new DateTime($period_start);
    $eDt = new DateTime($period_end);
    while ($d <= $eDt) {
        $dow = (int)$d->format('w');
        if ($dow !== 5 && $dow !== 6 && !isset($holidaysSet[$d->format('Y-m-d')])) $est_days++;
        $d->modify('+1 day');
    }
    $missing_names = array_filter($new_employees, fn($x) => trim($x['full_name']) === '');
    ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>إضافة موظفين + حضور 2026</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-bg">
<div class="card" style="max-width:680px;margin:50px auto;">
    <h1 style="font-size:22px;margin-bottom:6px">
        <i class="fa-solid fa-user-plus" style="color:var(--accent)"></i>
        إضافة 4 موظفين + توليد حضورهم (2026)
    </h1>

    <?php if ($missing_names): ?>
        <div class="alert error" style="margin-bottom:18px">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>
                يجب تعبئة حقل <code>full_name</code> لكل موظف داخل الملف قبل التشغيل.
                الأسماء الناقصة: <?= count($missing_names) ?> من <?= count($new_employees) ?>.
                افتح <code>seed_new_employees_2026.php</code> واكتب الأسماء ثم أعد التحميل.
            </span>
        </div>
    <?php else: ?>
        <div class="alert info" style="margin-bottom:18px">
            <i class="fa-solid fa-circle-info"></i>
            <span>سيُضاف الموظفون (إن لم يكونوا موجودين) ثم يُحذف حضورهم السابق في الفترة ويُعاد توليده.</span>
        </div>
    <?php endif; ?>

    <table class="data" style="margin-bottom:16px">
        <thead>
            <tr><th>الاسم</th><th>رقم الهوية</th><th>البريد (تلقائي)</th><th>الجوال</th></tr>
        </thead>
        <tbody>
        <?php foreach ($new_employees as $emp):
            $named = trim($emp['full_name']) !== '';
            $mail_preview = trim($emp['email']) !== ''
                ? $emp['email']
                : ($named ? name_to_email($emp['full_name'], $email_domain, 'e' . $emp['national_id']) : '—');
        ?>
            <tr>
                <td><?= $named
                        ? htmlspecialchars($emp['full_name'])
                        : '<span style="color:#EF4444">— غير معبأ —</span>' ?></td>
                <td><code><?= htmlspecialchars($emp['national_id']) ?></code></td>
                <td style="direction:ltr;text-align:right"><?= htmlspecialchars($mail_preview) ?></td>
                <td><?= htmlspecialchars($emp['phone'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="data" style="margin-bottom:18px">
        <tr><th>الفترة</th><td><strong><?= $period_start ?></strong> إلى <strong><?= $period_end ?></strong></td></tr>
        <tr><th>أيام العمل المتوقعة</th><td><?= $est_days ?> يوم لكل موظف</td></tr>
        <tr><th>أيام العمل</th><td>الأحد – الخميس (بدون الجمعة والسبت)</td></tr>
        <tr><th>ساعات الدوام</th><td>8:00 ص — 5:00 م (بتفاوت واقعي)</td></tr>
        <tr><th>اسم المستخدم</th><td><code>e</code> + رقم الهوية (مثال: <code>e1165723311</code>)</td></tr>
        <tr><th>كلمة المرور المبدئية</th><td>رقم الهوية (غيّرها لاحقاً)</td></tr>
        <tr><th>لن يُمَسّ</th><td><code>insurance</code></td></tr>
    </table>

    <form method="post">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn primary" <?= $missing_names ? 'disabled style="opacity:.5;cursor:not-allowed"' : '' ?>>
                <i class="fa-solid fa-play"></i> ابدأ الإضافة والتوليد
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

// =============================================================
// التنفيذ (POST)
// =============================================================
set_time_limit(180);
ini_set('memory_limit', '128M');

// منع التشغيل إن كانت الأسماء ناقصة
foreach ($new_employees as $emp) {
    if (trim($emp['full_name']) === '') {
        die('❌ يوجد موظف بدون اسم. عبّئ حقل full_name لكل الموظفين ثم أعد التشغيل.');
    }
}

$target_ids   = [];   // معرفات الموظفين (الجديدة أو الموجودة)
$emp_report   = [];   // للتقرير: national_id => [name, id, action]

db()->beginTransaction();
try {
    $find_emp  = db()->prepare("SELECT id FROM employees WHERE national_id = ?");
    $find_user = db()->prepare("SELECT id FROM users WHERE employee_id = ?");

    $ins_emp = db()->prepare("
        INSERT INTO employees
            (full_name, national_id, gender, phone, email, profession, medical_insurance,
             job_number, department_id, status, hire_date, nationality, religion, marital_status,
             birth_date, id_type, id_expiry, education, specialization, bank_name, iban)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $ins_user = db()->prepare(
        "INSERT INTO users (username, password, role, employee_id) VALUES (?,?,'employee',?)"
    );

    foreach ($new_employees as $emp) {
        // موجود مسبقاً؟
        $find_emp->execute([$emp['national_id']]);
        $existing = $find_emp->fetchColumn();

        // البريد: يدوي إن وُجد، وإلا يُبنى من الاسم على الدومين
        $email = trim($emp['email']) !== ''
            ? $emp['email']
            : name_to_email($emp['full_name'], $email_domain, 'e' . $emp['national_id']);

        if ($existing) {
            $emp_id = (int)$existing;
            $action = 'موجود مسبقاً — أعيد استخدامه';
        } else {
            $ins_emp->execute([
                $emp['full_name'], $emp['national_id'], $emp['gender'], $emp['phone'], $email,
                $emp['profession'], $emp['medical_insurance'], $emp['job_number'], null, 'active',
                $emp['hire_date'], $emp['nationality'], $emp['religion'], $emp['marital_status'],
                $emp['birth_date'], $emp['id_type'], $emp['id_expiry'], $emp['education'],
                $emp['specialization'], $emp['bank_name'], $emp['iban'],
            ]);
            $emp_id = (int)db()->lastInsertId();
            $action = 'أُضيف جديد';
        }

        // حساب الدخول — أنشئه إن لم يوجد
        $find_user->execute([$emp_id]);
        if (!$find_user->fetchColumn()) {
            $username = 'e' . $emp['national_id'];
            $hash     = password_hash($emp['national_id'], PASSWORD_DEFAULT);
            try {
                $ins_user->execute([$username, $hash, $emp_id]);
                $action .= ' + حساب دخول';
            } catch (Exception $ue) {
                // اسم مستخدم مكرر أو ما شابه — لا نوقف العملية
                $action .= ' (تعذّر إنشاء الحساب: اسم مستخدم مكرر؟)';
            }
        }

        $target_ids[] = $emp_id;
        $emp_report[$emp['national_id']] = [
            'name' => $emp['full_name'], 'id' => $emp_id, 'action' => $action,
        ];
    }
    db()->commit();
} catch (Exception $e) {
    db()->rollBack();
    die('❌ فشل إضافة الموظفين: ' . htmlspecialchars($e->getMessage()));
}

// =============================================================
// بناء شخصيات الموظفين (ثابتة حسب ID) — نفس منطق seed_attendance_2026
// =============================================================
$personalities = [];
foreach ($target_ids as $eid) {
    mt_srand(crc32('emp_' . $eid));
    $personalities[$eid] = [
        'absence_rate'    => mt_rand(40, 110) / 1000,   // 4%-11%
        'lateness_chance' => mt_rand(50, 250) / 1000,   // 5%-25%
        'early_leave'     => mt_rand(20, 70)  / 1000,   // 2%-7%
        'overtime_rate'   => mt_rand(30, 120) / 1000,   // 3%-12%
        'in_offset'       => mt_rand(-10, 12),
        'out_offset'      => mt_rand(-5,  15),
        'vacation_days'   => [],
    ];
}
foreach ($personalities as $eid => &$p) {
    mt_srand(crc32('vac26_' . $eid));
    $numVacs = mt_rand(2, 3);
    for ($v = 0; $v < $numVacs; $v++) {
        $month = mt_rand(1, 6);
        $day   = mt_rand(1, 22);
        $len   = mt_rand(2, 5);
        $start = strtotime("2026-{$month}-{$day}");
        for ($dd = 0; $dd < $len; $dd++) {
            $vDate = date('Y-m-d', $start + $dd * 86400);
            if ($vDate >= $period_start && $vDate <= $period_end) {
                $p['vacation_days'][$vDate] = true;
            }
        }
    }
}
unset($p);
mt_srand();

// =============================================================
// حذف الحضور السابق لهؤلاء الموظفين في الفترة فقط
// =============================================================
$id_placeholders = implode(',', array_fill(0, count($target_ids), '?'));
$del_params = array_merge($target_ids, [$period_start, $period_end]);
$del = db()->prepare(
    "DELETE FROM attendance WHERE employee_id IN ({$id_placeholders}) AND work_date BETWEEN ? AND ?"
);
$del->execute($del_params);
$deleted_count = $del->rowCount();

// =============================================================
// توليد الحضور
// =============================================================
db()->beginTransaction();
$stmt = db()->prepare(
    "INSERT INTO attendance (employee_id, check_in, check_out, work_date) VALUES (?, ?, ?, ?)"
);

$count         = 0;
$per_emp_count = [];
$start_dt      = new DateTime($period_start);
$end_dt        = new DateTime($period_end);

foreach ($target_ids as $eid) {
    $p         = $personalities[$eid];
    $emp_count = 0;
    $current   = clone $start_dt;

    while ($current <= $end_dt) {
        $dow     = (int)$current->format('w');   // 0=Sun … 5=Fri, 6=Sat
        $dateStr = $current->format('Y-m-d');

        if ($dow === 5 || $dow === 6)              { $current->modify('+1 day'); continue; } // جمعة/سبت
        if (isset($holidaysSet[$dateStr]))         { $current->modify('+1 day'); continue; } // عطلة رسمية
        if (isset($p['vacation_days'][$dateStr]))  { $current->modify('+1 day'); continue; } // إجازة
        if ((mt_rand(0, 10000) / 10000) < $p['absence_rate']) { $current->modify('+1 day'); continue; } // غياب

        $is_ramadan = ($dateStr >= $ramadan_start && $dateStr <= $ramadan_end);

        // ---- وقت الحضور ----
        $base_in = 8 * 60 + $p['in_offset'];
        $jitter  = mt_rand(-12, 12);
        $r       = mt_rand(0, 10000) / 10000;
        if ($r < $p['lateness_chance']) {
            $jitter += mt_rand(15, 40);
            if (mt_rand(0, 100) < 8) $jitter += mt_rand(20, 55);
        } elseif ($r > 0.90) {
            $jitter -= mt_rand(5, 15);
        }
        if ($dow === 0 && mt_rand(0, 100) < 30) $jitter += mt_rand(5, 20); // الأحد أصعب

        $in_total = $base_in + $jitter;
        $in_total = max(7 * 60 + 30, min(10 * 60 + 30, $in_total)); // [7:30, 10:30]

        // ---- مدة العمل → الانصراف ----
        if ($is_ramadan) {
            $duration = 5 * 60 + mt_rand(-15, 15);
        } else {
            $roll = mt_rand(0, 10000) / 10000;
            if ($roll < $p['early_leave']) {
                $duration = mt_rand(6 * 60, 7 * 60 + 30);
            } elseif ($roll > 1 - $p['overtime_rate']) {
                $duration = mt_rand(8 * 60 + 30, 9 * 60 + 15);
                if (mt_rand(0, 100) < 8) $duration = mt_rand(9 * 60 + 15, 9 * 60 + 45);
            } else {
                $duration = mt_rand(7 * 60 + 45, 8 * 60 + 15);
            }
        }

        $out_total = min(19 * 60, $in_total + $duration); // لا يتجاوز 7:00م، ودائماً بعد الحضور

        $in_h  = intdiv($in_total, 60);  $in_m  = $in_total  % 60; $in_s  = mt_rand(0, 59);
        $out_h = intdiv($out_total, 60); $out_m = $out_total % 60; $out_s = mt_rand(0, 59);

        $check_in  = sprintf('%s %02d:%02d:%02d', $dateStr, $in_h,  $in_m,  $in_s);
        $check_out = sprintf('%s %02d:%02d:%02d', $dateStr, $out_h, $out_m, $out_s);

        $stmt->execute([$eid, $check_in, $check_out, $dateStr]);
        $count++;
        $emp_count++;

        if ($count % 1000 === 0) { db()->commit(); db()->beginTransaction(); }

        $current->modify('+1 day');
    }
    $per_emp_count[$eid] = $emp_count;
}
db()->commit();

// حساب أيام العمل الفعلية للنِسَب
$work_days = 0;
$d = new DateTime($period_start);
while ($d <= new DateTime($period_end)) {
    $dow = (int)$d->format('w');
    if ($dow !== 5 && $dow !== 6 && !isset($holidaysSet[$d->format('Y-m-d')])) $work_days++;
    $d->modify('+1 day');
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تم التنفيذ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
<div class="card" style="max-width:720px;margin:40px auto;">
    <h1 style="font-size:22px;margin-bottom:16px">
        <i class="fa-solid fa-circle-check" style="color:#10B981"></i>
        تمت إضافة الموظفين وتوليد الحضور
    </h1>

    <h3 style="margin:12px 0 8px">الموظفون</h3>
    <table class="data" style="margin-bottom:18px">
        <thead>
            <tr><th>الاسم</th><th>رقم الهوية</th><th>ID</th><th>الإجراء</th></tr>
        </thead>
        <tbody>
        <?php foreach ($emp_report as $nid => $info): ?>
            <tr>
                <td><?= htmlspecialchars($info['name']) ?></td>
                <td><code><?= htmlspecialchars($nid) ?></code></td>
                <td><code>#<?= (int)$info['id'] ?></code></td>
                <td><?= htmlspecialchars($info['action']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="data" style="margin-bottom:18px">
        <tr><th>الفترة</th><td><?= $period_start ?> إلى <?= $period_end ?></td></tr>
        <tr><th>سجلات الحضور المحذوفة (القديمة)</th><td><?= number_format($deleted_count) ?></td></tr>
        <tr><th>سجلات الحضور المُنشأة</th><td><strong><?= number_format($count) ?></strong></td></tr>
    </table>

    <h3 style="margin:12px 0 8px">تفاصيل الحضور لكل موظف</h3>
    <table class="data" style="margin-bottom:18px">
        <thead>
            <tr><th>ID</th><th>السجلات</th><th>نسبة الحضور التقريبية</th></tr>
        </thead>
        <tbody>
        <?php foreach ($per_emp_count as $eid => $cnt):
            $rate = $work_days > 0 ? round($cnt / $work_days * 100) : 0; ?>
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

    <div class="alert error" style="margin-top:20px">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>احذف الآن ملف <code>seed_new_employees_2026.php</code> من السيرفر — لا تتركه منشوراً.</span>
    </div>

    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
        <a class="btn primary" href="admin_employees.php"><i class="fa-solid fa-users"></i> الموظفون</a>
        <a class="btn ghost" href="admin_attendance.php"><i class="fa-solid fa-clock"></i> سجل الحضور</a>
        <a class="btn ghost" href="admin_reports.php"><i class="fa-solid fa-chart-bar"></i> التقارير</a>
    </div>
</div>
</div>
</body>
</html>
