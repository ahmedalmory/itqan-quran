<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// التأكد من أن المستخدم مدير النظام
requireRole(['super_admin', 'department_admin']);

header('Content-Type: application/json');

if (!isset($_GET['department_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف الإدارة مطلوب']);
    exit;
}

$department_id = (int)$_GET['department_id'];
$exclude_circle = isset($_GET['exclude_circle']) ? (int)$_GET['exclude_circle'] : 0;

try {
    // التحقق من صلاحية الوصول للإدارة إذا كان مدير إدارة
    if (!isRole('super_admin')) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM department_admins 
            WHERE department_id = ? AND admin_id = ?
        ");
        $stmt->bind_param("ii", $department_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            http_response_code(403);
            echo json_encode(['error' => 'غير مصرح بالوصول لهذه الإدارة']);
            exit;
        }
    }
    
    // الحصول على الحلقات
    $stmt = $conn->prepare("
        SELECT id, name 
        FROM study_circles 
        WHERE department_id = ? 
        AND id != ?
        ORDER BY name
    ");
    $stmt->bind_param("ii", $department_id, $exclude_circle);
    $stmt->execute();
    $circles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode($circles);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في تحميل الحلقات']);
} 