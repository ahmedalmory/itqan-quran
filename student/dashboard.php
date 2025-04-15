<?php
// Start output buffering
ob_start();

require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

$user_id = $_SESSION['user_id'];

// Get student's circle information
$stmt = $conn->prepare("
    SELECT c.*, d.name as department_name, 
           t.name as teacher_name, s.name as supervisor_name
    FROM circle_students cs
    JOIN study_circles c ON cs.circle_id = c.id
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN users t ON c.teacher_id = t.id
    LEFT JOIN users s ON c.supervisor_id = s.id
    WHERE cs.student_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$circle = $stmt->get_result()->fetch_assoc();

// Get recent daily reports
$stmt = $conn->prepare("
    SELECT dr.*, s1.name as from_surah, s2.name as to_surah
    FROM daily_reports dr
    JOIN surahs s1 ON dr.memorization_from_surah = s1.id
    JOIN surahs s2 ON dr.memorization_to_surah = s2.id
    WHERE dr.student_id = ?
    ORDER BY dr.report_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate progress statistics
$stmt = $conn->prepare("
    SELECT 
        AVG(grade) as avg_grade,
        SUM(memorization_parts) as total_memorization,
        SUM(revision_parts) as total_revision,
        COUNT(*) as total_reports
    FROM daily_reports
    WHERE student_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$pageTitle = __('student_dashboard');
ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h4 class="mb-3"><?php echo __('welcome_message'); ?> <?php echo getUserName(); ?> <?php echo __('in_your_dashboard'); ?></h4>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title"><?php echo __('evaluation'); ?></h5>
                <h2 class="card-text"><?php echo number_format($stats['avg_grade'], 1); ?>%</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title"><?php echo __('total_memorized'); ?></h5>
                <h2 class="card-text"><?php echo number_format($stats['total_memorization'], 1); ?> <?php echo __('pages'); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title"><?php echo __('total_revised'); ?></h5>
                <h2 class="card-text"><?php echo number_format($stats['total_revision'], 1); ?> <?php echo __('pages'); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title"><?php echo __('total_reports'); ?></h5>
                <h2 class="card-text"><?php echo $stats['total_reports']; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo __('daily_reports'); ?></h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_reports)): ?>
                    <p class="text-center text-muted my-4"><?php echo __('no_reports'); ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php echo __('report_date'); ?></th>
                                    <th><?php echo __('memorization'); ?></th>
                                    <th><?php echo __('revision'); ?></th>
                                    <th><?php echo __('evaluation'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reports as $report): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($report['report_date'])); ?></td>
                                        <td>
                                            <?php echo $report['memorization_parts']; ?> <?php echo __('pages'); ?>
                                            (<?php echo $report['from_surah']; ?> - <?php echo $report['to_surah']; ?>)
                                        </td>
                                        <td><?php echo $report['revision_parts']; ?> <?php echo __('pages'); ?></td>
                                        <td><?php echo $report['grade']; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <a href="reports.php" class="btn btn-primary"><?php echo __('view_all_reports'); ?></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo __('circle_info'); ?></h5>
            </div>
            <div class="card-body">
                <?php if ($circle): ?>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong><?php echo __('circle_name'); ?>:</strong> 
                            <?php echo htmlspecialchars($circle['name']); ?>
                        </li>
                        <li class="list-group-item">
                            <strong><?php echo __('department_name'); ?>:</strong> 
                            <?php echo htmlspecialchars($circle['department_name']); ?>
                        </li>
                        <li class="list-group-item">
                            <strong><?php echo __('teacher_name'); ?>:</strong> 
                            <?php echo htmlspecialchars($circle['teacher_name'] ?? __('not_assigned')); ?>
                        </li>
                        <li class="list-group-item">
                            <strong><?php echo __('supervisor_name'); ?>:</strong> 
                            <?php echo htmlspecialchars($circle['supervisor_name'] ?? __('not_assigned')); ?>
                        </li>
                        <li class="list-group-item">
                            <strong><?php echo __('circle_time'); ?>:</strong> 
                            <?php echo $circle['circle_time'] ? str_replace('_', ' ', ucfirst($circle['circle_time'])) : __('not_assigned'); ?>
                        </li>
                    </ul>
                    <?php if ($circle['whatsapp_group'] || $circle['telegram_group']): ?>
                        <div class="mt-3">
                            <h6><?php echo __('communication_groups'); ?></h6>
                            <?php if ($circle['whatsapp_group']): ?>
                                <a href="<?php echo htmlspecialchars($circle['whatsapp_group']); ?>" class="btn btn-success mt-2 w-100">
                                    <i class="bi bi-whatsapp"></i> <?php echo __('whatsapp_group'); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($circle['telegram_group']): ?>
                                <a href="<?php echo htmlspecialchars($circle['telegram_group']); ?>" class="btn btn-primary mt-2 w-100">
                                    <i class="bi bi-telegram"></i> <?php echo __('telegram_group'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center text-muted my-4">
                        <p><?php echo __('no_circle_assigned'); ?></p>
                        <p><?php echo __('contact_admin'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?php echo __('quick_actions'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <a href="daily_report.php" class="btn btn-primary w-100 mb-2">
                            <i class="bi bi-plus-circle"></i> <?php echo __('add_daily_report'); ?>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="progress.php" class="btn btn-success w-100 mb-2">
                            <i class="bi bi-graph-up"></i> <?php echo __('view_progress'); ?>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="circle_info.php" class="btn btn-info w-100 mb-2">
                            <i class="bi bi-info-circle"></i> <?php echo __('view_circle_details'); ?>
                        </a>
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
