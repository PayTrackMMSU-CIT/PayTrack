<?php
// Script to initialize the database

// Include database configuration
require_once 'config/database.php';

// Create database instance
$database = new Database();

// Initialize the database
$success = $database->initializeDatabase();

if ($success) {
    echo "Database initialized successfully!\n";
    echo "You can now login with the following test accounts:\n";
    echo "- Admin: ADMIN001/admin123\n";
    echo "- Student: ST123456/student123\n";
    echo "- Officer: OF789012/officer123\n";
} else {
    echo "Failed to initialize database. Check error messages above.\n";
}
?>