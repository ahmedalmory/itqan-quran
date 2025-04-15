<?php
/**
 * AlQuran Subscription System - Database Update Script
 * 
 * This script applies the necessary database changes to implement the subscription system.
 * Run this script once to add the subscription tables to an existing AlQuran database.
 */

// Load database configuration
require_once '../config/database.php';

// Function to execute SQL from a file
function executeSQLFile($pdo, $sqlFile) {
    try {
        // Read the SQL file
        $sql = file_get_contents($sqlFile);
        
        if ($sql === false) {
            throw new Exception("Error reading SQL file: $sqlFile");
        }
        
        // Execute the SQL commands
        $result = $pdo->exec($sql);
        
        echo "SQL file executed successfully: $sqlFile\n";
        return true;
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage() . "\n";
        return false;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Display header
echo "==========================================================\n";
echo "AlQuran Subscription System - Database Update Script\n";
echo "==========================================================\n\n";

// Check if script is running from web or command line
$isWeb = isset($_SERVER['HTTP_HOST']);
if ($isWeb) {
    echo "<pre>";
}

// Start the update process
echo "Starting database update process...\n\n";

// Apply the subscription updates
$sqlFile = __DIR__ . '/subscription_updates.sql';
if (executeSQLFile($pdo, $sqlFile)) {
    echo "\nSubscription tables created successfully!\n";
} else {
    echo "\nFailed to create subscription tables. Please check the error messages above.\n";
}

echo "\n==========================================================\n";
echo "Database update process completed.\n";
echo "==========================================================\n";

if ($isWeb) {
    echo "</pre>";
    echo "<p><a href='../index.php'>Return to homepage</a></p>";
}
?>
