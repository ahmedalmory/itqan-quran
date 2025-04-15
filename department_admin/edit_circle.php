<?php
session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من صلاحية المستخدم
requireRole('department_admin');

$admin_id = $_SESSION['user_id'];
$circle_id = isset($_GET['circle_id']) ? (int)$_GET['circle_id'] : null;

// التحقق من صلاحية الوصول للحلقة
$circle_sql = "
    SELECT c.*, d.name as department_name, d.id as department_id
    FROM study_circles c
    JOIN departments d ON c.department_id = d.id
    JOIN department_admins da ON d.id = da.department_id
    WHERE c.id = ? AND da.user_id = ?
";
$stmt = $conn->prepare($circle_sql);
$stmt->bind_param("ii", $circle_id, $admin_id);
$stmt->execute();
$circle = $stmt->get_result()->fetch_assoc();

if (!$circle) {
    header('Location: index.php');
    exit();
}

// الحصول على المعلمين المتاحين
$teachers_sql = "SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name";
$teachers = $conn->query($teachers_sql)->fetch_all(MYSQLI_ASSOC);

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_circle'])) {
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $max_students = (int)$_POST['max_students'];
    $age_from = (int)$_POST['age_from'];
    $age_to = (int)$_POST['age_to'];
    $circle_time = sanitize_input($_POST['circle_time']);
    $whatsapp_group = sanitize_input($_POST['whatsapp_group']);
    $telegram_group = sanitize_input($_POST['telegram_group']);

    $update_sql = "
        UPDATE study_circles 
        SET name = ?, 
            description = ?, 
            teacher_id = ?, 
            max_students = ?, 
            age_from = ?, 
            age_to = ?, 
            circle_time = ?,
            whatsapp_group = ?,
            telegram_group = ?
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param(
        "ssiiiisssi",
        $name,
        $description,
        $teacher_id,
        $max_students,
        $age_from,
        $age_to,
        $circle_time,
        $whatsapp_group,
        $telegram_group,
        $circle_id
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "تم تحديث بيانات الحلقة بنجاح";
        header("Location: circles.php?dept_id=" . $circle['department_id']);
        exit();
    } else {
        $_SESSION['error'] = "حدث خطأ أثناء تحديث بيانات الحلقة";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل بيانات الحلقة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 2rem;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 0.25rem rgba(25, 118, 210, 0.25);
        }
        .alert {
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/department_admin_navbar.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="mb-0">تعديل بيانات الحلقة</h1>
                        <p class="text-muted"><?php echo htmlspecialchars($circle['name']); ?></p>
                    </div>
                    <a href="circles.php?dept_id=<?php echo $circle['department_id']; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-right-short"></i>
                        عودة للحلقات
                    </a>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="form-card">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <!-- اسم الحلقة -->
                            <div class="col-12">
                                <label class="form-label">اسم الحلقة</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?php echo htmlspecialchars($circle['name']); ?>" required>
                            </div>

                            <!-- الوصف -->
                            <div class="col-12">
                                <label class="form-label">وصف الحلقة</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($circle['description']); ?></textarea>
                            </div>

                            <!-- المعلم -->
                            <div class="col-md-6">
                                <label class="form-label">المعلم</label>
                                <select class="form-select" name="teacher_id">
                                    <option value="">اختر المعلم...</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" 
                                            <?php echo $teacher['id'] == $circle['teacher_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- الحد الأقصى للطلاب -->
                            <div class="col-md-6">
                                <label class="form-label">الحد الأقصى للطلاب</label>
                                <input type="number" class="form-control" name="max_students" 
                                       value="<?php echo $circle['max_students']; ?>" required min="1">
                            </div>

                            <!-- العمر -->
                            <div class="col-md-6">
                                <label class="form-label">العمر من</label>
                                <input type="number" class="form-control" name="age_from" 
                                       value="<?php echo $circle['age_from']; ?>" required min="4">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">العمر إلى</label>
                                <input type="number" class="form-control" name="age_to" 
                                       value="<?php echo $circle['age_to']; ?>" required min="4">
                            </div>

                            <!-- وقت الحلقة -->
                            <div class="col-md-12">
                                <label class="form-label">وقت الحلقة</label>
                                <select class="form-select" name="circle_time" required>
                                    <option value="after_fajr" <?php echo $circle['circle_time'] == 'after_fajr' ? 'selected' : ''; ?>>
                                        بعد صلاة الفجر
                                    </option>
                                    <option value="after_dhuhr" <?php echo $circle['circle_time'] == 'after_dhuhr' ? 'selected' : ''; ?>>
                                        بعد صلاة الظهر
                                    </option>
                                    <option value="after_asr" <?php echo $circle['circle_time'] == 'after_asr' ? 'selected' : ''; ?>>
                                        بعد صلاة العصر
                                    </option>
                                    <option value="after_maghrib" <?php echo $circle['circle_time'] == 'after_maghrib' ? 'selected' : ''; ?>>
                                        بعد صلاة المغرب
                                    </option>
                                    <option value="after_isha" <?php echo $circle['circle_time'] == 'after_isha' ? 'selected' : ''; ?>>
                                        بعد صلاة العشاء
                                    </option>
                                </select>
                            </div>

                            <!-- روابط المجموعات -->
                            <div class="col-md-6">
                                <label class="form-label">رابط مجموعة WhatsApp</label>
                                <input type="url" class="form-control" name="whatsapp_group" 
                                       value="<?php echo htmlspecialchars($circle['whatsapp_group']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">رابط مجموعة Telegram</label>
                                <input type="url" class="form-control" name="telegram_group" 
                                       value="<?php echo htmlspecialchars($circle['telegram_group']); ?>">
                            </div>

                            <!-- زر الحفظ -->
                            <div class="col-12">
                                <hr class="my-4">
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_circle" class="btn btn-primary">
                                        <i class="bi bi-check2-circle"></i>
                                        حفظ التغييرات
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تفعيل التحقق من صحة النموذج
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>
