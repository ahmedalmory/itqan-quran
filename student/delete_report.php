<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

// Get user ID and date
$user_id = $_SESSION['user_id'];
$date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);

if (!$date) {
    exit('error');
}

try {
    $pdo->beginTransaction();

    // Delete report
    $stmt = $pdo->prepare("DELETE FROM daily_reports WHERE student_id = ? AND report_date = ?");
    $stmt->execute([$user_id, $date]);

    $pdo->commit();
    echo 'success';
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo 'error';
}
