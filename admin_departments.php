<?php
require_once __DIR__ . '/auth.php';
require_admin();

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $op = $_POST['op'] ?? '';
    try {
        if ($op === 'add') {
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '') ?: null;
            if ($name === '') throw new Exception('اسم القسم مطلوب');
            $stmt = db()->prepare("INSERT INTO departments (name, description) VALUES (?,?)");
            $stmt->execute([$name, $desc]);
            $new_id = (int)db()->lastInsertId();
            log_activity('add_department', 'department', $new_id, "إضافة قسم: {$name}");
            $msg = 'تم إضافة القسم بنجاح'; $msg_type = 'success';
        }
        elseif ($op === 'edit') {
            $id   = (int)($_POST['id']   ?? 0);
            $name = trim($_POST['name']  ?? '');
            $desc = trim($_POST['description'] ?? '') ?: null;
            if ($name === '') throw new Exception('اسم القسم مطلوب');
            $stmt = db()->prepare("UPDATE departments SET name=?, description=? WHERE id=?");
            $stmt->execute([$name, $desc, $id]);
            log_activity('edit_department', 'department', $id, "تعديل قسم: {$name}");
            $msg = 'تم تحديث القسم بنجاح'; $msg_type = 'success';
        }
        elseif ($op === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $cnt = db()->prepare("SELECT COUNT(*) FROM employees WHERE department_id=?");
            $cnt->execute([$id]);
            if ((int)$cnt->fetchColumn() > 0) {
                throw new Exception('لا يمكن حذف قسم يحتوي على موظفين. قم بنقل الموظفين لقسم آخر أولاً.');
            }
            $nr = db()->prepare("SELECT name FROM departments WHERE id=?");
            $nr->execute([$id]); $dname = $nr->fetchColumn() ?: "#{$id}";
            db()->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
            log_activity('delete_department', 'department', $id, "حذف قسم: {$dname}");
            $msg = 'تم حذف القسم'; $msg_type = 'success';
        }
    } catch (Exception $e) {
        $msg = $e->getMessage(); $msg_type = 'error';
    }
}

$departments = db()->query("
    SELECT d.*, COUNT(e.id) AS emp_count
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id
    GROUP BY d.id
    ORDER BY d.name
")->fetchAll();

$total_assigned = 0;
try {
    $total_assigned = (int)db()->query(
        "SELECT COUNT(*) FROM employees WHERE department_id IS NOT NULL"
    )->fetchColumn();
} catch (Exception $e) {}

$page_title = 'الأقسام';
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
            <span><?= e($msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- إحصائيات -->
    <div class="stats">
        <div class="stat-card">
            <div class="icon blue"><i class="fa-solid fa-building"></i></div>
            <div class="body">
                <div class="stat-label">عدد الأقسام</div>
                <div class="stat-value"><?= count($departments) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon green"><i class="fa-solid fa-users"></i></div>
            <div class="body">
                <div class="stat-label">موظفون في أقسام</div>
                <div class="stat-value"><?= $total_assigned ?></div>
            </div>
        </div>
    </div>

    <!-- إضافة قسم -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-building-circle-arrow-right"></i> إضافة قسم جديد</h2>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="add">
            <label>اسم القسم <input type="text" name="name" required placeholder="مثال: الموارد البشرية"></label>
            <label>الوصف (اختياري) <input type="text" name="description" placeholder="وصف مختصر"></label>
            <div class="form-actions">
                <button type="submit" class="btn primary">
                    <i class="fa-solid fa-plus"></i> إضافة القسم
                </button>
            </div>
        </form>
    </div>

    <!-- قائمة الأقسام -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-sitemap"></i> الأقسام (<?= count($departments) ?>)</h2>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم القسم</th>
                        <th>الوصف</th>
                        <th>عدد الموظفين</th>
                        <th>تاريخ الإنشاء</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td><?= $dept['id'] ?></td>
                        <td><strong><?= e($dept['name']) ?></strong></td>
                        <td><?= $dept['description'] ? e($dept['description']) : '<span class="muted">—</span>' ?></td>
                        <td>
                            <a href="admin_employees.php?dept=<?= $dept['id'] ?>" class="badge neutral small" style="text-decoration:none">
                                <i class="fa-solid fa-users"></i> <?= $dept['emp_count'] ?> موظف
                            </a>
                        </td>
                        <td><span class="muted" style="font-size:13px"><?= e(date('Y-m-d', strtotime($dept['created_at']))) ?></span></td>
                        <td class="row-actions">
                            <button class="btn primary small" onclick="openEdit(this)"
                                data-id="<?= $dept['id'] ?>"
                                data-name="<?= e($dept['name']) ?>"
                                data-desc="<?= e($dept['description'] ?? '') ?>"
                                title="تعديل">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn danger small" onclick="confirmDelete(<?= $dept['id'] ?>, '<?= e(addslashes($dept['name'])) ?>', <?= (int)$dept['emp_count'] ?>)"
                                title="حذف">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$departments): ?>
                    <tr>
                        <td colspan="6" class="muted center" style="padding:40px">
                            <i class="fa-solid fa-building" style="font-size:32px;opacity:.3"></i>
                            <div style="margin-top:8px">لا توجد أقسام بعد. أضف قسماً من الأعلى.</div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal: تعديل القسم -->
<div class="modal-backdrop" id="editModal" onclick="if(event.target===this) closeEdit()">
    <div class="modal">
        <h3 style="text-align:right;border-bottom:1px solid var(--border);padding-bottom:10px;margin-bottom:16px">
            <i class="fa-solid fa-pen" style="color:var(--accent);margin-left:8px"></i>
            تعديل القسم
        </h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="edit">
            <input type="hidden" name="id" id="editId">
            <label style="text-align:right">اسم القسم
                <input type="text" name="name" id="editName" required>
            </label>
            <label style="text-align:right">الوصف (اختياري)
                <input type="text" name="description" id="editDesc">
            </label>
            <div class="modal-actions">
                <button type="button" class="btn ghost" onclick="closeEdit()">إلغاء</button>
                <button type="submit" class="btn primary">
                    <i class="fa-solid fa-save"></i> حفظ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: تأكيد الحذف -->
<div class="modal-backdrop" id="deleteModal" onclick="if(event.target===this) closeDelete()">
    <div class="modal">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3>حذف القسم</h3>
        <p id="deleteMsg"></p>
        <form method="post" id="deleteForm" class="actions">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <button type="button" class="btn ghost" onclick="closeDelete()">إلغاء</button>
            <button type="submit" id="deleteBtn" class="btn danger">
                <i class="fa-solid fa-trash"></i> حذف
            </button>
        </form>
    </div>
</div>

<script>
function openEdit(btn) {
    document.getElementById('editId').value   = btn.dataset.id;
    document.getElementById('editName').value = btn.dataset.name;
    document.getElementById('editDesc').value = btn.dataset.desc;
    document.getElementById('editModal').classList.add('active');
}
function closeEdit() { document.getElementById('editModal').classList.remove('active'); }

function confirmDelete(id, name, empCount) {
    document.getElementById('deleteId').value = id;
    const btn = document.getElementById('deleteBtn');
    if (empCount > 0) {
        document.getElementById('deleteMsg').innerHTML =
            'لا يمكن حذف <strong>' + name + '</strong> لأنه يحتوي على ' + empCount + ' موظف.<br>يرجى نقل الموظفين لقسم آخر أولاً.';
        btn.disabled = true;
    } else {
        document.getElementById('deleteMsg').innerHTML =
            'هل أنت متأكد من حذف قسم <strong>' + name + '</strong>؟';
        btn.disabled = false;
    }
    document.getElementById('deleteModal').classList.add('active');
}
function closeDelete() { document.getElementById('deleteModal').classList.remove('active'); }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeEdit(); closeDelete(); }
});
</script>
</body>
</html>
