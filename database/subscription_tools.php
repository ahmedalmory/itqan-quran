<?php
/**
 * AlQuran Subscription System - Database Tools
 * 
 * This page provides access to various database tools for managing the subscription system.
 */

// Check if user is logged in and is an admin (optional security measure)
session_start();
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';

// Display header
$title = "AlQuran Subscription System - Database Tools";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .tool-card {
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .tool-card:hover {
            transform: translateY(-5px);
        }
        .warning-card {
            border-color: #dc3545;
        }
        .warning-card .card-header {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="my-4 text-center"><?php echo $title; ?></h1>
        
        <?php if (!$isAdmin): ?>
        <div class="alert alert-warning">
            <strong>Note:</strong> You are not logged in as an administrator. Some actions may be restricted.
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Check Tables Tool -->
            <div class="col-md-6">
                <div class="card tool-card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Check Subscription Tables</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">This tool checks if the subscription tables exist in your database and displays their structure.</p>
                        <p><strong>Use this to:</strong> Verify that your subscription tables are properly set up.</p>
                        <a href="check_subscription_tables.php" class="btn btn-primary">Check Tables</a>
                    </div>
                </div>
            </div>
            
            <!-- Apply Updates Tool -->
            <div class="col-md-6">
                <div class="card tool-card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Apply Subscription Updates</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">This tool applies the necessary database changes to implement the subscription system.</p>
                        <p><strong>Use this to:</strong> Create the subscription tables if they don't exist.</p>
                        <a href="apply_subscription_updates.php" class="btn btn-success">Apply Updates</a>
                    </div>
                </div>
            </div>
            
            <!-- SQL Script Viewer -->
            <div class="col-md-6">
                <div class="card tool-card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">View SQL Script</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">View the SQL script that creates the subscription tables.</p>
                        <p><strong>Use this to:</strong> Manually apply the changes or understand the database structure.</p>
                        <a href="subscription_updates.sql" class="btn btn-info">View SQL Script</a>
                    </div>
                </div>
            </div>
            
            <!-- Remove Tables Tool -->
            <div class="col-md-6">
                <div class="card tool-card shadow-sm warning-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Remove Subscription Tables</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><strong>CAUTION:</strong> This tool will permanently delete all subscription tables and data!</p>
                        <p><strong>Use this to:</strong> Remove the subscription system completely from your database.</p>
                        <a href="remove_subscription_tables.php" class="btn btn-danger">Remove Tables</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="../index.php" class="btn btn-secondary">Return to Homepage</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
