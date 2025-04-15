<?php
/**
 * AlQuran Subscription System - Database Removal Script
 * 
 * This script removes the subscription tables from the database.
 * CAUTION: This will permanently delete all subscription data!
 */

// Load database configuration
require_once '../config/database.php';

// Function to check if a table exists
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Display header
echo "==========================================================\n";
echo "AlQuran Subscription System - Database Removal Script\n";
echo "==========================================================\n\n";

// Check if script is running from web or command line
$isWeb = isset($_SERVER['HTTP_HOST']);
if ($isWeb) {
    echo "<pre>";
}

// Check for confirmation
$confirmed = false;
if ($isWeb) {
    $confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';
    
    if (!$confirmed) {
        echo "WARNING: This script will permanently delete all subscription data!\n\n";
        echo "</pre>";
        echo "<form method='post' action=''>";
        echo "<p style='color: red; font-weight: bold;'>Are you sure you want to remove all subscription tables and data?</p>";
        echo "<input type='hidden' name='confirm' value='yes'>";
        echo "<button type='submit' style='background-color: red; color: white; padding: 10px 20px;'>Yes, Remove All Subscription Data</button> ";
        echo "<a href='../index.php' style='display: inline-block; background-color: gray; color: white; padding: 10px 20px; text-decoration: none;'>Cancel</a>";
        echo "</form>";
        exit;
    }
} else {
    echo "WARNING: This script will permanently delete all subscription data!\n";
    echo "Press CTRL+C now to cancel, or wait 5 seconds to continue...\n\n";
    sleep(5);
    $confirmed = true;
}

if ($confirmed) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        echo "Starting removal process...\n\n";
        
        // Drop tables in the correct order (to respect foreign key constraints)
        $tables = [
            'student_subscriptions',
            'subscription_plans'
        ];
        
        foreach ($tables as $table) {
            echo "Checking table: $table... ";
            
            if (tableExists($pdo, $table)) {
                echo "EXISTS\n";
                echo "Dropping table: $table... ";
                
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
                echo "DONE\n";
            } else {
                echo "DOES NOT EXIST (skipping)\n";
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo "\nAll subscription tables have been successfully removed.\n";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "\nRemoval process failed. Some tables may still exist.\n";
    }
}

echo "\n==========================================================\n";
echo "Database removal process completed.\n";
echo "==========================================================\n";

if ($isWeb) {
    echo "</pre>";
    echo "<p><a href='../index.php'>Return to homepage</a></p>";
}
?>
