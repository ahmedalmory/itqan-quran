<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// التأكد من أن المستخدم مدير
requireRole(['super_admin', 'department_admin']);

// جلب قائمة الإدارات المتاحة للمستخدم
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT d.* 
    FROM departments d
    LEFT JOIN department_admins da ON d.id = da.department_id
    WHERE (? IN (SELECT id FROM users WHERE role = 'super_admin')
         OR da.user_id = ?)
    ORDER BY d.name
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$departments = $result->fetch_all(MYSQLI_ASSOC);

// بداية المحتوى
ob_start();
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 arabic-font">استيراد الطلاب وإنشاء الحلقات</h5>
                </div>
                <div class="card-body">
                    <!-- نموذج الاستيراد -->
                    <form action="process_import.php" method="post" enctype="multipart/form-data" id="importForm">
                        <div class="row g-3">
                            <!-- اختيار الإدارة -->
                            <div class="col-md-6">
                                <label for="department" class="form-label">اختر الإدارة</label>
                                <select class="form-select" name="department_id" id="department" required>
                                    <option value="">-- اختر الإدارة --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- رفع ملف CSV -->
                            <div class="col-md-6">
                                <label for="csvFile" class="form-label">ملف CSV</label>
                                <input type="file" class="form-control" name="csvFile" id="csvFile" 
                                       accept=".csv" required>
                                <small class="text-muted">يجب أن يكون الملف بترميز UTF-8</small>
                            </div>
                        </div>

                        <!-- معلومات عن الملف المطلوب -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading mb-2">تعليمات استيراد البيانات:</h6>
                                    <ol class="mb-0">
                                        <li>قم بتحميل <a href="create_template.php" class="alert-link">نموذج CSV</a> المطلوب</li>
                                        <li>املأ البيانات في النموذج مع مراعاة التنسيق المطلوب</li>
                                        <li>احفظ الملف بتنسيق CSV مع ترميز UTF-8</li>
                                        <li>اختر الإدارة وارفع الملف</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <!-- زر الاستيراد -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-file-import"></i> بدء الاستيراد
                                </button>
                                <a href="create_template.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-download"></i> تحميل نموذج CSV
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- نتائج الاستيراد -->
                    <div id="importResults" class="mt-4" style="display: none;">
                        <div class="alert alert-success">
                            <h6 class="alert-heading mb-2">نتائج الاستيراد:</h6>
                            <div id="importSummary">
                                <ul class="mb-0">
                                    <li>عدد الطلاب الذين تم استيرادهم: <span id="importedStudents">0</span></li>
                                    <li>عدد الحلقات التي تم إنشاؤها: <span id="createdCircles">0</span></li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- عرض الأخطاء -->
                        <div id="importErrors" class="mt-4" style="display: none;">
                            <div class="alert alert-danger">
                                <h6 class="alert-heading mb-2">أخطاء الاستيراد:</h6>
                                <ul id="errorsList" class="mb-0">
                                </ul>
                            </div>
                        </div>
                        
                        <!-- عرض التقدم -->
                        <div id="importProgress" class="mt-4" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                                     aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
                            </div>
                            <p class="text-center mt-2">جاري معالجة البيانات، يرجى الانتظار...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.alert-info {
    background-color: rgba(var(--bs-info-rgb), 0.1);
    border-color: rgba(var(--bs-info-rgb), 0.2);
}
.card {
    border-radius: 15px;
}
.card-header {
    border-top-right-radius: 15px !important;
    border-top-left-radius: 15px !important;
}
.btn-primary {
    background-color: #1FA363;
    border-color: #1FA363;
}
.btn-primary:hover {
    background-color: #198754;
    border-color: #198754;
}
.btn-outline-secondary {
    color: #6c757d;
    border-color: #6c757d;
}
.btn-outline-secondary:hover {
    background-color: #6c757d;
    color: #fff;
}
.form-control, .form-select {
    border-radius: 8px;
}
.alert {
    border-radius: 10px;
}
.arabic-font {
    font-family: 'Noto Kufi Arabic', sans-serif;
}
</style>

<!-- إضافة Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- إضافة الخط العربي -->
<link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;700&display=swap" rel="stylesheet">

<script>
document.addEventListener('DOMContentLoaded', function() {
    // معالجة نموذج الاستيراد
    document.getElementById('importForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // إظهار شريط التقدم
        document.getElementById('importProgress').style.display = 'block';
        
        // إخفاء النتائج السابقة
        document.getElementById('importResults').style.display = 'none';
        document.getElementById('importErrors').style.display = 'none';
        
        var formData = new FormData(this);
        
        fetch('process_import.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // التحقق من نوع الاستجابة
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.indexOf('application/json') !== -1) {
                return response.json().catch(error => {
                    throw new Error('فشل في تحليل JSON: ' + error.message);
                });
            } else {
                // إذا لم تكن الاستجابة JSON، قراءتها كنص
                return response.text().then(text => {
                    // محاولة تحليل النص كـ JSON
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('استجابة غير صالحة من الخادم: ' + text.substring(0, 100) + '...');
                    }
                });
            }
        })
        .then(data => {
            // إخفاء شريط التقدم
            document.getElementById('importProgress').style.display = 'none';
            
            if (data.success) {
                // عرض النتائج
                document.getElementById('importResults').style.display = 'block';
                document.getElementById('importedStudents').textContent = data.imported_students || 0;
                document.getElementById('createdCircles').textContent = data.created_circles || 0;
                
                // عرض الأخطاء إن وجدت
                if (data.errors && data.errors.length > 0) {
                    showErrors(data.errors);
                }
            } else {
                // عرض رسالة الخطأ
                alert('حدث خطأ أثناء الاستيراد: ' + data.message);
                
                // عرض الأخطاء التفصيلية إن وجدت
                if (data.errors && data.errors.length > 0) {
                    showErrors(data.errors);
                }
            }
        })
        .catch(error => {
            // إخفاء شريط التقدم
            document.getElementById('importProgress').style.display = 'none';
            
            console.error('Error:', error);
            
            // عرض رسالة الخطأ على الشاشة
            document.getElementById('importErrors').style.display = 'block';
            var errorsList = document.getElementById('errorsList');
            errorsList.innerHTML = '';
            var li = document.createElement('li');
            li.textContent = 'خطأ في الاتصال: ' + error.message;
            errorsList.appendChild(li);
        });
    });
    
    // دالة لعرض الأخطاء
    function showErrors(errors) {
        var errorsList = document.getElementById('errorsList');
        errorsList.innerHTML = '';
        
        errors.forEach(function(error) {
            var li = document.createElement('li');
            li.textContent = error;
            errorsList.appendChild(li);
        });
        
        document.getElementById('importErrors').style.display = 'block';
    }
});
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>