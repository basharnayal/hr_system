<?php
// ترويسة HTML مشتركة - الخطوط والأيقونات والـCSS
// $page_title يجب أن يكون معرّفاً قبل include هذا الملف
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#DB6B3A">
<title><?= isset($page_title) ? e($page_title) . ' - ' . e(company_name()) : e(company_name()) ?></title>

<!-- خط Tajawal العربي -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

<!-- Font Awesome للأيقونات -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- CSS النظام -->
<link rel="stylesheet" href="style.css?v=20260625">

