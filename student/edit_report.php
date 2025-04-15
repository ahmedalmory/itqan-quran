<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

$date = $_GET['date'] ?? '';
$user_id = $_SESSION['user_id'];

// Get report
$stmt = $pdo->prepare("
    SELECT dr.*, s1.name as from_surah_name, s2.name as to_surah_name
    FROM daily_reports dr
    JOIN surahs s1 ON dr.memorization_from_surah = s1.id
    JOIN surahs s2 ON dr.memorization_to_surah = s2.id
    WHERE dr.student_id = ? AND dr.report_date = ?
");
$stmt->execute([$user_id, $date]);
$report = $stmt->fetch();

if (!$report) {
    header('Location: index.php');
    exit;
}

// Get surahs for dropdowns
$stmt = $pdo->query("SELECT id, name, total_verses FROM surahs ORDER BY id");
$surahs = $stmt->fetchAll();

$pageTitle = __('edit_report');
ob_start();
?>

<div class="container py-4">
    <form id="reportForm" class="needs-validation" novalidate>
        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('memorization_parts'); ?> (عدد الأوجه)</label>
                <div class="input-group">
                    <input type="text" class="form-control text-center" name="memorization_parts" id="memorization_parts" required readonly>
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustParts('memorization', 0.25)">+ ربع</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustParts('memorization', 0.5)">+ نصف</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustParts('memorization', 1)">+ 1</button>
                    <button type="button" class="btn btn-outline-danger" onclick="resetParts('memorization')">صفر</button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('revision_parts'); ?> (عدد الأوجه)</label>
                <div class="input-group">
                    <input type="text" class="form-control text-center" name="revision_parts" id="revision_parts" required readonly>
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustParts('revision', 0.25)">+ ربع</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustParts('revision', 0.5)">+ نصف</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="adjustParts('revision', 1)">+ 1</button>
                    <button type="button" class="btn btn-outline-danger" onclick="resetParts('revision')">صفر</button>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('from_surah'); ?></label>
                <select class="form-select" name="memorization_from_surah" required>
                    <?php foreach ($surahs as $surah): ?>
                        <option value="<?php echo $surah['id']; ?>" <?php if ($surah['id'] == $report['memorization_from_surah']) echo 'selected'; ?>>
                            <?php echo $surah['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('from_verse'); ?></label>
                <input type="number" class="form-control" name="memorization_from_verse" min="1" required
                       value="<?php echo $report['memorization_from_verse']; ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('to_surah'); ?></label>
                <select class="form-select" name="memorization_to_surah" required>
                    <?php foreach ($surahs as $surah): ?>
                        <option value="<?php echo $surah['id']; ?>" <?php if ($surah['id'] == $report['memorization_to_surah']) echo 'selected'; ?>>
                            <?php echo $surah['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('to_verse'); ?></label>
                <input type="number" class="form-control" name="memorization_to_verse" min="1" required
                       value="<?php echo $report['memorization_to_verse']; ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('grade'); ?></label>
                <input type="number" class="form-control" name="grade" min="0" max="100" step="1" required
                       value="<?php echo intval($report['grade']); ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('notes'); ?></label>
                <textarea class="form-control" name="notes" rows="1"><?php echo htmlspecialchars($report['notes']); ?></textarea>
            </div>
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> <?php echo __('update_report'); ?>
            </button>
        </div>
    </form>
</div>

<script>
// Store surahs data
const surahs = <?php echo json_encode($surahs); ?>;
const surahsMap = new Map(surahs.map(surah => [surah.id, surah]));

// Store parts values
let memorizationParts = <?php echo $report['memorization_parts']; ?>;
let revisionParts = <?php echo $report['revision_parts']; ?>;

// Function to format parts number
function formatParts(number) {
    if (number === Math.floor(number)) {
        return number.toString();
    } else if (number === Math.floor(number) + 0.5) {
        return number.toString();
    } else if (number === Math.floor(number) + 0.25) {
        return number.toString();
    }
    return number.toFixed(2);
}

// Function to adjust parts
function adjustParts(type, amount) {
    if (type === 'memorization') {
        memorizationParts += amount;
        if (memorizationParts > 20) memorizationParts = 20;
        document.getElementById('memorization_parts').value = formatParts(memorizationParts);
    } else {
        revisionParts += amount;
        if (revisionParts > 20) revisionParts = 20;
        document.getElementById('revision_parts').value = formatParts(revisionParts);
    }
}

// Function to reset parts
function resetParts(type) {
    if (type === 'memorization') {
        memorizationParts = 0;
        document.getElementById('memorization_parts').value = '0';
    } else {
        revisionParts = 0;
        document.getElementById('revision_parts').value = '0';
    }
}

// Update verse limits based on selected surah
function updateVerseLimits(selectElement, verseInput) {
    const surahId = selectElement.value;
    const surah = surahsMap.get(parseInt(surahId));
    verseInput.max = surah.total_verses;
    verseInput.placeholder = `1 - ${surah.total_verses}`;
    
    // If current value is greater than max, reset it
    if (parseInt(verseInput.value) > surah.total_verses) {
        verseInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const fromSurahSelect = document.querySelector('select[name="memorization_from_surah"]');
    const toSurahSelect = document.querySelector('select[name="memorization_to_surah"]');
    const fromVerseInput = document.querySelector('input[name="memorization_from_verse"]');
    const toVerseInput = document.querySelector('input[name="memorization_to_verse"]');
    document.getElementById('memorization_parts').value = formatParts(memorizationParts);
    document.getElementById('revision_parts').value = formatParts(revisionParts);

    // Set initial verse limits
    updateVerseLimits(fromSurahSelect, fromVerseInput);
    updateVerseLimits(toSurahSelect, toVerseInput);

    // Update verse limits when surah selection changes
    fromSurahSelect.addEventListener('change', () => updateVerseLimits(fromSurahSelect, fromVerseInput));
    toSurahSelect.addEventListener('change', () => updateVerseLimits(toSurahSelect, toVerseInput));

    // Form validation
    document.getElementById('reportForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const fromSurahId = parseInt(fromSurahSelect.value);
        const toSurahId = parseInt(toSurahSelect.value);
        const fromVerse = parseInt(fromVerseInput.value);
        const toVerse = parseInt(toVerseInput.value);
        
        const fromSurah = surahsMap.get(fromSurahId);
        const toSurah = surahsMap.get(toSurahId);
        
        // Validate verses
        if (fromVerse < 1 || fromVerse > fromSurah.total_verses) {
            alert('رقم آية البداية غير صحيح');
            return;
        }
        
        if (toVerse < 1 || toVerse > toSurah.total_verses) {
            alert('رقم آية النهاية غير صحيح');
            return;
        }
        
        if (fromSurahId === toSurahId && fromVerse > toVerse) {
            alert('رقم آية البداية يجب أن يكون أقل من أو يساوي رقم آية النهاية');
            return;
        }
        
        if (fromSurahId > toSurahId) {
            alert('سورة البداية يجب أن تكون قبل سورة النهاية');
            return;
        }

        // Validate that at least one of memorization or revision is greater than 0
        const memParts = parseFloat(document.getElementById('memorization_parts').value);
        const revParts = parseFloat(document.getElementById('revision_parts').value);
        
        if (memParts === 0 && revParts === 0) {
            alert('يجب إدخال قيمة أكبر من الصفر في الحفظ أو المراجعة');
            return;
        }

        const formData = new FormData(this);
        try {
            const response = await fetch('update_report.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                window.parent.location.href = 'index.php';
            } else {
                const error = await response.text();
                alert(error);
            }
        } catch (error) {
            alert('<?php echo __("error_updating_report"); ?>');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
