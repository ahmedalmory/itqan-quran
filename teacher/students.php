<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

$user_id = $_SESSION['user_id'];
$selected_circle = isset($_GET['circle_id']) ? (int)$_GET['circle_id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success_message = '';
$error_message = '';

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

// Handle student removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student'])) {
    $student_id = (int)$_POST['student_id'];
    $circle_id = (int)$_POST['circle_id'];
    
    $stmt = $conn->prepare("DELETE FROM circle_students WHERE student_id = ? AND circle_id = ?");
    $stmt->bind_param("ii", $student_id, $circle_id);
    
    if ($stmt->execute()) {
        $success_message = "Student removed successfully.";
    } else {
        $error_message = "Error removing student.";
    }
}

// Handle adding new student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $email = sanitize_input($_POST['email']);
    $circle_id = (int)$_POST['circle_id'];
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'student'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Check if student is already in the circle
        $stmt = $conn->prepare("SELECT id FROM circle_students WHERE student_id = ? AND circle_id = ?");
        $stmt->bind_param("ii", $user['id'], $circle_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $error_message = "Student is already in this circle.";
        } else {
            // Add student to circle
            $stmt = $conn->prepare("INSERT INTO circle_students (student_id, circle_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user['id'], $circle_id);
            
            if ($stmt->execute()) {
                $success_message = "Student added successfully.";
            } else {
                $error_message = "Error adding student.";
            }
        }
    } else {
        $error_message = "No student found with this email.";
    }
}

// Get students for selected circle
if ($selected_circle) {
    $stmt = $conn->prepare("
        SELECT 
            u.id, u.name, u.email, u.phone,
            (SELECT COUNT(*) FROM daily_reports WHERE student_id = u.id) as total_reports,
            (SELECT MAX(report_date) FROM daily_reports WHERE student_id = u.id) as last_report
        FROM circle_students cs
        JOIN users u ON cs.student_id = u.id
        WHERE cs.circle_id = ?
        ORDER BY u.name
    ");
    $stmt->bind_param("i", $selected_circle);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Student Management';
$pageHeader = 'Manage Students';
ob_start();
?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="circle_id" class="form-label">Select Circle</label>
                <select name="circle_id" id="circle_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($circles as $circle): ?>
                        <option value="<?php echo $circle['id']; ?>" 
                                <?php echo $circle['id'] == $selected_circle ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($circle['name']); ?> 
                            (<?php echo htmlspecialchars($circle['department_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_circle): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Students List</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="bi bi-person-plus"></i> Add Student
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Total Reports</th>
                                    <th>Last Report</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                        <td><?php echo $student['total_reports']; ?></td>
                                        <td>
                                            <?php echo $student['last_report'] ? 
                                                date('Y-m-d', strtotime($student['last_report'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="progress.php?student_id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bi bi-graph-up"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmRemove(<?php echo $student['id']; ?>)">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Circle Information</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $circle = array_filter($circles, function($c) use ($selected_circle) {
                        return $c['id'] == $selected_circle;
                    });
                    $circle = reset($circle);
                    ?>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Students
                            <span class="badge bg-primary rounded-pill">
                                <?php echo count($students); ?>/<?php echo $circle['max_students']; ?>
                            </span>
                        </li>
                        <li class="list-group-item">
                            Time: <?php echo str_replace('_', ' ', ucfirst($circle['circle_time'])); ?>
                        </li>
                        <?php if ($circle['whatsapp_group']): ?>
                            <li class="list-group-item">
                                <a href="<?php echo htmlspecialchars($circle['whatsapp_group']); ?>" 
                                   class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-whatsapp"></i> WhatsApp Group
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($circle['telegram_group']): ?>
                            <li class="list-group-item">
                                <a href="<?php echo htmlspecialchars($circle['telegram_group']); ?>" 
                                   class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-telegram"></i> Telegram Group
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="circle_id" value="<?php echo $selected_circle; ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label">Student Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Student Form -->
    <form id="removeStudentForm" method="POST" style="display: none;">
        <input type="hidden" name="student_id" id="removeStudentId">
        <input type="hidden" name="circle_id" value="<?php echo $selected_circle; ?>">
        <input type="hidden" name="remove_student" value="1">
    </form>

    <script>
    function confirmRemove(studentId) {
        if (confirm('Are you sure you want to remove this student from the circle?')) {
            document.getElementById('removeStudentId').value = studentId;
            document.getElementById('removeStudentForm').submit();
        }
    }
    </script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
