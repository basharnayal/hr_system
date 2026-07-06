<?php
require_once __DIR__ . '/auth.php';
require_admin();

$msg = ''; $msg_type = '';

// رسالة نجاح الإضافة (redirect من admin_employee_add.php)
if (isset($_GET['added'])) {
    $msg = 'تم إضافة الموظف "' . e($_GET['name'] ?? '') . '" وحساب الدخول بنجاح';
    $msg_type = 'success';
}

$departments = [];
try { $departments = db()->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(); }
catch (Exception $e) {}

$statuses = ['active' => 'نشط', 'inactive' => 'موقوف', 'terminated' => 'منتهي الخدمة'];
$status_badge_map = ['active' => 'approved', 'inactive' => 'pending', 'terminated' => 'rejected'];

function emp_null(string $key): ?string {
    $v = trim($_POST[$key] ?? '');
    return $v !== '' ? $v : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $op = $_POST['op'] ?? '';
    try {
        // ============================================================
        if ($op === 'edit') {
        // ============================================================
            $id          = (int)($_POST['id'] ?? 0);
            $full_name   = trim($_POST['full_name']   ?? '');
            $national_id = trim($_POST['national_id'] ?? '');
            $gender      = $_POST['gender'] ?? 'male';

            if (!$full_name || !$national_id) throw new Exception('الاسم ورقم الهوية مطلوبان');
            if (!in_array($gender, ['male','female'], true)) throw new Exception('قيمة الجنس غير صحيحة');

            $phone          = emp_null('phone');
            $email          = emp_null('email');
            $profession     = emp_null('profession');
            $insurance      = ($_POST['insurance'] ?? '0') === '1' ? 1 : 0;
            $job_number     = emp_null('job_number');
            $dept_id        = (int)($_POST['department_id'] ?? 0) ?: null;
            $status         = in_array($_POST['status'] ?? '', ['active','inactive','terminated'])
                              ? $_POST['status'] : 'active';
            $hire_date      = emp_null('hire_date');
            $nationality    = trim($_POST['nationality'] ?? '') ?: 'سعودي';
            $religion       = trim($_POST['religion']    ?? '') ?: 'مسلم';
            $marital_status = emp_null('marital_status');
            $birth_date     = emp_null('birth_date');
            $id_type        = trim($_POST['id_type'] ?? '') ?: 'رقم الهوية';
            $id_expiry      = emp_null('id_expiry');
            $education      = emp_null('education');
            $specialization = emp_null('specialization');
            $bank_name      = emp_null('bank_name');
            $iban           = emp_null('iban');

            $stmt = db()->prepare("
                UPDATE employees SET
                    full_name=?, national_id=?, gender=?, phone=?, email=?, profession=?,
                    medical_insurance=?, job_number=?, department_id=?, status=?, hire_date=?,
                    nationality=?, religion=?, marital_status=?, birth_date=?,
                    id_type=?, id_expiry=?, education=?, specialization=?, bank_name=?, iban=?
                WHERE id=?
            ");
            $stmt->execute([
                $full_name, $national_id, $gender, $phone, $email, $profession,
                $insurance, $job_number, $dept_id, $status, $hire_date,
                $nationality, $religion, $marital_status, $birth_date,
                $id_type, $id_expiry, $education, $specialization, $bank_name, $iban,
                $id,
            ]);
            log_activity('edit_employee', 'employee', $id, "تعديل: {$full_name}");
            $msg = 'تم تحديث بيانات الموظف بنجاح'; $msg_type = 'success';

        // ============================================================
        } elseif ($op === 'delete') {
        // ============================================================
            $id = (int)($_POST['id'] ?? 0);
            $nr = db()->prepare("SELECT full_name FROM employees WHERE id=?");
            $nr->execute([$id]);
            $dname = $nr->fetchColumn() ?: "#{$id}";
            db()->prepare("DELETE FROM employees WHERE id=?")->execute([$id]);
            log_activity('delete_employee', 'employee', $id, "حذف: {$dname}");
            $msg = 'تم حذف الموظف بنجاح'; $msg_type = 'success';

        // ============================================================
        } elseif ($op === 'reset_pw') {
        // ============================================================
            $id  = (int)($_POST['id'] ?? 0);
            $new = $_POST['new_password'] ?? '';
            if (strlen($new) < 4) throw new Exception('كلمة المرور قصيرة جداً');
            $hash = password_hash($new, PASSWORD_DEFAULT);
            db()->prepare("UPDATE users SET password=? WHERE employee_id=?")->execute([$hash, $id]);
            log_activity('reset_password', 'employee', $id, "تغيير كلمة مرور #{$id}");
            $msg = 'تم تحديث كلمة المرور'; $msg_type = 'success';
        }
    } catch (Exception $e) {
        if (db()->inTransaction()) db()->rollBack();
        $msg = $e->getMessage(); $msg_type = 'error';
    }
}

// ---- فلاتر البحث ----
$q        = trim($_GET['q']     ?? '');
$f_status = $_GET['status']     ?? '';
$f_dept   = (int)($_GET['dept'] ?? 0);

$where = ['1=1']; $params = [];
if ($q !== '') {
    $where[] = "(e.full_name LIKE ? OR e.national_id LIKE ? OR u.username LIKE ? OR e.profession LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($f_status && isset($statuses[$f_status])) {
    $where[] = "e.status = ?"; $params[] = $f_status;
}
if ($f_dept > 0) {
    $where[] = "e.department_id = ?"; $params[] = $f_dept;
}
$whereSql = implode(' AND ', $where);

$stmt = db()->prepare("
    SELECT e.*, u.username, d.name AS department_name
    FROM employees e
    LEFT JOIN users u ON u.employee_id = e.id
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE {$whereSql}
    ORDER BY e.id DESC
");
$stmt->execute($params);
$employees = $stmt->fetchAll();

$auto_edit  = (int)($_GET['edit'] ?? 0);
$page_title = 'الموظفون';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head><?php include __DIR__ . '/_head.php'; ?></head>
<body>
<?php include __DIR__ . '/_admin_nav.php'; ?>

<main class="container">

    <?php if ($msg): ?>
        <div class="alert <?= e($msg_type) ?>">
            <i class="fa-solid fa-<?= $msg_type === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
            <span><?= $msg /* already escaped above */ ?></span>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         رأس الصفحة
         ============================================================ -->
    <div class="card" style="padding:18px 24px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div>
                <h2 style="margin:0;font-size:18px;font-weight:600">
                    <i class="fa-solid fa-users" style="color:var(--accent)"></i>
                    الموظفون
                </h2>
                <div style="color:var(--text-muted);font-size:13px;margin-top:4px">
                    إجمالي <?= count($employees) ?> موظف في القائمة
                </div>
            </div>
            <a href="admin_employee_add.php" class="btn primary">
                <i class="fa-solid fa-user-plus"></i> إضافة موظف جديد
            </a>
        </div>
    </div>

    <!-- ============================================================
         قائمة الموظفين
         ============================================================ -->
    <div class="card">

        <!-- فلاتر البحث -->
        <form method="get" class="filters">
            <label>بحث
                <div class="input-with-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="اسم، هوية، مستخدم…">
                </div>
            </label>
            <label>الحالة
                <select name="status">
                    <option value="">— الكل —</option>
                    <?php foreach ($statuses as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $f_status === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($departments): ?>
            <label>القسم
                <select name="dept">
                    <option value="0">— الكل —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $f_dept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <button class="btn primary"><i class="fa-solid fa-filter"></i> بحث</button>
            <?php if ($q || $f_status || $f_dept): ?>
                <a href="admin_employees.php" class="btn ghost">
                    <i class="fa-solid fa-rotate-right"></i> مسح الفلاتر
                </a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>الرقم الوظيفي</th>
                        <th>الموظف</th>
                        <th>القسم</th>
                        <th>المهنة</th>
                        <th>الحالة</th>
                        <th>تاريخ التعيين</th>
                        <th>الجوال</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $emp):
                    $sbadge = $status_badge_map[$emp['status'] ?? 'active'] ?? 'approved';
                    $slabel = $statuses[$emp['status'] ?? 'active'] ?? 'نشط';
                ?>
                    <tr>
                        <td><?= $emp['job_number'] ? e($emp['job_number']) : '<span class="muted">—</span>' ?></td>
                        <td>
                            <a href="admin_employee_view.php?id=<?= $emp['id'] ?>" style="text-decoration:none;color:inherit">
                                <div class="avatar-cell">
                                    <span class="avatar <?= ($emp['gender'] ?? '') === 'female' ? 'female' : '' ?>">
                                        <?= e(initials($emp['full_name'])) ?>
                                    </span>
                                    <div>
                                        <div class="name"><?= e($emp['full_name']) ?></div>
                                        <?php if (!empty($emp['username'])): ?>
                                            <div class="sub"><code><?= e($emp['username']) ?></code></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td>
                            <?php if ($emp['department_name'] ?? null): ?>
                                <span class="badge neutral small">
                                    <i class="fa-solid fa-building"></i> <?= e($emp['department_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $emp['profession'] ? e($emp['profession']) : '<span class="muted">—</span>' ?></td>
                        <td><span class="badge <?= $sbadge ?> small"><?= e($slabel) ?></span></td>
                        <td><?= $emp['hire_date'] ? e($emp['hire_date']) : '<span class="muted">—</span>' ?></td>
                        <td><?= $emp['phone'] ? e($emp['phone']) : '<span class="muted">—</span>' ?></td>
                        <td class="row-actions">
                            <a href="admin_employee_view.php?id=<?= $emp['id'] ?>"
                               class="btn ghost small" title="عرض الملف">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <button class="btn primary small" title="تعديل" onclick="openEdit(this)"
                                data-id="<?= $emp['id'] ?>"
                                data-name="<?= e($emp['full_name']) ?>"
                                data-nid="<?= e($emp['national_id']) ?>"
                                data-gender="<?= e($emp['gender'] ?? 'male') ?>"
                                data-phone="<?= e($emp['phone'] ?? '') ?>"
                                data-email="<?= e($emp['email'] ?? '') ?>"
                                data-profession="<?= e($emp['profession'] ?? '') ?>"
                                data-hire="<?= e($emp['hire_date'] ?? '') ?>"
                                data-job="<?= e($emp['job_number'] ?? '') ?>"
                                data-deptid="<?= (int)($emp['department_id'] ?? 0) ?>"
                                data-status="<?= e($emp['status'] ?? 'active') ?>"
                                data-ins="<?= ($emp['medical_insurance'] ?? 0) ? '1' : '0' ?>"
                                data-nationality="<?= e($emp['nationality'] ?? 'سعودي') ?>"
                                data-religion="<?= e($emp['religion'] ?? 'مسلم') ?>"
                                data-marital="<?= e($emp['marital_status'] ?? '') ?>"
                                data-birth="<?= e($emp['birth_date'] ?? '') ?>"
                                data-idtype="<?= e($emp['id_type'] ?? 'رقم الهوية') ?>"
                                data-idexpiry="<?= e($emp['id_expiry'] ?? '') ?>"
                                data-education="<?= e($emp['education'] ?? '') ?>"
                                data-specialization="<?= e($emp['specialization'] ?? '') ?>"
                                data-bank="<?= e($emp['bank_name'] ?? '') ?>"
                                data-iban="<?= e($emp['iban'] ?? '') ?>">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn ghost small" title="تغيير كلمة المرور"
                                onclick="openResetPw(<?= $emp['id'] ?>, '<?= e(addslashes($emp['full_name'])) ?>')">
                                <i class="fa-solid fa-key"></i>
                            </button>
                            <button class="btn danger small" title="حذف"
                                onclick="confirmDelete(<?= $emp['id'] ?>, '<?= e(addslashes($emp['full_name'])) ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$employees): ?>
                    <tr>
                        <td colspan="8" class="muted center" style="padding:48px 20px">
                            <i class="fa-solid fa-user-slash" style="font-size:36px;opacity:.25;display:block;margin-bottom:10px"></i>
                            <?php if ($q || $f_status || $f_dept): ?>
                                لا توجد نتائج مطابقة للفلاتر المحددة
                                <br><a href="admin_employees.php" style="font-size:13px;margin-top:8px;display:inline-block">مسح الفلاتر</a>
                            <?php else: ?>
                                لا يوجد موظفون بعد
                                <br><a href="admin_employee_add.php" style="font-size:13px;margin-top:8px;display:inline-block">إضافة أول موظف</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- ============================================================
     Modal: تعديل الموظف (scroll مُصلَح)
     ============================================================ -->
<div class="modal-backdrop" id="editModal" onclick="if(event.target===this) closeEdit()">
    <div class="modal lg">
        <h3>
            <i class="fa-solid fa-user-pen" style="color:var(--accent);margin-left:8px"></i>
            تعديل بيانات الموظف
        </h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="edit">
            <input type="hidden" name="id" id="editId">

            <div class="modal-scroll">
                <div class="form-grid">

                    <div class="form-section"><i class="fa-solid fa-id-card"></i> البيانات الأساسية</div>
                    <label>الاسم الكامل
                        <input type="text" name="full_name" id="editName" required>
                    </label>
                    <label>رقم الهوية
                        <input type="text" name="national_id" id="editNid" required>
                    </label>
                    <label>الجنس
                        <select name="gender" id="editGender">
                            <option value="male">ذكر</option>
                            <option value="female">أنثى</option>
                        </select>
                    </label>
                    <label>الرقم الوظيفي
                        <input type="text" name="job_number" id="editJob">
                    </label>
                    <label>المسمى الوظيفي / المهنة
                        <input type="text" name="profession" id="editProfession">
                    </label>
                    <label>القسم
                        <select name="department_id" id="editDeptId">
                            <option value="0">— بدون قسم —</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>تاريخ التعيين
                        <input type="date" name="hire_date" id="editHire">
                    </label>
                    <label>الحالة الوظيفية
                        <select name="status" id="editStatus">
                            <option value="active">نشط</option>
                            <option value="inactive">موقوف</option>
                            <option value="terminated">منتهي الخدمة</option>
                        </select>
                    </label>
                    <label>التأمين الطبي
                        <select name="insurance" id="editIns">
                            <option value="1">نعم</option>
                            <option value="0">لا</option>
                        </select>
                    </label>

                    <div class="form-section"><i class="fa-solid fa-person"></i> البيانات الشخصية</div>
                    <label>الجنسية
                        <input type="text" name="nationality" id="editNationality">
                    </label>
                    <label>الديانة
                        <input type="text" name="religion" id="editReligion">
                    </label>
                    <label>الحالة الاجتماعية
                        <input type="text" name="marital_status" id="editMarital">
                    </label>
                    <label>تاريخ الميلاد
                        <input type="text" name="birth_date" id="editBirth" placeholder="YYYY-MM-DD">
                    </label>

                    <div class="form-section"><i class="fa-solid fa-passport"></i> بيانات الهوية</div>
                    <label>نوع الهوية
                        <input type="text" name="id_type" id="editIdType">
                    </label>
                    <label>تاريخ انتهاء الهوية
                        <input type="text" name="id_expiry" id="editIdExpiry" placeholder="YYYY-MM-DD">
                    </label>

                    <div class="form-section"><i class="fa-solid fa-address-book"></i> بيانات التواصل</div>
                    <label>رقم الجوال
                        <input type="tel" name="phone" id="editPhone">
                    </label>
                    <label>البريد الإلكتروني
                        <input type="email" name="email" id="editEmail">
                    </label>

                    <div class="form-section"><i class="fa-solid fa-graduation-cap"></i> المؤهلات</div>
                    <label>المؤهل العلمي
                        <input type="text" name="education" id="editEducation">
                    </label>
                    <label>التخصص
                        <input type="text" name="specialization" id="editSpecialization">
                    </label>

                    <div class="form-section"><i class="fa-solid fa-landmark"></i> البيانات البنكية</div>
                    <label>اسم البنك
                        <input type="text" name="bank_name" id="editBank">
                    </label>
                    <label>رقم الآيبان
                        <input type="text" name="iban" id="editIban">
                    </label>

                </div><!-- /form-grid -->
            </div><!-- /modal-scroll -->

            <div class="modal-actions">
                <button type="button" class="btn ghost" onclick="closeEdit()">
                    <i class="fa-solid fa-xmark"></i> إلغاء
                </button>
                <button type="submit" class="btn primary">
                    <i class="fa-solid fa-save"></i> حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: تأكيد الحذف -->
<div class="modal-backdrop" id="deleteModal" onclick="if(event.target===this) closeDelete()">
    <div class="modal">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3>تأكيد حذف الموظف</h3>
        <p>هل أنت متأكد من حذف <strong id="deleteName"></strong>؟<br>
           سيتم حذف حساب الدخول وجميع سجلات الحضور بشكل دائم.</p>
        <form method="post" class="actions">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <button type="button" class="btn ghost" onclick="closeDelete()">إلغاء</button>
            <button type="submit" class="btn danger">
                <i class="fa-solid fa-trash"></i> نعم، احذف
            </button>
        </form>
    </div>
</div>

<!-- Modal: تغيير كلمة المرور -->
<div class="modal-backdrop" id="resetPwModal" onclick="if(event.target===this) closeResetPw()">
    <div class="modal">
        <div class="modal-icon" style="background:var(--warning-soft);color:var(--warning)">
            <i class="fa-solid fa-key"></i>
        </div>
        <h3>إعادة تعيين كلمة المرور</h3>
        <p id="resetPwEmpName" style="font-weight:600;color:var(--text);margin-bottom:16px"></p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="reset_pw">
            <input type="hidden" name="id" id="resetPwId">
            <label style="text-align:right;display:block;margin-bottom:16px">كلمة المرور الجديدة
                <input type="password" name="new_password" id="resetPwInput"
                       minlength="4" required placeholder="٤ أحرف فأكثر">
            </label>
            <div class="actions">
                <button type="button" class="btn ghost" onclick="closeResetPw()">إلغاء</button>
                <button type="submit" class="btn warn">
                    <i class="fa-solid fa-key"></i> تعيين
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(btn) {
    const d = btn.dataset;
    document.getElementById('editId').value             = d.id;
    document.getElementById('editName').value           = d.name;
    document.getElementById('editNid').value            = d.nid;
    document.getElementById('editGender').value         = d.gender;
    document.getElementById('editPhone').value          = d.phone;
    document.getElementById('editEmail').value          = d.email;
    document.getElementById('editProfession').value     = d.profession;
    document.getElementById('editHire').value           = d.hire;
    document.getElementById('editJob').value            = d.job;
    document.getElementById('editDeptId').value         = d.deptid;
    document.getElementById('editStatus').value         = d.status;
    document.getElementById('editIns').value            = d.ins;
    document.getElementById('editNationality').value    = d.nationality;
    document.getElementById('editReligion').value       = d.religion;
    document.getElementById('editMarital').value        = d.marital;
    document.getElementById('editBirth').value          = d.birth;
    document.getElementById('editIdType').value         = d.idtype;
    document.getElementById('editIdExpiry').value       = d.idexpiry;
    document.getElementById('editEducation').value      = d.education;
    document.getElementById('editSpecialization').value = d.specialization;
    document.getElementById('editBank').value           = d.bank;
    document.getElementById('editIban').value           = d.iban;
    // إعادة scroll لأعلى المحتوى
    const sc = document.querySelector('#editModal .modal-scroll');
    if (sc) sc.scrollTop = 0;
    document.getElementById('editModal').classList.add('active');
}
function closeEdit()   { document.getElementById('editModal').classList.remove('active'); }

function confirmDelete(id, name) {
    document.getElementById('deleteId').value       = id;
    document.getElementById('deleteName').textContent = name;
    document.getElementById('deleteModal').classList.add('active');
}
function closeDelete() { document.getElementById('deleteModal').classList.remove('active'); }

function openResetPw(id, name) {
    document.getElementById('resetPwId').value          = id;
    document.getElementById('resetPwEmpName').textContent = name;
    document.getElementById('resetPwInput').value       = '';
    document.getElementById('resetPwModal').classList.add('active');
    setTimeout(() => document.getElementById('resetPwInput').focus(), 100);
}
function closeResetPw() { document.getElementById('resetPwModal').classList.remove('active'); }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeEdit(); closeDelete(); closeResetPw(); }
});

<?php if ($auto_edit > 0): ?>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.querySelector('button[data-id="<?= (int)$auto_edit ?>"][data-name]');
    if (btn) openEdit(btn);
});
<?php endif; ?>
</script>
</body>
</html>
