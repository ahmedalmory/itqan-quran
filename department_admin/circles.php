<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';

// Ensure user is super admin
requireRole('department_admin');

// إذا تم تحديد قسم، تأكد من أنه تابع للمستخدم الحالي
if (isset($_GET['dept_id'])) {
    $selected_department = (int)$_GET['dept_id'];
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM department_admins 
        WHERE department_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $selected_department, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] == 0) {
        // إذا كان القسم غير تابع للمستخدم، إعادة توجيه للصفحة الرئيسية
        header('Location: circles.php');
        exit;
    }
} else {
    $selected_department = null;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success_message = '';
$error_message = '';

// Handle circle creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_circle']) || isset($_POST['update_circle'])) {
        $name = sanitize_input($_POST['name']);
        $description = sanitize_input($_POST['description']);
        $department_id = (int)$_POST['department_id'];
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        $supervisor_id = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;
        $max_students = (int)$_POST['max_students'];
        $whatsapp_group = sanitize_input($_POST['whatsapp_group']);
        $telegram_group = sanitize_input($_POST['telegram_group']);
        $age_from = (int)$_POST['age_from'];
        $age_to = (int)$_POST['age_to'];
        $circle_time = sanitize_input($_POST['circle_time']);

        if (isset($_POST['create_circle'])) {
            $stmt = $conn->prepare("
                INSERT INTO study_circles (
                    name, description, department_id, teacher_id, supervisor_id,
                    max_students, whatsapp_group, telegram_group, age_from, age_to, circle_time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ssiiiissiis",
                $name, $description, $department_id, $teacher_id, $supervisor_id,
                $max_students, $whatsapp_group, $telegram_group, $age_from, $age_to, $circle_time
            );

            if ($stmt->execute()) {
                $success_message = "تم إنشاء الحلقة بنجاح";
            } else {
                $error_message = "حدث خطأ أثناء إنشاء الحلقة: " . $stmt->error;
            }
        } elseif (isset($_POST['update_circle'])) {
            $circle_id = (int)$_POST['circle_id'];
            
            $stmt = $conn->prepare("
                UPDATE study_circles SET 
                    name = ?, description = ?, department_id = ?, teacher_id = ?,
                    supervisor_id = ?, max_students = ?, whatsapp_group = ?,
                    telegram_group = ?, age_from = ?, age_to = ?, circle_time = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssiiiissiisi",
                $name, $description, $department_id, $teacher_id, $supervisor_id,
                $max_students, $whatsapp_group, $telegram_group, $age_from, $age_to,
                $circle_time, $circle_id
            );

            if ($stmt->execute()) {
                $success_message = "تم تحديث الحلقة بنجاح";
            } else {
                $error_message = "حدث خطأ أثناء تحديث الحلقة: " . $stmt->error;
            }
        }
    } elseif (isset($_POST['delete_circle'])) {
        $circle_id = (int)$_POST['circle_id'];
        
        // Check if circle has any students
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM circle_students WHERE circle_id = ?");
        $stmt->bind_param("i", $circle_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error_message = "لا يمكن حذف الحلقة لوجود طلاب مسجلين فيها";
        } else {
            $stmt = $conn->prepare("DELETE FROM study_circles WHERE id = ?");
            $stmt->bind_param("i", $circle_id);
            
            if ($stmt->execute()) {
                $success_message = "تم حذف الحلقة بنجاح";
            } else {
                $error_message = "حدث خطأ أثناء حذف الحلقة";
            }
        }
    }
}

// Get all departments for dropdown
$departments_query = "
    SELECT d.id, d.name 
    FROM departments d
    JOIN department_admins da ON d.id = da.department_id
    WHERE da.user_id = " . $_SESSION['user_id'] . "
    ORDER BY d.name
";
$departments = $conn->query($departments_query)->fetch_all(MYSQLI_ASSOC);

// Get teachers and supervisors for dropdowns
$users = $conn->query("
    SELECT id, name, role 
    FROM users 
    WHERE role IN ('teacher', 'supervisor') 
    ORDER BY name
")->fetch_all(MYSQLI_ASSOC);

// Get all circles with related information
$query = "
    SELECT 
        sc.*,
        d.name as department_name,
        t.name as teacher_name,
        s.name as supervisor_name,
        (SELECT COUNT(*) FROM circle_students WHERE circle_id = sc.id) as students_count
    FROM study_circles sc
    JOIN departments d ON sc.department_id = d.id
    JOIN department_admins da ON d.id = da.department_id
    LEFT JOIN users t ON sc.teacher_id = t.id
    LEFT JOIN users s ON sc.supervisor_id = s.id
    WHERE da.user_id = " . $_SESSION['user_id'];

if ($selected_department) {
    $query .= " AND sc.department_id = " . $selected_department;
}

$query .= " ORDER BY sc.name";
$circles = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get circle for editing
$edit_circle = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $circle_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM study_circles WHERE id = ?");
    $stmt->bind_param("i", $circle_id);
    $stmt->execute();
    $edit_circle = $stmt->get_result()->fetch_assoc();
}

$pageTitle = 'إدارة الحلقات';
$pageHeader = $action === 'edit' ? 'تعديل الحلقة' : ($action === 'new' ? 'حلقة جديدة' : 'إدارة الحلقات');
ob_start();
?>

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

<?php if ($action === 'list'): ?>
    <div class="row mb-4">
        <div class="col-md-8">
            <form class="d-flex gap-3 align-items-center">
                <div class="flex-grow-1">
                    <select class="form-select" name="dept_id" onchange="this.form.submit()">
                        
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $selected_department == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selected_department): ?>
                    <a href="?action=new&dept_id=<?php echo $selected_department; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> حلقة جديدة
                    </a>
                <?php else: ?>
                    <a href="?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> حلقة جديدة
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <div class="col-md-4">
            <div class="d-flex justify-content-end">
                <div class="bg-light rounded p-2">
                    <strong>إجمالي الحلقات:</strong> 
                    <span class="badge bg-primary"><?php echo count($circles); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
        <?php foreach ($circles as $circle): ?>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-success bg-gradient text-white py-3">
                        <h5 class="card-title mb-0">
                            <?php echo htmlspecialchars($circle['name']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Department -->
                            <div class="col-12">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-building me-2 text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">القسم</small>
                                        <strong><?php echo htmlspecialchars($circle['department_name']); ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Teacher -->
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person me-2 text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">المعلم</small>
                                        <strong><?php echo $circle['teacher_name'] ? htmlspecialchars($circle['teacher_name']) : 'غير محدد'; ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Supervisor -->
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-eye me-2 text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">المشرف</small>
                                        <strong><?php echo $circle['supervisor_name'] ? htmlspecialchars($circle['supervisor_name']) : 'غير محدد'; ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Students Count -->
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-people me-2 text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">الطلاب</small>
                                        <strong><?php echo $circle['students_count']; ?> / <?php echo $circle['max_students']; ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Circle Time -->
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-clock me-2 text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">وقت الحلقة</small>
                                        <strong>
                                            <?php
                                            $times = [
                                                'after_fajr' => 'بعد الفجر',
                                                'after_dhuhr' => 'بعد الظهر',
                                                'after_asr' => 'بعد العصر',
                                                'after_maghrib' => 'بعد المغرب',
                                                'after_isha' => 'بعد العشاء'
                                            ];
                                            echo $times[$circle['circle_time']];
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Age Range -->
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar3 me-2 text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">العمر</small>
                                        <strong><?php echo $circle['age_from']; ?> - <?php echo $circle['age_to']; ?> سنة</strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Social Links -->
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-chat-dots me-2 text-muted"></i>
                                    <div>
                                        <small class="text-muted d-block">روابط التواصل</small>
                                        <div class="mt-1">
                                            <?php if ($circle['whatsapp_group']): ?>
                                                <a href="<?php echo htmlspecialchars($circle['whatsapp_group']); ?>" 
                                                   class="btn btn-sm btn-outline-success me-1" target="_blank">
                                                    <i class="bi bi-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($circle['telegram_group']): ?>
                                                <a href="<?php echo htmlspecialchars($circle['telegram_group']); ?>" 
                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="bi bi-telegram"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="circle_students.php?circle_id=<?php echo $circle['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-people-fill"></i> عرض الطلاب
                            </a>
                            <a href="?action=edit&id=<?php echo $circle['id']; ?>" 
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-pencil"></i> تعديل
                            </a>
                            <?php if ($circle['students_count'] == 0): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="confirmDelete(<?php echo $circle['id']; ?>)">
                                    <i class="bi bi-trash"></i> حذف
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="circle_id" value="<?php echo $edit_circle['id']; ?>">
                <?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">اسم الحلقة</label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="<?php echo $edit_circle ? htmlspecialchars($edit_circle['name']) : ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="department_id" class="form-label">القسم</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">اختر القسم</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"
                                    <?php echo $edit_circle && $edit_circle['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <label for="description" class="form-label">وصف الحلقة</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php 
                            echo $edit_circle ? htmlspecialchars($edit_circle['description']) : ''; 
                        ?></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="teacher_id" class="form-label">المعلم (اختياري)</label>
                        <select class="form-select" id="teacher_id" name="teacher_id">
                            <option value="">اختر المعلم</option>
                            <?php foreach ($users as $user): ?>
                                <?php if ($user['role'] === 'teacher'): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                        <?php echo $edit_circle && $edit_circle['teacher_id'] == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="supervisor_id" class="form-label">المشرف (اختياري)</label>
                        <select class="form-select" id="supervisor_id" name="supervisor_id">
                            <option value="">اختر المشرف</option>
                            <?php foreach ($users as $user): ?>
                                <?php if ($user['role'] === 'supervisor'): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                        <?php echo $edit_circle && $edit_circle['supervisor_id'] == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="max_students" class="form-label">الحد الأقصى للطلاب</label>
                        <input type="number" class="form-control" id="max_students" name="max_students" 
                               min="1" required value="<?php echo $edit_circle ? $edit_circle['max_students'] : ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="age_from" class="form-label">العمر من</label>
                        <input type="number" class="form-control" id="age_from" name="age_from" 
                               min="4" required value="<?php echo $edit_circle ? $edit_circle['age_from'] : ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="age_to" class="form-label">العمر إلى</label>
                        <input type="number" class="form-control" id="age_to" name="age_to" 
                               min="4" required value="<?php echo $edit_circle ? $edit_circle['age_to'] : ''; ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="circle_time" class="form-label">وقت الحلقة</label>
                        <select class="form-select" id="circle_time" name="circle_time" required>
                            <option value="">اختر الوقت</option>
                            <option value="after_fajr" <?php echo $edit_circle && $edit_circle['circle_time'] === 'after_fajr' ? 'selected' : ''; ?>>بعد الفجر</option>
                            <option value="after_dhuhr" <?php echo $edit_circle && $edit_circle['circle_time'] === 'after_dhuhr' ? 'selected' : ''; ?>>بعد الظهر</option>
                            <option value="after_asr" <?php echo $edit_circle && $edit_circle['circle_time'] === 'after_asr' ? 'selected' : ''; ?>>بعد العصر</option>
                            <option value="after_maghrib" <?php echo $edit_circle && $edit_circle['circle_time'] === 'after_maghrib' ? 'selected' : ''; ?>>بعد المغرب</option>
                            <option value="after_isha" <?php echo $edit_circle && $edit_circle['circle_time'] === 'after_isha' ? 'selected' : ''; ?>>بعد العشاء</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="whatsapp_group" class="form-label">رابط مجموعة الواتساب</label>
                        <input type="url" class="form-control" id="whatsapp_group" name="whatsapp_group"
                               value="<?php echo $edit_circle ? htmlspecialchars($edit_circle['whatsapp_group']) : ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="telegram_group" class="form-label">رابط مجموعة التلجرام</label>
                        <input type="url" class="form-control" id="telegram_group" name="telegram_group"
                               value="<?php echo $edit_circle ? htmlspecialchars($edit_circle['telegram_group']) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <a href="circles.php" class="btn btn-secondary">إلغاء</a>
                        <button type="submit" name="<?php echo $action === 'edit' ? 'update_circle' : 'create_circle'; ?>" 
                                class="btn btn-primary">
                            <?php echo $action === 'edit' ? 'تعديل' : 'إنشاء'; ?> الحلقة
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Delete Circle Form -->
<form id="deleteCircleForm" method="POST" style="display: none;">
    <input type="hidden" name="circle_id" id="deleteCircleId">
    <input type="hidden" name="delete_circle" value="1">
</form>

<script>
function confirmDelete(circleId) {
    if (confirm('هل أنت متأكد من حذف هذه الحلقة؟')) {
        document.getElementById('deleteCircleId').value = circleId;
        document.getElementById('deleteCircleForm').submit();
    }
}

// Form validation
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

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
