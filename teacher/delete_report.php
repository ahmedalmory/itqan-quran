<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

// Get report ID
$report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);

if (!$report_id) {
    http_response_code(400);
    exit(__('invalid_report'));
}

try {
    // Delete report
    $stmt = $pdo->prepare("DELETE FROM daily_reports WHERE id = ?");
    $stmt->execute([$report_id]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        exit(__('report_deleted'));
    } else {
        http_response_code(404);
        exit(__('report_not_found'));
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit(__('delete_error'));
}
