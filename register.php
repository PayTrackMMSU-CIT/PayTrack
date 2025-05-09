<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database and constants
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION[SESSION_USER_ID])) {
    header('Location: dashboard.php');
    exit;
}

// Initialize variables
$student_id = '';
$full_name = '';
$email = '';
$department = '';
$year_level = '';
$error = '';
$success = '';

// Year level options
$year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Graduate'];

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $student_id = isset($_POST['student_id']) ? sanitizeInput($_POST['student_id']) : '';
    $full_name = isset($_POST['full_name']) ? sanitizeInput($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $department = isset($_POST['department']) ? sanitizeInput($_POST['department']) : '';
    $year_level = isset($_POST['year_level']) ? sanitizeInput($_POST['year_level']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate form data
    if (empty($student_id) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Connect to database
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if student ID already exists
        $check_student_id = "SELECT id FROM users WHERE student_id = :student_id LIMIT 1";
        $stmt_student_id = $db->prepare($check_student_id);
        $stmt_student_id->bindParam(':student_id', $student_id);
        $stmt_student_id->execute();
        
        // Check if email already exists
        $check_email = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt_email = $db->prepare($check_email);
        $stmt_email->bindParam(':email', $email);
        $stmt_email->execute();
        
        if ($stmt_student_id->rowCount() > 0) {
            $error = 'Student ID already exists. Please use a different one.';
        } elseif ($stmt_email->rowCount() > 0) {
            $error = 'Email already exists. Please use a different one.';
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $insert_query = "INSERT INTO users (student_id, full_name, email, password, role, department, year_level) 
                           VALUES (:student_id, :full_name, :email, :password, 'student', :department, :year_level)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':year_level', $year_level);
            
            if ($stmt->execute()) {
                // Success, redirect to login page
                setFlashMessage('success', SUCCESS_REGISTRATION);
                header('Location: login.php');
                exit;
            } else {
                $error = ERROR_REGISTRATION_FAILED;
            }
        }
    }
}

// Set page title
$page_title = 'Register';

// No header for register page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . " - " . APP_NAME; ?></title>
    
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
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen py-12">
    <div class="max-w-lg w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header with Logo -->
            <div class="bg-blue-800 px-6 py-4 text-center">
                <img src="assets/svg/logo.svg" alt="<?php echo APP_NAME; ?>" class="h-16 w-16 mx-auto mb-2">
                <h1 class="text-white font-bold text-2xl"><?php echo APP_NAME; ?></h1>
                <p class="text-blue-100 text-sm"><?php echo APP_DESCRIPTION; ?></p>
            </div>
            
            <!-- Registration Form -->
            <div class="p-6">
                <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Create an Account</h2>
                
                <?php if (!empty($error)): ?>
                <div class="bg-red-100 text-red-800 px-4 py-3 rounded mb-4 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?php echo $error; ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-4 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?php echo $success; ?></span>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" data-validate="true">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="student_id" class="block text-gray-700 font-medium mb-2">Student ID <span class="text-red-500">*</span></label>
                            <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>" class="form-control" placeholder="Enter your student ID" required>
                        </div>
                        
                        <div>
                            <label for="full_name" class="block text-gray-700 font-medium mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" class="form-control" placeholder="Enter your full name" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="email" class="block text-gray-700 font-medium mb-2">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="form-control" placeholder="Enter your email address" required>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="department" class="block text-gray-700 font-medium mb-2">Department</label>
                            <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($department); ?>" class="form-control" placeholder="Enter your department">
                        </div>
                        
                        <div>
                            <label for="year_level" class="block text-gray-700 font-medium mb-2">Year Level</label>
                            <select id="year_level" name="year_level" class="form-control">
                                <option value="">Select Year Level</option>
                                <?php foreach ($year_levels as $year): ?>
                                <option value="<?php echo $year; ?>" <?php if ($year_level === $year) echo 'selected'; ?>>
                                    <?php echo $year; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="password" class="block text-gray-700 font-medium mb-2">Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password" data-target="password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters long.</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <button type="submit" class="w-full bg-blue-800 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                            <i class="fas fa-user-plus mr-2"></i> Register
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-gray-600">Already have an account? <a href="login.php" class="text-blue-800 hover:underline font-medium">Login</a></p>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-gray-600 text-sm">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - MMSU-CIT
            </p>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html>
