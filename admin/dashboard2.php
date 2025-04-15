<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is admin
requireRole(['super_admin', 'department_admin']);

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get statistics based on user role
if ($user['role'] === 'super_admin') {
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM departments) as total_departments,
            (SELECT COUNT(*) FROM study_circles) as total_circles,
            (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
            (SELECT COUNT(*) FROM users WHERE role IN ('teacher', 'supervisor')) as total_staff
    ");
} else {
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM departments WHERE id IN (
                SELECT department_id FROM department_admins WHERE user_id = ?
            )) as total_departments,
            (SELECT COUNT(*) FROM study_circles WHERE department_id IN (
                SELECT department_id FROM department_admins WHERE user_id = ?
            )) as total_circles,
            (SELECT COUNT(*) FROM users u 
             JOIN circle_students cs ON u.id = cs.student_id 
             JOIN study_circles c ON cs.circle_id = c.id 
             WHERE c.department_id IN (
                SELECT department_id FROM department_admins WHERE user_id = ?
             )) as total_students,
            (SELECT COUNT(*) FROM users 
             WHERE role IN ('teacher', 'supervisor') 
             AND id IN (
                SELECT teacher_id FROM study_circles WHERE department_id IN (
                    SELECT department_id FROM department_admins WHERE user_id = ?
                )
                UNION
                SELECT supervisor_id FROM study_circles WHERE department_id IN (
                    SELECT department_id FROM department_admins WHERE user_id = ?
                )
             )) as total_staff
    ");
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent activities
$stmt = $conn->prepare("
    SELECT 
        dr.id,
        dr.report_date,
        s.name as surah_name,
        u.name as student_name,
        c.name as circle_name,
        dr.memorization_parts as memorized_verses,
        dr.revision_parts as revised_verses
    FROM daily_reports dr
    JOIN users u ON dr.student_id = u.id
    JOIN circle_students cs ON dr.student_id = cs.student_id
    JOIN study_circles c ON cs.circle_id = c.id
    JOIN surahs s ON dr.memorization_from_surah = s.id
    ORDER BY dr.report_date DESC
    LIMIT 5
");
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'لوحة التحكم';
ob_start();
?>

<!-- Welcome Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">مرحباً، <?php echo htmlspecialchars($user['name']); ?></h4>
                        <p class="mb-0 opacity-75">
                            <?php echo $user['role'] === 'super_admin' ? 'مدير النظام' : 'مدير القسم'; ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <p class="mb-0" id="current-time"></p>
                        <p class="mb-0" id="hijri-date"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="stats-icon bg-primary">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">الأقسام</h6>
                        <h2 class="card-title mb-0"><?php echo $stats['total_departments']; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="stats-icon bg-success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">الحلقات</h6>
                        <h2 class="card-title mb-0"><?php echo $stats['total_circles']; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="stats-icon bg-info">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">الطلاب</h6>
                        <h2 class="card-title mb-0"><?php echo $stats['total_students']; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="stats-icon bg-warning">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="card-subtitle text-muted mb-1">الكادر التعليمي</h6>
                        <h2 class="card-title mb-0"><?php echo $stats['total_staff']; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<?php if ($user['role'] === 'super_admin'): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">إجراءات سريعة</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="departments.php" class="text-decoration-none">
                            <div class="quick-action-card">
                                <i class="fas fa-building mb-2"></i>
                                <span>إدارة الأقسام</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="languages.php" class="text-decoration-none">
                            <div class="quick-action-card">
                                <i class="fas fa-language mb-2"></i>
                                <span>إدارة اللغات</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="translations.php" class="text-decoration-none">
                            <div class="quick-action-card">
                                <i class="fas fa-globe mb-2"></i>
                                <span>إدارة التراجم</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="users.php" class="text-decoration-none">
                            <div class="quick-action-card">
                                <i class="fas fa-users-cog mb-2"></i>
                                <span>إدارة المستخدمين</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activities -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">آخر التقارير اليومية</h5>
                <a href="reports.php" class="btn btn-sm btn-primary">عرض الكل</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>التاريخ</th>
                            <th>الطالب</th>
                            <th>الحلقة</th>
                            <th>السورة</th>
                            <th>الحفظ</th>
                            <th>المراجعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activities as $activity): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($activity['report_date'])); ?></td>
                                <td><?php echo htmlspecialchars($activity['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($activity['circle_name']); ?></td>
                                <td><?php echo htmlspecialchars($activity['surah_name']); ?></td>
                                <td><?php echo $activity['memorized_verses']; ?> صفحات</td>
                                <td><?php echo $activity['revised_verses']; ?> صفحات</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.welcome-card {
    background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
    border-radius: 20px;
    padding: 2rem;
    position: relative;
    overflow: hidden;
}

.welcome-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: var(--header-pattern);
    opacity: 0.1;
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.3s ease;
}

.stats-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    font-size: 24px;
    margin-bottom: 1rem;
    background: var(--primary-light);
    color: var(--primary-color);
}

.stats-number {
    font-size: 2rem;
    font-weight: 600;
    color: var(--primary-dark);
    margin-bottom: 0.5rem;
}

.stats-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 2rem;
}

.quick-action-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.quick-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(31, 169, 89, 0.15);
}

.quick-action-icon {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.quick-action-card i {
    font-size: 24px;
    display: block;
    color: #0d6efd;
}

@media (max-width: 768px) {
    .stats-icon {
        width: 36px;
        height: 36px;
        font-size: 18px;
    }
    
    .card-title {
        font-size: 1.25rem;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .quick-action-card {
        padding: 15px;
    }
    
    .quick-action-card i {
        font-size: 20px;
    }
    
    .quick-action-card span {
        font-size: 0.875rem;
    }
}
</style>

<script>
// Update current time and Hijri date
function updateDateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('ar-SA');
    const dateString = now.toLocaleDateString('ar-SA-u-ca-islamic', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
    
    document.getElementById('current-time').textContent = timeString;
    document.getElementById('hijri-date').textContent = dateString;
}

updateDateTime();
setInterval(updateDateTime, 1000);
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
