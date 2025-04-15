<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// التأكد من أن المستخدم مدير النظام
requireRole('department_admin');

// الحصول على معرف الإدارة والحلقة المستثناة من الرابط
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$exclude_circle = isset($_GET['exclude_circle']) ? (int)$_GET['exclude_circle'] : 0;

// التحقق من أن الإدارة تابعة للمستخدم الحالي
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM department_admins 
    WHERE department_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $department_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['count'] == 0) {
    // إذا لم تكن الإدارة تابعة للمستخدم، نرجع مصفوفة فارغة
    echo json_encode([]);
    exit;
}

// الحصول على الحلقات في الإدارة المحددة
$stmt = $conn->prepare("
    SELECT id, name 
    FROM study_circles 
    WHERE department_id = ? AND id != ?
    ORDER BY name
");
$stmt->bind_param("ii", $department_id, $exclude_circle);
$stmt->execute();
$circles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// إرجاع النتائج بصيغة JSON
header('Content-Type: application/json');
echo json_encode($circles);
