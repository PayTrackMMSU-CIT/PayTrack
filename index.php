<?php
// Initialize database and setup
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';

// Initialize database
$database = new Database();

// Force recreate the SQLite database if it doesn't exist or if reset flag is set
if (!file_exists('paytrack.db') || (isset($_GET['reset']) && $_GET['reset'] === '1')) {
    // Remove existing database if it exists
    if (file_exists('paytrack.db')) {
        unlink('paytrack.db');
    }
    
    // Initialize fresh database
    $success = $database->initializeDatabase();
    if ($success) {
        // Set flash message for successful initialization
        session_start();
        setFlashMessage('success', 'Database initialized successfully.');
    }
}

// Redirect to login page
header('Location: login.php');
exit;
?>
