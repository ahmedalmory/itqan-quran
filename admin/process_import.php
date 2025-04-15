<?php
// منع عرض الأخطاء مباشرة
error_reporting(0);
ini_set('display_errors', 0);

// تعيين رأس الاستجابة مبكراً
header('Content-Type: application/json; charset=UTF-8');

// تعريف معالج الأخطاء المخصص
function handleError($errno, $errstr, $errfile, $errline) {
    $response = [
        'success' => false,
        'message' => 'حدث خطأ في النظام',
        'errors' => [$errstr]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// تعيين معالج الأخطاء
set_error_handler('handleError');

// معالج الاستثناءات غير المعالجة
function handleException($e) {
    $response = [
        'success' => false,
        'message' => 'حدث خطأ غير متوقع',
        'errors' => [$e->getMessage()]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// تعيين معالج الاستثناءات
set_exception_handler('handleException');

// تهيئة متغيرات الاستجابة
$response = [
    'success' => false,
    'message' => '',
    'imported_students' => 0,
    'created_circles' => 0,
    'errors' => []
];

try {
    require_once '../config/database.php';
    require_once '../config/auth.php';
    require_once '../includes/functions.php';

    // التأكد من أن المستخدم مدير
    requireRole(['super_admin', 'department_admin']);

    // تعيين الترميز
    mysqli_set_charset($conn, "utf8mb4");
    ini_set('default_charset', 'UTF-8');

    // التحقق من وجود ملف
    if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'لم يتم تحميل الملف بشكل صحيح';
        $response['errors'][] = 'خطأ في تحميل الملف: ' . (isset($_FILES['csvFile']) ? $_FILES['csvFile']['error'] : 'الملف غير موجود');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // التحقق من نوع الملف
    $fileType = mime_content_type($_FILES['csvFile']['tmp_name']);
    if ($fileType !== 'text/plain' && $fileType !== 'text/csv' && $fileType !== 'application/csv' && $fileType !== 'application/vnd.ms-excel' && $fileType !== 'application/octet-stream') {
        $response['message'] = 'يرجى تحميل ملف CSV صالح';
        $response['errors'][] = 'نوع الملف غير مدعوم: ' . $fileType;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // التحقق من معرف الإدارة
    if (!isset($_POST['department_id']) || empty($_POST['department_id'])) {
        $response['message'] = 'يرجى اختيار الإدارة';
        $response['errors'][] = 'لم يتم تحديد الإدارة';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $department_id = $_POST['department_id'];

    // التحقق من وجود الإدارة
    $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $response['message'] = 'الإدارة غير موجودة';
        $response['errors'][] = 'الإدارة المحددة غير موجودة في قاعدة البيانات';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $department = $result->fetch_assoc();

    // تهيئة متغيرات
    $importedStudents = 0;
    $createdCircles = [];
    $circlesMap = []; // لتخزين معرفات الحلقات
    $transaction_started = false;

    // قراءة الملف
    $filePath = $_FILES['csvFile']['tmp_name'];
    
    // تسجيل معلومات الملف للتصحيح
    error_log('File path: ' . $filePath);
    error_log('File size: ' . filesize($filePath) . ' bytes');
    error_log('File type: ' . $fileType);
    
    // فتح الملف للقراءة
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new Exception('فشل في فتح الملف');
    }
    
    // تحديد الترميز وإزالة BOM إذا وجد
    $firstBytes = fread($handle, 3);
    rewind($handle);
    if (bin2hex($firstBytes) === 'efbbbf') {
        // إذا كان الملف يحتوي على BOM، تخطي أول 3 بايت
        fseek($handle, 3);
    } else {
        // إعادة المؤشر إلى بداية الملف
        rewind($handle);
    }

    // قراءة العناوين
    $headers = fgetcsv($handle);
    if ($headers === false) {
        throw new Exception('الملف فارغ');
    }

    // تنظيف وتحويل العناوين
    $headers = array_map(function($header) {
        // تنظيف العناوين
        $header = trim($header);
        $header = str_replace('*', '', $header);
        $header = preg_replace('/\s+/', ' ', $header);
        
        // تحويل الترميز إذا لزم الأمر
        $encodings = ['UTF-8', 'ISO-8859-1', 'ASCII'];
        $detectedEncoding = mb_detect_encoding($header, $encodings, true);
        if ($detectedEncoding !== 'UTF-8') {
            $header = mb_convert_encoding($header, 'UTF-8', $detectedEncoding);
        }
        
        return $header;
    }, $headers);

    // تسجيل العناوين للتصحيح
    error_log('Headers after cleaning: ' . implode(', ', $headers));

    // تعريف العناوين المطلوبة
    $requiredHeaders = [
        'الاسم',
        'البريد الإلكتروني',
        'رقم الهاتف',
        'تاريخ الميلاد',
        'الجنس',
        'الجنسية',
        'اسم الحلقة',
        'وقت الحلقة',
        'المعلم'
    ];

    // التحقق من وجود العناوين المطلوبة
    $missingHeaders = array_diff($requiredHeaders, $headers);
    if (!empty($missingHeaders)) {
        throw new Exception(sprintf(
            'العناوين التالية مفقودة: %s. العناوين الموجودة: %s',
            implode(', ', $missingHeaders),
            implode(', ', $headers)
        ));
    }

    // إنشاء مصفوفة فهرس للعناوين
    $headerIndexes = array_flip($headers);

    // بدء المعاملة
    $conn->autocommit(FALSE);
    $transaction_started = true;

    // قراءة البيانات
    $lineNumber = 2; // بدء من السطر الثاني (بعد العناوين)
    while (($data = fgetcsv($handle)) !== FALSE) {
        // تخطي الصفوف الفارغة
        if (count($data) <= 1 && empty($data[0])) {
            continue;
        }

        // التحقق من عدد الأعمدة
        if (count($data) < count($headers)) {
            $response['errors'][] = "السطر $lineNumber: عدد الأعمدة غير كافٍ";
            $lineNumber++;
            continue;
        }

        // تحويل البيانات إلى UTF-8
        $data = array_map(function($value) {
            $encodings = ['UTF-8', 'ISO-8859-1', 'ASCII'];
            $detectedEncoding = mb_detect_encoding($value, $encodings, true);
            if ($detectedEncoding !== 'UTF-8') {
                return mb_convert_encoding($value, 'UTF-8', $detectedEncoding);
            }
            return $value;
        }, $data);

        // استخراج البيانات
        $name = trim($data[$headerIndexes['الاسم']]);
        $email = trim($data[$headerIndexes['البريد الإلكتروني']]);
        $phone = trim($data[$headerIndexes['رقم الهاتف']]);
        $dob = trim($data[$headerIndexes['تاريخ الميلاد']]);
        $gender = trim($data[$headerIndexes['الجنس']]);
        $nationality = trim($data[$headerIndexes['الجنسية']]);
        $circleName = trim($data[$headerIndexes['اسم الحلقة']]);
        $circleTime = trim($data[$headerIndexes['وقت الحلقة']]);
        $teacher = trim($data[$headerIndexes['المعلم']]);

        // التحقق من البيانات الإلزامية
        if (empty($name) || empty($email) || empty($phone) || empty($dob) || 
            empty($gender) || empty($nationality) || empty($circleName) || 
            empty($circleTime)) {
            $response['errors'][] = "السطر $lineNumber: بعض البيانات الإلزامية مفقودة";
            $lineNumber++;
            continue;
        }

        // التحقق من صحة البريد الإلكتروني
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['errors'][] = "السطر $lineNumber: البريد الإلكتروني غير صالح: $email";
            $lineNumber++;
            continue;
        }

        // التحقق من صحة تاريخ الميلاد
        $dobFormatted = date('Y-m-d', strtotime($dob));
        if ($dobFormatted === '1970-01-01' && $dob !== '1970-01-01') {
            $response['errors'][] = "السطر $lineNumber: تاريخ الميلاد غير صالح: $dob";
            $lineNumber++;
            continue;
        }

        // التحقق من الجنس
        $genderValue = strtolower($gender) === 'ذكر' ? 'male' : (strtolower($gender) === 'أنثى' ? 'female' : '');
        if (empty($genderValue)) {
            $response['errors'][] = "السطر $lineNumber: الجنس غير صالح (يجب أن يكون 'ذكر' أو 'أنثى'): $gender";
            $lineNumber++;
            continue;
        }

        // التحقق من توافق الجنس مع الإدارة
        if ($department['student_gender'] !== $genderValue) {
            $response['errors'][] = "السطر $lineNumber: جنس الطالب ($gender) لا يتوافق مع جنس الإدارة ({$department['student_gender']})";
            $lineNumber++;
            continue;
        }

        // التحقق من وجود الجنسية
        $stmt = $conn->prepare("SELECT ID FROM countries WHERE Name = ? OR AltName = ? LIMIT 1");
        $stmt->bind_param("ss", $nationality, $nationality);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['errors'][] = "السطر $lineNumber: الجنسية غير موجودة: $nationality";
            $lineNumber++;
            continue;
        }
        
        $country = $result->fetch_assoc();
        $countryId = $country['ID'];

        // التحقق من وجود المعلم
        $teacherId = null;
        if (!empty($teacher)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE name = ? AND (role = 'teacher' OR role = 'super_admin') LIMIT 1");
            $stmt->bind_param("s", $teacher);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['errors'][] = "السطر $lineNumber: المعلم غير موجود: $teacher";
                $lineNumber++;
                continue;
            }
            
            $teacherData = $result->fetch_assoc();
            $teacherId = $teacherData['id'];
        }

        // تحويل وقت الحلقة إلى التنسيق المناسب
        $circleTimeValue = '';
        
        // التعامل مع تنسيق الوقت المباشر (مثل 16:00-18:00)
        if (preg_match('/^(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/', $circleTime, $matches)) {
            $startHour = (int)$matches[1];
            $startMinute = (int)$matches[2];
            $endHour = (int)$matches[3];
            $endMinute = (int)$matches[4];
            
            // التحقق من صحة الوقت
            if ($startHour >= 0 && $startHour <= 23 && $startMinute >= 0 && $startMinute <= 59 &&
                $endHour >= 0 && $endHour <= 23 && $endMinute >= 0 && $endMinute <= 59) {
                $circleTimeValue = sprintf('%02d:%02d-%02d:%02d', $startHour, $startMinute, $endHour, $endMinute);
            } else {
                $response['errors'][] = "السطر $lineNumber: وقت الحلقة غير صالح: $circleTime";
                $lineNumber++;
                continue;
            }
        } else {
            // التعامل مع الأوقات المرتبطة بالصلوات
            switch (strtolower(trim($circleTime))) {
                case 'بعد الفجر':
                    $circleTimeValue = 'after_fajr';
                    break;
                case 'بعد الظهر':
                    $circleTimeValue = 'after_dhuhr';
                    break;
                case 'بعد العصر':
                    $circleTimeValue = 'after_asr';
                    break;
                case 'بعد المغرب':
                    $circleTimeValue = 'after_maghrib';
                    break;
                case 'بعد العشاء':
                    $circleTimeValue = 'after_isha';
                    break;
                default:
                    $response['errors'][] = "السطر $lineNumber: وقت الحلقة غير صالح: $circleTime";
                    $lineNumber++;
                    continue;
            }
        }

        // إنشاء أو الحصول على الحلقة
        $circleKey = $circleName . '_' . $circleTimeValue;
        
        if (!isset($circlesMap[$circleKey])) {
            // التحقق من وجود الحلقة
            $stmt = $conn->prepare("SELECT id FROM study_circles WHERE name = ? AND circle_time = ? AND department_id = ? LIMIT 1");
            $stmt->bind_param("ssi", $circleName, $circleTimeValue, $department_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // الحلقة موجودة
                $circle = $result->fetch_assoc();
                $circleId = $circle['id'];
            } else {
                // إنشاء حلقة جديدة
                $stmt = $conn->prepare("
                    INSERT INTO study_circles 
                    (name, department_id, teacher_id, max_students, age_from, age_to, circle_time) 
                    VALUES (?, ?, ?, 10, 5, 18, ?)
                ");
                $stmt->bind_param("siis", $circleName, $department_id, $teacherId, $circleTimeValue);
                $stmt->execute();
                $circleId = $conn->insert_id;
                
                if (!in_array($circleName, $createdCircles)) {
                    $createdCircles[] = $circleName;
                }
            }
            
            $circlesMap[$circleKey] = $circleId;
        } else {
            $circleId = $circlesMap[$circleKey];
        }

        // التحقق من وجود الطالب بنفس البريد الإلكتروني
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // الطالب موجود
            $student = $result->fetch_assoc();
            $studentId = $student['id'];
            
            // تحديث بيانات الطالب
            $stmt = $conn->prepare("
                UPDATE users 
                SET name = ?, phone = ?, date_of_birth = ?, gender = ?, nationality = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssi", $name, $phone, $dobFormatted, $genderValue, $countryId, $studentId);
            $stmt->execute();
        } else {
            // إنشاء طالب جديد
            $password = generateRandomPassword();
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO users 
                (name, email, password, phone, date_of_birth, gender, nationality, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'student', NOW())
            ");
            $stmt->bind_param("sssssss", $name, $email, $hashedPassword, $phone, $dobFormatted, $genderValue, $countryId);
            $stmt->execute();
            $studentId = $conn->insert_id;
            
            // إرسال بريد إلكتروني بكلمة المرور (يمكن تنفيذه لاحقًا)
            
            $importedStudents++;
        }

        // إضافة الطالب إلى الحلقة إذا لم يكن موجودًا
        $stmt = $conn->prepare("
            SELECT id FROM circle_students 
            WHERE student_id = ? AND circle_id = ? LIMIT 1
        ");
        $stmt->bind_param("ii", $studentId, $circleId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt = $conn->prepare("
                INSERT INTO circle_students 
                (circle_id, student_id, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->bind_param("ii", $circleId, $studentId);
            $stmt->execute();
        }

        $lineNumber++;
    }

    // إغلاق الملف
    fclose($handle);

    // تأكيد المعاملة
    $conn->commit();
    
    // إعداد الاستجابة
    $response['success'] = true;
    $response['message'] = 'تم استيراد البيانات بنجاح';
    $response['imported_students'] = $importedStudents;
    $response['created_circles'] = count($createdCircles);

} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ
    if ($transaction_started) {
        $conn->rollback();
    }
    
    // تسجيل الخطأ
    error_log('Import error: ' . $e->getMessage());
    
    // إعداد رسالة الخطأ
    $response['success'] = false;
    $response['message'] = 'حدث خطأ أثناء استيراد البيانات: ' . $e->getMessage();
    if (!isset($response['errors'])) {
        $response['errors'] = [];
    }
    $response['errors'][] = $e->getMessage();
}

// إعادة الاستجابة
echo json_encode($response, JSON_UNESCAPED_UNICODE);
