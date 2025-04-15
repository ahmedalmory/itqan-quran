<?php
require_once __DIR__ . '/language.php';
require_once __DIR__ . '/config.php';

// Get current language direction and code
$dir = get_language_direction();
$current_language = get_current_language();

// Check if language change is requested
if (isset($_GET['lang'])) {
    $new_lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING);
    if (set_language($new_lang)) {
        // Redirect to the same page without the lang parameter
        $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: $redirect_url");
        exit();
    }
}

// Get user role
$user_role = $_SESSION['role'] ?? '';

// Set dashboard link based on user role
switch ($user_role) {
    case 'super_admin':
        $dashboard_link = BASE_URL . '/admin/dashboard.php';
        break;
    case 'department_admin':
        $dashboard_link = BASE_URL . '/department_admin/departments.php';
        break;
    case 'supervisor':
        $dashboard_link = BASE_URL . '/supervisor/';
        break;
    case 'teacher':
        $dashboard_link = BASE_URL . '/teacher/dashboard.php';
        break;
    case 'student':
        $dashboard_link = BASE_URL . '/student/index.php';
        break;
    default:
        $dashboard_link = BASE_URL . '/';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>/"><?php echo __('site_name'); ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/"><?php echo __('home'); ?></a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $dashboard_link; ?>"><?php echo __('dashboard'); ?></a>
                    </li>
                    <?php if ($user_role === 'super_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/users.php"><?php echo __('manage_users'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/departments.php"><?php echo __('manage_departments'); ?></a>
                        </li>
                    <?php elseif ($user_role === 'department_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/department_admin/departments.php"><?php echo __('manage_departments'); ?></a>
                        </li>
                    <?php elseif ($user_role === 'supervisor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/supervisor/circles.php"><?php echo __('view_circles'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/supervisor/reports.php"><?php echo __('view_reports'); ?></a>
                        </li>
                    <?php elseif ($user_role === 'teacher'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/teacher/circle_students.php"><?php echo __('my_students'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/teacher/reports.php"><?php echo __('daily_reports'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/teacher/points.php"><?php echo __('student_points'); ?></a>
                        </li>
                    <?php elseif ($user_role === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/student/reports.php"><?php echo __('my_reports'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/student/progress.php"><?php echo __('my_progress'); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/student/subscriptions.php"><?php echo __('my_subscriptions'); ?></a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <!-- Language Switcher -->
                <li class="nav-item">
                    <?php include __DIR__ . '/language_switcher.php'; ?>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <?php echo htmlspecialchars($_SESSION['name'] ?? __('user')); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/profile.php">
                                    <i class="bi bi-person"></i> <?php echo __('profile'); ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/change_password.php">
                                    <i class="bi bi-key"></i> <?php echo __('change_password'); ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> <?php echo __('logout'); ?>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/login.php"><?php echo __('login'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/register.php"><?php echo __('register'); ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
