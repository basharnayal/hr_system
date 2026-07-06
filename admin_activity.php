<?php
require_once __DIR__ . '/auth.php';
require_admin();

$from  = $_GET['from']  ?? date('Y-m-d', strtotime('-30 days'));
$to    = $_GET['to']    ?? date('Y-m-d');

// تسميات الإجراءات
$action_labels = [
    'add_employee'     => ['label' => 'إضافة موظف',         'icon' => 'fa-user-plus',         'color' => 'var(--success)'],
    'edit_employee'    => ['label' => 'تعديل موظف',          'icon' => 'fa-user-pen',           'color' => 'var(--info)'],
    'delete_employee'  => ['label' => 'حذف موظف',            'icon' => 'fa-user-minus',         'color' => 'var(--danger)'],
    'reset_password'   => ['label' => 'تغيير كلمة مرور',     'icon' => 'fa-key',                'color' => 'var(--warning)'],
    'approve_request'  => ['label' => 'موافقة على طلب',      'icon' => 'fa-circle-check',       'color' => 'var(--success)'],
    'reject_request'   => ['label' => 'رفض طلب',             'icon' => 'fa-circle-xmark',       'color' => 'var(--danger)'],
    'reset_request'    => ['label' => 'إعادة طلب للمراجعة',  'icon' => 'fa-rotate-left',        'color' => 'var(--text-muted)'],
    'update_request'   => ['label' => 'تحديث طلب',           'icon' => 'fa-file-pen',           'color' => 'var(--info)'],
    'update_settings'  => ['label' => 'تحديث الإعدادات',     'icon' => 'fa-gear',               'color' => 'var(--accent)'],
];

$rows = [];
$total = 0;
try {
    $stmt = db()->prepare("
        SELECT al.*, u.username AS admin_username
        FROM activity_log al
        LEFT JOIN users u ON u.id = al.admin_id
        WHERE DATE(al.created_at) BETWEEN ? AND ?
        ORDER BY al.created_at DESC
        LIMIT 500
    ");
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    $total = count($rows);
} catch (Exception $e) {
    $db_error = 'جدول سجل النشاط غير موجود. يرجى تشغيل migrate_005.sql أولاً.';
}

$page_title = 'سجل النشاط';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/_admin_nav.php'; ?>

<main class="container">

    <?php if (!empty($db_error)): ?>
        <div class="alert warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= e($db_error) ?></span>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-list-check"></i> سجل نشاط المديرين (<?= $total ?>)</h2>
        </div>

        <form method="get" class="filters">
            <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
            <button class="btn primary"><i class="fa-solid fa-filter"></i> عرض</button>
            <a class="btn ghost" href="admin_activity.php"><i class="fa-solid fa-rotate-right"></i> إعادة تعيين</a>
        </form>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>التاريخ والوقت</th>
                        <th>المدير</th>
                        <th>الإجراء</th>
                        <th>التفاصيل</th>
                        <th>رقم IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $meta  = $action_labels[$r['action']] ?? ['label' => $r['action'], 'icon' => 'fa-circle-dot', 'color' => 'var(--text-muted)'];
                ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:13px">
                            <?= e(date('Y-m-d', strtotime($r['created_at']))) ?>
                            <br><span class="muted"><?= e(date('H:i:s', strtotime($r['created_at']))) ?></span>
                        </td>
                        <td>
                            <?php if ($r['admin_username']): ?>
                                <div class="avatar-cell">
                                    <span class="avatar" style="width:28px;height:28px;font-size:11px"><?= e(initials($r['admin_username'])) ?></span>
                                    <code><?= e($r['admin_username']) ?></code>
                                </div>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <i class="fa-solid <?= e($meta['icon']) ?>" style="color:<?= $meta['color'] ?>;margin-left:6px"></i>
                            <?= e($meta['label']) ?>
                        </td>
                        <td style="font-size:13px;color:var(--text-muted)">
                            <?= $r['details'] ? e($r['details']) : '—' ?>
                        </td>
                        <td><code style="font-size:12px"><?= e($r['ip_address'] ?? '—') ?></code></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows && empty($db_error)): ?>
                    <tr>
                        <td colspan="5" class="muted center" style="padding:40px">
                            <i class="fa-solid fa-clipboard-list" style="font-size:32px;opacity:.3"></i>
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
