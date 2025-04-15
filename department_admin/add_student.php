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
    SELECT sc.*, d.name as department_name, d.student_gender 
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

// الحصول على قائمة الدول
$countries = $conn->query("
    SELECT ID, Name, CountryCode 
    FROM countries 
    ORDER BY `Order`, Name
")->fetch_all(MYSQLI_ASSOC);

// معالجة إضافة الطالب الجديد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $country_id = sanitize_input($_POST['country_id']);
    
    // الحصول على رمز الدولة
    $stmt = $conn->prepare("SELECT CountryCode FROM countries WHERE ID = ?");
    $stmt->bind_param("s", $country_id);
    $stmt->execute();
    $country_result = $stmt->get_result()->fetch_assoc();
    $country_code = $country_result['CountryCode'];
    
    // تنسيق رقم الهاتف بالصيغة الدولية
    $phone_number = sanitize_input($_POST['phone']);
    $phone = $country_code . $phone_number;
    $age = (int)$_POST['age'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // التحقق من عدم وجود البريد الإلكتروني مسبقاً
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $error_message = "البريد الإلكتروني مستخدم مسبقاً";
    } else {
        // إضافة المستخدم الجديد
        $conn->begin_transaction();
        try {
            // إنشاء حساب المستخدم
            $stmt = $conn->prepare("
                INSERT INTO users (
                    name, email, password, phone, age, gender, 
                    role, preferred_time, country_id
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 
                    'student', ?, ?
                )
            ");
            $stmt->bind_param(
                "ssssssss",
                $name, $email, $password, $phone, $age,
                $circle['student_gender'], $circle['circle_time'],
                $country_id
            );
            $stmt->execute();
            $student_id = $conn->insert_id;
            
            // إضافة الطالب للحلقة
            $stmt = $conn->prepare("INSERT INTO circle_students (circle_id, student_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $circle_id, $student_id);
            $stmt->execute();
            
            $conn->commit();
            $success_message = "تم إنشاء حساب الطالب وإضافته للحلقة بنجاح";
            
            // إعادة التوجيه بعد نجاح العملية
            header("Location: circle_students.php?circle_id=" . $circle_id . "&success=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "حدث خطأ أثناء إنشاء الحساب: " . $e->getMessage();
        }
    }
}

$pageTitle = 'إضافة طالب جديد - ' . $circle['name'];
ob_start();
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-3">إضافة طالب جديد</h2>
            <div class="text-muted">
                الحلقة: <?php echo htmlspecialchars($circle['name']); ?> |
                القسم: <?php echo htmlspecialchars($circle['department_name']); ?>
            </div>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="circle_students.php?circle_id=<?php echo $circle_id; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> عودة لقائمة الطلاب
            </a>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-plus-fill"></i> معلومات الطالب الجديد
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label for="name" class="form-label">اسم الطالب</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="country_id" class="form-label">الدولة</label>
                                <select class="form-select" id="country_id" name="country_id" required>
                                    <option value="">اختر الدولة...</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['ID']; ?>" 
                                                data-code="<?php echo $country['CountryCode']; ?>">
                                            <?php echo htmlspecialchars($country['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="phone" class="form-label">رقم الهاتف</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="phone-code">+</span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           required pattern="[0-9]+"
                                           placeholder="رقم الهاتف بدون صفر البداية">
                                </div>
                                <div class="form-text">أدخل الرقم بدون صفر البداية</div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="age" class="form-label">العمر</label>
                                <input type="number" class="form-control" id="age" name="age" 
                                       min="<?php echo $circle['age_from']; ?>" 
                                       max="<?php echo $circle['age_to']; ?>" required>
                                <div class="form-text">
                                    العمر المسموح: من <?php echo $circle['age_from']; ?> إلى <?php echo $circle['age_to']; ?> سنة
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <label class="form-label">معاينة رقم الواتساب</label>
                                        <div class="h5 mb-0" id="whatsapp-preview"></div>
                                        <small class="text-muted">سيتم استخدام هذا الرقم لفتح محادثة واتساب</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                <input type="password" class="form-control" id="confirm_password" required>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row gap-2 mt-4">
                            <button type="submit" class="btn btn-success flex-fill">
                                <i class="bi bi-person-plus-fill"></i> إنشاء الحساب وإضافة للحلقة
                            </button>
                            <a href="circle_students.php?circle_id=<?php echo $circle_id; ?>" 
                               class="btn btn-light flex-fill">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// إضافة كود لتحديث رمز الدولة عند اختيار دولة
document.getElementById('country_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const countryCode = selectedOption.getAttribute('data-code');
    document.getElementById('phone-code').textContent = '+' + (countryCode || '');
});

// التحقق من صحة رقم الهاتف
document.getElementById('phone').addEventListener('input', function() {
    // حذف أي أحرف غير رقمية
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // حذف الصفر من البداية إذا وجد
    if (this.value.startsWith('0')) {
        this.value = this.value.substring(1);
    }
});

// التحقق من تطابق كلمة المرور
document.getElementById('confirm_password').addEventListener('input', function() {
    if (this.value !== document.getElementById('password').value) {
        this.setCustomValidity('كلمات المرور غير متطابقة');
    } else {
        this.setCustomValidity('');
    }
});

// عرض معاينة رقم الواتساب عند إدخال الرقم
function updateWhatsAppPreview() {
    const phone = document.getElementById('phone').value;
    const countryCode = document.getElementById('country_id').options[
        document.getElementById('country_id').selectedIndex
    ].getAttribute('data-code');
    
    if (phone && countryCode) {
        const whatsappNumber = countryCode + phone;
        document.getElementById('whatsapp-preview').textContent = '+' + whatsappNumber;
    } else {
        document.getElementById('whatsapp-preview').textContent = '';
    }
}

document.getElementById('phone').addEventListener('input', updateWhatsAppPreview);
document.getElementById('country_id').addEventListener('change', updateWhatsAppPreview);

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