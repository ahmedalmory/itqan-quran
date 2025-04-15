<?php
// Start output buffering
ob_start();

require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is student
requireRole('student');

$user_id = $_SESSION['user_id'];

// Get student's circle information
$stmt = $conn->prepare("
    SELECT 
        c.*, 
        d.name as department_name,
        d.student_gender,
        d.monthly_fees,
        d.quarterly_fees,
        d.biannual_fees,
        d.annual_fees,
        d.work_sunday,
        d.work_monday,
        d.work_tuesday,
        d.work_wednesday,
        d.work_thursday,
        d.work_friday,
        d.work_saturday,
        t.name as teacher_name,
        t.email as teacher_email,
        t.phone as teacher_phone,
        s.name as supervisor_name,
        s.email as supervisor_email,
        s.phone as supervisor_phone,
        (SELECT COUNT(*) FROM circle_students WHERE circle_id = c.id) as total_students
    FROM circle_students cs
    JOIN study_circles c ON cs.circle_id = c.id
    JOIN departments d ON c.department_id = d.id
    LEFT JOIN users t ON c.teacher_id = t.id
    LEFT JOIN users s ON c.supervisor_id = s.id
    WHERE cs.student_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$circle = $stmt->get_result()->fetch_assoc();

// Get working days array
$working_days = [];
if ($circle) {
    $days = [
        'sunday' => $circle['work_sunday'],
        'monday' => $circle['work_monday'],
        'tuesday' => $circle['work_tuesday'],
        'wednesday' => $circle['work_wednesday'],
        'thursday' => $circle['work_thursday'],
        'friday' => $circle['work_friday'],
        'saturday' => $circle['work_saturday']
    ];
    
    foreach ($days as $day => $works) {
        if ($works) {
            $working_days[] = $day;
        }
    }
}

$pageTitle = 'معلومات الحلقة';
ob_start();
?>

<?php if (!$circle): ?>
    <div class="alert alert-warning">
        لم يتم تسجيلك في أي حلقة بعد
    </div>
<?php else: ?>

<div class="row">
    <!-- Circle Basic Info -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">معلومات الحلقة</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <strong>اسم الحلقة:</strong>
                        <p class="mb-0"><?php echo htmlspecialchars($circle['name']); ?></p>
                    </li>
                    <li class="list-group-item">
                        <strong>القسم:</strong>
                        <p class="mb-0"><?php echo htmlspecialchars($circle['department_name']); ?></p>
                    </li>
                    <li class="list-group-item">
                        <strong>وقت الحلقة:</strong>
                        <p class="mb-0">
                            <?php
                            $times = [
                                'after_fajr' => 'بعد الفجر',
                                'after_dhuhr' => 'بعد الظهر',
                                'after_asr' => 'بعد العصر',
                                'after_maghrib' => 'بعد المغرب',
                                'after_isha' => 'بعد العشاء'
                            ];
                            echo $times[$circle['circle_time']];
                            ?>
                        </p>
                    </li>
                    <li class="list-group-item">
                        <strong>عدد الطلاب:</strong>
                        <p class="mb-0"><?php echo $circle['total_students']; ?> / <?php echo $circle['max_students']; ?></p>
                    </li>
                    <li class="list-group-item">
                        <strong>الفئة العمرية:</strong>
                        <p class="mb-0">من <?php echo $circle['age_from']; ?> إلى <?php echo $circle['age_to']; ?> سنة</p>
                    </li>
                    <li class="list-group-item">
                        <strong>أيام العمل:</strong>
                        <p class="mb-0">
                            <?php
                            $days_ar = [
                                'sunday' => 'الأحد',
                                'monday' => 'الإثنين',
                                'tuesday' => 'الثلاثاء',
                                'wednesday' => 'الأربعاء',
                                'thursday' => 'الخميس',
                                'friday' => 'الجمعة',
                                'saturday' => 'السبت'
                            ];
                            if (!empty($working_days)) {
                                echo implode(' - ', array_map(function($day) use ($days_ar) {
                                    return $days_ar[$day];
                                }, $working_days));
                            } else {
                                echo 'لم يتم تحديد أيام العمل';
                            }
                            ?>
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Teacher & Supervisor Info -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">المعلم والمشرف</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6 class="card-subtitle mb-3">المعلم</h6>
                    <?php if ($circle['teacher_name']): ?>
                        <ul class="list-unstyled">
                            <li><strong>الاسم:</strong> <?php echo htmlspecialchars($circle['teacher_name']); ?></li>
                            <li><strong>البريد:</strong> <?php echo htmlspecialchars($circle['teacher_email']); ?></li>
                            <li><strong>الجوال:</strong> <?php echo htmlspecialchars($circle['teacher_phone']); ?></li>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">لم يتم تعيين معلم بعد</p>
                    <?php endif; ?>
                </div>

                <div>
                    <h6 class="card-subtitle mb-3">المشرف</h6>
                    <?php if ($circle['supervisor_name']): ?>
                        <ul class="list-unstyled">
                            <li><strong>الاسم:</strong> <?php echo htmlspecialchars($circle['supervisor_name']); ?></li>
                            <li><strong>البريد:</strong> <?php echo htmlspecialchars($circle['supervisor_email']); ?></li>
                            <li><strong>الجوال:</strong> <?php echo htmlspecialchars($circle['supervisor_phone']); ?></li>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">لم يتم تعيين مشرف بعد</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Communication Groups -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">مجموعات التواصل</h5>
            </div>
            <div class="card-body">
                <?php if ($circle['whatsapp_group'] || $circle['telegram_group']): ?>
                    <?php if ($circle['whatsapp_group']): ?>
                        <a href="<?php echo htmlspecialchars($circle['whatsapp_group']); ?>" 
                           class="btn btn-success mb-3 w-100">
                            <i class="bi bi-whatsapp"></i> مجموعة الواتساب
                        </a>
                    <?php endif; ?>

                    <?php if ($circle['telegram_group']): ?>
                        <a href="<?php echo htmlspecialchars($circle['telegram_group']); ?>" 
                           class="btn btn-primary w-100">
                            <i class="bi bi-telegram"></i> مجموعة التلجرام
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">لم يتم إضافة روابط لمجموعات التواصل بعد</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Fees Info -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">الرسوم الدراسية</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>المدة</th>
                                <th>المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>شهري</td>
                                <td><?php echo number_format($circle['monthly_fees']); ?> ريال</td>
                            </tr>
                            <tr>
                                <td>ربع سنوي</td>
                                <td><?php echo number_format($circle['quarterly_fees']); ?> ريال</td>
                            </tr>
                            <tr>
                                <td>نصف سنوي</td>
                                <td><?php echo number_format($circle['biannual_fees']); ?> ريال</td>
                            </tr>
                            <tr>
                                <td>سنوي</td>
                                <td><?php echo number_format($circle['annual_fees']); ?> ريال</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($circle['description']): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">وصف الحلقة</h5>
    </div>
    <div class="card-body">
        <?php echo nl2br(htmlspecialchars($circle['description'])); ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
