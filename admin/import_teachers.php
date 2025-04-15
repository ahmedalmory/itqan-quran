<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';



$error = '';
$success = '';

function importTeachersCsv($tempFile) {
    global $pdo, $error, $success;
    
    try {
        // زيادة حد الذاكرة والوقت للعمليات الكبيرة
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300);
        
        // Open file with UTF-8 encoding
        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            throw new Exception('لا يمكن قراءة الملف');
        }

        // Set UTF-8 encoding for file reading
        stream_filter_append($handle, 'convert.iconv.UTF-8/UTF-8//TRANSLIT');

        // Read headers
        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new Exception('الملف فارغ');
        }

        // تنظيف الرؤوس
        $headers = array_map(function($header) {
            $header = trim($header);
            $header = str_replace(["\xEF\xBB\xBF", "\r", "\n", "\t"], '', $header);
            return $header;
        }, $headers);

        // التحقق من الرؤوس المطلوبة
        $requiredHeaders = ['Name', 'Email', 'Age', 'Halaqa', 'Edara', 'Mobile', 'Country', 'Ginder'];
        $missingHeaders = array_diff($requiredHeaders, $headers);
        
        if (!empty($missingHeaders)) {
            throw new Exception(sprintf(
                "الأعمدة التالية مفقودة: %s\nالأعمدة الموجودة في الملف: %s",
                implode(', ', $missingHeaders),
                implode(', ', $headers)
            ));
        }

        // Start transaction
        $pdo->beginTransaction();
        
        $teacherCount = 0;
        $batchSize = 100;
        $currentBatch = 0;
        
        // تجهيز الاستعلامات مسبقاً
        $checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $insertUserStmt = $pdo->prepare("
            INSERT INTO users (name, email, password, phone, age, gender, role, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'teacher', NOW())
        ");
        $updateCircleStmt = $pdo->prepare("
            UPDATE study_circles SET teacher_id = ? WHERE name = ?
        ");

        // Process all rows
        while (($data = fgetcsv($handle)) !== FALSE) {
            // تخطي الصفوف الفارغة
            if (empty(array_filter($data))) {
                continue;
            }

            // إنشاء مصفوفة مترابطة
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = isset($data[$index]) ? $data[$index] : '';
            }
            
            $name = $rowData['Name'];
            $email = $rowData['Email'];
            $age = $rowData['Age'];
            $halaqaName = $rowData['Halaqa'];
            $mobile = $rowData['Mobile'];
            $gender = $rowData['Ginder'] === 'ذكر' ? 'male' : 'female';

            // Check if teacher already exists
            $checkUserStmt->execute([$email]);
            $teacherId = $checkUserStmt->fetchColumn();
            
            if (!$teacherId) {
                // Add new teacher
                $password = '1234567';
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $insertUserStmt->execute([
                    $name,
                    $email,
                    $hashedPassword,
                    $mobile,
                    $age,
                    $gender
                ]);
                
                $teacherId = $pdo->lastInsertId();
            }

            // Update study circle with teacher
            $updateCircleStmt->execute([$teacherId, $halaqaName]);
            
            $teacherCount++;
            $currentBatch++;
            
            // Commit in batches
            if ($currentBatch >= $batchSize) {
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                $pdo->beginTransaction();
                $currentBatch = 0;
                
                echo "<div style='background:#eee;padding:5px;margin:5px;'>تم معالجة $teacherCount معلم...</div>";
                ob_flush();
                flush();
            }
        }

        // Final commit
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        $success = sprintf("تم استيراد %d معلم بنجاح", $teacherCount);
        
        // Clear temporary file
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'خطأ: ' . $e->getMessage();
        error_log('CSV Import Error: ' . $e->getMessage());
    } finally {
        if (isset($handle) && is_resource($handle)) {
            fclose($handle);
        }
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file']['name'])) {
    try {
        $file = $_FILES['csv_file'];
        
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('حجم الملف كبير جداً. الحد الأقصى هو 5 ميجابايت');
        }

        // Check file type
        if ($file['type'] !== 'text/csv' && $file['type'] !== 'application/vnd.ms-excel') {
            throw new Exception('يرجى تحميل ملف CSV فقط');
        }

        // Move file to temp directory
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/' . uniqid('csv_import_') . '.csv';
        
        if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
            throw new Exception('فشل في نسخ الملف المؤقت');
        }
        
        importTeachersCsv($tempFile);
        
    } catch (Exception $e) {
        $error = 'خطأ: ' . $e->getMessage();
        error_log('CSV Import Error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد معلمي الحلقات</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h1>استيراد معلمي الحلقات</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="csv_file">اختر ملف CSV:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </div>
            
            <button type="submit">استيراد</button>
        </form>
    </div>
</body>
</html>