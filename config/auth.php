<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string $role Role to check
 * @return bool
 */
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

/**
 * Require user to be logged in
 * If not logged in, redirect to login page
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /windsurf/AlQuran/login.php');
        exit;
    }
}

/**
 * Require user to have specific role
 * If not authorized, redirect to appropriate page
 * @param array|string $roles Required roles
 */
function requireRole($roles) {
    requireLogin();
    
    // Convert single role to array for consistent handling
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    // Check if user's role is in the allowed roles array
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: /windsurf/AlQuran/unauthorized.php');
        exit;
    }
}

/**
 * Get user's full name
 * @return string
 */
function getUserName() {
    return $_SESSION['name'] ?? 'User';
}

/**
 * Get user's role
 * @return string
 */
function getUserRole() {
    return $_SESSION['role'] ?? '';
}

/**
 * Get user's ID
 * @return int|null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Set flash message to be displayed on next page load
 * @param string $message Message to display
 * @param string $type Message type (success, danger, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get flash message and clear it
 * @return array|null Array with 'message' and 'type' keys, or null if no message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return $message;
    }
    return null;
}

function login($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
}

function logout() {
    session_destroy();
    header('Location: /windsurf/AlQuran/login.php');
    exit();
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Check if user has a specific role
 * @param string $role Role to check
 * @return bool
 */
function isRole($role) {
    if (!isLoggedIn() || !isset($_SESSION['role'])) {
        return false;
    }
    
    // If the role is an array, check if the user's role is in the array
    if (is_array($role)) {
        return in_array($_SESSION['role'], $role);
    }
    
    // If the role is a single role, check if the user's role matches the role
    return $_SESSION['role'] === $role;
}
