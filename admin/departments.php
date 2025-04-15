<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول وصلاحية المستخدم
if (!isLoggedIn() || !hasRole('admin') && !hasRole('super_admin')) {
    $_SESSION['error'] = "غير مصرح لك بالوصول إلى هذه الصفحة";
    header('Location: ../login.php');
    exit();
}

// معالجة AJAX request لجلب المديرين المتاحين
if (isset($_GET['department_id'])) {
    header('Content-Type: application/json');
    $dept_id = (int)$_GET['department_id'];
    
    $available_admins_sql = "
        SELECT u.id, u.name 
        FROM users u 
        WHERE u.role = 'department_admin' 
        AND u.id NOT IN (
            SELECT user_id 
            FROM department_admins 
            WHERE department_id = ?
        )
        ORDER BY u.name
    ";
    
    $stmt = $conn->prepare($available_admins_sql);
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $available_admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode($available_admins);
    exit();
}

// الحصول على قائمة الدول
$countries_sql = "SELECT ID, Name, AltName FROM countries ORDER BY `Order`";
$countries = $conn->query($countries_sql)->fetch_all(MYSQLI_ASSOC);

// معالجة إضافة/حذف مدير قسم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_admin'])) {
        $dept_id = (int)$_POST['department_id'];
        $admin_id = (int)$_POST['admin_id'];
        
        // التحقق من عدم وجود المدير مسبقاً
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM department_admins WHERE department_id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $dept_id, $admin_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $_SESSION['error'] = "هذا المدير مضاف مسبقاً للقسم";
        } else {
            $stmt = $conn->prepare("INSERT INTO department_admins (department_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $dept_id, $admin_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "تمت إضافة المدير بنجاح";
            } else {
                $_SESSION['error'] = "حدث خطأ أثناء إضافة المدير";
            }
        }
    } elseif (isset($_POST['remove_admin'])) {
        $dept_id = (int)$_POST['department_id'];
        $admin_id = (int)$_POST['admin_id'];
        
        $stmt = $conn->prepare("DELETE FROM department_admins WHERE department_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $dept_id, $admin_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "تم حذف المدير بنجاح";
        } else {
            $_SESSION['error'] = "حدث خطأ أثناء حذف المدير";
        }
    } elseif (isset($_POST['add_department'])) {
        $name = sanitize_input($_POST['name']);
        $student_gender = sanitize_input($_POST['student_gender']);
        $work_friday = isset($_POST['work_friday']) ? 1 : 0;
        $work_saturday = isset($_POST['work_saturday']) ? 1 : 0;
        $work_sunday = isset($_POST['work_sunday']) ? 1 : 0;
        $work_monday = isset($_POST['work_monday']) ? 1 : 0;
        $work_tuesday = isset($_POST['work_tuesday']) ? 1 : 0;
        $work_wednesday = isset($_POST['work_wednesday']) ? 1 : 0;
        $work_thursday = isset($_POST['work_thursday']) ? 1 : 0;
        $monthly_fees = (int)$_POST['monthly_fees'];
        $quarterly_fees = (int)$_POST['quarterly_fees'];
        $biannual_fees = (int)$_POST['biannual_fees'];
        $annual_fees = (int)$_POST['annual_fees'];
        $restrict_countries = isset($_POST['restrict_countries']) ? 1 : 0;
        $selected_countries = $_POST['countries'] ?? [];
        
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO departments (
                    name, student_gender, 
                    work_friday, work_saturday, work_sunday, work_monday, 
                    work_tuesday, work_wednesday, work_thursday,
                    monthly_fees, quarterly_fees, biannual_fees, annual_fees,
                    restrict_countries
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stmt->bind_param(
                "ssiiiiiiiiiiii",
                $name, $student_gender,
                $work_friday, $work_saturday, $work_sunday, $work_monday,
                $work_tuesday, $work_wednesday, $work_thursday,
                $monthly_fees, $quarterly_fees, $biannual_fees, $annual_fees,
                $restrict_countries
            );
            
            if ($stmt->execute()) {
                $department_id = $conn->insert_id;
                
                // إضافة الدول المحددة
                if ($restrict_countries && !empty($selected_countries)) {
                    $country_stmt = $conn->prepare("INSERT INTO department_countries (department_id, country_id) VALUES (?, ?)");
                    foreach ($selected_countries as $country_id) {
                        $country_stmt->bind_param("is", $department_id, $country_id);
                        $country_stmt->execute();
                    }
                }
                
                $conn->commit();
                $_SESSION['success'] = "تم إضافة القسم بنجاح";
            } else {
                throw new Exception("حدث خطأ أثناء إضافة القسم");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['edit_department'])) {
        $department_id = (int)$_POST['department_id'];
        $name = sanitize_input($_POST['name']);
        $student_gender = sanitize_input($_POST['student_gender']);
        $work_friday = isset($_POST['work_friday']) ? 1 : 0;
        $work_saturday = isset($_POST['work_saturday']) ? 1 : 0;
        $work_sunday = isset($_POST['work_sunday']) ? 1 : 0;
        $work_monday = isset($_POST['work_monday']) ? 1 : 0;
        $work_tuesday = isset($_POST['work_tuesday']) ? 1 : 0;
        $work_wednesday = isset($_POST['work_wednesday']) ? 1 : 0;
        $work_thursday = isset($_POST['work_thursday']) ? 1 : 0;
        $monthly_fees = (int)$_POST['monthly_fees'];
        $quarterly_fees = (int)$_POST['quarterly_fees'];
        $biannual_fees = (int)$_POST['biannual_fees'];
        $annual_fees = (int)$_POST['annual_fees'];
        $restrict_countries = isset($_POST['restrict_countries']) ? 1 : 0;
        $selected_countries = $_POST['countries'] ?? [];

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                UPDATE departments SET 
                    name = ?, 
                    student_gender = ?,
                    work_friday = ?,
                    work_saturday = ?,
                    work_sunday = ?,
                    work_monday = ?,
                    work_tuesday = ?,
                    work_wednesday = ?,
                    work_thursday = ?,
                    monthly_fees = ?,
                    quarterly_fees = ?,
                    biannual_fees = ?,
                    annual_fees = ?,
                    restrict_countries = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssiiiiiiiiiiiii",
                $name, 
                $student_gender,
                $work_friday, 
                $work_saturday, 
                $work_sunday, 
                $work_monday,
                $work_tuesday, 
                $work_wednesday, 
                $work_thursday,
                $monthly_fees, 
                $quarterly_fees, 
                $biannual_fees, 
                $annual_fees,
                $restrict_countries,
                $department_id
            );

            if ($stmt->execute()) {
                // حذف الدول القديمة
                $delete_stmt = $conn->prepare("DELETE FROM department_countries WHERE department_id = ?");
                $delete_stmt->bind_param("i", $department_id);
                $delete_stmt->execute();
                
                // إضافة الدول الجديدة
                if ($restrict_countries && !empty($selected_countries)) {
                    $insert_stmt = $conn->prepare("INSERT INTO department_countries (department_id, country_id) VALUES (?, ?)");
                    foreach ($selected_countries as $country_id) {
                        $insert_stmt->bind_param("is", $department_id, $country_id);
                        $insert_stmt->execute();
                    }
                }
                
                $conn->commit();
                $_SESSION['success'] = "تم تحديث القسم بنجاح";
            } else {
                throw new Exception("حدث خطأ أثناء تحديث القسم");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['delete_department'])) {
        $department_id = (int)$_POST['department_id'];
        
        $conn->begin_transaction();
        
        try {
            // التحقق من عدم وجود حلقات مرتبطة
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM study_circles WHERE department_id = ?");
            $check_stmt->bind_param("i", $department_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                throw new Exception("لا يمكن حذف القسم لوجود حلقات مرتبطة به");
            }
            
            // حذف مديري القسم أولاً
            $stmt = $conn->prepare("DELETE FROM department_admins WHERE department_id = ?");
            $stmt->bind_param("i", $department_id);
            $stmt->execute();
            
            // حذف القسم
            $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->bind_param("i", $department_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['success'] = "تم حذف القسم بنجاح";
            } else {
                throw new Exception("حدث خطأ أثناء حذف القسم");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// الحصول على الأقسام مع إحصائياتها
$departments_sql = "
    SELECT 
        d.*,
        (SELECT COUNT(*) FROM study_circles WHERE department_id = d.id) as circles_count,
        (SELECT COUNT(*) FROM department_admins WHERE department_id = d.id) as admins_count,
        (SELECT COUNT(*) FROM study_circles sc 
         JOIN circle_students cs ON sc.id = cs.circle_id 
         WHERE sc.department_id = d.id) as students_count
    FROM departments d
    ORDER BY d.name
";
$departments = $conn->query($departments_sql)->fetch_all(MYSQLI_ASSOC);

// الحصول على المستخدمين المتاحين كمديري أقسام
$available_admins_sql = "
    SELECT u.id, u.name 
    FROM users u 
    WHERE u.role = 'department_admin' 
    AND u.id NOT IN (
        SELECT user_id 
        FROM department_admins 
        WHERE department_id = ?
    )
    ORDER BY u.name
";

$available_admins = [];
if (isset($_GET['department_id'])) {
    $stmt = $conn->prepare($available_admins_sql);
    $dept_id = (int)$_GET['department_id'];
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $available_admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأقسام</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #1FA959;
            --primary-dark: #198A47;
            --primary-light: #F0F9F2;
            --secondary-color: #8D6E63;
            --accent-color: #FFC107;
            --islamic-pattern: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-.895-3-2-3-3 .895-3 2 .895 3 2 3zm63 31c1.657 0 3-1.343 3-3s-.895-3-2-3-3 .895-3 2 .895 3 2 3zM34 90c1.657 0 3-1.343 3-3s-.895-3-2-3-3 .895-3 2 .895 3 2 3zm56-76c1.657 0 3-1.343 3-3s-.895-3-2-3-3 .895-3 2 .895 3 2 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23198A47' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
        }

        body {
            background-color: #f8f9fa;
            background-image: var(--islamic-pattern);
            font-family: 'Noto Kufi Arabic', sans-serif;
        }

        .department-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background-color: white;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .department-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(31, 169, 89, 0.15);
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border-bottom: none;
            position: relative;
            overflow: hidden;
            padding: 1rem;
        }

        .card-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg width='52' height='26' viewBox='0 0 52 26' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.4'%3E%3Cpath d='M10 10c0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6h2c0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4v2c-3.314 0-6-2.686-6-6 0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6zm25.464-1.95l8.486 8.486-1.414 1.414-8.486-8.486 1.414-1.414z' /%3E%3C/g%3E%3C/svg%3E");
        }

        .card-header .btn-light {
            background-color: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            margin-left: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .card-header .btn-light:hover {
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .card-header .card-title {
            position: relative;
            z-index: 1;
        }

        .department-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background-color: var(--primary-light);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .department-stat:hover {
            background-color: #DCF5E3;
        }

        .department-stat i {
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .admin-list {
            max-height: 300px;
            overflow-y: auto;
            border-radius: 10px;
            background-color: var(--primary-light);
            padding: 0.5rem;
        }

        .admin-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid rgba(31, 169, 89, 0.1);
            transition: background-color 0.3s ease;
        }

        .admin-item:hover {
            background-color: rgba(31, 169, 89, 0.05);
        }

        .admin-item:last-child {
            border-bottom: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(31, 169, 89, 0.25);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>إدارة الأقسام</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                <i class="bi bi-plus-lg"></i>
                إضافة قسم
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

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($departments as $department): ?>
            <div class="col">
                <div class="card h-100 department-card border-0 shadow-sm">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($department['name']); ?></h5>
                            <div>
                                <button type="button" class="btn btn-light btn-sm" 
                                        onclick="editDepartment(<?php echo $department['id']; ?>)"
                                        title="تعديل القسم">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button type="button" class="btn btn-light btn-sm" 
                                        onclick="deleteDepartment(<?php echo $department['id']; ?>, '<?php echo htmlspecialchars($department['name']); ?>')"
                                        title="حذف القسم">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                                <button type="button" class="btn btn-light btn-sm" 
                                        onclick="manageDepartmentAdmins(<?php echo $department['id']; ?>, '<?php echo htmlspecialchars($department['name']); ?>')"
                                        title="مديرو القسم">
                                    <i class="bi bi-people-fill"></i>
                                </button>
                                <a href="circles.php?dept_id=<?php echo $department['id']; ?>" 
                                   class="btn btn-light btn-sm" title="عرض الحلقات">
                                    <i class="bi bi-grid"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($department['description'])): ?>
                        <p class="card-text text-muted mb-3"><?php echo nl2br(htmlspecialchars($department['description'])); ?></p>
                        <?php endif; ?>
                        
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
                                        <small class="text-muted d-block">المديرون</small>
                                        <strong><?php echo $department['admins_count']; ?> مدير</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="department-stat">
                                    <i class="bi bi-person-check"></i>
                                    <div>
                                        <small class="text-muted d-block">الطلاب</small>
                                        <strong><?php echo $department['students_count']; ?> طالب</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal إدارة مديري القسم -->
    <div class="modal fade" id="manageDepartmentAdminsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">مديرو القسم: <span id="departmentName"></span></h5>
                    <button type="button" class="btn-close ms-0 me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" class="mb-4" id="addAdminForm">
                        <input type="hidden" name="department_id" id="departmentId">
                        <div class="mb-3">
                            <label class="form-label">إضافة مدير جديد</label>
                            <div class="input-group">
                                <select class="form-select" name="admin_id" required>
                                    <option value="">اختر مديراً...</option>
                                    <?php foreach ($available_admins as $admin): ?>
                                    <option value="<?php echo $admin['id']; ?>">
                                        <?php echo htmlspecialchars($admin['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="add_admin" class="btn btn-primary">
                                    <i class="bi bi-plus-lg"></i>
                                    إضافة
                                </button>
                            </div>
                        </div>
                    </form>

                    <h6 class="mb-3">المديرون الحاليون</h6>
                    <div class="admin-list" id="adminsList">
                        <!-- سيتم تعبئة هذا القسم عبر JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal إضافة قسم -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة قسم جديد</h5>
                        <button type="button" class="btn-close ms-0 me-2" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم القسم</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">جنس الطلاب</label>
                                <select class="form-select" name="student_gender" required>
                                    <option value="male">بنين</option>
                                    <option value="female">بنات</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">أيام العمل</label>
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="work_friday" name="work_friday" value="1">
                                            <label class="form-check-label" for="work_friday">الجمعة</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="work_saturday" name="work_saturday" value="1">
                                            <label class="form-check-label" for="work_saturday">السبت</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="work_sunday" name="work_sunday" value="1">
                                            <label class="form-check-label" for="work_sunday">الأحد</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="work_monday" name="work_monday" value="1">
                                            <label class="form-check-label" for="work_monday">الإثنين</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="work_tuesday" name="work_tuesday" value="1">
                                            <label class="form-check-label" for="work_tuesday">الثلاثاء</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="work_wednesday" name="work_wednesday" value="1">
                                            <label class="form-check-label" for="work_wednesday">الأربعاء</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="work_thursday" name="work_thursday" value="1">
                                            <label class="form-check-label" for="work_thursday">الخميس</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="restrict_countries" name="restrict_countries" value="1">
                                    <label class="form-check-label" for="restrict_countries">تقييد التسجيل حسب الدول</label>
                                </div>
                                <div id="countries_container" style="display: none;">
                                    <label class="form-label">اختر الدول المسموح لها بالتسجيل</label>
                                    <select class="form-select countries-select" name="countries[]" multiple>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo $country['ID']; ?>">
                                                <?php echo $country['Name'] . (!empty($country['AltName']) ? ' - ' . $country['AltName'] : ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label">الرسوم الشهرية</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="monthly_fees" required min="0">
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label">الرسوم الربع سنوية</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="quarterly_fees" required min="0">
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label">الرسوم نصف السنوية</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="biannual_fees" required min="0">
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label">الرسوم السنوية</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="annual_fees" required min="0">
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_department" class="btn btn-primary">إضافة القسم</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تعديل القسم -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editDepartmentForm">
                    <input type="hidden" name="department_id" id="edit_department_id">
                    <div class="modal-header">
                        <h5 class="modal-title">تعديل القسم</h5>
                        <button type="button" class="btn-close ms-0 me-2" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">اسم القسم</label>
                                <input type="text" class="form-control" name="name" id="edit_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">جنس الطلاب</label>
                                <select class="form-select" name="student_gender" id="edit_student_gender" required>
                                    <option value="male">بنين</option>
                                    <option value="female">بنات</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">أيام العمل</label>
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="edit_work_friday" name="work_friday" value="1">
                                            <label class="form-check-label" for="edit_work_friday">الجمعة</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="edit_work_saturday" name="work_saturday" value="1">
                                            <label class="form-check-label" for="edit_work_saturday">السبت</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="edit_work_sunday" name="work_sunday" value="1">
                                            <label class="form-check-label" for="edit_work_sunday">الأحد</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="edit_work_monday" name="work_monday" value="1">
                                            <label class="form-check-label" for="edit_work_monday">الإثنين</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="edit_work_tuesday" name="work_tuesday" value="1">
                                            <label class="form-check-label" for="edit_work_tuesday">الثلاثاء</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="edit_work_wednesday" name="work_wednesday" value="1">
                                            <label class="form-check-label" for="edit_work_wednesday">الأربعاء</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="edit_work_thursday" name="work_thursday" value="1">
                                            <label class="form-check-label" for="edit_work_thursday">الخميس</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="edit_restrict_countries" name="restrict_countries" value="1">
                                    <label class="form-check-label" for="edit_restrict_countries">تقييد التسجيل حسب الدول</label>
                                </div>
                                <div id="edit_countries_container" style="display: none;">
                                    <label class="form-label">اختر الدول المسموح لها بالتسجيل</label>
                                    <select class="form-select countries-select" name="countries[]" id="edit_countries" multiple>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo $country['ID']; ?>">
                                                <?php echo $country['Name'] . (!empty($country['AltName']) ? ' - ' . $country['AltName'] : ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label">الرسوم الشهرية</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="monthly_fees" id="edit_monthly_fees" required min="0">
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label">الرسوم الربع سنوية</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="quarterly_fees" id="edit_quarterly_fees" required min="0">
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label">الرسوم نصف السنوية</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="biannual_fees" id="edit_biannual_fees" required min="0">
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label">الرسوم السنوية</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="annual_fees" id="edit_annual_fees" required min="0">
                                    <span class="input-group-text">ريال</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="edit_department" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // تهيئة Select2 للدول
        $(document).ready(function() {
            $('.countries-select').select2({
                theme: 'bootstrap-5',
                dir: 'rtl',
                language: {
                    noResults: function() {
                        return "لا توجد نتائج";
                    },
                    searching: function() {
                        return "جاري البحث...";
                    }
                },
                placeholder: 'اختر الدول...',
                allowClear: true,
                closeOnSelect: false,
                width: '100%'
            });

            // تحديث عرض Select2 عند تغيير حالة التقييد
            $('#restrict_countries').change(function() {
                const container = $('#countries_container');
                if (this.checked) {
                    container.show();
                    $('.countries-select').select2('destroy').select2({
                        theme: 'bootstrap-5',
                        dir: 'rtl',
                        language: {
                            noResults: function() {
                                return "لا توجد نتائج";
                            },
                            searching: function() {
                                return "جاري البحث...";
                            }
                        },
                        placeholder: 'اختر الدول...',
                        allowClear: true,
                        closeOnSelect: false,
                        width: '100%'
                    });
                } else {
                    container.hide();
                    $('.countries-select').val(null).trigger('change');
                }
            });

            $('#edit_restrict_countries').change(function() {
                const container = $('#edit_countries_container');
                if (this.checked) {
                    container.show();
                    $('#edit_countries').select2('destroy').select2({
                        theme: 'bootstrap-5',
                        dir: 'rtl',
                        language: {
                            noResults: function() {
                                return "لا توجد نتائج";
                            },
                            searching: function() {
                                return "جاري البحث...";
                            }
                        },
                        placeholder: 'اختر الدول...',
                        allowClear: true,
                        closeOnSelect: false,
                        width: '100%'
                    });
                } else {
                    container.hide();
                    $('#edit_countries').val(null).trigger('change');
                }
            });
        });

        // تعديل في دالة editDepartment
        async function editDepartment(departmentId) {
            try {
                const response = await fetch(`get_department.php?id=${departmentId}`);
                const department = await response.json();
                
                // تعبئة النموذج بالبيانات الحالية
                document.getElementById('edit_department_id').value = department.id;
                document.getElementById('edit_name').value = department.name;
                document.getElementById('edit_student_gender').value = department.student_gender;
                document.getElementById('edit_work_friday').checked = department.work_friday == 1;
                document.getElementById('edit_work_saturday').checked = department.work_saturday == 1;
                document.getElementById('edit_work_sunday').checked = department.work_sunday == 1;
                document.getElementById('edit_work_monday').checked = department.work_monday == 1;
                document.getElementById('edit_work_tuesday').checked = department.work_tuesday == 1;
                document.getElementById('edit_work_wednesday').checked = department.work_wednesday == 1;
                document.getElementById('edit_work_thursday').checked = department.work_thursday == 1;
                document.getElementById('edit_monthly_fees').value = department.monthly_fees;
                document.getElementById('edit_quarterly_fees').value = department.quarterly_fees;
                document.getElementById('edit_biannual_fees').value = department.biannual_fees;
                document.getElementById('edit_annual_fees').value = department.annual_fees;
                
                // تحديث حالة تقييد الدول والدول المحددة
                const restrictCountries = document.getElementById('edit_restrict_countries');
                restrictCountries.checked = department.restrict_countries == 1;
                
                const countriesContainer = document.getElementById('edit_countries_container');
                countriesContainer.style.display = department.restrict_countries == 1 ? 'block' : 'none';
                
                if (department.selected_countries) {
                    const selectedCountries = department.selected_countries.split(',');
                    $('#edit_countries').val(selectedCountries).trigger('change');
                } else {
                    $('#edit_countries').val(null).trigger('change');
                }
                
                // إعادة تهيئة Select2 بعد عرض النموذج
                $('#editDepartmentModal').on('shown.bs.modal', function () {
                    $('#edit_countries').select2({
                        theme: 'bootstrap-5',
                        dir: 'rtl',
                        language: {
                            noResults: function() {
                                return "لا توجد نتائج";
                            },
                            searching: function() {
                                return "جاري البحث...";
                            }
                        },
                        placeholder: 'اختر الدول...',
                        allowClear: true,
                        closeOnSelect: false,
                        width: '100%'
                    });
                });
                
                const editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
                editModal.show();
            } catch (error) {
                console.error('Error:', error);
                alert('حدث خطأ أثناء جلب بيانات القسم');
            }
        }

        // Modal إدارة مديري القسم
        const manageDepartmentAdminsModal = new bootstrap.Modal(document.getElementById('manageDepartmentAdminsModal'));
        
        async function manageDepartmentAdmins(departmentId, departmentName) {
            document.getElementById('departmentName').textContent = departmentName;
            document.getElementById('departmentId').value = departmentId;
            
            // تحديث قائمة المديرين المتاحين
            try {
                const response = await fetch(`departments.php?department_id=${departmentId}`);
                const data = await response.json();
                
                const selectElement = document.querySelector('select[name="admin_id"]');
                selectElement.innerHTML = '<option value="">اختر مديراً...</option>';
                
                data.forEach(admin => {
                    const option = document.createElement('option');
                    option.value = admin.id;
                    option.textContent = admin.name;
                    selectElement.appendChild(option);
                });
            } catch (error) {
                console.error('Error fetching available admins:', error);
            }
            
            // جلب قائمة المديرين الحاليين
            try {
                const response = await fetch(`get_department_admins.php?department_id=${departmentId}`);
                const admins = await response.json();
                
                const adminsList = document.getElementById('adminsList');
                adminsList.innerHTML = '';
                
                if (admins.length === 0) {
                    adminsList.innerHTML = '<div class="text-muted text-center py-3">لا يوجد مديرون حالياً</div>';
                } else {
                    admins.forEach(admin => {
                        const adminItem = document.createElement('div');
                        adminItem.className = 'admin-item';
                        adminItem.innerHTML = `
                            <span>${admin.name}</span>
                            <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المدير؟')">
                                <input type="hidden" name="department_id" value="${departmentId}">
                                <input type="hidden" name="admin_id" value="${admin.id}">
                                <button type="submit" name="remove_admin" class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        `;
                        adminsList.appendChild(adminItem);
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('adminsList').innerHTML = 
                    '<div class="alert alert-danger">حدث خطأ أثناء جلب قائمة المديرين</div>';
            }
            
            manageDepartmentAdminsModal.show();
        }

        // دالة حذف القسم
        function deleteDepartment(departmentId, departmentName) {
            Swal.fire({
                title: 'تأكيد الحذف',
                text: `هل أنت متأكد من حذف قسم "${departmentName}"؟`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    // إرسال طلب الحذف
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="delete_department" value="1">
                        <input type="hidden" name="department_id" value="${departmentId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>
