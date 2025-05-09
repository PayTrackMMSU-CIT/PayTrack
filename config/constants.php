<?php
// Application Constants
define('APP_NAME', 'PayTrack');
define('APP_DESCRIPTION', 'A Mobile Application System for MMSU-CIT Organizations');
define('SITE_URL', 'http://localhost:5000');

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_OFFICER', 'officer');
define('ROLE_ADVISER', 'adviser');
define('ROLE_ADMIN', 'admin');

// Payment Status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_COMPLETED', 'completed');
define('PAYMENT_REJECTED', 'rejected');
define('PAYMENT_REFUNDED', 'refunded');

// Payment Methods
define('PAYMENT_CASH', 'cash');
define('PAYMENT_GCASH', 'gcash');
define('PAYMENT_BANK', 'bank_transfer');
define('PAYMENT_OTHER', 'other');

// Notification Types
define('NOTIFICATION_PAYMENT', 'payment');
define('NOTIFICATION_ANNOUNCEMENT', 'announcement');
define('NOTIFICATION_REMINDER', 'reminder');
define('NOTIFICATION_OTHER', 'other');

// Organization Member Status
define('MEMBER_ACTIVE', 'active');
define('MEMBER_INACTIVE', 'inactive');
define('MEMBER_PENDING', 'pending');

// Color Scheme
define('PRIMARY_COLOR', '#1a56db');
define('SECONDARY_COLOR', '#e2e8f0');
define('SUCCESS_COLOR', '#0e9f6e');
define('DANGER_COLOR', '#e02424');
define('WARNING_COLOR', '#ff5a1f');
define('INFO_COLOR', '#3f83f8');

// Date Format
define('DATE_FORMAT', 'F j, Y, g:i a');
define('SHORT_DATE_FORMAT', 'm/d/Y');

// Session Variables
define('SESSION_USER_ID', 'user_id');
define('SESSION_USER_ROLE', 'user_role');
define('SESSION_USER_NAME', 'user_name');
define('SESSION_USER_EMAIL', 'user_email');
define('SESSION_USER_STUDENT_ID', 'user_student_id');

// Error Messages
define('ERROR_LOGIN_FAILED', 'Invalid student ID or password');
define('ERROR_REGISTRATION_FAILED', 'Registration failed. Please try again.');
define('ERROR_UNAUTHORIZED', 'You are not authorized to access this page');
define('ERROR_PAYMENT_FAILED', 'Payment processing failed. Please try again.');
define('ERROR_FORM_VALIDATION', 'Please fill all required fields correctly');
define('ERROR_DB_CONNECTION', 'Database connection error');
define('ERROR_FILE_UPLOAD', 'File upload failed');
define('ERROR_NOT_FOUND', 'The requested resource was not found');

// Success Messages
define('SUCCESS_REGISTRATION', 'Registration successful! You can now log in.');
define('SUCCESS_LOGIN', 'Login successful! Welcome back.');
define('SUCCESS_LOGOUT', 'You have been logged out successfully.');
define('SUCCESS_PROFILE_UPDATE', 'Profile updated successfully.');
define('SUCCESS_PAYMENT', 'Payment submitted successfully.');
define('SUCCESS_PAYMENT_VERIFICATION', 'Payment verified successfully.');
define('SUCCESS_ORGANIZATION_CREATE', 'Organization created successfully.');
define('SUCCESS_MEMBER_ADD', 'Member added successfully.');

// Image Paths
define('DEFAULT_PROFILE_IMAGE', 'assets/img/default-profile.svg');
define('DEFAULT_ORG_LOGO', 'assets/img/default-org-logo.svg');
define('PAYMENT_PROOF_PATH', 'uploads/payment_proofs/');
?>
