<?php
// Initialize database and setup
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';

// Start session for flash messages if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$database = new Database();

// Initialize database if reset flag is set
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    // Initialize or reset database
    $success = $database->initializeDatabase();
    
    if ($success) {
        // Set flash message for successful initialization
        setFlashMessage('success', 'Database initialized successfully.');
    } else {
        // Set error message if initialization failed
        setFlashMessage('error', 'Database initialization failed. Please check the error logs.');
    }
}

// Redirect to login page
header('Location: login.php');
exit;
?>
