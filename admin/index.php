<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is admin
requireRole(['super_admin', 'department_admin']);

$pageTitle = 'لوحة الإدارة';
ob_start();
?>

<div class="container py-4">
    <h1 class="mb-4">لوحة الإدارة</h1>
    
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="list-group">
                <a href="dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt me-2"></i> لوحة التحكم
                </a>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="departments.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-building me-2"></i> إدارة الأقسام
                </a>
                <a href="users.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-users me-2"></i> إدارة المستخدمين
                </a>
                <a href="languages.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-language me-2"></i> إدارة اللغات
                </a>
                <a href="payment_settings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-money-bill-wave me-2"></i> إعدادات الدفع
                </a>
                <?php endif; ?>
                <a href="study_circles.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-users-cog me-2"></i> إدارة الحلقات
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-chart-bar me-2"></i> التقارير
                </a>
                <a href="subscription_plans.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tags me-2"></i> خطط الاشتراك
                </a>
                <a href="student_subscriptions.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user-tag me-2"></i> اشتراكات الطلاب
                </a>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="import_itqan.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-file-import me-2"></i> استيراد طلاب إتقان
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">مرحباً بك في لوحة الإدارة</h5>
                </div>
                <div class="card-body">
                    <p>اختر أحد الخيارات من القائمة الجانبية للبدء في إدارة النظام.</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">إدارة الطلاب</h5>
                                    <p class="card-text">إضافة وتعديل وحذف الطلاب وإدارة بياناتهم</p>
                                    <a href="users.php?role=student" class="btn btn-primary">إدارة الطلاب</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="fas fa-chalkboard-teacher fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">إدارة المعلمين</h5>
                                    <p class="card-text">إضافة وتعديل وحذف المعلمين وإدارة بياناتهم</p>
                                    <a href="users.php?role=teacher" class="btn btn-success">إدارة المعلمين</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="fas fa-users-cog fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">إدارة الحلقات</h5>
                                    <p class="card-text">إنشاء وتعديل وحذف الحلقات وإدارة الطلاب فيها</p>
                                    <a href="study_circles.php" class="btn btn-info">إدارة الحلقات</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 