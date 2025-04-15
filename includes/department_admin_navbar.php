<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/AlQuran/department_admin/">نظام إدارة حلقات القرآن</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/AlQuran/department_admin/">
                        <i class="bi bi-speedometer2"></i>
                        لوحة التحكم
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/AlQuran/department_admin/departments.php">
                        <i class="bi bi-book"></i>
                        الأقسام
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/AlQuran/department_admin/students.php">
                        <i class="bi bi-people"></i>
                        الطلاب
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/AlQuran/department_admin/reports.php">
                        <i class="bi bi-file-text"></i>
                        التقارير
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="/AlQuran/profile.php">
                                <i class="bi bi-person"></i>
                                الملف الشخصي
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="/AlQuran/logout.php">
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
