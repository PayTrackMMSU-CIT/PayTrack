<?php
// Set page title
$page_title = 'My Profile';

// Include header
require_once 'includes/header.php';

// Require login
requireLogin();

// Initialize variables
$success = '';
$error = '';
$user = null;

// Get current user data
$user_id = getCurrentUserId();

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Get user info
$query = "SELECT * FROM users WHERE id = :id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Redirect to dashboard if user not found
    setFlashMessage('error', 'User not found.');
    header('Location: dashboard.php');
    exit;
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = isset($_POST['full_name']) ? sanitizeInput($_POST['full_name']) : $user['full_name'];
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : $user['email'];
    $department = isset($_POST['department']) ? sanitizeInput($_POST['department']) : $user['department'];
    $year_level = isset($_POST['year_level']) ? sanitizeInput($_POST['year_level']) : $user['year_level'];
    
    // Validate email if changed
    if ($email !== $user['email']) {
        $check_email = "SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1";
        $stmt_email = $db->prepare($check_email);
        $stmt_email->bindParam(':email', $email);
        $stmt_email->bindParam(':id', $user_id);
        $stmt_email->execute();
        
        if ($stmt_email->rowCount() > 0) {
            $error = 'Email already exists. Please use a different one.';
        }
    }
    
    if (empty($error)) {
        // Update user info
        $update_query = "UPDATE users SET 
                        full_name = :full_name, 
                        email = :email, 
                        department = :department, 
                        year_level = :year_level
                        WHERE id = :id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':full_name', $full_name);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':department', $department);
        $update_stmt->bindParam(':year_level', $year_level);
        $update_stmt->bindParam(':id', $user_id);
        
        if ($update_stmt->execute()) {
            // Update session data
            $_SESSION[SESSION_USER_NAME] = $full_name;
            $_SESSION[SESSION_USER_EMAIL] = $email;
            
            $success = SUCCESS_PROFILE_UPDATE;
            
            // Refresh user data
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate passwords
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        // Hash the new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $update_password = "UPDATE users SET password = :password WHERE id = :id";
        $stmt_password = $db->prepare($update_password);
        $stmt_password->bindParam(':password', $password_hash);
        $stmt_password->bindParam(':id', $user_id);
        
        if ($stmt_password->execute()) {
            $success = 'Password updated successfully.';
        } else {
            $error = 'Failed to update password. Please try again.';
        }
    }
}

// Year level options
$year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Graduate'];
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
        <p class="text-gray-600">Manage your account information</p>
    </div>
    <div class="mt-4 md:mt-0">
        <a href="dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded inline-flex items-center text-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if (!empty($success)): ?>
<div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-6 flex items-center">
    <i class="fas fa-check-circle mr-2"></i>
    <span><?php echo $success; ?></span>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="bg-red-100 text-red-800 px-4 py-3 rounded mb-6 flex items-center">
    <i class="fas fa-exclamation-circle mr-2"></i>
    <span><?php echo $error; ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile Information -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h2 class="font-bold text-gray-800 text-lg">Profile Information</h2>
                <p class="text-sm text-gray-600">Update your account details</p>
            </div>
            <div class="p-6">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" data-validate="true">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="student_id" class="block text-gray-700 font-medium mb-2">Student ID</label>
                            <input type="text" id="student_id" value="<?php echo htmlspecialchars($user['student_id']); ?>" class="form-control bg-gray-100" readonly>
                            <p class="text-xs text-gray-500 mt-1">Student ID cannot be changed</p>
                        </div>
                        
                        <div>
                            <label for="full_name" class="block text-gray-700 font-medium mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="email" class="block text-gray-700 font-medium mb-2">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control" required>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="department" class="block text-gray-700 font-medium mb-2">Department</label>
                            <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($user['department']); ?>" class="form-control">
                        </div>
                        
                        <div>
                            <label for="year_level" class="block text-gray-700 font-medium mb-2">Year Level</label>
                            <select id="year_level" name="year_level" class="form-control">
                                <option value="">Select Year Level</option>
                                <?php foreach ($year_levels as $year): ?>
                                <option value="<?php echo $year; ?>" <?php if ($user['year_level'] === $year) echo 'selected'; ?>>
                                    <?php echo $year; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="role" class="block text-gray-700 font-medium mb-2">Role</label>
                            <input type="text" id="role" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" class="form-control bg-gray-100" readonly>
                        </div>
                        
                        <div>
                            <label for="created_at" class="block text-gray-700 font-medium mb-2">Member Since</label>
                            <input type="text" id="created_at" value="<?php echo formatDate($user['created_at'], SHORT_DATE_FORMAT); ?>" class="form-control bg-gray-100" readonly>
                        </div>
                    </div>
                    
                    <div class="border-t pt-6">
                        <button type="submit" name="update_profile" class="bg-blue-800 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Right Sidebar -->
    <div>
        <!-- Profile Picture Card -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h2 class="font-bold text-gray-800 text-lg">Profile Picture</h2>
            </div>
            <div class="p-6 text-center">
                <?php if ($user['profile_image'] && file_exists($user['profile_image']) && $user['profile_image'] != 'default.svg'): ?>
                <img src="<?php echo $user['profile_image']; ?>" alt="Profile" class="w-32 h-32 rounded-full object-cover mx-auto">
                <?php else: ?>
                <div class="w-32 h-32 rounded-full bg-blue-800 text-white flex items-center justify-center text-4xl font-bold mx-auto">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <?php endif; ?>
                
                <p class="text-gray-600 mt-4 mb-6">Upload a profile picture for your account.</p>
                
                <form action="#" method="post" enctype="multipart/form-data" class="mb-4">
                    <label for="profile_image" class="bg-blue-800 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center cursor-pointer transition duration-200">
                        <i class="fas fa-upload mr-2"></i> Upload Image
                        <input type="file" id="profile_image" name="profile_image" class="hidden">
                    </label>
                    
                    <!-- This button would be shown via JS when a file is selected -->
                    <button type="submit" class="bg-green-600 hover:bg-green-500 text-white font-bold py-2 px-4 rounded mt-2 hidden" id="submit-image">
                        <i class="fas fa-check mr-2"></i> Save
                    </button>
                </form>
                
                <div class="text-xs text-gray-500">
                    <p>Supported formats: JPG, PNG, GIF</p>
                    <p>Maximum size: 2MB</p>
                </div>
            </div>
        </div>
        
        <!-- Change Password Card -->
        <div class="bg-white rounded-lg shadow">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h2 class="font-bold text-gray-800 text-lg">Change Password</h2>
            </div>
            <div class="p-6">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" data-validate="true">
                    <div class="mb-4">
                        <label for="current_password" class="block text-gray-700 font-medium mb-2">Current Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password" data-target="current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="new_password" class="block text-gray-700 font-medium mb-2">New Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password" data-target="new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm New Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="change_password" class="w-full bg-blue-800 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                            <i class="fas fa-key mr-2"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
