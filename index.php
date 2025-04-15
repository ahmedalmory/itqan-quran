<?php

require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Get system settings
$settings = [
    'site_name' => 'Quran Study Circles',
    'site_description' => 'Welcome to our Quran Study Circles Management System - Your journey to memorizing the Holy Quran starts here'
];

$stmt = $conn->prepare("SELECT site_name, site_description FROM settings WHERE id = 1");
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $settings = $result;
    }
}

// Get statistics with error handling
$stats = [
    'departments' => 0,
    'teachers' => 0,
    'students' => 0,
    'circles' => 0
];

try {
    $stats['departments'] = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];
    $stats['teachers'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch_assoc()['count'];
    $stats['students'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
    $stats['circles'] = $conn->query("SELECT COUNT(*) as count FROM study_circles")->fetch_assoc()['count'];
} catch (Exception $e) {
    // Silently handle any database errors
}

$pageTitle = 'الرئيسية';
ob_start();
?>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="hero-title">
                    <span class="bismillah">بسم الله الرحمن الرحيم</span>
                    نظام إدارة حلقات القرآن الكريم
                </h1>
                <p class="hero-subtitle">
                    "خَيْرُكُمْ مَنْ تَعَلَّمَ القُرْآنَ وَعَلَّمَهُ"
                </p>
                <?php if (!isLoggedIn()): ?>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn btn-primary btn-lg">تسجيل الدخول</a>
                        <a href="register.php" class="btn btn-outline-primary btn-lg">إنشاء حساب</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <div class="hero-image">
                    <img src="assets/images/quran-hero.png" alt="القرآن الكريم">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Section -->
<div class="container mb-5" style="margin-top: -75px;">
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-building fs-1 text-primary mb-3"></i>
                    <h3 class="card-title"><?php echo $stats['departments']; ?></h3>
                    <p class="card-text"><?php echo __('departments_count'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-person-workspace fs-1 text-success mb-3"></i>
                    <h3 class="card-title"><?php echo $stats['teachers']; ?></h3>
                    <p class="card-text"><?php echo __('teachers_count'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-people fs-1 text-info mb-3"></i>
                    <h3 class="card-title"><?php echo $stats['students']; ?></h3>
                    <p class="card-text"><?php echo __('students_count'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="bi bi-book fs-1 text-warning mb-3"></i>
                    <h3 class="card-title"><?php echo $stats['circles']; ?></h3>
                    <p class="card-text"><?php echo __('circles_count'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title">مميزات النظام</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-book"></i>
                    </div>
                    <h3>متابعة الحفظ</h3>
                    <p>متابعة دقيقة لتقدم الطلاب في حفظ القرآن الكريم</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h3>تقارير مفصلة</h3>
                    <p>إحصائيات وتقارير شاملة عن مستوى الأداء</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3>إدارة الحلقات</h3>
                    <p>نظام متكامل لإدارة الحلقات والمعلمين والطلاب</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<div class="bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h2 class="mb-4"><?php echo __('contact_us'); ?></h2>
                <form id="contactForm" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" placeholder="<?php echo __('your_name'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <input type="email" class="form-control" placeholder="<?php echo __('your_email'); ?>" required>
                        </div>
                        <div class="col-12">
                            <input type="text" class="form-control" placeholder="<?php echo __('subject'); ?>" required>
                        </div>
                        <div class="col-12">
                            <textarea class="form-control" rows="5" placeholder="<?php echo __('your_message'); ?>" required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><?php echo __('send_message'); ?></button>
                        </div>
                    </div>
                </form>
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
            event.preventDefault()
            if (!form.checkValidity()) {
                event.stopPropagation()
            } else {
                // Handle form submission
                alert('<?php echo __('contact_success'); ?>')
                form.reset()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<style>
:root {
    --primary-color: #1FA959;
    --primary-dark: #198A47;
    --primary-light: #F0F9F2;
    --gold: #FFD700;
    --islamic-pattern: url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10S20 25.523 20 20zm30 0c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10-10-4.477-10-10zM20 50c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10-10-4.477-10-10zm30 0c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10-10-4.477-10-10z' fill='%23198A47' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
}

.hero-section {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 6rem 0;
    position: relative;
    overflow: hidden;
    color: white;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: var(--islamic-pattern);
    opacity: 0.1;
    z-index: 1;
}

.hero-section .container {
    position: relative;
    z-index: 2;
}

.bismillah {
    display: block;
    font-family: 'Noto Naskh Arabic', serif;
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--gold);
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.hero-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    line-height: 1.3;
}

.hero-subtitle {
    font-family: 'Noto Naskh Arabic', serif;
    font-size: 1.5rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.hero-buttons {
    display: flex;
    gap: 1rem;
    position: relative;
    z-index: 3;
}

.hero-buttons .btn {
    padding: 0.75rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-outline-primary {
    color: white;
    border-color: white;
    background-color: transparent;
}

.btn-outline-primary:hover {
    background-color: white;
    color: var(--primary-color);
    border-color: white;
}

.btn-primary {
    background-color: white;
    color: var(--primary-color);
    border-color: white;
}

.btn-primary:hover {
    background-color: var(--primary-light);
    color: var(--primary-color);
    border-color: white;
}

.hero-image {
    text-align: center;
}

.hero-image img {
    max-width: 100%;
    height: auto;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,0.2));
}

.features-section {
    padding: 6rem 0;
    background-color: white;
}

.section-title {
    text-align: center;
    color: var(--primary-dark);
    font-weight: 700;
    margin-bottom: 3rem;
    position: relative;
}

.section-title::after {
    content: '';
    display: block;
    width: 80px;
    height: 4px;
    background-color: var(--primary-color);
    margin: 1rem auto;
    border-radius: 2px;
}

.feature-card {
    text-align: center;
    padding: 2rem;
    background-color: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(31, 169, 89, 0.1);
}

.feature-icon {
    width: 80px;
    height: 80px;
    background-color: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}

.feature-icon i {
    font-size: 2rem;
    color: var(--primary-color);
}

.feature-card h3 {
    color: var(--primary-dark);
    font-weight: 600;
    margin-bottom: 1rem;
}

.feature-card p {
    color: #666;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .hero-section {
        padding: 4rem 0;
    }

    .bismillah {
        font-size: 2rem;
    }

    .hero-title {
        font-size: 2rem;
    }

    .hero-subtitle {
        font-size: 1.25rem;
    }

    .hero-image {
        margin-top: 3rem;
    }
}
</style>

<!-- إضافة خط Noto Naskh Arabic للنصوص العربية التقليدية -->
<link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;700&display=swap" rel="stylesheet">

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>
