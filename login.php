<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    if (!validate_email($email)) {
        $errors[] = __('invalid_email_format');
    }

    if (empty($password)) {
        $errors[] = __('password_required');
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                login($user);
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'super_admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'department_admin':
                        header("Location: department_admin/index.php");
                        break;
                    case 'teacher':
                        header("Location: teacher/dashboard.php");
                        break;
                    case 'supervisor':
                        header("Location: supervisor/dashboard.php");
                        break;
                    case 'student':
                        header("Location: student/index.php");
                        break;
                    default:
                        header("Location: dashboard.php");
                }
                exit();
            } else {
                $errors[] = __('invalid_credentials');
            }
        } else {
            $errors[] = __('invalid_credentials');
        }
    }
}

$pageTitle = __('login');
ob_start();
?>

<div class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-box">
                    <div class="text-center mb-4">
                        <h1 class="login-title"><?php echo __('login'); ?></h1>
                        <p class="login-subtitle"><?php echo __('login_welcome_message'); ?></p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="login-form">
                        <div class="form-group mb-3">
                            <label for="email" class="form-label"><?php echo __('email'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="password" class="form-label"><?php echo __('password'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg"><?php echo __('login_button'); ?></button>
                        </div>

                        <div class="text-center">
                            <p class="mb-0"><?php echo __('dont_have_account'); ?> 
                                <a href="register.php"><?php echo __('register_here'); ?></a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 2rem 0;
}

.login-box {
    background: white;
    border-radius: 15px;
    padding: 2.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}



.login-title {
    font-size: 1.75rem;
    color: var(--primary-dark);
    margin-bottom: 0.5rem;
}

.login-subtitle {
    color: #666;
    margin-bottom: 2rem;
}

.login-form .input-group-text {
    background-color: transparent;
    border-right: none;
    color: var(--primary-color);
}

.login-form .form-control {
    border-left: none;
}

.login-form .form-control:focus {
    box-shadow: none;
    border-color: #ced4da;
}

.login-form .btn-primary {
    padding: 0.8rem;
    font-weight: 600;
}

.login-form a {
    color: var(--primary-color);
    text-decoration: none;
}

.login-form a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}
</style>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
