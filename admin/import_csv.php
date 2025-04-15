<?php
// Set maximum execution time
set_time_limit(300); // 5 minutes

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Ensure user is super admin
requireRole('super_admin');

$success_message = '';
$error_message = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file'];
        $type = $_POST['import_type'];
        
        // Validate file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new Exception('Please upload a CSV file');
        }

        // Set UTF-8 encoding
        mb_internal_encoding('UTF-8');
        
        // Read file content and force UTF-8
        $content = file_get_contents($file['tmp_name']);
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, ASCII');
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== pack('CCC', 0xEF, 0xBB, 0xBF)) {
            rewind($handle);
        }

        // Read headers
        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new Exception('Error reading CSV headers');
        }

        // Convert headers to UTF-8 if needed
        $headers = array_map(function($header) {
            return mb_convert_encoding($header, 'UTF-8', 'auto');
        }, $headers);

        $conn->begin_transaction();
        $row_count = 0;
        $line_number = 2; // Start from line 2 as line 1 is headers
        $errors = [];

        while (($data = fgetcsv($handle)) !== false) {
            try {
                // Convert data to UTF-8
                $data = array_map(function($field) {
                    return mb_convert_encoding($field, 'UTF-8', 'auto');
                }, $data);

                // Validate required fields are not empty
                foreach ($data as $index => $field) {
                    if (trim($field) === '' && !in_array($headers[$index], ['country_id'])) {
                        throw new Exception("Field '{$headers[$index]}' cannot be empty");
                    }
                }

                switch ($type) {
                    case 'users':
                        if (count($data) >= 7) {
                            $name = $data[0];
                            $email = $data[1];
                            $phone = $data[2];
                            $age = (int)$data[3];
                            $gender = $data[4];
                            $role = $data[5];
                            $country_id = $data[6];
                            $preferred_time = !empty($data[7]) ? $data[7] : null;
                            
                            // Generate random password
                            $password = bin2hex(random_bytes(4));
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            $stmt = $conn->prepare("
                                INSERT INTO users (name, email, phone, password, age, gender, role, country_id, preferred_time)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->bind_param("ssssissss", $name, $email, $phone, $hashed_password, $age, $gender, $role, $country_id, $preferred_time);
                            $stmt->execute();
                            $row_count++;
                        }
                        break;
                        
                    case 'departments':
                        if (count($data) >= 5) {
                            $name = $data[0];
                            $student_gender = $data[1];
                            $monthly_fees = (int)$data[2];
                            $quarterly_fees = (int)$data[3];
                            $biannual_fees = (int)$data[4];
                            $annual_fees = (int)$data[5];
                            
                            $stmt = $conn->prepare("
                                INSERT INTO departments (name, student_gender, monthly_fees, quarterly_fees, biannual_fees, annual_fees)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->bind_param("ssiiii", $name, $student_gender, $monthly_fees, $quarterly_fees, $biannual_fees, $annual_fees);
                            $stmt->execute();
                            $row_count++;
                        }
                        break;
                        
                    case 'circles':
                        if (count($data) >= 8) {
                            $name = $data[0];
                            $description = $data[1];
                            $department_id = (int)$data[2];
                            $teacher_id = !empty($data[3]) ? (int)$data[3] : null;
                            $supervisor_id = !empty($data[4]) ? (int)$data[4] : null;
                            $max_students = (int)$data[5];
                            $age_from = (int)$data[6];
                            $age_to = (int)$data[7];
                            $circle_time = $data[8];
                            
                            $stmt = $conn->prepare("
                                INSERT INTO study_circles (name, description, department_id, teacher_id, supervisor_id, 
                                                                max_students, age_from, age_to, circle_time)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->bind_param("ssiiiiiis", $name, $description, $department_id, $teacher_id, $supervisor_id,
                                            $max_students, $age_from, $age_to, $circle_time);
                            $stmt->execute();
                            $row_count++;
                        }
                        break;

                    case 'circle_students':
                        if (count($data) >= 2) {
                            $circle_id = (int)$data[0];
                            $student_id = (int)$data[1];
                            
                            // Check if student exists in the circle
                            $check_stmt = $conn->prepare("
                                SELECT id FROM circle_students 
                                WHERE circle_id = ? AND student_id = ?
                            ");
                            $check_stmt->bind_param("ii", $circle_id, $student_id);
                            $check_stmt->execute();
                            $result = $check_stmt->get_result();
                            
                            if ($result->num_rows === 0) {
                                $stmt = $conn->prepare("
                                    INSERT INTO circle_students (circle_id, student_id)
                                    VALUES (?, ?)
                                ");
                                $stmt->bind_param("ii", $circle_id, $student_id);
                                $stmt->execute();
                                $row_count++;
                            }
                        }
                        break;

                    case 'students_circles':
                        if (count($data) >= 8) {
                            // Insert or get student
                            $student_name = $data[0];
                            $student_email = $data[1];
                            $student_phone = $data[2];
                            $student_age = (int)$data[3];
                            $student_country_id = $data[4];
                            $circle_name = $data[5];
                            $circle_time = $data[6];
                            $teacher_name = $data[7];
                            $teacher_email = $data[8];
                            
                            // First check if student exists
                            $stmt = $conn->prepare("
                                SELECT id FROM users 
                                WHERE email = ? AND role = 'student'
                            ");
                            $stmt->bind_param("s", $student_email);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows === 0) {
                                // Create new student with default password "1234"
                                $password = "1234";
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                
                                $stmt = $conn->prepare("
                                    INSERT INTO users (name, email, phone, age, country_id, role, password)
                                    VALUES (?, ?, ?, ?, ?, 'student', ?)
                                ");
                                $stmt->bind_param("sssiss", $student_name, $student_email, $student_phone, 
                                                $student_age, $student_country_id, $hashed_password);
                                $stmt->execute();
                                $student_id = $conn->insert_id;
                            } else {
                                $row = $result->fetch_assoc();
                                $student_id = $row['id'];
                            }
                            
                            // Get circle ID
                            $stmt = $conn->prepare("
                                SELECT c.id 
                                FROM study_circles c
                                JOIN users t ON c.teacher_id = t.id
                                WHERE c.name = ? AND c.circle_time = ? AND t.email = ?
                            ");
                            $stmt->bind_param("sss", $circle_name, $circle_time, $teacher_email);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $circle_id = $row['id'];
                                
                                // Add student to circle if not already added
                                $check_stmt = $conn->prepare("
                                    SELECT id FROM circle_students 
                                    WHERE circle_id = ? AND student_id = ?
                                ");
                                $check_stmt->bind_param("ii", $circle_id, $student_id);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                
                                if ($check_result->num_rows === 0) {
                                    $stmt = $conn->prepare("
                                        INSERT INTO circle_students (circle_id, student_id)
                                        VALUES (?, ?)
                                    ");
                                    $stmt->bind_param("ii", $circle_id, $student_id);
                                    $stmt->execute();
                                    $row_count++;
                                }
                            }
                        }
                        break;
                }
            } catch (Exception $e) {
                $errors[] = "Error on line {$line_number}: " . $e->getMessage();
            }
            $line_number++;
        }

        if (count($errors) > 0) {
            $conn->rollback();
            $_SESSION['error'] = "Import failed with following errors:<br>" . implode("<br>", $errors);
        } else {
            $conn->commit();
            $_SESSION['success'] = "Successfully imported {$row_count} records";
        }

        fclose($handle);
    } catch (Exception $e) {
        if (isset($handle)) {
            fclose($handle);
        }
        if (isset($conn)) {
            $conn->rollback();
        }
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Create sample CSV files
function generateSampleCSV($type) {
    $filename = "sample_{$type}.csv";
    $filepath = "../uploads/samples/{$filename}";
    
    if (!file_exists("../uploads/samples")) {
        mkdir("../uploads/samples", 0777, true);
    }
    
    $handle = fopen($filepath, 'w');
    
    switch ($type) {
        case 'users':
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Age', 'Gender', 'Role', 'Country ID', 'Preferred Time']);
            fputcsv($handle, ['John Doe', 'john@example.com', '+1234567890', '30', 'male', 'teacher', '', 'after_fajr']);
            break;
            
        case 'departments':
            fputcsv($handle, ['Name', 'Student Gender', 'Monthly Fees', 'Quarterly Fees', 'Biannual Fees', 'Annual Fees']);
            fputcsv($handle, ['Boys Department', 'male', '100', '270', '500', '900']);
            break;
            
        case 'circles':
            fputcsv($handle, ['Name', 'Description', 'Department ID', 'Teacher ID', 'Supervisor ID', 'Max Students', 'Age From', 'Age To', 'Circle Time']);
            fputcsv($handle, ['Morning Circle', 'Quran memorization circle', '1', '2', '3', '20', '15', '25', 'after_fajr']);
            break;

        case 'circle_students':
            fputcsv($handle, ['Circle ID', 'Student ID']);
            fputcsv($handle, ['1', '10']);
            fputcsv($handle, ['1', '11']);
            break;

        case 'students_circles':
            fputcsv($handle, ['Student Name', 'Student Email', 'Student Phone', 'Student Age', 'Student Country ID', 'Circle Name', 'Circle Time', 'Teacher Name', 'Teacher Email']);
            fputcsv($handle, ['John Doe', 'john@example.com', '+1234567890', '30', '1', 'Morning Circle', 'after_fajr', 'Jane Doe', 'jane@example.com']);
            break;
    }
    
    fclose($handle);
    return $filename;
}

$pageTitle = 'Import Data';
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

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Import Data from CSV</h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="import_type" class="form-label">Select Import Type</label>
                <select class="form-select" id="import_type" name="import_type" required>
                    <option value="">Choose...</option>
                    <option value="users">Users</option>
                    <option value="departments">Departments</option>
                    <option value="circles">Study Circles</option>
                    <option value="circle_students">Circle Students</option>
                    <option value="students_circles">Students with Circles</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="csv_file" class="form-label">CSV File</label>
                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Import Data</button>
        </form>
        
        <hr>
        
        <h6>Download Sample CSV Files:</h6>
        <div class="list-group">
            <?php
            $sample_types = ['users', 'departments', 'circles', 'circle_students', 'students_circles'];
            foreach ($sample_types as $type) {
                $filename = generateSampleCSV($type);
                echo "<a href='../uploads/samples/{$filename}' class='list-group-item list-group-item-action'>";
                echo "Download Sample " . ucfirst($type) . " CSV";
                echo "</a>";
            }
            ?>
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