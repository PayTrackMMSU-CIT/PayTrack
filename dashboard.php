<?php
// Set page title
$page_title = 'Dashboard';

// Include header
require_once 'includes/header.php';

// Require login
requireLogin();

// Get user stats
$stats = getDashboardStats();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800 md:text-3xl">Welcome, <?php echo getCurrentUserName(); ?></h1>
    <p class="text-gray-600">Here's an overview of your PayTrack activities</p>
</div>

<!-- Dashboard Content -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Organizations Card -->
    <div class="stat-card">
        <div class="flex justify-between items-start">
            <div>
                <div class="stat-title">Organizations</div>
                <div class="stat-value"><?php echo $stats['joined_organizations']; ?></div>
                <div class="stat-desc">You have joined</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    
    <!-- Pending Payments Card -->
    <div class="stat-card">
        <div class="flex justify-between items-start">
            <div>
                <div class="stat-title">Pending Payments</div>
                <div class="stat-value"><?php echo isset($stats['payments'][PAYMENT_PENDING]['count']) ? $stats['payments'][PAYMENT_PENDING]['count'] : 0; ?></div>
                <div class="stat-desc">Worth <?php echo formatCurrency(isset($stats['payments'][PAYMENT_PENDING]['total']) ? $stats['payments'][PAYMENT_PENDING]['total'] : 0); ?></div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
    </div>
    
    <!-- Completed Payments Card -->
    <div class="stat-card">
        <div class="flex justify-between items-start">
            <div>
                <div class="stat-title">Completed Payments</div>
                <div class="stat-value"><?php echo isset($stats['payments'][PAYMENT_COMPLETED]['count']) ? $stats['payments'][PAYMENT_COMPLETED]['count'] : 0; ?></div>
                <div class="stat-desc">Worth <?php echo formatCurrency(isset($stats['payments'][PAYMENT_COMPLETED]['total']) ? $stats['payments'][PAYMENT_COMPLETED]['total'] : 0); ?></div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    
    <?php if (hasRole([ROLE_OFFICER, ROLE_ADMIN, ROLE_ADVISER])): ?>
    <!-- Organizations Managed Card (For officers/admin only) -->
    <div class="stat-card">
        <div class="flex justify-between items-start">
            <div>
                <div class="stat-title">Organizations Managed</div>
                <div class="stat-value"><?php echo isset($stats['managed_organizations']) ? $stats['managed_organizations'] : 0; ?></div>
                <div class="stat-desc">As officer/treasurer</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-user-cog"></i>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Total Payments Card (For students) -->
    <div class="stat-card">
        <div class="flex justify-between items-start">
            <div>
                <div class="stat-title">Total Payments</div>
                <div class="stat-value"><?php 
                    $total = 0;
                    foreach ($stats['payments'] as $payment) {
                        if (isset($payment['total'])) $total += $payment['total'];
                    }
                    echo formatCurrency($total);
                ?></div>
                <div class="stat-desc">All-time</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-wallet"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Additional Stats for Officers/Admin -->
<?php if (hasRole([ROLE_OFFICER, ROLE_ADMIN, ROLE_ADVISER]) && isset($stats['managed_organizations']) && $stats['managed_organizations'] > 0): ?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Total Members Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-blue-100 p-3 rounded-full mr-4">
                <i class="fas fa-users text-blue-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Total Members</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo isset($stats['total_members']) ? $stats['total_members'] : 0; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Pending Verification Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-yellow-100 p-3 rounded-full mr-4">
                <i class="fas fa-hourglass-half text-yellow-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Pending Verification</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo isset($stats['pending_payments_count']) ? $stats['pending_payments_count'] : 0; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Total Revenue Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="bg-green-100 p-3 rounded-full mr-4">
                <i class="fas fa-money-bill-wave text-green-800 text-xl"></i>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Total Revenue</div>
                <div class="text-2xl font-bold text-gray-800"><?php echo formatCurrency(isset($stats['completed_payments_total']) ? $stats['completed_payments_total'] : 0); ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Recent Transactions -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h2 class="font-bold text-gray-800 text-lg">Recent Transactions</h2>
        </div>
        <div class="p-6">
            <?php if (isset($stats['recent_payments']) && count($stats['recent_payments']) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr class="text-left">
                                <th>Organization</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['recent_payments'] as $payment): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?php echo htmlspecialchars($payment['org_acronym']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($payment['category_name']); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo getStatusBadge($payment['status']); ?></td>
                                <td><?php echo formatDate($payment['payment_date'], SHORT_DATE_FORMAT); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-center">
                    <a href="transactions.php" class="text-blue-800 hover:underline">View All Transactions</a>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <img src="https://pixabay.com/get/g69ea01953a8fc2965f3f6e80286b3d1dec70c3f27d58a92cd56dddf15838a4ba082dbb3b64eba331e6ad6653402d808cfcce193d0108eb5599a67c11f3144787_1280.jpg" alt="No transactions" class="w-32 h-32 object-cover mx-auto rounded-full mb-4">
                    <p class="text-gray-500">No recent transactions found.</p>
                    <a href="organizations.php" class="mt-2 inline-block px-4 py-2 bg-blue-800 text-white rounded hover:bg-blue-700">Explore Organizations</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Pending Payments -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h2 class="font-bold text-gray-800 text-lg">Pending Payments</h2>
        </div>
        <div class="p-6">
            <?php if (isset($stats['pending_categories']) && count($stats['pending_categories']) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($stats['pending_categories'] as $category): ?>
                    <div class="border rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-semibold"><?php echo htmlspecialchars($category['name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($category['org_acronym']); ?></div>
                            </div>
                            <div class="text-lg font-bold"><?php echo formatCurrency($category['amount']); ?></div>
                        </div>
                        <div class="mt-2">
                            <a href="payment.php?category_id=<?php echo $category['id']; ?>" class="text-sm text-blue-800 hover:underline">Pay Now</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-check-circle text-green-500 text-5xl mb-4"></i>
                    <p class="text-gray-500">No pending payments.</p>
                    <p class="text-sm text-gray-400 mt-2">You're all caught up!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Organizations Section -->
<div class="mt-8">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Your Organizations</h2>
    
    <?php
    $organizations = getUserOrganizations();
    
    if (count($organizations) > 0):
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($organizations as $organization): ?>
        <div class="bg-white rounded-lg shadow overflow-hidden organization-card">
            <div class="h-24 bg-blue-800 flex items-center justify-center relative">
                <?php if ($organization['logo'] && file_exists($organization['logo'])): ?>
                <img src="<?php echo $organization['logo']; ?>" alt="<?php echo htmlspecialchars($organization['name']); ?>" class="h-16 w-16 object-contain">
                <?php else: ?>
                <div class="text-white text-4xl font-bold"><?php echo strtoupper(substr($organization['acronym'], 0, 2)); ?></div>
                <?php endif; ?>
                
                <?php if ($organization['member_role'] !== 'member'): ?>
                <div class="absolute top-2 right-2 bg-white text-blue-800 text-xs px-2 py-1 rounded-full">
                    <?php echo ucfirst($organization['member_role']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <h3 class="font-bold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($organization['acronym']); ?></h3>
                <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($organization['name']); ?></p>
                
                <div class="flex justify-between items-center mt-auto">
                    <a href="organization_details.php?id=<?php echo $organization['id']; ?>" class="text-blue-800 hover:underline text-sm font-medium">View Details</a>
                    
                    <?php echo getStatusBadge($organization['member_status']); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow p-8 text-center">
        <img src="https://pixabay.com/get/geb7a7bbd77259158ba7e97882d8dec8511e25dfe0149536854c56c1a3c889677198181f9538f9f0f578bab94736ae4f839dd5bc4511d277b4743bfef03fe5205_1280.jpg" alt="Join Organizations" class="w-40 h-40 object-cover mx-auto rounded-full mb-4">
        <h3 class="text-xl font-bold text-gray-800 mb-2">Join an Organization</h3>
        <p class="text-gray-600 mb-4">You haven't joined any organizations yet. Explore available organizations at MMSU-CIT.</p>
        <a href="organizations.php" class="inline-block px-4 py-2 bg-blue-800 text-white rounded hover:bg-blue-700">
            <i class="fas fa-users mr-2"></i> Browse Organizations
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
// Include specific JS for dashboard charts
$page_specific_js = '
<script src="assets/js/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize charts if elements exist
    if (document.getElementById("payment-status-chart")) {
        createFinancialStatusChart("payment-status-chart", {
            completed_payments_count: ' . (isset($stats['payments'][PAYMENT_COMPLETED]['count']) ? $stats['payments'][PAYMENT_COMPLETED]['count'] : 0) . ',
            pending_payments_count: ' . (isset($stats['payments'][PAYMENT_PENDING]['count']) ? $stats['payments'][PAYMENT_PENDING]['count'] : 0) . ',
            rejected_payments_count: ' . (isset($stats['payments'][PAYMENT_REJECTED]['count']) ? $stats['payments'][PAYMENT_REJECTED]['count'] : 0) . ',
            refunded_payments_count: ' . (isset($stats['payments'][PAYMENT_REFUNDED]['count']) ? $stats['payments'][PAYMENT_REFUNDED]['count'] : 0) . '
        });
    }
});
</script>';

// Include footer
require_once 'includes/footer.php';
?>
