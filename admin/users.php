<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is super admin
requireRole('super_admin');

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$success_message = '';
$error_message = '';

// إعدادات الصفحات
$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $items_per_page;

// معالجة البحث
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_conditions = [];
if ($search) {
    $search = '%' . $search . '%';
    $search_conditions[] = "
        (u.name LIKE ? OR 
         u.email LIKE ? OR 
         u.phone LIKE ?)
    ";
}

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user']) || isset($_POST['update_user'])) {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $role = sanitize_input($_POST['role']);
        $gender = sanitize_input($_POST['gender']);
        $country_id = sanitize_input($_POST['country_id']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            if (isset($_POST['create_user'])) {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $error_message = "البريد الإلكتروني مستخدم مسبقاً";
                } else {
                    // استخدام كلمة مرور موحدة
                    $password = '1234567';
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO users (name, email, phone, password, role, gender, country_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("sssssss", $name, $email, $phone, $hashed_password, $role, $gender, $country_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "تم إنشاء المستخدم بنجاح. كلمة المرور المؤقتة: 1234567";
                    } else {
                        $error_message = "حدث خطأ أثناء إنشاء المستخدم.";
                    }
                }
            } elseif (isset($_POST['update_user'])) {
                $user_id = (int)$_POST['user_id'];
                
                // Check if email exists for other users
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $error_message = "Email already exists.";
                } else {
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, phone = ?, role = ?, gender = ?, country_id = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ssssssi", $name, $email, $phone, $role, $gender, $country_id, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "User updated successfully.";
                    } else {
                        $error_message = "Error updating user.";
                    }
                }
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = (int)$_POST['user_id'];
        
        // استخدام كلمة مرور موحدة
        $password = '1234567';
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "تم إعادة تعيين كلمة المرور بنجاح. كلمة المرور الجديدة: 1234567";
        } else {
            $error_message = "حدث خطأ أثناء إعادة تعيين كلمة المرور.";
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        // Check if user is a teacher with circles
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM study_circles 
            WHERE teacher_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error_message = "Cannot delete teacher who has study circles.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Delete from circle_students if student
                $stmt = $conn->prepare("DELETE FROM circle_students WHERE student_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Delete daily reports
                $stmt = $conn->prepare("DELETE FROM daily_reports WHERE student_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                $conn->commit();
                $success_message = "User deleted successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error deleting user.";
            }
        }
    }
}

// Get user for editing
$edit_user = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

// تعديل استعلام الحصول على المستخدمين
$query = "
    SELECT SQL_CALC_FOUND_ROWS u.*, c.name AS country_name, 
           c.AltName AS country_alt_name, c.CountryCode as country_code,
           (SELECT COUNT(*) FROM study_circles WHERE teacher_id = u.id) AS circles_count,
           (SELECT COUNT(*) FROM circle_students WHERE student_id = u.id) AS enrolled_circles,
           (SELECT COUNT(*) FROM daily_reports WHERE student_id = u.id) AS reports_count
    FROM users u
    LEFT JOIN countries c ON u.country_id COLLATE utf8mb4_unicode_ci = c.ID COLLATE utf8mb4_unicode_ci
    WHERE 1=1
";

if ($role_filter !== 'all') {
    $query .= " AND u.role = ?";
}

if (!empty($search_conditions)) {
    $query .= " AND " . implode(" AND ", $search_conditions);
}

$query .= " ORDER BY u.name";
$query .= " LIMIT ? OFFSET ?";

// إعداد الباراميترز
$params = [];
$types = '';

if ($role_filter !== 'all') {
    $params[] = $role_filter;
    $types .= 's';
}

if ($search) {
    $params = array_merge($params, [$search, $search, $search]);
    $types .= 'sss';
}

$params = array_merge($params, [$items_per_page, $offset]);
$types .= 'ii';

// تنفيذ الاستعلام
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// الحصول على العدد الإجمالي
$total_items = $conn->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_pages = ceil($total_items / $items_per_page);

$pageTitle = 'Users Management';
$pageHeader = $action === 'edit' ? 'Edit User' : ($action === 'new' ? 'مستخدم جديد' : 'إدارة المستخدمين');
ob_start();
?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-start align-items-md-center">
                <!-- فورم البحث -->
                <form class="d-flex gap-2 w-100 mb-3 mb-md-0" method="GET">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_filter); ?>">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" 
                               placeholder="ابحث عن اسم، بريد، أو رقم هاتف..."
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>

                <div>
                    
                    <div class="btn-group d-flex flex-wrap">
                        <a href="?role=all" 
                          class="btn btn-outline-primary <?php echo $role_filter === 'all' ? 'active' : ''; ?> px-4">
                            <i class="bi bi-people-fill me-1"></i>
                            الكل
                        </a>
                        <a href="?role=super_admin" 
                          class="btn btn-outline-primary <?php echo $role_filter === 'super_admin' ? 'active' : ''; ?> px-4">
                            <i class="bi bi-shield-fill me-1"></i>
                            مديري النظام
                        </a>
                        <a href="?role=department_admin" 
                          class="btn btn-outline-primary <?php echo $role_filter === 'department_admin' ? 'active' : ''; ?> px-4">
                            <i class="bi bi-building-fill me-1"></i>
                            مشرفي الأقسام
                        </a>
                        <a href="?role=teacher" 
                          class="btn btn-outline-primary <?php echo $role_filter === 'teacher' ? 'active' : ''; ?> px-4">
                            <i class="bi bi-mortarboard-fill me-1"></i>
                            المعلمين
                        </a>
                        <a href="?role=supervisor" 
                          class="btn btn-outline-primary <?php echo $role_filter === 'supervisor' ? 'active' : ''; ?> px-4">
                            <i class="bi bi-eye-fill me-1"></i>
                            المشرفين التربويين
                        </a>
                        <a href="?role=student" 
                          class="btn btn-outline-primary <?php echo $role_filter === 'student' ? 'active' : ''; ?> px-4">
                            <i class="bi bi-person-fill me-1"></i>
                            الطلاب
                        </a>
                    </div>
                </div>
                <a href="?action=new" class="btn btn-primary w-100 w-md-auto">
                    <i class="bi bi-person-plus"></i> إضافة مستخدم جديد
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>البريد الإلكتروني</th>
                            <th>الهاتف</th>
                            <th>الدور</th>
                            <th>الجنس</th>
                            <th>الدولة</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <a href="https://wa.me/<?php echo $user['phone']; ?>" 
                                       class="text-decoration-none" target="_blank">
                                        <i class="bi bi-whatsapp text-success"></i>
                                        <?php echo htmlspecialchars($user['phone']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($user['role']) {
                                            'super_admin' => 'danger',
                                            'department_admin' => 'info',
                                            'teacher' => 'success',
                                            'supervisor' => 'warning',
                                            'student' => 'primary',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php 
                                        echo match($user['role']) {
                                            'super_admin' => 'مدير النظام',
                                            'department_admin' => 'مشرف قسم',
                                            'teacher' => 'معلم',
                                            'supervisor' => 'مشرف تربوي',
                                            'student' => 'طالب',
                                            default => $user['role']
                                        }; 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="bi <?php echo $user['gender'] === 'male' ? 'bi-gender-male text-primary' : 'bi-gender-female text-danger'; ?>"></i>
                                    <?php echo $user['gender'] === 'male' ? 'ذكر' : 'أنثى'; ?>
                                </td>
                                <td>
                                    <?php if ($user['country_name']): ?>
                                        <span class="d-inline-flex align-items-center">
                                            <i class="flag flag-<?php echo strtolower($user['country_code']); ?> me-1"></i>
                                            <?php echo htmlspecialchars($user['country_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'teacher'): ?>
                                        <span class="badge bg-light text-dark">
                                            <?php echo $user['circles_count']; ?> حلقة
                                        </span>
                                    <?php elseif ($user['role'] === 'student'): ?>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-light text-dark">
                                                <?php echo $user['enrolled_circles']; ?> حلقة
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $user['reports_count']; ?> تقرير
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if ($user['role'] === 'student'): ?>
                                            <a href="student_details.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="تفاصيل">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="user_details.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="تفاصيل">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=edit&id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-info" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-warning" title="إعادة تعيين كلمة المرور"
                                                onclick="confirmResetPassword(<?php echo $user['id']; ?>)">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <?php if (($user['role'] === 'teacher' && $user['circles_count'] == 0) || 
                                                  $user['role'] === 'student' || 
                                                  ($user['role'] === 'super_admin' && $user['id'] != $_SESSION['user_id'])): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" title="حذف"
                                                    onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- عرض الموبايل -->
            <div class="d-md-none">
                <?php foreach ($users as $user): ?>
                    <div class="card mb-3 user-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                                    <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <span class="badge bg-<?php 
                                    echo match($user['role']) {
                                        'super_admin' => 'danger',
                                        'department_admin' => 'info',
                                        'teacher' => 'success',
                                        'supervisor' => 'warning',
                                        'student' => 'primary',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php 
                                    echo match($user['role']) {
                                        'super_admin' => 'مدير النظام',
                                        'department_admin' => 'مشرف قسم',
                                        'teacher' => 'معلم',
                                        'supervisor' => 'مشرف تربوي',
                                        'student' => 'طالب',
                                        default => $user['role']
                                    }; 
                                    ?>
                                </span>
                            </div>
                            
                            <div class="d-flex flex-column gap-2 mb-3">
                                <div>
                                    <a href="https://wa.me/<?php echo $user['phone']; ?>" 
                                       class="text-decoration-none d-flex align-items-center gap-2"
                                       target="_blank">
                                        <i class="bi bi-whatsapp text-success"></i>
                                        <?php echo htmlspecialchars($user['phone']); ?>
                                    </a>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi <?php echo $user['gender'] === 'male' ? 'bi-gender-male text-primary' : 'bi-gender-female text-danger'; ?>"></i>
                                    <?php echo $user['gender'] === 'male' ? 'ذكر' : 'أنثى'; ?>
                                </div>
                                <?php if ($user['country_name']): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="flag flag-<?php echo strtolower($user['country_code']); ?>"></i>
                                        <?php echo htmlspecialchars($user['country_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($user['role'] === 'teacher'): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-mortarboard text-success"></i>
                                        <span><?php echo $user['circles_count']; ?> حلقة</span>
                                    </div>
                                <?php elseif ($user['role'] === 'student'): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-book text-primary"></i>
                                        <span><?php echo $user['enrolled_circles']; ?> حلقة</span>
                                        <span class="mx-2">•</span>
                                        <i class="bi bi-journal-text text-info"></i>
                                        <span><?php echo $user['reports_count']; ?> تقرير</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <div class="btn-group" role="group">
                                    <?php if ($user['role'] === 'student'): ?>
                                        <a href="student_details.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye me-1"></i>
                                            تفاصيل
                                        </a>
                                    <?php else: ?>
                                        <a href="user_details.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye me-1"></i>
                                            تفاصيل
                                        </a>
                                    <?php endif; ?>
                                    <a href="?action=edit&id=<?php echo $user['id']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bi bi-pencil me-1"></i>
                                        تعديل
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning"
                                            onclick="confirmResetPassword(<?php echo $user['id']; ?>)">
                                        <i class="bi bi-key me-1"></i>
                                        كلمة المرور
                                    </button>
                                    <?php if (($user['role'] === 'teacher' && $user['circles_count'] == 0) || 
                                              $user['role'] === 'student' || 
                                              ($user['role'] === 'super_admin' && $user['id'] != $_SESSION['user_id'])): ?>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmDelete(<?php echo $user['id']; ?>)">
                                            <i class="bi bi-trash me-1"></i>
                                            حذف
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ترقيم الصفحات -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>">
                                السابق
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>">
                                التالي
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- إحصائيات -->
            <div class="text-muted text-center mt-2">
                إجمالي النتائج: <?php echo $total_items; ?>
                <?php if ($search): ?>
                    | نتائج البحث عن: <?php echo htmlspecialchars($search); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="bi bi-person-plus-fill"></i>
                <?php echo $action === 'edit' ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم جديد'; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php
            // Get countries list
            $countries_query = "SELECT ID, name, AltName, CountryCode FROM countries ORDER BY `Order`";
            $countries = $conn->query($countries_query)->fetch_all(MYSQLI_ASSOC);
            ?>
            <form method="POST" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="name" class="form-label">الاسم الكامل</label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="<?php echo $edit_user ? htmlspecialchars($edit_user['name']) : ''; ?>">
                        <div class="invalid-feedback">يرجى إدخال الاسم الكامل</div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>">
                        <div class="invalid-feedback">يرجى إدخال بريد إلكتروني صحيح</div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="country_id" class="form-label">الدولة</label>
                        <select class="form-select" id="country_id" name="country_id" required>
                            <option value="">اختر الدولة...</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo $country['ID']; ?>" 
                                        data-code="<?php echo $country['CountryCode']; ?>"
                                        <?php echo $edit_user && $edit_user['country_id'] === $country['ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($country['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الدولة</div>
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <div class="input-group">
                            <span class="input-group-text" id="phone-code">+</span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   required pattern="[0-9]+"
                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['phone']) : ''; ?>"
                                   placeholder="رقم الهاتف بدون صفر البداية">
                        </div>
                        <div class="form-text">أدخل الرقم بدون صفر البداية</div>
                    </div>
                    <div class="col-md-4">
                        <label for="role" class="form-label">الدور</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">اختر الدور...</option>
                            <option value="student" <?php echo $edit_user && $edit_user['role'] === 'student' ? 'selected' : ''; ?>>طالب</option>
                            <option value="teacher" <?php echo $edit_user && $edit_user['role'] === 'teacher' ? 'selected' : ''; ?>>معلم</option>
                            <option value="supervisor" <?php echo $edit_user && $edit_user['role'] === 'supervisor' ? 'selected' : ''; ?>>مشرف تربوي</option>
                            <option value="department_admin" <?php echo $edit_user && $edit_user['role'] === 'department_admin' ? 'selected' : ''; ?>>مشرف قسم</option>
                            <option value="super_admin" <?php echo $edit_user && $edit_user['role'] === 'super_admin' ? 'selected' : ''; ?>>مدير النظام</option>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الدور</div>
                    </div>
                </div>

                <!-- معاينة رقم الواتساب -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <label class="form-label mb-3">معاينة رقم الواتساب</label>
                                <div class="whatsapp-preview">
                                    <a href="#" 
                                       class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2" 
                                       id="whatsapp-link" 
                                       style="display: none;"
                                       target="_blank">
                                        <i class="bi bi-whatsapp fs-5"></i>
                                        <span id="whatsapp-preview" class="h5 mb-0"></span>
                                    </a>
                                    <div id="whatsapp-placeholder" class="text-center text-muted p-3">
                                        أدخل رقم الهاتف والدولة لمعاينة رابط الواتساب
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="gender" class="form-label">الجنس</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="male" <?php echo $edit_user && $edit_user['gender'] === 'male' ? 'selected' : ''; ?>>ذكر</option>
                            <option value="female" <?php echo $edit_user && $edit_user['gender'] === 'female' ? 'selected' : ''; ?>>أنثى</option>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الجنس</div>
                    </div>
                    <?php if ($action === 'edit'): ?>
                    <div class="col-md-6">
                        <label for="password" class="form-label">كلمة المرور الجديدة (اختياري)</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <div class="form-text">اترك الحقل فارغاً للإبقاء على كلمة المرور الحالية</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="users.php" class="btn btn-light">إلغاء</a>
                    <button type="submit" name="<?php echo $action === 'edit' ? 'update_user' : 'create_user'; ?>" 
                            class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i>
                        <?php echo $action === 'edit' ? 'حفظ التغييرات' : 'إضافة المستخدم'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Hidden Forms -->
<form id="resetPasswordForm" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="resetPasswordUserId">
    <input type="hidden" name="reset_password" value="1">
</form>

<form id="deleteUserForm" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="deleteUserId">
    <input type="hidden" name="delete_user" value="1">
</form>

<script>
function confirmResetPassword(userId) {
    if (confirm('Are you sure you want to reset this user\'s password?')) {
        document.getElementById('resetPasswordUserId').value = userId;
        document.getElementById('resetPasswordForm').submit();
    }
}

function confirmDelete(userId) {
    if (confirm('Are you sure you want to delete this user?')) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserForm').submit();
    }
}

// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// تحديث كود الدولة عند اختيار دولة
document.getElementById('country_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const countryCode = selectedOption.getAttribute('data-code');
    document.getElementById('phone-code').textContent = countryCode ? '+' + countryCode : '+';
    updateWhatsAppPreview();
});

// التحقق من صحة رقم الهاتف وتحديثه
document.getElementById('phone').addEventListener('input', function() {
    // حذف أي أحرف غير رقمية
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // حذف الصفر من البداية إذا وجد
    if (this.value.startsWith('0')) {
        this.value = this.value.substring(1);
    }
    
    updateWhatsAppPreview();
});

// عرض معاينة رقم الواتساب
function updateWhatsAppPreview() {
    const phone = document.getElementById('phone').value;
    const countryCode = document.getElementById('country_id').options[
        document.getElementById('country_id').selectedIndex
    ].getAttribute('data-code');
    
    const preview = document.getElementById('whatsapp-preview');
    const link = document.getElementById('whatsapp-link');
    const placeholder = document.getElementById('whatsapp-placeholder');
    
    if (phone && countryCode) {
        const whatsappNumber = countryCode + phone;
        preview.textContent = '+' + whatsappNumber;
        const cleanNumber = whatsappNumber.replace(/[^0-9]/g, '');
        link.href = 'https://wa.me/' + cleanNumber;
        link.style.display = 'flex';
        placeholder.style.display = 'none';
    } else {
        preview.textContent = '';
        link.href = '#';
        link.style.display = 'none';
        placeholder.style.display = 'block';
    }
}

// تهيئة رقم الهاتف عند تحميل الصفحة
window.addEventListener('load', function() {
    updateWhatsAppPreview();
});
</script>

<style>
/* تنسيقات عامة */
.card-header {
    background-color: #f8f9fa;
    border-bottom: none;
    padding: 1.5rem;
}

.btn-group {
    flex-wrap: wrap;
}

.btn-group > .btn {
    margin-bottom: 0.25rem;
}

/* تنسيقات الجدول */
.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

/* تنسيقات كارت المستخدم للموبايل */
.user-card {
    border: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.user-card:hover {
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}

/* تنسيقات الأزرار */
.btn-group .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .card-header {
        padding: 1rem;
    }
    
    .btn-group {
        width: 100%;
    }
    
    .btn-group > .btn {
        flex: 1;
    }
}

/* تنسيقات الأعلام */
.flag {
    width: 20px;
    height: 15px;
    display: inline-block;
    background-size: contain;
    background-position: center;
    background-repeat: no-repeat;
    vertical-align: middle;
}

/* تنسيق خاص لكل دولة */
.flag-sa { background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480"><path fill="%23006c35" d="M0 0h640v480H0z"/><path fill="%23fff" d="M144 144h352v192H144z"/></svg>'); }
.flag-eg { background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 480"><path fill="%23ce1126" d="M0 0h640v480H0z"/><path fill="%23fff" d="M0 160h640v160H0z"/><path d="M0 320h640v160H0z"/></svg>'); }
/* يمكنك إضافة المزيد من الأعلام حسب الحاجة */

.whatsapp-preview {
    min-height: 60px;
}

#whatsapp-placeholder {
    border: 2px dashed #dee2e6;
    border-radius: 6px;
}

.whatsapp-preview .btn {
    padding: 0.75rem;
    font-size: 1.1rem;
}

.whatsapp-preview .h5 {
    font-family: monospace;
    letter-spacing: 1px;
    margin: 0;
}
</style>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
