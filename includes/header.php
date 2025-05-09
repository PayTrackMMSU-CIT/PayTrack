<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - " . APP_NAME : APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="assets/svg/logo.svg" type="image/svg+xml">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <?php if (isset($page_specific_css)): ?>
    <!-- Page Specific CSS -->
    <?php echo $page_specific_css; ?>
    <?php endif; ?>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">
    <?php if (isLoggedIn()): ?>
        <?php include_once __DIR__ . '/navbar.php'; ?>
    <?php endif; ?>
    
    <div class="container mx-auto px-4 py-6 flex-grow">
        <?php echo displayFlashMessage(); ?>
