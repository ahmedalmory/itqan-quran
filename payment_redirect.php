<?php
/**
 * Paymob Payment Redirect Handler
 * 
 * This file handles the redirection from Paymob after payment processing
 */

// تضمين ملفات النظام الأساسية
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/classes/PaymobPayment.php';

// بدء الجلسة إذا لم تكن قد بدأت بالفعل
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تسجيل الأخطاء في ملف
function log_error($message) {
    $logDir = dirname(__FILE__) . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = "[$timestamp] [ERROR] $message" . PHP_EOL;
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
}

// تسجيل المعلومات في ملف
function log_info($message, $data = []) {
    $logDir = dirname(__FILE__) . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/debug_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $dataString = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[$timestamp] [INFO] $message" . (!empty($dataString) ? " | $dataString" : "") . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // تسجيل بيانات إعادة التوجيه
    log_info('Received Paymob redirect', [
        'get' => $_GET,
        'session' => isset($_SESSION['user_id']) ? "User ID: " . $_SESSION['user_id'] : "No user session"
    ]);
    
    // استخدام اتصال قاعدة البيانات الموجود
    global $pdo, $conn;
    
    // إذا لم يكن $pdo موجودًا، قم بإنشائه
    if (!isset($pdo)) {
        // استخدام الثوابت المعرفة في ملف database.php
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
    
    // إنشاء كائن معالجة الدفع
    $payment = new PaymobPayment($pdo);
    
    // الحصول على معلمات إعادة التوجيه
    $success = isset($_GET['success']) && ($_GET['success'] === 'true' || $_GET['success'] === '1' || $_GET['success'] === true);
    $orderId = isset($_GET['order']) ? $_GET['order'] : null;
    $transactionId = isset($_GET['id']) ? $_GET['id'] : null;
    $merchantOrderId = isset($_GET['merchant_order_id']) ? $_GET['merchant_order_id'] : null;
    
    // استخراج معرف الاشتراك من معرف الطلب التاجر (إذا كان متاحًا)
    $subscriptionId = null;
    if ($merchantOrderId) {
        $parts = explode('_', $merchantOrderId);
        if (count($parts) > 0) {
            $subscriptionId = $parts[0];
        }
    }
    
    log_info('Payment redirect details', [
        'success' => $success,
        'order_id' => $orderId,
        'transaction_id' => $transactionId,
        'merchant_order_id' => $merchantOrderId,
        'subscription_id' => $subscriptionId
    ]);
    
    // البحث عن المعاملة في قاعدة البيانات
    $transaction = null;
    
    // البحث باستخدام معرف المعاملة
    if ($transactionId) {
        $stmt = $pdo->prepare("
            SELECT * FROM payment_transactions 
            WHERE paymob_transaction_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        
        // إذا لم يتم العثور على المعاملة، حاول البحث باستخدام حقل transaction_id
        if (!$transaction) {
            $stmt = $pdo->prepare("
                SELECT * FROM payment_transactions 
                WHERE transaction_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch();
        }
    }
    
    // إذا لم يتم العثور على المعاملة، حاول البحث باستخدام معرف الاشتراك
    if (!$transaction && $subscriptionId) {
        $stmt = $pdo->prepare("
            SELECT * FROM payment_transactions 
            WHERE subscription_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$subscriptionId]);
        $transaction = $stmt->fetch();
    }
    
    // إذا لم يتم العثور على المعاملة، حاول البحث باستخدام معرف الطلب
    if (!$transaction && $orderId) {
        $stmt = $pdo->prepare("
            SELECT * FROM payment_transactions 
            WHERE paymob_order_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $transaction = $stmt->fetch();
    }
    
    // إذا تم العثور على المعاملة، قم بتحديث حالتها
    if ($transaction) {
        log_info('Found transaction', [
            'transaction_id' => $transaction['id'],
            'subscription_id' => $transaction['subscription_id']
        ]);
        
        // تحديث حالة المعاملة
        $status = $success ? 'completed' : 'failed';
        $transactionDbId = $transaction['id'];
        
        try {
            $stmt = $pdo->prepare("
                UPDATE payment_transactions 
                SET status = ?, 
                    paymob_transaction_id = ?,
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $transactionId, $transactionDbId]);
            log_info('Updated transaction status', [
                'status' => $status,
                'paymob_transaction_id' => $transactionId,
                'db_id' => $transactionDbId
            ]);
        } catch (PDOException $e) {
            log_error('Error updating transaction status: ' . $e->getMessage());
        }
        
        // إذا كانت المعاملة ناجحة، قم بتحديث الاشتراك
        if ($success) {
            $subscriptionId = $transaction['subscription_id'];
            
            // تحديث حالة الاشتراك في جدول student_subscriptions
            try {
                $stmt = $pdo->prepare("
                    UPDATE student_subscriptions 
                    SET payment_status = 'paid', 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$subscriptionId]);
                
                log_info('Updated student_subscription status', [
                    'subscription_id' => $subscriptionId,
                    'payment_status' => 'paid'
                ]);
                
                // تحديث تاريخ انتهاء الاشتراك إذا لم يكن محددًا
                $stmt = $pdo->prepare("
                    UPDATE student_subscriptions 
                    SET end_date = DATE_ADD(NOW(), INTERVAL 1 MONTH) 
                    WHERE id = ? AND (end_date IS NULL OR end_date = '')
                ");
                $stmt->execute([$subscriptionId]);
            } catch (PDOException $e) {
                log_error('Error updating student_subscription: ' . $e->getMessage());
            }
        }
    } else {
        log_info('No transaction found for the payment', [
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'subscription_id' => $subscriptionId
        ]);
    }
    
    // تحديد عنوان URL للتوجيه
    $redirectUrl = null;
    
    // محاولة استخدام عنوان URL المخزن في الجلسة
    if (isset($_SESSION['paymob_success_url']) && $success) {
        $redirectUrl = $_SESSION['paymob_success_url'];
        unset($_SESSION['paymob_success_url']);
    } elseif (isset($_SESSION['paymob_cancel_url']) && !$success) {
        $redirectUrl = $_SESSION['paymob_cancel_url'];
        unset($_SESSION['paymob_cancel_url']);
    }
    
    // إذا لم يتم العثور على عنوان URL في الجلسة، استخدم الإعدادات
    if (!$redirectUrl) {
        $settingName = $success ? 'paymob_success_url' : 'paymob_cancel_url';
        $stmt = $pdo->prepare("SELECT setting_value FROM payment_settings WHERE setting_key = ?");
        $stmt->execute([$settingName]);
        $row = $stmt->fetch();
        
        if ($row) {
            $redirectUrl = $row['setting_value'];
        }
    }
    
    // إذا لم يتم العثور على عنوان URL، استخدم عنوان URL افتراضي
    if (!$redirectUrl) {
        $redirectUrl = $success ? '/student/index.php' : '/payment-failed.php';
    }
    
    // إضافة معلمات إضافية إلى عنوان URL
    $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
    $redirectUrl .= $separator . 'payment_status=' . ($success ? 'success' : 'failed');
    
    // إضافة معرف الاشتراك إذا كان متاحًا
    if ($subscriptionId) {
        $redirectUrl .= '&subscription_id=' . $subscriptionId;
    }
    
    log_info('Redirecting to', ['redirect_url' => $redirectUrl]);
    
    // إعادة التوجيه إلى عنوان URL
    header("Location: $redirectUrl");
    exit;
    
} catch (Exception $e) {
    // تسجيل الخطأ
    log_error('Error processing payment redirect: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    
    // عرض رسالة خطأ للمستخدم
    echo '<html><head><title>خطأ في معالجة الدفع</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<style>body{font-family:Arial,sans-serif;text-align:center;padding:20px;direction:rtl}';
    echo '.error-container{max-width:600px;margin:0 auto;padding:20px;border:1px solid #ddd;border-radius:5px}';
    echo 'h1{color:#e74c3c}p{margin:15px 0}a{display:inline-block;margin-top:20px;padding:10px 20px;background-color:#3498db;color:#fff;text-decoration:none;border-radius:5px}</style>';
    echo '</head><body>';
    echo '<div class="error-container">';
    echo '<h1>حدث خطأ أثناء معالجة الدفع</h1>';
    echo '<p>نعتذر عن هذا الخطأ. تم تسجيل المشكلة وسيتم حلها في أقرب وقت ممكن.</p>';
    echo '<p>يمكنك العودة إلى الصفحة الرئيسية أو المحاولة مرة أخرى لاحقًا.</p>';
    echo '<a href="index.php">العودة إلى الصفحة الرئيسية</a>';
    echo '</div></body></html>';
}
?>
