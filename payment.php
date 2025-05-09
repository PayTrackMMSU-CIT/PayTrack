<?php
// Set page title
$page_title = 'Payment';

// Include database and header
require_once 'config/database.php';
require_once 'includes/header.php';

// Require login
requireLogin();

// Initialize variables
$error = '';
$success = '';
$payment = null;
$verifying = false;
$edit_mode = false;
$payment_id = null;
$category_id = null;
$categories = [];
$organization = null;
$org_id = null;

// Get current user ID
$user_id = getCurrentUserId();

// Connect to database
$database = new Database();
$db = $database->getConnection();

// Check if viewing existing payment
if (isset($_GET['id'])) {
    $payment_id = intval($_GET['id']);
    
    // Get payment details
    $query = "SELECT p.*, u.full_name, u.student_id, o.name as org_name, o.acronym as org_acronym, 
             pc.name as category_name, pc.description as category_description, v.full_name as verified_by_name
             FROM payments p
             JOIN users u ON p.user_id = u.id
             JOIN organizations o ON p.org_id = o.id
             JOIN payment_categories pc ON p.category_id = pc.id
             LEFT JOIN users v ON p.verified_by = v.id
             WHERE p.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $payment_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if user is authorized to view this payment
        if ($payment['user_id'] != $user_id && !isOrgOfficer($payment['org_id']) && !hasRole([ROLE_ADMIN])) {
            setFlashMessage('error', ERROR_UNAUTHORIZED);
            header('Location: transactions.php');
            exit;
        }
        
        // Check if verifying the payment
        if (isset($_GET['action']) && $_GET['action'] === 'verify' && (isOrgFinanceOfficer($payment['org_id']) || hasRole([ROLE_ADMIN])) && $payment['status'] === PAYMENT_PENDING) {
            $verifying = true;
            $page_title = 'Verify Payment';
        }
        
        // Check if editing payment (only pending payments can be edited)
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && $payment['user_id'] == $user_id && $payment['status'] === PAYMENT_PENDING) {
            $edit_mode = true;
        }
    } else {
        // Payment not found
        setFlashMessage('error', ERROR_NOT_FOUND);
        header('Location: transactions.php');
        exit;
    }
} else {
    // New payment
    // Get category ID if provided
    if (isset($_GET['category_id'])) {
        $category_id = intval($_GET['category_id']);
        
        // Get category details
        $category_query = "SELECT pc.*, o.name as org_name, o.acronym as org_acronym, o.id as org_id
                         FROM payment_categories pc
                         JOIN organizations o ON pc.org_id = o.id
                         WHERE pc.id = :id";
        
        $category_stmt = $db->prepare($category_query);
        $category_stmt->bindParam(':id', $category_id);
        $category_stmt->execute();
        
        if ($category_stmt->rowCount() > 0) {
            $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if user is a member of this organization
            if (!isOrgMember($category['org_id']) && !hasRole([ROLE_ADMIN])) {
                setFlashMessage('error', 'You are not a member of this organization.');
                header('Location: organizations.php');
                exit;
            }
            
            // Set organization ID
            $org_id = $category['org_id'];
        } else {
            // Category not found
            setFlashMessage('error', 'Payment category not found.');
            header('Location: dashboard.php');
            exit;
        }
    } elseif (isset($_GET['org_id'])) {
        // Get organization ID if provided
        $org_id = intval($_GET['org_id']);
        
        // Check if user is a member of this organization
        if (!isOrgMember($org_id) && !hasRole([ROLE_ADMIN])) {
            setFlashMessage('error', 'You are not a member of this organization.');
            header('Location: organizations.php');
            exit;
        }
        
        // Get organization details
        $org_query = "SELECT * FROM organizations WHERE id = :id";
        $org_stmt = $db->prepare($org_query);
        $org_stmt->bindParam(':id', $org_id);
        $org_stmt->execute();
        
        if ($org_stmt->rowCount() > 0) {
            $organization = $org_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get payment categories for this organization
            $categories_query = "SELECT * FROM payment_categories WHERE org_id = :org_id ORDER BY name ASC";
            $categories_stmt = $db->prepare($categories_query);
            $categories_stmt->bindParam(':org_id', $org_id);
            $categories_stmt->execute();
            $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($categories) === 0) {
                setFlashMessage('error', 'No payment categories available for this organization.');
                header('Location: organization_details.php?id=' . $org_id);
                exit;
            }
        } else {
            // Organization not found
            setFlashMessage('error', 'Organization not found.');
            header('Location: organizations.php');
            exit;
        }
    } else {
        // No category or organization specified, get user organizations
        $orgs = getUserOrganizations();
        
        if (count($orgs) === 0) {
            setFlashMessage('error', 'You need to join an organization before making a payment.');
            header('Location: organizations.php');
            exit;
        }
        
        if (count($orgs) === 1) {
            // Redirect to payment page with org_id
            header('Location: payment.php?org_id=' . $orgs[0]['id']);
            exit;
        }
    }
}

// Process payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment']) && $verifying) {
    $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
    $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
    
    if (empty($status) || !in_array($status, [PAYMENT_COMPLETED, PAYMENT_REJECTED])) {
        $error = 'Please select a valid status.';
    } else {
        // Update payment status
        $update_query = "UPDATE payments SET 
                       status = :status, 
                       notes = :notes, 
                       verified_by = :verified_by, 
                       verified_at = NOW() 
                       WHERE id = :id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $status);
        $update_stmt->bindParam(':notes', $notes);
        $update_stmt->bindParam(':verified_by', $user_id);
        $update_stmt->bindParam(':id', $payment_id);
        
        if ($update_stmt->execute()) {
            // Create notification for the student
            $notification_title = $status === PAYMENT_COMPLETED ? 'Payment Approved' : 'Payment Rejected';
            $notification_message = $status === PAYMENT_COMPLETED ? 
                'Your payment of ' . formatCurrency($payment['amount']) . ' for ' . $payment['category_name'] . ' has been approved.' : 
                'Your payment of ' . formatCurrency($payment['amount']) . ' for ' . $payment['category_name'] . ' has been rejected. Reason: ' . $notes;
            
            createNotification($payment['user_id'], $notification_title, $notification_message, NOTIFICATION_PAYMENT, $payment['org_id'], $payment_id);
            
            setFlashMessage('success', 'Payment has been ' . ($status === PAYMENT_COMPLETED ? 'approved' : 'rejected') . ' successfully.');
            header('Location: transactions.php');
            exit;
        } else {
            $error = 'Failed to update payment status. Please try again.';
        }
    }
}

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment']) && !$verifying) {
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $payment_method = isset($_POST['payment_method']) ? sanitizeInput($_POST['payment_method']) : '';
    $reference_number = isset($_POST['reference_number']) ? sanitizeInput($_POST['reference_number']) : '';
    $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
    
    // Validate inputs
    if ($category_id <= 0) {
        $error = 'Please select a valid payment category.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid amount.';
    } elseif (empty($payment_method)) {
        $error = 'Please select a payment method.';
    } else {
        // Check if editing existing payment
        if ($edit_mode && $payment_id) {
            // Update payment
            $update_query = "UPDATE payments SET 
                           category_id = :category_id, 
                           amount = :amount, 
                           payment_method = :payment_method, 
                           reference_number = :reference_number, 
                           notes = :notes, 
                           status = 'pending', 
                           verified_by = NULL, 
                           verified_at = NULL 
                           WHERE id = :id AND user_id = :user_id AND status = 'pending'";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':category_id', $category_id);
            $update_stmt->bindParam(':amount', $amount);
            $update_stmt->bindParam(':payment_method', $payment_method);
            $update_stmt->bindParam(':reference_number', $reference_number);
            $update_stmt->bindParam(':notes', $notes);
            $update_stmt->bindParam(':id', $payment_id);
            $update_stmt->bindParam(':user_id', $user_id);
            
            if ($update_stmt->execute()) {
                setFlashMessage('success', 'Payment updated successfully. It will be reviewed by the organization officers.');
                header('Location: transactions.php');
                exit;
            } else {
                $error = 'Failed to update payment. Please try again.';
            }
        } else {
            // Get organization ID from category
            $category_query = "SELECT org_id FROM payment_categories WHERE id = :id";
            $category_stmt = $db->prepare($category_query);
            $category_stmt->bindParam(':id', $category_id);
            $category_stmt->execute();
            
            if ($category_stmt->rowCount() > 0) {
                $category = $category_stmt->fetch(PDO::FETCH_ASSOC);
                $org_id = $category['org_id'];
                
                // Insert new payment
                $insert_query = "INSERT INTO payments 
                               (user_id, org_id, category_id, amount, payment_method, reference_number, notes, status) 
                               VALUES 
                               (:user_id, :org_id, :category_id, :amount, :payment_method, :reference_number, :notes, 'pending')";
                
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':user_id', $user_id);
                $insert_stmt->bindParam(':org_id', $org_id);
                $insert_stmt->bindParam(':category_id', $category_id);
                $insert_stmt->bindParam(':amount', $amount);
                $insert_stmt->bindParam(':payment_method', $payment_method);
                $insert_stmt->bindParam(':reference_number', $reference_number);
                $insert_stmt->bindParam(':notes', $notes);
                
                if ($insert_stmt->execute()) {
                    $payment_id = $db->lastInsertId();
                    
                    // Notify organization officers
                    $org_officers_query = "SELECT u.id FROM users u 
                                         JOIN org_members om ON u.id = om.user_id 
                                         WHERE om.org_id = :org_id 
                                         AND om.role IN ('officer', 'president', 'treasurer') 
                                         AND om.status = 'active'";
                    
                    $org_officers_stmt = $db->prepare($org_officers_query);
                    $org_officers_stmt->bindParam(':org_id', $org_id);
                    $org_officers_stmt->execute();
                    
                    $officers = $org_officers_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get user name
                    $user_name = getCurrentUserName();
                    
                    // Get category name
                    $cat_name_query = "SELECT name FROM payment_categories WHERE id = :id";
                    $cat_name_stmt = $db->prepare($cat_name_query);
                    $cat_name_stmt->bindParam(':id', $category_id);
                    $cat_name_stmt->execute();
                    $cat_name = $cat_name_stmt->fetch(PDO::FETCH_ASSOC)['name'];
                    
                    foreach ($officers as $officer) {
                        createNotification(
                            $officer['id'],
                            'New Payment Received',
                            $user_name . ' has submitted a payment of ' . formatCurrency($amount) . ' for ' . $cat_name . '.',
                            NOTIFICATION_PAYMENT,
                            $org_id,
                            $payment_id
                        );
                    }
                    
                    setFlashMessage('success', 'Payment submitted successfully. It will be reviewed by the organization officers.');
                    header('Location: transactions.php');
                    exit;
                } else {
                    $error = 'Failed to submit payment. Please try again.';
                }
            } else {
                $error = 'Invalid payment category.';
            }
        }
    }
}

// If no payment and no org_id, show organization selection
if (!$payment && !$org_id && !isset($_GET['org_id'])) {
    // Get user organizations
    $orgs_query = "SELECT o.* 
                  FROM organizations o 
                  JOIN org_members om ON o.id = om.org_id 
                  WHERE om.user_id = :user_id 
                  AND om.status = 'active' 
                  ORDER BY o.name ASC";
    
    $orgs_stmt = $db->prepare($orgs_query);
    $orgs_stmt->bindParam(':user_id', $user_id);
    $orgs_stmt->execute();
    
    $organizations = $orgs_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If we have an org_id but no category_id, get categories
if ($org_id && !$category_id && !$payment) {
    // Get payment categories for this organization
    $categories_query = "SELECT * FROM payment_categories WHERE org_id = :org_id ORDER BY name ASC";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->bindParam(':org_id', $org_id);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get organization details
    $org_query = "SELECT * FROM organizations WHERE id = :id";
    $org_stmt = $db->prepare($org_query);
    $org_stmt->bindParam(':id', $org_id);
    $org_stmt->execute();
    $organization = $org_stmt->fetch(PDO::FETCH_ASSOC);
}

// If editing, get categories for the organization
if ($edit_mode && $payment) {
    // Get payment categories for this organization
    $categories_query = "SELECT * FROM payment_categories WHERE org_id = :org_id ORDER BY name ASC";
    $categories_stmt = $db->prepare($categories_query);
    $categories_stmt->bindParam(':org_id', $payment['org_id']);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            <?php if ($verifying): ?>
                Verify Payment
            <?php elseif ($payment): ?>
                <?php echo $edit_mode ? 'Edit Payment' : 'Payment Details'; ?>
            <?php elseif ($category_id): ?>
                New Payment - <?php echo htmlspecialchars($category['name']); ?>
            <?php elseif ($org_id): ?>
                New Payment - <?php echo htmlspecialchars($organization['name']); ?>
            <?php else: ?>
                New Payment
            <?php endif; ?>
        </h1>
        <p class="text-gray-600">
            <?php if ($verifying): ?>
                Review and verify student payment
            <?php elseif ($payment): ?>
                <?php echo $edit_mode ? 'Update your payment information' : 'View payment transaction details'; ?>
            <?php else: ?>
                Submit a new payment to your organization
            <?php endif; ?>
        </p>
    </div>
    <div class="mt-4 md:mt-0">
        <a href="<?php echo $payment ? 'transactions.php' : 'dashboard.php'; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded inline-flex items-center text-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back
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

<!-- Organization Selection (if needed) -->
<?php if (!$payment && !$org_id && !isset($_GET['org_id']) && isset($organizations) && count($organizations) > 0): ?>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="bg-gray-50 px-6 py-4 border-b">
        <h2 class="font-bold text-gray-800 text-lg">Select an Organization</h2>
        <p class="text-sm text-gray-600">Choose the organization you want to pay to</p>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($organizations as $org): ?>
            <a href="payment.php?org_id=<?php echo $org['id']; ?>" class="block p-4 border rounded-lg hover:border-blue-500 hover:bg-blue-50 transition duration-200">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-800 rounded-full flex items-center justify-center text-white font-bold text-xl mr-4">
                        <?php echo strtoupper(substr($org['acronym'], 0, 2)); ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($org['acronym']); ?></h3>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($org['name']); ?></p>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Payment Form or Details -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <?php if ($verifying): ?>
    <!-- Verification Form -->
    <div class="bg-gray-50 px-6 py-4 border-b">
        <h2 class="font-bold text-gray-800 text-lg">Verify Payment #<?php echo $payment_id; ?></h2>
        <p class="text-sm text-gray-600">Review and verify this payment transaction</p>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Student</div>
                    <div class="font-medium"><?php echo htmlspecialchars($payment['full_name']); ?> (<?php echo htmlspecialchars($payment['student_id']); ?>)</div>
                </div>
                
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Category</div>
                    <div class="font-medium"><?php echo htmlspecialchars($payment['category_name']); ?></div>
                </div>
                
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Payment Method</div>
                    <div class="font-medium"><?php echo ucfirst($payment['payment_method']); ?></div>
                </div>
                
                <?php if (!empty($payment['reference_number'])): ?>
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Reference Number</div>
                    <div class="font-medium"><?php echo htmlspecialchars($payment['reference_number']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div>
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Amount</div>
                    <div class="text-2xl font-bold text-blue-800"><?php echo formatCurrency($payment['amount']); ?></div>
                </div>
                
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Date</div>
                    <div class="font-medium"><?php echo formatDate($payment['payment_date']); ?></div>
                </div>
                
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Status</div>
                    <div class="font-medium"><?php echo getStatusBadge($payment['status']); ?></div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($payment['notes'])): ?>
        <div class="mb-6">
            <div class="text-sm text-gray-500 mb-1">Student Notes</div>
            <div class="bg-gray-50 p-3 rounded border"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($payment['proof_of_payment'])): ?>
        <div class="mb-6">
            <div class="text-sm text-gray-500 mb-1">Proof of Payment</div>
            <div class="bg-gray-50 p-3 rounded border">
                <a href="<?php echo htmlspecialchars($payment['proof_of_payment']); ?>" target="_blank" class="text-blue-800 hover:underline">
                    <i class="fas fa-file-alt mr-1"></i> View Proof of Payment
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?id=<?php echo $payment_id; ?>&action=verify" method="post" data-validate="true">
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Verification Action <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                    <div class="payment-method-option relative p-4 border rounded-lg cursor-pointer" data-method="<?php echo PAYMENT_COMPLETED; ?>">
                        <input type="radio" name="status" id="status_approved" value="<?php echo PAYMENT_COMPLETED; ?>" class="hidden" required>
                        <label for="status_approved" class="flex items-center cursor-pointer">
                            <div class="h-5 w-5 border-2 border-gray-300 rounded-full mr-3 flex-shrink-0">
                                <div class="h-3 w-3 m-0.5 rounded-full bg-blue-800 hidden"></div>
                            </div>
                            <div>
                                <span class="block font-medium text-gray-900">Approve Payment</span>
                                <span class="block text-sm text-gray-500">Mark this payment as completed</span>
                            </div>
                        </label>
                    </div>
                    
                    <div class="payment-method-option relative p-4 border rounded-lg cursor-pointer" data-method="<?php echo PAYMENT_REJECTED; ?>">
                        <input type="radio" name="status" id="status_rejected" value="<?php echo PAYMENT_REJECTED; ?>" class="hidden" required>
                        <label for="status_rejected" class="flex items-center cursor-pointer">
                            <div class="h-5 w-5 border-2 border-gray-300 rounded-full mr-3 flex-shrink-0">
                                <div class="h-3 w-3 m-0.5 rounded-full bg-blue-800 hidden"></div>
                            </div>
                            <div>
                                <span class="block font-medium text-gray-900">Reject Payment</span>
                                <span class="block text-sm text-gray-500">Reject this payment transaction</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <label for="notes" class="block text-gray-700 font-medium mb-2">Notes</label>
                <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Enter any notes for the student regarding this payment verification"></textarea>
                <p class="text-xs text-gray-500 mt-1">Required if rejecting the payment</p>
            </div>
            
            <div class="border-t pt-6 flex justify-between">
                <a href="transactions.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" name="verify_payment" class="bg-blue-800 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                    <i class="fas fa-check mr-2"></i> Submit Verification
                </button>
            </div>
        </form>
    </div>
    <?php elseif ($payment && !$edit_mode): ?>
    <!-- Payment Details -->
    <div class="bg-gray-50 px-6 py-4 border-b">
        <h2 class="font-bold text-gray-800 text-lg">Payment #<?php echo $payment_id; ?> Details</h2>
        <div class="flex items-center mt-2">
            <div class="mr-2"><?php echo getStatusBadge($payment['status']); ?></div>
            <div class="text-sm text-gray-500"><?php echo formatDate($payment['payment_date']); ?></div>
        </div>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Organization</div>
                    <div class="font-medium"><?php echo htmlspecialchars($payment['org_name']); ?> (<?php echo htmlspecialchars($payment['org_acronym']); ?>)</div>
                </div>
                
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Category</div>
                    <div class="font-medium"><?php echo htmlspecialchars($payment['category_name']); ?></div>
                </div>
                
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Payment Method</div>
                    <div class="font-medium"><?php echo ucfirst($payment['payment_method']); ?></div>
                </div>
                
                <?php if (!empty($payment['reference_number'])): ?>
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Reference Number</div>
                    <div class="font-medium"><?php echo htmlspecialchars($payment['reference_number']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div>
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Amount</div>
                    <div class="text-2xl font-bold text-blue-800"><?php echo formatCurrency($payment['amount']); ?></div>
                </div>
                
                <?php if ($payment['status'] !== PAYMENT_PENDING): ?>
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Verified By</div>
                    <div class="font-medium"><?php echo htmlspecialchars($payment['verified_by_name'] ?: 'N/A'); ?></div>
                </div>
                
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Verification Date</div>
                    <div class="font-medium"><?php echo $payment['verified_at'] ? formatDate($payment['verified_at']) : 'N/A'; ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($payment['notes'])): ?>
        <div class="mb-6">
            <div class="text-sm text-gray-500 mb-1">Notes</div>
            <div class="bg-gray-50 p-3 rounded border"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($payment['proof_of_payment'])): ?>
        <div class="mb-6">
            <div class="text-sm text-gray-500 mb-1">Proof of Payment</div>
            <div class="bg-gray-50 p-3 rounded border">
                <a href="<?php echo htmlspecialchars($payment['proof_of_payment']); ?>" target="_blank" class="text-blue-800 hover:underline">
                    <i class="fas fa-file-alt mr-1"></i> View Proof of Payment
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="border-t pt-6 flex justify-between items-center">
            <a href="transactions.php" class="text-blue-800 hover:underline">
                <i class="fas fa-arrow-left mr-1"></i> Back to Transactions
            </a>
            
            <?php if ($payment['status'] === PAYMENT_PENDING && $payment['user_id'] == $user_id): ?>
            <a href="payment.php?id=<?php echo $payment_id; ?>&action=edit" class="bg-blue-800 hover:bg-blue-700 text-white px-4 py-2 rounded">
                <i class="fas fa-edit mr-2"></i> Edit Payment
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Payment Form -->
    <div class="bg-gray-50 px-6 py-4 border-b">
        <h2 class="font-bold text-gray-800 text-lg">
            <?php if ($edit_mode): ?>
                Edit Payment
            <?php elseif ($category_id): ?>
                Payment for <?php echo htmlspecialchars($category['name']); ?>
            <?php else: ?>
                New Payment
            <?php endif; ?>
        </h2>
        <p class="text-sm text-gray-600">
            <?php if ($edit_mode): ?>
                Update your payment information
            <?php else: ?>
                Fill in the required information to submit your payment
            <?php endif; ?>
        </p>
    </div>
    <div class="p-6">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($edit_mode ? '?id=' . $payment_id . '&action=edit' : ($category_id ? '?category_id=' . $category_id : ''))); ?>" method="post" data-validate="true" enctype="multipart/form-data">
            <?php if (count($categories) > 0 && !$category_id): ?>
            <div class="mb-6">
                <label for="category_id" class="block text-gray-700 font-medium mb-2">Payment Category <span class="text-red-500">*</span></label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">Select Payment Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_mode && $payment['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?> - <?php echo formatCurrency($cat['amount']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Select the purpose of your payment</p>
            </div>
            <?php elseif ($category_id): ?>
            <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
            <div class="mb-6">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="font-medium text-blue-800 mb-1"><?php echo htmlspecialchars($category['name']); ?></div>
                    <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($category['description']); ?></p>
                    <div class="text-lg font-bold"><?php echo formatCurrency($category['amount']); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mb-6">
                <label for="amount" class="block text-gray-700 font-medium mb-2">Amount <span class="text-red-500">*</span></label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500">₱</span>
                    </div>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" 
                           value="<?php echo $edit_mode ? htmlspecialchars($payment['amount']) : ($category_id ? htmlspecialchars($category['amount']) : ''); ?>" 
                           class="form-control pl-8" required <?php echo $category_id ? 'readonly' : ''; ?>>
                </div>
                <p class="text-xs text-gray-500 mt-1">Enter the payment amount</p>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-2">Payment Method <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                    <div class="payment-method-option relative p-4 border rounded-lg cursor-pointer <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_CASH) ? 'selected' : ''; ?>" data-method="<?php echo PAYMENT_CASH; ?>">
                        <input type="radio" name="payment_method" id="method_cash" value="<?php echo PAYMENT_CASH; ?>" class="hidden" <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_CASH) ? 'checked' : ''; ?> required>
                        <label for="method_cash" class="flex items-center cursor-pointer">
                            <div class="h-5 w-5 border-2 border-gray-300 rounded-full mr-3 flex-shrink-0">
                                <div class="h-3 w-3 m-0.5 rounded-full bg-blue-800 <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_CASH) ? '' : 'hidden'; ?>"></div>
                            </div>
                            <div>
                                <span class="font-medium">Cash</span>
                            </div>
                        </label>
                    </div>
                    
                    <div class="payment-method-option relative p-4 border rounded-lg cursor-pointer <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_GCASH) ? 'selected' : ''; ?>" data-method="<?php echo PAYMENT_GCASH; ?>">
                        <input type="radio" name="payment_method" id="method_gcash" value="<?php echo PAYMENT_GCASH; ?>" class="hidden" <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_GCASH) ? 'checked' : ''; ?> required>
                        <label for="method_gcash" class="flex items-center cursor-pointer">
                            <div class="h-5 w-5 border-2 border-gray-300 rounded-full mr-3 flex-shrink-0">
                                <div class="h-3 w-3 m-0.5 rounded-full bg-blue-800 <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_GCASH) ? '' : 'hidden'; ?>"></div>
                            </div>
                            <div>
                                <span class="font-medium">GCash</span>
                            </div>
                        </label>
                    </div>
                    
                    <div class="payment-method-option relative p-4 border rounded-lg cursor-pointer <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_BANK) ? 'selected' : ''; ?>" data-method="<?php echo PAYMENT_BANK; ?>">
                        <input type="radio" name="payment_method" id="method_bank" value="<?php echo PAYMENT_BANK; ?>" class="hidden" <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_BANK) ? 'checked' : ''; ?> required>
                        <label for="method_bank" class="flex items-center cursor-pointer">
                            <div class="h-5 w-5 border-2 border-gray-300 rounded-full mr-3 flex-shrink-0">
                                <div class="h-3 w-3 m-0.5 rounded-full bg-blue-800 <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_BANK) ? '' : 'hidden'; ?>"></div>
                            </div>
                            <div>
                                <span class="font-medium">Bank Transfer</span>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Hidden input to store payment method -->
                <input type="hidden" id="payment_method" name="payment_method" value="<?php echo $edit_mode ? $payment['payment_method'] : ''; ?>">
            </div>
            
            <!-- Reference Number (shown for GCash and Bank Transfer) -->
            <div class="payment-additional-fields mb-6 <?php echo ($edit_mode && ($payment['payment_method'] === PAYMENT_GCASH || $payment['payment_method'] === PAYMENT_BANK)) ? '' : 'hidden'; ?>" data-method="<?php echo PAYMENT_GCASH; ?>">
                <label for="reference_number" class="block text-gray-700 font-medium mb-2">Reference Number <span class="text-red-500">*</span></label>
                <input type="text" id="reference_number" name="reference_number" value="<?php echo $edit_mode ? htmlspecialchars($payment['reference_number']) : ''; ?>" class="form-control" placeholder="Enter transaction reference number">
                <p class="text-xs text-gray-500 mt-1">Enter the reference number from your GCash transaction</p>
            </div>
            
            <div class="payment-additional-fields mb-6 <?php echo ($edit_mode && $payment['payment_method'] === PAYMENT_BANK) ? '' : 'hidden'; ?>" data-method="<?php echo PAYMENT_BANK; ?>">
                <label for="reference_number" class="block text-gray-700 font-medium mb-2">Reference Number <span class="text-red-500">*</span></label>
                <input type="text" id="reference_number" name="reference_number" value="<?php echo $edit_mode ? htmlspecialchars($payment['reference_number']) : ''; ?>" class="form-control" placeholder="Enter transaction reference number">
                <p class="text-xs text-gray-500 mt-1">Enter the reference number from your bank transfer</p>
            </div>
            
            <div class="mb-6">
                <label for="notes" class="block text-gray-700 font-medium mb-2">Additional Notes</label>
                <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Enter any additional notes about this payment"><?php echo $edit_mode ? htmlspecialchars($payment['notes']) : ''; ?></textarea>
            </div>
            
            <div class="border-t pt-6 flex justify-between">
                <a href="<?php echo $edit_mode ? 'payment.php?id=' . $payment_id : 'transactions.php'; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" name="submit_payment" class="bg-blue-800 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-200">
                    <i class="fas fa-save mr-2"></i> <?php echo $edit_mode ? 'Update Payment' : 'Submit Payment'; ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php
// Page specific JS for payment methods
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Payment method selection
    const methodOptions = document.querySelectorAll(".payment-method-option");
    const methodInput = document.getElementById("payment_method");
    const additionalFields = document.querySelectorAll(".payment-additional-fields");
    
    if (methodOptions.length > 0 && methodInput) {
        methodOptions.forEach(function(option) {
            option.addEventListener("click", function() {
                const method = this.getAttribute("data-method");
                
                // Update radio buttons
                const radio = this.querySelector("input[type=radio]");
                radio.checked = true;
                
                // Update hidden input
                methodInput.value = method;
                
                // Update visual state
                methodOptions.forEach(function(opt) {
                    opt.classList.remove("selected");
                    const indicator = opt.querySelector(".h-3.w-3");
                    if (indicator) indicator.classList.add("hidden");
                });
                
                this.classList.add("selected");
                const selectedIndicator = this.querySelector(".h-3.w-3");
                if (selectedIndicator) selectedIndicator.classList.remove("hidden");
                
                // Show/hide additional fields
                additionalFields.forEach(function(field) {
                    if (field.getAttribute("data-method") === method || 
                        (method === "bank_transfer" && field.getAttribute("data-method") === "gcash")) {
                        field.classList.remove("hidden");
                    } else {
                        field.classList.add("hidden");
                    }
                });
            });
        });
    }
    
    // Status selection for verification
    const statusOptions = document.querySelectorAll("input[name=status]");
    const notesField = document.getElementById("notes");
    
    if (statusOptions.length > 0 && notesField) {
        statusOptions.forEach(function(option) {
            option.addEventListener("change", function() {
                if (this.value === "rejected") {
                    notesField.setAttribute("required", "required");
                    notesField.closest(".mb-6").querySelector("label").innerHTML = "Notes <span class=\"text-red-500\">*</span>";
                } else {
                    notesField.removeAttribute("required");
                    notesField.closest(".mb-6").querySelector("label").textContent = "Notes";
                }
            });
        });
    }
});
</script>';

// Include footer
require_once 'includes/footer.php';
?>
