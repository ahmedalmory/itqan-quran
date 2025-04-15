<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="nav flex-column py-3">
    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="/department/dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a class="nav-link <?php echo $current_page == 'circles.php' ? 'active' : ''; ?>" href="/department/circles.php">
        <i class="bi bi-circle"></i> Study Circles
    </a>
    <a class="nav-link <?php echo $current_page == 'teachers.php' ? 'active' : ''; ?>" href="/department/teachers.php">
        <i class="bi bi-person-video3"></i> Teachers
    </a>
    <a class="nav-link <?php echo $current_page == 'supervisors.php' ? 'active' : ''; ?>" href="/department/supervisors.php">
        <i class="bi bi-person-check"></i> Supervisors
    </a>
    <a class="nav-link <?php echo $current_page == 'students.php' ? 'active' : ''; ?>" href="/department/students.php">
        <i class="bi bi-mortarboard"></i> Students
    </a>
    <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="/department/reports.php">
        <i class="bi bi-file-text"></i> Reports
    </a>
</nav>
