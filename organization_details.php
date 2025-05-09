<?php
// Set page title
$page_title = 'Organization Details';

// Include header
require_once 'includes/header.php';

// Require login
requireLogin();

// Initialize variables
$error = '';
$success = '';
$organization = null;
$members = [];
$dues = [];
$isOfficer = false;
$isMember = false;
$isPendingMember = false;

// Check if organization ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'Organization ID is required.');
    header('Location: organizations.php');
    exit;
}

// Get organization ID
$org_id = intval($_GET['id']);

// Get current user ID
$user_id = getCurrentUserId();

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Get organization details
$query = "SELECT o.*, 
         u_p.full_name as president_name, 
         u_t.full_name as treasurer_name, 
         u_a.full_name as adviser_name,
         (SELECT COUNT(*) FROM org_members WHERE org_id = o.id AND status = 'active') as member_count
         FROM organizations o
         LEFT JOIN users u_p ON o.president_id = u_p.id
         LEFT JOIN users u_t ON o.treasurer_id = u_t.id
         LEFT JOIN users u_a ON o.adviser_id = u_a.id
         WHERE o.id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $org_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $organization = $stmt->fetch(PDO::FETCH_ASSOC);
    $page_title = $organization['acronym'] . ' - ' . $page_title;
} else {
    // Organization not found
    setFlashMessage('error', 'Organization not found.');
    header('Location: organizations.php');
    exit;
}

// Check if user is an officer of this organization
$isOfficer = isOrgOfficer($org_id);

// Check if user is already a member of this organization
$member_query = "SELECT * FROM org_members WHERE org_id = :org_id AND user_id = :user_id";
$member_stmt = $db->prepare($member_query);
$member_stmt->bindParam(':org_id', $org_id);
$member_stmt->bindParam(':user_id', $user_id);
$member_stmt->execute();

if ($member_stmt->rowCount() > 0) {
    $member_data = $member_stmt->fetch(PDO::FETCH_ASSOC);
    $isMember = ($member_data['status'] === 'active');
    $isPendingMember = ($member_data['status'] === 'pending');
    $memberRole = $member_data['role'];
}

// Handle join request
if (isset($_GET['action']) && $_GET['action'] === 'join' && !$isMember && !$isPendingMember) {
    // Check if the organization is active
    if ($organization['status'] !== 'active') {
        $error = 'This organization is currently inactive and not accepting new members.';
    } else {
        // Add user as a pending member
        $join_query = "INSERT INTO org_members (org_id, user_id, role, status) VALUES (:org_id, :user_id, 'member', 'pending')";
        $join_stmt = $db->prepare($join_query);
        $join_stmt->bindParam(':org_id', $org_id);
        $join_stmt->bindParam(':user_id', $user_id);
        
        if ($join_stmt->execute()) {
            $success = 'Your membership request has been submitted. It will be reviewed by the organization officers.';
            $isPendingMember = true;
            
            // Notify organization officers
            $officers_query = "SELECT u.id FROM users u 
                            JOIN org_members om ON u.id = om.user_id 
                            WHERE om.org_id = :org_id 
                            AND om.role IN ('officer', 'president', 'treasurer') 
                            AND om.status = 'active'";
            
            $officers_stmt = $db->prepare($officers_query);
            $officers_stmt->bindParam(':org_id', $org_id);
            $officers_stmt->execute();
            
            $officers = $officers_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get user name
            $user_name = getCurrentUserName();
            
            foreach ($officers as $officer) {
                createNotification(
                    $officer['id'],
                    'New Membership Request',
                    $user_name . ' has requested to join ' . $organization['acronym'] . '.',
                    NOTIFICATION_OTHER,
                    $org_id
                );
            }
        } else {
            $error = 'Failed to submit membership request. Please try again.';
        }
    }
}

// Get payment categories (dues)
$dues_query = "SELECT * FROM payment_categories WHERE org_id = :org_id ORDER BY name ASC";
$dues_stmt = $db->prepare($dues_query);
$dues_stmt->bindParam(':org_id', $org_id);
$dues_stmt->execute();
$dues = $dues_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get organization officers
$officers_query = "SELECT u.id, u.full_name, u.email, u.student_id, om.role 
                 FROM users u
                 JOIN org_members om ON u.id = om.user_id
                 WHERE om.org_id = :org_id
                 AND om.role IN ('president', 'treasurer', 'officer')
                 AND om.status = 'active'
                 ORDER BY FIELD(om.role, 'president', 'treasurer', 'officer'), u.full_name";

$officers_stmt = $db->prepare($officers_query);
$officers_stmt->bindParam(':org_id', $org_id);
$officers_stmt->execute();
$officers = $officers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payments for this organization (if user is an officer)
if ($isOfficer) {
    $payments_query = "SELECT p.*, u.full_name, u.student_id, pc.name as category_name
                     FROM payments p
                     JOIN users u ON p.user_id = u.id
                     JOIN payment_categories pc ON p.category_id = pc.id
                     WHERE p.org_id = :org_id
                     ORDER BY p.payment_date DESC
                     LIMIT 5";
                     
    $payments_stmt = $db->prepare($payments_query);
    $payments_stmt->bindParam(':org_id', $org_id);
    $payments_stmt->execute();
    $recent_payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending payments
    $pending_query = "SELECT p.*, u.full_name, u.student_id, pc.name as category_name
                    FROM payments p
                    JOIN users u ON p.user_id = u.id
                    JOIN payment_categories pc ON p.category_id = pc.id
                    WHERE p.org_id = :org_id AND p.status = 'pending'
                    ORDER BY p.payment_date DESC
                    LIMIT 5";
                    
    $pending_stmt = $db->prepare($pending_query);
    $pending_stmt->bindParam(':org_id', $org_id);
    $pending_stmt->execute();
    $pending_payments = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's payment history for this organization
if ($isMember) {
    $user_payments_query = "SELECT p.*, pc.name as category_name
                          FROM payments p
                          JOIN payment_categories pc ON p.category_id = pc.id
                          WHERE p.org_id = :org_id AND p.user_id = :user_id
                          ORDER BY p.payment_date DESC";
                          
    $user_payments_stmt = $db->prepare($user_payments_query);
    $user_payments_stmt->bindParam(':org_id', $org_id);
    $user_payments_stmt->bindParam(':user_id', $user_id);
    $user_payments_stmt->execute();
    $user_payments = $user_payments_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($organization['name']); ?></h1>
        <p class="text-gray-600"><?php echo htmlspecialchars($organization['acronym']); ?> - Student Organization</p>
    </div>
    <div class="mt-4 md:mt-0">
        <a href="organizations.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded inline-flex items-center text-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Organizations
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
<div class="bg-red-100 text-red-800 px-4 py-3 rounded mb-6 flex items-center">
    <i class="fas fa-exclamation-circle mr-2"></i>
    <span><?php echo $error; ?></span>
</div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div class="bg-green-100 text-green-800 px-4 py-3 rounded mb-6 flex items-center">
    <i class="fas fa-check-circle mr-2"></i>
    <span><?php echo $success; ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Organization Info -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-blue-800 px-6 py-12 flex flex-col items-center">
                <?php if ($organization['logo'] && file_exists($organization['logo'])): ?>
                <img src="<?php echo $organization['logo']; ?>" alt="<?php echo htmlspecialchars($organization['name']); ?>" class="h-24 w-24 object-contain bg-white rounded-full p-2">
                <?php else: ?>
                <div class="h-24 w-24 bg-white text-blue-800 rounded-full flex items-center justify-center text-4xl font-bold">
                    <?php echo strtoupper(substr($organization['acronym'], 0, 2)); ?>
                </div>
                <?php endif; ?>
                <h2 class="mt-4 text-white text-2xl font-bold"><?php echo htmlspecialchars($organization['acronym']); ?></h2>
                <p class="text-blue-100"><?php echo htmlspecialchars($organization['name']); ?></p>
                <div class="mt-2 px-3 py-1 bg-white text-blue-800 rounded-full text-sm">
                    <?php echo $organization['member_count']; ?> members
                </div>
            </div>
            
            <div class="p-6">
                <h3 class="font-bold text-gray-800 text-lg mb-4">About the Organization</h3>
                
                <?php if (!empty($organization['description'])): ?>
                <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($organization['description'])); ?></p>
                <?php else: ?>
                <p class="text-gray-500 italic mb-4">No description available.</p>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                    <div>
                        <h4 class="text-sm text-gray-500 mb-1">President</h4>
                        <p class="font-medium"><?php echo !empty($organization['president_name']) ? htmlspecialchars($organization['president_name']) : 'Not assigned'; ?></p>
                    </div>
                    <div>
                        <h4 class="text-sm text-gray-500 mb-1">Treasurer</h4>
                        <p class="font-medium"><?php echo !empty($organization['treasurer_name']) ? htmlspecialchars($organization['treasurer_name']) : 'Not assigned'; ?></p>
                    </div>
                    <div>
                        <h4 class="text-sm text-gray-500 mb-1">Faculty Adviser</h4>
                        <p class="font-medium"><?php echo !empty($organization['adviser_name']) ? htmlspecialchars($organization['adviser_name']) : 'Not assigned'; ?></p>
                    </div>
                    <div>
                        <h4 class="text-sm text-gray-500 mb-1">Status</h4>
                        <p class="font-medium"><?php echo getStatusBadge($organization['status']); ?></p>
                    </div>
                </div>
                
                <!-- Join Organization Button (for non-members) -->
                <?php if (!$isMember && !$isPendingMember && $organization['status'] === 'active'): ?>
                <div class="mt-6 flex justify-end">
                    <a href="organization_details.php?id=<?php echo $org_id; ?>&action=join" class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded inline-flex items-center">
                        <i class="fas fa-user-plus mr-2"></i> Join Organization
                    </a>
                </div>
                <?php elseif ($isPendingMember): ?>
                <div class="mt-6 bg-yellow-100 text-yellow-800 px-4 py-3 rounded flex items-center">
                    <i class="fas fa-hourglass-half mr-2"></i>
                    <span>Your membership request is pending approval.</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment Categories/Dues -->
        <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h3 class="font-bold text-gray-800 text-lg">Payment Categories</h3>
            </div>
            <div class="p-6">
                <?php if (count($dues) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($dues as $due): ?>
                    <div class="border rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 transition duration-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold"><?php echo htmlspecialchars($due['name']); ?></div>
                                <?php if (!empty($due['description'])): ?>
                                <div class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($due['description']); ?></div>
                                <?php endif; ?>
                                
                                <?php if ($due['is_recurring']): ?>
                                <div class="text-xs text-blue-800 mt-1">
                                    <i class="fas fa-sync-alt mr-1"></i> <?php echo ucfirst($due['recurrence']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($due['due_date'])): ?>
                                <div class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> Due: <?php echo formatDate($due['due_date'], SHORT_DATE_FORMAT); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-lg font-bold"><?php echo formatCurrency($due['amount']); ?></div>
                        </div>
                        
                        <?php if ($isMember): ?>
                        <div class="mt-2 flex justify-end">
                            <a href="payment.php?category_id=<?php echo $due['id']; ?>" class="text-sm text-blue-800 hover:underline">Pay Now</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-6">
                    <p class="text-gray-500">No payment categories found for this organization.</p>
                    <?php if ($isOfficer): ?>
                    <p class="mt-2">
                        <a href="manage_payments.php?org_id=<?php echo $org_id; ?>" class="text-blue-800 hover:underline">Create Payment Categories</a>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- User's Payment History (for members) -->
        <?php if ($isMember && isset($user_payments) && count($user_payments) > 0): ?>
        <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h3 class="font-bold text-gray-800 text-lg">My Payment History</h3>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr class="text-left">
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['category_name']); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo getStatusBadge($payment['status']); ?></td>
                                <td><?php echo formatDate($payment['payment_date'], SHORT_DATE_FORMAT); ?></td>
                                <td>
                                    <a href="payment.php?id=<?php echo $payment['id']; ?>" class="text-blue-800 hover:underline">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Payments (for officers only) -->
        <?php if ($isOfficer && isset($recent_payments) && count($recent_payments) > 0): ?>
        <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-800 text-lg">Recent Payments</h3>
                <a href="manage_payments.php?org_id=<?php echo $org_id; ?>" class="text-blue-800 hover:underline text-sm">
                    <i class="fas fa-arrow-right ml-1"></i> View All
                </a>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr class="text-left">
                                <th>Student</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?php echo htmlspecialchars($payment['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($payment['student_id']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($payment['category_name']); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo getStatusBadge($payment['status']); ?></td>
                                <td><?php echo formatDate($payment['payment_date'], SHORT_DATE_FORMAT); ?></td>
                                <td>
                                    <a href="payment.php?id=<?php echo $payment['id']; ?>" class="text-blue-800 hover:underline">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div>
        <!-- Officers -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-800 text-lg">Organization Officers</h3>
                <?php if ($isOfficer): ?>
                <a href="manage_members.php?org_id=<?php echo $org_id; ?>" class="text-blue-800 hover:underline text-sm">
                    Manage <i class="fas fa-arrow-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <?php if (count($officers) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($officers as $officer): ?>
                    <div class="flex items-center">
                        <div class="h-10 w-10 bg-blue-800 text-white rounded-full flex items-center justify-center font-bold mr-3">
                            <?php echo strtoupper(substr($officer['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="font-medium"><?php echo htmlspecialchars($officer['full_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo ucfirst($officer['role']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No officers assigned yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h3 class="font-bold text-gray-800 text-lg">Quick Actions</h3>
            </div>
            <div class="p-6 space-y-3">
                <?php if ($isMember): ?>
                <a href="payment.php?org_id=<?php echo $org_id; ?>" class="block w-full bg-blue-800 hover:bg-blue-700 text-white text-center py-2 px-4 rounded transition duration-200">
                    <i class="fas fa-money-bill-wave mr-2"></i> Make Payment
                </a>
                <?php endif; ?>
                
                <?php if ($isOfficer): ?>
                <a href="manage_members.php?org_id=<?php echo $org_id; ?>" class="block w-full bg-green-600 hover:bg-green-500 text-white text-center py-2 px-4 rounded transition duration-200">
                    <i class="fas fa-users mr-2"></i> Manage Members
                </a>
                
                <a href="manage_payments.php?org_id=<?php echo $org_id; ?>" class="block w-full bg-indigo-600 hover:bg-indigo-500 text-white text-center py-2 px-4 rounded transition duration-200">
                    <i class="fas fa-cog mr-2"></i> Manage Payments
                </a>
                
                <a href="reports.php?org_id=<?php echo $org_id; ?>" class="block w-full bg-yellow-600 hover:bg-yellow-500 text-white text-center py-2 px-4 rounded transition duration-200">
                    <i class="fas fa-chart-bar mr-2"></i> View Reports
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pending Payments (for officers) -->
        <?php if ($isOfficer && isset($pending_payments) && count($pending_payments) > 0): ?>
        <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h3 class="font-bold text-gray-800 text-lg">Pending Payments</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <?php foreach ($pending_payments as $payment): ?>
                    <div class="border rounded-lg p-3 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-medium"><?php echo htmlspecialchars($payment['full_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($payment['category_name']); ?></div>
                            </div>
                            <div class="font-bold"><?php echo formatCurrency($payment['amount']); ?></div>
                        </div>
                        <div class="mt-2 flex justify-end">
                            <a href="payment.php?id=<?php echo $payment['id']; ?>&action=verify" class="text-sm text-blue-800 hover:underline">
                                <i class="fas fa-check-circle mr-1"></i> Verify
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-center">
                    <a href="manage_payments.php?org_id=<?php echo $org_id; ?>&filter=pending" class="text-blue-800 hover:underline text-sm">
                        View All Pending Payments
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
