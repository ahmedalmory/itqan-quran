<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                if (isset($_POST['language_id'], $_POST['translation_key'], $_POST['translation_value'])) {
                    $stmt = $conn->prepare("INSERT INTO translations (language_id, translation_key, translation_value) VALUES (?, ?, ?)");
                    $stmt->bind_param('iss', 
                        $_POST['language_id'],
                        $_POST['translation_key'],
                        $_POST['translation_value']
                    );
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'تمت إضافة الترجمة بنجاح';
                    } else {
                        $_SESSION['error'] = 'حدث خطأ أثناء إضافة الترجمة';
                    }
                }
                break;
                
            case 'edit':
                if (isset($_POST['id'], $_POST['language_id'], $_POST['translation_key'], $_POST['translation_value'])) {
                    $stmt = $conn->prepare("UPDATE translations SET language_id = ?, translation_key = ?, translation_value = ? WHERE id = ?");
                    $stmt->bind_param('issi', 
                        $_POST['language_id'],
                        $_POST['translation_key'],
                        $_POST['translation_value'],
                        $_POST['id']
                    );
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'تم تحديث الترجمة بنجاح';
                    } else {
                        $_SESSION['error'] = 'حدث خطأ أثناء تحديث الترجمة';
                    }
                }
                break;
                
            case 'delete':
                if (isset($_POST['id'])) {
                    $stmt = $conn->prepare("DELETE FROM translations WHERE id = ?");
                    $stmt->bind_param('i', $_POST['id']);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'تم حذف الترجمة بنجاح';
                    } else {
                        $_SESSION['error'] = 'حدث خطأ أثناء حذف الترجمة';
                    }
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Ensure user is super admin
requireRole('super_admin');

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Get filters
$language_id = isset($_GET['language_id']) ? (int)$_GET['language_id'] : 0;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if ($language_id > 0) {
    $conditions[] = "t.language_id = ?";
    $params[] = $language_id;
    $types .= 'i';
}

if ($search) {
    $conditions[] = "(t.translation_key LIKE ? OR t.translation_value LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM translations t 
    JOIN languages l ON t.language_id = l.id 
    $where_clause
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_items = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get translations with language names
$query = "
    SELECT t.*, l.name as language_name, l.code as language_code
    FROM translations t
    JOIN languages l ON t.language_id = l.id
    $where_clause
    ORDER BY l.name, t.translation_key
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $params[] = $items_per_page;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $items_per_page, $offset);
}
$stmt->execute();
$translations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all languages for dropdown
$stmt = $conn->prepare("SELECT * FROM languages ORDER BY name");
$stmt->execute();
$languages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'إدارة التراجم';
ob_start();
?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="language_id" class="form-label">اللغة</label>
                <select class="form-select" id="language_id" name="language_id">
                    <option value="">جميع اللغات</option>
                    <?php foreach ($languages as $lang): ?>
                        <option value="<?php echo $lang['id']; ?>" <?php echo $language_id == $lang['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lang['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="search" class="form-label">بحث</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="ابحث في المفتاح أو النص المترجم...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">بحث</button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php 
        echo $_SESSION['success'];
        unset($_SESSION['success']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php 
        echo $_SESSION['error'];
        unset($_SESSION['error']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">إدارة التراجم</h5>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTranslationModal">
            إضافة ترجمة جديدة
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>اللغة</th>
                        <th>المفتاح</th>
                        <th>النص المترجم</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($translations as $translation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($translation['language_name']); ?></td>
                            <td><?php echo htmlspecialchars($translation['translation_key']); ?></td>
                            <td><?php echo htmlspecialchars($translation['translation_value']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="editTranslation(<?php echo htmlspecialchars(json_encode($translation)); ?>)">
                                    تعديل
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteTranslation(<?php echo $translation['id']; ?>, '<?php echo htmlspecialchars($translation['translation_key']); ?>')">
                                    حذف
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=1<?php echo $language_id ? "&language_id=$language_id" : ''; ?><?php echo $search ? "&search=$search" : ''; ?>">
                    الأول
                </a>
            </li>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $language_id ? "&language_id=$language_id" : ''; ?><?php echo $search ? "&search=$search" : ''; ?>">
                    السابق
                </a>
            </li>
        <?php endif; ?>

        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);

        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $language_id ? "&language_id=$language_id" : ''; ?><?php echo $search ? "&search=$search" : ''; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $language_id ? "&language_id=$language_id" : ''; ?><?php echo $search ? "&search=$search" : ''; ?>">
                    التالي
                </a>
            </li>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $language_id ? "&language_id=$language_id" : ''; ?><?php echo $search ? "&search=$search" : ''; ?>">
                    الأخير
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Results count -->
<div class="text-center mt-2 text-muted">
    إجمالي النتائج: <?php echo $total_items; ?>
    <?php if ($total_items > 0): ?>
        (عرض <?php echo ($offset + 1); ?> إلى <?php echo min($offset + $items_per_page, $total_items); ?>)
    <?php endif; ?>
</div>

<!-- Add Translation Modal -->
<div class="modal fade" id="addTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة ترجمة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="language_id" class="form-label">اللغة</label>
                        <select class="form-select" id="language_id" name="language_id" required>
                            <?php foreach ($languages as $language): ?>
                                <option value="<?php echo $language['id']; ?>">
                                    <?php echo htmlspecialchars($language['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="translation_key" class="form-label">المفتاح</label>
                        <input type="text" class="form-control" id="translation_key" name="translation_key" required>
                    </div>
                    <div class="mb-3">
                        <label for="translation_value" class="form-label">النص المترجم</label>
                        <textarea class="form-control" id="translation_value" name="translation_value" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Translation Modal -->
<div class="modal fade" id="editTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل الترجمة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_language_id" class="form-label">اللغة</label>
                        <select class="form-select" id="edit_language_id" name="language_id" required>
                            <?php foreach ($languages as $language): ?>
                                <option value="<?php echo $language['id']; ?>">
                                    <?php echo htmlspecialchars($language['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_translation_key" class="form-label">المفتاح</label>
                        <input type="text" class="form-control" id="edit_translation_key" name="translation_key" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_translation_value" class="form-label">النص المترجم</label>
                        <textarea class="form-control" id="edit_translation_value" name="translation_value" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Translation Modal -->
<div class="modal fade" id="deleteTranslationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title">حذف الترجمة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>هل أنت متأكد من حذف الترجمة للمفتاح: <span id="delete_key"></span>؟</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">حذف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTranslation(translation) {
    document.getElementById('edit_id').value = translation.id;
    document.getElementById('edit_language_id').value = translation.language_id;
    document.getElementById('edit_translation_key').value = translation.translation_key;
    document.getElementById('edit_translation_value').value = translation.translation_value;
    
    new bootstrap.Modal(document.getElementById('editTranslationModal')).show();
}

function deleteTranslation(id, key) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_key').textContent = key;
    
    new bootstrap.Modal(document.getElementById('deleteTranslationModal')).show();
}
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
