<?php
require_once __DIR__ . '/language.php';

// Handle language switching
if (isset($_GET['lang'])) {
    set_language($_GET['lang']);
    
    // Get the full current URI
    $currentUri = $_SERVER['REQUEST_URI'];
    
    // Remove existing lang parameter if present
    $currentUri = preg_replace('/[?&]lang=[^&]+/', '', $currentUri);
    
    // Remove trailing ? or & if present
    $currentUri = rtrim($currentUri, '?&');
    
    // Redirect back to the same page without the lang parameter
    header("Location: $currentUri");
    exit;
}

$pageTitle = $pageTitle ?? 'Quran Study Circles';
$pageHeader = $pageHeader ?? '';
$dir = get_language_direction();
$language_code = get_current_language();
$error = $error ?? '';
$success = $success ?? '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - نظام حلقات القرآن</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #1FA959;
            --primary-dark: #198A47;
            --primary-light: #F0F9F2;
            --secondary-color: #8D6E63;
            --accent-color: #FFC107;
            --header-pattern: url("data:image/svg+xml,%3Csvg width='52' height='26' viewBox='0 0 52 26' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M10 10c0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6h2c0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4v2c-3.314 0-6-2.686-6-6 0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6zm25.464-1.95l8.486 8.486-1.414 1.414-8.486-8.486 1.414-1.414z' /%3E%3C/g%3E%3C/svg%3E");
        }

        body {
            font-family: 'Noto Kufi Arabic', sans-serif;
            background-color: #f8f9fa;
        }

        .navbar {
            background-color: var(--primary-color) !important;
            padding: 1rem 0;
            box-shadow: 0 2px 15px rgba(31, 169, 89, 0.1);
            position: relative;
            z-index: 1000;
        }

        .navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: var(--header-pattern);
            opacity: 0.1;
            pointer-events: none;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 600;
            font-size: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .nav-link:hover, .nav-link.active {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.3);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 0.9)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        /* تنسيقات عامة للصفحات */
        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(31, 169, 89, 0.15);
        }

        .btn {
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
        }

        .table {
            vertical-align: middle;
        }

        .table th {
            font-weight: 600;
            background-color: var(--primary-light);
            border: none;
        }

        /* تخصيص SweetAlert2 */
        .swal2-popup {
            font-family: 'Noto Kufi Arabic', sans-serif;
        }
        
        .swal2-confirm {
            background-color: var(--primary-color) !important;
        }
        
        .swal2-confirm:focus {
            box-shadow: 0 0 0 3px rgba(31, 169, 89, 0.5) !important;
        }
        
        .swal2-styled.swal2-cancel {
            background-color: #dc3545 !important;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main class="">
        <?php if (!empty($pageHeader)): ?>
            <div class="container">
                <h1 class="mb-4"><?php echo htmlspecialchars($pageHeader); ?></h1>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="container">
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="container">
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php echo $content; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-light py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo __('site_name'); ?></p>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
