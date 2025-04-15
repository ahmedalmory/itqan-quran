<?php
/**
 * Test Paymob Connection
 * 
 * This file tests the connection to Paymob API using the configured settings.
 */

// Include necessary files
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/debug_logger.php';
require_once '../includes/classes/PaymobPayment.php';

// Check if user is logged in and is an admin
requireRole('super_admin');

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Create PaymobPayment instance
    $paymobPayment = new PaymobPayment($pdo);
    
    // Test if Paymob is configured
    if (!$paymobPayment->isConfigured()) {
        echo json_encode([
            'success' => false,
            'message' => 'الدفع الإلكتروني غير مفعل أو الإعدادات غير مكتملة. يرجى التحقق من المفتاح السري والمفتاح العام ومعرف Iframe.'
        ]);
        exit;
    }
    
    // Test authentication token
    $authToken = $paymobPayment->testAuthToken();
    
    if (!$authToken) {
        echo json_encode([
            'success' => false,
            'message' => 'فشل الحصول على رمز المصادقة. تأكد من صحة المفتاح السري.'
        ]);
        exit;
    }
    
    // Test wallet integration if enabled
    $walletEnabled = false;
    $stmt = $pdo->prepare("SELECT setting_value FROM payment_settings WHERE setting_key = 'wallet_payment_enabled'");
    $stmt->execute();
    $walletEnabled = $stmt->fetchColumn() === '1';
    
    $walletStatus = '';
    if ($walletEnabled) {
        if ($paymobPayment->isWalletEnabled()) {
            $walletStatus = ' تم التحقق من إعدادات المحفظة الإلكترونية بنجاح.';
        } else {
            $walletStatus = ' تم تفعيل الدفع بالمحفظة الإلكترونية ولكن الإعدادات غير مكتملة.';
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'تم الاتصال بـ Paymob بنجاح وتم التحقق من رمز المصادقة باستخدام النظام الجديد (Intention API).' . $walletStatus
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء الاتصال بـ Paymob: ' . $e->getMessage()
    ]);
}
?> 