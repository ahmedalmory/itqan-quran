<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

if (isset($_POST['bulk_submit'])) {
    // Handle bulk report submission
    $circle_id = filter_input(INPUT_POST, 'circle_id', FILTER_VALIDATE_INT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $memorization_parts = filter_input(INPUT_POST, 'memorization_parts', FILTER_VALIDATE_FLOAT);
    $revision_parts = filter_input(INPUT_POST, 'revision_parts', FILTER_VALIDATE_FLOAT);
    $memorization_from_surah = filter_input(INPUT_POST, 'memorization_from_surah', FILTER_VALIDATE_INT);
    $memorization_from_verse = filter_input(INPUT_POST, 'memorization_from_verse', FILTER_VALIDATE_INT);
    $memorization_to_surah = filter_input(INPUT_POST, 'memorization_to_surah', FILTER_VALIDATE_INT);
    $memorization_to_verse = filter_input(INPUT_POST, 'memorization_to_verse', FILTER_VALIDATE_INT);
    $grade = filter_input(INPUT_POST, 'grade', FILTER_VALIDATE_INT);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (!$circle_id || !$date || ($memorization_parts === false && $revision_parts === false)) {
        http_response_code(400);
        exit(__('enter_parts_error'));
    }

    // Validate parts values
    if ($memorization_parts < 0 || $memorization_parts > 20 || 
        $revision_parts < 0 || $revision_parts > 20) {
        http_response_code(400);
        exit(__('invalid_parts'));
    }

    // Only validate memorization fields if there are memorization parts
    if ($memorization_parts > 0) {
        if (!$memorization_from_surah || !$memorization_from_verse || 
            !$memorization_to_surah || !$memorization_to_verse) {
            http_response_code(400);
            exit(__('fill_all_fields'));
        }
    }

    // Validate grade if provided
    if ($grade === false || $grade < 0 || $grade > 100) {
        http_response_code(400);
        exit(__('invalid_grade'));
    }

    try {
        // Get all students in the circle who don't have a report for this date
        $stmt = $pdo->prepare("
            SELECT cs.student_id 
            FROM circle_students cs
            LEFT JOIN daily_reports dr ON cs.student_id = dr.student_id AND dr.report_date = ?
            WHERE cs.circle_id = ? AND dr.id IS NULL
        ");
        $stmt->execute([$date, $circle_id]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($students)) {
            http_response_code(400);
            exit(__('all_students_have_reports'));
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Insert new reports for students without reports
        $sql = "INSERT INTO daily_reports 
                (student_id, report_date, memorization_parts, revision_parts,
                memorization_from_surah, memorization_from_verse,
                memorization_to_surah, memorization_to_verse,
                grade, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($students as $student_id) {
            $stmt->execute([
                $student_id,
                $date,
                $memorization_parts,
                $revision_parts,
                $memorization_parts > 0 ? $memorization_from_surah : null,
                $memorization_parts > 0 ? $memorization_from_verse : null,
                $memorization_parts > 0 ? $memorization_to_surah : null,
                $memorization_parts > 0 ? $memorization_to_verse : null,
                $grade,
                $notes
            ]);
        }

        // Commit transaction
        $pdo->commit();
        http_response_code(200);
        exit(__('bulk_report_success'));
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log($e->getMessage());
        http_response_code(500);
        exit(__('save_error'));
    }
} else {
    // Handle individual report submission
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $memorization_parts = filter_input(INPUT_POST, 'memorization_parts', FILTER_VALIDATE_FLOAT);
    $revision_parts = filter_input(INPUT_POST, 'revision_parts', FILTER_VALIDATE_FLOAT);
    $memorization_from_surah = filter_input(INPUT_POST, 'memorization_from_surah', FILTER_VALIDATE_INT);
    $memorization_from_verse = filter_input(INPUT_POST, 'memorization_from_verse', FILTER_VALIDATE_INT);
    $memorization_to_surah = filter_input(INPUT_POST, 'memorization_to_surah', FILTER_VALIDATE_INT);
    $memorization_to_verse = filter_input(INPUT_POST, 'memorization_to_verse', FILTER_VALIDATE_INT);
    $grade = filter_input(INPUT_POST, 'grade', FILTER_VALIDATE_INT);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (!$student_id || !$date || ($memorization_parts === false && $revision_parts === false) || 
        !$memorization_from_surah || !$memorization_from_verse || 
        !$memorization_to_surah || !$memorization_to_verse || 
        $grade === false) {
        http_response_code(400);
        exit(__('fill_all_fields'));
    }

    // Validate parts values
    if ($memorization_parts < 0 || $memorization_parts > 20 || 
        $revision_parts < 0 || $revision_parts > 20) {
        http_response_code(400);
        exit(__('invalid_parts'));
    }

    // Validate grade
    if ($grade < 0 || $grade > 100) {
        http_response_code(400);
        exit(__('invalid_grade'));
    }

    try {
        // Check if report already exists
        $stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE student_id = ? AND report_date = ?");
        $stmt->execute([$student_id, $date]);
        $existing_report = $stmt->fetch();

        if ($existing_report) {
            // Update existing report
            $sql = "UPDATE daily_reports SET 
                    memorization_parts = ?,
                    revision_parts = ?,
                    memorization_from_surah = ?,
                    memorization_from_verse = ?,
                    memorization_to_surah = ?,
                    memorization_to_verse = ?,
                    grade = ?,
                    notes = ?
                    WHERE student_id = ? AND report_date = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $memorization_parts,
                $revision_parts,
                $memorization_from_surah,
                $memorization_from_verse,
                $memorization_to_surah,
                $memorization_to_verse,
                $grade,
                $notes,
                $student_id,
                $date
            ]);
        } else {
            // Insert new report
            $sql = "INSERT INTO daily_reports 
                    (student_id, report_date, memorization_parts, revision_parts,
                    memorization_from_surah, memorization_from_verse,
                    memorization_to_surah, memorization_to_verse,
                    grade, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $student_id,
                $date,
                $memorization_parts,
                $revision_parts,
                $memorization_from_surah,
                $memorization_from_verse,
                $memorization_to_surah,
                $memorization_to_verse,
                $grade,
                $notes
            ]);
        }

        http_response_code(200);
        exit(__('report_saved'));
    } catch (PDOException $e) {
        error_log($e->getMessage());
        http_response_code(500);
        exit(__('save_error'));
    }
}
