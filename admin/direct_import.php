<?php
// بدء جلسة الجلسة
session_start();

// التحقق من تسجيل الدخول
require_once '../config/auth.php';
requireRole(['super_admin', 'department_admin']);

// استيراد ملفات التكوين
require_once '../config/database.php';
require_once '../includes/functions.php';

// تهيئة متغيرات
$departments = [];
$errors = [];
$success = '';
$importedStudents = 0;
$createdCircles = 0;

// الحصول على قائمة الإدارات
$query = "SELECT * FROM departments ORDER BY name";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}

// تحقق مما إذا تم تقديم النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من وجود ملف
    if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'لم يتم تحميل الملف بشكل صحيح';
    } else {
        // التحقق من نوع الملف
        $fileType = mime_content_type($_FILES['csvFile']['tmp_name']);
        $validTypes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'];
        
        if (!in_array($fileType, $validTypes)) {
            $errors[] = 'يرجى تحميل ملف CSV صالح. نوع الملف المكتشف: ' . $fileType;
        } else if (!isset($_POST['department_id']) || empty($_POST['department_id'])) {
            $errors[] = 'يرجى اختيار الإدارة';
        } else {
            $department_id = $_POST['department_id'];
            
            // التحقق من وجود الإدارة
            $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
            $stmt->bind_param("i", $department_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $errors[] = 'الإدارة غير موجودة';
            } else {
                $department = $result->fetch_assoc();
                
                // تهيئة متغيرات
                $importErrors = [];
                $transaction_started = false;
                
                try {
                    // قراءة الملف
                    $filePath = $_FILES['csvFile']['tmp_name'];
                    
                    // فتح الملف للقراءة
                    $file = fopen($filePath, 'r');
                    if (!$file) {
                        throw new Exception('لا يمكن فتح الملف للقراءة');
                    }
                    
                    // قراءة الصف الأول (الرؤوس)
                    $headers = fgetcsv($file);
                    if (!$headers) {
                        throw new Exception('الملف فارغ أو تنسيقه غير صحيح');
                    }
                    
                    // تحويل الرؤوس إلى UTF-8 إذا لزم الأمر
                    foreach ($headers as &$header) {
                        $header = mb_convert_encoding($header, 'UTF-8', 'UTF-8');
                        $header = trim($header);
                    }
                    
                    // طباعة الرؤوس للتشخيص
                    error_log('CSV Headers: ' . implode(', ', $headers));
                    
                    // تعيين الرؤوس المطلوبة حسب ملف CSV
                    $requiredHeaders = ['الاسم', 'البريد الالكتروني', 'رقم الهاتف', 'تاريخ الميلاد', 'الجنس', 'الجنسية', 'وقت الحلقة'];
                    
                    // التحقق من وجود الرؤوس المطلوبة
                    $missingHeaders = [];
                    foreach ($requiredHeaders as $requiredHeader) {
                        if (!in_array($requiredHeader, $headers)) {
                            $missingHeaders[] = $requiredHeader;
                        }
                    }
                    
                    if (!empty($missingHeaders)) {
                        throw new Exception('الرؤوس المطلوبة مفقودة: ' . implode(', ', $missingHeaders) . '. الرؤوس الموجودة هي: ' . implode(', ', $headers));
                    }
                    
                    // الحصول على فهارس الأعمدة
                    $firstNameIndex = array_search('الاسم', $headers);
                    $emailIndex = array_search('البريد الالكتروني', $headers);
                    $phoneIndex = array_search('رقم الهاتف', $headers);
                    $dobIndex = array_search('تاريخ الميلاد', $headers);
                    $genderIndex = array_search('الجنس', $headers);
                    $nationalityIndex = array_search('الجنسية', $headers);
                    $circleTimeIndex = array_search('وقت الحلقة', $headers);
                    
                    // بدء المعاملة
                    $conn->begin_transaction();
                    $transaction_started = true;
                    
                    // قراءة بيانات الطلاب
                    $rowNumber = 1; // بدءًا من 1 لأن الصف 0 هو الرؤوس
                    $circlesMap = []; // لتخزين معرفات الحلقات
                    
                    while (($data = fgetcsv($file)) !== FALSE) {
                        $rowNumber++;
                        
                        // تخطي الصفوف الفارغة
                        if (count($data) <= 1 && empty($data[0])) {
                            continue;
                        }
                        
                        // التحقق من عدد الأعمدة
                        if (count($data) < count($headers)) {
                            $importErrors[] = "الصف {$rowNumber}: عدد الأعمدة غير كافٍ";
                            continue;
                        }
                        
                        // تحويل البيانات إلى UTF-8 إذا لزم الأمر
                        foreach ($data as &$value) {
                            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                            $value = trim($value);
                        }
                        
                        // استخراج البيانات
                        $firstName = $data[$firstNameIndex];
                        $email = $data[$emailIndex];
                        $phone = $data[$phoneIndex];
                        $dob = $data[$dobIndex];
                        $gender = $data[$genderIndex];
                        $nationality = $data[$nationalityIndex];
                        $circleTime = $data[$circleTimeIndex];
                        
                        // التحقق من البيانات المطلوبة
                        if (empty($firstName) || empty($email) || empty($phone) || empty($dob) || empty($gender) || empty($nationality) || empty($circleTime)) {
                            $importErrors[] = "الصف {$rowNumber}: بعض البيانات المطلوبة فارغة";
                            continue;
                        }
                        
                        // التحقق من صحة البريد الإلكتروني
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $importErrors[] = "الصف {$rowNumber}: البريد الإلكتروني غير صالح: {$email}";
                            continue;
                        }
                        
                        // التحقق من صحة تاريخ الميلاد
                        $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
                        if (!$dobDate) {
                            $importErrors[] = "الصف {$rowNumber}: تاريخ الميلاد غير صالح: {$dob}. استخدم التنسيق YYYY-MM-DD";
                            continue;
                        }
                        
                        // التحقق من الجنس
                        if ($gender !== 'ذكر' && $gender !== 'أنثى') {
                            $importErrors[] = "الصف {$rowNumber}: الجنس غير صالح: {$gender}. يجب أن يكون 'ذكر' أو 'أنثى'";
                            continue;
                        }
                        
                        // التحقق من توافق الجنس مع الإدارة
                        if (($department['gender'] === 'male' && $gender !== 'ذكر') || 
                            ($department['gender'] === 'female' && $gender !== 'أنثى')) {
                            $departmentGenderText = ($department['gender'] === 'male') ? 'ذكر' : 'أنثى';
                            $importErrors[] = "الصف {$rowNumber}: جنس الطالب ({$gender}) لا يتوافق مع جنس الإدارة ({$departmentGenderText})";
                            continue;
                        }
                        
                        // التحقق من وجود الجنسية
                        $stmt = $conn->prepare("SELECT id FROM nationalities WHERE name = ?");
                        $stmt->bind_param("s", $nationality);
                        $stmt->execute();
                        $nationalityResult = $stmt->get_result();
                        
                        if ($nationalityResult->num_rows === 0) {
                            $importErrors[] = "الصف {$rowNumber}: الجنسية غير موجودة: {$nationality}";
                            continue;
                        }
                        
                        $nationalityRow = $nationalityResult->fetch_assoc();
                        $nationalityId = $nationalityRow['id'];
                        
                        // التعامل مع الحلقة
                        if (!isset($circlesMap[$circleTime])) {
                            // إنشاء حلقة جديدة
                            $stmt = $conn->prepare("INSERT INTO circles (name, department_id, time) VALUES (?, ?, ?)");
                            $circleName = "حلقة " . $circleTime;
                            $stmt->bind_param("sis", $circleName, $department_id, $circleTime);
                            $stmt->execute();
                            
                            $circleId = $conn->insert_id;
                            $circlesMap[$circleTime] = $circleId;
                            $createdCircles++;
                        } else {
                            $circleId = $circlesMap[$circleTime];
                        }
                        
                        // التحقق من وجود الطالب بنفس البريد الإلكتروني
                        $stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $existingStudentResult = $stmt->get_result();
                        
                        if ($existingStudentResult->num_rows > 0) {
                            $importErrors[] = "الصف {$rowNumber}: يوجد طالب بنفس البريد الإلكتروني: {$email}";
                            continue;
                        }
                        
                        // إنشاء كلمة مرور عشوائية
                        $password = generateRandomPassword();
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        // إدراج الطالب
                        $stmt = $conn->prepare("INSERT INTO students (first_name, email, phone, dob, gender, nationality_id, department_id, circle_id, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $genderDb = ($gender === 'ذكر') ? 'male' : 'female';
                        $stmt->bind_param("ssssssiiis", $firstName, $email, $phone, $dob, $genderDb, $nationalityId, $department_id, $circleId, $hashedPassword);
                        $stmt->execute();
                        
                        // إنشاء حساب مستخدم
                        $userId = $conn->insert_id;
                        $username = strtolower(str_replace(' ', '', $firstName)) . $userId;
                        
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, student_id) VALUES (?, ?, ?, 'student', ?)");
                        $stmt->bind_param("sssi", $username, $email, $hashedPassword, $userId);
                        $stmt->execute();
                        
                        $importedStudents++;
                    }
                    
                    // إغلاق الملف
                    fclose($file);
                    
                    // التحقق من وجود أخطاء
                    if (count($importErrors) > 0 && $importedStudents === 0) {
                        // إلغاء المعاملة إذا لم يتم استيراد أي طالب
                        if ($transaction_started) {
                            $conn->rollback();
                        }
                        
                        $errors = array_merge($errors, $importErrors);
                    } else {
                        // تأكيد المعاملة
                        if ($transaction_started) {
                            $conn->commit();
                        }
                        
                        // إعداد رسالة النجاح
                        $success = "تم استيراد {$importedStudents} طالب و {$createdCircles} حلقة بنجاح";
                        
                        // إضافة أي أخطاء حدثت أثناء الاستيراد
                        if (count($importErrors) > 0) {
                            $errors = array_merge($errors, $importErrors);
                        }
                    }
                } catch (Exception $e) {
                    // إلغاء المعاملة في حالة حدوث خطأ
                    if ($transaction_started) {
                        $conn->rollback();
                    }
                    
                    // تسجيل الخطأ
                    error_log('Import error: ' . $e->getMessage());
                    
                    // إضافة رسالة الخطأ
                    $errors[] = 'حدث خطأ أثناء استيراد البيانات: ' . $e->getMessage();
                }
            }
        }
    }
}

// بدء المخزن المؤقت
ob_start();
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">استيراد الطلاب</h1>
    
    <?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h5>حدثت الأخطاء التالية:</h5>
        <ul>
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">استيراد الطلاب من ملف CSV</h6>
        </div>
        <div class="card-body">
            <p>قم بتحميل ملف CSV يحتوي على بيانات الطلاب. يجب أن يحتوي الملف على الأعمدة التالية:</p>
            <ul>
                <li>الاسم</li>
                <li>البريد الالكتروني</li>
                <li>رقم الهاتف</li>
                <li>تاريخ الميلاد (بتنسيق YYYY-MM-DD)</li>
                <li>الجنس (ذكر أو أنثى)</li>
                <li>الجنسية</li>
                <li>وقت الحلقة</li>
            </ul>
            
            <div class="mb-4">
                <a href="download_template.php" class="btn btn-info" target="_blank">
                    <i class="fas fa-download"></i> تحميل نموذج CSV
                </a>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="department_id">اختر الإدارة:</label>
                    <select class="form-control" id="department_id" name="department_id" required>
                        <option value="">-- اختر الإدارة --</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>">
                            <?php echo $dept['name']; ?> 
                            (<?php echo $dept['gender'] === 'male' ? 'ذكور' : 'إناث'; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="csvFile">اختر ملف CSV:</label>
                    <input type="file" class="form-control-file" id="csvFile" name="csvFile" accept=".csv,text/csv,application/vnd.ms-excel,application/csv,text/x-csv,application/x-csv,text/comma-separated-values,text/x-comma-separated-values" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> استيراد الطلاب
                </button>
            </form>
        </div>
    </div>
    
    <?php if ($importedStudents > 0): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">نتائج الاستيراد</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <tr>
                        <th>عدد الطلاب المستوردين</th>
                        <td><?php echo $importedStudents; ?></td>
                    </tr>
                    <tr>
                        <th>عدد الحلقات التي تم إنشاؤها</th>
                        <td><?php echo $createdCircles; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
