<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get all surahs for dropdowns
$surahs = $conn->query("SELECT id, name, total_verses FROM surahs ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_date = sanitize_input($_POST['report_date']);
    $memorization_parts = (float)$_POST['memorization_parts'];
    $revision_parts = (float)$_POST['revision_parts'];
    $grade = (float)$_POST['grade'];
    $memorization_from_surah = (int)$_POST['memorization_from_surah'];
    $memorization_from_verse = (int)$_POST['memorization_from_verse'];
    $memorization_to_surah = (int)$_POST['memorization_to_surah'];
    $memorization_to_verse = (int)$_POST['memorization_to_verse'];
    $notes = sanitize_input($_POST['notes']);

    // Validate inputs
    if ($memorization_parts < 0 || $memorization_parts > 30 ||
        $revision_parts < 0 || $revision_parts > 30 ||
        $grade < 0 || $grade > 100) {
        $error_message = "القيم المدخلة غير صحيحة";
    } else {
        // Check if report already exists for this date
        $stmt = $conn->prepare("SELECT id FROM daily_reports WHERE student_id = ? AND report_date = ?");
        $stmt->bind_param("is", $user_id, $report_date);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error_message = "يوجد تقرير مسجل لهذا اليوم";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO daily_reports (
                    student_id, report_date, memorization_parts, revision_parts,
                    grade, memorization_from_surah, memorization_from_verse,
                    memorization_to_surah, memorization_to_verse, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "isdddiiiis",
                $user_id, $report_date, $memorization_parts, $revision_parts,
                $grade, $memorization_from_surah, $memorization_from_verse,
                $memorization_to_surah, $memorization_to_verse, $notes
            );

            if ($stmt->execute()) {
                $success_message = "تم تسجيل التقرير بنجاح";
            } else {
                $error_message = "حدث خطأ أثناء تسجيل التقرير";
            }
        }
    }
}

// Get recent reports
$stmt = $conn->prepare("
    SELECT dr.*, s1.name as from_surah_name, s2.name as to_surah_name
    FROM daily_reports dr
    JOIN surahs s1 ON dr.memorization_from_surah = s1.id
    JOIN surahs s2 ON dr.memorization_to_surah = s2.id
    WHERE dr.student_id = ?
    ORDER BY dr.report_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'التقرير اليومي';
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

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">تسجيل التقرير اليومي</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="report_date" class="form-label">تاريخ التقرير</label>
                            <input type="date" class="form-control" id="report_date" name="report_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="grade" class="form-label">الدرجة</label>
                            <input type="number" class="form-control" id="grade" name="grade" 
                                   min="0" max="100" step="0.5" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="memorization_parts" class="form-label">أجزاء الحفظ</label>
                            <input type="number" class="form-control" id="memorization_parts" name="memorization_parts" 
                                   min="0" max="30" step="0.25" required>
                        </div>
                        <div class="col-md-6">
                            <label for="revision_parts" class="form-label">أجزاء المراجعة</label>
                            <input type="number" class="form-control" id="revision_parts" name="revision_parts" 
                                   min="0" max="30" step="0.25" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="memorization_from_surah" class="form-label">الحفظ من سورة</label>
                            <select class="form-select" id="memorization_from_surah" name="memorization_from_surah" required>
                                <option value="">اختر السورة</option>
                                <?php foreach ($surahs as $surah): ?>
                                    <option value="<?php echo $surah['id']; ?>" data-verses="<?php echo $surah['total_verses']; ?>">
                                        <?php echo htmlspecialchars($surah['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="memorization_from_verse" class="form-label">من آية</label>
                            <input type="number" class="form-control" id="memorization_from_verse" name="memorization_from_verse" 
                                   min="1" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="memorization_to_surah" class="form-label">الحفظ إلى سورة</label>
                            <select class="form-select" id="memorization_to_surah" name="memorization_to_surah" required>
                                <option value="">اختر السورة</option>
                                <?php foreach ($surahs as $surah): ?>
                                    <option value="<?php echo $surah['id']; ?>" data-verses="<?php echo $surah['total_verses']; ?>">
                                        <?php echo htmlspecialchars($surah['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="memorization_to_verse" class="form-label">إلى آية</label>
                            <input type="number" class="form-control" id="memorization_to_verse" name="memorization_to_verse" 
                                   min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">تسجيل التقرير</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">آخر التقارير</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_reports)): ?>
                    <p class="text-muted">لا توجد تقارير سابقة</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_reports as $report): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?php echo date('Y-m-d', strtotime($report['report_date'])); ?></strong>
                                    <span class="badge bg-success"><?php echo $report['grade']; ?>%</span>
                                </div>
                                <div class="small text-muted">
                                    الحفظ: من <?php echo htmlspecialchars($report['from_surah_name']); ?> 
                                    (<?php echo $report['memorization_from_verse']; ?>) 
                                    إلى <?php echo htmlspecialchars($report['to_surah_name']); ?>
                                    (<?php echo $report['memorization_to_verse']; ?>)
                                </div>
                                <div class="small">
                                    <span class="text-primary">الحفظ: <?php echo $report['memorization_parts']; ?> جزء</span>
                                    <span class="text-success me-2">المراجعة: <?php echo $report['revision_parts']; ?> جزء</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
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

// Update verse limits based on selected surah
function updateVerseLimit(surahSelect, verseInput) {
    surahSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const totalVerses = selectedOption.getAttribute('data-verses');
        verseInput.max = totalVerses;
        verseInput.value = Math.min(verseInput.value, totalVerses);
    });
}

updateVerseLimit(
    document.getElementById('memorization_from_surah'),
    document.getElementById('memorization_from_verse')
);
updateVerseLimit(
    document.getElementById('memorization_to_surah'),
    document.getElementById('memorization_to_verse')
);
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
