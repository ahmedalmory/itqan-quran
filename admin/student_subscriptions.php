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

// Handle form submission for updating subscription status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $subscription_id = filter_input(INPUT_POST, 'subscription_id', FILTER_VALIDATE_INT);
    $payment_status = sanitize_input($_POST['payment_status']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($subscription_id && in_array($payment_status, ['pending', 'paid', 'failed', 'refunded'])) {
        try {
            $stmt = $conn->prepare("UPDATE student_subscriptions SET payment_status = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("sii", $payment_status, $is_active, $subscription_id);
            
            if ($stmt->execute()) {
                $success_message = __('subscription_updated_successfully');
            } else {
                $error_message = __('failed_to_update_subscription');
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = __('invalid_input');
    }
}

// Get filter parameters
$filter_student = filter_input(INPUT_GET, 'student', FILTER_SANITIZE_STRING);
$filter_status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$filter_payment = filter_input(INPUT_GET, 'payment', FILTER_SANITIZE_STRING);

// Build query with filters
$query = "
    SELECT ss.*, 
           u.name as student_name, 
           u.email as student_email,
           sp.lessons_per_month, 
           sp.price, 
           sc.name as circle_name
    FROM student_subscriptions ss
    JOIN users u ON ss.student_id = u.id
    JOIN subscription_plans sp ON ss.plan_id = sp.id
    JOIN study_circles sc ON ss.circle_id = sc.id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($filter_student)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$filter_student%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

if (!empty($filter_status)) {
    $query .= " AND ss.is_active = ?";
    $params[] = $filter_status;
    $types .= "i";
}

if (!empty($filter_payment)) {
    $query .= " AND ss.payment_status = ?";
    $params[] = $filter_payment;
    $types .= "s";
}

$query .= " ORDER BY ss.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$subscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = __('student_subscriptions');
ob_start();
?>

<div class="container py-4">
    <h1 class="mb-4"><?php echo __('student_subscriptions'); ?></h1>
    
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
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><?php echo __('filter_subscriptions'); ?></h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="student" class="form-label"><?php echo __('student_name_or_email'); ?></label>
                    <input type="text" class="form-control" id="student" name="student" 
                           value="<?php echo htmlspecialchars($filter_student ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-select" id="status" name="status">
                        <option value=""><?php echo __('all'); ?></option>
                        <option value="1" <?php echo ($filter_status === '1') ? 'selected' : ''; ?>>
                            <?php echo __('active'); ?>
                        </option>
                        <option value="0" <?php echo ($filter_status === '0') ? 'selected' : ''; ?>>
                            <?php echo __('inactive'); ?>
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="payment" class="form-label"><?php echo __('payment_status'); ?></label>
                    <select class="form-select" id="payment" name="payment">
                        <option value=""><?php echo __('all'); ?></option>
                        <option value="pending" <?php echo ($filter_payment === 'pending') ? 'selected' : ''; ?>>
                            <?php echo __('pending'); ?>
                        </option>
                        <option value="paid" <?php echo ($filter_payment === 'paid') ? 'selected' : ''; ?>>
                            <?php echo __('paid'); ?>
                        </option>
                        <option value="failed" <?php echo ($filter_payment === 'failed') ? 'selected' : ''; ?>>
                            <?php echo __('failed'); ?>
                        </option>
                        <option value="refunded" <?php echo ($filter_payment === 'refunded') ? 'selected' : ''; ?>>
                            <?php echo __('refunded'); ?>
                        </option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><?php echo __('filter'); ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Subscriptions Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><?php echo __('subscriptions_list'); ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($subscriptions)): ?>
                <p class="text-center"><?php echo __('no_subscriptions_found'); ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo __('id'); ?></th>
                                <th><?php echo __('student'); ?></th>
                                <th><?php echo __('circle'); ?></th>
                                <th><?php echo __('plan'); ?></th>
                                <th><?php echo __('duration'); ?></th>
                                <th><?php echo __('period'); ?></th>
                                <th><?php echo __('amount'); ?></th>
                                <th><?php echo __('payment_status'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('created_at'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $subscription): ?>
                                <tr>
                                    <td><?php echo $subscription['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($subscription['student_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($subscription['student_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($subscription['circle_name']); ?></td>
                                    <td><?php echo $subscription['lessons_per_month']; ?> <?php echo __('lessons_per_month'); ?></td>
                                    <td><?php echo $subscription['duration_months']; ?> <?php echo $subscription['duration_months'] == 1 ? __('month') : __('months'); ?></td>
                                    <td>
                                        <?php echo date('Y-m-d', strtotime($subscription['start_date'])); ?> - 
                                        <?php echo date('Y-m-d', strtotime($subscription['end_date'])); ?>
                                    </td>
                                    <td><?php echo $subscription['total_amount']; ?> <?php echo __('currency'); ?></td>
                                    <td>
                                        <?php if ($subscription['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success"><?php echo __('paid'); ?></span>
                                        <?php elseif ($subscription['payment_status'] === 'pending'): ?>
                                            <span class="badge bg-warning"><?php echo __('pending'); ?></span>
                                        <?php elseif ($subscription['payment_status'] === 'failed'): ?>
                                            <span class="badge bg-danger"><?php echo __('failed'); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo __($subscription['payment_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($subscription['is_active']): ?>
                                            <span class="badge bg-success"><?php echo __('active'); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?php echo __('inactive'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($subscription['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-subscription-btn" 
                                                data-id="<?php echo $subscription['id']; ?>"
                                                data-payment="<?php echo $subscription['payment_status']; ?>"
                                                data-active="<?php echo $subscription['is_active']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editSubscriptionModal">
                                            <i class="bi bi-pencil"></i>
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

<!-- Edit Subscription Modal -->
<div class="modal fade" id="editSubscriptionModal" tabindex="-1" aria-labelledby="editSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="subscription_id" id="edit_subscription_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSubscriptionModalLabel"><?php echo __('update_subscription_status'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_payment_status" class="form-label"><?php echo __('payment_status'); ?></label>
                        <select class="form-select" id="edit_payment_status" name="payment_status" required>
                            <option value="pending"><?php echo __('pending'); ?></option>
                            <option value="paid"><?php echo __('paid'); ?></option>
                            <option value="failed"><?php echo __('failed'); ?></option>
                            <option value="refunded"><?php echo __('refunded'); ?></option>
                        </select>
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

<script>
    // Populate edit modal with subscription data
    document.querySelectorAll('.edit-subscription-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const payment = this.getAttribute('data-payment');
            const active = this.getAttribute('data-active') === '1';
            
            document.getElementById('edit_subscription_id').value = id;
            document.getElementById('edit_payment_status').value = payment;
            document.getElementById('edit_is_active').checked = active;
        });
    });
</script>

<?php
$content = ob_get_clean();
include '../includes/admin_layout.php';
?>
