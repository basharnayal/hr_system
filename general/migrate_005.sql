-- =============================================================
-- Migration 005: سجل النشاط + إعدادات أوقات الدوام
-- التشغيل: phpMyAdmin → اختر almaxzuh_hr → SQL → الصق وشغّل
-- =============================================================

-- جدول سجل نشاط المدير (Audit Log)
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`          INT          AUTO_INCREMENT PRIMARY KEY,
    `admin_id`    INT          DEFAULT NULL,
    `action`      VARCHAR(80)  NOT NULL,
    `entity_type` VARCHAR(40)  DEFAULT NULL,
    `entity_id`   INT          DEFAULT NULL,
    `details`     TEXT         DEFAULT NULL,
    `ip_address`  VARCHAR(45)  DEFAULT NULL,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_created` (`created_at`),
    KEY `idx_admin`   (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة إعدادات أوقات الدوام لجدول settings
-- ملاحظة: إن أعطى خطأ "Duplicate column" فمعناه هي موجودة بالفعل، تجاهل الخطأ
ALTER TABLE `settings`
    ADD COLUMN `work_start` TIME NOT NULL DEFAULT '08:00:00',
    ADD COLUMN `work_end`   TIME NOT NULL DEFAULT '17:00:00';
