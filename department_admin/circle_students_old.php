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
    SELECT c.*, d.name as department_name, d.student_gender 
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

// الحصول على قائمة الطلاب في الحلقة
$students_sql = "
    SELECT u.*, cs.created_at as join_date,
           (SELECT COUNT(*) FROM daily_reports WHERE student_id = u.id) as reports_count
    FROM users u
    JOIN circle_students cs ON u.id = cs.student_id
    WHERE cs.circle_id = ? AND u.role = 'student'
    ORDER BY u.name
";
$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("i", $circle_id);
$students_stmt->execute();
$students = $students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// الحصول على قائمة الطلاب المتاحين للإضافة
$available_students_sql = "
    SELECT u.*
    FROM users u
    WHERE u.role = 'student' 
    AND u.gender = ?
    AND NOT EXISTS (
        SELECT 1 FROM circle_students 
        WHERE student_id = u.id AND circle_id = ?
    )
    ORDER BY u.name
";
$available_stmt = $conn->prepare($available_students_sql);
$available_stmt->bind_param("si", $circle['student_gender'], $circle_id);
$available_stmt->execute();
$available_students = $available_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلاب الحلقة - <?php echo htmlspecialchars($circle['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">طلاب الحلقة</h1>
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
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="bi bi-person-plus"></i>
                إضافة طالب
            </button>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($students)): ?>
            <div class="alert alert-info">
                لا يوجد طلاب في هذه الحلقة حتى الآن.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>اسم الطالب</th>
                            <th>العمر</th>
                            <th>رقم الجوال</th>
                            <th>تاريخ الانضمام</th>
                            <th>عدد التقارير</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo $student['age']; ?></td>
                                <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($student['join_date'])); ?></td>
                                <td><?php echo $student['reports_count']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../student/reports.php?student_id=<?php echo $student['id']; ?>" 
                                           class="btn btn-outline-primary">
                                            <i class="bi bi-journal-text"></i>
                                            التقارير
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-danger"
                                                onclick="confirmRemoveStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')">
                                            <i class="bi bi-person-x"></i>
                                            حذف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal إضافة طالب -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="add_student_to_circle.php">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة طالب للحلقة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="circle_id" value="<?php echo $circle_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">اختر الطالب</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">اختر طالباً...</option>
                                <?php foreach ($available_students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['name']); ?>
                                        (<?php echo $student['age']; ?> سنة)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة الطالب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmRemoveStudent(studentId, studentName) {
            if (confirm(`هل أنت متأكد من حذف الطالب "${studentName}" من الحلقة؟`)) {
                window.location.href = `remove_student_from_circle.php?circle_id=<?php echo $circle_id; ?>&student_id=${studentId}`;
            }
        }
    </script>
</body>
</html>
