<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول وصلاحية المستخدم
if (!isLoggedIn() || !hasRole('admin') && !hasRole('super_admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بالوصول']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['department_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف القسم مطلوب']);
    exit();
}

$department_id = (int)$_GET['department_id'];

try {
    // جلب المديرين الحاليين للقسم
    $stmt = $conn->prepare("
        SELECT u.id, u.name 
        FROM users u 
        JOIN department_admins da ON u.id = da.user_id 
        WHERE da.department_id = ?
        ORDER BY u.name
    ");
    
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admins = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode($admins);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء جلب قائمة المديرين']);
}
