<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

$user_id = $_SESSION['user_id'];
$selected_circle = isset($_GET['circle_id']) ? (int)$_GET['circle_id'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

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

// Get report data based on type
if ($selected_circle) {
    switch ($report_type) {
        case 'summary':
            // Get circle summary
            $stmt = $conn->prepare("
                SELECT 
                    u.id, u.name,
                    COUNT(dr.id) as total_reports,
                    AVG(dr.grade) as avg_grade,
                    SUM(dr.memorization_parts) as total_memorization,
                    SUM(dr.revision_parts) as total_revision,
                    MAX(dr.report_date) as last_report_date
                FROM circle_students cs
                JOIN users u ON cs.student_id = u.id
                LEFT JOIN daily_reports dr ON u.id = dr.student_id 
                    AND dr.report_date BETWEEN ? AND ?
                WHERE cs.circle_id = ?
                GROUP BY u.id, u.name
                ORDER BY u.name
            ");
            $stmt->bind_param("ssi", $date_from, $date_to, $selected_circle);
            $stmt->execute();
            $summary_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;

        case 'attendance':
            // Get attendance report
            $stmt = $conn->prepare("
                WITH RECURSIVE dates AS (
                    SELECT ? as date
                    UNION ALL
                    SELECT date + INTERVAL 1 DAY
                    FROM dates
                    WHERE date < ?
                )
                SELECT 
                    d.date,
                    COUNT(DISTINCT cs.student_id) as total_students,
                    COUNT(DISTINCT dr.student_id) as reports_submitted
                FROM dates d
                CROSS JOIN circle_students cs
                LEFT JOIN daily_reports dr ON cs.student_id = dr.student_id 
                    AND dr.report_date = d.date
                WHERE cs.circle_id = ?
                GROUP BY d.date
                ORDER BY d.date
            ");
            $stmt->bind_param("ssi", $date_from, $date_to, $selected_circle);
            $stmt->execute();
            $attendance_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;

        case 'performance':
            // Get performance metrics
            $stmt = $conn->prepare("
                SELECT 
                    DATE(dr.report_date) as report_date,
                    AVG(dr.grade) as avg_grade,
                    AVG(dr.memorization_parts) as avg_memorization,
                    AVG(dr.revision_parts) as avg_revision,
                    COUNT(DISTINCT dr.student_id) as students_reported
                FROM daily_reports dr
                JOIN circle_students cs ON dr.student_id = cs.student_id
                WHERE cs.circle_id = ?
                AND dr.report_date BETWEEN ? AND ?
                GROUP BY DATE(dr.report_date)
                ORDER BY dr.report_date
            ");
            $stmt->bind_param("iss", $selected_circle, $date_from, $date_to);
            $stmt->execute();
            $performance_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            break;
    }
}

$pageTitle = 'Reports Analysis';
$pageHeader = 'Reports Analysis';
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

            <div class="col-md-2">
                <label for="report_type" class="form-label">Report Type</label>
                <select name="report_type" id="report_type" class="form-select" onchange="this.form.submit()">
                    <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary</option>
                    <option value="attendance" <?php echo $report_type == 'attendance' ? 'selected' : ''; ?>>Attendance</option>
                    <option value="performance" <?php echo $report_type == 'performance' ? 'selected' : ''; ?>>Performance</option>
                </select>
            </div>

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

            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">View</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_circle): ?>
    <?php if ($report_type == 'summary' && !empty($summary_data)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Circle Summary Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Total Reports</th>
                                <th>Average Grade</th>
                                <th>Total Memorization</th>
                                <th>Total Revision</th>
                                <th>Last Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo $row['total_reports']; ?></td>
                                    <td><?php echo number_format($row['avg_grade'], 1); ?>%</td>
                                    <td><?php echo number_format($row['total_memorization'], 1); ?> pages</td>
                                    <td><?php echo number_format($row['total_revision'], 1); ?> pages</td>
                                    <td>
                                        <?php echo $row['last_report_date'] ? date('Y-m-d', strtotime($row['last_report_date'])) : 'No reports'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($report_type == 'attendance' && !empty($attendance_data)): ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Attendance Chart</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Attendance Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_days = count($attendance_data);
                        $total_attendance = 0;
                        $total_possible = 0;
                        foreach ($attendance_data as $day) {
                            $total_attendance += $day['reports_submitted'];
                            $total_possible += $day['total_students'];
                        }
                        $attendance_rate = $total_possible ? ($total_attendance / $total_possible) * 100 : 0;
                        ?>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Days
                                <span class="badge bg-primary rounded-pill"><?php echo $total_days; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Average Attendance
                                <span class="badge bg-success rounded-pill">
                                    <?php echo number_format($attendance_rate, 1); ?>%
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($attendance_data, 'date')); ?>,
                datasets: [{
                    label: 'Attendance',
                    data: <?php 
                        echo json_encode(array_map(function($day) {
                            return $day['total_students'] ? 
                                ($day['reports_submitted'] / $day['total_students']) * 100 : 0;
                        }, $attendance_data));
                    ?>,
                    borderColor: 'rgb(13, 110, 253)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Attendance Rate (%)'
                        }
                    }
                }
            }
        });
        </script>

    <?php elseif ($report_type == 'performance' && !empty($performance_data)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Performance Metrics</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const perfCtx = document.getElementById('performanceChart').getContext('2d');
        new Chart(perfCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($performance_data, 'report_date')); ?>,
                datasets: [{
                    label: 'Average Grade (%)',
                    data: <?php echo json_encode(array_column($performance_data, 'avg_grade')); ?>,
                    borderColor: 'rgb(13, 110, 253)',
                    yAxisID: 'y'
                }, {
                    label: 'Average Memorization (Pages)',
                    data: <?php echo json_encode(array_column($performance_data, 'avg_memorization')); ?>,
                    borderColor: 'rgb(25, 135, 84)',
                    yAxisID: 'y1'
                }, {
                    label: 'Average Revision (Pages)',
                    data: <?php echo json_encode(array_column($performance_data, 'avg_revision')); ?>,
                    borderColor: 'rgb(255, 193, 7)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Grade (%)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Pages'
                        }
                    }
                }
            }
        });
        </script>
    <?php else: ?>
        <div class="alert alert-info">
            No data available for the selected period.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
