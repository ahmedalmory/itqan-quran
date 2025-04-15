<?php
// Start output buffering
ob_start();

require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/classes/PaymobPayment.php';
require_once '../includes/debug_logger.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../unauthorized.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Check for payment status messages in URL parameters
if (isset($_GET['success']) && $_GET['success'] === 'payment_completed') {
    $success_message = __('payment_completed_successfully');
    debug_log("Payment success message displayed", 'info', ['user_id' => $user_id]);
}

if (isset($_GET['error']) && $_GET['error'] === 'payment_failed') {
    $error_message = __('payment_failed_please_try_again');
    debug_log("Payment failure message displayed", 'info', ['user_id' => $user_id]);
}

// Check if Paymob payment is enabled
$stmt = $pdo->prepare("SELECT setting_value FROM payment_settings WHERE setting_key = 'payment_enabled'");
$stmt->execute();
$payment_enabled = $stmt->fetchColumn() === '1';

// Check if wallet payment is enabled
$stmt = $pdo->prepare("SELECT setting_value FROM payment_settings WHERE setting_key = 'wallet_payment_enabled'");
$stmt->execute();
$wallet_payment_enabled = $stmt->fetchColumn() === '1';

// Get all payment settings for debugging
$stmt = $pdo->query("SELECT setting_key, setting_value FROM payment_settings");
$payment_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
debug_log("Payment settings", 'info', $payment_settings);

// Handle payment initiation
if (isset($_POST['action']) && $_POST['action'] === 'pay' && isset($_POST['subscription_id'])) {
    $subscription_id = filter_input(INPUT_POST, 'subscription_id', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_type', FILTER_SANITIZE_STRING) ?: 'card';
    $wallet_phone = filter_input(INPUT_POST, 'wallet_phone', FILTER_SANITIZE_STRING) ?: '';
    
    debug_log("Payment initiation", 'info', [
        'subscription_id' => $subscription_id,
        'payment_method' => $payment_method,
        'has_wallet_phone' => !empty($wallet_phone)
    ]);
    
    // التحقق من تفعيل الدفع بالمحفظة إذا كانت طريقة الدفع هي المحفظة
    if ($payment_method === 'wallet' && !$wallet_payment_enabled) {
        $error_message = __('wallet_payment_not_enabled');
        debug_log("Wallet payment is not enabled", 'warning');
    } else if ($subscription_id) {
        // Get subscription details
        $stmt = $pdo->prepare("
            SELECT ss.*, sp.price, u.* 
            FROM student_subscriptions ss
            JOIN subscription_plans sp ON ss.plan_id = sp.id
            JOIN users u ON ss.student_id = u.id
            WHERE ss.id = ? AND ss.student_id = ? AND ss.payment_status = 'pending'
        ");
        $stmt->execute([$subscription_id, $user_id]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscription) {
            try {
                $paymobPayment = new PaymobPayment($pdo);
                
                // Prepare user data with wallet phone if provided
                $userData = [
                    'first_name' => $subscription['first_name'] ?? $subscription['name'] ?? '',
                    'last_name' => $subscription['last_name'] ?? '',
                    'email' => $subscription['email'] ?? '',
                    'phone' => $subscription['phone'] ?? ''
                ];
                
                // Add wallet phone if provided
                if (!empty($wallet_phone) && $payment_method === 'wallet') {
                    // Clean up the phone number - remove any non-numeric characters
                    $wallet_phone = preg_replace('/[^0-9]/', '', $wallet_phone);
                    
                    // Format Egyptian phone numbers
                    if (strlen($wallet_phone) == 10 && substr($wallet_phone, 0, 1) == '1') {
                        // This is likely an Egyptian number without country code (e.g., 1XXXXXXXXX)
                        $wallet_phone = '2' . $wallet_phone;
                    } else if (strlen($wallet_phone) == 11 && substr($wallet_phone, 0, 1) == '0') {
                        // This is likely an Egyptian number with leading 0 (e.g., 01XXXXXXXXX)
                        $wallet_phone = '2' . substr($wallet_phone, 1);
                    }
                    
                    $userData['wallet_phone'] = $wallet_phone;
                    debug_log("Using provided wallet phone for payment", 'info', [
                        'wallet_phone' => substr($wallet_phone, 0, 3) . '****' . substr($wallet_phone, -2)
                    ]);
                }
                
                $paymentResult = $paymobPayment->processPayment(
                    $subscription_id,
                    $subscription['total_amount'],
                    $userData,
                    $payment_method
                );
                
                if ($paymentResult['success']) {
                    // Check if this is a wallet payment with HTML content
                    if ($payment_method === 'wallet' && isset($paymentResult['html_content'])) {
                        // Output the HTML content directly
                        echo $paymentResult['html_content'];
                        exit();
                    } else if ($payment_method === 'wallet' && isset($paymentResult['redirect_url'])) {
                        // Redirect to wallet payment URL
                        header("Location: " . $paymentResult['redirect_url']);
                        exit();
                    } else if (isset($paymentResult['iframe_url'])) {
                        // Regular iframe redirect
                        header("Location: " . $paymentResult['iframe_url']);
                        exit();
                    } else {
                        // Fallback for any other success case
                        header("Location: subscriptions.php?success=payment_initiated");
                        exit();
                    }
                } else {
                    $error_message = $paymentResult['message'];
                    // Log the error for debugging
                    error_log("Payment processing error in student/subscriptions.php: " . $error_message);
                    if (function_exists('debug_log')) {
                        debug_log("Payment processing error", 'error', [
                            'subscription_id' => $subscription_id,
                            'payment_method' => $payment_method,
                            'message' => $error_message,
                            'details' => $paymentResult['error_details'] ?? []
                        ]);
                    }
                }
            } catch (Exception $e) {
                $error_message = __('payment_processing_error');
                debug_log("Payment exception", 'error', [
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            $error_message = __('invalid_subscription');
        }
    }
}

// Get student's active subscription
$stmt = $pdo->prepare("
    SELECT ss.*, sp.lessons_per_month, sp.price, sc.name as circle_name,
           pt.status as payment_transaction_status, pt.paymob_order_id
    FROM student_subscriptions ss
    JOIN subscription_plans sp ON ss.plan_id = sp.id
    JOIN study_circles sc ON ss.circle_id = sc.id
    LEFT JOIN payment_transactions pt ON pt.subscription_id = ss.id
    WHERE ss.student_id = ? AND ss.is_active = 1
    ORDER BY ss.created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$active_subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student's subscription history
$stmt = $pdo->prepare("
    SELECT ss.*, sp.lessons_per_month, sp.price, sc.name as circle_name,
           pt.status as payment_transaction_status, pt.paymob_order_id
    FROM student_subscriptions ss
    JOIN subscription_plans sp ON ss.plan_id = sp.id
    JOIN study_circles sc ON ss.circle_id = sc.id
    LEFT JOIN payment_transactions pt ON pt.subscription_id = ss.id
    WHERE ss.student_id = ?
    ORDER BY ss.created_at DESC
");
$stmt->execute([$user_id]);
$subscription_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available subscription plans for renewal
$stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY lessons_per_month ASC");
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle subscription renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew') {
    $plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
    $duration_months = filter_input(INPUT_POST, 'duration_months', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $payment_type = filter_input(INPUT_POST, 'payment_type', FILTER_SANITIZE_STRING) ?: 'card';
    
    if (!$plan_id) {
        $error_message = __('please_select_subscription_plan');
    } elseif (!$duration_months || $duration_months < 1 || $duration_months > 12) {
        $error_message = __('invalid_subscription_duration');
    } elseif (!$payment_method || !in_array($payment_method, ['cash', 'paymob'])) {
        $error_message = __('please_select_payment_method');
    } elseif ($payment_method === 'paymob' && $payment_type === 'wallet' && !$wallet_payment_enabled) {
        $error_message = __('wallet_payment_not_enabled');
        debug_log("Wallet payment is not enabled for renewal", 'warning');
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get plan details
            $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
            $stmt->execute([$plan_id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                throw new Exception(__('invalid_subscription_plan'));
            }
            
            // Get student's circle
            $stmt = $pdo->prepare("
                SELECT cs.*, sc.id as circle_id 
                FROM circle_students cs
                JOIN study_circles sc ON cs.circle_id = sc.id
                WHERE cs.student_id = ?
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $circle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$circle) {
                throw new Exception(__('no_circle_found'));
            }
            
            // Calculate subscription dates and total amount
            $start_date = date('Y-m-d');
            if ($active_subscription && strtotime($active_subscription['end_date']) > time()) {
                $start_date = date('Y-m-d', strtotime($active_subscription['end_date'] . ' +1 day'));
            }
            
            $end_date = date('Y-m-d', strtotime($start_date . " +$duration_months months -1 day"));
            $total_amount = $plan['price'] * $duration_months;
            
            // Create subscription
            $stmt = $pdo->prepare("
                INSERT INTO student_subscriptions 
                (student_id, circle_id, plan_id, duration_months, start_date, end_date, total_amount, payment_method) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $circle['circle_id'],
                $plan_id,
                $duration_months,
                $start_date,
                $end_date,
                $total_amount,
                $payment_method
            ]);
            
            $subscription_id = $pdo->lastInsertId();
            
            $pdo->commit();
            
            // Handle payment based on selected method
            if ($payment_method === 'paymob' && $payment_enabled) {
                // Get user data for payment
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $userData = [
                    'first_name' => $user_data['name'] ?? '',
                    'last_name' => $user_data['last_name'] ?? '',
                    'email' => $user_data['email'] ?? '',
                    'phone' => $user_data['phone'] ?? ''
                ];
                
                // If wallet payment, use the phone number from the form
                if ($payment_type === 'wallet') {
                    $wallet_phone = filter_input(INPUT_POST, 'wallet_phone', FILTER_SANITIZE_STRING);
                    if (!empty($wallet_phone)) {
                        // Clean up the phone number - remove any non-numeric characters
                        $wallet_phone = preg_replace('/[^0-9]/', '', $wallet_phone);
                        
                        // Format Egyptian phone numbers
                        if (strlen($wallet_phone) == 10 && substr($wallet_phone, 0, 1) == '1') {
                            // This is likely an Egyptian number without country code (e.g., 1XXXXXXXXX)
                            $wallet_phone = '2' . $wallet_phone;
                        } else if (strlen($wallet_phone) == 11 && substr($wallet_phone, 0, 1) == '0') {
                            // This is likely an Egyptian number with leading 0 (e.g., 01XXXXXXXXX)
                            $wallet_phone = '2' . substr($wallet_phone, 1);
                        }
                        
                        $userData['wallet_phone'] = $wallet_phone;
                        debug_log("Using provided wallet phone for renewal payment", 'info', [
                            'wallet_phone' => substr($wallet_phone, 0, 3) . '****' . substr($wallet_phone, -2)
                        ]);
                    }
                }
                
                $paymobPayment = new PaymobPayment($pdo);
                $paymentResult = $paymobPayment->processPayment(
                    $subscription_id,
                    $total_amount,
                    $userData,
                    $payment_type
                );
                
                if ($paymentResult['success']) {
                    // Check if this is a wallet payment with HTML content
                    if ($payment_type === 'wallet' && isset($paymentResult['html_content'])) {
                        // Output the HTML content directly
                        echo $paymentResult['html_content'];
                        exit();
                    } else if ($payment_type === 'wallet' && isset($paymentResult['redirect_url'])) {
                        // Redirect to wallet payment URL
                        header("Location: " . $paymentResult['redirect_url']);
                        exit();
                    } else if (isset($paymentResult['iframe_url'])) {
                        // Regular iframe redirect
                        header("Location: " . $paymentResult['iframe_url']);
                        exit();
                    } else {
                        // Fallback for any other success case
                        header("Location: subscriptions.php?success=payment_initiated");
                        exit();
                    }
                } else {
                    $error_message = $paymentResult['message'];
                    // Log the error for debugging
                    error_log("Payment processing error in student/subscriptions.php: " . $error_message);
                    if (function_exists('debug_log')) {
                        debug_log("Payment processing error", 'error', [
                            'subscription_id' => $subscription_id,
                            'message' => $error_message,
                            'details' => $paymentResult['error_details'] ?? []
                        ]);
                    }
                }
            } else {
                $success_message = __('subscription_renewed_successfully');
                header("Location: subscriptions.php?success=1");
                exit();
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = $e->getMessage();
            debug_log("Subscription renewal exception", 'error', [
                'error' => $e->getMessage()
            ]);
        }
    }
}

// Check for success message in URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = __('subscription_renewed_successfully');
}

$pageTitle = __('my_subscriptions');
?>

<div class="container py-4">
    <h1 class="mb-4"><?php echo __('my_subscriptions'); ?></h1>
    
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
    
    <div class="row">
        <div class="col-md-8">
            <!-- Active Subscription -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo __('active_subscription'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($active_subscription): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?php echo __('circle'); ?>:</strong> <?php echo htmlspecialchars($active_subscription['circle_name']); ?></p>
                                <p><strong><?php echo __('plan'); ?>:</strong> <?php echo $active_subscription['lessons_per_month']; ?> <?php echo __('lessons_per_month'); ?></p>
                                <p><strong><?php echo __('price'); ?>:</strong> <?php echo $active_subscription['price']; ?> <?php echo __('currency'); ?>/<?php echo __('month'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo __('start_date'); ?>:</strong> <?php echo date('Y-m-d', strtotime($active_subscription['start_date'])); ?></p>
                                <p><strong><?php echo __('end_date'); ?>:</strong> <?php echo date('Y-m-d', strtotime($active_subscription['end_date'])); ?></p>
                                <p><strong><?php echo __('status'); ?>:</strong> 
                                    <?php if ($active_subscription['payment_status'] === 'paid'): ?>
                                        <span class="badge bg-success"><?php echo __('paid'); ?></span>
                                    <?php elseif ($active_subscription['payment_status'] === 'pending'): ?>
                                        <span class="badge bg-warning"><?php echo __('pending'); ?></span>
                                        <?php if ($payment_enabled && $active_subscription['payment_method'] === 'paymob'): ?>
                                            <form method="post" action="" class="mt-2">
                                                <input type="hidden" name="action" value="pay">
                                                <input type="hidden" name="subscription_id" value="<?php echo $active_subscription['id']; ?>">
                                                
                                                <!-- Payment type selection -->
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo __('payment_type'); ?></label>
                                                    <div class="d-flex gap-2">
                                                        <div class="form-check">
                                                            <input type="radio" class="form-check-input" name="payment_type" id="active_payment_card" value="card" checked>
                                                            <label class="form-check-label" for="active_payment_card">
                                                                <i class="fas fa-credit-card me-1"></i> <?php echo __('credit_card'); ?>
                                                            </label>
                                                        </div>
                                                        
                                                        <?php if (!empty($payment_settings['paymob_wallet_integration_id']) && $wallet_payment_enabled): ?>
                                                        <div class="form-check">
                                                            <input type="radio" class="form-check-input" name="payment_type" id="active_payment_wallet" value="wallet">
                                                            <label class="form-check-label" for="active_payment_wallet">
                                                                <i class="fas fa-wallet me-1"></i> <?php echo __('electronic_wallet'); ?>
                                                            </label>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Wallet phone number field -->
                                                <div id="active_wallet_phone_container" class="mb-3" style="display: none;">
                                                    <label for="active_wallet_phone" class="form-label"><?php echo __('wallet_phone_number'); ?></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">+2</span>
                                                        <input type="tel" class="form-control" id="active_wallet_phone" name="wallet_phone" 
                                                               placeholder="01xxxxxxxxx" pattern="[0-9]{10,11}">
                                                    </div>
                                                    <div class="form-text">
                                                        <?php echo __('wallet_phone_number_help'); ?> (01xxxxxxxxx)
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-money-bill-wave me-1"></i> <?php echo __('pay_now'); ?>
                                                </button>
                                            </form>
                                            
                                            <script>
                                                // Toggle wallet phone field visibility based on payment type selection
                                                document.addEventListener('DOMContentLoaded', function() {
                                                    const walletRadio = document.getElementById('active_payment_wallet');
                                                    const walletPhoneContainer = document.getElementById('active_wallet_phone_container');
                                                    
                                                    if (walletRadio && walletPhoneContainer) {
                                                        walletRadio.addEventListener('change', function() {
                                                            walletPhoneContainer.style.display = this.checked ? 'block' : 'none';
                                                            if (this.checked) {
                                                                document.getElementById('active_wallet_phone').setAttribute('required', 'required');
                                                            }
                                                        });
                                                        
                                                        document.getElementById('active_payment_card').addEventListener('change', function() {
                                                            walletPhoneContainer.style.display = 'none';
                                                            document.getElementById('active_wallet_phone').removeAttribute('required');
                                                        });
                                                    }
                                                });
                                            </script>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?php echo __($active_subscription['payment_status']); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php
                        // Calculate days remaining
                        $end_date = new DateTime($active_subscription['end_date']);
                        $today = new DateTime();
                        $days_remaining = $today->diff($end_date)->days;
                        $is_expired = $today > $end_date;
                        ?>
                        
                        <div class="mt-3">
                            <?php if ($is_expired): ?>
                                <div class="alert alert-danger">
                                    <?php echo __('subscription_expired'); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <?php echo sprintf(__('subscription_days_remaining'), $days_remaining); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <?php echo __('no_active_subscription'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Subscription History -->
            <?php if (!empty($subscription_history)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo __('subscription_history'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo __('plan'); ?></th>
                                        <th><?php echo __('duration'); ?></th>
                                        <th><?php echo __('dates'); ?></th>
                                        <th><?php echo __('amount'); ?></th>
                                        <th><?php echo __('status'); ?></th>
                                        <th><?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscription_history as $subscription): ?>
                                        <tr>
                                            <td>
                                                <?php echo $subscription['lessons_per_month']; ?> <?php echo __('lessons_per_month'); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($subscription['circle_name']); ?></small>
                                            </td>
                                            <td><?php echo $subscription['duration_months']; ?> <?php echo $subscription['duration_months'] > 1 ? __('months') : __('month'); ?></td>
                                            <td>
                                                <?php echo date('Y-m-d', strtotime($subscription['start_date'])); ?><br>
                                                <small class="text-muted"><?php echo date('Y-m-d', strtotime($subscription['end_date'])); ?></small>
                                            </td>
                                            <td><?php echo $subscription['total_amount']; ?> <?php echo __('currency'); ?></td>
                                            <td>
                                                <?php if ($subscription['payment_status'] === 'paid'): ?>
                                                    <span class="badge bg-success"><?php echo __('paid'); ?></span>
                                                <?php elseif ($subscription['payment_status'] === 'pending'): ?>
                                                    <span class="badge bg-warning"><?php echo __('pending'); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><?php echo __($subscription['payment_status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($subscription['payment_status'] === 'pending' && $payment_enabled && $subscription['payment_method'] === 'paymob'): ?>
                                                    <form method="post" action="">
                                                        <input type="hidden" name="action" value="pay">
                                                        <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <?php echo __('pay_now'); ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <!-- Renew Subscription -->
            <?php
            // Only show renewal option if there's no active subscription or if the active subscription is expiring soon (5 days or less)
            $show_renewal = true;
            
            if ($active_subscription) {
                $end_date = new DateTime($active_subscription['end_date']);
                $today = new DateTime();
                $days_remaining = $today->diff($end_date)->days;
                
                if ($days_remaining > 5) {
                    $show_renewal = false;
                }
            }
            
            if ($show_renewal):
            ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo __('renew_subscription'); ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="renew">
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('select_subscription_plan'); ?></label>
                            <select class="form-select" name="plan_id" required>
                                <option value=""><?php echo __('select_plan'); ?></option>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?php echo $plan['id']; ?>" data-price="<?php echo $plan['price']; ?>">
                                        <?php echo $plan['lessons_per_month']; ?> <?php echo __('lessons_per_month'); ?> - 
                                        <?php echo $plan['price']; ?> <?php echo __('currency'); ?>/<?php echo __('month'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('subscription_duration'); ?></label>
                            <select class="form-select" name="duration_months" required id="durationSelect">
                                <option value="1" data-multiplier="1"><?php echo __('1_month'); ?></option>
                                <option value="3" data-multiplier="3"><?php echo __('3_months'); ?></option>
                                <option value="6" data-multiplier="6"><?php echo __('6_months'); ?></option>
                                <option value="12" data-multiplier="12"><?php echo __('12_months'); ?></option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('payment_method'); ?></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="payment_cash" value="cash" required checked>
                                <label class="form-check-label" for="payment_cash">
                                    <?php echo __('cash'); ?>
                                </label>
                            </div>
                            
                            <?php if ($payment_enabled): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="payment_paymob" value="paymob" required>
                                <label class="form-check-label" for="payment_paymob">
                                    <?php echo __('online_payment'); ?>
                                </label>
                            </div>
                            
                            <div class="mt-3 payment-type-container" style="display: none;">
                                <label class="form-label"><?php echo __('payment_type'); ?></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_type" 
                                           id="payment_type_card" value="card" checked>
                                    <label class="form-check-label" for="payment_type_card">
                                        <?php echo __('credit_card'); ?>
                                    </label>
                                </div>
                                
                                <?php if ($wallet_payment_enabled): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_type" 
                                           id="payment_type_wallet" value="wallet">
                                    <label class="form-check-label" for="payment_type_wallet">
                                        <?php echo __('mobile_wallet'); ?>
                                    </label>
                                </div>
                                
                                <div id="wallet_phone_container" class="mt-2" style="display: none;">
                                    <label class="form-label"><?php echo __('wallet_phone_number'); ?></label>
                                    <input type="text" class="form-control" name="wallet_phone" 
                                           placeholder="<?php echo __('enter_wallet_phone'); ?>">
                                    <small class="form-text text-muted">
                                        <?php echo __('wallet_phone_format_hint'); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo __('total_amount'); ?></label>
                            <div class="input-group">
                                <span class="form-control" id="totalAmount">0</span>
                                <span class="input-group-text"><?php echo __('currency'); ?></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <?php echo __('renew_subscription'); ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo __('subscription_info'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <p><?php echo __('renewal_not_available_yet'); ?></p>
                        <p><?php echo __('can_renew_when_5_days_remaining'); ?></p>
                        
                        <?php if ($active_subscription): ?>
                            <?php
                            $end_date = new DateTime($active_subscription['end_date']);
                            $today = new DateTime();
                            $days_remaining = $today->diff($end_date)->days;
                            ?>
                            <p>
                                <strong><?php echo __('current_subscription_ends'); ?>:</strong> 
                                <?php echo date('Y-m-d', strtotime($active_subscription['end_date'])); ?>
                                (<?php echo $days_remaining; ?> <?php echo __('days_remaining'); ?>)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide payment type options based on payment method selection
    const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
    const paymobOptions = document.getElementById('paymob_options');
    
    if (paymentMethodRadios && paymobOptions) {
        paymentMethodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'paymob') {
                    paymobOptions.style.display = 'block';
                } else {
                    paymobOptions.style.display = 'none';
                }
            });
        });
    }
    
    // Show/hide phone field based on payment type selection in renewal form
    const paymentTypeRadios = document.querySelectorAll('input[name="payment_type"]');
    const walletPhoneContainer = document.getElementById('wallet_phone_container');
    
    if (paymentTypeRadios && walletPhoneContainer) {
        paymentTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'wallet') {
                    walletPhoneContainer.style.display = 'block';
                    document.getElementById('wallet_phone').setAttribute('required', 'required');
                } else {
                    walletPhoneContainer.style.display = 'none';
                    document.getElementById('wallet_phone').removeAttribute('required');
                }
            });
        });
    }
    
    // Handle active subscription payment form
    const activePaymentWallet = document.getElementById('active_payment_wallet');
    const activePaymentCard = document.getElementById('active_payment_card');
    const activeWalletPhoneContainer = document.getElementById('active_wallet_phone_container');
    const activeCardButtonContainer = document.getElementById('active_card_button_container');
    
    if (activePaymentWallet && activePaymentCard && activeWalletPhoneContainer && activeCardButtonContainer) {
        activePaymentWallet.addEventListener('change', function() {
            if (this.checked) {
                activeWalletPhoneContainer.style.display = 'block';
                activeCardButtonContainer.style.display = 'none';
                document.getElementById('active_wallet_phone').setAttribute('required', 'required');
            }
        });
        
        activePaymentCard.addEventListener('change', function() {
            if (this.checked) {
                activeWalletPhoneContainer.style.display = 'none';
                activeCardButtonContainer.style.display = 'block';
                document.getElementById('active_wallet_phone').removeAttribute('required');
            }
        });
    }
});
</script>
