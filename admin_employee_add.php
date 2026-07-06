<?php
require_once __DIR__ . '/auth.php';
require_admin();

$msg = ''; $msg_type = '';

$departments = [];
try { $departments = db()->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(); }
catch (Exception $e) {}

function emp_null_add(string $key): ?string {
    $v = trim($_POST[$key] ?? '');
    return $v !== '' ? $v : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $full_name   = trim($_POST['full_name']   ?? '');
        $national_id = trim($_POST['national_id'] ?? '');
        $gender      = $_POST['gender']  ?? 'male';
        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';

        if (!$full_name)               throw new Exception('الاسم الكامل مطلوب');
        if (!$national_id)             throw new Exception('رقم الهوية مطلوب');
        if (!$username)                throw new Exception('اسم المستخدم مطلوب');
        if (strlen($password) < 4)     throw new Exception('كلمة المرور يجب أن تكون 4 أحرف فأكثر');
        if (!in_array($gender, ['male','female'], true)) throw new Exception('قيمة الجنس غير صحيحة');

        // التحقق من عدم تكرار اسم المستخدم
        $chk = db()->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) throw new Exception('اسم المستخدم مستخدم بالفعل، اختر آخر');

        $phone          = emp_null_add('phone');
        $email          = emp_null_add('email');
        $profession     = emp_null_add('profession');
        $insurance      = ($_POST['insurance'] ?? '') === '1' ? 1 : 0;
        $job_number     = emp_null_add('job_number');
        $dept_id        = (int)($_POST['department_id'] ?? 0) ?: null;
        $status         = in_array($_POST['status'] ?? '', ['active','inactive','terminated'])
                          ? $_POST['status'] : 'active';
        $hire_date      = emp_null_add('hire_date');
        $nationality    = trim($_POST['nationality'] ?? '') ?: 'سعودي';
        $religion       = trim($_POST['religion']    ?? '') ?: 'مسلم';
        $marital_status = emp_null_add('marital_status');
        $birth_date     = emp_null_add('birth_date');
        $id_type        = trim($_POST['id_type'] ?? '') ?: 'رقم الهوية';
        $id_expiry      = emp_null_add('id_expiry');
        $education      = emp_null_add('education');
        $specialization = emp_null_add('specialization');
        $bank_name      = emp_null_add('bank_name');
        $iban           = emp_null_add('iban');

        db()->beginTransaction();

        $stmt = db()->prepare("
            INSERT INTO employees
                (full_name, national_id, gender, phone, email, profession, medical_insurance,
                 job_number, department_id, status, hire_date, nationality, religion, marital_status,
                 birth_date, id_type, id_expiry, education, specialization, bank_name, iban)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $full_name, $national_id, $gender, $phone, $email, $profession, $insurance,
            $job_number, $dept_id, $status, $hire_date, $nationality, $religion, $marital_status,
            $birth_date, $id_type, $id_expiry, $education, $specialization, $bank_name, $iban,
        ]);
        $emp_id = (int)db()->lastInsertId();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare("INSERT INTO users (username, password, role, employee_id) VALUES (?,?,'employee',?)")
            ->execute([$username, $hash, $emp_id]);

        db()->commit();
        log_activity('add_employee', 'employee', $emp_id, "إضافة: {$full_name}");

        header('Location: admin_employees.php?added=1&name=' . urlencode($full_name));
        exit;

    } catch (Exception $e) {
        if (db()->inTransaction()) db()->rollBack();
        $msg = $e->getMessage(); $msg_type = 'error';
    }
}

$page_title = 'إضافة موظف جديد';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head><?php include __DIR__ . '/_head.php'; ?></head>
<body>
<?php include __DIR__ . '/_admin_nav.php'; ?>

<main class="container">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="admin_employees.php"><i class="fa-solid fa-users"></i> الموظفون</a>
        <span class="sep">›</span>
        <span class="current">إضافة موظف جديد</span>
    </div>

    <?php if ($msg): ?>
        <div class="alert <?= e($msg_type) ?>">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= e($msg) ?></span>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <!-- ================================================ -->
        <!-- قسم 1: البيانات الأساسية -->
        <!-- ================================================ -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-id-card" style="color:var(--accent)"></i> البيانات الأساسية</h2>
            </div>
            <div class="form-grid">
                <label>الاسم الكامل <span class="req">*</span>
                    <input type="text" name="full_name" required placeholder="الاسم الرباعي">
                </label>
                <label>رقم الهوية الوطنية <span class="req">*</span>
                    <input type="text" name="national_id" required placeholder="10 أرقام">
                </label>
                <label>الجنس
                    <select name="gender">
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                    </select>
                </label>
                <label>تاريخ الميلاد
                    <input type="text" name="birth_date" placeholder="YYYY-MM-DD أو هجري">
                </label>
                <label>الجنسية
                    <input type="text" name="nationality" value="سعودي">
                </label>
                <label>الديانة
                    <input type="text" name="religion" value="مسلم">
                </label>
                <label>الحالة الاجتماعية
                    <select name="marital_status">
                        <option value="">— اختر —</option>
                        <option>أعزب</option>
                        <option>متزوج</option>
                        <option>مطلق</option>
                        <option>أرمل</option>
                    </select>
                </label>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- قسم 2: البيانات الوظيفية -->
        <!-- ================================================ -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-briefcase" style="color:var(--accent)"></i> البيانات الوظيفية</h2>
            </div>
            <div class="form-grid">
                <label>الرقم الوظيفي
                    <input type="text" name="job_number" placeholder="اختياري">
                </label>
                <label>المسمى الوظيفي / المهنة
                    <input type="text" name="profession" placeholder="مثال: محاسب، مدير مبيعات">
                </label>
                <label>القسم
                    <select name="department_id">
                        <option value="">— بدون قسم —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>تاريخ التعيين
                    <input type="date" name="hire_date">
                </label>
                <label>الحالة الوظيفية
                    <select name="status">
                        <option value="active">نشط</option>
                        <option value="inactive">موقوف</option>
                        <option value="terminated">منتهي الخدمة</option>
                    </select>
                </label>
                <label>التأمين الطبي
                    <select name="insurance">
                        <option value="1">نعم</option>
                        <option value="">لا</option>
                    </select>
                </label>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- قسم 3: بيانات الهوية -->
        <!-- ================================================ -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-passport" style="color:var(--accent)"></i> بيانات الهوية</h2>
            </div>
            <div class="form-grid">
                <label>نوع الهوية
                    <select name="id_type">
                        <option value="رقم الهوية">رقم الهوية الوطنية</option>
                        <option value="إقامة">إقامة</option>
                        <option value="جواز سفر">جواز سفر</option>
                    </select>
                </label>
                <label>تاريخ انتهاء الهوية
                    <input type="text" name="id_expiry" placeholder="YYYY-MM-DD أو هجري">
                </label>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- قسم 4: بيانات التواصل -->
        <!-- ================================================ -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-address-book" style="color:var(--accent)"></i> بيانات التواصل</h2>
            </div>
            <div class="form-grid">
                <label>رقم الجوال
                    <input type="tel" name="phone" placeholder="05xxxxxxxx">
                </label>
                <label>البريد الإلكتروني
                    <input type="email" name="email" placeholder="example@domain.com">
                </label>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- قسم 5: المؤهلات -->
        <!-- ================================================ -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-graduation-cap" style="color:var(--accent)"></i> المؤهلات العلمية</h2>
            </div>
            <div class="form-grid">
                <label>المؤهل العلمي
                    <select name="education">
                        <option value="">— اختر —</option>
                        <option>دكتوراه</option>
                        <option>ماجستير</option>
                        <option>بكالوريوس</option>
                        <option>دبلوم عالي</option>
                        <option>دبلوم</option>
                        <option>ثانوية عامة</option>
                        <option>متوسطة</option>
                        <option>ابتدائية</option>
                    </select>
                </label>
                <label>التخصص
                    <input type="text" name="specialization" placeholder="مثال: إدارة أعمال، محاسبة">
                </label>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- قسم 6: البيانات البنكية -->
        <!-- ================================================ -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-landmark" style="color:var(--accent)"></i> البيانات البنكية</h2>
            </div>
            <div class="form-grid">
                <label>اسم البنك
                    <input type="text" name="bank_name" placeholder="مثال: بنك الراجحي">
                </label>
                <label>رقم الآيبان
                    <input type="text" name="iban" placeholder="SA29 ..." style="direction:ltr;text-align:right">
                </label>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- قسم 7: حساب الدخول -->
        <!-- ================================================ -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-lock" style="color:var(--accent)"></i> حساب الدخول للنظام</h2>
            </div>
            <div class="form-grid">
                <label>اسم المستخدم <span class="req">*</span>
                    <input type="text" name="username" required placeholder="يُستخدم لتسجيل الدخول" autocomplete="off">
                </label>
                <label>كلمة المرور <span class="req">*</span>
                    <input type="password" name="password" required minlength="4" placeholder="4 أحرف فأكثر" autocomplete="new-password">
                </label>
            </div>
        </div>

        <!-- أزرار الحفظ -->
        <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;margin-bottom:32px">
            <a href="admin_employees.php" class="btn ghost">
                <i class="fa-solid fa-xmark"></i> إلغاء
            </a>
            <button type="submit" class="btn primary big">
                <i class="fa-solid fa-user-plus"></i> إضافة الموظف
            </button>
        </div>

    </form>
</main>


</body>
</html>
