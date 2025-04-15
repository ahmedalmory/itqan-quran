<?php
require_once 'config/database.php';

// Function to run SQL file
function runSQLFile($conn, $filename) {
    $sql = file_get_contents($filename);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $conn->begin_transaction();
    try {
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $conn->query($statement);
            }
        }
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Check if database needs initialization
$needsInit = false;
try {
    $result = $conn->query("SELECT 1 FROM settings LIMIT 1");
} catch (Exception $e) {
    $needsInit = true;
}

if ($needsInit) {
    echo "Initializing database...\n";
    if (runSQLFile($conn, 'database/schema.sql')) {
        echo "Database initialized successfully!\n";
    } else {
        echo "Error initializing database.\n";
    }
} else {
    echo "Database already initialized.\n";
}

// Redirect to homepage
header("Location: index.php");
exit;
