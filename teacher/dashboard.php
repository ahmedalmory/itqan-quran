<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

$user_id = $_SESSION['user_id'];

// Get teacher's circles
$stmt = $conn->prepare("
    SELECT c.*, d.name as department_name,
           (SELECT COUNT(*) FROM circle_students WHERE circle_id = c.id) as student_count
    FROM study_circles c
    JOIN departments d ON c.department_id = d.id
    WHERE c.teacher_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$circles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get today's attendance and reports count
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT 
        c.id as circle_id,
        c.name as circle_name,
        COUNT(DISTINCT cs.student_id) as total_students,
        COUNT(DISTINCT dr.student_id) as reports_submitted
    FROM study_circles c
    JOIN circle_students cs ON c.id = cs.circle_id
    LEFT JOIN daily_reports dr ON cs.student_id = dr.student_id 
        AND dr.report_date = ?
    WHERE c.teacher_id = ?
    GROUP BY c.id
");
$stmt->bind_param("si", $today, $user_id);
$stmt->execute();
$attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent daily reports
$stmt = $conn->prepare("
    SELECT dr.*, u.name as student_name, 
           s1.name as from_surah, s2.name as to_surah,
           c.name as circle_name
    FROM daily_reports dr
    JOIN users u ON dr.student_id = u.id
    JOIN surahs s1 ON dr.memorization_from_surah = s1.id
    JOIN surahs s2 ON dr.memorization_to_surah = s2.id
    JOIN circle_students cs ON dr.student_id = cs.student_id
    JOIN study_circles c ON cs.circle_id = c.id
    WHERE c.teacher_id = ?
    ORDER BY dr.report_date DESC, dr.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate overall statistics
$total_students = 0;
$total_attendance = 0;
foreach ($attendance as $circle) {
    $total_students += $circle['total_students'];
    $total_attendance += $circle['reports_submitted'];
}

$pageTitle = __('teacher_dashboard');
$pageHeader = __('teacher_dashboard');
ob_start();
?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title"><?php echo __('my_circles'); ?></h5>
                <h2 class="card-text"><?php echo count($circles); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title"><?php echo __('total_students'); ?></h5>
                <h2 class="card-text"><?php echo $total_students; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title"><?php echo __('todays_reports'); ?></h5>
                <h2 class="card-text"><?php echo $total_attendance; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title"><?php echo __('attendance_rate'); ?></h5>
                <h2 class="card-text">
                    <?php echo $total_students ? round(($total_attendance / $total_students) * 100) : 0; ?>%
                </h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><?php echo __('todays_attendance'); ?></h5>
                <a href="daily_reports.php" class="btn btn-sm btn-primary"><?php echo __('add_reports'); ?></a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __('circle'); ?></th>
                                <th><?php echo __('total_students'); ?></th>
                                <th><?php echo __('reports_submitted'); ?></th>
                                <th><?php echo __('progress'); ?></th>
                                <th><?php echo __('action'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $circle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($circle['circle_name']); ?></td>
                                    <td><?php echo $circle['total_students']; ?></td>
                                    <td><?php echo $circle['reports_submitted']; ?></td>
                                    <td>
                                        <div class="progress">
                                            <?php 
                                            $percentage = $circle['total_students'] ? 
                                                round(($circle['reports_submitted'] / $circle['total_students']) * 100) : 0;
                                            ?>
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%"
                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="daily_reports.php?circle_id=<?php echo $circle['circle_id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <?php echo __('add_reports'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo __('recent_reports'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('student'); ?></th>
                                <th><?php echo __('circle'); ?></th>
                                <th><?php echo __('memorization'); ?></th>
                                <th><?php echo __('grade'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reports as $report): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($report['report_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($report['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($report['circle_name']); ?></td>
                                    <td>
                                        <?php echo $report['memorization_parts']; ?> <?php echo __('pages'); ?>
                                        (<?php echo $report['from_surah']; ?> - <?php echo $report['to_surah']; ?>)
                                    </td>
                                    <td><?php echo $report['grade']; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo __('my_circles'); ?></h5>
            </div>
            <div class="card-body">
                <?php foreach ($circles as $circle): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($circle['name']); ?></h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    <?php echo __('department'); ?>: <?php echo htmlspecialchars($circle['department_name']); ?>
                                </small>
                            </p>
                            <p class="mb-0">
                                <span class="badge bg-primary">
                                    <?php echo str_replace('_', ' ', ucfirst($circle['circle_time'])); ?>
                                </span>
                                <span class="badge bg-info">
                                    <?php echo $circle['student_count']; ?>/<?php echo $circle['max_students']; ?> <?php echo __('students'); ?>
                                </span>
                            </p>
                            <div class="mt-2">
                                <?php if ($circle['whatsapp_group']): ?>
                                    <a href="<?php echo htmlspecialchars($circle['whatsapp_group']); ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($circle['telegram_group']): ?>
                                    <a href="<?php echo htmlspecialchars($circle['telegram_group']); ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-telegram"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="students.php?circle_id=<?php echo $circle['id']; ?>" 
                                   class="btn btn-sm btn-info">
                                    <i class="bi bi-people"></i> <?php echo __('students'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo __('quick_actions'); ?></h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="daily_reports.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> <?php echo __('add_daily_reports'); ?>
                    </a>
                    <a href="progress.php" class="btn btn-success">
                        <i class="bi bi-graph-up"></i> <?php echo __('view_progress_reports'); ?>
                    </a>
                    <a href="students.php" class="btn btn-info">
                        <i class="bi bi-people"></i> <?php echo __('manage_students'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
