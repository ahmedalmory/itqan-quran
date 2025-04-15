<?php
if (!function_exists('init_language')) {
    /**
     * Initialize language settings
     */
    function init_language() {
        if (!isset($_SESSION['language'])) {
            // Get default language from database
            global $conn;
            $result = $conn->query("SELECT code FROM languages WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
            if ($result && $result->num_rows > 0) {
                $_SESSION['language'] = $result->fetch_assoc()['code'];
            } else {
                $_SESSION['language'] = 'ar'; // Fallback to Arabic
            }
        }
    }
}

if (!function_exists('get_current_language')) {
    /**
     * Get current language code
     * @return string
     */
    function get_current_language() {
        init_language();
        return $_SESSION['language'];
    }
}

if (!function_exists('set_language')) {
    /**
     * Set current language
     * @param string $code Language code
     * @return bool
     */
    function set_language($code) {
        global $conn;
        $stmt = $conn->prepare("SELECT code FROM languages WHERE code = ? AND is_active = 1");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $_SESSION['language'] = $code;
            return true;
        }
        return false;
    }
}

if (!function_exists('get_translation')) {
    /**
     * Get translation for a key
     * @param string $key Translation key
     * @return string
     */
    function get_translation($key) {
        global $conn;
        $language = get_current_language();
        
        $stmt = $conn->prepare("
            SELECT t.translation_value 
            FROM translations t 
            JOIN languages l ON l.id = t.language_id 
            WHERE l.code = ? AND t.translation_key = ?
        ");
        $stmt->bind_param('ss', $language, $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['translation_value'];
        }
        
        // Fallback to key if translation not found
        return $key;
    }
}

if (!function_exists('__')) {
    /**
     * Shorthand function for get_translation
     * @param string $key Translation key
     * @return string
     */
    function __($key) {
        return get_translation($key);
    }
}

if (!function_exists('get_available_languages')) {
    /**
     * Get list of available languages
     * @return array
     */
    function get_available_languages() {
        global $conn;
        $languages = [];
        
        $result = $conn->query("SELECT code, name, direction FROM languages WHERE is_active = 1");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $languages[] = $row;
            }
        }
        
        return $languages;
    }
}

if (!function_exists('get_language_direction')) {
    /**
     * Get current language direction
     * @return string 'rtl' or 'ltr'
     */
    function get_language_direction() {
        global $conn;
        $language = get_current_language();
        
        $stmt = $conn->prepare("SELECT direction FROM languages WHERE code = ?");
        $stmt->bind_param('s', $language);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['direction'];
        }
        
        return 'rtl'; // Default to RTL for Arabic
    }
}
