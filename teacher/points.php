<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is teacher
requireRole('teacher');

// Get circle ID
$circle_id = filter_input(INPUT_GET, 'circle_id', FILTER_VALIDATE_INT);

// Get teacher's circles
$stmt = $pdo->prepare("SELECT * FROM study_circles WHERE teacher_id = ? ORDER BY name ASC");
$stmt->execute([$_SESSION['user_id']]);
$circles = $stmt->fetchAll();

if (empty($circles)) {
    setError(__('no_circles_found'));
    header('Location: index.php');
    exit();
}

// If no circle_id is provided, show circles list
if (!$circle_id) {
    $pageTitle = __('student_points');
    ob_start();
    ?>
    <div class="container py-4">
        <h2><?php echo __('student_points'); ?></h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-2">
            <?php foreach ($circles as $circle): ?>
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($circle['name']); ?></h5>
                        <?php
                        // Get total students and total points for this circle
                        $stmt = $pdo->prepare("
                            SELECT 
                                COUNT(DISTINCT cs.student_id) as total_students,
                                COALESCE(SUM(sp.total_points), 0) as total_points
                            FROM circle_students cs
                            LEFT JOIN student_points sp ON cs.student_id = sp.student_id AND sp.circle_id = cs.circle_id
                            WHERE cs.circle_id = ?
                        ");
                        $stmt->execute([$circle['id']]);
                        $stats = $stmt->fetch();
                        ?>
                        <p class="card-text">
                            <span class="badge bg-primary"><?php echo formatNumber($stats['total_students']); ?> <?php echo __('students'); ?></span>
                            <span class="badge bg-success"><?php echo formatNumber($stats['total_points']); ?> <?php echo __('points'); ?></span>
                        </p>
                        <a href="?circle_id=<?php echo $circle['id']; ?>" class="btn btn-primary">
                            <?php echo __('manage_points'); ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    include '../includes/layout.php';
    exit();
}

// Verify teacher has access to this circle
$stmt = $pdo->prepare("SELECT * FROM study_circles WHERE id = ? AND teacher_id = ?");
$stmt->execute([$circle_id, $_SESSION['user_id']]);
$circle = $stmt->fetch();

if (!$circle) {
    header('Location: points.php');
    exit();
}

// Get all students in the circle with their points
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        COALESCE(sp.total_points, 0) as total_points,
        sp.last_updated
    FROM users u
    JOIN circle_students cs ON u.id = cs.student_id
    LEFT JOIN student_points sp ON u.id = sp.student_id AND sp.circle_id = ?
    WHERE cs.circle_id = ?
    ORDER BY sp.total_points DESC, u.name ASC
");
$stmt->execute([$circle_id, $circle_id]);
$students = $stmt->fetchAll();

$pageTitle = __('student_points');
ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?php echo __('student_points'); ?></h2>
        <div>
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addPointsModal">
                <i class="bi bi-plus-circle"></i> <?php echo __('add_points'); ?>
            </button>
            <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#subtractPointsModal">
                <i class="bi bi-dash-circle"></i> <?php echo __('subtract_points'); ?>
            </button>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#resetPointsModal">
                <i class="bi bi-x-circle"></i> <?php echo __('reset_points'); ?>
            </button>
        </div>
    </div>

    <!-- Points Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?php echo __('rank'); ?></th>
                            <th><?php echo __('student_name'); ?></th>
                            <th><?php echo __('points'); ?></th>
                            <th><?php echo __('last_update'); ?></th>
                            <th><?php echo __('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td>
                                <span class="badge bg-primary"><?php echo formatNumber($student['total_points']); ?></span>
                            </td>
                            <td>
                                <?php echo $student['last_updated'] ? date('Y-m-d H:i', strtotime($student['last_updated'])) : '-'; ?>
                            </td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-success quick-add"
                                        data-student-id="<?php echo $student['id']; ?>"
                                        title="<?php echo __('quick_add'); ?>">
                                    <i class="bi bi-plus"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-warning quick-subtract"
                                        data-student-id="<?php echo $student['id']; ?>"
                                        title="<?php echo __('quick_subtract'); ?>">
                                    <i class="bi bi-dash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Points Modal -->
<div class="modal fade" id="addPointsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('add_points'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __('student_name'); ?></th>
                                <th><?php echo __('current_points'); ?></th>
                                <th><?php echo __('points_to_add'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo formatNumber($student['total_points']); ?></td>
                                <td>
                                    <input type="number" 
                                           class="form-control form-control-sm points-input" 
                                           min="0" 
                                           value="0"
                                           data-student-id="<?php echo $student['id']; ?>">
                                </td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-sm btn-success save-points"
                                            data-student-id="<?php echo $student['id']; ?>"
                                            data-action="add">
                                        <?php echo __('save'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subtract Points Modal -->
<div class="modal fade" id="subtractPointsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('subtract_points'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __('student_name'); ?></th>
                                <th><?php echo __('current_points'); ?></th>
                                <th><?php echo __('points_to_subtract'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo formatNumber($student['total_points']); ?></td>
                                <td>
                                    <input type="number" 
                                           class="form-control form-control-sm points-input" 
                                           min="0" 
                                           max="<?php echo $student['total_points']; ?>"
                                           value="0"
                                           data-student-id="<?php echo $student['id']; ?>">
                                </td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-sm btn-warning save-points"
                                            data-student-id="<?php echo $student['id']; ?>"
                                            data-action="subtract">
                                        <?php echo __('save'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Points Modal -->
<div class="modal fade" id="resetPointsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('reset_points'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><?php echo __('reset_points_warning'); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo __('cancel'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirmReset">
                    <?php echo __('confirm_reset'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Save points function
    function savePoints(studentId, points, action) {
        fetch('save_points.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                student_id: studentId,
                circle_id: <?php echo $circle_id; ?>,
                points: points,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to show updated points
                location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<?php echo __("error"); ?>',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: '<?php echo __("error"); ?>',
                text: '<?php echo __("save_error"); ?>'
            });
        });
    }

    // Handle save points buttons
    document.querySelectorAll('.save-points').forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.dataset.studentId;
            const action = this.dataset.action;
            const pointsInput = this.closest('tr').querySelector('.points-input');
            const points = parseInt(pointsInput.value);

            if (points > 0) {
                savePoints(studentId, points, action);
            }
        });
    });

    // Handle quick add/subtract buttons
    document.querySelectorAll('.quick-add, .quick-subtract').forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.dataset.studentId;
            const action = this.classList.contains('quick-add') ? 'add' : 'subtract';
            
            Swal.fire({
                title: action === 'add' ? '<?php echo __("add_points"); ?>' : '<?php echo __("subtract_points"); ?>',
                input: 'number',
                inputAttributes: {
                    min: 0
                },
                showCancelButton: true,
                confirmButtonText: '<?php echo __("save"); ?>',
                cancelButtonText: '<?php echo __("cancel"); ?>'
            }).then((result) => {
                if (result.isConfirmed && result.value > 0) {
                    savePoints(studentId, result.value, action);
                }
            });
        });
    });

    // Handle reset points
    document.getElementById('confirmReset').addEventListener('click', function() {
        fetch('save_points.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                circle_id: <?php echo $circle_id; ?>,
                action: 'reset'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<?php echo __("error"); ?>',
                    text: data.message
                });
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
