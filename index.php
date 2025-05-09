<?php
// Initialize database and setup
require_once 'config/database.php';
$database = new Database();
$database->initializeDatabase();

// Redirect to login page
header('Location: login.php');
exit;
?>
