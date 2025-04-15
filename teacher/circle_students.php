<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';

// التأكد من أن المستخدم معلم
requireRole('teacher');

// الحصول على حلقات المعلم
$teacher_id = $_SESSION['user_id'];
$circles_stmt = $conn->prepare("
    SELECT sc.*, d.name as department_name 
    FROM study_circles sc
    JOIN departments d ON sc.department_id = d.id
    WHERE sc.teacher_id = ?
    ORDER BY d.name, sc.name
");
$circles_stmt->bind_param("i", $teacher_id);
$circles_stmt->execute();
$circles = $circles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// إذا لم يكن هناك حلقات، توجيه المعلم إلى صفحة الحلقات
if (empty($circles)) {
    $_SESSION['error'] = "ليس لديك حلقات مسجلة";
    header('Location: circles.php');
    exit;
}

// تحديد الحلقة المختارة
$selected_circle = isset($_GET['circle_id']) ? (int)$_GET['circle_id'] : $circles[0]['id'];

// التحقق من أن الحلقة المختارة تنتمي للمعلم
$circle_belongs_to_teacher = false;
foreach ($circles as $c) {
    if ($c['id'] == $selected_circle) {
        $circle_belongs_to_teacher = true;
        break;
    }
}

if (!$circle_belongs_to_teacher) {
    $selected_circle = $circles[0]['id'];
    header('Location: ' . $_SERVER['PHP_SELF'] . '?circle_id=' . $selected_circle);
    exit;
}

$success_message = '';
$error_message = '';

// الحصول على معلومات الحلقة
$stmt = $conn->prepare("
    SELECT sc.*, d.name as department_name 
    FROM study_circles sc
    JOIN departments d ON sc.department_id = d.id
    WHERE sc.id = ? AND sc.teacher_id = ?
");
$stmt->bind_param("ii", $selected_circle, $teacher_id);
$stmt->execute();
$circle = $stmt->get_result()->fetch_assoc();

if (!$circle) {
    $_SESSION['error'] = "لا يمكنك الوصول إلى هذه الحلقة";
    header('Location: circles.php');
    exit;
}

// الحصول على قائمة الطلاب في الحلقة
$students = $conn->query("
    SELECT u.*, cs.created_at as joined_at,
           u.is_active
    FROM users u
    JOIN circle_students cs ON u.id = cs.student_id
    WHERE cs.circle_id = $selected_circle
    ORDER BY u.is_active DESC, u.name
")->fetch_all(MYSQLI_ASSOC);

// الحصول على قائمة الطلاب غير المسجلين في الحلقة
$available_students = $conn->query("
    SELECT u.*
    FROM users u
    WHERE u.role = 'student'
    AND u.id NOT IN (SELECT student_id FROM circle_students WHERE circle_id = $selected_circle)
    ORDER BY u.name
")->fetch_all(MYSQLI_ASSOC);

// الحصول على آخر التقارير للطلاب في الحلقة
$recent_reports_sql = "
    SELECT dr.*, 
           u.name as student_name,
           s1.name as memorization_from_surah_name,
           s2.name as memorization_to_surah_name
    FROM daily_reports dr
    JOIN users u ON dr.student_id = u.id
    JOIN surahs s1 ON dr.memorization_from_surah = s1.id
    JOIN surahs s2 ON dr.memorization_to_surah = s2.id
    JOIN circle_students cs ON dr.student_id = cs.student_id
    WHERE cs.circle_id = ?
    AND dr.report_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
    ORDER BY dr.report_date DESC, dr.created_at DESC";

$reports_stmt = $conn->prepare($recent_reports_sql);
$reports_stmt->bind_param("i", $selected_circle);
$reports_stmt->execute();
$recent_reports = $reports_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// تجميع التقارير حسب الطالب
$student_reports = [];
foreach ($recent_reports as $report) {
    $student_id = $report['student_id'];
    if (!isset($student_reports[$student_id])) {
        $student_reports[$student_id] = [];
    }
    $student_reports[$student_id][] = $report;
}

// معالجة تغيير حالة الطالب
if (isset($_POST['toggle_status'])) {
    $student_id = (int)$_POST['student_id'];
    
    // التحقق من أن الطالب في حلقة المعلم
    $check_stmt = $conn->prepare("
        SELECT cs.student_id 
        FROM circle_students cs
        JOIN study_circles sc ON cs.circle_id = sc.id
        WHERE cs.student_id = ? AND sc.teacher_id = ? AND cs.circle_id = ?
    ");
    $check_stmt->bind_param("iii", $student_id, $teacher_id, $selected_circle);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // تحديث حالة الطالب
        $stmt = $conn->prepare("
            UPDATE users 
            SET is_active = NOT is_active 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $student_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "تم تحديث حالة الطالب بنجاح";
        } else {
            $_SESSION['error'] = "حدث خطأ أثناء تحديث حالة الطالب";
        }
    } else {
        $_SESSION['error'] = "لا يمكنك تعديل حالة هذا الطالب";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?circle_id=' . $selected_circle);
    exit();
}

$pageTitle = 'طلاب الحلقة: ' . $circle['name'];
ob_start();
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-6">
                <label for="circle_id" class="form-label">اختر الحلقة</label>
                <select name="circle_id" id="circle_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($circles as $c): ?>
                        <option value="<?php echo $c['id']; ?>" 
                                <?php echo $c['id'] == $selected_circle ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?> 
                            (<?php echo htmlspecialchars($c['department_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end align-items-end h-100">
                    <a href="circles.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-right"></i> عودة للحلقات
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

    <div class="row mb-4">
    <div class="col">
            <h2 class="mb-3">طلاب الحلقة: <?php echo htmlspecialchars($circle['name']); ?></h2>
            <div class="text-muted">
                القسم: <?php echo htmlspecialchars($circle['department_name']); ?>
            </div>
        </div>
    </div>

<div class="container-fluid py-4">


    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">

        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-plus"></i> إضافة طالب
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <p class="mb-3">قم بإنشاء حساب طالب جديد وإضافته للحلقة</p>
                        <a href="add_student.php?circle_id=<?php echo $selected_circle; ?>" class="btn btn-success w-100">
                            <i class="bi bi-person-plus-fill"></i> إنشاء حساب طالب جديد
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-people-fill"></i> الطلاب المسجلون
                        <span class="badge bg-light text-primary"><?php echo count($students); ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($students)): ?>
                        <div class="text-center p-5">
                            <i class="bi bi-people h1 text-muted"></i>
                            <p class="text-muted mt-3">لا يوجد طلاب مسجلون في هذه الحلقة</p>
                        </div>
                    <?php else: ?>
                        <!-- الطلاب النشطون -->
                        <div class="active-students">
                            <div class="row g-0">
                                <?php 
                                $active_count = 0;
                                foreach ($students as $index => $student): 
                                    if ($student['is_active']):
                                        $active_count++;
                                ?>
                                    <div class="col-12 col-md-6 col-xl-4">
                                        <div class="student-card position-relative h-100">
                                            <div class="p-3 border-bottom h-100">
                                                <!-- معلومات الطالب الأساسية -->
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="avatar-circle me-3">
                                                        <span class="avatar-text">
                                                            <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h5>
                                                    <div class="text-muted small">
                                                        انضم في <?php echo date('Y/m/d', strtotime($student['joined_at'])); ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- معلومات الاتصال -->
                                                <div class="contact-info mb-3 pb-3 border-bottom">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <div class="d-flex align-items-center">
                                                        <i class="bi bi-calendar3 text-muted me-2"></i>
                                                        <span>العمر: <?php echo $student['age']; ?> سنة</span>
                                                    </div>
                                                        <div>
                                                        <button type="button" 
                                                                    class="btn btn-sm <?php echo $student['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"
                                                                data-bs-toggle="modal" 
                                                                    data-bs-target="#toggleStatusModal"
                                                                data-student-id="<?php echo $student['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                                    data-student-status="<?php echo $student['is_active']; ?>">
                                                                <i class="bi <?php echo $student['is_active'] ? 'bi-pause-fill' : 'bi-play-fill'; ?>"></i>
                                                                <?php echo $student['is_active'] ? 'إيقاف' : 'تفعيل'; ?>
                                                        </button>
                                                    </div>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-phone text-muted me-2"></i>
                                                            <span><?php echo htmlspecialchars($student['phone']); ?></span>
                                                        </div>
                                                        <a href="https://wa.me/<?php echo $student['phone']; ?>" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="bi bi-whatsapp"></i>
                                                            واتساب
                                                        </a>
                                                    </div>
                                                </div>

                                                <!-- متابعة الحفظ والمراجعة -->
                                                <div class="reports-section">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="mb-0">متابعة الحفظ والمراجعة</h6>
                                                        <a href="daily_report.php?student_id=<?php echo $student['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="bi bi-plus-lg"></i>
                                                            تقرير جديد
                                                        </a>
                                                    </div>
                                                    
                                                    <!-- جدول التقارير -->
                                                    <div class="weekly-report-table">
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered">
                                                                <thead>
                                                                    <tr class="text-center">
                                                                        <th>اليوم</th>
                                                                        <th>الحضور</th>
                                                                        <th>الحفظ</th>
                                                                        <th>المراجعة</th>
                                                                        <th>الدرجة</th>
                                                                        <th>النقاط</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    // إنشاء مصفوفة للأيام السبعة الماضية
                                                                    $days = [];
                                                                    for ($i = 0; $i < 7; $i++) {
                                                                        $date = date('Y-m-d', strtotime("-$i days"));
                                                                        $days[$date] = [
                                                                            'date' => $date,
                                                                            'day_name' => date('l', strtotime($date)),
                                                                            'report' => null
                                                                        ];
                                                                    }

                                                                    // تعبئة التقارير المتوفرة
                                                                    if (isset($student_reports[$student['id']])) {
                                                                        foreach ($student_reports[$student['id']] as $report) {
                                                                            if (isset($days[$report['report_date']])) {
                                                                                $days[$report['report_date']]['report'] = $report;
                                                                            }
                                                                        }
                                                                    }
                                                                    ?>

                                                                    <?php foreach ($days as $day): ?>
                                                                        <tr class="text-center">
                                                                            <td class="day-cell">
                                                                                <div class="arabic-day">
                                                                                    <?php 
                                                                                    $dayNames = [
                                                                                        'Sunday' => 'الأحد',
                                                                                        'Monday' => 'الإثنين',
                                                                                        'Tuesday' => 'الثلاثاء',
                                                                                        'Wednesday' => 'الأربعاء',
                                                                                        'Thursday' => 'الخميس',
                                                                                        'Friday' => 'الجمعة',
                                                                                        'Saturday' => 'السبت'
                                                                                    ];
                                                                                    echo $dayNames[$day['day_name']];
                                                                                    ?>
                                                    </div>
                                                                                <div class="date-small">
                                                                                    <?php echo date('d/m/Y', strtotime($day['date'])); ?>
                                                                                </div>
                                                                            </td>
                                                                            <td>
                                                                                <?php if ($day['report']): ?>
                                                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                                                <?php else: ?>
                                                                                    <i class="bi bi-x-circle-fill text-warning"></i>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php 
                                                                                if ($day['report']) {
                                                                                    $memorization = $day['report']['memorization_parts'];
                                                                                    // عرض الرقم بدون كسور إذا كان رقماً صحيحاً
                                                                                    echo '<span>' . (floor($memorization) == $memorization ? number_format($memorization) : number_format($memorization, 2)) . '</span>';
                                                                                } else {
                                                                                    echo '---';
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php 
                                                                                if ($day['report']) {
                                                                                    $revision = $day['report']['revision_parts'];
                                                                                    // عرض الرقم بدون كسور إذا كان رقماً صحيحاً
                                                                                    echo '<span>' . (floor($revision) == $revision ? number_format($revision) : number_format($revision, 2)) . '</span>';
                                                                                } else {
                                                                                    echo '---';
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php 
                                                                                if ($day['report']) {
                                                                                    // عرض الدرجة دائماً بدون كسور
                                                                                    echo number_format($day['report']['grade']) . '%';
                                                                                } else {
                                                                                    echo '---';
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php 
                                                                                if ($day['report']) {
                                                                                    // حساب النقاط (يمكن تعديل المعادلة حسب الحاجة)
                                                                                    $points = $day['report']['grade'] >= 90 ? 12 : 
                                                                                             ($day['report']['grade'] >= 80 ? 10 : 
                                                                                             ($day['report']['grade'] >= 70 ? 8 : 6));
                                                                                    echo $points;
                                                                                } else {
                                                                                    echo '0';
                                                                                }
                                                                                ?>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>

                        <!-- الطلاب الموقوفون -->
                        <?php 
                        $inactive_count = count($students) - $active_count;
                        if ($inactive_count > 0): 
                        ?>
                        <div class="inactive-students bg-light border-top">
                            <div class="p-3 bg-danger bg-opacity-10 border-bottom">
                                <h6 class="mb-0 text-danger">
                                    <i class="bi bi-pause-circle"></i>
                                    الحسابات الموقوفة
                                    <span class="badge bg-danger"><?php echo $inactive_count; ?></span>
                                </h6>
                            </div>
                            <div class="row g-0">
                                <?php foreach ($students as $index => $student): ?>
                                    <?php if (!$student['is_active']): ?>
                                        <div class="col-12 col-md-6 col-xl-4">
                                            <div class="student-card position-relative h-100">
                                                <div class="p-3 border-bottom h-100">
                                                    <!-- معلومات الطالب الأساسية -->
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="avatar-circle me-3">
                                                            <span class="avatar-text">
                                                                <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h5>
                                                        <div class="text-muted small">
                                                            انضم في <?php echo date('Y/m/d', strtotime($student['joined_at'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- معلومات الاتصال -->
                                                    <div class="contact-info mb-3 pb-3 border-bottom">
                                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                                            <div class="d-flex align-items-center">
                                                            <i class="bi bi-calendar3 text-muted me-2"></i>
                                                            <span>العمر: <?php echo $student['age']; ?> سنة</span>
                                                        </div>
                                                            <div>
                                                            <button type="button" 
                                                                        class="btn btn-sm <?php echo $student['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#toggleStatusModal"
                                                                    data-student-id="<?php echo $student['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                                    data-student-status="<?php echo $student['is_active']; ?>">
                                                                <i class="bi <?php echo $student['is_active'] ? 'bi-pause-fill' : 'bi-play-fill'; ?>"></i>
                                                                <?php echo $student['is_active'] ? 'إيقاف' : 'تفعيل'; ?>
                                                            </button>
                                                        </div>
                                                        </div>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-phone text-muted me-2"></i>
                                                                <span><?php echo htmlspecialchars($student['phone']); ?></span>
                                                    </div>
                                                            <a href="https://wa.me/<?php echo $student['phone']; ?>" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-success">
                                                                <i class="bi bi-whatsapp"></i>
                                                                واتساب
                                                            </a>
                                                </div>
                                            </div>

                                                    <!-- متابعة الحفظ والمراجعة -->
                                                    <div class="reports-section">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <h6 class="mb-0">متابعة الحفظ والمراجعة</h6>
                                                            <a href="daily_report.php?student_id=<?php echo $student['id']; ?>" 
                                                               class="btn btn-sm btn-primary">
                                                                <i class="bi bi-plus-lg"></i>
                                                                تقرير جديد
                                                            </a>
                                        </div>

                                                        <!-- جدول التقارير -->
                                                        <div class="weekly-report-table">
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered">
                                                                    <thead>
                                                                        <tr class="text-center">
                                                                            <th>اليوم</th>
                                                                            <th>الحضور</th>
                                                                            <th>الحفظ</th>
                                                                            <th>المراجعة</th>
                                                                            <th>الدرجة</th>
                                                                            <th>النقاط</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        // إنشاء مصفوفة للأيام السبعة الماضية
                                                                        $days = [];
                                                                        for ($i = 0; $i < 7; $i++) {
                                                                            $date = date('Y-m-d', strtotime("-$i days"));
                                                                            $days[$date] = [
                                                                                'date' => $date,
                                                                                'day_name' => date('l', strtotime($date)),
                                                                                'report' => null
                                                                            ];
                                                                        }

                                                                        // تعبئة التقارير المتوفرة
                                                                        if (isset($student_reports[$student['id']])) {
                                                                            foreach ($student_reports[$student['id']] as $report) {
                                                                                if (isset($days[$report['report_date']])) {
                                                                                    $days[$report['report_date']]['report'] = $report;
                                                                                }
                                                                            }
                                                                        }
                                                                        ?>

                                                                        <?php foreach ($days as $day): ?>
                                                                            <tr class="text-center">
                                                                                <td class="day-cell">
                                                                                    <div class="arabic-day">
                                                                                        <?php 
                                                                                        $dayNames = [
                                                                                            'Sunday' => 'الأحد',
                                                                                            'Monday' => 'الإثنين',
                                                                                            'Tuesday' => 'الثلاثاء',
                                                                                            'Wednesday' => 'الأربعاء',
                                                                                            'Thursday' => 'الخميس',
                                                                                            'Friday' => 'الجمعة',
                                                                                            'Saturday' => 'السبت'
                                                                                        ];
                                                                                        echo $dayNames[$day['day_name']];
                                                                                        ?>
                            </div>
                                                                                    <div class="date-small">
                                                                                        <?php echo date('d/m/Y', strtotime($day['date'])); ?>
                        </div>
                                                                                </td>
                                                                                <td>
                                                                                    <?php if ($day['report']): ?>
                                                                                        <i class="bi bi-check-circle-fill text-success"></i>
                                                                                    <?php else: ?>
                                                                                        <i class="bi bi-x-circle-fill text-warning"></i>
                        <?php endif; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php 
                                                                                    if ($day['report']) {
                                                                                        $memorization = $day['report']['memorization_parts'];
                                                                                        // عرض الرقم بدون كسور إذا كان رقماً صحيحاً
                                                                                        echo '<span>' . (floor($memorization) == $memorization ? number_format($memorization) : number_format($memorization, 2)) . '</span>';
                                                                                    } else {
                                                                                        echo '---';
                                                                                    }
                                                                                    ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php 
                                                                                    if ($day['report']) {
                                                                                        $revision = $day['report']['revision_parts'];
                                                                                        // عرض الرقم بدون كسور إذا كان رقماً صحيحاً
                                                                                        echo '<span>' . (floor($revision) == $revision ? number_format($revision) : number_format($revision, 2)) . '</span>';
                                                                                    } else {
                                                                                        echo '---';
                                                                                    }
                                                                                    ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php 
                                                                                    if ($day['report']) {
                                                                                        // عرض الدرجة دائماً بدون كسور
                                                                                        echo number_format($day['report']['grade']) . '%';
                                                                                    } else {
                                                                                        echo '---';
                                                                                    }
                                                                                    ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php 
                                                                                    if ($day['report']) {
                                                                                        // حساب النقاط (يمكن تعديل المعادلة حسب الحاجة)
                                                                                        $points = $day['report']['grade'] >= 90 ? 12 : 
                                                                                                 ($day['report']['grade'] >= 80 ? 10 : 
                                                                                                 ($day['report']['grade'] >= 70 ? 8 : 6));
                                                                                        echo $points;
                                                                                    } else {
                                                                                        echo '0';
                                                                                    }
                                                                                    ?>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                </div>
            </div>
        </div>
                </div>
                    </div>
                </div>
                                    <?php endif; ?>
                            <?php endforeach; ?>
                    </div>
                    </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                </div>
        </div>

    </div>
</div>



<!-- Modal تفعيل/إيقاف الحساب -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="status-icon mb-4">
                    <i class="bi bi-question-circle text-warning h1"></i>
                </div>
                <h4 class="modal-title mb-3" id="toggleStatusTitle">تأكيد العملية</h4>
                <p class="text-muted mb-4" id="toggleStatusMessage">هل أنت متأكد من تغيير حالة حساب الطالب؟</p>
                
                <form method="POST" id="toggleStatusForm">
                    <input type="hidden" name="student_id" id="toggleStatusStudentId">
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                            إلغاء
                        </button>
                        <button type="submit" name="toggle_status" class="btn px-4" id="toggleStatusBtn">
                            تأكيد
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// تهيئة مودال تفعيل/إيقاف الحساب
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة مودال تفعيل/إيقاف الحساب
    const toggleStatusModal = document.getElementById('toggleStatusModal');
    if (toggleStatusModal) {
        toggleStatusModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const studentId = button.getAttribute('data-student-id');
    const studentName = button.getAttribute('data-student-name');
    const isActive = button.getAttribute('data-student-status') === '1';
    
    const modal = this;
    const title = modal.querySelector('#toggleStatusTitle');
    const message = modal.querySelector('#toggleStatusMessage');
    const submitBtn = modal.querySelector('#toggleStatusBtn');
    const icon = modal.querySelector('.status-icon i');
    
    // تعيين معرف الطالب في النموذج
    document.getElementById('toggleStatusStudentId').value = studentId;
    
    if (isActive) {
        // إيقاف الحساب
        title.textContent = 'تأكيد إيقاف الحساب';
        message.innerHTML = `هل أنت متأكد من إيقاف حساب الطالب <strong class="text-danger">${studentName}</strong>؟`;
        submitBtn.className = 'btn btn-danger px-4';
        submitBtn.innerHTML = '<i class="bi bi-pause-fill me-1"></i> إيقاف الحساب';
                icon.className = 'bi bi-pause-circle text-danger h1';
    } else {
        // تفعيل الحساب
        title.textContent = 'تأكيد تفعيل الحساب';
        message.innerHTML = `هل أنت متأكد من تفعيل حساب الطالب <strong class="text-success">${studentName}</strong>؟`;
        submitBtn.className = 'btn btn-success px-4';
        submitBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i> تفعيل الحساب';
                icon.className = 'bi bi-play-circle text-success h1';
            }
        });
    }

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    if (forms.length > 0) {
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }
});
</script>

<style>
/* تحديث أنماط بطاقة الطالب */
.student-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 1rem;
    border: 1px solid #eee;
}

/* تمييز البطاقات حسب الترتيب */
.col-12:nth-child(odd) .student-card {
    background-color: #ffffff;
}

.col-12:nth-child(even) .student-card {
    background-color: #f8f9fa;
}

/* تحسين مظهر البطاقات عند التحويم */
.student-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-color: #e0e0e0;
}

/* تمييز حدود البطاقات */
.col-12:nth-child(odd) .student-card {
    border-left: 3px solid #4CAF50;
}

.col-12:nth-child(even) .student-card {
    border-left: 3px solid #2196F3;
}

@media (max-width: 768px) {
    .student-card {
        margin-bottom: 0.5rem;
        border-radius: 0;
    }

    .col-12:nth-child(odd) .student-card {
        border-left: none;
        border-right: 3px solid #4CAF50;
    }

    .col-12:nth-child(even) .student-card {
        border-left: none;
        border-right: 3px solid #2196F3;
    }
}

.avatar-circle {
    width: 48px;
    height: 48px;
    background-color: #4CAF50;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-text {
    color: white;
    font-size: 20px;
    font-weight: bold;
}

.contact-info {
    font-size: 0.9rem;
}

/* تحديث أنماط قسم التقارير */
.reports-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
}

.weekly-report-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.weekly-report-table table {
    margin-bottom: 0;
}

.weekly-report-table th {
    background-color: #f8f9fa;
    font-weight: 500;
    padding: 12px;
    border-color: #dee2e6;
}

.weekly-report-table td {
    padding: 1px;
    vertical-align: middle;
    border-color: #dee2e6;
}

.day-cell {
    min-width: 120px;
}

.arabic-day {
    font-weight: 500;
    color: #495057;
}

.date-small {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 2px;
}

.bi-check-circle-fill {
    font-size: 1.2rem;
    color: #28a745;
}

.bi-x-circle-fill {
    font-size: 1.2rem;
    color: #ffc107;
}

/* تحسين عرض الجدول على الشاشات الصغيرة */
@media (max-width: 768px) {
    /* إزالة الهوامش والحشو */
    .container-fluid {
        padding: 0;
    }

    .card {
        border-radius: 0;
        border: none;
        margin: 0;
    }

    .card-body {
        padding: 0;
    }

    .reports-section {
        margin: 0;
        padding: 0;
        background: transparent;
    }

    .weekly-report-table {
        margin: 0;
        border-radius: 0;
    }

    /* تحسين مظهر الجدول */
    .weekly-report-table table {
        font-size: 0.8rem;
        border: none;
    }

    .weekly-report-table th {
        padding: 8px 4px;
        font-weight: 600;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        white-space: nowrap;
    }

    .weekly-report-table td {
        padding: 8px 4px;
        border: 1px solid #dee2e6;
        vertical-align: middle;
    }

    /* تحسين عرض خلية اليوم */
    .day-cell {
        min-width: auto;
        text-align: right;
        padding-right: 8px !important;
    }

    .arabic-day {
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .date-small {
        font-size: 0.65rem;
        color: #666;
    }

    /* تحسين عرض الأيقونات */
    .bi-check-circle-fill {
        color: #28a745;
        font-size: 1rem;
    }

    .bi-x-circle-fill {
        color: #ffc107;
        font-size: 1rem;
    }

    /* تنسيق الأرقام والنصوص */
    .weekly-report-table td:nth-child(3),
    .weekly-report-table td:nth-child(4),
    .weekly-report-table td:nth-child(5),
    .weekly-report-table td:nth-child(6) {
        font-size: 0.8rem;
        font-weight: 500;
    }

    /* إخفاء كلمة أجزاء في الموبايل */
    .weekly-report-table td:nth-child(3) span:after,
    .weekly-report-table td:nth-child(4) span:after {
        content: 'ج';
        font-size: 0.7rem;
        color: #666;
        margin-right: 1px;
    }

    /* تنسيق الخلايا الفارغة */
    .weekly-report-table td:empty,
    .weekly-report-table td:contains('---') {
        color: #ccc;
    }

    /* تحسين عرض الصفوف */
    .weekly-report-table tr:nth-child(even) {
        background-color: #fbfbfb;
    }

    /* تعديل عرض الأعمدة */
    .weekly-report-table th:first-child,
    .weekly-report-table td:first-child {
        width: 25%;
    }

    .weekly-report-table th:nth-child(2),
    .weekly-report-table td:nth-child(2) {
        width: 12%;
    }

    .weekly-report-table th:nth-child(3),
    .weekly-report-table td:nth-child(3),
    .weekly-report-table th:nth-child(4),
    .weekly-report-table td:nth-child(4) {
        width: 18%;
    }

    .weekly-report-table th:nth-child(5),
    .weekly-report-table td:nth-child(5),
    .weekly-report-table th:nth-child(6),
    .weekly-report-table td:nth-child(6) {
        width: 13%;
    }
}

/* تحسينات عامة للجدول */
.weekly-report-table {
    box-shadow: none;
    background: white;
}

.weekly-report-table table {
    border-collapse: collapse;
    width: 100%;
}

/* تنسيق رأس الجدول */
.weekly-report-table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 1;
}
</style>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 