<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

// التحقق من وجود حلقات للمعلم
$teacher_circles = $pdo->prepare("
    SELECT sc.*, d.name as department_name 
    FROM study_circles sc
    JOIN departments d ON sc.department_id = d.id
    WHERE sc.teacher_id = ?
    ORDER BY d.name, sc.name
");
$teacher_circles->execute([$_SESSION['user_id']]);
$circles = $teacher_circles->fetchAll();

// إذا لم يكن لدى المعلم حلقات
if (empty($circles)) {
    $_SESSION['error'] = "لا توجد لديك حلقات مسجلة";
    header('Location: circles.php');
    exit;
}

// تحديد الحلقة - إما المحددة في الرابط أو أول حلقة
$circle_id = isset($_GET['circle_id']) ? (int)$_GET['circle_id'] : $circles[0]['id'];

// التحقق من أن الحلقة المحددة تنتمي للمعلم
$circle_belongs_to_teacher = false;
foreach ($circles as $circle) {
    if ($circle['id'] == $circle_id) {
        $circle_belongs_to_teacher = true;
        break;
    }
}

// إذا كانت الحلقة لا تنتمي للمعلم، استخدم أول حلقة
if (!$circle_belongs_to_teacher) {
    $circle_id = $circles[0]['id'];
}

// تحديد التاريخ - إما المحدد في الرابط أو تاريخ اليوم
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// التحقق من صحة التاريخ
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// الحصول على معلومات الحلقة المحددة
$circle_stmt = $pdo->prepare("
    SELECT sc.*, d.name as department_name 
    FROM study_circles sc
    JOIN departments d ON sc.department_id = d.id
    WHERE sc.id = ? AND sc.teacher_id = ?
");
$circle_stmt->execute([$circle_id, $_SESSION['user_id']]);
$circle = $circle_stmt->fetch();

if (!$circle) {
    $_SESSION['error'] = "لا يمكنك الوصول إلى هذه الحلقة";
    header('Location: circles.php');
    exit;
}

// الحصول على قائمة السور
$surahs_stmt = $pdo->query("SELECT id, name, total_verses FROM surahs ORDER BY id");
$surahs = $surahs_stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من البيانات المطلوبة
        if (!isset($_POST['student_id'], $_POST['date'], $_POST['memorization_parts'], $_POST['revision_parts'])) {
            throw new Exception('جميع الحقول مطلوبة');
        }

        // تنقية وتحويل البيانات
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $memorization_parts = filter_input(INPUT_POST, 'memorization_parts', FILTER_VALIDATE_FLOAT);
    $revision_parts = filter_input(INPUT_POST, 'revision_parts', FILTER_VALIDATE_FLOAT);
    $grade = filter_input(INPUT_POST, 'grade', FILTER_VALIDATE_INT);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

        // التحقق من صحة البيانات
        if (!$student_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('بيانات غير صحيحة: student_id=' . $_POST['student_id'] . ', date=' . $_POST['date']);
        }

        if ($memorization_parts === false || $revision_parts === false) {
            throw new Exception('قيم غير صحيحة للحفظ أو المراجعة: memorization=' . $_POST['memorization_parts'] . ', revision=' . $_POST['revision_parts']);
        }

        // التحقق من وجود تقرير سابق
        $check_stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE student_id = ? AND report_date = ?");
        $check_stmt->execute([$student_id, $date]);
        $existing_report = $check_stmt->fetch();

        // تحضير البيانات للإدخال
        $params = [
            $memorization_parts,
            $revision_parts,
            $memorization_parts > 0 ? $_POST['memorization_from_surah'] : null,
            $memorization_parts > 0 ? $_POST['memorization_from_verse'] : null,
            $memorization_parts > 0 ? $_POST['memorization_to_surah'] : null,
            $memorization_parts > 0 ? $_POST['memorization_to_verse'] : null,
            $grade,
            $notes,
            $student_id,
            $date
        ];

        // طباعة البيانات للتحقق
        error_log('SQL Parameters: ' . print_r($params, true));

        if ($existing_report) {
            // تحديث التقرير الموجود
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
        } else {
            // إنشاء تقرير جديد
            $sql = "INSERT INTO daily_reports 
                    (student_id, report_date, memorization_parts, revision_parts,
                    memorization_from_surah, memorization_from_verse,
                    memorization_to_surah, memorization_to_verse,
                    grade, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        }

        error_log('SQL Query: ' . $sql);
            
            $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception('Database Error: ' . implode(', ', $stmt->errorInfo()));
        }

        http_response_code(200);
        exit('تم حفظ التقرير بنجاح');

    } catch (Exception $e) {
        error_log('Error in daily_reports.php: ' . $e->getMessage());
        http_response_code(500);
        exit('خطأ: ' . $e->getMessage());
    }
}

// Handle bulk form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_submit'])) {
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
    if (!$circle_id || !$date || ($memorization_parts === 0 && $revision_parts === 0)) {
        http_response_code(400);
        exit(__('enter_parts_error'));
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
        // Get all students in the circle
        $stmt = $pdo->prepare("SELECT student_id FROM circle_students WHERE circle_id = ?");
        $stmt->execute([$circle_id]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($students)) {
            http_response_code(400);
            exit(__('no_students_in_circle'));
        }

        // Begin transaction
        $pdo->beginTransaction();

        foreach ($students as $student_id) {
            // Check if report exists
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
                    $memorization_parts > 0 ? $memorization_from_surah : null,
                    $memorization_parts > 0 ? $memorization_from_verse : null,
                    $memorization_parts > 0 ? $memorization_to_surah : null,
                    $memorization_parts > 0 ? $memorization_to_verse : null,
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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
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
}

// تعديل استعلام الطلاب ليشمل سجل الحضور وأيام العطل
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        u.phone as student_number,
        dr.memorization_parts,
        dr.revision_parts,
        dr.memorization_from_surah,
        dr.memorization_from_verse,
        dr.memorization_to_surah,
        dr.memorization_to_verse,
        dr.grade,
        dr.notes,
        dr.id as report_id,
        s1.name as from_surah_name,
        s2.name as to_surah_name,
        (
            SELECT GROUP_CONCAT(
                CONCAT(
                    CASE 
                        WHEN DAYOFWEEK(dates.date) = 1 AND d.work_sunday = 0 THEN 'holiday'
                        WHEN DAYOFWEEK(dates.date) = 2 AND d.work_monday = 0 THEN 'holiday'
                        WHEN DAYOFWEEK(dates.date) = 3 AND d.work_tuesday = 0 THEN 'holiday'
                        WHEN DAYOFWEEK(dates.date) = 4 AND d.work_wednesday = 0 THEN 'holiday'
                        WHEN DAYOFWEEK(dates.date) = 5 AND d.work_thursday = 0 THEN 'holiday'
                        WHEN DAYOFWEEK(dates.date) = 6 AND d.work_friday = 0 THEN 'holiday'
                        WHEN DAYOFWEEK(dates.date) = 7 AND d.work_saturday = 0 THEN 'holiday'
                        WHEN dr_hist.id IS NOT NULL THEN 'present'
                        ELSE 'absent'
                    END,
                    '|',
                    COALESCE(dr_hist.memorization_parts, ''),
                    '|',
                    COALESCE(dr_hist.revision_parts, ''),
                    '|',
                    COALESCE(dr_hist.grade, ''),
                    '|',
                    dates.date
                )
                ORDER BY dates.date DESC
            )
            FROM (
                SELECT CURDATE() - INTERVAL n DAY as date
                FROM (
                    SELECT 0 as n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 
                    UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
                ) numbers
            ) dates
            LEFT JOIN daily_reports dr_hist ON dr_hist.student_id = u.id 
                AND dr_hist.report_date = dates.date
                AND (dr_hist.memorization_parts > 0 OR dr_hist.revision_parts > 0)
        ) as attendance_history
    FROM users u
    JOIN circle_students cs ON u.id = cs.student_id
    JOIN study_circles sc ON cs.circle_id = sc.id
    JOIN departments d ON sc.department_id = d.id
    LEFT JOIN daily_reports dr ON u.id = dr.student_id AND dr.report_date = ?
    LEFT JOIN surahs s1 ON dr.memorization_from_surah = s1.id
    LEFT JOIN surahs s2 ON dr.memorization_to_surah = s2.id
    WHERE cs.circle_id = ?
    ORDER BY u.name
");
$stmt->execute([$date, $circle_id]);
$students = $stmt->fetchAll();

// Get student report statistics for last 30 days
$stmt = $pdo->prepare("
    SELECT 
        student_id,
        COUNT(*) as total_reports,
        SUM(memorization_parts) as total_memorization,
        SUM(revision_parts) as total_revision,
        AVG(grade) as average_grade
    FROM daily_reports 
    WHERE student_id IN (
        SELECT student_id 
        FROM circle_students 
        WHERE circle_id = ?
    )
    AND report_date >= DATE_SUB(?, INTERVAL 30 DAY)
    GROUP BY student_id
");
$stmt->execute([$circle_id, $date]);
$student_stats = array_column($stmt->fetchAll(), null, 'student_id');

$pageTitle = __('daily_reports');
ob_start();
?>

<!-- فلتر الحلقات في أعلى الصفحة -->
<div class="container-fluid py-3 bg-light border-bottom">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <select class="form-select" onchange="window.location.href='?circle_id=' + this.value">
                    <?php
                    // الحصول على حلقات المعلم
                    $teacher_circles = $pdo->prepare("
                        SELECT sc.*, d.name as department_name 
                        FROM study_circles sc
                        JOIN departments d ON sc.department_id = d.id
                        WHERE sc.teacher_id = ?
                        ORDER BY d.name, sc.name
                    ");
                    $teacher_circles->execute([$_SESSION['user_id']]);
                    $circles = $teacher_circles->fetchAll();
                    
                    foreach ($circles as $c):
                        $selected = ($c['id'] == $circle_id) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($c['name'] . ' (' . $c['department_name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
        <div class="d-flex align-items-center">
                    <a href="?circle_id=<?php echo $circle_id; ?>&date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?>" class="btn btn-outline-secondary btn-sm me-2">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <input type="date" 
                           class="form-control form-control-sm" 
                           value="<?php echo $date; ?>" 
                           onchange="window.location.href='?circle_id=<?php echo $circle_id; ?>&date=' + this.value">
                    <a href="?circle_id=<?php echo $circle_id; ?>&date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?>" class="btn btn-outline-secondary btn-sm ms-2">
                <i class="bi bi-chevron-left"></i>
            </a>
            </div>
        </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#bulkReportModal">
                    <i class="bi bi-plus-lg"></i>
                    تقرير جماعي
            </button>
            </div>
        </div>
        </div>
    </div>

<div class="container py-4">


    <!-- Students Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($students as $student): ?>
        <div class="col">
            <div class="card h-100 <?php echo $student['memorization_parts'] || $student['revision_parts'] ? 'has-report' : 'no-report'; ?>">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($student['name']); ?></h5>
                            <div class="btn-group btn-group-sm">
                                <?php if ($student['memorization_parts'] || $student['revision_parts']): ?>
                                    <button type="button" class="btn btn-outline-primary edit-report" data-report='<?php echo json_encode($student); ?>' title="تعديل التقرير">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                    <button type="button" class="btn btn-outline-danger delete-report" 
                                            data-student-id="<?php echo $student['id']; ?>"
                                        data-report-id="<?php echo $student['report_id']; ?>"
                                            title="حذف التقرير">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-success add-report" data-student-id="<?php echo $student['id']; ?>" title="إضافة تقرير">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $student['student_number']); ?>" 
                           target="_blank" 
                           class="btn btn-outline-success btn-sm">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if ($student['memorization_parts'] || $student['revision_parts']): ?>
                        <div class="row g-3 mb-3">
                                <div class="col-6">
                                <div class="stats-box">
                                    <small class="text-muted d-block">الحفظ</small>
                                    <span class="number numeric-text"><?php echo number_format($student['memorization_parts'], 2); ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                <div class="stats-box">
                                    <small class="text-muted d-block">المراجعة</small>
                                    <span class="number numeric-text"><?php echo number_format($student['revision_parts'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if ($student['memorization_parts'] > 0): ?>
                            <div class="memorization-range">
                                <small class="text-muted d-block mb-2">نطاق الحفظ</small>
                            <div class="d-flex align-items-center gap-2">
                                    <span class="badge">
                                    <?php echo $student['from_surah_name']; ?> (<?php echo $student['memorization_from_verse']; ?>)
                                </span>
                                    <i class="bi bi-arrow-left"></i>
                                    <span class="badge">
                                    <?php echo $student['to_surah_name']; ?> (<?php echo $student['memorization_to_verse']; ?>)
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mt-3">
                            <small class="text-muted d-block mb-2">الدرجة</small>
                            <div class="progress">
                                <div class="progress-bar <?php 
                                    echo $student['grade'] >= 95 ? 'excellent' : 
                                        ($student['grade'] >= 85 ? 'good' : 
                                        ($student['grade'] >= 75 ? 'average' : 'poor')); 
                                ?>" style="width: <?php echo $student['grade']; ?>%"></div>
                                </div>
                            <div class="text-end mt-1">
                                <small class="text-muted numeric-text"><?php echo number_format($student['grade']); ?>%</small>
                            </div>
                            </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-journal-x display-4 d-block mb-2"></i>
                            <p class="mb-0">لم يتم تسجيل تقرير</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- إضافة فوتر الكارت مع مربعات الحضور -->
                <div class="card-footer bg-white">
                    <small class="text-muted d-block mb-2">سجل الحضور آخر 7 أيام</small>
                    <div class="attendance-boxes">
                        <?php
                        $attendance = explode(',', $student['attendance_history'] ?? '');
                        for ($i = 0; $i < 7; $i++):
                            $day_data = explode('|', $attendance[$i] ?? '');
                            $status = $day_data[0] ?? 'no-data';
                            $memorization = $day_data[1] ?? '';
                            $revision = $day_data[2] ?? '';
                            $grade = $day_data[3] ?? '';
                            $date = $day_data[4] ?? date('Y-m-d', strtotime("-$i days"));
                            
                            $tooltip_content = '';
                            if ($status == 'present') {
                                $tooltip_content = sprintf(
                                    'الحفظ: %s أجزاء<br>المراجعة: %s أجزاء<br>الدرجة: %s%%',
                                    number_format((float)$memorization, 2),
                                    number_format((float)$revision, 2),
                                    number_format((float)$grade)
                                );
                            } elseif ($status == 'holiday') {
                                $tooltip_content = 'يوم عطلة';
                            } else {
                                $tooltip_content = 'لم يحضر';
                            }
                        ?>
                            <div class="attendance-box <?php echo $status; ?>" 
                                 data-bs-toggle="popover"
                                 data-bs-trigger="hover focus"
                                 data-bs-html="true"
                                 data-bs-content="<?php echo htmlspecialchars($tooltip_content); ?>"
                                 title="<?php echo date('Y/m/d', strtotime($date)); ?>">
                                <?php if ($status == 'present'): ?>
                                    <i class="bi bi-check-lg"></i>
                                <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                        </div>
                        </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                    <input type="hidden" name="student_id" id="student_id">
                    <input type="hidden" name="date" id="date" value="<?php echo $date; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('memorization'); ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control text-center" name="memorization_parts" id="memorization_parts" 
                                       min="0" max="20" step="0.25" value="0" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustMemorizationParts(0.25)">+ ربع</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustMemorizationParts(0.5)">+ نصف</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustMemorizationParts(1)">+ 1</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustMemorizationParts(-9999)">صفر</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('revision'); ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control text-center" name="revision_parts" id="revision_parts" 
                                       min="0" max="20" step="0.25" value="0" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustRevisionParts(0.25)">+ ربع</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustRevisionParts(0.5)">+ نصف</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustRevisionParts(1)">+ 1</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustRevisionParts(-9999)">صفر</button>
                            </div>
                        </div>
                    </div>

                    <div class="memorization-fields">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('memorization_from'); ?></label>
                            <div class="row g-2">
                                <div class="col-8">
                                        <select class="form-select" name="memorization_from_surah" id="from_surah">
                                        <option value=""><?php echo __('select_surah'); ?></option>
                                            <?php foreach ($surahs as $surah): ?>
                                                <option value="<?php echo $surah['id']; ?>"><?php echo $surah['name']; ?></option>
                                            <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="memorization_from_verse" id="from_verse" 
                                               min="1" placeholder="<?php echo __('verse'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('memorization_to'); ?></label>
                            <div class="row g-2">
                                <div class="col-8">
                                        <select class="form-select" name="memorization_to_surah" id="to_surah">
                                        <option value=""><?php echo __('select_surah'); ?></option>
                                            <?php foreach ($surahs as $surah): ?>
                                                <option value="<?php echo $surah['id']; ?>"><?php echo $surah['name']; ?></option>
                                            <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="memorization_to_verse" id="to_verse" 
                                               min="1" placeholder="<?php echo __('verse'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('grade'); ?></label>
                                <input type="number" class="form-control" name="grade" id="grade" min="0" max="100" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" name="notes" id="notes" rows="1"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="saveReport"><?php echo __('save'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Add Bulk Report Modal -->
<div class="modal fade" id="bulkReportModal" tabindex="-1" aria-labelledby="bulkReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('bulk_report'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkReportForm" method="post" action="save_report.php" class="needs-validation" novalidate>
                    <input type="hidden" name="bulk_submit" value="1">
                    <input type="hidden" name="circle_id" value="<?php echo $circle_id; ?>">
                    <input type="hidden" name="date" value="<?php echo $date; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('memorization'); ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control text-center" name="memorization_parts" id="bulk_memorization_parts" 
                                       min="0" max="20" step="0.25" value="0" required readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustBulkMemorizationParts(0.25)">+ ربع</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustBulkMemorizationParts(0.5)">+ نصف</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustBulkMemorizationParts(1)">+ 1</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustBulkMemorizationParts(-9999)">صفر</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('revision'); ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control text-center" name="revision_parts" id="bulk_revision_parts" 
                                       min="0" max="20" step="0.25" value="0" required readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustBulkRevisionParts(0.25)">+ ربع</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustBulkRevisionParts(0.5)">+ نصف</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustBulkRevisionParts(1)">+ 1</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="adjustBulkRevisionParts(-9999)">صفر</button>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3" id="bulk_memorization_fields">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('memorization_from'); ?></label>
                            <div class="row g-2">
                                <div class="col-8">
                                    <select class="form-select" name="memorization_from_surah" id="bulk_from_surah">
                                        <option value=""><?php echo __('select_surah'); ?></option>
                                        <?php foreach ($surahs as $surah): ?>
                                            <option value="<?php echo $surah['id']; ?>"><?php echo $surah['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="memorization_from_verse" id="bulk_from_verse" 
                                           min="1" placeholder="<?php echo __('verse'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('memorization_to'); ?></label>
                            <div class="row g-2">
                                <div class="col-8">
                                    <select class="form-select" name="memorization_to_surah" id="bulk_to_surah">
                                        <option value=""><?php echo __('select_surah'); ?></option>
                                        <?php foreach ($surahs as $surah): ?>
                                            <option value="<?php echo $surah['id']; ?>"><?php echo $surah['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control" name="memorization_to_verse" id="bulk_to_verse" 
                                           min="1" placeholder="<?php echo __('verse'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('grade'); ?></label>
                            <input type="number" class="form-control" name="grade" id="bulk_grade" min="0" max="100" step="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" name="notes" id="bulk_notes" rows="1"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="saveBulkReport"><?php echo __('save'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Add before including layout.php -->
<script>
// تحويل الأرقام العربية إلى إنجليزية
function toEnglishNumbers(num) {
    return num.toString().replace(/[٠١٢٣٤٥٦٧٨٩]/g, function(d) {
        return d.charCodeAt(0) - 1632; // للأرقام العربية
    }).replace(/[۰۱۲۳۴۵۶۷۸۹]/g, function(d) {
        return d.charCodeAt(0) - 1776; // للأرقام الفارسية
    });
}

// تطبيق التحويل على جميع المدخلات الرقمية
document.addEventListener('DOMContentLoaded', function() {
    // تحويل الأرقام في المدخلات
    const numericInputs = document.querySelectorAll('input[type="number"]');
    numericInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = toEnglishNumbers(this.value);
        });
    });

    // تحويل الأرقام في النصوص
    const numericTexts = document.querySelectorAll('.numeric-text');
    numericTexts.forEach(element => {
        element.textContent = toEnglishNumbers(element.textContent);
    });

    // Store translations
    const translations = {
        add_report: '<?php echo __("add_report"); ?>',
        edit_report: '<?php echo __("edit_report"); ?>',
        error: '<?php echo __("error"); ?>',
        success: '<?php echo __("success"); ?>',
        save_error: '<?php echo __("save_error"); ?>',
        enter_parts_error: '<?php echo __("enter_parts_error"); ?>',
        fill_all_fields: '<?php echo __("fill_all_fields"); ?>',
        invalid_from_verse: '<?php echo __("invalid_from_verse"); ?>',
        invalid_to_verse: '<?php echo __("invalid_to_verse"); ?>',
        from_verse_greater_than_to: '<?php echo __("from_verse_greater_than_to"); ?>',
        from_surah_greater_than_to: '<?php echo __("from_surah_greater_than_to"); ?>',
        report_saved: '<?php echo __("report_saved"); ?>',
        delete_report: '<?php echo __("delete_report"); ?>',
        delete_confirmation: '<?php echo __("delete_confirmation"); ?>',
        delete_error: '<?php echo __("delete_error"); ?>',
        yes: '<?php echo __("yes"); ?>',
        cancel: '<?php echo __("cancel"); ?>',
        bulk_report: '<?php echo __("bulk_report"); ?>',
        bulk_report_confirmation: '<?php echo __("bulk_report_confirmation"); ?>',
        bulk_report_success: '<?php echo __("bulk_report_success"); ?>'
    };

    // Store surahs data
    const surahs = <?php
        $stmt = $pdo->query("SELECT id, name, total_verses FROM surahs ORDER BY id");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    ?>;
    const surahsMap = new Map(surahs.map(surah => [surah.id, surah]));

    // Initialize variables for parts
    let memorizationParts = 0;
    let revisionParts = 0;
    let bulkMemorizationParts = 0;
    let bulkRevisionParts = 0;

    // Function to format parts number
    function formatParts(number) {
        if (number === Math.floor(number)) {
            return number.toString();
        } else if (number === Math.floor(number) + 0.5) {
            return number.toString();
        } else if (number === Math.floor(number) + 0.25) {
            return number.toString();
        }
        return number.toFixed(2);
    }

    // Function to adjust memorization parts
    window.adjustMemorizationParts = function(amount) {
        if (amount === -9999) {
            memorizationParts = 0;
        } else {
            memorizationParts = Math.min(20, Math.max(0, memorizationParts + amount));
        }
        document.getElementById('memorization_parts').value = formatParts(memorizationParts);
    };

    // Function to adjust revision parts
    window.adjustRevisionParts = function(amount) {
        if (amount === -9999) {
            revisionParts = 0;
        } else {
            revisionParts = Math.min(20, Math.max(0, revisionParts + amount));
        }
        document.getElementById('revision_parts').value = formatParts(revisionParts);
    };

    // Function to adjust bulk memorization parts
    window.adjustBulkMemorizationParts = function(amount) {
        if (amount === -9999) {
            bulkMemorizationParts = 0;
        } else {
            bulkMemorizationParts = Math.min(20, Math.max(0, bulkMemorizationParts + amount));
        }
        document.getElementById('bulk_memorization_parts').value = formatParts(bulkMemorizationParts);
        toggleBulkMemorizationFields();
    };

    // Function to adjust bulk revision parts
    window.adjustBulkRevisionParts = function(amount) {
        if (amount === -9999) {
            bulkRevisionParts = 0;
        } else {
            bulkRevisionParts = Math.min(20, Math.max(0, bulkRevisionParts + amount));
        }
        document.getElementById('bulk_revision_parts').value = formatParts(bulkRevisionParts);
    };

    // Function to update verse limits
    function updateVerseLimits(selectElement, verseInput) {
        const surahId = selectElement.value;
        const surah = surahsMap.get(parseInt(surahId));
        if (surah) {
            verseInput.max = surah.total_verses;
            verseInput.placeholder = `1 - ${surah.total_verses}`;
            
            // If current value is greater than max, reset it
            if (parseInt(verseInput.value) > surah.total_verses) {
                verseInput.value = '';
            }
        }
    }

    // Add event listeners for surah selects
    const fromSurah = document.getElementById('from_surah');
    const toSurah = document.getElementById('to_surah');
    const fromVerse = document.getElementById('from_verse');
    const toVerse = document.getElementById('to_verse');

    fromSurah.addEventListener('change', () => updateVerseLimits(fromSurah, fromVerse));
    toSurah.addEventListener('change', () => updateVerseLimits(toSurah, toVerse));

    // Add event listeners for bulk surah selects
    const bulkFromSurah = document.getElementById('bulk_from_surah');
    const bulkToSurah = document.getElementById('bulk_to_surah');
    const bulkFromVerse = document.getElementById('bulk_from_verse');
    const bulkToVerse = document.getElementById('bulk_to_verse');

    bulkFromSurah.addEventListener('change', () => updateVerseLimits(bulkFromSurah, bulkFromVerse));
    bulkToSurah.addEventListener('change', () => updateVerseLimits(bulkToSurah, bulkToVerse));

    // Function to toggle memorization fields visibility
    function toggleBulkMemorizationFields() {
        const memorizationFields = document.getElementById('bulk_memorization_fields');
        const bulkFromSurah = document.getElementById('bulk_from_surah');
        const bulkToSurah = document.getElementById('bulk_to_surah');
        const bulkFromVerse = document.getElementById('bulk_from_verse');
        const bulkToVerse = document.getElementById('bulk_to_verse');
        
        if (bulkMemorizationParts > 0) {
            memorizationFields.style.display = '';
            bulkFromSurah.required = true;
            bulkToSurah.required = true;
            bulkFromVerse.required = true;
            bulkToVerse.required = true;
        } else {
            memorizationFields.style.display = 'none';
            bulkFromSurah.required = false;
            bulkToSurah.required = false;
            bulkFromVerse.required = false;
            bulkToVerse.required = false;
            
            // Clear values
            bulkFromSurah.value = '';
            bulkToSurah.value = '';
            bulkFromVerse.value = '';
            bulkToVerse.value = '';
        }
    }

    // Initialize Bootstrap Modals
    const reportModalEl = document.getElementById('reportModal');
    const reportModal = new bootstrap.Modal(reportModalEl);
    const bulkReportModalEl = document.getElementById('bulkReportModal');
    const bulkReportModal = new bootstrap.Modal(bulkReportModalEl);

    // Handle add report button click
    document.querySelectorAll('.add-report').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const modalTitle = document.querySelector('#reportModal .modal-title');
            modalTitle.textContent = translations.add_report;
            
            // Reset form
            document.getElementById('reportForm').reset();
            document.getElementById('student_id').value = this.dataset.studentId;
            
            // Set memorization and revision parts
            memorizationParts = parseFloat(this.dataset.memorizationParts || 0);
            revisionParts = parseFloat(this.dataset.revisionParts || 0);
            
            document.getElementById('memorization_parts').value = formatParts(memorizationParts);
            document.getElementById('revision_parts').value = formatParts(revisionParts);
            
            fromSurah.value = this.dataset.fromSurah || '';
            updateVerseLimits(fromSurah, fromVerse);
            fromVerse.value = this.dataset.fromVerse || '';
            
            toSurah.value = this.dataset.toSurah || '';
            updateVerseLimits(toSurah, toVerse);
            toVerse.value = this.dataset.toVerse || '';
            
            document.getElementById('grade').value = this.dataset.grade || '';
            document.getElementById('notes').value = this.dataset.notes || '';
            
            reportModal.show();
        });
    });

    // Handle edit report
    document.querySelectorAll('.edit-report').forEach(button => {
        button.addEventListener('click', function(e) {
            const reportData = JSON.parse(this.dataset.report);
            
            // Reset form
            document.getElementById('reportForm').reset();
            
            // Set form values
            document.getElementById('student_id').value = reportData.student_id;
            document.getElementById('memorization_parts').value = reportData.memorization_parts;
            document.getElementById('revision_parts').value = reportData.revision_parts;
            document.getElementById('from_surah').value = reportData.memorization_from_surah;
            document.getElementById('from_verse').value = reportData.memorization_from_verse;
            document.getElementById('to_surah').value = reportData.memorization_to_surah;
            document.getElementById('to_verse').value = reportData.memorization_to_verse;
            document.getElementById('grade').value = reportData.grade;
            document.getElementById('notes').value = reportData.notes || '';
            
            // Update global variables
            memorizationParts = parseFloat(reportData.memorization_parts);
            revisionParts = parseFloat(reportData.revision_parts);
            
            // Show modal
            reportModal.show();
        });
    });

    // Handle delete report
    document.querySelectorAll('.delete-report').forEach(button => {
        button.addEventListener('click', async function() {
            const studentId = this.dataset.studentId;
            const reportId = this.dataset.reportId;
            
            const result = await Swal.fire({
                title: translations.delete_report,
                text: translations.delete_confirmation,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: translations.yes,
                cancelButtonText: translations.cancel
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('delete_report.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `student_id=${studentId}&report_id=${reportId}&date=<?php echo $date; ?>`
                    });

                    if (response.ok) {
                        Swal.fire({
                            icon: 'success',
                            title: translations.success,
                            text: await response.text(),
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: translations.error,
                            text: translations.delete_error
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: translations.error,
                        text: translations.delete_error
                    });
                }
            }
        });
    });

    // Handle save report
    document.getElementById('saveReport').addEventListener('click', async function() {
        try {
            const form = document.getElementById('reportForm');
            if (!form) {
                throw new Error('لم يتم العثور على النموذج');
            }

            // التحقق من البيانات
            const memorizationInput = document.getElementById('memorization_parts');
            const revisionInput = document.getElementById('revision_parts');
            const studentIdInput = document.getElementById('student_id');
            const dateInput = document.getElementById('date');

            if (!memorizationInput || !revisionInput || !studentIdInput || !dateInput) {
                throw new Error('لم يتم العثور على بعض عناصر النموذج');
            }

            const memorizationParts = parseFloat(memorizationInput.value) || 0;
            const revisionParts = parseFloat(revisionInput.value) || 0;
            const studentId = studentIdInput.value;
            const reportDate = dateInput.value;

            // التحقق من وجود student_id و date
            if (!studentId || !reportDate) {
                throw new Error('بيانات الطالب والتاريخ مطلوبة');
            }

        if (memorizationParts === 0 && revisionParts === 0) {
                throw new Error(translations.enter_parts_error);
            }

            // التحقق من حقول الحفظ إذا كان هناك حفظ
            if (memorizationParts > 0) {
                const fromSurah = document.getElementById('from_surah');
                const fromVerse = document.getElementById('from_verse');
                const toSurah = document.getElementById('to_surah');
                const toVerse = document.getElementById('to_verse');
                const grade = document.getElementById('grade');

                if (!fromSurah || !fromVerse || !toSurah || !toVerse || !grade) {
                    throw new Error('لم يتم العثور على حقول الحفظ المطلوبة');
                }

                if (!fromSurah.value || !fromVerse.value || !toSurah.value || !toVerse.value || !grade.value) {
                    throw new Error(translations.fill_all_fields);
                }

                // التحقق من صحة الآيات
                const fromSurahData = surahsMap.get(parseInt(fromSurah.value));
                const toSurahData = surahsMap.get(parseInt(toSurah.value));

                if (!fromSurahData || !toSurahData) {
                    throw new Error('خطأ في بيانات السور');
                }

                if (parseInt(fromVerse.value) < 1 || parseInt(fromVerse.value) > fromSurahData.total_verses) {
                    throw new Error(translations.invalid_from_verse);
                }

                if (parseInt(toVerse.value) < 1 || parseInt(toVerse.value) > toSurahData.total_verses) {
                    throw new Error(translations.invalid_to_verse);
                }

                if (parseInt(fromSurah.value) > parseInt(toSurah.value)) {
                    throw new Error(translations.from_surah_greater_than_to);
                }

                if (parseInt(fromSurah.value) === parseInt(toSurah.value) && 
                    parseInt(fromVerse.value) > parseInt(toVerse.value)) {
                    throw new Error(translations.from_verse_greater_than_to);
                }
            }

            const formData = new FormData(form);
            
            // طباعة البيانات للتحقق
            console.log('Form Data:', {
                student_id: studentId,
                date: reportDate,
                memorization_parts: memorizationParts,
                revision_parts: revisionParts
            });

            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });

            const responseText = await response.text();

            if (!response.ok) {
                throw new Error(responseText);
            }

            await Swal.fire({
                    icon: 'success',
                    title: translations.success,
                    text: responseText,
                    timer: 1500
            });

            location.reload();

        } catch (error) {
            console.error('Error:', error);
            await Swal.fire({
                icon: 'error',
                title: translations.error,
                text: error.message
            });
        }
    });

    // Handle save bulk report
    document.getElementById('saveBulkReport').addEventListener('click', async function() {
        // Validate that at least one of memorization or revision is greater than 0
        if (bulkMemorizationParts === 0 && bulkRevisionParts === 0) {
            Swal.fire({
                icon: 'error',
                title: translations.error,
                text: translations.enter_parts_error
            });
            return;
        }

        // Validate memorization fields if there is memorization
        if (bulkMemorizationParts > 0) {
            const fromSurahId = parseInt(bulkFromSurah.value);
            const toSurahId = parseInt(bulkToSurah.value);
            const fromVerseNum = parseInt(bulkFromVerse.value);
            const toVerseNum = parseInt(bulkToVerse.value);
            
            const fromSurahData = surahsMap.get(fromSurahId);
            const toSurahData = surahsMap.get(toSurahId);
            
            if (!fromSurahData || !toSurahData || !fromVerseNum || !toVerseNum) {
                Swal.fire({
                    icon: 'error',
                    title: translations.error,
                    text: translations.fill_all_fields
                });
                return;
            }
            
            if (fromVerseNum < 1 || fromVerseNum > fromSurahData.total_verses) {
                Swal.fire({
                    icon: 'error',
                    title: translations.error,
                    text: translations.invalid_from_verse
                });
                return;
            }
            
            if (toVerseNum < 1 || toVerseNum > toSurahData.total_verses) {
                Swal.fire({
                    icon: 'error',
                    title: translations.error,
                    text: translations.invalid_to_verse
                });
                return;
            }
            
            if (fromSurahId === toSurahId && fromVerseNum > toVerseNum) {
                Swal.fire({
                    icon: 'error',
                    title: translations.error,
                    text: translations.from_verse_greater_than_to
                });
                return;
            }
            
            if (fromSurahId > toSurahId) {
                Swal.fire({
                    icon: 'error',
                    title: translations.error,
                    text: translations.from_surah_greater_than_to
                });
                return;
            }
        }

        // Show confirmation dialog
        const result = await Swal.fire({
            title: translations.bulk_report,
            text: translations.bulk_report_confirmation,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: translations.yes,
            cancelButtonText: translations.cancel
        });

        if (result.isConfirmed) {
            const formData = new FormData(document.getElementById('bulkReportForm'));
            formData.set('memorization_parts', bulkMemorizationParts);
            formData.set('revision_parts', bulkRevisionParts);

            try {
                const response = await fetch('save_report.php?circle_id=' + formData.get('circle_id'), {
                    method: 'POST',
                    body: formData
                });

                const responseText = await response.text();

                if (response.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: translations.success,
                        text: responseText,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: translations.error,
                        text: responseText
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: translations.error,
                    text: translations.save_error
                });
            }
        }
    });

    // Initial setup
    toggleBulkMemorizationFields();

    // تفعيل جميع popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            placement: 'top',
            container: 'body'
        });
    });
});

// تحديث دالة تحويل الأرقام
function convertNumbers() {
    // تحويل جميع الأرقام في الصفحة
    document.querySelectorAll('.numeric-text, .grade-text, .parts-text').forEach(element => {
        let text = element.textContent;
        // تحويل الأرقام العربية إلى إنجليزية
        text = text.replace(/[٠١٢٣٤٥٦٧٨٩]/g, d => d.charCodeAt(0) - 1632);
        // تحويل الأرقام الفارسية إلى إنجليزية
        text = text.replace(/[۰۱۲۳۴۵۶۷۸۹]/g, d => d.charCodeAt(0) - 1776);
        element.textContent = text;
    });

    // تحويل الأرقام في المدخلات
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value;
            value = value.replace(/[٠١٢٣٤٥٦٧٨٩]/g, d => d.charCodeAt(0) - 1632);
            value = value.replace(/[۰۱۲۳۴۵۶۷۸۹]/g, d => d.charCodeAt(0) - 1776);
            this.value = value;
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    convertNumbers();
    
    // تحويل الأرقام بعد كل تحديث للصفحة
    const observer = new MutationObserver(convertNumbers);
    observer.observe(document.body, { 
        childList: true, 
        subtree: true 
    });
});

// تعديل دالة فتح النافذة المنبثقة
function openReportModal(studentId, studentName) {
    // تعيين معرف الطالب واسمه
    document.getElementById('student_id').value = studentId;
    
    // تعيين عنوان النافذة المنبثقة
    document.querySelector('#reportModal .modal-title').textContent = 'إضافة تقرير: ' + studentName;
    
    // إعادة تعيين قيم النموذج
    document.getElementById('memorization_parts').value = 0;
    document.getElementById('revision_parts').value = 0;
    document.getElementById('from_surah').value = '';
    document.getElementById('from_verse').value = '';
    document.getElementById('to_surah').value = '';
    document.getElementById('to_verse').value = '';
    document.getElementById('grade').value = '';
    document.getElementById('notes').value = '';
    
    // التحقق من وجود تقرير سابق
    const existingReport = document.querySelector(`[data-student-id="${studentId}"]`);
    if (existingReport) {
        // ملء النموذج بالبيانات الموجودة
        const reportData = JSON.parse(existingReport.dataset.report);
        document.getElementById('memorization_parts').value = reportData.memorization_parts || 0;
        document.getElementById('revision_parts').value = reportData.revision_parts || 0;
        
        if (reportData.memorization_parts > 0) {
            document.getElementById('from_surah').value = reportData.memorization_from_surah || '';
            document.getElementById('from_verse').value = reportData.memorization_from_verse || '';
            document.getElementById('to_surah').value = reportData.memorization_to_surah || '';
            document.getElementById('to_verse').value = reportData.memorization_to_verse || '';
        }
        
        document.getElementById('grade').value = reportData.grade || '';
        document.getElementById('notes').value = reportData.notes || '';
    }
    
    // فتح النافذة المنبثقة
    const reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
    reportModal.show();
}
</script>

<style>
/* أنماط الكروت */
.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

/* كرت لديه تقرير */
.card.has-report {
    border-right: 4px solid #28a745;
}

/* كرت بدون تقرير */
.card.no-report {
    border-right: 4px solid #ffc107;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* رأس الكرت */
.card-header {
    background: linear-gradient(45deg, #f8f9fa, #ffffff);
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1rem;
}

/* كرت لديه تقرير */
.card.has-report .card-header {
    background: linear-gradient(45deg, #d4edda, #ffffff);
}

/* محتوى الكرت */
.card-body {
    padding: 1.25rem;
}

/* أرقام الحفظ والمراجعة */
.stats-box {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 0.75rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stats-box:hover {
    background-color: #e9ecef;
}

.stats-box .number {
    font-size: 1.25rem;
    font-weight: 600;
    color: #198754;
}

/* شريط التقدم */
.progress {
    height: 8px;
    background-color: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.3s ease;
}

.progress-bar.excellent {
    background-color: #198754;
}

.progress-bar.good {
    background-color: #28a745;
}

.progress-bar.average {
    background-color: #ffc107;
}

.progress-bar.poor {
    background-color: #dc3545;
}

/* تذييل الكرت */
.card-footer {
    background-color: #f8f9fa;
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 1rem;
}

/* أزرار التحكم */
.btn-outline-success {
    border-color: #28a745;
    color: #28a745;
}

.btn-outline-success:hover {
    background-color: #28a745;
    color: #fff;
}

/* نطاق الحفظ */
.memorization-range {
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 0.5rem;
    margin: 0.5rem 0;
}

.memorization-range .badge {
    background-color: #e9ecef;
    color: #198754;
    font-weight: 500;
}

/* تحديث أنماط مربعات الحضور */
.attendance-boxes {
    display: flex;
    gap: 4px;
    justify-content: space-between;
}

.attendance-box {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.attendance-box:hover {
    transform: scale(1.1);
}

.attendance-box.present {
    background-color: #28a745;
    color: white;
    border: 1px solid #1e7e34;
}

.attendance-box.absent {
    background-color: #dc3545;
    border: 1px solid #bd2130;
}

.attendance-box.holiday {
    background-color: #e9ecef;
    border: 1px solid #dee2e6;
}

.attendance-box i {
    font-size: 14px;
}
</style>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
