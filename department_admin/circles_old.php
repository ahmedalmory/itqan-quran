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

// التحقق من وجود معرف القسم
if (!isset($_GET['department_id'])) {
    $_SESSION['error'] = "لم يتم تحديد القسم";
    header('Location: index.php');
    exit();
}

$department_id = $_GET['department_id'];
$user_id = $_SESSION['user_id'];

// التحقق من أن المستخدم مدير لهذا القسم
$check_admin = $conn->prepare("SELECT 1 FROM department_admins WHERE department_id = ? AND user_id = ?");
$check_admin->bind_param("ii", $department_id, $user_id);
$check_admin->execute();
if (!$check_admin->get_result()->fetch_assoc()) {
    $_SESSION['error'] = "غير مصرح لك بإدارة هذا القسم";
    header('Location: index.php');
    exit();
}

// الحصول على معلومات القسم
$dept_stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
$dept_stmt->bind_param("i", $department_id);
$dept_stmt->execute();
$department = $dept_stmt->get_result()->fetch_assoc();

// الحصول على قائمة الحلقات
$circles_sql = "
    SELECT sc.*, 
           u.name as teacher_name,
           (SELECT COUNT(*) FROM circle_students WHERE circle_id = sc.id) as students_count
    FROM study_circles sc
    LEFT JOIN users u ON sc.teacher_id = u.id
    WHERE sc.department_id = ?
    ORDER BY sc.name
";
$circles_stmt = $conn->prepare($circles_sql);
$circles_stmt->bind_param("i", $department_id);
$circles_stmt->execute();
$circles = $circles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// الحصول على قائمة المعلمين المتاحين
$teachers_sql = "
    SELECT u.id, u.name
    FROM users u
    WHERE u.role = 'teacher'
    ORDER BY u.name
";
$teachers = $conn->query($teachers_sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الحلقات - <?php echo htmlspecialchars($department['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">إدارة الحلقات</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($department['name']); ?></li>
                    </ol>
                </nav>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCircleModal">
                <i class="bi bi-plus-lg"></i>
                إضافة حلقة جديدة
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

        <?php if (empty($circles)): ?>
            <div class="alert alert-info">
                لا توجد حلقات في هذا القسم حتى الآن.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                <?php foreach ($circles as $circle): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($circle['name']); ?></h5>
                                
                                <div class="mb-3">
                                    <small class="text-muted">المعلم:</small>
                                    <div><?php echo $circle['teacher_name'] ? htmlspecialchars($circle['teacher_name']) : 'لم يتم تعيين معلم'; ?></div>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">عدد الطلاب:</small>
                                    <div><?php echo $circle['students_count']; ?> / <?php echo $circle['max_students']; ?></div>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">وقت الحلقة:</small>
                                    <div><?php echo format_prayer_time($circle['circle_time']); ?></div>
                                </div>

                                <?php if ($circle['description']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">الوصف:</small>
                                        <div><?php echo nl2br(htmlspecialchars($circle['description'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent border-top-0">
                                <div class="btn-group w-100">
                                    <a href="circle_students.php?circle_id=<?php echo $circle['id']; ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-people"></i>
                                        الطلاب
                                    </a>
                                    <a href="circle_reports.php?circle_id=<?php echo $circle['id']; ?>" class="btn btn-outline-success">
                                        <i class="bi bi-journal-text"></i>
                                        التقارير
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick="editCircle(<?php echo htmlspecialchars(json_encode($circle)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                        تعديل
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal إضافة حلقة جديدة -->
    <div class="modal fade" id="addCircleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="add_circle.php">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة حلقة جديدة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="department_id" value="<?php echo $department_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">اسم الحلقة</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">المعلم</label>
                            <select name="teacher_id" class="form-select">
                                <option value="">اختر المعلم...</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الحد الأقصى للطلاب</label>
                            <input type="number" name="max_students" class="form-control" required min="1" value="15">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">وقت الحلقة</label>
                            <select name="circle_time" class="form-select" required>
                                <option value="after_fajr">بعد الفجر</option>
                                <option value="after_dhuhr">بعد الظهر</option>
                                <option value="after_asr">بعد العصر</option>
                                <option value="after_maghrib">بعد المغرب</option>
                                <option value="after_isha">بعد العشاء</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رابط مجموعة WhatsApp</label>
                            <input type="url" name="whatsapp_group" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رابط قناة Telegram</label>
                            <input type="url" name="telegram_group" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة الحلقة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تعديل الحلقة -->
    <div class="modal fade" id="editCircleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="edit_circle.php">
                    <div class="modal-header">
                        <h5 class="modal-title">تعديل الحلقة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="circle_id" id="edit_circle_id">
                        
                        <div class="mb-3">
                            <label class="form-label">اسم الحلقة</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">المعلم</label>
                            <select name="teacher_id" id="edit_teacher_id" class="form-select">
                                <option value="">اختر المعلم...</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الحد الأقصى للطلاب</label>
                            <input type="number" name="max_students" id="edit_max_students" class="form-control" required min="1">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">وقت الحلقة</label>
                            <select name="circle_time" id="edit_circle_time" class="form-select" required>
                                <option value="after_fajr">بعد الفجر</option>
                                <option value="after_dhuhr">بعد الظهر</option>
                                <option value="after_asr">بعد العصر</option>
                                <option value="after_maghrib">بعد المغرب</option>
                                <option value="after_isha">بعد العشاء</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رابط مجموعة WhatsApp</label>
                            <input type="url" name="whatsapp_group" id="edit_whatsapp_group" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رابط قناة Telegram</label>
                            <input type="url" name="telegram_group" id="edit_telegram_group" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCircle(circle) {
            document.getElementById('edit_circle_id').value = circle.id;
            document.getElementById('edit_name').value = circle.name;
            document.getElementById('edit_teacher_id').value = circle.teacher_id || '';
            document.getElementById('edit_max_students').value = circle.max_students;
            document.getElementById('edit_circle_time').value = circle.circle_time;
            document.getElementById('edit_description').value = circle.description || '';
            document.getElementById('edit_whatsapp_group').value = circle.whatsapp_group || '';
            document.getElementById('edit_telegram_group').value = circle.telegram_group || '';
            
            new bootstrap.Modal(document.getElementById('editCircleModal')).show();
        }
    </script>
</body>
</html>
