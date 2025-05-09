<?php
// Set page title
$page_title = 'Organizations';

// Include header
require_once 'includes/header.php';

// Require login
requireLogin();

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Get user ID
$user_id = getCurrentUserId();

// Get search term
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Get organizations based on user role
if (hasRole([ROLE_ADMIN])) {
    // Admins can see all organizations
    $query = "SELECT o.*, 
            (SELECT COUNT(*) FROM org_members WHERE org_id = o.id AND status = 'active') as member_count
            FROM organizations o
            WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND (o.name LIKE :search OR o.acronym LIKE :search OR o.description LIKE :search)";
    }
    
    $query .= " ORDER BY o.name ASC";
    
} else {
    // Get organizations user is a member of
    $member_query = "SELECT o.*, om.role as member_role, om.status as member_status,
                   (SELECT COUNT(*) FROM org_members WHERE org_id = o.id AND status = 'active') as member_count
                   FROM organizations o
                   JOIN org_members om ON o.id = om.org_id
                   WHERE om.user_id = :user_id";
    
    if (!empty($search)) {
        $member_query .= " AND (o.name LIKE :search OR o.acronym LIKE :search OR o.description LIKE :search)";
    }
    
    $member_query .= " ORDER BY o.name ASC";
    
    // Get organizations user is not a member of
    $non_member_query = "SELECT o.*,
                       NULL as member_role,
                       NULL as member_status,
                       (SELECT COUNT(*) FROM org_members WHERE org_id = o.id AND status = 'active') as member_count
                       FROM organizations o
                       WHERE o.id NOT IN (
                           SELECT org_id FROM org_members WHERE user_id = :user_id
                       )";
    
    if (!empty($search)) {
        $non_member_query .= " AND (o.name LIKE :search OR o.acronym LIKE :search OR o.description LIKE :search)";
    }
    
    $non_member_query .= " ORDER BY o.name ASC";
}

// Execute query for organizations user is a member of
if (!hasRole([ROLE_ADMIN])) {
    $member_stmt = $db->prepare($member_query);
    $member_stmt->bindParam(':user_id', $user_id);
    
    if (!empty($search)) {
        $search_param = "%$search%";
        $member_stmt->bindParam(':search', $search_param);
    }
    
    $member_stmt->execute();
    $member_orgs = $member_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Execute query for organizations user is not a member of
    $non_member_stmt = $db->prepare($non_member_query);
    $non_member_stmt->bindParam(':user_id', $user_id);
    
    if (!empty($search)) {
        $non_member_stmt->bindParam(':search', $search_param);
    }
    
    $non_member_stmt->execute();
    $non_member_orgs = $non_member_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Admin sees all organizations
    $stmt = $db->prepare($query);
    
    if (!empty($search)) {
        $search_param = "%$search%";
        $stmt->bindParam(':search', $search_param);
    }
    
    $stmt->execute();
    $all_orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Organizations</h1>
        <p class="text-gray-600">Browse and manage student organizations at MMSU-CIT</p>
    </div>
    <div class="mt-4 md:mt-0">
        <?php if (hasRole([ROLE_ADMIN])): ?>
        <a href="#" class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded inline-flex items-center text-sm">
            <i class="fas fa-plus mr-2"></i> Create Organization
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
        <div class="flex-grow">
            <div class="relative">
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search organizations" class="form-control pl-10">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
            </div>
        </div>
        
        <div class="flex space-x-2">
            <button type="submit" class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded">
                <i class="fas fa-search mr-2"></i> Search
            </button>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">
                <i class="fas fa-sync-alt mr-2"></i> Reset
            </a>
        </div>
    </form>
</div>

<?php if (!hasRole([ROLE_ADMIN]) && count($member_orgs) > 0): ?>
<!-- Organizations User is a Member Of -->
<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">My Organizations</h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($member_orgs as $org): ?>
        <div class="bg-white rounded-lg shadow overflow-hidden organization-card">
            <div class="h-24 bg-blue-800 flex items-center justify-center relative">
                <?php if ($org['logo'] && file_exists($org['logo'])): ?>
                <img src="<?php echo $org['logo']; ?>" alt="<?php echo htmlspecialchars($org['name']); ?>" class="h-16 w-16 object-contain">
                <?php else: ?>
                <div class="text-white text-4xl font-bold"><?php echo strtoupper(substr($org['acronym'], 0, 2)); ?></div>
                <?php endif; ?>
                
                <?php if ($org['member_role'] && $org['member_role'] !== 'member'): ?>
                <div class="absolute top-2 right-2 bg-white text-blue-800 text-xs px-2 py-1 rounded-full">
                    <?php echo ucfirst($org['member_role']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <h3 class="font-bold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($org['acronym']); ?></h3>
                <p class="text-gray-600 text-sm mb-2"><?php echo htmlspecialchars($org['name']); ?></p>
                <div class="text-sm text-gray-500 mb-4">Members: <?php echo $org['member_count']; ?></div>
                
                <div class="flex justify-between items-center mt-auto">
                    <a href="organization_details.php?id=<?php echo $org['id']; ?>" class="text-blue-800 hover:underline text-sm font-medium">View Details</a>
                    
                    <?php echo getStatusBadge($org['member_status']); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- All Organizations or Organizations to Join -->
<div>
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <?php echo hasRole([ROLE_ADMIN]) ? 'All Organizations' : 'Available Organizations'; ?>
    </h2>
    
    <?php if ((hasRole([ROLE_ADMIN]) && count($all_orgs) > 0) || (!hasRole([ROLE_ADMIN]) && count($non_member_orgs) > 0)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php 
        $display_orgs = hasRole([ROLE_ADMIN]) ? $all_orgs : $non_member_orgs;
        foreach ($display_orgs as $org): 
        ?>
        <div class="bg-white rounded-lg shadow overflow-hidden organization-card">
            <div class="h-24 bg-blue-800 flex items-center justify-center">
                <?php if ($org['logo'] && file_exists($org['logo'])): ?>
                <img src="<?php echo $org['logo']; ?>" alt="<?php echo htmlspecialchars($org['name']); ?>" class="h-16 w-16 object-contain">
                <?php else: ?>
                <div class="text-white text-4xl font-bold"><?php echo strtoupper(substr($org['acronym'], 0, 2)); ?></div>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <h3 class="font-bold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($org['acronym']); ?></h3>
                <p class="text-gray-600 text-sm mb-2"><?php echo htmlspecialchars($org['name']); ?></p>
                <div class="text-sm text-gray-500 mb-4">Members: <?php echo $org['member_count']; ?></div>
                
                <div class="flex justify-between items-center mt-auto">
                    <a href="organization_details.php?id=<?php echo $org['id']; ?>" class="text-blue-800 hover:underline text-sm font-medium">View Details</a>
                    
                    <?php if (hasRole([ROLE_ADMIN])): ?>
                    <a href="manage_members.php?org_id=<?php echo $org['id']; ?>" class="text-blue-800 hover:underline text-sm font-medium">Manage</a>
                    <?php elseif (!$org['member_role']): ?>
                    <a href="organization_details.php?id=<?php echo $org['id']; ?>&action=join" class="bg-blue-800 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded">Join</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow p-8 text-center">
        <img src="https://pixabay.com/get/g003060233e5444330adc115eef06a4fede7780bfcfe2db6d736c8303fc76ffb2698ac7e61185eceed808e003a38b32a5574e4a563b2ff485fffc44f27e8c6214_1280.jpg" alt="No organizations found" class="w-40 h-40 object-cover mx-auto rounded-full mb-4">
        <h3 class="text-xl font-bold text-gray-800 mb-2">No Organizations Found</h3>
        <p class="text-gray-600 mb-4">No organizations match your current search criteria.</p>
        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline-block px-4 py-2 bg-blue-800 text-white rounded hover:bg-blue-700">
            <i class="fas fa-sync-alt mr-2"></i> View All Organizations
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
