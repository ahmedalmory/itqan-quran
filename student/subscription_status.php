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

// Check if Paymob payment is enabled
$stmt = $pdo->prepare("SELECT setting_value FROM payment_settings WHERE setting_key = 'payment_enabled'");
$stmt->execute();
$payment_enabled = $stmt->fetchColumn() === '1';

// Get all payment settings for debugging
$stmt = $pdo->query("SELECT setting_key, setting_value FROM payment_settings");
$payment_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
debug_log("Payment settings", 'info', $payment_settings);

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
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get available subscription plans for renewal
$stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY lessons_per_month ASC");
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate days until expiration if subscription exists
$days_until_expiration = 0;
$show_renewal_notice = false;
$renewal_threshold = 5; // Show renewal notice 5 days before expiration

if ($subscription) {
    $end_date = new DateTime($subscription['end_date']);
    $today = new DateTime();
    $interval = $today->diff($end_date);
    $days_until_expiration = $interval->days;
    
    // If end date is in the past, days will be negative
    if ($end_date < $today) {
        $days_until_expiration = -$days_until_expiration;
    }
    
    // Show renewal notice if subscription expires within the threshold
    $show_renewal_notice = ($days_until_expiration >= 0 && $days_until_expiration <= $renewal_threshold);
}

// Handle subscription renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew') {
    $plan_id = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
    $duration_months = filter_input(INPUT_POST, 'duration_months', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    
    if (!$plan_id) {
        $error_message = __('please_select_subscription_plan');
    } elseif (!$duration_months || $duration_months < 1 || $duration_months > 12) {
        $error_message = __('invalid_subscription_duration');
    } elseif (!$payment_method || !in_array($payment_method, ['cash', 'paymob'])) {
        $error_message = __('please_select_payment_method');
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
            if ($subscription && strtotime($subscription['end_date']) > time()) {
                $start_date = date('Y-m-d', strtotime($subscription['end_date'] . ' +1 day'));
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
                // Get the payment type (card or wallet)
                $payment_type = filter_input(INPUT_POST, 'payment_type', FILTER_SANITIZE_STRING) ?: 'card';
                
                // Get user data for payment
                $userData = [
                    'first_name' => $_SESSION['user_name'] ?? '',
                    'last_name' => '',
                    'email' => $_SESSION['user_email'] ?? '',
                    'phone' => $_SESSION['user_phone'] ?? ''
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
                        
                        $userData['phone'] = $wallet_phone;
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
                    } else {
                        // Regular iframe redirect
                        header("Location: " . $paymentResult['iframe_url']);
                        exit();
                    }
                } else {
                    $error_message = $paymentResult['message'];
                    // Log the error for debugging
                    error_log("Payment processing error in student/subscription_status.php: " . $error_message);
                    if (function_exists('debug_log')) {
                        debug_log("Payment processing error", 'error', [
                            'subscription_id' => $subscription_id,
                            'message' => $error_message
                        ]);
                    }
                }
            } else {
                $success_message = __('subscription_renewed_successfully');
                header("Location: subscription_status.php?success=1");
                exit();
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = $e->getMessage();
        }
    }
}

// Check for success message in URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = __('subscription_renewed_successfully');
}

$pageTitle = __('subscription_status');

?>

<div class="container py-4">
    <h1 class="mb-4"><?php echo __('subscription_status'); ?></h1>
    
    <?php if ($subscription): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><?php echo __('current_subscription'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><?php echo __('plan'); ?>:</strong> 
                            <?php echo $subscription['lessons_per_month']; ?> <?php echo __('lessons_per_month'); ?>
                        </p>
                        <p><strong><?php echo __('start_date'); ?>:</strong> 
                            <?php echo formatDate($subscription['start_date']); ?>
                        </p>
                        <p><strong><?php echo __('end_date'); ?>:</strong> 
                            <?php echo formatDate($subscription['end_date']); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><?php echo __('status'); ?>:</strong> 
                            <?php if ($days_until_expiration >= 0): ?>
                                <span class="badge bg-success"><?php echo __('active'); ?></span>
                            <?php else: ?>
                                <span class="badge bg-danger"><?php echo __('expired'); ?></span>
                            <?php endif; ?>
                        </p>
                        <p><strong><?php echo __('payment_status'); ?>:</strong> 
                            <?php if ($subscription['payment_status'] == 'paid'): ?>
                                <span class="badge bg-success"><?php echo __('paid'); ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><?php echo __('pending'); ?></span>
                                <?php if ($payment_enabled && $subscription['payment_method'] === 'paymob'): ?>
                                    <form method="post" action="subscriptions.php" class="d-inline ms-2">
                                        <input type="hidden" name="action" value="pay">
                                        <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
                                        
                                        <!-- Payment type selection -->
                                        <div class="btn-group btn-group-sm mb-2">
                                            <input type="radio" class="btn-check" name="payment_type" id="active_payment_card" value="card" checked>
                                            <label class="btn btn-outline-primary" for="active_payment_card"><?php echo __('credit_card'); ?></label>
                                            
                                            <?php if (!empty($payment_settings['paymob_wallet_integration_id'])): ?>
                                            <input type="radio" class="btn-check" name="payment_type" id="active_payment_wallet" value="wallet">
                                            <label class="btn btn-outline-primary" for="active_payment_wallet"><?php echo __('electronic_wallet'); ?></label>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Wallet phone number field -->
                                        <div id="active_wallet_phone_container" class="mb-2" style="display: none;">
                                            <div class="input-group input-group-sm">
                                                <input type="tel" class="form-control" id="active_wallet_phone" name="wallet_phone" 
                                                       placeholder="01xxxxxxxxx" pattern="[0-9]{10,13}">
                                                <button type="submit" class="btn btn-primary">
                                                    <?php echo __('pay_now'); ?>
                                                </button>
                                            </div>
                                            <div class="form-text">
                                                <?php echo __('wallet_phone_number_help'); ?> (01xxxxxxxxx)
                                            </div>
                                        </div>
                                        
                                        <!-- Card payment button (shown by default) -->
                                        <div id="active_card_button_container">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <?php echo __('pay_now'); ?>
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                        <p><strong><?php echo __('days_remaining'); ?>:</strong> 
                            <?php if ($days_until_expiration >= 0): ?>
                                <?php echo $days_until_expiration; ?> <?php echo __('days'); ?>
                            <?php else: ?>
                                <span class="text-danger"><?php echo __('expired'); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($show_renewal_notice): ?>
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading"><?php echo __('subscription_expiring_soon'); ?></h4>
            <p><?php echo sprintf(__('subscription_expiry_notice'), $days_until_expiration); ?></p>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><?php echo __('renew_subscription'); ?></h5>
            </div>
            <div class="card-body">
                <form action="subscription_status.php" method="post">
                    <input type="hidden" name="action" value="renew">
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('select_subscription_plan'); ?></label>
                        <?php foreach ($plans as $plan): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="plan_id" 
                                       id="plan_<?php echo $plan['id']; ?>" 
                                       value="<?php echo $plan['id']; ?>" required
                                       <?php echo ($plan['id'] == $subscription['plan_id']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="plan_<?php echo $plan['id']; ?>">
                                    <?php echo $plan['lessons_per_month']; ?> <?php echo __('lessons_per_month'); ?>
                                    (<?php echo $plan['price']; ?> <?php echo __('currency'); ?>/<?php echo __('month'); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duration_months" class="form-label"><?php echo __('subscription_duration'); ?></label>
                        <select class="form-select" id="duration_months" name="duration_months" required>
                            <option value="1">1 <?php echo __('month'); ?></option>
                            <option value="3">3 <?php echo __('months'); ?></option>
                            <option value="6">6 <?php echo __('months'); ?></option>
                            <option value="12">12 <?php echo __('months'); ?></option>
                        </select>
                    </div>
                    
                    <?php if ($payment_enabled): ?>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payment_method'); ?></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="payment_cash" value="cash" required checked>
                            <label class="form-check-label" for="payment_cash">
                                <?php echo __('cash_payment'); ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="payment_paymob" value="paymob" required>
                            <label class="form-check-label" for="payment_paymob">
                                <?php echo __('online_payment'); ?>
                            </label>
                        </div>
                        
                        <div id="paymob_options" class="mt-3 ps-4" style="display: none;">
                            <label class="form-label"><?php echo __('payment_type'); ?></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_type" 
                                       id="payment_type_card" value="card" checked>
                                <label class="form-check-label" for="payment_type_card">
                                    <?php echo __('credit_card'); ?>
                                </label>
                            </div>
                            <?php if (!empty($payment_settings['paymob_wallet_integration_id'])): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_type" 
                                       id="payment_type_wallet" value="wallet">
                                <label class="form-check-label" for="payment_type_wallet">
                                    <?php echo __('electronic_wallet'); ?>
                                </label>
                            </div>
                            
                            <!-- Phone number field for wallet payment -->
                            <div id="wallet_phone_container" class="mt-2" style="display: none;">
                                <label for="wallet_phone" class="form-label"><?php echo __('wallet_phone_number'); ?></label>
                                <input type="tel" class="form-control" id="wallet_phone" name="wallet_phone" 
                                       placeholder="01xxxxxxxxx" 
                                       pattern="[0-9]{10,13}">
                                <div class="form-text">
                                    <?php echo __('wallet_phone_number_help'); ?> (01xxxxxxxxx)
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="payment_method" value="cash">
                    <?php endif; ?>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <?php echo __('renew_subscription'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            <?php echo __('no_active_subscription'); ?>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><?php echo __('subscribe_now'); ?></h5>
            </div>
            <div class="card-body">
                <p><?php echo __('subscription_benefits'); ?></p>
                <a href="subscriptions.php" class="btn btn-primary"><?php echo __('view_subscription_plans'); ?></a>
            </div>
        </div>
    <?php endif; ?>
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
    
    // Show/hide phone field based on payment type selection
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
