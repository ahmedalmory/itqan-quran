<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../unauthorized.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission for adding/editing plans
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add new plan
        if ($_POST['action'] === 'add') {
            $lessons = filter_input(INPUT_POST, 'lessons_per_month', FILTER_VALIDATE_INT);
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            
            if ($lessons && $price) {
                try {
                    $stmt = $conn->prepare("INSERT INTO subscription_plans (lessons_per_month, price) VALUES (?, ?)");
                    $stmt->bind_param("id", $lessons, $price);
                    
                    if ($stmt->execute()) {
                        $success_message = __('plan_added_successfully');
                    } else {
                        $error_message = __('failed_to_add_plan');
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            } else {
                $error_message = __('invalid_input');
            }
        }
        
        // Edit existing plan
        else if ($_POST['action'] === 'edit') {
            $plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
            $lessons = filter_input(INPUT_POST, 'lessons_per_month', FILTER_VALIDATE_INT);
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($plan_id && $lessons && $price) {
                try {
                    $stmt = $conn->prepare("UPDATE subscription_plans SET lessons_per_month = ?, price = ?, is_active = ? WHERE id = ?");
                    $stmt->bind_param("idii", $lessons, $price, $is_active, $plan_id);
                    
                    if ($stmt->execute()) {
                        $success_message = __('plan_updated_successfully');
                    } else {
                        $error_message = __('failed_to_update_plan');
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            } else {
                $error_message = __('invalid_input');
            }
        }
        
        // Delete plan
        else if ($_POST['action'] === 'delete') {
            $plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
            
            if ($plan_id) {
                // Check if plan is being used in any subscriptions
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM student_subscriptions WHERE plan_id = ?");
                $stmt->bind_param("i", $plan_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    $error_message = __('cannot_delete_plan_in_use');
                } else {
                    try {
                        $stmt = $conn->prepare("DELETE FROM subscription_plans WHERE id = ?");
                        $stmt->bind_param("i", $plan_id);
                        
                        if ($stmt->execute()) {
                            $success_message = __('plan_deleted_successfully');
                        } else {
                            $error_message = __('failed_to_delete_plan');
                        }
                    } catch (Exception $e) {
                        $error_message = $e->getMessage();
                    }
                }
            } else {
                $error_message = __('invalid_input');
            }
        }
    }
}

// Get all subscription plans
$stmt = $conn->prepare("SELECT * FROM subscription_plans ORDER BY lessons_per_month ASC");
$stmt->execute();
$plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = __('subscription_plans');
ob_start();
?>

<div class="container py-4">
    <h1 class="mb-4"><?php echo __('subscription_plans'); ?></h1>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Add New Plan Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><?php echo __('add_new_plan'); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="lessons_per_month" class="form-label"><?php echo __('lessons_per_month'); ?></label>
                            <select class="form-select" id="lessons_per_month" name="lessons_per_month" required>
                                <option value="4">4</option>
                                <option value="8">8</option>
                                <option value="12">12</option>
                                <option value="16">16</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="price" class="form-label"><?php echo __('price'); ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                                <span class="input-group-text"><?php echo __('currency'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary"><?php echo __('add_plan'); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Existing Plans Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><?php echo __('existing_plans'); ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($plans)): ?>
                <p class="text-center"><?php echo __('no_plans_found'); ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo __('id'); ?></th>
                                <th><?php echo __('lessons_per_month'); ?></th>
                                <th><?php echo __('price'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('created_at'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plans as $plan): ?>
                                <tr>
                                    <td><?php echo $plan['id']; ?></td>
                                    <td><?php echo $plan['lessons_per_month']; ?></td>
                                    <td><?php echo $plan['price']; ?></td>
                                    <td>
                                        <?php if ($plan['is_active']): ?>
                                            <span class="badge bg-success"><?php echo __('active'); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?php echo __('inactive'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($plan['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-plan-btn" 
                                                data-id="<?php echo $plan['id']; ?>"
                                                data-lessons="<?php echo $plan['lessons_per_month']; ?>"
                                                data-price="<?php echo $plan['price']; ?>"
                                                data-active="<?php echo $plan['is_active']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editPlanModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-plan-btn"
                                                data-id="<?php echo $plan['id']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#deletePlanModal">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1" aria-labelledby="editPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="plan_id" id="edit_plan_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPlanModalLabel"><?php echo __('edit_plan'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_lessons_per_month" class="form-label"><?php echo __('lessons_per_month'); ?></label>
                        <select class="form-select" id="edit_lessons_per_month" name="lessons_per_month" required>
                            <option value="4">4</option>
                            <option value="8">8</option>
                            <option value="12">12</option>
                            <option value="16">16</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_price" class="form-label"><?php echo __('price'); ?></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required>
                            <span class="input-group-text"><?php echo __('currency'); ?></span>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">
                            <?php echo __('active'); ?>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo __('save_changes'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Plan Modal -->
<div class="modal fade" id="deletePlanModal" tabindex="-1" aria-labelledby="deletePlanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="plan_id" id="delete_plan_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePlanModalLabel"><?php echo __('confirm_delete'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo __('are_you_sure_delete_plan'); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-danger"><?php echo __('delete'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Populate edit modal with plan data
    document.querySelectorAll('.edit-plan-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const lessons = this.getAttribute('data-lessons');
            const price = this.getAttribute('data-price');
            const active = this.getAttribute('data-active') === '1';
            
            document.getElementById('edit_plan_id').value = id;
            document.getElementById('edit_lessons_per_month').value = lessons;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_is_active').checked = active;
        });
    });
    
    // Set plan ID for deletion
    document.querySelectorAll('.delete-plan-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            document.getElementById('delete_plan_id').value = id;
        });
    });
</script>

<?php
$content = ob_get_clean();
include '../includes/admin_layout.php';
?>
