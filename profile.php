<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Ensure user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user information
$stmt = $conn->prepare("
    SELECT u.*, d.name as department_name, c.name as circle_name
    FROM users u
    LEFT JOIN department_admins da ON u.id = da.user_id
    LEFT JOIN departments d ON da.department_id = d.id
    LEFT JOIN circle_students cs ON u.id = cs.student_id
    LEFT JOIN study_circles c ON cs.circle_id = c.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Map roles to Arabic names
$role_names = [
    'super_admin' => 'مدير النظام',
    'department_admin' => 'مدير القسم',
    'teacher' => 'معلم',
    'supervisor' => 'مشرف',
    'student' => 'طالب'
];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "صيغة البريد الإلكتروني غير صحيحة";
    } else {
        // Check if email exists for another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['error'] = "البريد الإلكتروني مستخدم من قبل مستخدم آخر";
        } else {
            // Update user information
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "تم تحديث الملف الشخصي بنجاح";
                // Refresh user data
                header('Location: profile.php');
                exit();
            } else {
                $_SESSION['error'] = "حدث خطأ أثناء تحديث الملف الشخصي";
            }
        }
    }
}

$pageTitle = 'الملف الشخصي';
ob_start();
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="avatar-circle">
                            <span class="avatar-text"><?php echo substr($user['name'], 0, 2); ?></span>
                        </div>
                    </div>
                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <p class="text-muted"><?php echo $role_names[$user['role']] ?? $user['role']; ?></p>
                    <hr>
                    <?php if ($user['department_name']): ?>
                        <p class="mb-1"><strong>القسم:</strong> <?php echo htmlspecialchars($user['department_name']); ?></p>
                    <?php endif; ?>
                    <?php if ($user['circle_name']): ?>
                        <p class="mb-1"><strong>الحلقة:</strong> <?php echo htmlspecialchars($user['circle_name']); ?></p>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="change_password.php" class="btn btn-primary">تغيير كلمة المرور</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">تعديل الملف الشخصي</h5>
                </div>
                <div class="card-body">
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

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">الاسم</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            <div class="invalid-feedback">
                                يرجى إدخال الاسم
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <div class="invalid-feedback">
                                يرجى إدخال بريد إلكتروني صحيح
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">رقم الهاتف</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">الدور</label>
                            <input type="text" class="form-control" id="role" 
                                   value="<?php echo $role_names[$user['role']] ?? $user['role']; ?>" readonly>
                        </div>

                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
}

.avatar-text {
    color: white;
    font-size: 2rem;
    font-weight: bold;
    text-transform: uppercase;
}
</style>

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
</script>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
