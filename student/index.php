<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Check if student is suspended and get teacher info
$stmt = $pdo->prepare("
    SELECT 
        u.is_active,
        t.name as teacher_name,
        t.phone as teacher_phone
    FROM users u
    LEFT JOIN circle_students cs ON cs.student_id = u.id
    LEFT JOIN study_circles c ON cs.circle_id = c.id
    LEFT JOIN users t ON c.teacher_id = t.id
    WHERE u.id = ?
    ORDER BY cs.created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if ($user_data['is_active'] != 1): ?>
    <div class="container mt-4">
        <div class="alert alert-warning text-center p-5" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-1 mb-3 d-block"></i>
            <h4 class="alert-heading mb-3"><?php echo __('account_suspended'); ?></h4>
            <p class="mb-3"><?php echo __('suspension_message'); ?></p>
            <hr>
            <p class="mb-0">
                <?php echo __('contact_teacher_message'); ?>
                <?php if (!empty($user_data['teacher_name'])): ?>
                    <br>
                    <strong><?php echo __('your_teacher'); ?>:</strong> 
                    <?php echo htmlspecialchars($user_data['teacher_name']); ?>
                    <?php if (!empty($user_data['teacher_phone'])): ?>
                        <br>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $user_data['teacher_phone']); ?>" 
                           class="btn btn-success btn-sm mt-2" 
                           target="_blank">
                            <i class="bi bi-whatsapp"></i> 
                            <?php echo __('contact_via_whatsapp'); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php
    // Stop executing the rest of the page
    $content = ob_get_clean();
    include '../includes/layout.php';
    exit;
endif;

// Get student's circle, department and teacher information
$stmt = $pdo->prepare("
    SELECT 
        d.name as department_name,
        c.name as circle_name,
        t.name as teacher_name,
        s.name as supervisor_name,
        c.circle_time,
        d.student_gender,
        c.whatsapp_group,
        c.telegram_group
    FROM circle_students cs
    JOIN study_circles c ON cs.circle_id = c.id
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN users t ON c.teacher_id = t.id
    LEFT JOIN users s ON c.supervisor_id = s.id
    WHERE cs.student_id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$student_info = $stmt->fetch();

// Get student's points from all circles
$stmt = $pdo->prepare("
    SELECT 
        c.name as circle_name,
        COALESCE(sp.total_points, 0) as points,
        (
            SELECT COUNT(*) + 1
            FROM student_points sp2
            WHERE sp2.circle_id = c.id AND sp2.total_points > COALESCE(sp.total_points, 0)
        ) as rank,
        (
            SELECT COUNT(*)
            FROM circle_students cs2
            WHERE cs2.circle_id = c.id
        ) as total_students
    FROM circle_students cs
    JOIN study_circles c ON cs.circle_id = c.id
    LEFT JOIN student_points sp ON sp.student_id = cs.student_id AND sp.circle_id = cs.circle_id
    WHERE cs.student_id = ?
");
$stmt->execute([$user_id]);
$points_data = $stmt->fetchAll();

// Get total points across all circles
$total_points = 0;
foreach ($points_data as $data) {
    $total_points += $data['points'];
}

// Get working days
$work_days = getStudentDepartmentWorkDays($user_id);

// Get reports for the last 7 days
$days = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = strtolower(date('l', strtotime($date)));
    $is_working_day = $work_days["work_$day_name"] ?? false;
    
    $report = getStudentDailyReport($user_id, $date);
    $card_color = getReportCardColor($report, $is_working_day);
    
    $days[] = [
        'date' => $date,
        'day_name' => $day_name,
        'is_working_day' => $is_working_day,
        'report' => $report,
        'card_color' => $card_color
    ];
}

$pageTitle = __('daily_reports');
ob_start();
?>

<style>
.islamic-pattern {
    background-color: #f8f9fa;
    background-image: url('data:image/svg+xml,<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><path d="M20 0L0 20h40L20 0zm0 40L40 20H0l20 20z" fill="%23e9ecef" fill-opacity="0.4"/></svg>');
    padding: 0rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.stats-container {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.stats-card {
    flex: 1;
    background: linear-gradient(135deg, #004d40 0%, #00695c 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
    pointer-events: none;
}

.stats-card .value {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 0.5rem 0;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
}

.stats-card .label {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.stats-card .icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.circle-list {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1rem;
}

.circle-item {
    background: rgba(255, 255, 255, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.circle-item .rank {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    font-size: 0.8rem;
}

.report-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    border: 1px solid #e9ecef;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.report-card .card-header {
    background-color: #004d40;
    color: white;
    padding: 1rem;
    text-align: center;
    font-family: "Traditional Arabic", serif;
}

.report-card.holiday .card-header {
    background-color: #7b1fa2;
}

.report-card.no-report .card-header {
    background-color: #616161;
}

.report-card.excellent .card-header {
    background-color: #2e7d32;
}

.report-card.good .card-header {
    background-color: #1565c0;
}

.report-card.pass .card-header {
    background-color: #ef6c00;
}

.report-card.poor .card-header {
    background-color: #c62828;
}

.report-card .card-body {
    padding: 1.5rem;
    text-align: center;
    flex-grow: 1;
}

.report-card .card-footer {
    background-color: #f8f9fa;
    padding: 1rem;
    text-align: center;
    margin-top: auto;
}

.report-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
}

.status-excellent {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.status-good {
    background-color: #e3f2fd;
    color: #1565c0;
}

.status-pass {
    background-color: #fff3e0;
    color: #ef6c00;
}

.status-poor {
    background-color: #ffebee;
    color: #c62828;
}

.status-holiday {
    background-color: #f3e5f5;
    color: #7b1fa2;
}

.status-no-report {
    background-color: #fafafa;
    color: #616161;
}

.report-stats {
    display: flex;
    justify-content: space-around;
    margin: 1rem 0;
    font-family: "Traditional Arabic", serif;
}

.stat-item {
    text-align: center;
    padding: 0.5rem;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: bold;
    color: #004d40;
}

.stat-label {
    font-size: 0.9rem;
    color: #666;
}

.surah-range {
    font-size: 0.9rem;
    color: #666;
    margin-top: 1rem;
    padding: 0.5rem;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.action-btn {
    background-color: #004d40;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    transition: all 0.3s;
}

.action-btn:hover {
    background-color: #00695c;
    color: white;
    transform: translateY(-2px);
}

.action-btn.delete-btn {
    background-color: #dc3545;
    margin-left: 0.5rem;
}

.action-btn.delete-btn:hover {
    background-color: #c82333;
}

.welcome-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 2rem;
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.welcome-header i {
    font-size: 2rem;
    color: #004d40;
    margin-bottom: 1rem;
}

.welcome-header h3 {
    color: #004d40;
    margin-bottom: 0;
}

.points-bubble {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #004d40 0%, #00695c 100%);
    border-radius: 50%;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 1000;
}

.points-bubble:hover {
    transform: scale(1.1);
}

.points-bubble .value {
    font-size: 1.5rem;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 2px;
}

.points-bubble .label {
    font-size: 0.7rem;
    opacity: 0.9;
}

.points-details {
    position: fixed;
    top: 110px;
    right: 20px;
    background: white;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    width: 200px;
    display: none;
    z-index: 999;
}

.points-details.show {
    display: block;
}

.points-details .circle-item {
    background: #f8f9fa;
    color: #004d40;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    border-radius: 5px;
    font-size: 0.8rem;
}

.points-details .circle-item:last-child {
    margin-bottom: 0;
}

.points-details .circle-name {
    font-weight: bold;
    margin-bottom: 0.2rem;
}

.points-details .circle-stats {
    display: flex;
    justify-content: space-between;
    color: #666;
}
</style>

<!-- Add SweetAlert2 CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.all.min.js"></script>

<div class="welcome-header">
    <i class="bi bi-book"></i>
    <h3><?php echo __('welcome_message'); ?> <?php echo getUserName(); ?></h3>
</div>

<!-- Points Bubble -->
<div class="points-bubble" onclick="togglePointsDetails()">
    <div class="value"><?php echo formatNumber($total_points); ?></div>
    <div class="label"><?php echo __('points'); ?></div>
</div>

<!-- Points Details -->
<div class="points-details" id="pointsDetails">
    <?php foreach ($points_data as $data): ?>
    <div class="circle-item">
        <div class="circle-name"><?php echo htmlspecialchars($data['circle_name']); ?></div>
        <div class="circle-stats">
            <span><?php echo formatNumber($data['points']); ?> <?php echo __('points'); ?></span>
            <span><?php echo formatNumber($data['rank']); ?>/<?php echo formatNumber($data['total_students']); ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="container mt-4">
    <?php include '../includes/alerts.php'; ?>
    
    <?php if (isset($_GET['payment_status'])): ?>
        <?php if ($_GET['payment_status'] == 'success'): ?>
            <div class="alert alert-success text-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill fs-3 mb-2 d-block"></i>
                <h4 class="alert-heading mb-2"><?php echo __('payment_successful'); ?></h4>
                <p><?php echo __('subscription_activated'); ?></p>
                <?php if (isset($_GET['subscription_id'])): ?>
                    <small><?php echo __('subscription_id'); ?>: <?php echo htmlspecialchars($_GET['subscription_id']); ?></small>
                <?php endif; ?>
            </div>
        <?php elseif ($_GET['payment_status'] == 'failed'): ?>
            <div class="alert alert-danger text-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-3 mb-2 d-block"></i>
                <h4 class="alert-heading mb-2"><?php echo __('payment_failed'); ?></h4>
                <p><?php echo __('payment_error_message'); ?></p>
                <a href="../payment.php" class="btn btn-outline-danger mt-2"><?php echo __('try_again'); ?></a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="islamic-pattern">
        <!-- Student Info Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><?php echo __('circle_info'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><?php echo __('department'); ?>:</strong> <?php echo htmlspecialchars($student_info['department_name'] ?? __('not_assigned')); ?></p>
                        <p><strong><?php echo __('circle'); ?>:</strong> <?php echo htmlspecialchars($student_info['circle_name'] ?? __('not_assigned')); ?></p>
                        <p><strong><?php echo __('teacher'); ?>:</strong> <?php echo htmlspecialchars($student_info['teacher_name'] ?? __('not_assigned')); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><?php echo __('supervisor'); ?>:</strong> <?php echo htmlspecialchars($student_info['supervisor_name'] ?? __('not_assigned')); ?></p>
                        <p><strong><?php echo __('circle_time'); ?>:</strong> <?php echo isset($student_info['circle_time']) ? __($student_info['circle_time']) : __('not_assigned'); ?></p>
                        <p><strong><?php echo __('gender'); ?>:</strong> <?php echo isset($student_info['student_gender']) ? __($student_info['student_gender']) : __('not_specified'); ?></p>
                    </div>
                </div>
                <div class="mt-3">
                    <?php if (!empty($student_info['whatsapp_group'])): ?>
                        <a href="<?php echo htmlspecialchars($student_info['whatsapp_group']); ?>" class="btn btn-success btn-sm" target="_blank">
                            <i class="bi bi-whatsapp"></i> <?php echo __('join_whatsapp_group'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($student_info['telegram_group'])): ?>
                        <a href="<?php echo htmlspecialchars($student_info['telegram_group']); ?>" class="btn btn-info btn-sm" target="_blank">
                            <i class="bi bi-telegram"></i> <?php echo __('join_telegram_group'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Subscription Info Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><?php echo __('subscription_info'); ?></h5>
            </div>
            <div class="card-body">
                <?php
                // Get student's active subscription
                $stmt = $pdo->prepare("
                    SELECT ss.*, sp.lessons_per_month, sp.price, sc.name as circle_name
                    FROM student_subscriptions ss
                    JOIN subscription_plans sp ON ss.plan_id = sp.id
                    JOIN study_circles sc ON ss.circle_id = sc.id
                    WHERE ss.student_id = ? AND ss.is_active = 1 AND ss.end_date >= CURDATE()
                    ORDER BY ss.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $active_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($active_subscription): 
                    // Calculate days remaining
                    $end_date = new DateTime($active_subscription['end_date']);
                    $today = new DateTime();
                    $days_remaining = $today->diff($end_date)->days;
                    $is_expiring_soon = $days_remaining <= 7;
                ?>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('plan'); ?>:</strong> <?php echo $active_subscription['lessons_per_month']; ?> <?php echo __('lessons'); ?></p>
                            <p><strong><?php echo __('duration'); ?>:</strong> <?php echo $active_subscription['duration_months']; ?> <?php echo __('months'); ?></p>
                            <p><strong><?php echo __('payment_status'); ?>:</strong> 
                                <?php if ($active_subscription['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success"><?php echo __('paid'); ?></span>
                                <?php elseif ($active_subscription['payment_status'] === 'pending'): ?>
                                    <span class="badge bg-warning"><?php echo __('pending'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><?php echo __($active_subscription['payment_status']); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('start_date'); ?>:</strong> <?php echo date('Y-m-d', strtotime($active_subscription['start_date'])); ?></p>
                            <p><strong><?php echo __('end_date'); ?>:</strong> <?php echo date('Y-m-d', strtotime($active_subscription['end_date'])); ?></p>
                            <p><strong><?php echo __('days_remaining'); ?>:</strong> 
                                <span class="<?php echo $is_expiring_soon ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo $days_remaining; ?> <?php echo __('days'); ?>
                                </span>
                                <?php if ($is_expiring_soon): ?>
                                    <span class="badge bg-danger"><?php echo __('expiring_soon'); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="subscriptions.php" class="btn btn-primary">
                            <i class="bi bi-card-list"></i> <?php echo __('view_subscription_details'); ?>
                        </a>
                        <?php if ($is_expiring_soon): ?>
                            <a href="subscriptions.php#renew" class="btn btn-warning">
                                <i class="bi bi-arrow-repeat"></i> <?php echo __('renew_subscription'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <p><?php echo __('no_active_subscription'); ?></p>
                        <p><?php echo __('please_subscribe_to_continue'); ?></p>
                    </div>
                    
                    <?php
                    // Get available subscription plans
                    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY lessons_per_month ASC");
                    $stmt->execute();
                    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($plans)):
                    ?>
                        <h5 class="mt-3 mb-3"><?php echo __('available_plans'); ?></h5>
                        <div class="row">
                            <?php foreach ($plans as $plan): ?>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <div class="card h-100 border-primary">
                                        <div class="card-header bg-primary text-white text-center">
                                            <h5 class="mb-0"><?php echo $plan['lessons_per_month']; ?> <?php echo __('lessons'); ?></h5>
                                        </div>
                                        <div class="card-body text-center">
                                            <h3 class="card-title pricing-card-title">
                                                <?php echo $plan['price']; ?> <?php echo __('currency'); ?>
                                                <small class="text-muted">/ <?php echo __('month'); ?></small>
                                            </h3>
                                            <a href="subscriptions.php?plan=<?php echo $plan['id']; ?>" class="btn btn-outline-primary mt-3">
                                                <?php echo __('subscribe_now'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php echo __('no_plans_available'); ?></p>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <a href="subscriptions.php" class="btn btn-primary">
                            <i class="bi bi-card-list"></i> <?php echo __('view_all_subscription_options'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="row">
            <?php foreach ($days as $day): ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="report-card <?php 
                        if (!$day['is_working_day'] && !$day['report']) echo 'holiday';
                        elseif (!$day['report']) echo 'no-report';
                        elseif ($day['report']['grade'] >= 90) echo 'excellent';
                        elseif ($day['report']['grade'] >= 75) echo 'good';
                        elseif ($day['report']['grade'] >= 60) echo 'pass';
                        else echo 'poor';
                    ?>">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php echo __($day['day_name']); ?>
                                <br>
                                <small><?php echo date('Y/m/d', strtotime($day['date'])); ?></small>
                                <?php if (!$day['is_working_day']): ?>
                                    <br>
                                    <small class="text-warning"><i class="bi bi-moon-stars"></i> <?php echo __('holiday'); ?></small>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$day['is_working_day'] && !$day['report']): ?>
                                <div class="report-icon status-holiday">
                                    <i class="bi bi-moon-stars"></i>
                                </div>
                                <div class="stat-value"><?php echo __('holiday'); ?></div>
                            <?php elseif ($day['report']): ?>
                                <div class="report-icon <?php 
                                    if ($day['report']['grade'] >= 90) echo 'status-excellent';
                                    elseif ($day['report']['grade'] >= 75) echo 'status-good';
                                    elseif ($day['report']['grade'] >= 60) echo 'status-pass';
                                    else echo 'status-poor';
                                ?>">
                                    <i class="bi bi-mortarboard"></i>
                                </div>
                                <div class="report-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo formatParts($day['report']['memorization_parts']); ?></div>
                                        <div class="stat-label"><?php echo __('memorization'); ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo formatParts($day['report']['revision_parts']); ?></div>
                                        <div class="stat-label"><?php echo __('revision'); ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo (int)$day['report']['grade']; ?>%</div>
                                        <div class="stat-label"><?php echo __('grade'); ?></div>
                                    </div>
                                </div>
                                <div class="surah-range">
                                    <i class="bi bi-bookmark"></i>
                                    <?php echo $day['report']['from_surah_name']; ?> 
                                    (<?php echo $day['report']['memorization_from_verse']; ?>) 
                                    - 
                                    <?php echo $day['report']['to_surah_name']; ?>
                                    (<?php echo $day['report']['memorization_to_verse']; ?>)
                                </div>
                            <?php else: ?>
                                <div class="report-icon status-no-report">
                                    <i class="bi bi-journal-plus"></i>
                                </div>
                                <div class="stat-value"><?php echo __('no_report'); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <?php if ($day['report']): ?>
                                <a href="edit_report.php?date=<?php echo $day['date']; ?>" class="action-btn" target="_blank">
                                    <i class="bi bi-pencil"></i> 
                                </a>
                                <button onclick="confirmDelete('<?php echo $day['date']; ?>')" class="action-btn delete-btn">
                                    <i class="bi bi-trash"></i> 
                                </button>
                            <?php else: ?>
                                <a href="add_report.php?date=<?php echo $day['date']; ?>" class="action-btn" target="_blank">
                                    <i class="bi bi-plus-circle"></i> 
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><?php echo __('reports_summary'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('memorization'); ?></th>
                                <th><?php echo __('revision'); ?></th>
                                <th><?php echo __('grade'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $day): ?>
                            <tr>
                                <td><?php echo date('Y/m/d', strtotime($day['date'])); ?></td>
                                <td><?php echo formatParts($day['report']['memorization_parts'] ?? 0); ?></td>
                                <td><?php echo formatParts($day['report']['revision_parts'] ?? 0); ?></td>
                                <td><?php echo (int)($day['report']['grade'] ?? 0); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePointsDetails() {
    const details = document.getElementById('pointsDetails');
    details.classList.toggle('show');
}

// Close details when clicking outside
document.addEventListener('click', function(event) {
    const bubble = document.querySelector('.points-bubble');
    const details = document.getElementById('pointsDetails');
    
    if (!bubble.contains(event.target) && !details.contains(event.target)) {
        details.classList.remove('show');
    }
});

function confirmDelete(date) {
    Swal.fire({
        title: '<?php echo __("confirm_delete"); ?>',
        text: '<?php echo __("delete_report_confirmation"); ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<?php echo __("yes_delete"); ?>',
        cancelButtonText: '<?php echo __("cancel"); ?>',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Send delete request
            fetch('delete_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'date=' + encodeURIComponent(date)
            })
            .then(response => response.text())
            .then(result => {
                if (result === 'success') {
                    Swal.fire({
                        title: '<?php echo __("deleted"); ?>',
                        text: '<?php echo __("report_deleted_successfully"); ?>',
                        icon: 'success'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: '<?php echo __("error"); ?>',
                        text: '<?php echo __("error_deleting_report"); ?>',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: '<?php echo __("error"); ?>',
                    text: '<?php echo __("error_deleting_report"); ?>',
                    icon: 'error'
                });
            });
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';