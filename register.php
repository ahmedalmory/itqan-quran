<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Check if user has completed step 1
if (!isset($_SESSION['registration_data'])) {
    header("Location: register_step1.php");
    exit();
}

$registration_data = $_SESSION['registration_data'];
$errors = [];

// تحضير الحلقات المتاحة
$available_circles = $registration_data['available_circles'];

// إذا لم تكن الحلقات العشوائية موجودة في الجلسة، قم بإنشائها
if (!isset($_SESSION['random_circles'])) {
    // خلط الحلقات بشكل عشوائي
    shuffle($available_circles);
    // أخذ أول 4 حلقات فقط
    $_SESSION['random_circles'] = array_slice($available_circles, 0, 4);
}

// استخدام الحلقات المحفوظة في الجلسة
$available_circles = $_SESSION['random_circles'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $phone = sanitize_input($_POST['phone']);
    $circle_id = filter_input(INPUT_POST, 'circle_id', FILTER_VALIDATE_INT);
    $country_id = sanitize_input($_POST['country_id']);

    // Validate inputs
    if (empty($name)) {
        $errors[] = "Name is required";
    }

    if (!validate_email($email)) {
        $errors[] = "Invalid email format";
    }

    if (!validate_password($password)) {
        $errors[] = "Password must be at least 8 characters long";
    }

    // تحسين التحقق من رقم الهاتف
    $phone = preg_replace('/[^0-9]/', '', $phone); // إزالة كل الأحرف ما عدا الأرقام
    
    // التحقق من أن رقم الهاتف ليس فارغاً
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    // التحقق من طول رقم الهاتف (بين 8 و 15 رقم)
    if (strlen($phone) < 8 || strlen($phone) > 15) {
        $errors[] = "Phone number must be between 8 and 15 digits";
    }

    if (!$circle_id) {
        $errors[] = "Please select a valid circle";
    } else {
        // التحقق من وجود الحلقة في المصفوفة المتاحة
        $circle_exists = false;
        foreach ($available_circles as $circle) {
            if ($circle['id'] == $circle_id) {
                $circle_exists = true;
                break;
            }
        }
        if (!$circle_exists) {
            $errors[] = "Please select a valid circle";
        }
    }

    if (empty($country_id)) {
        $errors[] = "select_country_required";
    }

    // تحديث الكود عند إدخال المستخدم
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // الحصول على كود الدولة
            $stmt = $conn->prepare("SELECT CountryCode FROM countries WHERE ID = ?");
            $stmt->bind_param("s", $country_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // إزالة الصفر في البداية إذا وجد
                $phone = ltrim($phone, '0');
                
                // تنسيق رقم الهاتف بتنسيق الواتساب (+CountryCode PhoneNumber)
                $formatted_phone = '+' . $row['CountryCode'] . $phone;
                
                // التحقق من صحة التنسيق النهائي
                if (!preg_match('/^\+[1-9]\d{1,3}\d{8,14}$/', $formatted_phone)) {
                    throw new Exception("Invalid phone number format");
                }
            } else {
                throw new Exception("Invalid country");
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'student';

            // استخدام رقم الهاتف المنسق
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, country_id, age, gender, role, preferred_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $name, $email, $hashed_password, $formatted_phone, 
                            $country_id, $registration_data['age'], 
                            $registration_data['gender'], $role, 
                            $registration_data['preferred_time']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create user account");
            }

            $user_id = $stmt->insert_id;

            // Add student to circle
            $stmt = $conn->prepare("INSERT INTO circle_students (circle_id, student_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $circle_id, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to add student to circle");
            }

            // Commit transaction
            $conn->commit();
            
            // Clear registration data
            unset($_SESSION['registration_data']);
            // Clear random circles
            unset($_SESSION['random_circles']);
            
            // Set session data
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $role;
            $_SESSION['name'] = $name;
            
            header("Location: subscription_select.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = __('complete_registration');
ob_start();
?>

<div class="register-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="register-box">
                    <div class="text-center mb-4">
                        <h1 class="register-title"><?php echo __('complete_registration'); ?></h1>
                        <p class="register-subtitle"><?php echo __('select_circle_and_complete'); ?></p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars(__($error)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="register-form">
                        <div class="circles-section mb-4">
                            <label class="form-label fw-bold mb-3"><?php echo __('select_suitable_circle'); ?></label>
                            <div class="row g-3">
                                <?php foreach ($available_circles as $circle): ?>
                                <div class="col-md-6">
                                    <div class="circle-card">
                                        <input type="radio" class="btn-check" name="circle_id" 
                                               id="circle_<?php echo $circle['id']; ?>" 
                                               value="<?php echo $circle['id']; ?>" required
                                               <?php echo (isset($_POST['circle_id']) && $_POST['circle_id'] == $circle['id']) ? 'checked' : ''; ?>>
                                        <label class="card h-100" for="circle_<?php echo $circle['id']; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($circle['name']); ?></h5>
                                                <h6 class="card-subtitle mb-3 text-muted">
                                                    <?php echo htmlspecialchars($circle['department_name']); ?>
                                                </h6>
                                                <div class="circle-info">
                                                    <p>
                                                        <i class="bi bi-person-circle"></i>
                                                        <span class="info-label"><?php echo __('teacher'); ?>:</span>
                                                        <?php echo htmlspecialchars($circle['teacher_name']); ?>
                                                    </p>
                                                    <p>
                                                        <i class="bi bi-clock"></i>
                                                        <span class="info-label"><?php echo __('circle_time'); ?>:</span>
                                                        <?php echo format_prayer_time($circle['circle_time']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="personal-info-section">
                            <div class="form-group mb-3">
                                <label for="name" class="form-label"><?php echo __('full_name'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="email" class="form-label"><?php echo __('email'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="password" class="form-label"><?php echo __('password'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <small class="text-muted"><?php echo __('password_hint'); ?></small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="phone" class="form-label"><?php echo __('phone'); ?></label>
                                <div class="input-group">
                                    <select class="form-select" id="country_id" name="country_id" required style="max-width: 200px;">
                                        <option value=""><?php echo __('select_country'); ?></option>
                                        <?php
                                        $countries_query = "SELECT ID, name, CountryCode FROM countries ORDER BY `Order`";
                                        $countries = $conn->query($countries_query)->fetch_all(MYSQLI_ASSOC);
                                        foreach ($countries as $country): ?>
                                            <option value="<?php echo $country['ID']; ?>" 
                                                    data-code="<?php echo $country['CountryCode']; ?>"
                                                    <?php echo (isset($_POST['country_id']) && $_POST['country_id'] == $country['ID']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($country['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="input-group-text" id="phone-code">+</span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           required pattern="[0-9]+"
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                           placeholder="<?php echo __('phone_without_zero'); ?>">
                                </div>
                                <div class="form-text"><?php echo __('phone_hint'); ?></div>

                                <!-- معاينة رقم الواتساب 
                                <div class="mt-3">
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
                                            <?php echo __('whatsapp_preview_hint'); ?>
                                        </div>
                                    </div>
                                </div>-->
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <?php echo __('complete_registration_button'); ?>
                            </button>
                            <a href="register_step1.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> <?php echo __('back_to_step1'); ?>
                            </a>
                        </div>

                        <div class="text-center mt-3">
                            <p><?php echo __('already_have_account'); ?> 
                                <a href="login.php"><?php echo __('login_here'); ?></a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- إضافة JavaScript في نهاية الصفحة -->
<script>
// تحديث كود الدولة عند اختيار دولة
document.getElementById('country_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const countryCode = selectedOption.getAttribute('data-code');
    document.getElementById('phone-code').textContent = countryCode ? '+' + countryCode : '+';
    updateWhatsAppPreview();
});

// تحسين معالجة رقم الهاتف
document.getElementById('phone').addEventListener('input', function() {
    // إزالة كل شيء ما عدا الأرقام
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // إزالة الصفر في البداية إذا وجد
    if (this.value.startsWith('0')) {
        this.value = this.value.substring(1);
    }
});

// تهيئة رقم الهاتف عند تحميل الصفحة
window.addEventListener('load', function() {
    var phoneInput = document.getElementById('phone');
    if (phoneInput.value.startsWith('0')) {
        phoneInput.value = phoneInput.value.substring(1);
    }
});
</script>

<style>
.register-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 2rem 0;
}

.register-box {
    background: white;
    border-radius: 15px;
    padding: 2.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.register-logo {
    max-width: 120px;
}

.register-title {
    font-size: 1.75rem;
    color: var(--primary-dark);
    margin-bottom: 0.5rem;
}

.register-subtitle {
    color: #666;
}

.circle-card .card {
    transition: all 0.3s ease;
    border: 2px solid #eee;
    cursor: pointer;
}

.circle-card .card:hover {
    border-color: var(--primary-color);
    transform: translateY(-3px);
}

.btn-check:checked + .card {
    border-color: var(--primary-color);
    background-color: rgba(var(--primary-rgb), 0.05);
}

.circle-info p {
    margin-bottom: 0.5rem;
    color: #666;
    font-size: 0.9rem;
}

.circle-info i {
    color: var(--primary-color);
    margin-left: 0.5rem;
    margin-right: 0.5rem;
}

.info-label {
    font-weight: 600;
    color: #444;
}

.input-group-text {
    background-color: transparent;
    border-left: none;
    color: var(--primary-color);
}

.form-control {
    border-right: none;
}

.form-control:focus {
    box-shadow: none;
    border-color: #ced4da;
}

.form-control:focus + .input-group-text {
    border-color: #ced4da;
}

@media (max-width: 768px) {
    .register-box {
        padding: 1.5rem;
    }
}

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

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
