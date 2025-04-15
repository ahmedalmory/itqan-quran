<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="nav flex-column py-3">
    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="/supervisor/dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a class="nav-link <?php echo $current_page == 'circles.php' ? 'active' : ''; ?>" href="/supervisor/circles.php">
        <i class="bi bi-circle"></i> Supervised Circles
    </a>
    <a class="nav-link <?php echo $current_page == 'teachers.php' ? 'active' : ''; ?>" href="/supervisor/teachers.php">
        <i class="bi bi-person-video3"></i> Teachers
    </a>
    <a class="nav-link <?php echo $current_page == 'students.php' ? 'active' : ''; ?>" href="/supervisor/students.php">
        <i class="bi bi-mortarboard"></i> Students
    </a>
    <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="/supervisor/reports.php">
        <i class="bi bi-file-text"></i> Reports
    </a>
</nav>
