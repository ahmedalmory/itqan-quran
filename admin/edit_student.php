<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';

// التأكد من أن المستخدم مدير النظام
requireRole('super_admin');

// التحقق من وجود معرف الطالب
if (!isset($_GET['id'])) {
    header('Location: circles.php');
    exit;
}

$student_id = (int)$_GET['id'];
$success_message = '';
$error_message = '';

// الحصول على بيانات الطالب
$stmt = $conn->prepare("
    SELECT u.*, cs.circle_id, c.Name as country_name, 
          c.ID as country_id, c.CountryCode
    FROM users u 
    LEFT JOIN circle_students cs ON u.id = cs.student_id
    LEFT JOIN countries c ON CAST(u.country_id AS CHAR) = c.ID
    WHERE u.id = ? AND u.role = 'student'
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    header('Location: circles.php');
    exit;
}

// استخراج رقم الهاتف بدون كود الدولة
$phone_number = '';
if ($student['CountryCode'] && $student['phone']) {
    $phone_number = preg_replace('/^' . $student['CountryCode'] . '/', '', $student['phone']);
}

// الحصول على قائمة الدول
$countries = $conn->query("
    SELECT ID, Name, CountryCode 
    FROM countries 
    ORDER BY `Order`, Name
")->fetch_all(MYSQLI_ASSOC);

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $country_id = sanitize_input($_POST['country_id']);
    $age = (int)$_POST['age'];
    
    // الحصول على رمز الدولة
    $stmt = $conn->prepare("SELECT CountryCode FROM countries WHERE ID = ?");
    $stmt->bind_param("s", $country_id);
    $stmt->execute();
    $country_result = $stmt->get_result()->fetch_assoc();
    $country_code = $country_result['CountryCode'];
    
    // تنسيق رقم الهاتف
    $phone = $country_code . $phone_number;
    
    // التحقق من عدم وجود البريد الإلكتروني مع مستخدم آخر
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $error_message = "البريد الإلكتروني مستخدم مسبقاً";
    } else {
        // تحديث كلمة المرور إذا تم إدخالها
        $password_sql = '';
        $types = "ssssis"; // name, email, phone, country_id, age, id
        $params = [$name, $email, $phone, $country_id, $age, $student_id];
        
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $password_sql = ", password = ?";
            $types = "ssssisi"; // إضافة كلمة المرور للباراميترز
            array_splice($params, -1, 0, [$password]); // إضافة كلمة المرور قبل الـ id
        }
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, country_id = ?, age = ? $password_sql
            WHERE id = ?
        ");
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $success_message = "تم تحديث بيانات الطالب بنجاح";
        } else {
            $error_message = "حدث خطأ أثناء تحديث البيانات";
        }
    }
}

$pageTitle = 'تعديل بيانات الطالب: ' . $student['name'];
ob_start();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="mb-3">تعديل بيانات الطالب</h2>
            <div class="text-muted">
                <?php echo htmlspecialchars($student['name']); ?>
            </div>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="circle_students.php?circle_id=<?php echo $student['circle_id']; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> عودة لقائمة الطلاب
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

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-gear"></i> معلومات الطالب
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="name" class="form-label">اسم الطالب</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($student['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="country_id" class="form-label">الدولة</label>
                                <select class="form-select" id="country_id" name="country_id" required>
                                    <option value="">اختر الدولة...</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['ID']; ?>" 
                                                data-code="<?php echo $country['CountryCode']; ?>"
                                                <?php echo $country['ID'] === $student['country_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($country['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="phone" class="form-label">رقم الهاتف</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="phone-code">+<?php echo $student['CountryCode']; ?></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           required pattern="[0-9]+"
                                           value="<?php echo htmlspecialchars($phone_number); ?>"
                                           placeholder="رقم الهاتف بدون صفر البداية">
                                </div>
                                <div class="form-text">أدخل الرقم بدون صفر البداية</div>
                            </div>
                            <div class="col-md-4">
                                <label for="age" class="form-label">العمر</label>
                                <input type="number" class="form-control" id="age" name="age" 
                                       value="<?php echo $student['age']; ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <label class="form-label mb-3">معاينة رقم الواتساب</label>
                                        <div class="whatsapp-preview">
                                            <a href="#" 
                                               class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2" 
                                               id="whatsapp-link" 
                                               style="display: none;"
                                               target="_blank">
                                                <i class="bi bi-whatsapp fs-5"></i>
                                                <span id="whatsapp-preview" class="h5 mb-0"></span>
                                            </a>
                                            <div id="whatsapp-placeholder" class="text-center text-muted p-3">
                                                أدخل رقم الهاتف والدولة لمعاينة رابط الواتساب
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label">كلمة المرور الجديدة (اختياري)</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <div class="form-text">اترك الحقل فارغاً للإبقاء على كلمة المرور الحالية</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                <input type="password" class="form-control" id="confirm_password">
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="circle_students.php?circle_id=<?php echo $student['circle_id']; ?>" 
                               class="btn btn-light">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle"></i> حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.whatsapp-preview {
    min-height: 60px;
}

#whatsapp-placeholder {
    border: 2px dashed #dee2e6;
    border-radius: 6px;
}

.whatsapp-preview .btn {
    padding: 0.75rem;
    font-size: 1.1rem;
}

.whatsapp-preview .h5 {
    font-family: monospace;
    letter-spacing: 1px;
    margin: 0;
}
</style>

<script>
// تحديث كود الدولة عند اختيار دولة
document.getElementById('country_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const countryCode = selectedOption.getAttribute('data-code');
    document.getElementById('phone-code').textContent = countryCode ? '+' + countryCode : '+';
    updateWhatsAppPreview();
});

// التحقق من صحة رقم الهاتف وتحديثه
document.getElementById('phone').addEventListener('input', function() {
    // حذف أي أحرف غير رقمية
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // حذف الصفر من البداية إذا وجد
    if (this.value.startsWith('0')) {
        this.value = this.value.substring(1);
    }
    
    updateWhatsAppPreview();
});

// التحقق من تطابق كلمة المرور
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    if (password && this.value !== password) {
        this.setCustomValidity('كلمات المرور غير متطابقة');
    } else {
        this.setCustomValidity('');
    }
});

// عرض معاينة رقم الواتساب
function updateWhatsAppPreview() {
    const phone = document.getElementById('phone').value;
    const countryCode = document.getElementById('country_id').options[
        document.getElementById('country_id').selectedIndex
    ].getAttribute('data-code');
    
    const preview = document.getElementById('whatsapp-preview');
    const link = document.getElementById('whatsapp-link');
    const placeholder = document.getElementById('whatsapp-placeholder');
    
    if (phone && countryCode) {
        const whatsappNumber = countryCode + phone;
        preview.textContent = '+' + whatsappNumber;
        const cleanNumber = whatsappNumber.replace(/[^0-9]/g, '');
        link.href = 'https://wa.me/' + cleanNumber;
        link.style.display = 'flex';
        placeholder.style.display = 'none';
    } else {
        preview.textContent = '';
        link.href = '#';
        link.style.display = 'none';
        placeholder.style.display = 'block';
    }
}

// تهيئة رقم الهاتف عند تحميل الصفحة
window.addEventListener('load', function() {
    updateWhatsAppPreview();
});

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