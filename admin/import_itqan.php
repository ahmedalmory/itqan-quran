<?php
session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
requireRole(['super_admin', 'department_admin']);

$error = '';
$success = '';

// تأكيد الاستيراد من المعاينة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import']) && isset($_SESSION['csv_file'])) {
    $tempFile = $_SESSION['csv_file'];
    
    if (!file_exists($tempFile)) {
        $error = 'خطأ: لم يتم العثور على الملف المؤقت';
    } else {
        importCsvFile($tempFile);
    }
}
// معالجة تحميل الملف للمعاينة
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file']['name'])) {
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

        // نسخ الملف إلى مجلد مؤقت
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/' . uniqid('csv_import_') . '.csv';
        
        if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
            throw new Exception('فشل في نسخ الملف المؤقت');
        }
        
        // حفظ مسار الملف المؤقت في الجلسة
        $_SESSION['csv_file'] = $tempFile;
        
        // Preview mode
        if (isset($_POST['preview'])) {
            previewCsvFile($tempFile);
        } else {
            // استيراد مباشر بدون معاينة
            importCsvFile($tempFile);
        }
    } catch (Exception $e) {
        $error = 'خطأ: ' . $e->getMessage();
        error_log('CSV Import Error: ' . $e->getMessage());
    }
}

function importCsvFile($tempFile) {
    global $pdo, $error, $success;
    try {
        // زيادة حد الذاكرة والوقت للعمليات الكبيرة
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300); // 5 دقائق
        
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
        $requiredHeaders = ['Name', 'Email', 'Age', 'Edara', 'Halaqa', 'Teacher', 'Country', 'Ginder', 'Mobile'];
        $missingHeaders = array_diff($requiredHeaders, $headers);
        
        if (!empty($missingHeaders)) {
            throw new Exception(sprintf(
                "الأعمدة التالية مفقودة: %s\nالأعمدة الموجودة في الملف: %s",
                implode(', ', $missingHeaders),
                implode(', ', $headers)
            ));
        }

        // تحسين الأداء عن طريق تخزين البيانات مؤقتاً
        $pdo->exec('SET unique_checks=0');
        $pdo->exec('SET foreign_key_checks=0');
        
        // Start transaction
        $pdo->beginTransaction();
        
        $studentCount = 0;
        $batchSize = 100; // معالجة البيانات على دفعات
        $currentBatch = 0;
        
        // Store added departments and circles to avoid duplicates
        $departments = [];
        $circles = [];
        
        // تجهيز الاستعلامات مسبقاً
        $checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $updateUserStmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, phone = ?, age = ?, gender = ?
            WHERE id = ?
        ");
        $insertUserStmt = $pdo->prepare("
            INSERT INTO users (name, email, password, phone, age, role, gender, created_at)
            VALUES (?, ?, ?, ?, ?, 'student', ?, NOW())
        ");
        $checkDeptStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
        $insertDeptStmt = $pdo->prepare("
            INSERT INTO departments (name, student_gender, work_saturday, work_sunday, work_monday, 
                                  work_tuesday, work_wednesday, created_at)
            VALUES (?, ?, 1, 1, 1, 1, 1, NOW())
        ");
        $checkCircleStmt = $pdo->prepare("
            SELECT id FROM study_circles 
            WHERE name = ? AND department_id = ?
        ");
        $insertCircleStmt = $pdo->prepare("
            INSERT INTO study_circles (name, department_id, age_from, age_to)
            VALUES (?, ?, 4, 18)
        ");
        $checkLinkStmt = $pdo->prepare("
            SELECT id FROM circle_students 
            WHERE circle_id = ? AND student_id = ?
        ");
        $insertLinkStmt = $pdo->prepare("
            INSERT INTO circle_students (circle_id, student_id, created_at)
            VALUES (?, ?, NOW())
        ");
        
        // Process all rows
        while (($data = fgetcsv($handle)) !== FALSE) {
            // تخطي الصفوف الفارغة
            if (empty(array_filter($data))) {
                continue;
            }

            // التحقق من تطابق عدد الأعمدة
            if (count($data) !== count($headers)) {
                throw new Exception(sprintf(
                    "عدد الأعمدة غير متطابق في السطر %d. متوقع: %d, موجود: %d",
                    $studentCount + 2,
                    count($headers),
                    count($data)
                ));
            }

            // تنظيف البيانات
            $data = array_map('trim', $data);
            
            // إنشاء مصفوفة مترابطة
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = isset($data[$index]) ? $data[$index] : '';
            }
            
            $name = $rowData['Name'];
            $email = $rowData['Email'];
            $age = $rowData['Age'];
            $departmentName = $rowData['Edara'];
            $halaqaName = $rowData['Halaqa'];
            $teacherName = $rowData['Teacher'];
            $country = $rowData['Country'];
            $gender = $rowData['Ginder'];
            $mobile = $rowData['Mobile'];

            // تنظيف اسم المعلم
            $teacherName = trim($teacherName);

            // تنظيف اسم الإدارة
            $departmentName = trim($departmentName);
            
            // تنظيف اسم الحلقة
            $halaqaName = trim($halaqaName);

            // Check required data
            if (empty($name) || empty($email) || empty($mobile)) {
                throw new Exception(sprintf(
                    "بيانات إلزامية مفقودة. الاسم: %s، البريد: %s، الجوال: %s",
                    $name ?: 'فارغ',
                    $email ?: 'فارغ',
                    $mobile ?: 'فارغ'
                ));
            }

            /* Check email validity
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception(sprintf(
                    "البريد الإلكتروني غير صالح: %s",
                    $email
                ));
            }*/

            // تنظيف رقم الجوال
            $mobile = preg_replace('/[^0-9]/', '', $mobile);
            if (strlen($mobile) > 19) {
                throw new Exception(sprintf(
                    "رقم الجوال غير صالح: %s",
                    $mobile
                ));
            }

            // Check if email already exists
            $checkUserStmt->execute([$email]);
            $existingUserId = $checkUserStmt->fetchColumn();
            
            if ($existingUserId) {
                // تحديث بيانات المستخدم الموجود
                $updateUserStmt->execute([
                    $name,
                    $mobile,
                    $age,
                    $gender === 'ذكر' ? 'male' : 'female',
                    $existingUserId
                ]);
                
                $userId = $existingUserId;
            } else {
                // استخدام كلمة مرور ثابتة لجميع الطلاب
                $password = '1234567';
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Add user
                $insertUserStmt->execute([
                    $name,
                    $email,
                    $hashedPassword,
                    $mobile,
                    $age,
                    $gender === 'ذكر' ? 'male' : 'female'
                ]);

                $userId = $pdo->lastInsertId();
            }

            // Find or create department
            if (!isset($departments[$departmentName])) {
                $checkDeptStmt->execute([$departmentName]);
                $departmentId = $checkDeptStmt->fetchColumn();

                if (!$departmentId) {
                    // Create new department
                    $insertDeptStmt->execute([
                        $departmentName,
                        $gender === 'ذكر' ? 'male' : 'female'
                    ]);
                    $departmentId = $pdo->lastInsertId();
                }
                $departments[$departmentName] = $departmentId;
            }

            // تكوين مفتاح فريد للحلقة
            $circleKey = $departmentName . '|' . $halaqaName;

            // Find or create study circle
            if (!isset($circles[$circleKey])) {
                $checkCircleStmt->execute([$halaqaName, $departments[$departmentName]]);
                $circleId = $checkCircleStmt->fetchColumn();

                if (!$circleId) {
                    // Create new study circle
                    $insertCircleStmt->execute([
                        $halaqaName,
                        $departments[$departmentName]
                    ]);
                    $circleId = $pdo->lastInsertId();
                }
                $circles[$circleKey] = $circleId;
            }

            // تحقق من وجود الطالب في الحلقة
            $checkLinkStmt->execute([$circles[$departmentName . '|' . $halaqaName], $userId]);
            $existingLink = $checkLinkStmt->fetchColumn();
            
            // ربط الطالب بالحلقة إذا لم يكن مرتبطاً بها
            if (!$existingLink) {
                $insertLinkStmt->execute([$circles[$departmentName . '|' . $halaqaName], $userId]);
                $studentCount++;
            }
            
            // تنفيذ الالتزام على دفعات لتحسين الأداء
            $currentBatch++;
            if ($currentBatch >= $batchSize) {
                // إنهاء المعاملة الحالية وبدء معاملة جديدة
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                $pdo->beginTransaction();
                $currentBatch = 0;
                
                // عرض تقدم العملية
                echo "<div style='background:#eee;padding:5px;margin:5px;'>تم معالجة $studentCount سجل...</div>";
                ob_flush();
                flush();
            }
        }

        // إعادة الإعدادات إلى وضعها الطبيعي
        $pdo->exec('SET unique_checks=1');
        $pdo->exec('SET foreign_key_checks=1');
        
        // Commit transaction
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        $success = sprintf("تم استيراد %d طالب بنجاح مع إنشاء الإدارات والحلقات اللازمة", $studentCount);
        
        // Clear temporary file after successful import
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        // Clear session data
        unset($_SESSION['csv_file']);
        unset($_SESSION['csv_preview']);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // إعادة الإعدادات إلى وضعها الطبيعي في حالة الخطأ
        if (isset($pdo)) {
            $pdo->exec('SET unique_checks=1');
            $pdo->exec('SET foreign_key_checks=1');
        }
        
        $error = 'خطأ: ' . $e->getMessage();
        error_log('CSV Import Error: ' . $e->getMessage());
    } finally {
        if (isset($handle) && is_resource($handle)) {
            fclose($handle);
        }
    }
}

function previewCsvFile($tempFile) {
    global $pdo, $error, $success;
    try {
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
        $requiredHeaders = ['Name', 'Email', 'Age', 'Edara', 'Halaqa', 'Teacher', 'Country', 'Ginder', 'Mobile'];
        $missingHeaders = array_diff($requiredHeaders, $headers);
        
        if (!empty($missingHeaders)) {
            throw new Exception(sprintf(
                "الأعمدة التالية مفقودة: %s\nالأعمدة الموجودة في الملف: %s",
                implode(', ', $missingHeaders),
                implode(', ', $headers)
            ));
        }

        // Preview mode
        $_SESSION['csv_preview'] = [];
        $previewRows = 5;
        $rowCount = 0;
        
        while (($data = fgetcsv($handle)) !== FALSE && $rowCount < $previewRows) {
            // تخطي الصفوف الفارغة
            if (empty(array_filter($data))) {
                continue;
            }

            // التحقق من تطابق عدد الأعمدة
            if (count($data) !== count($headers)) {
                throw new Exception(sprintf(
                    "عدد الأعمدة غير متطابق في السطر %d. متوقع: %d, موجود: %d",
                    $rowCount + 2,
                    count($headers),
                    count($data)
                ));
            }

            // تنظيف البيانات
            $data = array_map('trim', $data);
            
            // إنشاء مصفوفة مترابطة مع التحقق من صحة البيانات
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = isset($data[$index]) ? $data[$index] : '';
            }
            
            $_SESSION['csv_preview'][] = $rowData;
            $rowCount++;
        }
        
        if ($rowCount === 0) {
            throw new Exception("لم يتم العثور على بيانات صالحة في الملف");
        }
        
        fclose($handle);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?preview=1');
        exit;
    } catch (Exception $e) {
        $error = 'خطأ: ' . $e->getMessage();
        error_log('CSV Import Error: ' . $e->getMessage());
    } finally {
        if (isset($handle) && is_resource($handle)) {
            fclose($handle);
        }
    }
}

// Clear preview data if not in preview mode
if (!isset($_GET['preview'])) {
    unset($_SESSION['csv_preview']);
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد طلاب إتقان</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #28a745;
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .btn-success {
            background-color: #28a745;
            border: none;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">استيراد طلاب إتقان</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['csv_preview'])): ?>
                            <h4>معاينة البيانات</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <?php foreach (array_keys($_SESSION['csv_preview'][0]) as $header): ?>
                                                <th><?php echo htmlspecialchars($header); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($_SESSION['csv_preview'] as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $value): ?>
                                                    <td><?php echo htmlspecialchars($value); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="confirm_import" value="1">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-file-import"></i> تأكيد الاستيراد
                                </button>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> إلغاء
                                </a>
                            </form>
                        <?php else: ?>
                            <form method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="csv_file">اختر ملف CSV</label>
                                    <input type="file" class="form-control-file" id="csv_file" name="csv_file" accept=".csv" required>
                                    <small class="form-text text-muted">
                                        يجب أن يحتوي الملف على الأعمدة التالية: الاسم، البريد الإلكتروني، العمر، الإدارة، الحلقة، المعلم، الدولة، الجنس، الجوال
                                    </small>
                                </div>
                                <button type="submit" name="preview" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> معاينة البيانات
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
