<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';

// التأكد من أن المستخدم مدير النظام
requireRole('department_admin');

// التحقق من وجود معرف الحلقة في الرابط
if (!isset($_GET['circle_id'])) {
    header('Location: circles.php');
    exit;
}

$circle_id = (int)$_GET['circle_id'];
$success_message = '';
$error_message = '';

// الحصول على معلومات الحلقة
$stmt = $conn->prepare("
    SELECT sc.*, d.name as department_name 
    FROM study_circles sc
    JOIN departments d ON sc.department_id = d.id
    WHERE sc.id = ?
");
$stmt->bind_param("i", $circle_id);
$stmt->execute();
$circle = $stmt->get_result()->fetch_assoc();

if (!$circle) {
    header('Location: circles.php');
    exit;
}

// الحصول على قائمة الإدارات المتاحة
if (isRole('super_admin')) {
    // جميع الإدارات للسوبر أدمن
    $departments = $conn->query("
        SELECT id, name 
        FROM departments 
        ORDER BY name
    ")->fetch_all(MYSQLI_ASSOC);
} else {
    // الإدارات التابعة للمشرف فقط
    $stmt = $conn->prepare("
        SELECT d.id, d.name 
        FROM departments d
        JOIN department_admins da ON d.id = da.department_id
        WHERE da.user_id = ?
        ORDER BY d.name
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// معالجة إضافة/حذف الطلاب
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        $student_id = (int)$_POST['student_id'];
        
        // التحقق من عدم وجود الطالب في الحلقة مسبقاً
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM circle_students WHERE circle_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $circle_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            $stmt = $conn->prepare("INSERT INTO circle_students (circle_id, student_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $circle_id, $student_id);
            
            if ($stmt->execute()) {
                $success_message = "تمت إضافة الطالب بنجاح";
            } else {
                $error_message = "حدث خطأ أثناء إضافة الطالب";
            }
        } else {
            $error_message = "الطالب موجود بالفعل في الحلقة";
        }
    } elseif (isset($_POST['remove_student'])) {
        $student_id = (int)$_POST['student_id'];
        
        $stmt = $conn->prepare("DELETE FROM circle_students WHERE circle_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $circle_id, $student_id);
        
        if ($stmt->execute()) {
            $success_message = "تم حذف الطالب من الحلقة بنجاح";
        } else {
            $error_message = "حدث خطأ أثناء حذف الطالب";
        }
    } elseif (isset($_POST['move_student'])) {
        $student_id = (int)$_POST['student_id'];
        $new_circle_id = (int)$_POST['new_circle_id'];
        
        $stmt = $conn->prepare("
            UPDATE circle_students 
            SET circle_id = ? 
            WHERE student_id = ? AND circle_id = ?
        ");
        $stmt->bind_param("iii", $new_circle_id, $student_id, $circle_id);
        
        if ($stmt->execute()) {
            $success_message = "تم نقل الطالب بنجاح";
        } else {
            $error_message = "حدث خطأ أثناء نقل الطالب";
        }
    } elseif (isset($_POST['toggle_status'])) {
        $student_id = (int)$_POST['student_id'];
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET is_active = NOT is_active 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $student_id);
        
        if ($stmt->execute()) {
            $success_message = "تم تحديث حالة الطالب بنجاح";
        } else {
            $error_message = "حدث خطأ أثناء تحديث حالة الطالب";
        }
    }
}

// الحصول على قائمة الطلاب في الحلقة
$students = $conn->query("
    SELECT u.*, cs.created_at as joined_at,
           u.is_active
    FROM users u
    JOIN circle_students cs ON u.id = cs.student_id
    WHERE cs.circle_id = $circle_id
    ORDER BY u.is_active DESC, u.name
")->fetch_all(MYSQLI_ASSOC);

// الحصول على قائمة الطلاب غير المسجلين في الحلقة
$available_students = $conn->query("
    SELECT u.*
    FROM users u
    WHERE u.role = 'student'
    AND u.id NOT IN (SELECT student_id FROM circle_students WHERE circle_id = $circle_id)
    ORDER BY u.name
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'طلاب الحلقة: ' . $circle['name'];
ob_start();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="mb-3">طلاب الحلقة: <?php echo htmlspecialchars($circle['name']); ?></h2>
            <div class="text-muted">
                القسم: <?php echo htmlspecialchars($circle['department_name']); ?>
            </div>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="circles.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> عودة للحلقات
            </a>
        </div>
    </div>

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
        <div class="col-md-8">
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
                                            <div class="p-3 border-bottom border-end h-100">
                                                <!-- Student Number Badge -->
                                                <div class="position-absolute top-0 start-0 mt-2 ms-2">
                                                    <span class="badge bg-primary rounded-pill">
                                                        <?php echo $index + 1; ?>
                                                    </span>
                                                </div>
                                                
                                                <!-- Student Info -->
                                                <div class="text-center mb-3 mt-2">
                                                    <div class="avatar-circle mb-3 mx-auto">
                                                        <span class="avatar-text">
                                                            <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                                                        </span>
                                                    </div>
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h5>
                                                    <div class="text-muted small">
                                                        انضم في <?php echo date('Y/m/d', strtotime($student['joined_at'])); ?>
                                                    </div>
                                                </div>

                                                <!-- Student Details -->
                                                <div class="student-details">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="bi bi-calendar3 text-muted me-2"></i>
                                                        <span>العمر: <?php echo $student['age']; ?> سنة</span>
                                                    </div>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="bi bi-envelope text-muted me-2"></i>
                                                        <span class="text-truncate">
                                                            <?php echo htmlspecialchars($student['email']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-phone text-muted me-2"></i>
                                                        <span><?php echo htmlspecialchars($student['phone']); ?></span>
                                                    </div>
                                                </div>

                                                <!-- Actions -->
                                                <div class="student-actions mt-3 pt-3 border-top">
                                                    <!-- صف أول من الأزرار -->
                                                    <div class="d-flex gap-2 justify-content-center mb-2">
                                                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" 
                                                           class="btn btn-sm btn-primary flex-fill">
                                                            <i class="bi bi-pencil-square"></i>
                                                            تعديل
                                                        </a>
                                                        
                                                        <button type="button" 
                                                                class="btn btn-sm btn-warning flex-fill"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#moveStudentModal"
                                                                data-student-id="<?php echo $student['id']; ?>"
                                                                data-student-name="<?php echo htmlspecialchars($student['name']); ?>">
                                                            <i class="bi bi-arrow-left-right"></i>
                                                            نقل
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- صف ثاني من الأزرار -->
                                                    <div class="d-flex gap-2 justify-content-center">
                                                        <a href="https://wa.me/<?php echo $student['phone']; ?>" 
                                                           target="_blank" 
                                                           class="btn btn-sm btn-success flex-fill">
                                                            <i class="bi bi-whatsapp"></i>
                                                            واتساب
                                                        </a>
                                                        
                                                        <button type="button" 
                                                                class="btn btn-sm <?php echo $student['is_active'] ? 'btn-secondary' : 'btn-success'; ?> w-100"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#toggleStatusModal"
                                                                data-student-id="<?php echo $student['id']; ?>"
                                                                data-student-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                                data-student-status="<?php echo $student['is_active']; ?>">
                                                            <i class="bi <?php echo $student['is_active'] ? 'bi-pause-fill' : 'bi-play-fill'; ?>"></i>
                                                            <?php echo $student['is_active'] ? 'إيقاف' : 'تفعيل'; ?>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- زر الحذف -->
                                                    <div class="text-center mt-2">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                            <button type="submit" name="remove_student" 
                                                                    class="btn btn-sm btn-outline-danger w-100"
                                                                    onclick="return confirm('هل أنت متأكد من حذف هذا الطالب من الحلقة؟')">
                                                                <i class="bi bi-person-x"></i>
                                                                حذف
                                                            </button>
                                                        </form>
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
                                                <div class="p-3 border-bottom border-end h-100 bg-light">
                                                    <!-- نفس محتوى كارت الطالب مع تعديل الألوان -->
                                                    <div class="position-absolute top-0 start-0 mt-2 ms-2">
                                                        <span class="badge bg-danger rounded-pill">
                                                            <?php echo $index + 1; ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="text-center mb-3 mt-2">
                                                        <div class="avatar-circle mb-3 mx-auto bg-secondary">
                                                            <span class="avatar-text">
                                                                <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                                                            </span>
                                                        </div>
                                                        <h5 class="mb-1 text-muted">
                                                            <?php echo htmlspecialchars($student['name']); ?>
                                                        </h5>
                                                        <div class="text-muted small">
                                                            انضم في <?php echo date('Y/m/d', strtotime($student['joined_at'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- باقي محتوى الكارت كما هو -->
                                                    <div class="student-details">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="bi bi-calendar3 text-muted me-2"></i>
                                                            <span>العمر: <?php echo $student['age']; ?> سنة</span>
                                                        </div>
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="bi bi-envelope text-muted me-2"></i>
                                                            <span class="text-truncate">
                                                                <?php echo htmlspecialchars($student['email']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-phone text-muted me-2"></i>
                                                            <span><?php echo htmlspecialchars($student['phone']); ?></span>
                                                        </div>
                                                    </div>

                                                    <!-- Actions -->
                                                    <div class="student-actions mt-3 pt-3 border-top">
                                                        <!-- صف أول من الأزرار -->
                                                        <div class="d-flex gap-2 justify-content-center mb-2">
                                                            <a href="edit_student.php?id=<?php echo $student['id']; ?>" 
                                                               class="btn btn-sm btn-primary flex-fill">
                                                                <i class="bi bi-pencil-square"></i>
                                                                تعديل
                                                            </a>
                                                            
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-warning flex-fill"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#moveStudentModal"
                                                                    data-student-id="<?php echo $student['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($student['name']); ?>">
                                                                <i class="bi bi-arrow-left-right"></i>
                                                                نقل
                                                            </button>
                                                        </div>
                                                        
                                                        <!-- صف ثاني من الأزرار -->
                                                        <div class="d-flex gap-2 justify-content-center">
                                                            <a href="https://wa.me/<?php echo $student['phone']; ?>" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-success flex-fill">
                                                                <i class="bi bi-whatsapp"></i>
                                                                واتساب
                                                            </a>
                                                            
                                                            <button type="button" 
                                                                    class="btn btn-sm <?php echo $student['is_active'] ? 'btn-secondary' : 'btn-success'; ?> w-100"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#toggleStatusModal"
                                                                    data-student-id="<?php echo $student['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                                    data-student-status="<?php echo $student['is_active']; ?>">
                                                                <i class="bi <?php echo $student['is_active'] ? 'bi-pause-fill' : 'bi-play-fill'; ?>"></i>
                                                                <?php echo $student['is_active'] ? 'إيقاف' : 'تفعيل'; ?>
                                                            </button>
                                                        </div>
                                                        
                                                        <!-- زر الحذف -->
                                                        <div class="text-center mt-2">
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                                <button type="submit" name="remove_student" 
                                                                        class="btn btn-sm btn-outline-danger w-100"
                                                                        onclick="return confirm('هل أنت متأكد من حذف هذا الطالب من الحلقة؟')">
                                                                    <i class="bi bi-person-x"></i>
                                                                    حذف
                                                                </button>
                                                            </form>
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

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-plus"></i> إضافة طالب
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <p class="mb-3">قم بإنشاء حساب طالب جديد وإضافته للحلقة</p>
                        <a href="add_student.php?circle_id=<?php echo $circle_id; ?>" class="btn btn-success w-100">
                            <i class="bi bi-person-plus-fill"></i> إنشاء حساب طالب جديد
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal نقل الطالب -->
<div class="modal fade" id="moveStudentModal" tabindex="-1" data-current-circle="<?php echo $circle_id; ?>" data-current-department="<?php echo $circle['department_id']; ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">نقل الطالب لحلقة أخرى</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="student_id" id="moveStudentId">
                    <p>نقل الطالب: <strong id="moveStudentName"></strong></p>
                    <div class="mb-3">
                        <label for="department_id" class="form-label">اختر الإدارة</label>
                        <select class="form-select" id="department_id" required>
                            <option value="">اختر الإدارة...</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"
                                        <?php echo $dept['id'] == $circle['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_circle_id" class="form-label">اختر الحلقة الجديدة</label>
                        <select class="form-select" id="new_circle_id" name="new_circle_id" required disabled>
                            <option value="">اختر الحلقة...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="move_student" class="btn btn-warning" disabled>نقل الطالب</button>
                </div>
            </form>
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
                <div class="status-icon">
                    <i class="bi bi-question-circle text-warning"></i>
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

// تهيئة مودال نقل الطالب
document.getElementById('moveStudentModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const studentId = button.getAttribute('data-student-id');
    const studentName = button.getAttribute('data-student-name');
    
    document.getElementById('moveStudentId').value = studentId;
    document.getElementById('moveStudentName').textContent = studentName;
    
    // تحديث قائمة الحلقات عند اختيار الإدارة
    const departmentSelect = document.getElementById('department_id');
    if (departmentSelect.value) {
        loadCircles(departmentSelect.value);
    }
});

// دالة تحميل الحلقات حسب الإدارة المختارة
function loadCircles(departmentId) {
    const currentCircle = document.getElementById('moveStudentModal').getAttribute('data-current-circle');
    const circleSelect = document.getElementById('new_circle_id');
    const moveButton = document.querySelector('button[name="move_student"]');
    
    // تفعيل/تعطيل قائمة الحلقات وزر النقل
    circleSelect.disabled = true;
    moveButton.disabled = true;
    
    fetch(`get_circles.php?department_id=${departmentId}&exclude_circle=${currentCircle}`)
        .then(response => response.json())
        .then(circles => {
            circleSelect.innerHTML = '<option value="">اختر الحلقة...</option>';
            
            circles.forEach(circle => {
                const option = document.createElement('option');
                option.value = circle.id;
                option.textContent = circle.name;
                circleSelect.appendChild(option);
            });
            
            circleSelect.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            circleSelect.innerHTML = '<option value="">حدث خطأ في تحميل الحلقات</option>';
        });
}

// تحديث الحلقات عند تغيير الإدارة
document.getElementById('department_id').addEventListener('change', function() {
    if (this.value) {
        loadCircles(this.value);
    } else {
        const circleSelect = document.getElementById('new_circle_id');
        circleSelect.innerHTML = '<option value="">اختر الحلقة...</option>';
        circleSelect.disabled = true;
        document.querySelector('button[name="move_student"]').disabled = true;
    }
});

// تفعيل/تعطيل زر النقل حسب اختيار الحلقة
document.getElementById('new_circle_id').addEventListener('change', function() {
    document.querySelector('button[name="move_student"]').disabled = !this.value;
});

// تهيئة مودال تفعيل/إيقاف الحساب
document.getElementById('toggleStatusModal').addEventListener('show.bs.modal', function (event) {
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
        icon.className = 'bi bi-pause-circle text-danger';
    } else {
        // تفعيل الحساب
        title.textContent = 'تأكيد تفعيل الحساب';
        message.innerHTML = `هل أنت متأكد من تفعيل حساب الطالب <strong class="text-success">${studentName}</strong>؟`;
        submitBtn.className = 'btn btn-success px-4';
        submitBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i> تفعيل الحساب';
        icon.className = 'bi bi-play-circle text-success';
    }
});

function updateWhatsAppPreview() {
    const phone = document.getElementById('phone').value;
    const countryCode = document.getElementById('country_id').options[
        document.getElementById('country_id').selectedIndex
    ].getAttribute('data-code');
    
    const preview = document.getElementById('whatsapp-preview');
    const link = document.getElementById('whatsapp-link');
    
    if (phone && countryCode) {
        const whatsappNumber = countryCode + phone;
        preview.textContent = '+' + whatsappNumber;
        link.href = 'https://wa.me/' + whatsappNumber;
        link.classList.remove('disabled');
    } else {
        preview.textContent = '';
        link.href = '#';
        link.classList.add('disabled');
    }
}
</script>

<style>
.avatar-circle {
    width: 64px;
    height: 64px;
    background-color: #4CAF50;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-text {
    color: white;
    font-size: 24px;
    font-weight: bold;
}

.student-card {
    transition: all 0.3s ease;
}

.student-card:hover {
    background-color: #f8f9fa;
}

.student-details {
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .student-card {
        border-bottom: 1px solid #dee2e6;
    }
}

.inactive-students .student-card {
    opacity: 0.8;
}

.inactive-students .btn-success {
    background-color: #6c757d;
    border-color: #6c757d;
}

.inactive-students .btn-primary {
    background-color: #6c757d;
    border-color: #6c757d;
}

.inactive-students .btn-warning {
    background-color: #8a8a8a;
    border-color: #8a8a8a;
    color: white;
}

.inactive-students .btn-outline-danger {
    color: #6c757d;
    border-color: #6c757d;
}

.inactive-students .btn-outline-danger:hover {
    background-color: #6c757d;
    color: white;
}

.inactive-students .avatar-circle {
    background-color: #6c757d;
}

.inactive-students .student-details {
    color: #6c757d;
}

.status-icon {
    line-height: 1;
}

.modal-content {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
    padding-bottom: 0;
}

.modal-body {
    padding-top: 0;
}

.whatsapp-preview .btn {
    padding: 0.75rem;
    font-size: 1.1rem;
}

.whatsapp-preview .btn.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.whatsapp-preview .h5 {
    font-family: monospace;
    letter-spacing: 1px;
}

.phone-number {
    letter-spacing: 0.5px;
    font-size: 0.9rem;
}

.btn-success:hover .phone-number {
    color: white;
}
</style>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 