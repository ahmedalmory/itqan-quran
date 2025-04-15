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
            (SELECT COUNT(*) FROM users WHERE role IN ('teacher', 'supervisor')) as total_staff,
            (SELECT COUNT(*) FROM daily_reports WHERE report_date = CURDATE()) as today_reports,
            (SELECT COUNT(*) FROM daily_reports WHERE grade >= 90) as excellent_students,
            (SELECT SUM(memorization_parts) FROM daily_reports) as total_memorization_parts,
            (SELECT COUNT(DISTINCT student_id) FROM daily_reports WHERE memorization_parts >= 30) as completed_quran
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
             )) as total_staff,
            (SELECT COUNT(*) FROM daily_reports dr
             JOIN circle_students cs ON dr.student_id = cs.student_id
             JOIN study_circles c ON cs.circle_id = c.id
             WHERE c.department_id IN (
                SELECT department_id FROM department_admins WHERE user_id = ?
             ) AND dr.report_date = CURDATE()) as today_reports,
            (SELECT COUNT(*) FROM daily_reports dr
             JOIN circle_students cs ON dr.student_id = cs.student_id
             JOIN study_circles c ON cs.circle_id = c.id
             WHERE c.department_id IN (
                SELECT department_id FROM department_admins WHERE user_id = ?
             ) AND dr.grade >= 90) as excellent_students,
            (SELECT SUM(dr.memorization_parts) FROM daily_reports dr
             JOIN circle_students cs ON dr.student_id = cs.student_id
             JOIN study_circles c ON cs.circle_id = c.id
             WHERE c.department_id IN (
                SELECT department_id FROM department_admins WHERE user_id = ?
             )) as total_memorization_parts,
            (SELECT COUNT(DISTINCT dr.student_id) FROM daily_reports dr
             JOIN circle_students cs ON dr.student_id = cs.student_id
             JOIN study_circles c ON cs.circle_id = c.id
             WHERE c.department_id IN (
                SELECT department_id FROM department_admins WHERE user_id = ?
             ) AND dr.memorization_parts >= 30) as completed_quran
    ");
    $stmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get data for charts
$chartData = [];

// Student Progress Data (Monthly)
$progressQuery = $user['role'] === 'super_admin' 
    ? "SELECT DATE_FORMAT(report_date, '%Y-%m') as month,
             SUM(memorization_parts) as memorization,
             SUM(revision_parts) as revision
      FROM daily_reports
      WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      GROUP BY month
      ORDER BY month ASC"
    : "SELECT DATE_FORMAT(dr.report_date, '%Y-%m') as month,
             SUM(dr.memorization_parts) as memorization,
             SUM(dr.revision_parts) as revision
      FROM daily_reports dr
      JOIN circle_students cs ON dr.student_id = cs.student_id
      JOIN study_circles c ON cs.circle_id = c.id
      WHERE c.department_id IN (
          SELECT department_id FROM department_admins WHERE user_id = ?
      )
      AND dr.report_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      GROUP BY month
      ORDER BY month ASC";

$stmt = $conn->prepare($progressQuery);
if ($user['role'] !== 'super_admin') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$progressResult = $stmt->get_result();
$chartData['progress'] = [];
while ($row = $progressResult->fetch_assoc()) {
    $chartData['progress'][] = $row;
}

// Performance Distribution Data
$performanceQuery = $user['role'] === 'super_admin'
    ? "SELECT 
         COUNT(CASE WHEN grade >= 90 THEN 1 END) as excellent,
         COUNT(CASE WHEN grade >= 80 AND grade < 90 THEN 1 END) as very_good,
         COUNT(CASE WHEN grade >= 70 AND grade < 80 THEN 1 END) as good,
         COUNT(CASE WHEN grade < 70 THEN 1 END) as fair
       FROM daily_reports
       WHERE report_date = CURDATE()"
    : "SELECT 
         COUNT(CASE WHEN dr.grade >= 90 THEN 1 END) as excellent,
         COUNT(CASE WHEN dr.grade >= 80 AND dr.grade < 90 THEN 1 END) as very_good,
         COUNT(CASE WHEN dr.grade >= 70 AND dr.grade < 80 THEN 1 END) as good,
         COUNT(CASE WHEN dr.grade < 70 THEN 1 END) as fair
       FROM daily_reports dr
       JOIN circle_students cs ON dr.student_id = cs.student_id
       JOIN study_circles c ON cs.circle_id = c.id
       WHERE c.department_id IN (
           SELECT department_id FROM department_admins WHERE user_id = ?
       )
       AND dr.report_date = CURDATE()";

$stmt = $conn->prepare($performanceQuery);
if ($user['role'] !== 'super_admin') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$chartData['performance'] = $stmt->get_result()->fetch_assoc();

// Department Statistics
$departmentQuery = $user['role'] === 'super_admin'
    ? "SELECT 
         d.name as department_name,
         COUNT(DISTINCT cs.student_id) as student_count,
         COUNT(DISTINCT c.id) as circle_count
       FROM departments d
       LEFT JOIN study_circles c ON d.id = c.department_id
       LEFT JOIN circle_students cs ON c.id = cs.circle_id
       GROUP BY d.id"
    : "SELECT 
         d.name as department_name,
         COUNT(DISTINCT cs.student_id) as student_count,
         COUNT(DISTINCT c.id) as circle_count
       FROM departments d
       LEFT JOIN study_circles c ON d.id = c.department_id
       LEFT JOIN circle_students cs ON c.id = cs.circle_id
       WHERE d.id IN (
           SELECT department_id FROM department_admins WHERE user_id = ?
       )
       GROUP BY d.id";

$stmt = $conn->prepare($departmentQuery);
if ($user['role'] !== 'super_admin') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$chartData['departments'] = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $chartData['departments'][] = $row;
}

// Daily Activity Data
$activityQuery = $user['role'] === 'super_admin'
    ? "SELECT 
         HOUR(created_at) as hour,
         COUNT(*) as report_count
       FROM daily_reports
       WHERE DATE(created_at) = CURDATE()
       GROUP BY HOUR(created_at)
       ORDER BY hour"
    : "SELECT 
         HOUR(dr.created_at) as hour,
         COUNT(*) as report_count
       FROM daily_reports dr
       JOIN circle_students cs ON dr.student_id = cs.student_id
       JOIN study_circles c ON cs.circle_id = c.id
       WHERE c.department_id IN (
           SELECT department_id FROM department_admins WHERE user_id = ?
       )
       AND DATE(dr.created_at) = CURDATE()
       GROUP BY HOUR(dr.created_at)
       ORDER BY hour";

$stmt = $conn->prepare($activityQuery);
if ($user['role'] !== 'super_admin') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$chartData['activity'] = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $chartData['activity'][] = $row;
}

$pageTitle = 'لوحة التحكم';
ob_start();
?>

<!-- Main Dashboard Container -->
<div class="dashboard-container">
    <!-- Welcome Section with Hijri Date -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card islamic-pattern-bg text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-2 arabic-font fw-bold">مرحباً، <?php echo htmlspecialchars($user['name']); ?></h3>
                            <p class="mb-0 opacity-75">
                                <?php echo $user['role'] === 'super_admin' ? 'مدير النظام' : 'مدير القسم'; ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <p class="mb-0 hijri-font fs-5" id="current-time"></p>
                            <p class="mb-0 hijri-font fs-5" id="hijri-date"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-hover islamic-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon islamic-green-bg">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-subtitle mb-1">الأقسام</h6>
                            <h2 class="card-title mb-0 arabic-font"><?php echo $stats['total_departments']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-hover islamic-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon islamic-green-dark-bg">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-subtitle mb-1">الحلقات</h6>
                            <h2 class="card-title mb-0 arabic-font"><?php echo $stats['total_circles']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-hover islamic-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon islamic-green-light-bg">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-subtitle mb-1">الطلاب</h6>
                            <h2 class="card-title mb-0 arabic-font"><?php echo $stats['total_students']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-hover islamic-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon islamic-accent-bg">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-subtitle mb-1">الكادر التعليمي</h6>
                            <h2 class="card-title mb-0 arabic-font"><?php echo $stats['total_staff']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Statistics Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-hover islamic-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon islamic-blue-bg">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-subtitle mb-1">تقارير اليوم</h6>
                            <h2 class="card-title mb-0 arabic-font"><?php echo $stats['today_reports']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-hover islamic-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon islamic-purple-bg">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-subtitle mb-1">الطلاب المتميزون</h6>
                            <h2 class="card-title mb-0 arabic-font"><?php echo $stats['excellent_students']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-hover islamic-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon islamic-teal-bg">
                                <i class="fas fa-book-open"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-subtitle mb-1">إجمالي الأجزاء المحفوظة</h6>
                            <h2 class="card-title mb-0 arabic-font"><?php echo number_format($stats['total_memorization_parts']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-hover islamic-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="stats-icon islamic-gold-bg">
                                <i class="fas fa-award"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="card-subtitle mb-1">حافظين القرآن كاملاً</h6>
                            <h2 class="card-title mb-0 arabic-font"><?php echo $stats['completed_quran']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Charts Section -->
    <div class="row g-3 mb-4">
        <!-- Student Progress Chart -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-hover islamic-card">
                <div class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 arabic-font">تقدم الطلاب في الحفظ</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="updateStudentProgressChart('week')">أسبوعي</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm active" onclick="updateStudentProgressChart('month')">شهري</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="updateStudentProgressChart('year')">سنوي</button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="studentProgressChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Performance Distribution -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-hover islamic-card">
                <div class="card-header bg-white border-bottom-0">
                    <h5 class="card-title mb-0 arabic-font">توزيع مستويات الأداء</h5>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Department Statistics -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-hover islamic-card">
                <div class="card-header bg-white border-bottom-0">
                    <h5 class="card-title mb-0 arabic-font">إحصائيات الأقسام</h5>
                </div>
                <div class="card-body">
                    <canvas id="departmentChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Daily Activity Timeline -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-hover islamic-card">
                <div class="card-header bg-white border-bottom-0">
                    <h5 class="card-title mb-0 arabic-font">نشاط اليوم</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyActivityChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <?php if ($user['role'] === 'super_admin'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm islamic-card">
                <div class="card-header bg-white border-bottom-0">
                    <h5 class="card-title mb-0 arabic-font">إجراءات سريعة</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <a href="departments.php" class="text-decoration-none">
                                <div class="quick-action-card islamic-hover">
                                    <div class="icon-wrapper islamic-green-bg">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <span class="action-text">إدارة الأقسام</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="languages.php" class="text-decoration-none">
                                <div class="quick-action-card islamic-hover">
                                    <div class="icon-wrapper islamic-green-dark-bg">
                                        <i class="fas fa-language"></i>
                                    </div>
                                    <span class="action-text">إدارة اللغات</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="users.php" class="text-decoration-none">
                                <div class="quick-action-card islamic-hover">
                                    <div class="icon-wrapper islamic-green-light-bg">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <span class="action-text">إدارة المستخدمين</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="reports.php" class="text-decoration-none">
                                <div class="quick-action-card islamic-hover">
                                    <div class="icon-wrapper islamic-accent-bg">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <span class="action-text">التقارير الإحصائية</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="import_itqan.php" class="text-decoration-none">
                                <div class="quick-action-card islamic-hover">
                                    <div class="icon-wrapper islamic-green-bg">
                                        <i class="fas fa-file-import"></i>
                                    </div>
                                    <span class="action-text">استيراد طلاب إتقان</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    /* Islamic Theme Styles */
    :root {
        --islamic-green: #1FA363;
        --islamic-green-dark: #167A49;
        --islamic-green-light: #28CC7C;
        --islamic-accent: #FFB300;
        --islamic-blue: #2196F3;
        --islamic-purple: #9C27B0;
        --islamic-teal: #009688;
        --islamic-gold: #FFC107;
        --islamic-pattern: url('data:image/svg+xml,<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><path d="M20 0L40 20L20 40L0 20L20 0z" fill="%23167A49" opacity="0.1"/></svg>');
    }

    .dashboard-container {
        padding: 1.5rem 0;
    }

    .arabic-font {
        font-family: 'Amiri', 'Traditional Arabic', serif;
    }

    .hijri-font {
        font-family: 'Lateef', 'Traditional Arabic', serif;
    }

    .islamic-pattern-bg {
        background-color: var(--islamic-green);
        background-image: var(--islamic-pattern);
        background-size: 40px 40px;
    }

    .islamic-card {
        border-radius: 0.5rem;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .islamic-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }

    .stats-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .islamic-green-bg { background-color: var(--islamic-green); }
    .islamic-green-dark-bg { background-color: var(--islamic-green-dark); }
    .islamic-green-light-bg { background-color: var(--islamic-green-light); }
    .islamic-accent-bg { background-color: var(--islamic-accent); }
    .islamic-blue-bg { background-color: var(--islamic-blue); }
    .islamic-purple-bg { background-color: var(--islamic-purple); }
    .islamic-teal-bg { background-color: var(--islamic-teal); }
    .islamic-gold-bg { background-color: var(--islamic-gold); }

    .quick-action-card {
        padding: 1.5rem;
        border-radius: 12px;
        text-align: center;
        background: white;
        transition: all 0.3s ease;
        border: 1px solid #e0e0e0;
    }

    .quick-action-card .icon-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .quick-action-card .action-text {
        color: #333;
        font-weight: 500;
    }

    .islamic-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    @media (max-width: 768px) {
        .card-body {
            padding: 1rem;
        }

        .quick-action-card {
            padding: 1rem;
        }

        .quick-action-card .icon-wrapper {
            width: 45px;
            height: 45px;
            font-size: 1.2rem;
        }
    }

    /* Additional Chart Styles */
    .btn-group .btn-outline-secondary {
        color: var(--islamic-green);
        border-color: var(--islamic-green);
    }

    .btn-group .btn-outline-secondary.active,
    .btn-group .btn-outline-secondary:hover {
        background-color: var(--islamic-green);
        border-color: var(--islamic-green);
        color: white;
    }

    .chart-legend {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 1rem;
    }

    .chart-legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-legend-color {
        width: 12px;
        height: 12px;
        border-radius: 3px;
    }
</style>

<!-- Add required fonts -->
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Lateef&display=swap" rel="stylesheet">

<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

// Chart Configuration
Chart.defaults.font.family = "'Amiri', 'Traditional Arabic', serif";
Chart.defaults.font.size = 14;
Chart.defaults.color = '#333';

// Initialize PHP data for charts
const chartData = <?php echo json_encode($chartData); ?>;

// Student Progress Chart
const studentProgressCtx = document.getElementById('studentProgressChart').getContext('2d');
const studentProgressChart = new Chart(studentProgressCtx, {
    type: 'line',
    data: {
        labels: chartData.progress.map(item => {
            const [year, month] = item.month.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('ar-SA', { month: 'long' });
        }),
        datasets: [{
            label: 'الحفظ',
            data: chartData.progress.map(item => item.memorization),
            borderColor: '#1FA363',
            backgroundColor: 'rgba(31, 163, 99, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'المراجعة',
            data: chartData.progress.map(item => item.revision),
            borderColor: '#FFB300',
            backgroundColor: 'rgba(255, 179, 0, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                align: 'end'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'عدد الأجزاء'
                }
            }
        }
    }
});

// Performance Distribution Chart
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(performanceCtx, {
    type: 'doughnut',
    data: {
        labels: ['ممتاز', 'جيد جداً', 'جيد', 'مقبول'],
        datasets: [{
            data: [
                chartData.performance.excellent,
                chartData.performance.very_good,
                chartData.performance.good,
                chartData.performance.fair
            ],
            backgroundColor: [
                '#1FA363',
                '#2196F3',
                '#FFB300',
                '#9C27B0'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Department Statistics Chart
const departmentCtx = document.getElementById('departmentChart').getContext('2d');
const departmentChart = new Chart(departmentCtx, {
    type: 'bar',
    data: {
        labels: chartData.departments.map(dept => dept.department_name),
        datasets: [{
            label: 'عدد الطلاب',
            data: chartData.departments.map(dept => dept.student_count),
            backgroundColor: '#1FA363'
        }, {
            label: 'عدد الحلقات',
            data: chartData.departments.map(dept => dept.circle_count),
            backgroundColor: '#FFB300'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Daily Activity Chart
const dailyActivityCtx = document.getElementById('dailyActivityChart').getContext('2d');
const dailyActivityChart = new Chart(dailyActivityCtx, {
    type: 'line',
    data: {
        labels: chartData.activity.map(item => `${item.hour}:00`),
        datasets: [{
            label: 'عدد التقارير',
            data: chartData.activity.map(item => item.report_count),
            borderColor: '#2196F3',
            backgroundColor: 'rgba(33, 150, 243, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'عدد التقارير'
                }
            }
        }
    }
});

// Function to update student progress chart with real data from server
async function updateStudentProgressChart(period) {
    try {
        const response = await fetch(`get_progress_data.php?period=${period}`);
        const data = await response.json();
        
        studentProgressChart.data.labels = data.labels;
        studentProgressChart.data.datasets[0].data = data.memorization;
        studentProgressChart.data.datasets[1].data = data.revision;
        studentProgressChart.update();

        // Update active button
        document.querySelectorAll('.btn-group .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
    } catch (error) {
        console.error('Error fetching progress data:', error);
    }
}
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
