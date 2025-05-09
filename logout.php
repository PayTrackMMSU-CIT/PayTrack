<?php
// Start session
session_start();

// Include auth functions
require_once 'includes/auth.php';
require_once 'config/constants.php';

// Logout user
logoutUser();
?>
