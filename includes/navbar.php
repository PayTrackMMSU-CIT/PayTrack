<?php
// Get user's unread notifications count
$unread_count = 0; // Temporarily disable until we fix the PostgreSQL compatibility
$notifications = []; // Temporarily disable until we fix the PostgreSQL compatibility
?>

<header class="bg-white shadow">
    <nav class="container mx-auto px-4 py-2">
        <div class="flex justify-between items-center">
            <!-- Logo and Title -->
            <div class="flex items-center">
                <a href="dashboard.php" class="flex items-center">
                    <img src="assets/svg/logo.svg" alt="<?php echo APP_NAME; ?>" class="h-10 w-10 mr-2">
                    <span class="text-blue-800 font-bold text-xl md:text-2xl"><?php echo APP_NAME; ?></span>
                </a>
            </div>
            
            <!-- Navigation Links - Desktop -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="dashboard.php" class="text-gray-700 hover:text-blue-800 transition duration-200 flex items-center">
                    <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                </a>
                <a href="organizations.php" class="text-gray-700 hover:text-blue-800 transition duration-200 flex items-center">
                    <i class="fas fa-users mr-1"></i> Organizations
                </a>
                <a href="transactions.php" class="text-gray-700 hover:text-blue-800 transition duration-200 flex items-center">
                    <i class="fas fa-receipt mr-1"></i> Transactions
                </a>
                <?php if (hasRole([ROLE_OFFICER, ROLE_ADMIN, ROLE_ADVISER])): ?>
                <a href="reports.php" class="text-gray-700 hover:text-blue-800 transition duration-200 flex items-center">
                    <i class="fas fa-chart-bar mr-1"></i> Reports
                </a>
                <?php endif; ?>
                
                <!-- Notifications Dropdown -->
                <div class="relative">
                    <button id="notification-btn" class="text-gray-700 hover:text-blue-800 transition duration-200 flex items-center">
                        <i class="fas fa-bell mr-1"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="bg-red-500 text-white text-xs rounded-full px-1 absolute -top-1 -right-1"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notification-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg overflow-hidden z-20 hidden">
                        <div class="py-2 px-3 bg-blue-800 text-white flex justify-between items-center">
                            <span>Notifications</span>
                            <?php if ($unread_count > 0): ?>
                                <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1"><?php echo $unread_count; ?> new</span>
                            <?php endif; ?>
                        </div>
                        <div class="divide-y divide-gray-200 max-h-80 overflow-y-auto">
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="p-3 hover:bg-gray-100 <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                                        <div class="flex justify-between items-start">
                                            <div class="font-medium"><?php echo htmlspecialchars($notification['title']); ?></div>
                                            <span class="text-xs text-gray-500"><?php echo formatDate($notification['created_at'], 'm/d/y'); ?></span>
                                        </div>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <?php if ($notification['org_id']): ?>
                                            <div class="text-xs text-blue-800 mt-1">
                                                <i class="fas fa-users mr-1"></i> <?php echo htmlspecialchars($notification['org_acronym']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-3 text-center text-gray-500">No notifications</div>
                            <?php endif; ?>
                        </div>
                        <div class="py-2 px-3 bg-gray-100 text-center">
                            <a href="#" class="text-blue-800 hover:underline text-sm">View all notifications</a>
                        </div>
                    </div>
                </div>
                
                <!-- User Dropdown -->
                <div class="relative">
                    <button id="user-menu-btn" class="flex items-center text-gray-700 hover:text-blue-800 transition duration-200">
                        <span class="mr-1 hidden lg:inline-block"><?php echo getCurrentUserName(); ?></span>
                        <i class="fas fa-user-circle text-xl"></i>
                    </button>
                    <div id="user-menu-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 hidden">
                        <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-user-cog mr-2"></i> Profile
                        </a>
                        <div class="border-t border-gray-100"></div>
                        <a href="logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button id="mobile-menu-btn" class="text-gray-700 focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="md:hidden hidden pt-4 pb-2">
            <div class="flex flex-col space-y-3">
                <a href="dashboard.php" class="text-gray-700 hover:text-blue-800 py-2 transition duration-200 flex items-center">
                    <i class="fas fa-tachometer-alt mr-2 w-6 text-center"></i> Dashboard
                </a>
                <a href="organizations.php" class="text-gray-700 hover:text-blue-800 py-2 transition duration-200 flex items-center">
                    <i class="fas fa-users mr-2 w-6 text-center"></i> Organizations
                </a>
                <a href="transactions.php" class="text-gray-700 hover:text-blue-800 py-2 transition duration-200 flex items-center">
                    <i class="fas fa-receipt mr-2 w-6 text-center"></i> Transactions
                </a>
                <?php if (hasRole([ROLE_OFFICER, ROLE_ADMIN, ROLE_ADVISER])): ?>
                <a href="reports.php" class="text-gray-700 hover:text-blue-800 py-2 transition duration-200 flex items-center">
                    <i class="fas fa-chart-bar mr-2 w-6 text-center"></i> Reports
                </a>
                <?php endif; ?>
                <a href="#" class="text-gray-700 hover:text-blue-800 py-2 transition duration-200 flex items-center" id="mobile-notification-btn">
                    <i class="fas fa-bell mr-2 w-6 text-center"></i> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="bg-red-500 text-white text-xs rounded-full px-1 ml-1"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="profile.php" class="text-gray-700 hover:text-blue-800 py-2 transition duration-200 flex items-center">
                    <i class="fas fa-user-cog mr-2 w-6 text-center"></i> Profile
                </a>
                <a href="logout.php" class="text-gray-700 hover:text-blue-800 py-2 transition duration-200 flex items-center">
                    <i class="fas fa-sign-out-alt mr-2 w-6 text-center"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Mobile Notifications Panel -->
        <div id="mobile-notification-panel" class="md:hidden hidden mt-4 bg-white rounded-md shadow-lg overflow-hidden">
            <div class="py-2 px-3 bg-blue-800 text-white flex justify-between items-center">
                <span>Notifications</span>
                <?php if ($unread_count > 0): ?>
                    <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1"><?php echo $unread_count; ?> new</span>
                <?php endif; ?>
            </div>
            <div class="divide-y divide-gray-200 max-h-80 overflow-y-auto">
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="p-3 hover:bg-gray-100 <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                            <div class="flex justify-between items-start">
                                <div class="font-medium"><?php echo htmlspecialchars($notification['title']); ?></div>
                                <span class="text-xs text-gray-500"><?php echo formatDate($notification['created_at'], 'm/d/y'); ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <?php if ($notification['org_id']): ?>
                                <div class="text-xs text-blue-800 mt-1">
                                    <i class="fas fa-users mr-1"></i> <?php echo htmlspecialchars($notification['org_acronym']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-3 text-center text-gray-500">No notifications</div>
                <?php endif; ?>
            </div>
            <div class="py-2 px-3 bg-gray-100 text-center">
                <a href="#" class="text-blue-800 hover:underline text-sm">View all notifications</a>
            </div>
        </div>
    </nav>
</header>
