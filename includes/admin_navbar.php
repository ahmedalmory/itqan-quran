<?php
if (!isset($_SESSION)) {
    session_start();
}

// التحقق من تسجيل الدخول وصلاحية المستخدم
if (!isset($_SESSION['user_id']) || (!hasRole('admin') && !hasRole('super_admin'))) {
    header('Location: ../login.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">لوحة التحكم</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" 
                       href="index.php">الرئيسية</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'departments.php' ? 'active' : ''; ?>" 
                       href="departments.php">الأقسام</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" 
                       href="users.php">المستخدمون</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" 
                       href="reports.php">التقارير</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" 
                       href="settings.php">الإعدادات</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['name'] ?? 'المستخدم'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person-lines-fill"></i>
                                الملف الشخصي
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                تسجيل الخروج
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
