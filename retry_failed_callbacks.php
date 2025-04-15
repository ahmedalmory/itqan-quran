<?php
/**
 * Retry Failed Callbacks Script
 * 
 * This script retries processing failed callbacks stored in the database.
 * It can be run manually or set up as a cron job to automatically retry failed callbacks.
 */

// Include necessary files
require_once 'config/database.php';
require_once 'includes/classes/PaymobPayment.php';
require_once 'includes/debug_logger.php';

// Set a longer execution time for processing multiple callbacks
set_time_limit(300);

// Initialize payment handler
$paymobPayment = new PaymobPayment($pdo);

// Log script start
debug_log("Starting retry of failed callbacks", 'info');

// Check if the failed_callbacks table exists, create it if not
try {
    $pdo->query("SELECT 1 FROM failed_callbacks LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $pdo->exec("
        CREATE TABLE failed_callbacks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            callback_type VARCHAR(50) NOT NULL,
            callback_data TEXT NOT NULL,
            retry_count INT DEFAULT 0,
            last_retry DATETIME,
            created_at DATETIME NOT NULL,
            processed TINYINT(1) DEFAULT 0
        )
    ");
    debug_log("Created failed_callbacks table", 'info');
}

// Get unprocessed failed callbacks
$stmt = $pdo->prepare("
    SELECT * FROM failed_callbacks 
    WHERE processed = 0 AND (retry_count < 5 OR retry_count IS NULL)
    ORDER BY created_at ASC
");
$stmt->execute();
$failedCallbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCallbacks = count($failedCallbacks);
$successCount = 0;
$failCount = 0;

debug_log("Found {$totalCallbacks} failed callbacks to retry", 'info');

// Process each failed callback
foreach ($failedCallbacks as $callback) {
    debug_log("Retrying callback", 'info', [
        'id' => $callback['id'],
        'type' => $callback['callback_type'],
        'retry_count' => $callback['retry_count'] ?? 0
    ]);
    
    $callbackData = json_decode($callback['callback_data'], true);
    if (!$callbackData) {
        debug_log("Failed to decode callback data", 'error', [
            'id' => $callback['id']
        ]);
        $failCount++;
        continue;
    }
    
    $result = false;
    
    // Process based on callback type
    if ($callback['callback_type'] === 'paymob') {
        $result = $paymobPayment->processCallback($callbackData);
    }
    // Add other callback types as needed
    
    // Update the callback record
    $retryCount = ($callback['retry_count'] ?? 0) + 1;
    $processed = $result ? 1 : 0;
    
    $stmt = $pdo->prepare("
        UPDATE failed_callbacks 
        SET retry_count = ?, 
            last_retry = CURRENT_TIMESTAMP,
            processed = ?
        WHERE id = ?
    ");
    $stmt->execute([$retryCount, $processed, $callback['id']]);
    
    if ($result) {
        debug_log("Successfully processed callback on retry", 'info', [
            'id' => $callback['id']
        ]);
        $successCount++;
    } else {
        debug_log("Failed to process callback on retry", 'warning', [
            'id' => $callback['id'],
            'retry_count' => $retryCount
        ]);
        $failCount++;
    }
    
    // Add a small delay between processing callbacks
    usleep(500000); // 0.5 seconds
}

// Log summary
debug_log("Completed retry of failed callbacks", 'info', [
    'total' => $totalCallbacks,
    'success' => $successCount,
    'failed' => $failCount
]);

// Output results if run from command line
if (php_sapi_name() === 'cli') {
    echo "Completed retry of failed callbacks\n";
    echo "Total: {$totalCallbacks}\n";
    echo "Success: {$successCount}\n";
    echo "Failed: {$failCount}\n";
}
?>
