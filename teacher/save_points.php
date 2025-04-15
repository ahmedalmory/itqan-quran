<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => __('invalid_request')]);
    exit;
}

// Validate circle access
$stmt = $pdo->prepare("SELECT * FROM study_circles WHERE id = ? AND teacher_id = ?");
$stmt->execute([$data['circle_id'], $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => __('unauthorized')]);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($data['action'] === 'reset') {
        // Reset all points for the circle
        $stmt = $pdo->prepare("
            INSERT INTO points_history (student_id, circle_id, points, action_type, created_by)
            SELECT student_id, circle_id, -total_points, 'reset', ?
            FROM student_points
            WHERE circle_id = ? AND total_points > 0
        ");
        $stmt->execute([$_SESSION['user_id'], $data['circle_id']]);

        $stmt = $pdo->prepare("
            UPDATE student_points 
            SET total_points = 0 
            WHERE circle_id = ?
        ");
        $stmt->execute([$data['circle_id']]);
    } else {
        // Validate student belongs to circle
        $stmt = $pdo->prepare("
            SELECT 1 FROM circle_students 
            WHERE student_id = ? AND circle_id = ?
        ");
        $stmt->execute([$data['student_id'], $data['circle_id']]);
        if (!$stmt->fetch()) {
            throw new Exception(__('student_not_in_circle'));
        }

        // Get current points
        $stmt = $pdo->prepare("
            SELECT total_points 
            FROM student_points 
            WHERE student_id = ? AND circle_id = ?
        ");
        $stmt->execute([$data['student_id'], $data['circle_id']]);
        $current = $stmt->fetch();

        $points = (int)$data['points'];
        if ($data['action'] === 'subtract') {
            $points = -$points;
        }

        if ($current) {
            // Update existing record
            $new_total = max(0, $current['total_points'] + $points);
            $stmt = $pdo->prepare("
                UPDATE student_points 
                SET total_points = ? 
                WHERE student_id = ? AND circle_id = ?
            ");
            $stmt->execute([$new_total, $data['student_id'], $data['circle_id']]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO student_points (student_id, circle_id, total_points)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$data['student_id'], $data['circle_id'], max(0, $points)]);
        }

        // Record in history
        $stmt = $pdo->prepare("
            INSERT INTO points_history 
            (student_id, circle_id, points, action_type, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['student_id'],
            $data['circle_id'],
            $points,
            $data['action'],
            $_SESSION['user_id']
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
