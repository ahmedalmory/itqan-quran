<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/classes/PaymobPayment.php';

// Check if user has completed registration
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;
$payment_url = '';

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's circle
$stmt = $conn->prepare("
    SELECT cs.*, sc.name as circle_name 
    FROM circle_students cs
    JOIN study_circles sc ON cs.circle_id = sc.id
    WHERE cs.student_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$circle = $stmt->get_result()->fetch_assoc();

if (!$circle) {
    header("Location: select_circle.php");
    exit();
}

// Get available subscription plans
$stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY lessons_per_month ASC");
$stmt->execute();
$plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($plans)) {
    $errors[] = __('no_subscription_plans_available');
}

// Check if Paymob payment is enabled
$stmt = $conn->prepare("SELECT setting_value FROM payment_settings WHERE setting_key = 'payment_enabled'");
$stmt->execute();
$result = $stmt->get_result();
$payment_enabled = $result->fetch_assoc()['setting_value'] ?? '0';
$payment_enabled = ($payment_enabled === '1');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
    $duration_months = filter_input(INPUT_POST, 'duration_months', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    
    if (!$plan_id) {
        $errors[] = __('please_select_subscription_plan');
    }
    
    if (!$duration_months || $duration_months < 1 || $duration_months > 12) {
        $errors[] = __('invalid_subscription_duration');
    }
    
    if (!$payment_method || !in_array($payment_method, ['cash', 'paymob'])) {
        $errors[] = __('please_select_payment_method');
    }
    
    if (empty($errors)) {
        // Get plan details
        $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE id = ?");
        $stmt->bind_param("i", $plan_id);
        $stmt->execute();
        $plan = $stmt->get_result()->fetch_assoc();
        
        if (!$plan) {
            $errors[] = __('invalid_subscription_plan');
        } else {
            try {
                $conn->begin_transaction();
                
                // Calculate subscription dates and total amount
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime("+$duration_months months"));
                $total_amount = $plan['price'] * $duration_months;
                
                // Create subscription
                $stmt = $conn->prepare("
                    INSERT INTO student_subscriptions 
                    (student_id, circle_id, plan_id, duration_months, start_date, end_date, total_amount, payment_method) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iiisssds", 
                    $user_id, 
                    $circle['circle_id'], 
                    $plan_id, 
                    $duration_months, 
                    $start_date, 
                    $end_date, 
                    $total_amount,
                    $payment_method
                );
                
                if (!$stmt->execute()) {
                    throw new Exception(__('failed_to_create_subscription'));
                }
                
                $subscription_id = $conn->insert_id;
                
                $conn->commit();
                $success = true;
                
                // Handle payment based on selected method
                if ($payment_method === 'paymob') {
                    // Create PDO connection for PaymobPayment class
                    $paymobPayment = new PaymobPayment($pdo);
                    
                    // Process payment
                    $paymentResult = $paymobPayment->processPayment(
                        $subscription_id,
                        $total_amount,
                        [
                            'first_name' => $user['first_name'] ?? $user['name'] ?? '',
                            'last_name' => $user['last_name'] ?? '',
                            'email' => $user['email'] ?? '',
                            'phone' => $user['phone'] ?? ''
                        ]
                    );
                    
                    if ($paymentResult['success']) {
                        // Redirect to Paymob payment page
                        $payment_url = $paymentResult['iframe_url'];
                        header("Location: " . $payment_url);
                        exit();
                    } else {
                        $errors[] = $paymentResult['message'] ?? __('payment_processing_failed');
                    }
                } else {
                    // Cash payment - redirect to dashboard
                    header("Location: student/index.php?subscription_success=1");
                    exit();
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

$pageTitle = __('select_subscription');
ob_start();
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><?php echo __('select_subscription'); ?></h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo __('subscription_created_successfully'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <h4><?php echo __('subscription_details'); ?></h4>
                        <p><?php echo __('subscription_intro_text'); ?></p>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo __('select_subscription_plan'); ?></label>
                            
                            <div class="row g-3 mb-3">
                                <?php foreach ($plans as $plan): ?>
                                <div class="col-md-6">
                                    <div class="subscription-card">
                                        <input type="radio" class="btn-check" name="plan_id" 
                                               id="plan_<?php echo $plan['id']; ?>" 
                                               value="<?php echo $plan['id']; ?>" required
                                               <?php echo (isset($_POST['plan_id']) && $_POST['plan_id'] == $plan['id']) ? 'checked' : ''; ?>>
                                        <label class="card h-100" for="plan_<?php echo $plan['id']; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo $plan['lessons_per_month']; ?> <?php echo __('lessons_per_month'); ?></h5>
                                                <h6 class="card-subtitle mb-3 text-primary fw-bold">
                                                    <?php echo $plan['price']; ?> <?php echo __('currency'); ?>
                                                </h6>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="duration_months" class="form-label fw-bold"><?php echo __('subscription_duration'); ?></label>
                            <select class="form-select" id="duration_months" name="duration_months" required>
                                <option value="1" <?php echo (isset($_POST['duration_months']) && $_POST['duration_months'] == 1) ? 'selected' : ''; ?>>
                                    1 <?php echo __('month'); ?>
                                </option>
                                <option value="3" <?php echo (isset($_POST['duration_months']) && $_POST['duration_months'] == 3) ? 'selected' : ''; ?>>
                                    3 <?php echo __('months'); ?>
                                </option>
                                <option value="6" <?php echo (isset($_POST['duration_months']) && $_POST['duration_months'] == 6) ? 'selected' : ''; ?>>
                                    6 <?php echo __('months'); ?>
                                </option>
                                <option value="12" <?php echo (isset($_POST['duration_months']) && $_POST['duration_months'] == 12) ? 'selected' : ''; ?>>
                                    12 <?php echo __('months'); ?>
                                </option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo __('payment_method'); ?></label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="payment-method-card">
                                        <input type="radio" class="btn-check" name="payment_method" 
                                               id="payment_cash" value="cash" required
                                               <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash') ? 'checked' : ''; ?>>
                                        <label class="card h-100" for="payment_cash">
                                            <div class="card-body">
                                                <h5 class="card-title"><i class="fas fa-money-bill-wave me-2"></i> <?php echo __('cash_payment'); ?></h5>
                                                <p class="card-text small"><?php echo __('pay_cash_at_center'); ?></p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <?php if ($payment_enabled): ?>
                                <div class="col-md-6">
                                    <div class="payment-method-card">
                                        <input type="radio" class="btn-check" name="payment_method" 
                                               id="payment_paymob" value="paymob" required
                                               <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'paymob') ? 'checked' : ''; ?>>
                                        <label class="card h-100" for="payment_paymob">
                                            <div class="card-body">
                                                <h5 class="card-title"><i class="fas fa-credit-card me-2"></i> <?php echo __('online_payment'); ?></h5>
                                                <p class="card-text small"><?php echo __('pay_online_with_card'); ?></p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo __('subscription_summary'); ?></h5>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><?php echo __('circle'); ?>:</span>
                                        <span class="fw-bold"><?php echo htmlspecialchars($circle['circle_name']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><?php echo __('plan'); ?>:</span>
                                        <span class="fw-bold" id="selected_plan_text">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><?php echo __('duration'); ?>:</span>
                                        <span class="fw-bold" id="selected_duration_text">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><?php echo __('payment_method'); ?>:</span>
                                        <span class="fw-bold" id="selected_payment_text">-</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold"><?php echo __('total_amount'); ?>:</span>
                                        <span class="fw-bold text-primary" id="total_amount_text">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <?php echo __('subscribe_and_continue'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Calculate and update subscription summary
    function updateSubscriptionSummary() {
        // Get selected plan
        const selectedPlanEl = document.querySelector('input[name="plan_id"]:checked');
        let planText = '-';
        let planPrice = 0;
        
        if (selectedPlanEl) {
            const planLabel = document.querySelector(`label[for="${selectedPlanEl.id}"]`);
            const planTitle = planLabel.querySelector('.card-title').textContent.trim();
            const planSubtitle = planLabel.querySelector('.card-subtitle').textContent.trim();
            planText = planTitle;
            
            // Extract price from subtitle (e.g. "50.00 EGP" -> 50.00)
            const priceMatch = planSubtitle.match(/(\d+(\.\d+)?)/);
            if (priceMatch) {
                planPrice = parseFloat(priceMatch[1]);
            }
        }
        
        // Get selected duration
        const durationSelect = document.getElementById('duration_months');
        const durationValue = durationSelect.value;
        const durationText = durationSelect.options[durationSelect.selectedIndex].text;
        
        // Get selected payment method
        const selectedPaymentEl = document.querySelector('input[name="payment_method"]:checked');
        let paymentText = '-';
        
        if (selectedPaymentEl) {
            const paymentLabel = document.querySelector(`label[for="${selectedPaymentEl.id}"]`);
            const paymentTitle = paymentLabel.querySelector('.card-title').textContent.trim();
            paymentText = paymentTitle;
        }
        
        // Calculate total amount
        const totalAmount = planPrice * durationValue;
        
        // Update summary
        document.getElementById('selected_plan_text').textContent = planText;
        document.getElementById('selected_duration_text').textContent = durationText;
        document.getElementById('selected_payment_text').textContent = paymentText;
        document.getElementById('total_amount_text').textContent = totalAmount.toFixed(2) + ' <?php echo __('currency'); ?>';
    }
    
    // Add event listeners
    document.querySelectorAll('input[name="plan_id"]').forEach(el => {
        el.addEventListener('change', updateSubscriptionSummary);
    });
    
    document.getElementById('duration_months').addEventListener('change', updateSubscriptionSummary);
    
    document.querySelectorAll('input[name="payment_method"]').forEach(el => {
        el.addEventListener('change', updateSubscriptionSummary);
    });
    
    // Initialize summary
    updateSubscriptionSummary();
</script>

<style>
    .subscription-card .card,
    .payment-method-card .card {
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid #dee2e6;
    }
    
    .subscription-card .card:hover,
    .payment-method-card .card:hover {
        border-color: #adb5bd;
    }
    
    .btn-check:checked + .card {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
