<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Ensure user is logged in and has just registered
if (!isset($_SESSION['registration_success']) || !isset($_SESSION['available_circles'])) {
    header("Location: login.php");
    exit();
}

$available_circles = $_SESSION['available_circles'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['circle_id'])) {
    $circle_id = (int)$_POST['circle_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify circle is still available
    $stmt = $conn->prepare("SELECT c.* FROM study_circles c
                           WHERE c.id = ? AND (
                               SELECT COUNT(*) FROM circle_students 
                               WHERE circle_id = c.id
                           ) < c.max_students");
    $stmt->bind_param("i", $circle_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Add student to circle
        $stmt = $conn->prepare("INSERT INTO circle_students (circle_id, student_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $circle_id, $user_id);
        
        if ($stmt->execute()) {
            // Clear registration session variables
            unset($_SESSION['registration_success']);
            unset($_SESSION['available_circles']);
            
            header("Location: student/dashboard.php");
            exit();
        }
    }
    
    $error = __('circle_no_longer_available');
}

$pageTitle = __('select_circle');
ob_start();
?>

<div class="select-circle-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="select-circle-box">
                    <div class="text-center mb-4">
                        <h1 class="select-circle-title"><?php echo __('select_circle'); ?></h1>
                        <p class="select-circle-subtitle"><?php echo __('select_circle_description'); ?></p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($available_circles)): ?>
                        <div class="alert alert-info">
                            <?php echo __('no_circles_available'); ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" class="circle-selection-form">
                            <div class="row g-3">
                                <?php foreach ($available_circles as $circle): ?>
                                <div class="col-md-6">
                                    <div class="circle-card">
                                        <input type="radio" class="btn-check" name="circle_id" 
                                               id="circle_<?php echo $circle['id']; ?>" 
                                               value="<?php echo $circle['id']; ?>" required>
                                        <label class="card h-100" for="circle_<?php echo $circle['id']; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($circle['name']); ?></h5>
                                                <h6 class="card-subtitle mb-3 text-muted">
                                                    <?php echo htmlspecialchars($circle['department_name']); ?>
                                                </h6>
                                                <div class="circle-info">
                                                    <p>
                                                        <i class="bi bi-person-circle"></i>
                                                        <span class="info-label"><?php echo __('teacher'); ?>:</span>
                                                        <?php echo htmlspecialchars($circle['teacher_name']); ?>
                                                    </p>
                                                    <p>
                                                        <i class="bi bi-clock"></i>
                                                        <span class="info-label"><?php echo __('circle_time'); ?>:</span>
                                                        <?php echo format_prayer_time($circle['circle_time']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <?php echo __('confirm_circle_selection'); ?>
                                </button>
                                <a href="register_step1.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> <?php echo __('choose_different_time'); ?>
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.select-circle-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 2rem 0;
}

.select-circle-box {
    background: white;
    border-radius: 15px;
    padding: 2.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.select-circle-logo {
    max-width: 120px;
}

.select-circle-title {
    font-size: 1.75rem;
    color: var(--primary-dark);
    margin-bottom: 0.5rem;
}

.select-circle-subtitle {
    color: #666;
}

.circle-card .card {
    transition: all 0.3s ease;
    border: 2px solid #eee;
    cursor: pointer;
}

.circle-card .card:hover {
    border-color: var(--primary-color);
    transform: translateY(-3px);
}

.btn-check:checked + .card {
    border-color: var(--primary-color);
    background-color: rgba(var(--primary-rgb), 0.05);
}

.circle-info p {
    margin-bottom: 0.5rem;
    color: #666;
    font-size: 0.9rem;
}

.circle-info i {
    color: var(--primary-color);
    margin-left: 0.5rem;
    margin-right: 0.5rem;
}

.info-label {
    font-weight: 600;
    color: #444;
}

@media (max-width: 768px) {
    .select-circle-box {
        padding: 1.5rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
