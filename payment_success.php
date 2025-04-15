<?php
/**
 * Payment Success Page
 * 
 * This page is displayed after a successful payment through Paymob.
 */

// Start output buffering
ob_start();

require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';
require_once 'includes/debug_logger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Get transaction ID and order ID from query parameters
$transaction_id = isset($_GET['transaction_id']) ? filter_input(INPUT_GET, 'transaction_id', FILTER_SANITIZE_STRING) : null;
$order_id = isset($_GET['order_id']) ? filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_STRING) : null;
$verified = isset($_GET['verified']) && $_GET['verified'] === 'true';

// Log the success page access
debug_log("Payment success page accessed", 'info', [
    'user_id' => $user_id,
    'transaction_id' => $transaction_id,
    'order_id' => $order_id,
    'verified' => $verified
]);

// Get transaction details if transaction ID is provided
$transaction = null;
$subscription = null;

if ($transaction_id) {
    $stmt = $pdo->prepare("
        SELECT pt.*, ss.* 
        FROM payment_transactions pt
        JOIN student_subscriptions ss ON pt.subscription_id = ss.id
        WHERE pt.id = ? AND ss.student_id = ?
    ");
    $stmt->execute([$transaction_id, $user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If no transaction found by ID, try to find by order ID
if (!$transaction && $order_id) {
    $stmt = $pdo->prepare("
        SELECT pt.*, ss.* 
        FROM payment_transactions pt
        JOIN student_subscriptions ss ON pt.subscription_id = ss.id
        WHERE pt.paymob_order_id = ? AND ss.student_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If still no transaction, get the latest transaction for the user
if (!$transaction) {
    $stmt = $pdo->prepare("
        SELECT pt.*, ss.* 
        FROM payment_transactions pt
        JOIN student_subscriptions ss ON pt.subscription_id = ss.id
        WHERE ss.student_id = ?
        ORDER BY pt.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If transaction was verified directly with Paymob or is still pending, update its status
if ($transaction && ($verified || $transaction['status'] === 'pending')) {
    // Update transaction status to success
    $stmt = $pdo->prepare("
        UPDATE payment_transactions 
        SET status = 'success', 
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$transaction['id']]);
    
    // Update subscription payment status
    $stmt = $pdo->prepare("
        UPDATE student_subscriptions 
        SET payment_status = 'paid', 
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$transaction['subscription_id']]);
    
    debug_log("Updated transaction and subscription status after successful payment", 'info', [
        'transaction_id' => $transaction['id'],
        'subscription_id' => $transaction['subscription_id'],
        'verified_directly' => $verified
    ]);
    
    // Refresh transaction data
    $stmt = $pdo->prepare("
        SELECT pt.*, ss.* 
        FROM payment_transactions pt
        JOIN student_subscriptions ss ON pt.subscription_id = ss.id
        WHERE pt.id = ?
    ");
    $stmt->execute([$transaction['id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = __('payment_successful');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h1 class="card-title mb-4"><?php echo __('payment_successful'); ?></h1>
                    <p class="card-text lead mb-4">
                        <?php echo __('thank_you_for_your_payment'); ?>
                    </p>
                    
                    <?php if ($transaction): ?>
                    <div class="alert alert-light text-start mb-4">
                        <h5><?php echo __('transaction_details'); ?></h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?php echo __('transaction_id'); ?>:</strong> <?php echo $transaction['id']; ?></p>
                                <p><strong><?php echo __('amount'); ?>:</strong> <?php echo $transaction['amount']; ?> <?php echo $transaction['currency']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo __('date'); ?>:</strong> <?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></p>
                                <p><strong><?php echo __('status'); ?>:</strong> <span class="badge bg-success"><?php echo __('paid'); ?></span></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <?php if ($role === 'student'): ?>
                            <a href="student/subscriptions.php" class="btn btn-primary btn-lg"><?php echo __('view_my_subscriptions'); ?></a>
                        <?php else: ?>
                            <a href="index.php" class="btn btn-primary btn-lg"><?php echo __('back_to_home'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
