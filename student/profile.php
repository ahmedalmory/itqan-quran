<?php
// Start output buffering
ob_start();

require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $age = (int)$_POST['age'];
    $preferred_time = sanitize_input($_POST['preferred_time']);
    
    // Validate email uniqueness if changed
    $email_exists = false;
    if ($email !== $user['email']) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $email_exists = $stmt->get_result()->num_rows > 0;
    }

    if ($email_exists) {
        $error_message = "البريد الإلكتروني مستخدم من قبل";
    } else {
        // Update user data
        $stmt = $conn->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, age = ?, preferred_time = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssisi", $name, $email, $phone, $age, $preferred_time, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "تم تحديث البيانات بنجاح";
            // Update user data for display
            $user['name'] = $name;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $user['age'] = $age;
            $user['preferred_time'] = $preferred_time;
        } else {
            $error_message = "حدث خطأ أثناء تحديث البيانات";
        }
    }

    // Handle password change
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "تم تحديث البيانات وكلمة المرور بنجاح";
            } else {
                $error_message = "حدث خطأ أثناء تحديث كلمة المرور";
            }
        } else {
            $error_message = "كلمة المرور الحالية غير صحيحة";
        }
    }
}

$pageTitle = 'الملف الشخصي';
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

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">تعديل البيانات الشخصية</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">الاسم</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">رقم الجوال</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="age" class="form-label">العمر</label>
                            <input type="number" class="form-control" id="age" name="age" 
                                   value="<?php echo $user['age']; ?>" min="4" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="preferred_time" class="form-label">الوقت المفضل</label>
                        <select class="form-select" id="preferred_time" name="preferred_time">
                            <option value="">اختر الوقت المفضل</option>
                            <option value="after_fajr" <?php echo $user['preferred_time'] === 'after_fajr' ? 'selected' : ''; ?>>بعد الفجر</option>
                            <option value="after_dhuhr" <?php echo $user['preferred_time'] === 'after_dhuhr' ? 'selected' : ''; ?>>بعد الظهر</option>
                            <option value="after_asr" <?php echo $user['preferred_time'] === 'after_asr' ? 'selected' : ''; ?>>بعد العصر</option>
                            <option value="after_maghrib" <?php echo $user['preferred_time'] === 'after_maghrib' ? 'selected' : ''; ?>>بعد المغرب</option>
                            <option value="after_isha" <?php echo $user['preferred_time'] === 'after_isha' ? 'selected' : ''; ?>>بعد العشاء</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">تغيير كلمة المرور</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               minlength="6" required>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                        <input type="password" class="form-control" id="confirm_password" 
                               minlength="6" required>
                    </div>

                    <button type="submit" class="btn btn-primary">تغيير كلمة المرور</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">معلومات الحساب</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <strong>نوع الحساب:</strong>
                        <span class="badge bg-primary">طالب</span>
                    </li>
                    <li class="list-group-item">
                        <strong>تاريخ التسجيل:</strong>
                        <br>
                        <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                    </li>
                    <li class="list-group-item">
                        <strong>آخر تحديث:</strong>
                        <br>
                        <?php echo date('Y-m-d', strtotime($user['updated_at'])); ?>
                    </li>
                </ul>
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

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    if (this.value !== newPassword) {
        this.setCustomValidity('كلمات المرور غير متطابقة');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (this.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity('كلمات المرور غير متطابقة');
    } else {
        confirmPassword.setCustomValidity('');
    }
});
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
