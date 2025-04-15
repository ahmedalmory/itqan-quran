<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$user_id = $_SESSION['user_id'];
$date = $_POST['date'] ?? '';

// Validate date
if (!$date || !strtotime($date)) {
    http_response_code(400);
    exit(__('invalid_date'));
}

// Check if date is in future
if (strtotime($date) > time()) {
    http_response_code(400);
    exit(__('future_date'));
}

// Check if report already exists
$stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE student_id = ? AND report_date = ?");
$stmt->execute([$user_id, $date]);
if ($stmt->fetch()) {
    http_response_code(400);
    exit(__('report_exists'));
}

// Validate input
$memorization_parts = filter_input(INPUT_POST, 'memorization_parts', FILTER_VALIDATE_FLOAT);
$revision_parts = filter_input(INPUT_POST, 'revision_parts', FILTER_VALIDATE_FLOAT);

// Custom validation for parts (must be whole number, 0.25, or 0.5)
function validateParts($value) {
    if ($value === false || $value < 0 || $value > 20) {
        return false;
    }
    
    $fraction = $value - floor($value);
    return $fraction === 0.0 || $fraction === 0.25 || $fraction === 0.5;
}

if (!validateParts($memorization_parts) || !validateParts($revision_parts)) {
    http_response_code(400);
    die('عدد الأوجه غير صحيح. يجب أن يكون رقماً صحيحاً أو يحتوي على ربع أو نصف فقط');
}

// Validate that at least one part is greater than 0
if ($memorization_parts === 0 && $revision_parts === 0) {
    http_response_code(400);
    die('يجب إدخال قيمة أكبر من الصفر في الحفظ أو المراجعة');
}

// Validate grade
$grade = filter_input(INPUT_POST, 'grade', FILTER_VALIDATE_INT);
if ($grade === false || $grade < 0 || $grade > 100) {
    http_response_code(400);
    exit(__('invalid_grade'));
}

// Validate surahs and verses
$memorization_from_surah = filter_input(INPUT_POST, 'memorization_from_surah', FILTER_VALIDATE_INT);
$memorization_to_surah = filter_input(INPUT_POST, 'memorization_to_surah', FILTER_VALIDATE_INT);
$memorization_from_verse = filter_input(INPUT_POST, 'memorization_from_verse', FILTER_VALIDATE_INT);
$memorization_to_verse = filter_input(INPUT_POST, 'memorization_to_verse', FILTER_VALIDATE_INT);

if (!$memorization_from_surah || !$memorization_to_surah || !$memorization_from_verse || !$memorization_to_verse) {
    http_response_code(400);
    exit(__('invalid_verses'));
}

// Get notes
$notes = trim($_POST['notes'] ?? '');

try {
    $pdo->beginTransaction();

    // Insert report
    $stmt = $pdo->prepare("
        INSERT INTO daily_reports (
            student_id, report_date, memorization_parts, revision_parts,
            memorization_from_surah, memorization_to_surah, memorization_from_verse,
            memorization_to_verse, grade, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $user_id, $date, $memorization_parts, $revision_parts,
        $memorization_from_surah, $memorization_to_surah, $memorization_from_verse,
        $memorization_to_verse, intval($grade), $notes
    ]);

    $pdo->commit();
    $success = true;
} catch (Exception $e) {
    $pdo->rollBack();
    $success = false;
}

if ($success) {
    // Redirect to index page after successful save
    header('Location: index.php');
    exit();
} else {
    http_response_code(500);
    exit(__('error_saving_report'));
}
?>
