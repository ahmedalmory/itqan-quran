<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

$user_id = $_SESSION['user_id'];

// Get date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get reports for the date range
$stmt = $conn->prepare("
    SELECT dr.*, s1.name as from_surah_name, s2.name as to_surah_name
    FROM daily_reports dr
    JOIN surahs s1 ON dr.memorization_from_surah = s1.id
    JOIN surahs s2 ON dr.memorization_to_surah = s2.id
    WHERE dr.student_id = ? AND dr.report_date BETWEEN ? AND ?
    ORDER BY dr.report_date DESC
");
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_memorization = 0;
$total_revision = 0;
$total_grade = 0;
$report_count = count($reports);

foreach ($reports as $report) {
    $total_memorization += $report['memorization_parts'];
    $total_revision += $report['revision_parts'];
    $total_grade += $report['grade'];
}

$avg_memorization = $report_count ? round($total_memorization / $report_count, 2) : 0;
$avg_revision = $report_count ? round($total_revision / $report_count, 2) : 0;
$avg_grade = $report_count ? round($total_grade / $report_count, 2) : 0;

$pageTitle = 'تقاريري السابقة';
ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">عرض التقارير</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">عدد التقارير</h5>
                <p class="display-4"><?php echo $report_count; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">متوسط الحفظ</h5>
                <p class="display-4"><?php echo $avg_memorization; ?></p>
                <p class="text-muted">جزء / يوم</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">متوسط المراجعة</h5>
                <p class="display-4"><?php echo $avg_revision; ?></p>
                <p class="text-muted">جزء / يوم</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">متوسط الدرجات</h5>
                <p class="display-4"><?php echo $avg_grade; ?>%</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">التقارير السابقة</h5>
    </div>
    <div class="card-body">
        <?php if (empty($reports)): ?>
            <p class="text-muted text-center">لا توجد تقارير في الفترة المحددة</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الحفظ من</th>
                            <th>الحفظ إلى</th>
                            <th>أجزاء الحفظ</th>
                            <th>أجزاء المراجعة</th>
                            <th>الدرجة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($report['report_date'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($report['from_surah_name']); ?>
                                    (<?php echo $report['memorization_from_verse']; ?>)
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($report['to_surah_name']); ?>
                                    (<?php echo $report['memorization_to_verse']; ?>)
                                </td>
                                <td><?php echo $report['memorization_parts']; ?></td>
                                <td><?php echo $report['revision_parts']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $report['grade'] >= 90 ? 'success' : ($report['grade'] >= 70 ? 'warning' : 'danger'); ?>">
                                        <?php echo $report['grade']; ?>%
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($report['notes'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
