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

$user_id = (int)$_GET['id'];

// الحصول على معلومات المستخدم
$stmt = $conn->prepare("
    SELECT u.*, c.name AS country_name, c.CountryCode
    FROM users u
    LEFT JOIN countries c ON CAST(u.country_id AS CHAR) = c.ID
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users.php');
    exit;
}

// الحصول على الحلقات المرتبطة
$circles = [];
if ($user['role'] === 'teacher') {
    // الحلقات التي يدرسها المعلم
    $stmt = $conn->prepare("
        SELECT sc.*, d.name as department_name,
               (SELECT COUNT(*) FROM circle_students WHERE circle_id = sc.id) as students_count
        FROM study_circles sc
        JOIN departments d ON CAST(sc.department_id AS CHAR) = CAST(d.id AS CHAR)
        WHERE sc.teacher_id = ?
        ORDER BY sc.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $circles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($user['role'] === 'student') {
    // الحلقات التي يدرس فيها الطالب
    $stmt = $conn->prepare("
        SELECT sc.*, d.name as department_name, cs.created_at as joined_at,
               u.name as teacher_name
        FROM circle_students cs
        JOIN study_circles sc ON cs.circle_id = sc.id
        JOIN departments d ON CAST(sc.department_id AS CHAR) = CAST(d.id AS CHAR)
        JOIN users u ON sc.teacher_id = u.id
        WHERE cs.student_id = ?
        ORDER BY cs.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $circles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// الحصول على التقارير اليومية للطالب
$reports = [];
if ($user['role'] === 'student') {
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
            sc.name as circle_name
        FROM daily_reports dr
        JOIN study_circles sc ON dr.study_circle_id = sc.id
        LEFT JOIN surahs s1 ON dr.memorization_from_surah = s1.id
        LEFT JOIN surahs s2 ON dr.memorization_to_surah = s2.id
        WHERE dr.student_id = ?
        ORDER BY dr.report_date DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'تفاصيل المستخدم: ' . $user['name'];
ob_start();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="users.php">المستخدمين</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($user['name']); ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> عودة للمستخدمين
            </a>
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
                        <div class="avatar-circle mx-auto mb-3" style="width: 100px; height: 100px;">
                            <span class="avatar-text" style="font-size: 40px;">
                                <?php echo mb_substr($user['name'], 0, 1, 'UTF-8'); ?>
                            </span>
                        </div>
                        <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                        <span class="badge bg-<?php 
                            echo $user['role'] === 'super_admin' ? 'danger' : 
                                ($user['role'] === 'teacher' ? 'success' : 'primary');
                        ?>">
                            <?php echo $user['role'] === 'super_admin' ? 'مشرف' : 
                                ($user['role'] === 'teacher' ? 'معلم' : 'طالب'); ?>
                        </span>
                    </div>

                    <div class="user-details">
                        <div class="mb-3">
                            <label class="text-muted mb-1">البريد الإلكتروني</label>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-envelope text-muted me-2"></i>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-muted mb-1">رقم الهاتف</label>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-phone text-muted me-2"></i>
                                <a href="https://wa.me/<?php echo $user['phone']; ?>" 
                                   class="text-decoration-none" target="_blank">
                                    <i class="bi bi-whatsapp text-success"></i>
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted mb-1">الجنس</label>
                            <div class="d-flex align-items-center">
                                <i class="bi <?php echo $user['gender'] === 'male' ? 'bi-gender-male text-primary' : 'bi-gender-female text-danger'; ?> me-2"></i>
                                <?php echo $user['gender'] === 'male' ? 'ذكر' : 'أنثى'; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted mb-1">الدولة</label>
                            <div class="d-flex align-items-center">
                                <i class="flag flag-<?php echo strtolower($user['CountryCode']); ?> me-2"></i>
                                <?php echo htmlspecialchars($user['country_name']); ?>
                            </div>
                        </div>

                        <div>
                            <label class="text-muted mb-1">تاريخ التسجيل</label>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar3 text-muted me-2"></i>
                                <?php echo date('Y/m/d', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الحلقات والتقارير -->
        <div class="col-md-8">
            <?php if ($user['role'] === 'teacher'): ?>
                <!-- حلقات المعلم -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-book"></i>
                            الحلقات التي يدرسها
                            <span class="badge bg-light text-success"><?php echo count($circles); ?></span>
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
                                            <th>القسم</th>
                                            <th>عدد الطلاب</th>
                                            <th>تاريخ الإنشاء</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($circles as $circle): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($circle['name']); ?></td>
                                                <td><?php echo htmlspecialchars($circle['department_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo $circle['students_count']; ?> طالب
                                                    </span>
                                                </td>
                                                <td><?php echo date('Y/m/d', strtotime($circle['created_at'])); ?></td>
                                                <td>
                                                    <a href="circle_students.php?circle_id=<?php echo $circle['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i>
                                                        عرض
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
            <?php elseif ($user['role'] === 'student'): ?>
                <!-- حلقات الطالب -->
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
                                                <td><?php echo date('Y/m/d', strtotime($circle['joined_at'])); ?></td>
                                                <td>
                                                    <a href="circle_students.php?circle_id=<?php echo $circle['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i>
                                                        عرض
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
                                            <th>مقدار الحفظ</th>
                                            <th>تفاصيل الحفظ</th>
                                            <th>مقدار المراجعة</th>
                                            <th>التقييم</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?php echo date('Y/m/d', strtotime($report['report_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($report['circle_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo $report['memorization_parts']; ?> أجزاء
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($report['memorization']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $report['revision_parts']; ?> أجزاء
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
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    background-color: #4CAF50;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-text {
    color: white;
    font-weight: bold;
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