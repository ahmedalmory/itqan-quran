<?php
/**
 * Debug Logger
 * 
 * This file provides a custom error logging function for the AlQuran system.
 */

/**
 * Log a debug message to a custom log file
 * 
 * @param string $message The message to log
 * @param string $level The log level (debug, info, warning, error)
 * @param array $context Additional context data
 */
function debug_log($message, $level = 'debug', $context = []) {
    $log_file = __DIR__ . '/../logs/debug_' . date('Y-m-d') . '.log';
    
    // Format the log message
    $timestamp = date('Y-m-d H:i:s');
    $level = strtoupper($level);
    
    // Add context if provided
    $contextStr = '';
    if (!empty($context)) {
        $contextStr = ' | ' . json_encode($context);
    }
    
    // Format the log entry
    $log_entry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
    
    // Write to log file
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
