<?php
// Set page title
$page_title = 'Reports';

// Include header
require_once 'includes/header.php';

// Require login
requireLogin([ROLE_ADMIN, ROLE_OFFICER, ROLE_ADVISER]);

// Initialize variables
$org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : null;
$report_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'financial';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : date('Y-m-d');
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Get user ID
$user_id = getCurrentUserId();

// Get organizations managed by user
$managed_orgs = getManagedOrganizations();

// If org_id is not set, use the first managed organization
if (empty($org_id) && count($managed_orgs) > 0) {
    $org_id = $managed_orgs[0]['id'];
}

// Check if user has access to this organization
if (!empty($org_id) && !hasRole([ROLE_ADMIN])) {
    $has_access = false;
    foreach ($managed_orgs as $org) {
        if ($org['id'] == $org_id) {
            $has_access = true;
            $current_org = $org;
            break;
        }
    }
    
    if (!$has_access) {
        setFlashMessage('error', 'You do not have permission to view reports for this organization.');
        header('Location: dashboard.php');
        exit;
    }
} elseif (!empty($org_id) && hasRole([ROLE_ADMIN])) {
    // Get organization details for admin
    $org_query = "SELECT * FROM organizations WHERE id = :id";
    $org_stmt = $db->prepare($org_query);
    $org_stmt->bindParam(':id', $org_id);
    $org_stmt->execute();
    
    if ($org_stmt->rowCount() > 0) {
        $current_org = $org_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        setFlashMessage('error', 'Organization not found.');
        header('Location: dashboard.php');
        exit;
    }
}

// Get payment categories for the selected organization
if (!empty($org_id)) {
    $categories_query = "SELECT * FROM payment_categories WHERE org_id = :org_id ORDER BY name ASC";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->bindParam(':org_id', $org_id);
    $categories_stmt->execute();
    $payment_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get report data
if (!empty($org_id)) {
    // Financial Summary
    if ($report_type === 'financial') {
        // Total payments by status
        $status_query = "SELECT status, COUNT(*) as count, SUM(amount) as total 
                       FROM payments 
                       WHERE org_id = :org_id 
                       AND payment_date BETWEEN :date_from AND :date_to 
                       GROUP BY status";
        
        $status_stmt = $db->prepare($status_query);
        $status_stmt->bindParam(':org_id', $org_id);
        $status_stmt->bindParam(':date_from', $date_from);
        $status_stmt->bindParam(':date_to', $date_to);
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
        $category_query = "SELECT pc.id, pc.name, COUNT(p.id) as count, SUM(p.amount) as total 
                         FROM payments p 
                         JOIN payment_categories pc ON p.category_id = pc.id 
                         WHERE p.org_id = :org_id 
                         AND p.status = 'completed' 
                         AND p.payment_date BETWEEN :date_from AND :date_to 
                         GROUP BY p.category_id 
                         ORDER BY total DESC";
        
        $category_stmt = $db->prepare($category_query);
        $category_stmt->bindParam(':org_id', $org_id);
        $category_stmt->bindParam(':date_from', $date_from);
        $category_stmt->bindParam(':date_to', $date_to);
        $category_stmt->execute();
        
        $category_stats = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Monthly trends
        $trends_query = "SELECT DATE_FORMAT(payment_date, '%b %Y') as month, 
                        DATE_FORMAT(payment_date, '%Y-%m') as month_sort,
                        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_total,
                        COUNT(CASE WHEN status = 'completed' THEN 1 ELSE NULL END) as completed_count,
                        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_total,
                        COUNT(CASE WHEN status = 'pending' THEN 1 ELSE NULL END) as pending_count
                        FROM payments 
                        WHERE org_id = :org_id 
                        AND payment_date BETWEEN DATE_SUB(:date_from, INTERVAL 6 MONTH) AND :date_to 
                        GROUP BY month, month_sort 
                        ORDER BY month_sort";
        
        $trends_stmt = $db->prepare($trends_query);
        $trends_stmt->bindParam(':org_id', $org_id);
        $trends_stmt->bindParam(':date_from', $date_from);
        $trends_stmt->bindParam(':date_to', $date_to);
        $trends_stmt->execute();
        
        $trends_data = $trends_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top paying members
        $members_query = "SELECT u.id, u.full_name, u.student_id, COUNT(p.id) as payment_count, SUM(p.amount) as payment_total 
                        FROM payments p 
                        JOIN users u ON p.user_id = u.id 
                        WHERE p.org_id = :org_id 
                        AND p.status = 'completed' 
                        AND p.payment_date BETWEEN :date_from AND :date_to 
                        GROUP BY p.user_id 
                        ORDER BY payment_total DESC 
                        LIMIT 10";
        
        $members_stmt = $db->prepare($members_query);
        $members_stmt->bindParam(':org_id', $org_id);
        $members_stmt->bindParam(':date_from', $date_from);
        $members_stmt->bindParam(':date_to', $date_to);
        $members_stmt->execute();
        
        $top_members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Membership Report
    elseif ($report_type === 'membership') {
        // Members by status
        $member_status_query = "SELECT status, COUNT(*) as count 
                              FROM org_members 
                              WHERE org_id = :org_id 
                              GROUP BY status";
        
        $member_status_stmt = $db->prepare($member_status_query);
        $member_status_stmt->bindParam(':org_id', $org_id);
        $member_status_stmt->execute();
        
        $member_status_stats = [
            MEMBER_ACTIVE => 0,
            MEMBER_INACTIVE => 0,
            MEMBER_PENDING => 0
        ];
        
        while ($row = $member_status_stmt->fetch(PDO::FETCH_ASSOC)) {
            $member_status_stats[$row['status']] = $row['count'];
        }
        
        // Members by role
        $member_role_query = "SELECT role, COUNT(*) as count 
                            FROM org_members 
                            WHERE org_id = :org_id AND status = 'active' 
                            GROUP BY role";
        
        $member_role_stmt = $db->prepare($member_role_query);
        $member_role_stmt->bindParam(':org_id', $org_id);
        $member_role_stmt->execute();
        
        $member_role_stats = [
            'member' => 0,
            'officer' => 0,
            'president' => 0,
            'treasurer' => 0
        ];
        
        while ($row = $member_role_stmt->fetch(PDO::FETCH_ASSOC)) {
            $member_role_stats[$row['role']] = $row['count'];
        }
        
        // Recent members
        $recent_members_query = "SELECT u.id, u.full_name, u.student_id, u.email, om.role, om.status, om.joined_at 
                               FROM org_members om 
                               JOIN users u ON om.user_id = u.id 
                               WHERE om.org_id = :org_id 
                               ORDER BY om.joined_at DESC 
                               LIMIT 10";
        
        $recent_members_stmt = $db->prepare($recent_members_query);
        $recent_members_stmt->bindParam(':org_id', $org_id);
        $recent_members_stmt->execute();
        
        $recent_members = $recent_members_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Member join trends
        $member_trends_query = "SELECT DATE_FORMAT(joined_at, '%b %Y') as month, 
                              DATE_FORMAT(joined_at, '%Y-%m') as month_sort,
                              COUNT(*) as count 
                              FROM org_members 
                              WHERE org_id = :org_id 
                              GROUP BY month, month_sort 
                              ORDER BY month_sort";
        
        $member_trends_stmt = $db->prepare($member_trends_query);
        $member_trends_stmt->bindParam(':org_id', $org_id);
        $member_trends_stmt->execute();
        
        $member_trends = $member_trends_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Category-specific Report
    elseif ($report_type === 'category' && $category_id > 0) {
        // Get category details
        $category_query = "SELECT * FROM payment_categories WHERE id = :id AND org_id = :org_id";
        $category_stmt = $db->prepare($category_query);
        $category_stmt->bindParam(':id', $category_id);
        $category_stmt->bindParam(':org_id', $org_id);
        $category_stmt->execute();
        
        if ($category_stmt->rowCount() > 0) {
            $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Payments for this category by status
            $cat_status_query = "SELECT status, COUNT(*) as count, SUM(amount) as total 
                               FROM payments 
                               WHERE org_id = :org_id AND category_id = :category_id 
                               AND payment_date BETWEEN :date_from AND :date_to 
                               GROUP BY status";
            
            $cat_status_stmt = $db->prepare($cat_status_query);
            $cat_status_stmt->bindParam(':org_id', $org_id);
            $cat_status_stmt->bindParam(':category_id', $category_id);
            $cat_status_stmt->bindParam(':date_from', $date_from);
            $cat_status_stmt->bindParam(':date_to', $date_to);
            $cat_status_stmt->execute();
            
            $cat_status_stats = [
                PAYMENT_COMPLETED => ['count' => 0, 'total' => 0],
                PAYMENT_PENDING => ['count' => 0, 'total' => 0],
                PAYMENT_REJECTED => ['count' => 0, 'total' => 0],
                PAYMENT_REFUNDED => ['count' => 0, 'total' => 0]
            ];
            
            while ($row = $cat_status_stmt->fetch(PDO::FETCH_ASSOC)) {
                $cat_status_stats[$row['status']] = [
                    'count' => $row['count'],
                    'total' => $row['total']
                ];
            }
            
            // List of payments for this category
            $cat_payments_query = "SELECT p.*, u.full_name, u.student_id, v.full_name as verified_by_name 
                                FROM payments p 
                                JOIN users u ON p.user_id = u.id 
                                LEFT JOIN users v ON p.verified_by = v.id 
                                WHERE p.org_id = :org_id AND p.category_id = :category_id 
                                AND p.payment_date BETWEEN :date_from AND :date_to 
                                ORDER BY p.payment_date DESC";
            
            $cat_payments_stmt = $db->prepare($cat_payments_query);
            $cat_payments_stmt->bindParam(':org_id', $org_id);
            $cat_payments_stmt->bindParam(':category_id', $category_id);
            $cat_payments_stmt->bindParam(':date_from', $date_from);
            $cat_payments_stmt->bindParam(':date_to', $date_to);
            $cat_payments_stmt->execute();
            
            $category_payments = $cat_payments_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Members who haven't paid
            $unpaid_members_query = "SELECT u.id, u.full_name, u.student_id, u.email 
                                   FROM users u 
                                   JOIN org_members om ON u.id = om.user_id 
                                   WHERE om.org_id = :org_id AND om.status = 'active' 
                                   AND u.id NOT IN (
                                       SELECT user_id FROM payments 
                                       WHERE org_id = :org_id AND category_id = :category_id 
                                       AND (status = 'completed' OR status = 'pending')
                                   )";
            
            $unpaid_members_stmt = $db->prepare($unpaid_members_query);
            $unpaid_members_stmt->bindParam(':org_id', $org_id);
            $unpaid_members_stmt->bindParam(':category_id', $category_id);
            $unpaid_members_stmt->execute();
            
            $unpaid_members = $unpaid_members_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Category not found or not in this organization
            setFlashMessage('error', 'Payment category not found.');
            header("Location: reports.php?org_id=$org_id");
            exit;
        }
    }
    
    // Payments List (detailed transactions)
    elseif ($report_type === 'transactions') {
        // Get all payments for the organization in the date range
        $transactions_query = "SELECT p.*, u.full_name, u.student_id, pc.name as category_name, 
                             v.full_name as verified_by_name 
                             FROM payments p 
                             JOIN users u ON p.user_id = u.id 
                             JOIN payment_categories pc ON p.category_id = pc.id 
                             LEFT JOIN users v ON p.verified_by = v.id 
                             WHERE p.org_id = :org_id 
                             AND p.payment_date BETWEEN :date_from AND :date_to 
                             ORDER BY p.payment_date DESC";
        
        $transactions_stmt = $db->prepare($transactions_query);
        $transactions_stmt->bindParam(':org_id', $org_id);
        $transactions_stmt->bindParam(':date_from', $date_from);
        $transactions_stmt->bindParam(':date_to', $date_to);
        $transactions_stmt->execute();
        
        $transactions = $transactions_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $total_amount = 0;
        $completed_amount = 0;
        $pending_amount = 0;
        
        foreach ($transactions as $transaction) {
            $total_amount += $transaction['amount'];
            if ($transaction['status'] === PAYMENT_COMPLETED) {
                $completed_amount += $transaction['amount'];
            } elseif ($transaction['status'] === PAYMENT_PENDING) {
                $pending_amount += $transaction['amount'];
            }
        }
    }
}
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Reports</h1>
        <p class="text-gray-600">
            <?php if (!empty($current_org)): ?>
            <?php echo htmlspecialchars($current_org['acronym']); ?> - <?php echo htmlspecialchars($current_org['name']); ?>
            <?php else: ?>
            Financial and membership analytics
            <?php endif; ?>
        </p>
    </div>
    <div class="mt-4 md:mt-0">
        <a href="<?php echo !empty($org_id) ? 'organization_details.php?id=' . $org_id : 'dashboard.php'; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded inline-flex items-center text-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>
</div>

<?php if (count($managed_orgs) === 0 && !hasRole([ROLE_ADMIN])): ?>
<div class="bg-white rounded-lg shadow p-8 text-center">
    <img src="https://pixabay.com/get/g167b8d7be852cdac7ecca36885440f4509ea5da9df245977b129fad49467d7b67b6f9f5b9fac341750864f27557284eecb8bd56d592e652728d4b4ddcdf9ebe4_1280.jpg" alt="No access" class="w-40 h-40 object-cover mx-auto rounded-full mb-4">
    <h3 class="text-xl font-bold text-gray-800 mb-2">No Organizations Found</h3>
    <p class="text-gray-600 mb-4">You don't manage any organizations. Only organization officers and admins can access reports.</p>
    <a href="dashboard.php" class="inline-block px-4 py-2 bg-blue-800 text-white rounded hover:bg-blue-700">
        <i class="fas fa-home mr-2"></i> Back to Dashboard
    </a>
</div>
<?php else: ?>

<!-- Organization and Date Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Organization Selector -->
            <div>
                <label for="org_id" class="block text-gray-700 text-sm font-medium mb-2">Organization</label>
                <select name="org_id" id="org_id" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($managed_orgs as $org): ?>
                    <option value="<?php echo $org['id']; ?>" <?php if ($org_id == $org['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($org['acronym']); ?> - <?php echo htmlspecialchars($org['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Report Type -->
            <div>
                <label for="type" class="block text-gray-700 text-sm font-medium mb-2">Report Type</label>
                <select name="type" id="type" class="form-control" onchange="this.form.submit()">
                    <option value="financial" <?php if ($report_type == 'financial') echo 'selected'; ?>>Financial Summary</option>
                    <option value="membership" <?php if ($report_type == 'membership') echo 'selected'; ?>>Membership Report</option>
                    <option value="transactions" <?php if ($report_type == 'transactions') echo 'selected'; ?>>Transactions List</option>
                    <option value="category" <?php if ($report_type == 'category') echo 'selected'; ?>>Category Report</option>
                </select>
            </div>
            
            <!-- Category Selector (only visible for category report) -->
            <?php if ($report_type === 'category' && !empty($payment_categories)): ?>
            <div id="category_selector">
                <label for="category_id" class="block text-gray-700 text-sm font-medium mb-2">Payment Category</label>
                <select name="category_id" id="category_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Select Category</option>
                    <?php foreach ($payment_categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php if ($category_id == $cat['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Spacer for layout when category selector is shown -->
            <div></div>
            <?php else: ?>
            
            <!-- Date Range Selectors -->
            <div>
                <label for="date_from" class="block text-gray-700 text-sm font-medium mb-2">From Date</label>
                <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>" class="form-control">
            </div>
            
            <div>
                <label for="date_to" class="block text-gray-700 text-sm font-medium mb-2">To Date</label>
                <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>" class="form-control">
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Hidden field to preserve category_id when switching report types -->
        <?php if ($report_type === 'category'): ?>
        <input type="hidden" name="date_from" value="<?php echo $date_from; ?>">
        <input type="hidden" name="date_to" value="<?php echo $date_to; ?>">
        <?php elseif (!empty($category_id)): ?>
        <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
        <?php endif; ?>
        
        <div class="flex justify-end">
            <button type="submit" class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<?php if (!empty($org_id)): ?>
<!-- Report Content -->
<?php if ($report_type === 'financial'): ?>
<!-- Financial Summary Report -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Total Collected -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-green-100 p-3 rounded-full mr-4">
                <i class="fas fa-money-bill-wave text-green-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Total Collected</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($status_stats[PAYMENT_COMPLETED]['total'] ?: 0); ?></div>
                <div class="text-sm text-gray-500"><?php echo $status_stats[PAYMENT_COMPLETED]['count'] ?: 0; ?> payments</div>
            </div>
        </div>
    </div>
    
    <!-- Pending Payments -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-yellow-100 p-3 rounded-full mr-4">
                <i class="fas fa-clock text-yellow-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Pending Payments</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($status_stats[PAYMENT_PENDING]['total'] ?: 0); ?></div>
                <div class="text-sm text-gray-500"><?php echo $status_stats[PAYMENT_PENDING]['count'] ?: 0; ?> payments</div>
            </div>
        </div>
    </div>
    
    <!-- Rejected Payments -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-red-100 p-3 rounded-full mr-4">
                <i class="fas fa-times-circle text-red-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Rejected Payments</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($status_stats[PAYMENT_REJECTED]['total'] ?: 0); ?></div>
                <div class="text-sm text-gray-500"><?php echo $status_stats[PAYMENT_REJECTED]['count'] ?: 0; ?> payments</div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Monthly Trends Chart -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h3 class="font-bold text-gray-800 text-lg">Monthly Payment Trends</h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="monthly-trends-chart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Payment Categories Chart -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h3 class="font-bold text-gray-800 text-lg">Payment by Category</h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="categories-chart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Payment Distribution by Category -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="bg-gray-50 px-6 py-4 border-b">
        <h3 class="font-bold text-gray-800 text-lg">Payment Distribution by Category</h3>
    </div>
    <div class="p-6">
        <?php if (count($category_stats) > 0): ?>
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr class="text-left">
                        <th>Category</th>
                        <th>Payments</th>
                        <th>Total Amount</th>
                        <th>Average</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($category_stats as $cat): ?>
                    <tr>
                        <td class="font-medium"><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td><?php echo $cat['count']; ?></td>
                        <td><?php echo formatCurrency($cat['total']); ?></td>
                        <td><?php echo formatCurrency($cat['total'] / $cat['count']); ?></td>
                        <td>
                            <a href="reports.php?org_id=<?php echo $org_id; ?>&type=category&category_id=<?php echo $cat['id']; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="text-blue-800 hover:underline">
                                View Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-6">
            <p class="text-gray-500">No payments found in the selected date range.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Top Paying Members -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="bg-gray-50 px-6 py-4 border-b">
        <h3 class="font-bold text-gray-800 text-lg">Top Paying Members</h3>
    </div>
    <div class="p-6">
        <?php if (count($top_members) > 0): ?>
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr class="text-left">
                        <th>Student</th>
                        <th>ID</th>
                        <th>Number of Payments</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_members as $member): ?>
                    <tr>
                        <td class="font-medium"><?php echo htmlspecialchars($member['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($member['student_id']); ?></td>
                        <td><?php echo $member['payment_count']; ?></td>
                        <td><?php echo formatCurrency($member['payment_total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-6">
            <p class="text-gray-500">No member payments found in the selected date range.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($report_type === 'membership'): ?>
<!-- Membership Report -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Active Members -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-green-100 p-3 rounded-full mr-4">
                <i class="fas fa-user-check text-green-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Active Members</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $member_status_stats[MEMBER_ACTIVE] ?: 0; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Pending Members -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-yellow-100 p-3 rounded-full mr-4">
                <i class="fas fa-user-clock text-yellow-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Pending Members</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $member_status_stats[MEMBER_PENDING] ?: 0; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Inactive Members -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-red-100 p-3 rounded-full mr-4">
                <i class="fas fa-user-times text-red-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Inactive Members</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $member_status_stats[MEMBER_INACTIVE] ?: 0; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Member Role Distribution Chart -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h3 class="font-bold text-gray-800 text-lg">Member Roles Distribution</h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="roles-chart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Member Status Chart -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h3 class="font-bold text-gray-800 text-lg">Member Status Distribution</h3>
        </div>
        <div class="p-6">
            <div class="chart-container">
                <canvas id="status-chart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Member Joining Trends -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="bg-gray-50 px-6 py-4 border-b">
        <h3 class="font-bold text-gray-800 text-lg">Member Joining Trends</h3>
    </div>
    <div class="p-6">
        <div class="chart-container">
            <canvas id="member-trends-chart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Members -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="bg-gray-50 px-6 py-4 border-b">
        <h3 class="font-bold text-gray-800 text-lg">Recent Members</h3>
    </div>
    <div class="p-6">
        <?php if (count($recent_members) > 0): ?>
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr class="text-left">
                        <th>Name</th>
                        <th>Student ID</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_members as $member): ?>
                    <tr>
                        <td class="font-medium"><?php echo htmlspecialchars($member['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($member['student_id']); ?></td>
                        <td><?php echo ucfirst($member['role']); ?></td>
                        <td><?php echo getStatusBadge($member['status']); ?></td>
                        <td><?php echo formatDate($member['joined_at'], SHORT_DATE_FORMAT); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-6">
            <p class="text-gray-500">No members found.</p>
        </div>
        <?php endif; ?>
        
        <div class="mt-4 text-center">
            <a href="manage_members.php?org_id=<?php echo $org_id; ?>" class="text-blue-800 hover:underline">
                <i class="fas fa-users mr-1"></i> Manage Members
            </a>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'category' && isset($category) && !empty($category_id)): ?>
<!-- Category-specific Report -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="bg-blue-800 px-6 py-8">
        <h3 class="text-white text-xl font-bold mb-2"><?php echo htmlspecialchars($category['name']); ?></h3>
        <p class="text-blue-100"><?php echo htmlspecialchars($category['description'] ?: 'No description available.'); ?></p>
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mt-4">
            <div class="bg-white text-blue-800 rounded-lg px-4 py-2 inline-block">
                <span class="font-bold"><?php echo formatCurrency($category['amount']); ?></span>
                <?php if ($category['is_recurring']): ?>
                <span class="text-sm ml-1">(<?php echo ucfirst($category['recurrence']); ?>)</span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($category['due_date'])): ?>
            <div class="mt-2 md:mt-0 bg-blue-700 text-white rounded-lg px-4 py-2 inline-block">
                <i class="fas fa-calendar-alt mr-1"></i> Due: <?php echo formatDate($category['due_date'], SHORT_DATE_FORMAT); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Completed Payments -->
            <div class="bg-green-100 rounded-lg p-4">
                <div class="text-sm text-green-800 mb-1">Completed Payments</div>
                <div class="text-xl font-bold text-green-800"><?php echo formatCurrency($cat_status_stats[PAYMENT_COMPLETED]['total'] ?: 0); ?></div>
                <div class="text-sm text-green-700"><?php echo $cat_status_stats[PAYMENT_COMPLETED]['count'] ?: 0; ?> payments</div>
            </div>
            
            <!-- Pending Payments -->
            <div class="bg-yellow-100 rounded-lg p-4">
                <div class="text-sm text-yellow-800 mb-1">Pending Payments</div>
                <div class="text-xl font-bold text-yellow-800"><?php echo formatCurrency($cat_status_stats[PAYMENT_PENDING]['total'] ?: 0); ?></div>
                <div class="text-sm text-yellow-700"><?php echo $cat_status_stats[PAYMENT_PENDING]['count'] ?: 0; ?> payments</div>
            </div>
            
            <!-- Rejected Payments -->
            <div class="bg-red-100 rounded-lg p-4">
                <div class="text-sm text-red-800 mb-1">Rejected Payments</div>
                <div class="text-xl font-bold text-red-800"><?php echo formatCurrency($cat_status_stats[PAYMENT_REJECTED]['total'] ?: 0); ?></div>
                <div class="text-sm text-red-700"><?php echo $cat_status_stats[PAYMENT_REJECTED]['count'] ?: 0; ?> payments</div>
            </div>
        </div>
        
        <!-- Payments for this Category -->
        <div class="mt-6">
            <h4 class="font-bold text-gray-800 mb-4">Payment Transactions</h4>
            
            <?php if (isset($category_payments) && count($category_payments) > 0): ?>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr class="text-left">
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Verified By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_payments as $payment): ?>
                        <tr>
                            <td>
                                <div class="font-medium"><?php echo htmlspecialchars($payment['full_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($payment['student_id']); ?></div>
                            </td>
                            <td class="font-medium"><?php echo formatCurrency($payment['amount']); ?></td>
                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                            <td><?php echo getStatusBadge($payment['status']); ?></td>
                            <td><?php echo formatDate($payment['payment_date'], SHORT_DATE_FORMAT); ?></td>
                            <td><?php echo !empty($payment['verified_by_name']) ? htmlspecialchars($payment['verified_by_name']) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-6">
                <p class="text-gray-500">No payments found for this category in the selected date range.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Members Who Haven't Paid -->
        <div class="mt-8">
            <h4 class="font-bold text-gray-800 mb-4">Members Who Haven't Paid</h4>
            
            <?php if (isset($unpaid_members) && count($unpaid_members) > 0): ?>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr class="text-left">
                            <th>Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unpaid_members as $member): ?>
                        <tr>
                            <td class="font-medium"><?php echo htmlspecialchars($member['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td>
                                <a href="#" class="text-blue-800 hover:underline" onclick="sendReminder(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name']); ?>', <?php echo $category_id; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                    <i class="fas fa-bell mr-1"></i> Send Reminder
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-6">
                <p class="text-gray-500">All active members have paid or have pending payments for this category.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'transactions'): ?>
<!-- Transactions List Report -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Total Amount -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-blue-100 p-3 rounded-full mr-4">
                <i class="fas fa-money-bill-wave text-blue-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Total Amount</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($total_amount); ?></div>
                <div class="text-sm text-gray-500"><?php echo count($transactions); ?> transactions</div>
            </div>
        </div>
    </div>
    
    <!-- Completed Amount -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-green-100 p-3 rounded-full mr-4">
                <i class="fas fa-check-circle text-green-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Completed Amount</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($completed_amount); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Pending Amount -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-yellow-100 p-3 rounded-full mr-4">
                <i class="fas fa-clock text-yellow-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Pending Amount</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($pending_amount); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
        <h3 class="font-bold text-gray-800 text-lg">All Transactions</h3>
        <a href="#" class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm" onclick="exportReport()">
            <i class="fas fa-download mr-2"></i> Export CSV
        </a>
    </div>
    <div class="p-6">
        <?php if (count($transactions) > 0): ?>
        <div class="overflow-x-auto">
            <table class="table w-full" id="transactions-table">
                <thead>
                    <tr class="text-left">
                        <th>Date</th>
                        <th>Student</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Verified By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo formatDate($transaction['payment_date'], SHORT_DATE_FORMAT); ?></td>
                        <td>
                            <div class="font-medium"><?php echo htmlspecialchars($transaction['full_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($transaction['student_id']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['category_name']); ?></td>
                        <td class="font-medium"><?php echo formatCurrency($transaction['amount']); ?></td>
                        <td><?php echo ucfirst($transaction['payment_method']); ?></td>
                        <td><?php echo getStatusBadge($transaction['status']); ?></td>
                        <td><?php echo !empty($transaction['verified_by_name']) ? htmlspecialchars($transaction['verified_by_name']) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-6">
            <p class="text-gray-500">No transactions found in the selected date range.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>

<?php
// Page specific JS for charts
if (!empty($org_id)) {
    $page_specific_js = '
    <script src="assets/js/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Show/hide category selector based on report type
        const reportTypeSelect = document.getElementById("type");
        const categorySelector = document.getElementById("category_selector");
        
        if (reportTypeSelect && categorySelector) {
            reportTypeSelect.addEventListener("change", function() {
                if (this.value === "category") {
                    categorySelector.style.display = "block";
                } else {
                    categorySelector.style.display = "none";
                }
            });
        }
        
        ' . ($report_type === 'financial' ? '
        // Financial report charts
        if (document.getElementById("monthly-trends-chart")) {
            createLineChart(
                "monthly-trends-chart",
                ' . json_encode(array_column($trends_data, 'month')) . ',
                ' . json_encode(array_column($trends_data, 'completed_total')) . ',
                "Completed Payments",
                {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return "₱" + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            );
        }
        
        if (document.getElementById("categories-chart")) {
            createPieChart(
                "categories-chart",
                ' . json_encode(array_column($category_stats, 'name')) . ',
                ' . json_encode(array_column($category_stats, 'total')) . '
            );
        }
        ' : '') . '
        
        ' . ($report_type === 'membership' ? '
        // Membership report charts
        if (document.getElementById("roles-chart")) {
            createPieChart(
                "roles-chart",
                ["Regular Members", "Officers", "President", "Treasurer"],
                [' . $member_role_stats['member'] . ', ' . $member_role_stats['officer'] . ', ' . $member_role_stats['president'] . ', ' . $member_role_stats['treasurer'] . ']
            );
        }
        
        if (document.getElementById("status-chart")) {
            createPieChart(
                "status-chart",
                ["Active", "Pending", "Inactive"],
                [' . $member_status_stats[MEMBER_ACTIVE] . ', ' . $member_status_stats[MEMBER_PENDING] . ', ' . $member_status_stats[MEMBER_INACTIVE] . '],
                ["rgba(14, 159, 110, 0.7)", "rgba(255, 90, 31, 0.7)", "rgba(224, 36, 36, 0.7)"]
            );
        }
        
        if (document.getElementById("member-trends-chart")) {
            createBarChart(
                "member-trends-chart",
                ' . json_encode(array_column($member_trends, 'month')) . ',
                ' . json_encode(array_column($member_trends, 'count')) . ',
                "New Members"
            );
        }
        ' : '') . '
        
        ' . ($report_type === 'transactions' ? '
        // Export function for transaction list
        window.exportReport = function() {
            const table = document.getElementById("transactions-table");
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll("tr");
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll("td, th");
                
                for (let j = 0; j < cols.length; j++) {
                    // Get the text content and clean it up
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/,/g, ";");
                    row.push(\'"\'+ data + \'"\');
                }
                
                csv.push(row.join(","));
            }
            
            const csvContent = "data:text/csv;charset=utf-8," + csv.join("\\n");
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "transactions_report_' . $current_org['acronym'] . '_' . date('Y-m-d') . '.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };
        ' : '') . '
        
        ' . ($report_type === 'category' && isset($category) ? '
        // Send reminder function for category report
        window.sendReminder = function(userId, userName, categoryId, categoryName) {
            if (confirm("Send payment reminder to " + userName + " for " + categoryName + "?")) {
                fetch("api/send_reminder.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: "user_id=" + userId + "&category_id=" + categoryId + "&org_id=' . $org_id . '"
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Reminder sent successfully!");
                    } else {
                        alert("Failed to send reminder: " + data.message);
                    }
                })
                .catch(error => {
                    alert("An error occurred while sending the reminder.");
                    console.error("Error:", error);
                });
            }
        };
        ' : '') . '
    });
    </script>';
}

// Include footer
require_once 'includes/footer.php';
?>
