<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="nav flex-column py-3">
    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="/admin/dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a class="nav-link <?php echo $current_page == 'departments.php' ? 'active' : ''; ?>" href="/admin/departments.php">
        <i class="bi bi-building"></i> Departments
    </a>
    <a class="nav-link <?php echo $current_page == 'circles.php' ? 'active' : ''; ?>" href="/admin/circles.php">
        <i class="bi bi-circle"></i> Study Circles
    </a>
    <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>" href="/admin/users.php">
        <i class="bi bi-people"></i> Users
    </a>
    <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="/admin/reports.php">
        <i class="bi bi-file-text"></i> Reports
    </a>
    <a class="nav-link <?php echo $current_page == 'subscription_plans.php' ? 'active' : ''; ?>" href="/admin/subscription_plans.php">
        <i class="bi bi-credit-card"></i> Subscription Plans
    </a>
    <a class="nav-link <?php echo $current_page == 'student_subscriptions.php' ? 'active' : ''; ?>" href="/admin/student_subscriptions.php">
        <i class="bi bi-receipt"></i> Student Subscriptions
    </a>
    <a class="nav-link <?php echo $current_page == 'languages.php' ? 'active' : ''; ?>" href="/admin/languages.php">
        <i class="bi bi-translate"></i> Languages
    </a>
    <a class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" href="/admin/settings.php">
        <i class="bi bi-gear"></i> Settings
    </a>
</nav>
