<?php
// Set page title
$page_title = 'Transactions';

// Include header
require_once 'includes/header.php';

// Require login
requireLogin();

// Initialize variables
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$filter_org = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
$filter_date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Get user ID
$user_id = getCurrentUserId();

// Get user role
$user_role = getCurrentUserRole();

// Get user's organizations
$organizations = getUserOrganizations();

// Build the query based on user role
if (hasRole([ROLE_ADMIN])) {
    // Admin can see all transactions
    $base_query = "SELECT p.*, u.full_name, u.student_id, o.name as org_name, o.acronym as org_acronym, 
                  pc.name as category_name, v.full_name as verified_by_name
                  FROM payments p
                  JOIN users u ON p.user_id = u.id
                  JOIN organizations o ON p.org_id = o.id
                  JOIN payment_categories pc ON p.category_id = pc.id
                  LEFT JOIN users v ON p.verified_by = v.id";
    $count_query = "SELECT COUNT(*) as total FROM payments p
                  JOIN users u ON p.user_id = u.id
                  JOIN organizations o ON p.org_id = o.id
                  JOIN payment_categories pc ON p.category_id = pc.id";
    $where_clause = " WHERE 1=1";
} elseif (hasRole([ROLE_OFFICER, ROLE_ADVISER])) {
    // Officers can see transactions for organizations they manage
    $managed_orgs = getManagedOrganizations();
    if (count($managed_orgs) > 0) {
        $org_ids = array_column($managed_orgs, 'id');
        $org_ids_str = implode(',', $org_ids);
        
        $base_query = "SELECT p.*, u.full_name, u.student_id, o.name as org_name, o.acronym as org_acronym, 
                      pc.name as category_name, v.full_name as verified_by_name
                      FROM payments p
                      JOIN users u ON p.user_id = u.id
                      JOIN organizations o ON p.org_id = o.id
                      JOIN payment_categories pc ON p.category_id = pc.id
                      LEFT JOIN users v ON p.verified_by = v.id";
        $count_query = "SELECT COUNT(*) as total FROM payments p
                      JOIN users u ON p.user_id = u.id
                      JOIN organizations o ON p.org_id = o.id
                      JOIN payment_categories pc ON p.category_id = pc.id";
        $where_clause = " WHERE p.org_id IN ($org_ids_str)";
    } else {
        // No managed organizations, show only personal transactions
        $base_query = "SELECT p.*, u.full_name, u.student_id, o.name as org_name, o.acronym as org_acronym, 
                      pc.name as category_name, v.full_name as verified_by_name
                      FROM payments p
                      JOIN users u ON p.user_id = u.id
                      JOIN organizations o ON p.org_id = o.id
                      JOIN payment_categories pc ON p.category_id = pc.id
                      LEFT JOIN users v ON p.verified_by = v.id";
        $count_query = "SELECT COUNT(*) as total FROM payments p
                      JOIN users u ON p.user_id = u.id
                      JOIN organizations o ON p.org_id = o.id
                      JOIN payment_categories pc ON p.category_id = pc.id";
        $where_clause = " WHERE p.user_id = :user_id";
    }
} else {
    // Regular students can only see their own transactions
    $base_query = "SELECT p.*, u.full_name, u.student_id, o.name as org_name, o.acronym as org_acronym, 
                  pc.name as category_name, v.full_name as verified_by_name
                  FROM payments p
                  JOIN users u ON p.user_id = u.id
                  JOIN organizations o ON p.org_id = o.id
                  JOIN payment_categories pc ON p.category_id = pc.id
                  LEFT JOIN users v ON p.verified_by = v.id";
    $count_query = "SELECT COUNT(*) as total FROM payments p
                  JOIN users u ON p.user_id = u.id
                  JOIN organizations o ON p.org_id = o.id
                  JOIN payment_categories pc ON p.category_id = pc.id";
    $where_clause = " WHERE p.user_id = :user_id";
}

// Add filters
if (!empty($filter_status)) {
    $where_clause .= " AND p.status = :status";
}

if (!empty($filter_org)) {
    $where_clause .= " AND p.org_id = :org_id";
}

if (!empty($filter_date_from)) {
    $where_clause .= " AND DATE(p.payment_date) >= :date_from";
}

if (!empty($filter_date_to)) {
    $where_clause .= " AND DATE(p.payment_date) <= :date_to";
}

if (!empty($search)) {
    $where_clause .= " AND (u.full_name LIKE :search OR u.student_id LIKE :search OR o.name LIKE :search OR o.acronym LIKE :search OR pc.name LIKE :search)";
}

// Add order by
$order_clause = " ORDER BY p.payment_date DESC";

// Add pagination for PostgreSQL
$limit_clause = " LIMIT :limit OFFSET :offset";

// Execute count query
$count_stmt = $db->prepare($count_query . $where_clause);

// Bind parameters for count query with proper PostgreSQL types
if (!hasRole([ROLE_ADMIN]) && (empty($managed_orgs) || count($managed_orgs) === 0)) {
    $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
}

if (!empty($filter_status)) {
    $count_stmt->bindParam(':status', $filter_status, PDO::PARAM_STR);
}

if (!empty($filter_org)) {
    $count_stmt->bindParam(':org_id', $filter_org, PDO::PARAM_INT);
}

if (!empty($filter_date_from)) {
    $count_stmt->bindParam(':date_from', $filter_date_from, PDO::PARAM_STR);
}

if (!empty($filter_date_to)) {
    $count_stmt->bindParam(':date_to', $filter_date_to, PDO::PARAM_STR);
}

if (!empty($search)) {
    $search_param = "%$search%";
    $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
}

$count_stmt->execute();
$count_row = $count_stmt->fetch(PDO::FETCH_ASSOC);
$total_items = $count_row['total'];
$total_pages = ceil($total_items / $items_per_page);

if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

$offset = ($page - 1) * $items_per_page;

// Execute main query
$stmt = $db->prepare($base_query . $where_clause . $order_clause . $limit_clause);

// Bind parameters for main query with proper PostgreSQL types
if (!hasRole([ROLE_ADMIN]) && (empty($managed_orgs) || count($managed_orgs) === 0)) {
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
}

if (!empty($filter_status)) {
    $stmt->bindParam(':status', $filter_status, PDO::PARAM_STR);
}

if (!empty($filter_org)) {
    $stmt->bindParam(':org_id', $filter_org, PDO::PARAM_INT);
}

if (!empty($filter_date_from)) {
    $stmt->bindParam(':date_from', $filter_date_from, PDO::PARAM_STR);
}

if (!empty($filter_date_to)) {
    $stmt->bindParam(':date_to', $filter_date_to, PDO::PARAM_STR);
}

if (!empty($search)) {
    $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
}

$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);

$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Transactions</h1>
        <p class="text-gray-600">View and manage your payment transactions</p>
    </div>
    <div class="mt-4 md:mt-0">
        <?php if (!hasRole([ROLE_ADMIN, ROLE_ADVISER])): ?>
        <a href="payment.php" class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded inline-flex items-center text-sm">
            <i class="fas fa-plus mr-2"></i> New Payment
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-gray-700 text-sm font-medium mb-2">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="<?php echo PAYMENT_PENDING; ?>" <?php if ($filter_status === PAYMENT_PENDING) echo 'selected'; ?>>Pending</option>
                    <option value="<?php echo PAYMENT_COMPLETED; ?>" <?php if ($filter_status === PAYMENT_COMPLETED) echo 'selected'; ?>>Completed</option>
                    <option value="<?php echo PAYMENT_REJECTED; ?>" <?php if ($filter_status === PAYMENT_REJECTED) echo 'selected'; ?>>Rejected</option>
                    <option value="<?php echo PAYMENT_REFUNDED; ?>" <?php if ($filter_status === PAYMENT_REFUNDED) echo 'selected'; ?>>Refunded</option>
                </select>
            </div>
            
            <!-- Organization Filter -->
            <div>
                <label for="org_id" class="block text-gray-700 text-sm font-medium mb-2">Organization</label>
                <select name="org_id" id="org_id" class="form-control">
                    <option value="">All Organizations</option>
                    <?php foreach ($organizations as $org): ?>
                    <option value="<?php echo $org['id']; ?>" <?php if ($filter_org === $org['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($org['acronym']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Date Range Filters -->
            <div>
                <label for="date_from" class="block text-gray-700 text-sm font-medium mb-2">From Date</label>
                <input type="date" name="date_from" id="date_from" value="<?php echo $filter_date_from; ?>" class="form-control">
            </div>
            
            <div>
                <label for="date_to" class="block text-gray-700 text-sm font-medium mb-2">To Date</label>
                <input type="date" name="date_to" id="date_to" value="<?php echo $filter_date_to; ?>" class="form-control">
            </div>
        </div>
        
        <div class="flex flex-col md:flex-row md:justify-between md:items-center space-y-4 md:space-y-0">
            <!-- Search -->
            <div class="md:w-1/2">
                <div class="relative">
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search transactions" class="form-control pl-10">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>
            
            <!-- Buttons -->
            <div class="flex space-x-2">
                <button type="submit" class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">
                    <i class="fas fa-sync-alt mr-2"></i> Reset
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Transactions Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <?php if (count($transactions) > 0): ?>
        <table class="table w-full">
            <thead>
                <tr class="text-left bg-gray-50">
                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Organization</th>
                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($transactions as $transaction): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if (hasRole([ROLE_ADMIN, ROLE_OFFICER, ROLE_ADVISER]) && $transaction['user_id'] != $user_id): ?>
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($transaction['full_name']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($transaction['student_id']); ?></div>
                        <?php else: ?>
                        <div class="font-medium text-gray-900">Payment #<?php echo $transaction['id']; ?></div>
                        <div class="text-sm text-gray-500"><?php echo $transaction['payment_method']; ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium"><?php echo htmlspecialchars($transaction['org_acronym']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo htmlspecialchars($transaction['category_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap font-medium">
                        <?php echo formatCurrency($transaction['amount']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo getStatusBadge($transaction['status']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo formatDate($transaction['payment_date'], SHORT_DATE_FORMAT); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="payment.php?id=<?php echo $transaction['id']; ?>" class="text-blue-800 hover:underline">
                            <i class="fas fa-eye mr-1"></i> View
                        </a>
                        
                        <?php if (isOrgFinanceOfficer($transaction['org_id']) && $transaction['status'] === PAYMENT_PENDING): ?>
                        <div class="mt-2">
                            <a href="payment.php?id=<?php echo $transaction['id']; ?>&action=verify" class="text-green-600 hover:underline">
                                <i class="fas fa-check-circle mr-1"></i> Verify
                            </a>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="p-6 text-center">
            <img src="https://pixabay.com/get/gc922622b4087f6b84c716463635f1900ecc76e8ad1040479bfde98fc417cba32a6e7cdc520a9201857823acdffdac74fd95e93bb4100934ca0bc5e21dd62b463_1280.jpg" alt="No transactions found" class="w-40 h-40 object-cover mx-auto rounded-full mb-4">
            <h3 class="text-xl font-bold text-gray-800 mb-2">No Transactions Found</h3>
            <p class="text-gray-600 mb-4">No transactions match your current filters.</p>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline-block px-4 py-2 bg-blue-800 text-white rounded hover:bg-blue-700">
                <i class="fas fa-sync-alt mr-2"></i> Reset Filters
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <!-- Pagination -->
    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-500">
                Showing <?php echo count($transactions) > 0 ? ($offset + 1) : 0; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> transactions
            </div>
            <div class="flex space-x-1">
                <?php if ($page > 1): ?>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?page=1<?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_org) ? '&org_id=' . urlencode($filter_org) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-white rounded border hover:bg-gray-100">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?page=<?php echo $page - 1; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_org) ? '&org_id=' . urlencode($filter_org) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-white rounded border hover:bg-gray-100">
                    <i class="fas fa-angle-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                if ($end_page - $start_page < 4 && $start_page > 1) {
                    $start_page = max(1, $end_page - 4);
                }
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?page=<?php echo $i; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_org) ? '&org_id=' . urlencode($filter_org) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded border <?php echo $i === $page ? 'bg-blue-800 text-white' : 'bg-white hover:bg-gray-100'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?page=<?php echo $page + 1; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_org) ? '&org_id=' . urlencode($filter_org) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-white rounded border hover:bg-gray-100">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?page=<?php echo $total_pages; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_org) ? '&org_id=' . urlencode($filter_org) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-white rounded border hover:bg-gray-100">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
