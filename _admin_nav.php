<?php
$_u = current_user();
$_self = basename($_SERVER['PHP_SELF']);
function _active($f) { global $_self; return $_self === $f ? 'active' : ''; }

$_pending_requests = 0;
try {
    $_pending_requests = (int)db()->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {}

$_initials = initials($_u['username'] ?? 'A');
?>
<header class="topbar">

    <!-- الصف الأول: الشعار + المستخدم -->
    <div class="topbar-main">
        <div class="brand">
            <i class="fa-solid fa-briefcase"></i>
            <span><?= e(company_name()) ?> · لوحة المدير</span>
        </div>

        <button class="nav-toggle" type="button"
                onclick="document.getElementById('mainNav').classList.toggle('open')"
                aria-label="القائمة">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="user-area">
            <span class="user-name">
                <span class="avatar"><?= e($_initials) ?></span>
                <span class="username-text"><?= e($_u['username']) ?></span>
            </span>
            <a href="logout.php" class="btn ghost small" title="خروج">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span class="logout-text">خروج</span>
            </a>
        </div>
    </div>

    <!-- الصف الثاني: روابط التنقل -->
    <nav class="nav" id="mainNav">
        <a href="admin.php" class="<?= _active('admin.php') ?>">
            <i class="fa-solid fa-gauge-high"></i> الرئيسية
        </a>
        <a href="admin_employees.php" class="<?= _active('admin_employees.php') === 'active' || _active('admin_employee_add.php') === 'active' || _active('admin_employee_view.php') === 'active' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> الموظفون
        </a>
        <a href="admin_departments.php" class="<?= _active('admin_departments.php') ?>">
            <i class="fa-solid fa-sitemap"></i> الأقسام
        </a>
        <a href="admin_attendance.php" class="<?= _active('admin_attendance.php') ?>">
            <i class="fa-solid fa-clock"></i> الحضور
        </a>
        <a href="admin_requests.php" class="<?= _active('admin_requests.php') ?>">
            <i class="fa-solid fa-file-lines"></i> الطلبات
            <?php if ($_pending_requests > 0): ?>
                <span class="nav-badge"><?= $_pending_requests ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_reports.php" class="<?= _active('admin_reports.php') ?>">
            <i class="fa-solid fa-chart-bar"></i> التقارير
        </a>
        <a href="admin_activity.php" class="<?= _active('admin_activity.php') ?>">
            <i class="fa-solid fa-list-check"></i> سجل النشاط
        </a>
        <a href="admin_settings.php" class="<?= _active('admin_settings.php') ?>">
            <i class="fa-solid fa-gear"></i> الإعدادات
        </a>
    </nav>

</header>
