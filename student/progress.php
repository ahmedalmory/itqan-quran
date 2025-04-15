<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

$user_id = $_SESSION['user_id'];

// Get student's total progress
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_reports,
        SUM(memorization_parts) as total_memorization,
        SUM(revision_parts) as total_revision,
        AVG(grade) as average_grade,
        MIN(report_date) as start_date,
        MAX(report_date) as last_date
    FROM daily_reports
    WHERE student_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progress = $stmt->get_result()->fetch_assoc();

// Get monthly progress for the chart
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(report_date, '%Y-%m') as month,
        SUM(memorization_parts) as memorization,
        SUM(revision_parts) as revision,
        AVG(grade) as grade
    FROM daily_reports
    WHERE student_id = ?
    GROUP BY DATE_FORMAT(report_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_progress = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get last memorized verses
$stmt = $conn->prepare("
    SELECT dr.*, s1.name as from_surah_name, s2.name as to_surah_name
    FROM daily_reports dr
    JOIN surahs s1 ON dr.memorization_from_surah = s1.id
    JOIN surahs s2 ON dr.memorization_to_surah = s2.id
    WHERE dr.student_id = ?
    ORDER BY dr.report_date DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$last_report = $stmt->get_result()->fetch_assoc();

$pageTitle = 'مستوى التقدم';
ob_start();
?>

<div class="row">
    <!-- Overall Progress -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">التقدم الإجمالي</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="border rounded p-3 text-center">
                            <h3 class="mb-0"><?php echo number_format($progress['total_memorization'], 2); ?></h3>
                            <small class="text-muted">إجمالي أجزاء الحفظ</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3 text-center">
                            <h3 class="mb-0"><?php echo number_format($progress['total_revision'], 2); ?></h3>
                            <small class="text-muted">إجمالي أجزاء المراجعة</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3 text-center">
                            <h3 class="mb-0"><?php echo number_format($progress['average_grade'], 1); ?>%</h3>
                            <small class="text-muted">متوسط الدرجات</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3 text-center">
                            <h3 class="mb-0"><?php echo $progress['total_reports']; ?></h3>
                            <small class="text-muted">عدد التقارير</small>
                        </div>
                    </div>
                </div>

                <?php if ($last_report): ?>
                    <div class="mt-4">
                        <h6>آخر ما تم حفظه:</h6>
                        <p class="mb-0">
                            من سورة <?php echo htmlspecialchars($last_report['from_surah_name']); ?> 
                            (آية <?php echo $last_report['memorization_from_verse']; ?>)
                            إلى سورة <?php echo htmlspecialchars($last_report['to_surah_name']); ?>
                            (آية <?php echo $last_report['memorization_to_verse']; ?>)
                        </p>
                        <small class="text-muted">
                            بتاريخ <?php echo date('Y-m-d', strtotime($last_report['report_date'])); ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Monthly Progress Chart -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">التقدم الشهري</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyProgressChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Progress Timeline -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">الجدول الزمني للتقدم</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>الشهر</th>
                        <th>أجزاء الحفظ</th>
                        <th>أجزاء المراجعة</th>
                        <th>متوسط الدرجات</th>
                        <th>المستوى</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_progress as $month): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                            <td><?php echo number_format($month['memorization'], 2); ?></td>
                            <td><?php echo number_format($month['revision'], 2); ?></td>
                            <td><?php echo number_format($month['grade'], 1); ?>%</td>
                            <td>
                                <?php
                                $grade = $month['grade'];
                                if ($grade >= 90) {
                                    echo '<span class="badge bg-success">ممتاز</span>';
                                } elseif ($grade >= 80) {
                                    echo '<span class="badge bg-primary">جيد جداً</span>';
                                } elseif ($grade >= 70) {
                                    echo '<span class="badge bg-warning">جيد</span>';
                                } else {
                                    echo '<span class="badge bg-danger">يحتاج تحسين</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare data for chart
    const months = <?php echo json_encode(array_reverse(array_column($monthly_progress, 'month'))); ?>;
    const memorization = <?php echo json_encode(array_reverse(array_column($monthly_progress, 'memorization'))); ?>;
    const revision = <?php echo json_encode(array_reverse(array_column($monthly_progress, 'revision'))); ?>;
    const grades = <?php echo json_encode(array_reverse(array_column($monthly_progress, 'grade'))); ?>;

    // Create chart
    const ctx = document.getElementById('monthlyProgressChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months.map(m => {
                const date = new Date(m + '-01');
                return date.toLocaleDateString('ar', { month: 'long', year: 'numeric' });
            }),
            datasets: [{
                label: 'الحفظ',
                data: memorization,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }, {
                label: 'المراجعة',
                data: revision,
                borderColor: 'rgb(255, 159, 64)',
                tension: 0.1
            }, {
                label: 'الدرجات',
                data: grades,
                borderColor: 'rgb(153, 102, 255)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
