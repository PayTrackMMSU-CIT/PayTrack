<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';

/**
 * Display a flash message
 * @param string $type Message type (success, error, warning, info)
 * @param string $message The message to display
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION["flash_message"] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display flash message and clear it
 * @return string HTML for the flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION["flash_message"])) {
        $message = $_SESSION["flash_message"]['message'];
        $type = $_SESSION["flash_message"]['type'];
        
        // Clear the message
        unset($_SESSION["flash_message"]);
        
        $bg_color = 'bg-gray-100';
        $text_color = 'text-gray-800';
        $border_color = 'border-gray-300';
        $icon = 'fas fa-info-circle';
        
        switch ($type) {
            case 'success':
                $bg_color = 'bg-green-100';
                $text_color = 'text-green-800';
                $border_color = 'border-green-300';
                $icon = 'fas fa-check-circle';
                break;
            case 'error':
                $bg_color = 'bg-red-100';
                $text_color = 'text-red-800';
                $border_color = 'border-red-300';
                $icon = 'fas fa-exclamation-circle';
                break;
            case 'warning':
                $bg_color = 'bg-yellow-100';
                $text_color = 'text-yellow-800';
                $border_color = 'border-yellow-300';
                $icon = 'fas fa-exclamation-triangle';
                break;
            case 'info':
                $bg_color = 'bg-blue-100';
                $text_color = 'text-blue-800';
                $border_color = 'border-blue-300';
                $icon = 'fas fa-info-circle';
                break;
        }
        
        return '
        <div class="px-4 py-3 mb-4 rounded-md border ' . $bg_color . ' ' . $text_color . ' ' . $border_color . ' flex items-center justify-between" role="alert">
            <div class="flex items-center">
                <i class="' . $icon . ' mr-2"></i>
                <span>' . htmlspecialchars($message) . '</span>
            </div>
            <button type="button" class="close-alert" onclick="this.parentElement.style.display=\'none\'">
                <i class="fas fa-times"></i>
            </button>
        </div>';
    }
    return '';
}

/**
 * Sanitize input data
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format date according to the defined constant
 * @param string $date Date string
 * @param string $format Date format (default: DATE_FORMAT)
 * @return string Formatted date
 */
function formatDate($date, $format = DATE_FORMAT) {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format currency amount
 * @param float $amount Amount to format
 * @param string $currency Currency symbol (default: ₱)
 * @return string Formatted amount
 */
function formatCurrency($amount, $currency = '₱') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Get payment status badge HTML
 * @param string $status Payment status
 * @return string HTML for the status badge
 */
function getStatusBadge($status) {
    $color_class = '';
    $text_class = '';
    
    switch ($status) {
        case PAYMENT_COMPLETED:
            $color_class = 'bg-green-100';
            $text_class = 'text-green-800';
            break;
        case PAYMENT_PENDING:
            $color_class = 'bg-yellow-100';
            $text_class = 'text-yellow-800';
            break;
        case PAYMENT_REJECTED:
            $color_class = 'bg-red-100';
            $text_class = 'text-red-800';
            break;
        case PAYMENT_REFUNDED:
            $color_class = 'bg-blue-100';
            $text_class = 'text-blue-800';
            break;
        case MEMBER_ACTIVE:
            $color_class = 'bg-green-100';
            $text_class = 'text-green-800';
            break;
        case MEMBER_INACTIVE:
            $color_class = 'bg-gray-100';
            $text_class = 'text-gray-800';
            break;
        case MEMBER_PENDING:
            $color_class = 'bg-yellow-100';
            $text_class = 'text-yellow-800';
            break;
        default:
            $color_class = 'bg-gray-100';
            $text_class = 'text-gray-800';
    }
    
    return '<span class="px-2 py-1 text-xs rounded-full ' . $color_class . ' ' . $text_class . '">' . ucfirst($status) . '</span>';
}

/**
 * Get user's full name by ID
 * @param int $user_id User ID
 * @return string User's full name
 */
function getUserName($user_id) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT full_name FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['full_name'];
    }
    
    return 'Unknown User';
}

/**
 * Get organization name by ID
 * @param int $org_id Organization ID
 * @return string Organization name
 */
function getOrganizationName($org_id) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT name, acronym FROM organizations WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $org_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['acronym'] . ' - ' . $row['name'];
    }
    
    return 'Unknown Organization';
}

/**
 * Get payment category name by ID
 * @param int $category_id Category ID
 * @return string Category name
 */
function getPaymentCategoryName($category_id) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT name FROM payment_categories WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $category_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['name'];
    }
    
    return 'Unknown Category';
}

/**
 * Get user's unread notifications count
 * @param int $user_id User ID (defaults to current user)
 * @return int Number of unread notifications
 */
function getUnreadNotificationsCount($user_id = null) {
    if ($user_id === null) {
        require_once __DIR__ . '/../includes/auth.php';
        if (!isLoggedIn()) {
            return 0;
        }
        $user_id = getCurrentUserId();
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['count'];
}

/**
 * Get user's notifications
 * @param int $user_id User ID (defaults to current user)
 * @param int $limit Number of notifications to retrieve (default: 10)
 * @param bool $unread_only Whether to retrieve only unread notifications (default: false)
 * @return array Array of notifications
 */
function getUserNotifications($user_id = null, $limit = 10, $unread_only = false) {
    if ($user_id === null) {
        require_once __DIR__ . '/../includes/auth.php';
        if (!isLoggedIn()) {
            return [];
        }
        $user_id = getCurrentUserId();
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT n.*, o.name as org_name, o.acronym as org_acronym 
              FROM notifications n 
              LEFT JOIN organizations o ON n.org_id = o.id 
              WHERE n.user_id = :user_id ";
    
    if ($unread_only) {
        $query .= "AND n.is_read = 0 ";
    }
    
    $query .= "ORDER BY n.created_at DESC LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mark a notification as read
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (defaults to current user)
 * @return bool True if successful, false otherwise
 */
function markNotificationAsRead($notification_id, $user_id = null) {
    if ($user_id === null) {
        require_once __DIR__ . '/../includes/auth.php';
        if (!isLoggedIn()) {
            return false;
        }
        $user_id = getCurrentUserId();
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $notification_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

/**
 * Create a new notification
 * @param int $user_id User ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type
 * @param int|null $org_id Organization ID (optional)
 * @param int|null $related_id Related entity ID (optional)
 * @return bool True if successful, false otherwise
 */
function createNotification($user_id, $title, $message, $type = NOTIFICATION_OTHER, $org_id = null, $related_id = null) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO notifications (user_id, org_id, title, message, type, related_id)
              VALUES (:user_id, :org_id, :title, :message, :type, :related_id)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':org_id', $org_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':type', $type);
    $stmt->bindParam(':related_id', $related_id);
    
    return $stmt->execute();
}

/**
 * Get total payments amount for an organization
 * @param int $org_id Organization ID
 * @param string $status Payment status (optional)
 * @return float Total amount
 */
function getOrganizationTotalPayments($org_id, $status = null) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT SUM(amount) as total FROM payments WHERE org_id = :org_id";
    
    if ($status !== null) {
        $query .= " AND status = :status";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':org_id', $org_id);
    
    if ($status !== null) {
        $stmt->bindParam(':status', $status);
    }
    
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ? $row['total'] : 0;
}

/**
 * Get organizations where the user is an officer
 * @param int $user_id User ID (defaults to current user)
 * @return array Array of organizations
 */
function getManagedOrganizations($user_id = null) {
    if ($user_id === null) {
        require_once __DIR__ . '/../includes/auth.php';
        if (!isLoggedIn()) {
            return [];
        }
        $user_id = getCurrentUserId();
    }
    
    // Admin can manage all organizations
    if (hasRole(ROLE_ADMIN)) {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM organizations ORDER BY name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT o.* FROM organizations o 
              WHERE o.president_id = :user_id OR o.treasurer_id = :user_id 
              UNION 
              SELECT o.* FROM organizations o 
              JOIN org_members om ON o.id = om.org_id 
              WHERE om.user_id = :user_id AND om.role = 'officer' AND om.status = 'active' 
              ORDER BY name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get payment statistics for an organization
 * @param int $org_id Organization ID
 * @return array Statistics data
 */
function getOrganizationPaymentStats($org_id) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Total payments by status
    $status_query = "SELECT status, COUNT(*) as count, SUM(amount) as total
                     FROM payments
                     WHERE org_id = :org_id
                     GROUP BY status";
    
    $status_stmt = $db->prepare($status_query);
    $status_stmt->bindParam(':org_id', $org_id);
    $status_stmt->execute();
    
    $status_stats = [
        PAYMENT_COMPLETED => ['count' => 0, 'total' => 0],
        PAYMENT_PENDING => ['count' => 0, 'total' => 0],
        PAYMENT_REJECTED => ['count' => 0, 'total' => 0],
        PAYMENT_REFUNDED => ['count' => 0, 'total' => 0]
    ];
    
    while ($row = $status_stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_stats[$row['status']] = [
            'count' => $row['count'],
            'total' => $row['total']
        ];
    }
    
    // Payments by category
    $category_query = "SELECT pc.name, COUNT(p.id) as count, SUM(p.amount) as total
                      FROM payments p
                      JOIN payment_categories pc ON p.category_id = pc.id
                      WHERE p.org_id = :org_id AND p.status = 'completed'
                      GROUP BY p.category_id
                      ORDER BY total DESC";
    
    $category_stmt = $db->prepare($category_query);
    $category_stmt->bindParam(':org_id', $org_id);
    $category_stmt->execute();
    
    $category_stats = [];
    while ($row = $category_stmt->fetch(PDO::FETCH_ASSOC)) {
        $category_stats[] = $row;
    }
    
    // Recent payments
    $recent_query = "SELECT p.*, u.full_name, pc.name as category_name
                    FROM payments p
                    JOIN users u ON p.user_id = u.id
                    JOIN payment_categories pc ON p.category_id = pc.id
                    WHERE p.org_id = :org_id
                    ORDER BY p.payment_date DESC
                    LIMIT 5";
    
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->bindParam(':org_id', $org_id);
    $recent_stmt->execute();
    
    $recent_payments = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly trends (last 6 months)
    $months = [];
    $month_totals = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $month = date('M', strtotime("-$i months"));
        $year = date('Y', strtotime("-$i months"));
        $months[] = "$month $year";
        $month_totals[] = 0;
    }
    
    $trends_query = "SELECT DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount) as total
                    FROM payments
                    WHERE org_id = :org_id AND status = 'completed'
                    AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(payment_date, '%b %Y')
                    ORDER BY payment_date";
    
    $trends_stmt = $db->prepare($trends_query);
    $trends_stmt->bindParam(':org_id', $org_id);
    $trends_stmt->execute();
    
    $db_trends = [];
    while ($row = $trends_stmt->fetch(PDO::FETCH_ASSOC)) {
        $db_trends[$row['month']] = $row['total'];
    }
    
    for ($i = 0; $i < count($months); $i++) {
        if (isset($db_trends[$months[$i]])) {
            $month_totals[$i] = $db_trends[$months[$i]];
        }
    }
    
    return [
        'status_stats' => $status_stats,
        'category_stats' => $category_stats,
        'recent_payments' => $recent_payments,
        'months' => $months,
        'month_totals' => $month_totals
    ];
}

/**
 * Get dashboard statistics based on user role
 * @param int $user_id User ID (defaults to current user)
 * @return array Statistics data
 */
function getDashboardStats($user_id = null) {
    if ($user_id === null) {
        require_once __DIR__ . '/../includes/auth.php';
        if (!isLoggedIn()) {
            return [];
        }
        $user_id = getCurrentUserId();
    }
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $stats = [];
    
    // For admin or officers
    if (hasRole([ROLE_ADMIN, ROLE_OFFICER, ROLE_ADVISER])) {
        // Organizations managed
        $managed_orgs = getManagedOrganizations($user_id);
        $stats['managed_organizations'] = count($managed_orgs);
        
        if (!empty($managed_orgs)) {
            $org_ids = array_column($managed_orgs, 'id');
            $org_ids_str = implode(',', $org_ids);
            
            // Total members across managed organizations
            $members_query = "SELECT COUNT(DISTINCT user_id) as count
                            FROM org_members
                            WHERE org_id IN ($org_ids_str) AND status = 'active'";
            
            $members_stmt = $db->prepare($members_query);
            $members_stmt->execute();
            $members_row = $members_stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_members'] = $members_row['count'];
            
            // Total pending payments
            $pending_query = "SELECT COUNT(*) as count, SUM(amount) as total
                             FROM payments
                             WHERE org_id IN ($org_ids_str) AND status = 'pending'";
            
            $pending_stmt = $db->prepare($pending_query);
            $pending_stmt->execute();
            $pending_row = $pending_stmt->fetch(PDO::FETCH_ASSOC);
            $stats['pending_payments_count'] = $pending_row['count'] ? $pending_row['count'] : 0;
            $stats['pending_payments_total'] = $pending_row['total'] ? $pending_row['total'] : 0;
            
            // Total completed payments
            $completed_query = "SELECT COUNT(*) as count, SUM(amount) as total
                               FROM payments
                               WHERE org_id IN ($org_ids_str) AND status = 'completed'";
            
            $completed_stmt = $db->prepare($completed_query);
            $completed_stmt->execute();
            $completed_row = $completed_stmt->fetch(PDO::FETCH_ASSOC);
            $stats['completed_payments_count'] = $completed_row['count'] ? $completed_row['count'] : 0;
            $stats['completed_payments_total'] = $completed_row['total'] ? $completed_row['total'] : 0;
        } else {
            $stats['total_members'] = 0;
            $stats['pending_payments_count'] = 0;
            $stats['pending_payments_total'] = 0;
            $stats['completed_payments_count'] = 0;
            $stats['completed_payments_total'] = 0;
        }
    }
    
    // For all users including students
    // Organizations joined
    $orgs_query = "SELECT COUNT(*) as count
                  FROM org_members
                  WHERE user_id = :user_id AND status = 'active'";
    
    $orgs_stmt = $db->prepare($orgs_query);
    $orgs_stmt->bindParam(':user_id', $user_id);
    $orgs_stmt->execute();
    $orgs_row = $orgs_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['joined_organizations'] = $orgs_row['count'];
    
    // Payments made
    $payments_query = "SELECT status, COUNT(*) as count, SUM(amount) as total
                      FROM payments
                      WHERE user_id = :user_id
                      GROUP BY status";
    
    $payments_stmt = $db->prepare($payments_query);
    $payments_stmt->bindParam(':user_id', $user_id);
    $payments_stmt->execute();
    
    $payments_stats = [
        PAYMENT_COMPLETED => ['count' => 0, 'total' => 0],
        PAYMENT_PENDING => ['count' => 0, 'total' => 0],
        PAYMENT_REJECTED => ['count' => 0, 'total' => 0],
        PAYMENT_REFUNDED => ['count' => 0, 'total' => 0]
    ];
    
    while ($row = $payments_stmt->fetch(PDO::FETCH_ASSOC)) {
        $payments_stats[$row['status']] = [
            'count' => $row['count'],
            'total' => $row['total']
        ];
    }
    
    $stats['payments'] = $payments_stats;
    
    // Recent payments
    $recent_query = "SELECT p.*, o.name as org_name, o.acronym as org_acronym, pc.name as category_name
                    FROM payments p
                    JOIN organizations o ON p.org_id = o.id
                    JOIN payment_categories pc ON p.category_id = pc.id
                    WHERE p.user_id = :user_id
                    ORDER BY p.payment_date DESC
                    LIMIT 5";
    
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->bindParam(':user_id', $user_id);
    $recent_stmt->execute();
    
    $stats['recent_payments'] = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Upcoming/pending payments
    $pending_categories_query = "SELECT pc.*, o.name as org_name, o.acronym as org_acronym
                               FROM payment_categories pc
                               JOIN organizations o ON pc.org_id = o.id
                               JOIN org_members om ON pc.org_id = om.org_id
                               WHERE om.user_id = :user_id AND om.status = 'active'
                               AND (pc.due_date IS NULL OR pc.due_date >= CURDATE())
                               AND NOT EXISTS (
                                   SELECT 1 FROM payments p
                                   WHERE p.user_id = :user_id AND p.category_id = pc.id
                                   AND (p.status = 'completed' OR p.status = 'pending')
                               )
                               ORDER BY pc.due_date ASC";
    
    $pending_categories_stmt = $db->prepare($pending_categories_query);
    $pending_categories_stmt->bindParam(':user_id', $user_id);
    $pending_categories_stmt->execute();
    
    $stats['pending_categories'] = $pending_categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}
?>
