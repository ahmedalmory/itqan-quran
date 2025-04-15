<?php
/**
 * Payment Settings Management
 * 
 * This page allows administrators to manage payment gateway settings.
 */

// Start output buffering at the beginning of the file
ob_start();

// Include necessary files
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
require_once '../includes/debug_logger.php';

// Check if user is logged in and is an admin
requireRole('super_admin');

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $secretKey = $_POST['paymob_secret_key'] ?? '';
        $publicKey = $_POST['paymob_public_key'] ?? '';
        $apiKey = $_POST['paymob_api_key'] ?? '';
        $integrationId = $_POST['paymob_integration_id'] ?? '';
        $walletIntegrationId = $_POST['paymob_wallet_integration_id'] ?? '';
        $iframeId = $_POST['paymob_iframe_id'] ?? '';
        $hmacSecret = $_POST['paymob_hmac_secret'] ?? '';
        $callbackUrl = $_POST['paymob_callback_url'] ?? '';
        $redirectUrl = $_POST['paymob_redirect_url'] ?? '';
        $currency = $_POST['payment_currency'] ?? 'EGP';
        $enabled = isset($_POST['payment_enabled']) ? '1' : '0';
        $walletEnabled = isset($_POST['wallet_payment_enabled']) ? '1' : '0';
        $debugMode = isset($_POST['debug_mode']) ? '1' : '0';
        
        // Validate integration IDs are numeric
        if (!empty($integrationId) && !is_numeric($integrationId)) {
            throw new Exception("معرف التكامل للبطاقات يجب أن يكون رقمًا صحيحًا.");
        }
        
        if (!empty($walletIntegrationId) && !is_numeric($walletIntegrationId)) {
            throw new Exception("معرف التكامل للمحفظة الإلكترونية يجب أن يكون رقمًا صحيحًا.");
        } else {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update settings
            $settings = [
                'paymob_secret_key' => $secretKey,
                'paymob_public_key' => $publicKey,
                'paymob_api_key' => $apiKey,
                'paymob_integration_id' => $integrationId,
                'paymob_wallet_integration_id' => $walletIntegrationId,
                'paymob_iframe_id' => $iframeId,
                'paymob_hmac_secret' => $hmacSecret,
                'paymob_callback_url' => $callbackUrl,
                'paymob_redirect_url' => $redirectUrl,
                'payment_currency' => $currency,
                'payment_enabled' => $enabled,
                'wallet_payment_enabled' => $walletEnabled,
                'debug_mode' => $debugMode
            ];
            
            // Log settings for debugging
            debug_log("Saving payment settings", 'info', $settings);
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO payment_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $pdo->commit();
            $message = 'تم تحديث إعدادات الدفع بنجاح';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'خطأ في تحديث إعدادات الدفع: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM payment_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Log current settings
debug_log("Current payment settings", 'info', [
    'api_key' => !empty($settings['paymob_api_key']) ? 'Set' : 'Not set',
    'integration_id' => $settings['paymob_integration_id'] ?? 'Not set',
    'wallet_integration_id' => $settings['paymob_wallet_integration_id'] ?? 'Not set',
    'iframe_id' => $settings['paymob_iframe_id'] ?? 'Not set',
    'hmac_secret' => !empty($settings['paymob_hmac_secret']) ? 'Set' : 'Not set',
    'callback_url' => $settings['paymob_callback_url'] ?? 'Not set',
    'redirect_url' => $settings['paymob_redirect_url'] ?? 'Not set',
    'currency' => $settings['payment_currency'] ?? 'Not set',
    'enabled' => $settings['payment_enabled'] ?? 'Not set',
    'wallet_enabled' => $settings['wallet_payment_enabled'] ?? 'Not set',
    'debug_mode' => $settings['debug_mode'] ?? 'Not set'
]);

// Set default values if not set
$settings['paymob_api_key'] = $settings['paymob_api_key'] ?? '';
$settings['paymob_integration_id'] = $settings['paymob_integration_id'] ?? '';
$settings['paymob_wallet_integration_id'] = $settings['paymob_wallet_integration_id'] ?? '';
$settings['paymob_iframe_id'] = $settings['paymob_iframe_id'] ?? '';
$settings['paymob_hmac_secret'] = $settings['paymob_hmac_secret'] ?? '';
$settings['paymob_callback_url'] = $settings['paymob_callback_url'] ?? '';
$settings['paymob_redirect_url'] = $settings['paymob_redirect_url'] ?? '';
$settings['payment_currency'] = $settings['payment_currency'] ?? 'EGP';
$settings['payment_enabled'] = $settings['payment_enabled'] ?? '0';
$settings['wallet_payment_enabled'] = $settings['wallet_payment_enabled'] ?? '0';
$settings['debug_mode'] = $settings['debug_mode'] ?? '0';

// Page title
$pageTitle = 'إعدادات الدفع';

$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2);

?>

<h2>إعدادات الدفع</h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">إعدادات بوابة الدفع Paymob</h5>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="payment_enabled" name="payment_enabled" <?php echo $settings['payment_enabled'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="payment_enabled">تفعيل الدفع الإلكتروني</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="wallet_payment_enabled" name="wallet_payment_enabled" <?php echo $settings['wallet_payment_enabled'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="wallet_payment_enabled">تفعيل الدفع بالمحفظة الإلكترونية</label>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="debug_mode" name="debug_mode" <?php echo $settings['debug_mode'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="debug_mode">تفعيل وضع التصحيح</label>
                        <div class="form-text">يقوم بتسجيل المزيد من المعلومات التفصيلية للمساعدة في تشخيص المشكلات.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="payment_currency" class="form-label">العملة</label>
                    <select class="form-select" id="payment_currency" name="payment_currency">
                        <option value="EGP" <?php echo $settings['payment_currency'] === 'EGP' ? 'selected' : ''; ?>>جنيه مصري (EGP)</option>
                        <option value="USD" <?php echo $settings['payment_currency'] === 'USD' ? 'selected' : ''; ?>>دولار أمريكي (USD)</option>
                        <option value="SAR" <?php echo $settings['payment_currency'] === 'SAR' ? 'selected' : ''; ?>>ريال سعودي (SAR)</option>
                        <option value="AED" <?php echo $settings['payment_currency'] === 'AED' ? 'selected' : ''; ?>>درهم إماراتي (AED)</option>
                    </select>
                </div>
            </div>
            
            <h5 class="mt-4 mb-3">بيانات اعتماد Paymob API</h5>
            
            <div class="mb-3">
                <label for="paymob_secret_key" class="form-label">المفتاح السري (Secret Key)</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_secret_key" name="paymob_secret_key" value="<?php echo htmlspecialchars($settings['paymob_secret_key'] ?? ''); ?>">
                <div class="form-text">المفتاح السري الخاص بك من لوحة تحكم Paymob. يستخدم للاتصالات من الخادم.</div>
            </div>
            
            <div class="mb-3">
                <label for="paymob_public_key" class="form-label">المفتاح العام (Public Key)</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_public_key" name="paymob_public_key" value="<?php echo htmlspecialchars($settings['paymob_public_key'] ?? ''); ?>">
                <div class="form-text">المفتاح العام الخاص بك من لوحة تحكم Paymob. يستخدم للاتصالات من العميل.</div>
            </div>
            
            <div class="mb-3">
                <label for="paymob_api_key" class="form-label">مفتاح API (للتوافق مع النظام القديم)</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_api_key" name="paymob_api_key" value="<?php echo htmlspecialchars($settings['paymob_api_key'] ?? ''); ?>">
                <div class="form-text">مفتاح API الخاص بك من لوحة تحكم Paymob (للتوافق مع النظام القديم).</div>
            </div>
            
            <div class="mb-3">
                <label for="paymob_integration_id" class="form-label">معرف التكامل للبطاقات (للتوافق مع النظام القديم)</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_integration_id" name="paymob_integration_id" value="<?php echo htmlspecialchars($settings['paymob_integration_id'] ?? ''); ?>">
                <div class="form-text">معرف التكامل الخاص بك من Paymob للدفع بالبطاقات (للتوافق مع النظام القديم).</div>
            </div>
            
            <div class="mb-3">
                <label for="paymob_wallet_integration_id" class="form-label">معرف التكامل للمحفظة الإلكترونية</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_wallet_integration_id" name="paymob_wallet_integration_id" value="<?php echo htmlspecialchars($settings['paymob_wallet_integration_id'] ?? ''); ?>">
                <div class="form-text">معرف التكامل الخاص بك من Paymob للدفع بالمحفظة الإلكترونية. يجب أن يكون رقمًا صحيحًا.</div>
            </div>
            
            <div class="mb-3">
                <label for="paymob_iframe_id" class="form-label">معرف Iframe</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_iframe_id" name="paymob_iframe_id" value="<?php echo htmlspecialchars($settings['paymob_iframe_id'] ?? ''); ?>">
                <div class="form-text">معرف iframe الخاص بنموذج الدفع.</div>
            </div>
            
            <div class="mb-3">
                <label for="paymob_hmac_secret" class="form-label">كلمة سر HMAC</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_hmac_secret" name="paymob_hmac_secret" value="<?php echo htmlspecialchars($settings['paymob_hmac_secret'] ?? ''); ?>">
                <div class="form-text">كلمة سر HMAC الخاصة بك للتحقق من المعاملات.</div>
            </div>
            
            <div class="mb-3">
                <label for="paymob_callback_url" class="form-label">رابط Callback</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_callback_url" name="paymob_callback_url" readonly value="<?php echo htmlspecialchars($baseUrl . '/paymob_callback.php'); ?>">
                <div class="form-text">قم بتعيين هذا الرابط في لوحة تحكم Paymob كرابط callback للمعاملات.</div>
            </div>
            
            <div class="mb-3">
                <label for="paymob_redirect_url" class="form-label">رابط Redirect</label>
                <input type="text" class="form-control" dir="ltr" id="paymob_redirect_url" name="paymob_redirect_url" readonly value="<?php echo htmlspecialchars($baseUrl . '/payment_redirect.php'); ?>">
                <div class="form-text">رابط Redirect الخاص بك.</div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> حفظ الإعدادات
            </button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">تعليمات التكامل مع Paymob</h5>
    </div>
    <div class="card-body">
        <h5>كيفية إعداد تكامل Paymob باستخدام النظام الجديد (Intention API):</h5>
        <ol>
            <li>قم بإنشاء حساب على <a href="https://paymob.com" target="_blank">Paymob</a> إذا لم يكن لديك حساب.</li>
            <li>قم بتسجيل الدخول إلى لوحة تحكم Paymob وانتقل إلى "Developers" > "API Keys".</li>
            <li>انسخ المفتاح السري (Secret Key) والصقه في حقل "المفتاح السري" أعلاه.</li>
            <li>انسخ المفتاح العام (Public Key) والصقه في حقل "المفتاح العام" أعلاه.</li>
            <li>اذهب إلى "Developers" > "Integration" وقم بإنشاء تكامل جديد للمحفظة الإلكترونية إذا لم يكن لديك واحد.</li>
            <li>انسخ معرف التكامل والصقه في حقل "معرف التكامل للمحفظة الإلكترونية" أعلاه.</li>
            <li>اذهب إلى "Developers" > "Iframe" وقم بإنشاء iframe جديد إذا لم يكن لديك واحد.</li>
            <li>انسخ معرف Iframe والصقه في حقل "معرف Iframe" أعلاه.</li>
            <li>اذهب إلى "Developers" > "HMAC" وانسخ كلمة سر HMAC الخاصة بك.</li>
            <li>الصق كلمة سر HMAC في حقل "كلمة سر HMAC" أعلاه.</li>
            <li>في لوحة تحكم Paymob، قم بتعيين رابط callback إلى الرابط المعروض أعلاه.</li>
            <li>احفظ الإعدادات واختبر التكامل.</li>
        </ol>
        
        <h5 class="mt-4">ملاحظات هامة:</h5>
        <ul>
            <li>النظام الآن يستخدم واجهة برمجة التطبيقات الجديدة (Intention API) من باي موب.</li>
            <li>المفتاح السري (Secret Key) والمفتاح العام (Public Key) هما المفتاحان الرئيسيان المطلوبان للنظام الجديد.</li>
            <li>للدفع بالمحفظة الإلكترونية، يجب تفعيل خيار "تفعيل الدفع بالمحفظة الإلكترونية" أعلاه.</li>
            <li>تأكد من إدخال معرف التكامل الصحيح للمحفظة الإلكترونية.</li>
            <li>في حالة وجود مشكلات في الدفع، قم بتفعيل وضع التصحيح للحصول على معلومات أكثر تفصيلاً.</li>
            <li>تأكد من أن رقم الهاتف المستخدم للدفع بالمحفظة الإلكترونية هو نفس الرقم المسجل في المحفظة.</li>
        </ul>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="card-title mb-0">اختبار الاتصال بـ Paymob</h5>
    </div>
    <div class="card-body">
        <p>يمكنك اختبار الاتصال بـ Paymob للتأكد من صحة الإعدادات.</p>
        <button type="button" class="btn btn-warning" id="test-connection">
            <i class="fas fa-plug me-1"></i> اختبار الاتصال
        </button>
        <div id="test-result" class="mt-3"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // اختبار الاتصال بـ Paymob
    document.getElementById('test-connection').addEventListener('click', function() {
        const resultDiv = document.getElementById('test-result');
        resultDiv.innerHTML = '<div class="alert alert-info">جاري الاختبار...</div>';
        
        fetch('test_paymob_connection.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success">تم الاتصال بنجاح! ' + data.message + '</div>';
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger">فشل الاتصال: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء الاختبار: ' + error.message + '</div>';
            });
    });
});
</script>
<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
