<?php
/**
 * AlQuran Subscription System - Database Check Script
 * 
 * This script checks if the subscription tables exist and displays their structure.
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

// Function to get table structure
function getTableStructure($pdo, $table) {
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Function to count records in a table
function countRecords($pdo, $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch (Exception $e) {
        return 0;
    }
}

// Display header
echo "==========================================================\n";
echo "AlQuran Subscription System - Database Check Script\n";
echo "==========================================================\n\n";

// Check if script is running from web or command line
$isWeb = isset($_SERVER['HTTP_HOST']);
if ($isWeb) {
    echo "<pre>";
}

// Tables to check
$tables = [
    'subscription_plans',
    'student_subscriptions'
];

// Check each table
foreach ($tables as $table) {
    echo "Checking table: $table\n";
    echo "----------------------------------------\n";
    
    if (tableExists($pdo, $table)) {
        echo "Status: EXISTS\n";
        
        // Get record count
        $count = countRecords($pdo, $table);
        echo "Records: $count\n\n";
        
        // Display structure
        echo "Table Structure:\n";
        $structure = getTableStructure($pdo, $table);
        
        if (!empty($structure)) {
            echo str_pad("Field", 25) . str_pad("Type", 20) . str_pad("Null", 6) . str_pad("Key", 6) . str_pad("Default", 15) . "Extra\n";
            echo str_repeat("-", 80) . "\n";
            
            foreach ($structure as $column) {
                echo str_pad($column['Field'], 25);
                echo str_pad($column['Type'], 20);
                echo str_pad($column['Null'], 6);
                echo str_pad($column['Key'], 6);
                echo str_pad($column['Default'] ?? 'NULL', 15);
                echo $column['Extra'] . "\n";
            }
        } else {
            echo "Could not retrieve table structure.\n";
        }
    } else {
        echo "Status: DOES NOT EXIST\n";
    }
    
    echo "\n";
}

echo "==========================================================\n";
echo "Database check completed.\n";
echo "==========================================================\n";

if ($isWeb) {
    echo "</pre>";
    echo "<p><a href='../index.php'>Return to homepage</a></p>";
}
?>
