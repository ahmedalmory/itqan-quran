<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is super admin
requireRole('super_admin');

// Handle language operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize_input($_POST['name']);
                $code = sanitize_input($_POST['code']);
                $direction = sanitize_input($_POST['direction']);

                $stmt = $conn->prepare("INSERT INTO languages (name, code, direction) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $code, $direction);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "تمت إضافة اللغة بنجاح";
                } else {
                    $_SESSION['error'] = "حدث خطأ أثناء إضافة اللغة";
                }
                break;

            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitize_input($_POST['name']);
                $code = sanitize_input($_POST['code']);
                $direction = sanitize_input($_POST['direction']);

                $stmt = $conn->prepare("UPDATE languages SET name = ?, code = ?, direction = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $code, $direction, $id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "تم تحديث اللغة بنجاح";
                } else {
                    $_SESSION['error'] = "حدث خطأ أثناء تحديث اللغة";
                }
                break;

            case 'delete':
                $id = (int)$_POST['id'];
                
                // Check if language is used in translations
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM translations WHERE language_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    $_SESSION['error'] = "لا يمكن حذف اللغة لوجود تراجم مرتبطة بها";
                } else {
                    $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "تم حذف اللغة بنجاح";
                    } else {
                        $_SESSION['error'] = "حدث خطأ أثناء حذف اللغة";
                    }
                }
                break;
        }
        
        header('Location: languages.php');
        exit();
    }
}

// Get all languages
$stmt = $conn->prepare("SELECT * FROM languages ORDER BY name");
$stmt->execute();
$languages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'إدارة اللغات';
ob_start();
?>

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
        <h5 class="card-title mb-0">إدارة اللغات</h5>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLanguageModal">
            إضافة لغة جديدة
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الكود</th>
                        <th>اتجاه الكتابة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($languages as $language): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($language['name']); ?></td>
                            <td><?php echo htmlspecialchars($language['code']); ?></td>
                            <td>
                                <?php echo $language['direction'] === 'rtl' ? 'من اليمين إلى اليسار' : 'من اليسار إلى اليمين'; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="editLanguage(<?php echo htmlspecialchars(json_encode($language)); ?>)">
                                    تعديل
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteLanguage(<?php echo $language['id']; ?>, '<?php echo htmlspecialchars($language['name']); ?>')">
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

<!-- Add Language Modal -->
<div class="modal fade" id="addLanguageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة لغة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">اسم اللغة</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="code" class="form-label">كود اللغة</label>
                        <input type="text" class="form-control" id="code" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label for="direction" class="form-label">اتجاه الكتابة</label>
                        <select class="form-select" id="direction" name="direction" required>
                            <option value="rtl">من اليمين إلى اليسار</option>
                            <option value="ltr">من اليسار إلى اليمين</option>
                        </select>
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

<!-- Edit Language Modal -->
<div class="modal fade" id="editLanguageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل اللغة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">اسم اللغة</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_code" class="form-label">كود اللغة</label>
                        <input type="text" class="form-control" id="edit_code" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_direction" class="form-label">اتجاه الكتابة</label>
                        <select class="form-select" id="edit_direction" name="direction" required>
                            <option value="rtl">من اليمين إلى اليسار</option>
                            <option value="ltr">من اليسار إلى اليمين</option>
                        </select>
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

<!-- Delete Language Modal -->
<div class="modal fade" id="deleteLanguageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title">حذف اللغة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>هل أنت متأكد من حذف اللغة: <span id="delete_name"></span>؟</p>
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
function editLanguage(language) {
    document.getElementById('edit_id').value = language.id;
    document.getElementById('edit_name').value = language.name;
    document.getElementById('edit_code').value = language.code;
    document.getElementById('edit_direction').value = language.direction;
    
    new bootstrap.Modal(document.getElementById('editLanguageModal')).show();
}

function deleteLanguage(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    
    new bootstrap.Modal(document.getElementById('deleteLanguageModal')).show();
}
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
