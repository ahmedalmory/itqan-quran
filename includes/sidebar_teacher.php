<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="nav flex-column py-3">
    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="/teacher/dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a class="nav-link <?php echo $current_page == 'circles.php' ? 'active' : ''; ?>" href="/teacher/circles.php">
        <i class="bi bi-circle"></i> My Circles
    </a>
    <a class="nav-link <?php echo $current_page == 'students.php' ? 'active' : ''; ?>" href="/teacher/students.php">
        <i class="bi bi-mortarboard"></i> Students
    </a>
    <a class="nav-link <?php echo $current_page == 'daily_reports.php' ? 'active' : ''; ?>" href="/teacher/daily_reports.php">
        <i class="bi bi-journal-text"></i> Daily Reports
    </a>
    <a class="nav-link <?php echo $current_page == 'progress.php' ? 'active' : ''; ?>" href="/teacher/progress.php">
        <i class="bi bi-graph-up"></i> Progress Tracking
    </a>
</nav>
