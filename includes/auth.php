<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';

/**
 * Check if a user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION[SESSION_USER_ID]) && !empty($_SESSION[SESSION_USER_ID]);
}

/**
 * Check if user has a specific role
 * @param string|array $roles Role or array of roles to check
 * @return bool True if user has specified role, false otherwise
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION[SESSION_USER_ROLE], $roles);
}

/**
 * Get current user ID
 * @return int|null User ID if logged in, null otherwise
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION[SESSION_USER_ID] : null;
}

/**
 * Get current user role
 * @return string|null User role if logged in, null otherwise
 */
function getCurrentUserRole() {
    return isLoggedIn() ? $_SESSION[SESSION_USER_ROLE] : null;
}

/**
 * Get current user name
 * @return string|null User name if logged in, null otherwise
 */
function getCurrentUserName() {
    return isLoggedIn() ? $_SESSION[SESSION_USER_NAME] : null;
}

/**
 * Get current user student ID
 * @return string|null User student ID if logged in, null otherwise
 */
function getCurrentUserStudentId() {
    return isLoggedIn() ? $_SESSION[SESSION_USER_STUDENT_ID] : null;
}

/**
 * Verify if the user is authenticated, redirect to login page if not
 * @param string|array $allowed_roles Role or array of roles allowed to access the page
 * @param string $redirect_url URL to redirect if not authenticated (default: login.php)
 * @return void
 */
function requireLogin($allowed_roles = null, $redirect_url = 'login.php') {
    if (!isLoggedIn()) {
        $_SESSION['error_message'] = ERROR_UNAUTHORIZED;
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirect_url);
        exit;
    }
    
    if ($allowed_roles !== null) {
        if (is_string($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        
        if (!in_array($_SESSION[SESSION_USER_ROLE], $allowed_roles)) {
            $_SESSION['error_message'] = ERROR_UNAUTHORIZED;
            header('Location: dashboard.php');
            exit;
        }
    }
}

/**
 * Login the user and set session variables
 * @param array $user User data array
 * @return void
 */
function loginUser($user) {
    $_SESSION[SESSION_USER_ID] = $user['id'];
    $_SESSION[SESSION_USER_ROLE] = $user['role'];
    $_SESSION[SESSION_USER_NAME] = $user['full_name'];
    $_SESSION[SESSION_USER_EMAIL] = $user['email'];
    $_SESSION[SESSION_USER_STUDENT_ID] = $user['student_id'];
    
    // Clear any error messages
    if (isset($_SESSION['error_message'])) {
        unset($_SESSION['error_message']);
    }
    
    // Handle redirect after login if set
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit;
    }
    
    // Default redirect to dashboard
    header('Location: dashboard.php');
    exit;
}

/**
 * Logout the user and clear session
 * @return void
 */
function logoutUser() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: login.php');
    exit;
}

/**
 * Check if a user is an officer or admin of a specific organization
 * @param int $org_id Organization ID
 * @return bool True if user is an officer/admin of the organization, false otherwise
 */
function isOrgOfficer($org_id) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admin has access to all organizations
    if ($_SESSION[SESSION_USER_ROLE] === ROLE_ADMIN) {
        return true;
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT om.id 
              FROM org_members om 
              WHERE om.org_id = :org_id 
              AND om.user_id = :user_id 
              AND om.role IN ('officer', 'president', 'treasurer') 
              AND om.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':org_id', $org_id);
    $stmt->bindParam(':user_id', $_SESSION[SESSION_USER_ID]);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

/**
 * Check if current user is president or treasurer of a specific organization
 * @param int $org_id Organization ID
 * @return bool True if user is president/treasurer of the organization, false otherwise
 */
function isOrgFinanceOfficer($org_id) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admin has access to all organizations' finances
    if ($_SESSION[SESSION_USER_ROLE] === ROLE_ADMIN) {
        return true;
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT o.id 
              FROM organizations o 
              WHERE o.id = :org_id 
              AND (o.president_id = :user_id OR o.treasurer_id = :user_id)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':org_id', $org_id);
    $stmt->bindParam(':user_id', $_SESSION[SESSION_USER_ID]);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

/**
 * Check if current user is a member of a specific organization
 * @param int $org_id Organization ID
 * @return bool True if user is a member of the organization, false otherwise
 */
function isOrgMember($org_id) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admin has access to all organizations
    if ($_SESSION[SESSION_USER_ROLE] === ROLE_ADMIN) {
        return true;
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT om.id 
              FROM org_members om 
              WHERE om.org_id = :org_id 
              AND om.user_id = :user_id 
              AND om.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':org_id', $org_id);
    $stmt->bindParam(':user_id', $_SESSION[SESSION_USER_ID]);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

/**
 * Get user's organizations (as member or officer)
 * @param int $user_id User ID (defaults to current user)
 * @return array List of organizations the user is associated with
 */
function getUserOrganizations($user_id = null) {
    if ($user_id === null) {
        if (!isLoggedIn()) {
            return [];
        }
        $user_id = $_SESSION[SESSION_USER_ID];
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // If admin, return all organizations
    if (isset($_SESSION[SESSION_USER_ROLE]) && $_SESSION[SESSION_USER_ROLE] === ROLE_ADMIN) {
        $query = "SELECT o.*, 'admin' as member_role, 'active' as member_status 
                  FROM organizations o 
                  ORDER BY o.name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For regular users, return organizations they're members of
    $query = "SELECT o.*, om.role as member_role, om.status as member_status 
              FROM organizations o 
              JOIN org_members om ON o.id = om.org_id 
              WHERE om.user_id = :user_id 
              ORDER BY o.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
