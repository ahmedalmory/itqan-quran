<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

$user_id = $_SESSION['user_id'];
$selected_circle = isset($_GET['circle_id']) ? (int)$_GET['circle_id'] : null;
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get teacher's circles
$stmt = $conn->prepare("
    SELECT c.*, d.name as department_name
    FROM study_circles c
    JOIN departments d ON c.department_id = d.id
    WHERE c.teacher_id = ?
    ORDER BY c.name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$circles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// If no circle is selected, use the first one
if (!$selected_circle && !empty($circles)) {
    $selected_circle = $circles[0]['id'];
}

// Get students for selected circle
if ($selected_circle) {
    $stmt = $conn->prepare("
        SELECT u.id, u.name
        FROM circle_students cs
        JOIN users u ON cs.student_id = u.id
        WHERE cs.circle_id = ?
        ORDER BY u.name
    ");
    $stmt->bind_param("i", $selected_circle);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get progress data
if ($selected_circle && $selected_student) {
    // Get daily reports for the selected period
    $stmt = $conn->prepare("
        SELECT dr.*, 
               s1.name as from_surah_name,
               s2.name as to_surah_name
        FROM daily_reports dr
        JOIN surahs s1 ON dr.memorization_from_surah = s1.id
        JOIN surahs s2 ON dr.memorization_to_surah = s2.id
        WHERE dr.student_id = ?
        AND dr.report_date BETWEEN ? AND ?
        ORDER BY dr.report_date
    ");
    $stmt->bind_param("iss", $selected_student, $date_from, $date_to);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate statistics
    $total_memorization = 0;
    $total_revision = 0;
    $total_days = 0;
    $grades = [];
    $memorization_trend = [];
    $revision_trend = [];
    $grade_trend = [];

    foreach ($reports as $report) {
        $total_memorization += $report['memorization_parts'];
        $total_revision += $report['revision_parts'];
        $total_days++;
        $grades[] = $report['grade'];
        
        $date = $report['report_date'];
        $memorization_trend[$date] = $report['memorization_parts'];
        $revision_trend[$date] = $report['revision_parts'];
        $grade_trend[$date] = $report['grade'];
    }

    $avg_grade = !empty($grades) ? array_sum($grades) / count($grades) : 0;
    $avg_memorization = $total_days ? $total_memorization / $total_days : 0;
    $avg_revision = $total_days ? $total_revision / $total_days : 0;
}

$pageTitle = 'Student Progress';
$pageHeader = 'Progress Tracking';
ob_start();
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="circle_id" class="form-label">Select Circle</label>
                <select name="circle_id" id="circle_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($circles as $circle): ?>
                        <option value="<?php echo $circle['id']; ?>" 
                                <?php echo $circle['id'] == $selected_circle ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($circle['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (!empty($students)): ?>
            <div class="col-md-3">
                <label for="student_id" class="form-label">Select Student</label>
                <select name="student_id" id="student_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Select Student</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>"
                                <?php echo $student['id'] == $selected_student ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-3">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" name="date_from" id="date_from" class="form-control" 
                       value="<?php echo $date_from; ?>">
            </div>

            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" name="date_to" id="date_to" class="form-control" 
                       value="<?php echo $date_to; ?>">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">View Progress</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_student && !empty($reports)): ?>
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Average Grade</h5>
                    <h2 class="card-text"><?php echo number_format($avg_grade, 1); ?>%</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Memorization</h5>
                    <h2 class="card-text"><?php echo number_format($total_memorization, 1); ?> pages</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Revision</h5>
                    <h2 class="card-text"><?php echo number_format($total_revision, 1); ?> pages</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Days Reported</h5>
                    <h2 class="card-text"><?php echo $total_days; ?> days</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Progress Chart</h5>
                </div>
                <div class="card-body">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Daily Averages</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Average Memorization
                            <span class="badge bg-primary rounded-pill">
                                <?php echo number_format($avg_memorization, 2); ?> pages/day
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Average Revision
                            <span class="badge bg-success rounded-pill">
                                <?php echo number_format($avg_revision, 2); ?> pages/day
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Attendance Rate
                            <span class="badge bg-info rounded-pill">
                                <?php 
                                $date_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
                                echo number_format(($total_days / ($date_diff + 1)) * 100, 1); 
                                ?>%
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Detailed Reports</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Memorization</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Revision</th>
                            <th>Grade</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo $report['report_date']; ?></td>
                                <td><?php echo $report['memorization_parts']; ?> pages</td>
                                <td>
                                    <?php echo htmlspecialchars($report['from_surah_name']); ?>
                                    (<?php echo $report['memorization_from_verse']; ?>)
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($report['to_surah_name']); ?>
                                    (<?php echo $report['memorization_to_verse']; ?>)
                                </td>
                                <td><?php echo $report['revision_parts']; ?> pages</td>
                                <td><?php echo $report['grade']; ?>%</td>
                                <td><?php echo htmlspecialchars($report['notes']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    const ctx = document.getElementById('progressChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_keys($memorization_trend)); ?>,
            datasets: [{
                label: 'Memorization (Pages)',
                data: <?php echo json_encode(array_values($memorization_trend)); ?>,
                borderColor: 'rgb(13, 110, 253)',
                tension: 0.1
            }, {
                label: 'Revision (Pages)',
                data: <?php echo json_encode(array_values($revision_trend)); ?>,
                borderColor: 'rgb(25, 135, 84)',
                tension: 0.1
            }, {
                label: 'Grade (%)',
                data: <?php echo json_encode(array_values($grade_trend)); ?>,
                borderColor: 'rgb(255, 193, 7)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    </script>
<?php elseif ($selected_student): ?>
    <div class="alert alert-info">
        No reports found for the selected period.
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
