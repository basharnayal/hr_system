-- =============================================================
-- Migration 006: جدول الأقسام + حالة الموظف
-- التشغيل: phpMyAdmin → اختر almaxzuh_hr → SQL → شغّل
-- =============================================================

-- جدول الأقسام
CREATE TABLE IF NOT EXISTS `departments` (
    `id`          INT          AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- أقسام افتراضية
INSERT IGNORE INTO `departments` (`name`) VALUES
    ('الإدارة العامة'),
    ('الموارد البشرية'),
    ('المالية والمحاسبة'),
    ('تقنية المعلومات'),
    ('العمليات والخدمات');

-- إضافة حقلَي القسم والحالة للموظفين
-- ملاحظة: إن أعطى خطأ "Duplicate column" فالأعمدة موجودة بالفعل، تجاهل
ALTER TABLE `employees`
    ADD COLUMN `department_id` INT  NULL              AFTER `job_number`,
    ADD COLUMN `status`        ENUM('active','inactive','terminated')
                               NOT NULL DEFAULT 'active' AFTER `medical_insurance`;

-- ربط القسم بجدول الأقسام
ALTER TABLE `employees`
    ADD CONSTRAINT `fk_emp_dept`
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
    ON DELETE SET NULL;
