<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// التحقق من تسجيل الدخول وصلاحية المستخدم
if (!isLoggedIn() || !hasRole('admin') && !hasRole('super_admin')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'غير مصرح لك بالوصول']);
    exit();
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'معرف القسم مطلوب']);
    exit();
}

$department_id = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT d.*, 
           GROUP_CONCAT(dc.country_id) as selected_countries
    FROM departments d
    LEFT JOIN department_countries dc ON d.id = dc.department_id
    WHERE d.id = ?
    GROUP BY d.id
");

$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
$department = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($department); 