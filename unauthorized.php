<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

$pageTitle = 'غير مصرح';
ob_start();
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h1 class="display-1 text-danger">403</h1>
                    <h2 class="card-title mb-4">غير مصرح بالوصول</h2>
                    <p class="card-text mb-4">عذراً، لا تملك الصلاحيات الكافية للوصول إلى هذه الصفحة.</p>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="javascript:history.back()" class="btn btn-primary me-2">العودة للصفحة السابقة</a>
                        <?php if ($_SESSION['role'] === 'student'): ?>
                            <a href="student/dashboard.php" class="btn btn-secondary">الذهاب للوحة التحكم</a>
                        <?php elseif (in_array($_SESSION['role'], ['super_admin', 'department_admin'])): ?>
                            <a href="admin/dashboard.php" class="btn btn-secondary">الذهاب للوحة التحكم</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="index.php" class="btn btn-primary">تسجيل الدخول</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
