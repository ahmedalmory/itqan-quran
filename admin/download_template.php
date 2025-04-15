<?php
// بدء جلسة الجلسة
session_start();

// التحقق من تسجيل الدخول
require_once '../config/auth.php';
requireRole(['super_admin', 'department_admin']);

// تعيين نوع المحتوى
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="student_import_template.csv"');

// إنشاء مخرج CSV
$output = fopen('php://output', 'w');

// إضافة BOM للتعامل مع UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// كتابة رؤوس الأعمدة
fputcsv($output, [
    'الاسم',
    'البريد الالكتروني',
    'رقم الهاتف',
    'تاريخ الميلاد',
    'الجنس',
    'الجنسية',
    'وقت الحلقة'
]);

// إضافة بعض الأمثلة
fputcsv($output, [
    'محمد أحمد',
    'mohammed.ahmed@example.com',
    '0501234567',
    '2010-01-01',
    'ذكر',
    'سعودي',
    'بعد العصر'
]);

fputcsv($output, [
    'نورة محمد',
    'noura.mohammed@example.com',
    '0507654321',
    '2011-05-15',
    'أنثى',
    'سعودي',
    '16:00-18:00'
]);

fputcsv($output, [
    'خالد عبدالله',
    'khalid.abdullah@example.com',
    '0509876543',
    '2009-10-20',
    'ذكر',
    'مصري',
    'بعد المغرب'
]);

// إغلاق الملف
fclose($output);
exit;
?>
