<?php
// Start session
session_start();

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
$password = '';
$error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $student_id = isset($_POST['student_id']) ? sanitizeInput($_POST['student_id']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate form data
    if (empty($student_id) || empty($password)) {
        $error = 'Please enter both student ID and password.';
    } else {
        // Connect to database
        $database = new Database();
        $db = $database->getConnection();
        
        // Initialize the database if it doesn't exist
        if (!file_exists('paytrack.db')) {
            $database->initializeDatabase();
        }
        
        // Check if user exists
        $query = "SELECT * FROM users WHERE student_id = :student_id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        
        // Get the result
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Store user data in session
                $_SESSION[SESSION_USER_ID] = $user['id'];
                $_SESSION[SESSION_USER_ROLE] = $user['role'];
                $_SESSION[SESSION_USER_NAME] = $user['full_name'];
                $_SESSION[SESSION_USER_EMAIL] = $user['email'];
                $_SESSION[SESSION_USER_STUDENT_ID] = $user['student_id'];
                
                // Set success message
                setFlashMessage('success', SUCCESS_LOGIN);
                
                // Redirect to dashboard or requested page
                $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'dashboard.php';
                unset($_SESSION['redirect_after_login']);
                
                header("Location: $redirect");
                exit;
            } else {
                $error = ERROR_LOGIN_FAILED . " (Password verification failed)";
            }
        } else {
            $error = ERROR_LOGIN_FAILED . " (User not found)";
        }
    }
}

// Set page title
$page_title = 'Login';

// No header for login page
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
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header with Logo -->
            <div class="bg-blue-800 px-6 py-8 text-center">
                <img src="assets/svg/logo.svg" alt="<?php echo APP_NAME; ?>" class="h-20 w-20 mx-auto mb-4">
                <h1 class="text-white font-bold text-3xl"><?php echo APP_NAME; ?></h1>
                <p class="text-blue-100 mt-1"><?php echo APP_DESCRIPTION; ?></p>
            </div>
            
            <!-- Login Form -->
            <div class="p-6">
                <?php if (!empty($error)): ?>
                <div class="bg-red-100 text-red-800 px-4 py-3 rounded mb-4 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?php echo $error; ?></span>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" data-validate="true">
                    <div class="mb-4">
                        <label for="student_id" class="block text-gray-700 font-medium mb-2">Student ID</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-id-card text-gray-400"></i>
                            </div>
                            <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>" class="form-control pl-10" placeholder="Enter your student ID" required>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" class="form-control pl-10" placeholder="Enter your password" required>
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <button type="submit" class="w-full bg-blue-800 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                            <i class="fas fa-sign-in-alt mr-2"></i> Login
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-gray-600">Don't have an account? <a href="register.php" class="text-blue-800 hover:underline font-medium">Register</a></p>
                    </div>
                </form>
            </div>
            
            <!-- Demo Account Information -->
            <div class="px-6 py-4 bg-gray-50 text-center border-t">
                <p class="text-gray-700 font-medium mb-2">Demo Accounts:</p>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="bg-white p-2 rounded border">
                        <p class="font-medium text-blue-800">Student</p>
                        <p>ID: ST123456</p>
                        <p>Password: student123</p>
                    </div>
                    <div class="bg-white p-2 rounded border">
                        <p class="font-medium text-blue-800">Officer</p>
                        <p>ID: OF789012</p>
                        <p>Password: officer123</p>
                    </div>
                </div>
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
