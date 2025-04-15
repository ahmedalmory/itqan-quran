<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول وصلاحية المستخدم
if (!isLoggedIn() || !hasRole('department_admin')) {
    $_SESSION['error'] = "غير مصرح لك بالوصول إلى هذه الصفحة";
    header('Location: ../login.php');
    exit();
}

// التحقق من وجود معرف الحلقة
if (!isset($_GET['circle_id'])) {
    $_SESSION['error'] = "لم يتم تحديد الحلقة";
    header('Location: index.php');
    exit();
}

$circle_id = $_GET['circle_id'];
$user_id = $_SESSION['user_id'];

// الحصول على معلومات الحلقة والتحقق من الصلاحيات
$circle_sql = "
    SELECT c.*, d.name as department_name 
    FROM study_circles c
    JOIN departments d ON c.department_id = d.id
    JOIN department_admins da ON d.id = da.department_id
    WHERE c.id = ? AND da.user_id = ?
";
$circle_stmt = $conn->prepare($circle_sql);
$circle_stmt->bind_param("ii", $circle_id, $user_id);
$circle_stmt->execute();
$circle = $circle_stmt->get_result()->fetch_assoc();

if (!$circle) {
    $_SESSION['error'] = "غير مصرح لك بإدارة هذه الحلقة";
    header('Location: index.php');
    exit();
}

// تحديد التاريخ المطلوب
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// الحصول على تقارير اليوم
$reports_sql = "
    SELECT dr.*, 
           u.name as student_name,
           CONCAT(
               (SELECT name FROM surahs WHERE id = dr.memorization_from_surah),
               ' (', dr.memorization_from_verse, ') - ',
               (SELECT name FROM surahs WHERE id = dr.memorization_to_surah),
               ' (', dr.memorization_to_verse, ')'
           ) as memorization_range
    FROM daily_reports dr
    JOIN users u ON dr.student_id = u.id
    JOIN circle_students cs ON dr.student_id = cs.student_id
    WHERE cs.circle_id = ? AND DATE(dr.report_date) = ?
    ORDER BY dr.created_at DESC
";
$reports_stmt = $conn->prepare($reports_sql);
$reports_stmt->bind_param("is", $circle_id, $date);
$reports_stmt->execute();
$reports = $reports_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير الحلقة - <?php echo htmlspecialchars($circle['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">تقارير الحلقة</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">الرئيسية</a></li>
                        <li class="breadcrumb-item">
                            <a href="circles.php?department_id=<?php echo $circle['department_id']; ?>">
                                <?php echo htmlspecialchars($circle['department_name']); ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($circle['name']); ?></li>
                    </ol>
                </nav>
            </div>
            <form class="d-flex gap-2">
                <input type="hidden" name="circle_id" value="<?php echo $circle_id; ?>">
                <input type="date" class="form-control" name="date" value="<?php echo $date; ?>" max="<?php echo date('Y-m-d'); ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i>
                    عرض
                </button>
            </form>
        </div>

        <?php if (empty($reports)): ?>
            <div class="alert alert-info">
                لا توجد تقارير لهذا اليوم.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>الطالب</th>
                            <th>المحفوظات</th>
                            <th>الحفظ</th>
                            <th>المراجعة</th>
                            <th>الدرجة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($report['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($report['memorization_range']); ?></td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo number_format($report['memorization_parts'], 1); ?> جزء
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo number_format($report['revision_parts'], 1); ?> جزء
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo $report['grade']; ?>/100
                                </span>
                            </td>
                            <td>
                                <?php if ($report['notes']): ?>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="popover"
                                        data-bs-content="<?php echo htmlspecialchars($report['notes']); ?>">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تفعيل tooltips و popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    </script>
</body>
</html>
