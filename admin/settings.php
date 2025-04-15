<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is super admin
requireRole('super_admin');

$success_message = '';
$error_message = '';

// Get current settings
$stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        // System settings
        $site_name = sanitize_input($_POST['site_name']);
        $site_description = sanitize_input($_POST['site_description']);
        $admin_email = sanitize_input($_POST['admin_email']);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        
        // Email settings
        $smtp_host = sanitize_input($_POST['smtp_host']);
        $smtp_port = (int)$_POST['smtp_port'];
        $smtp_username = sanitize_input($_POST['smtp_username']);
        $smtp_password = $_POST['smtp_password']; // Only update if not empty
        $smtp_encryption = sanitize_input($_POST['smtp_encryption']);
        
        // Academic settings
        $academic_year = sanitize_input($_POST['academic_year']);
        $semester = sanitize_input($_POST['semester']);
        $registration_open = isset($_POST['registration_open']) ? 1 : 0;
        
        // Update settings
        $query = "
            UPDATE settings SET
                site_name = ?,
                site_description = ?,
                admin_email = ?,
                maintenance_mode = ?,
                smtp_host = ?,
                smtp_port = ?,
                smtp_username = ?,
                smtp_encryption = ?,
                academic_year = ?,
                semester = ?,
                registration_open = ?
        ";
        
        $params = [
            $site_name,
            $site_description,
            $admin_email,
            $maintenance_mode,
            $smtp_host,
            $smtp_port,
            $smtp_username,
            $smtp_encryption,
            $academic_year,
            $semester,
            $registration_open
        ];
        $types = "sssississsi";
        
        // Only update SMTP password if provided
        if (!empty($_POST['smtp_password'])) {
            $query .= ", smtp_password = ?";
            $params[] = $_POST['smtp_password'];
            $types .= "s";
        }
        
        $query .= " WHERE id = 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $success_message = "Settings updated successfully.";
            // Refresh settings
            $stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
            $stmt->execute();
            $settings = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Error updating settings.";
        }
    }
}

$pageTitle = 'System Settings';
$pageHeader = 'System Settings';
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
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <!-- System Settings -->
                    <h5 class="card-title mb-3">System Settings</h5>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name" required
                                   value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="admin_email" class="form-label">Admin Email</label>
                            <input type="email" class="form-control" id="admin_email" name="admin_email" required
                                   value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label for="site_description" class="form-label">Site Description</label>
                            <textarea class="form-control" id="site_description" name="site_description" rows="2"><?php 
                                echo htmlspecialchars($settings['site_description']); 
                            ?></textarea>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="maintenance_mode" 
                                       name="maintenance_mode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="maintenance_mode">Maintenance Mode</label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Email Settings -->
                    <h5 class="card-title mb-3">Email Settings</h5>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="smtp_host" class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                   value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="smtp_port" class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                   value="<?php echo $settings['smtp_port']; ?>">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="smtp_username" class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                   value="<?php echo htmlspecialchars($settings['smtp_username']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="smtp_password" class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                   placeholder="Leave empty to keep current password">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="smtp_encryption" class="form-label">SMTP Encryption</label>
                            <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <!-- Academic Settings -->
                    <h5 class="card-title mb-3">Academic Settings</h5>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" required
                                   value="<?php echo htmlspecialchars($settings['academic_year']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="semester" class="form-label">Current Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="first" <?php echo $settings['semester'] === 'first' ? 'selected' : ''; ?>>First Semester</option>
                                <option value="second" <?php echo $settings['semester'] === 'second' ? 'selected' : ''; ?>>Second Semester</option>
                                <option value="summer" <?php echo $settings['semester'] === 'summer' ? 'selected' : ''; ?>>Summer Semester</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="registration_open" 
                                       name="registration_open" <?php echo $settings['registration_open'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="registration_open">Registration Open</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                Update Settings
                            </button>
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
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>
