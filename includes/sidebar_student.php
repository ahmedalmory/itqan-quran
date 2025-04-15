<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="nav flex-column py-3">
    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="/student/dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a class="nav-link <?php echo $current_page == 'daily_report.php' ? 'active' : ''; ?>" href="/student/daily_report.php">
        <i class="bi bi-journal-text"></i> Daily Report
    </a>
    <a class="nav-link <?php echo $current_page == 'progress.php' ? 'active' : ''; ?>" href="/student/progress.php">
        <i class="bi bi-graph-up"></i> My Progress
    </a>
    <a class="nav-link <?php echo $current_page == 'circle_info.php' ? 'active' : ''; ?>" href="/student/circle_info.php">
        <i class="bi bi-info-circle"></i> Circle Information
    </a>
    <a class="nav-link <?php echo $current_page == 'subscriptions.php' ? 'active' : ''; ?>" href="/student/subscriptions.php">
        <i class="bi bi-credit-card"></i> My Subscriptions
    </a>
</nav>
