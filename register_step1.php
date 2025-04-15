<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gender = sanitize_input($_POST['gender']);
    $age = sanitize_input($_POST['age']);
    $preferred_time = sanitize_input($_POST['preferred_time']);

    if (!validate_age($age)) {
        $errors[] = __('invalid_age_range');
    }

    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = __('invalid_gender');
    }

    if (!in_array($preferred_time, ['after_fajr', 'after_dhuhr', 'after_asr', 'after_maghrib', 'after_isha'])) {
        $errors[] = __('invalid_preferred_time');
    }

    if (empty($errors)) {
        $available_circles = get_available_circles($gender, $age, $preferred_time);
        
        if (empty($available_circles)) {
            $errors[] = __('no_circles_match_criteria');
        } else {
            $_SESSION['registration_data'] = [
                'gender' => $gender,
                'age' => $age,
                'preferred_time' => $preferred_time,
                'available_circles' => $available_circles
            ];
            
            header("Location: register.php");
            exit();
        }
    }
}

$pageTitle = __('step1_find_circles');
ob_start();
?>

<div class="register-step1-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="register-box">
                    <div class="text-center mb-4">
                        <h1 class="register-title"><?php echo __('step1_find_circles'); ?></h1>
                        <p class="register-subtitle"><?php echo __('enter_preferences'); ?></p>
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

                    <form method="POST" action="" class="register-form">
                        <div class="form-group mb-4">
                            <label for="age" class="form-label"><?php echo __('age'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                <input type="number" class="form-control" id="age" name="age" 
                                       min="5" max="100" required 
                                       value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>">
                            </div>
                            <small class="text-muted"><?php echo __('age_range_hint'); ?></small>
                        </div>

                        <div class="form-group mb-4">
                            <label class="form-label d-block"><?php echo __('gender'); ?></label>
                            <div class="gender-options">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" 
                                           id="male" value="male" required
                                           <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="male">
                                        <i class="bi bi-gender-male"></i> <?php echo __('male'); ?>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" 
                                           id="female" value="female"
                                           <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="female">
                                        <i class="bi bi-gender-female"></i> <?php echo __('female'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="preferred_time" class="form-label"><?php echo __('preferred_study_time'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                <select class="form-select" id="preferred_time" name="preferred_time" required>
                                    <option value=""><?php echo __('select_preferred_time'); ?></option>
                                    <option value="after_fajr" <?php echo (isset($_POST['preferred_time']) && $_POST['preferred_time'] === 'after_fajr') ? 'selected' : ''; ?>><?php echo __('after_fajr'); ?></option>
                                    <option value="after_dhuhr" <?php echo (isset($_POST['preferred_time']) && $_POST['preferred_time'] === 'after_dhuhr') ? 'selected' : ''; ?>><?php echo __('after_dhuhr'); ?></option>
                                    <option value="after_asr" <?php echo (isset($_POST['preferred_time']) && $_POST['preferred_time'] === 'after_asr') ? 'selected' : ''; ?>><?php echo __('after_asr'); ?></option>
                                    <option value="after_maghrib" <?php echo (isset($_POST['preferred_time']) && $_POST['preferred_time'] === 'after_maghrib') ? 'selected' : ''; ?>><?php echo __('after_maghrib'); ?></option>
                                    <option value="after_isha" <?php echo (isset($_POST['preferred_time']) && $_POST['preferred_time'] === 'after_isha') ? 'selected' : ''; ?>><?php echo __('after_isha'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <?php echo __('find_available_circles'); ?>
                            </button>
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

<style>
.register-step1-page {
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
    margin-bottom: 2rem;
}

.input-group-text {
    background-color: transparent;
    border-right: none;
    color: var(--primary-color);
}

.form-control, .form-select {
    border-left: none;
}

.form-control:focus, .form-select:focus {
    box-shadow: none;
    border-color: #ced4da;
}

.gender-options {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.form-check-inline {
    margin-right: 1.5rem;
}

.form-check-label {
    cursor: pointer;
}

.form-check-label i {
    color: var(--primary-color);
    margin-right: 0.5rem;
}

.btn-primary {
    padding: 0.8rem;
    font-weight: 600;
}

a {
    color: var(--primary-color);
    text-decoration: none;
}

a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

@media (max-width: 768px) {
    .register-box {
        padding: 1.5rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
