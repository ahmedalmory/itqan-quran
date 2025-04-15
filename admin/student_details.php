<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// التأكد من أن المستخدم مدير النظام
requireRole('super_admin');

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$student_id = (int)$_GET['id'];

// الحصول على معلومات الطالب
$stmt = $conn->prepare("
    SELECT u.*, c.name AS country_name, c.CountryCode,
           (SELECT COUNT(*) FROM circle_students WHERE student_id = u.id) as circles_count,
           (SELECT COUNT(*) FROM daily_reports WHERE student_id = u.id) as reports_count,
           (SELECT SUM(memorization_parts) FROM daily_reports WHERE student_id = u.id) as total_memorized_parts,
           (SELECT SUM(revision_parts) FROM daily_reports WHERE student_id = u.id) as total_revised_parts
    FROM users u
    LEFT JOIN countries c ON CAST(u.country_id AS CHAR) = c.ID
    WHERE u.id = ? AND u.role = 'student'
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    header('Location: users.php');
    exit;
}

// الحصول على الحلقات المسجل فيها الطالب
$stmt = $conn->prepare("
    SELECT 
        sc.*,
        d.name as department_name,
        cs.created_at as joined_at,
        u.name as teacher_name,
        (SELECT COUNT(*) FROM daily_reports dr 
         JOIN circle_students cs2 ON cs2.student_id = dr.student_id
         WHERE dr.student_id = ? AND cs2.circle_id = sc.id) as reports_count,
        (SELECT SUM(memorization_parts) FROM daily_reports dr
         JOIN circle_students cs2 ON cs2.student_id = dr.student_id
         WHERE dr.student_id = ? AND cs2.circle_id = sc.id) as total_memorized_parts,
        (SELECT AVG(grade) FROM daily_reports dr
         JOIN circle_students cs2 ON cs2.student_id = dr.student_id
         WHERE dr.student_id = ? AND cs2.circle_id = sc.id) as avg_grade
    FROM circle_students cs
    JOIN study_circles sc ON cs.circle_id = sc.id
    JOIN departments d ON CAST(sc.department_id AS CHAR) = CAST(d.id AS CHAR)
    JOIN users u ON sc.teacher_id = u.id
    WHERE cs.student_id = ?
    ORDER BY cs.created_at DESC
");
$stmt->bind_param("iiii", $student_id, $student_id, $student_id, $student_id);
$stmt->execute();
$circles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// الحصول على آخر التقارير
$stmt = $conn->prepare("
    SELECT 
        dr.report_date,
        CONCAT(
            (SELECT name FROM surahs WHERE id = dr.memorization_from_surah),
            ' (', dr.memorization_from_verse, ') - ',
            (SELECT name FROM surahs WHERE id = dr.memorization_to_surah),
            ' (', dr.memorization_to_verse, ')'
        ) as memorization,
        dr.memorization_parts,
        dr.revision_parts,
        dr.grade,
        dr.notes,
        sc.name as circle_name,
        u.name as teacher_name
    FROM daily_reports dr
    JOIN circle_students cs ON cs.student_id = dr.student_id
    JOIN study_circles sc ON cs.circle_id = sc.id
    JOIN users u ON sc.teacher_id = u.id
    WHERE dr.student_id = ?
    ORDER BY dr.report_date DESC
    LIMIT 10
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'تفاصيل الطالب: ' . $student['name'];
ob_start();
?>

<div class="container-fluid py-4">
    <!-- شريط التنقل -->
    <div class="row mb-4">
        <div class="col-md-6">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="users.php">المستخدمين</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($student['name']); ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="btn-group">
                <a href="users.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-right"></i> عودة للمستخدمين
                </a>
                <a href="?action=edit&id=<?php echo $student['id']; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> تعديل البيانات
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- بطاقة المعلومات الشخصية -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-circle"></i>
                        المعلومات الشخصية
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mx-auto mb-3">
                            <span class="avatar-text">
                                <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                            </span>
                        </div>
                        <h4><?php echo htmlspecialchars($student['name']); ?></h4>
                    </div>

                    <div class="user-details">
                        <div class="mb-3">
                            <label class="text-muted mb-1">البريد الإلكتروني</label>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-envelope text-muted me-2"></i>
                                <?php echo htmlspecialchars($student['email']); ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-muted mb-1">رقم الهاتف</label>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-phone text-muted me-2"></i>
                                <a href="https://wa.me/<?php echo $student['phone']; ?>" 
                                   class="text-decoration-none" target="_blank">
                                    <i class="bi bi-whatsapp text-success"></i>
                                    <?php echo htmlspecialchars($student['phone']); ?>
                                </a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted mb-1">الدولة</label>
                            <div class="d-flex align-items-center">
                                <i class="flag flag-<?php echo strtolower($student['CountryCode']); ?> me-2"></i>
                                <?php echo htmlspecialchars($student['country_name']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- إحصائيات سريعة -->
                    <hr>
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <div class="p-3 border rounded">
                                <h3 class="mb-1"><?php echo $student['circles_count']; ?></h3>
                                <small class="text-muted">الحلقات</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded">
                                <h3 class="mb-1"><?php echo $student['reports_count']; ?></h3>
                                <small class="text-muted">التقارير</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded">
                                <h3 class="mb-1"><?php echo number_format($student['total_memorized_parts'], 1); ?></h3>
                                <small class="text-muted">أوجه الحفظ</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded">
                                <h3 class="mb-1"><?php echo number_format($student['total_revised_parts'], 1); ?></h3>
                                <small class="text-muted">أوجه المراجعة</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- الحلقات المسجل فيها -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-book"></i>
                        الحلقات المسجل فيها
                        <span class="badge bg-light text-primary"><?php echo count($circles); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($circles)): ?>
                        <div class="text-center text-muted">
                            <i class="bi bi-info-circle"></i>
                            لا يوجد حلقات مسجلة
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>اسم الحلقة</th>
                                        <th>المعلم</th>
                                        <th>القسم</th>
                                        <th>الإحصائيات</th>
                                        <th>تاريخ الانضمام</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($circles as $circle): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($circle['name']); ?></td>
                                            <td><?php echo htmlspecialchars($circle['teacher_name']); ?></td>
                                            <td><?php echo htmlspecialchars($circle['department_name']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-success" title="عدد التقارير">
                                                        <i class="bi bi-journal-text"></i>
                                                        <?php echo $circle['reports_count']; ?>
                                                    </span>
                                                    <span class="badge bg-info" title="الأجزاء المحفوظة">
                                                        <i class="bi bi-book"></i>
                                                        <?php echo number_format($circle['total_memorized_parts'], 1); ?>
                                                    </span>
                                                    <span class="badge bg-warning" title="متوسط التقييم">
                                                        <i class="bi bi-star"></i>
                                                        <?php echo number_format($circle['avg_grade'], 1); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><?php echo date('Y/m/d', strtotime($circle['joined_at'])); ?></td>
                                            <td>
                                                <a href="circle_students.php?circle_id=<?php echo $circle['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye"></i>
                                                    عرض الحلقة
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- آخر التقارير -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-journal-text"></i>
                        آخر التقارير
                        <span class="badge bg-light text-info"><?php echo count($reports); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reports)): ?>
                        <div class="text-center text-muted">
                            <i class="bi bi-info-circle"></i>
                            لا يوجد تقارير مسجلة
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>الحلقة</th>
                                        <th>المعلم</th>
                                        <th>أوجه الحفظ</th>
                                        <th>تفاصيل الحفظ</th>
                                        <th>أوجه المراجعة</th>
                                        <th>التقييم</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td><?php echo date('Y/m/d', strtotime($report['report_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($report['circle_name']); ?></td>
                                            <td><?php echo htmlspecialchars($report['teacher_name']); ?></td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo $report['memorization_parts']; ?> 
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['memorization']); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $report['revision_parts']; ?> 
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $report['grade'] >= 8 ? 'success' : 
                                                        ($report['grade'] >= 5 ? 'warning' : 'danger');
                                                ?>">
                                                    <?php echo $report['grade']; ?>/10
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #4CAF50;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-text {
    color: white;
    font-weight: bold;
    font-size: 40px;
}

.user-details label {
    font-size: 0.875rem;
    display: block;
}

.flag {
    width: 20px;
    height: 15px;
    display: inline-block;
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
    vertical-align: middle;
}

/* تنسيق خاص لكل دولة */
.flag-sa { background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480"><path fill="%23006c35" d="M0 0h640v480H0z"/><path fill="%23fff" d="M144 144h352v192H144z"/></svg>'); }
.flag-eg { background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480"><path fill="%23ce1126" d="M0 0h640v480H0z"/><path fill="%23fff" d="M0 160h640v160H0z"/><path d="M0 320h640v160H0z"/></svg>'); }
/* يمكنك إضافة المزيد من الأعلام حسب الحاجة */
</style>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 