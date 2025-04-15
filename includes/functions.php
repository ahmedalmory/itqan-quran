<?php
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    // Basic phone validation with country code
    return preg_match('/^\+[1-9]\d{1,14}$/', $phone);
}

function validate_password($password) {
    // Password must be at least 8 characters
    return strlen($password) >= 8;
}

function validate_age($age) {
    return is_numeric($age) && $age >= 5 && $age <= 100;
}

function format_prayer_time($time) {
    $prayer_times = [
        'after_fajr' => 'بعد الفجر',
        'after_dhuhr' => 'بعد الظهر',
        'after_asr' => 'بعد العصر',
        'after_maghrib' => 'بعد المغرب',
        'after_isha' => 'بعد العشاء'
    ];
    return $prayer_times[$time] ?? $time;
}

function get_available_circles($user_gender, $age, $preferred_time) {
    global $conn;
    
    $sql = "SELECT c.*, d.name as department_name, u.name as teacher_name,
            (SELECT COUNT(*) FROM circle_students WHERE circle_id = c.id) as current_students 
            FROM study_circles c
            JOIN departments d ON c.department_id = d.id
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE d.student_gender = ?
            AND ? BETWEEN c.age_from AND c.age_to
            AND c.circle_time = ?
            AND (
                SELECT COUNT(*) FROM circle_students 
                WHERE circle_id = c.id
            ) < c.max_students";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $user_gender, $age, $preferred_time);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get translation for a key in the user's current language
 * @param string $key Translation key
 * @return string Translated text or key if translation not found
 */
function __($key) {
    global $conn;
    static $translations = null;
    
    // Get user's language from session or default to Arabic
    $language_code = $_SESSION['language'] ?? 'ar';
    
    // Load translations if not already loaded
    if ($translations === null) {
        $stmt = $conn->prepare("
            SELECT t.translation_key, t.translation_value 
            FROM translations t
            JOIN languages l ON t.language_id = l.id
            WHERE l.code = ?
        ");
        $stmt->bind_param("s", $language_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $translations = [];
        while ($row = $result->fetch_assoc()) {
            $translations[$row['translation_key']] = $row['translation_value'];
        }
    }
    
    // Return translation or key if not found
    return $translations[$key] ?? $key;
}

/**
 * Get department working days for a student
 * @param int $student_id Student ID
 * @return array Array of working days
 */
function getStudentDepartmentWorkDays($student_id) {
    global $conn;
    
    $sql = "SELECT 
                d.work_sunday, d.work_monday, d.work_tuesday, 
                d.work_wednesday, d.work_thursday, d.work_friday, 
                d.work_saturday
            FROM circle_students cs
            JOIN study_circles c ON cs.circle_id = c.id
            JOIN departments d ON c.department_id = d.id
            WHERE cs.student_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ?: [
        'work_sunday' => 0,
        'work_monday' => 1,
        'work_tuesday' => 1,
        'work_wednesday' => 1,
        'work_thursday' => 1,
        'work_friday' => 0,
        'work_saturday' => 0
    ];
}

/**
 * Get daily report for a student on a specific date
 * @param int $student_id Student ID
 * @param string $date Date in Y-m-d format
 * @return array|null Report data or null if not found
 */
function getStudentDailyReport($student_id, $date) {
    global $conn;
    
    $sql = "SELECT dr.*, 
            s1.name as from_surah_name, 
            s2.name as to_surah_name
            FROM daily_reports dr
            JOIN surahs s1 ON dr.memorization_from_surah = s1.id
            JOIN surahs s2 ON dr.memorization_to_surah = s2.id
            WHERE dr.student_id = ? AND dr.report_date = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $student_id, $date);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get card color based on report status
 * @param array|null $report Report data
 * @param bool $is_working_day Whether it's a working day
 * @return string Bootstrap color class
 */
function getReportCardColor($report, $is_working_day) {
    if (!$is_working_day) {
        return 'bg-secondary'; // Weekend/Holiday
    }
    if (!$report) {
        return 'bg-warning'; // No report
    }
    if ($report['grade'] >= 90) {
        return 'bg-success'; // Excellent
    }
    if ($report['grade'] >= 75) {
        return 'bg-info'; // Good
    }
    if ($report['grade'] >= 60) {
        return 'bg-primary'; // Pass
    }
    return 'bg-danger'; // Poor
}

/**
 * Format number of parts
 * @param float $number Number of parts
 * @return string Formatted number of parts
 */
function formatParts($number) {
    if ($number == floor($number)) {
        return (int)$number;
    } else if ($number == floor($number) + 0.5) {
        return number_format($number, 1);
    } else if ($number == floor($number) + 0.25) {
        return number_format($number, 2);
    }
    return $number;
}

/**
 * Format a number with Arabic digits
 * @param float|int $number The number to format
 * @return string Formatted number
 */
function formatNumber($number) {
    if (!is_numeric($number)) {
        return '0';
    }
    
    // Format with 2 decimal places if it has decimals
    if (floor($number) != $number) {
        $number = number_format($number, 2);
    }
    
    // Convert to Arabic numerals
    $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    
    return str_replace($western, $eastern, $number);
}

/**
 * Get departments for an admin
 * @param int $admin_id Admin ID
 * @return array Array of departments
 */
function get_admin_departments($admin_id) {
    global $conn;
    
    $sql = "SELECT d.* 
            FROM departments d
            JOIN department_admins da ON d.id = da.department_id
            WHERE da.user_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Format a date in both Gregorian and Hijri calendars
 * @param string $date Date string in any format that strtotime can parse
 * @return string Formatted date string
 */
function formatDate($date) {
    if (!$date) return '';
    
    // Convert to timestamp
    $timestamp = strtotime($date);
    if ($timestamp === false) return $date;
    
    // Format Gregorian date in Arabic
    $months = [
        'January' => 'يناير',
        'February' => 'فبراير',
        'March' => 'مارس',
        'April' => 'أبريل',
        'May' => 'مايو',
        'June' => 'يونيو',
        'July' => 'يوليو',
        'August' => 'أغسطس',
        'September' => 'سبتمبر',
        'October' => 'أكتوبر',
        'November' => 'نوفمبر',
        'December' => 'ديسمبر'
    ];
    
    $gregorian_date = date('j', $timestamp) . ' ' . 
                      $months[date('F', $timestamp)] . ' ' . 
                      date('Y', $timestamp);
    
    // Convert numbers to Arabic
    $arabic_numbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $gregorian_date = str_replace(
        range(0, 9),
        $arabic_numbers,
        $gregorian_date
    );
    
    return $gregorian_date;
}

/**
 * Generate a random password
 * @param int $length Length of the password
 * @return string Random password
 */
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}
