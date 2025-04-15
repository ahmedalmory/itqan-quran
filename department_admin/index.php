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

// الحصول على معلومات الأقسام التي يديرها المستخدم
$user_id = $_SESSION['user_id'];
$departments_sql = "
    SELECT d.*, 
           (SELECT COUNT(*) FROM study_circles WHERE department_id = d.id) as circles_count,
           (SELECT COUNT(*) FROM study_circles sc 
            JOIN circle_students cs ON sc.id = cs.circle_id 
            WHERE sc.department_id = d.id) as students_count
    FROM departments d
    JOIN department_admins da ON d.id = da.department_id
    WHERE da.user_id = ?
    ORDER BY d.name
";

$stmt = $conn->prepare($departments_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم مدير القسم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .department-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .department-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        .department-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .department-stat i {
            font-size: 1.2rem;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h1>لوحة تحكم مدير القسم</h1>
                <p class="text-muted">مرحباً <?php echo htmlspecialchars($_SESSION['name']); ?></p>
            </div>
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

        <?php if (empty($departments)): ?>
            <div class="alert alert-info">
                لم يتم تعيينك كمدير لأي قسم بعد.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                <?php foreach ($departments as $department): ?>
                <div class="col">
                    <div class="card h-100 department-card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($department['name']); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="department-stat">
                                        <i class="bi bi-grid"></i>
                                        <div>
                                            <small class="text-muted d-block">الحلقات</small>
                                            <strong><?php echo $department['circles_count']; ?> حلقة</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="department-stat">
                                        <i class="bi bi-people"></i>
                                        <div>
                                            <small class="text-muted d-block">الطلاب</small>
                                            <strong><?php echo $department['students_count']; ?> طالب</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <a href="circles.php?department_id=<?php echo $department['id']; ?>" 
                                   class="btn btn-primary w-100">
                                    <i class="bi bi-grid"></i>
                                    إدارة الحلقات
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
