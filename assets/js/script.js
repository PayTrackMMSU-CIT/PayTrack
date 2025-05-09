// PayTrack Main JavaScript File

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Toggle mobile menu
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
    
    // Toggle user menu dropdown
    const userMenuBtn = document.getElementById('user-menu-btn');
    const userMenuDropdown = document.getElementById('user-menu-dropdown');
    
    if (userMenuBtn && userMenuDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('hidden');
            
            // Hide notification dropdown if open
            const notificationDropdown = document.getElementById('notification-dropdown');
            if (notificationDropdown && !notificationDropdown.classList.contains('hidden')) {
                notificationDropdown.classList.add('hidden');
            }
        });
    }
    
    // Toggle notification dropdown
    const notificationBtn = document.getElementById('notification-btn');
    const notificationDropdown = document.getElementById('notification-dropdown');
    
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
            
            // Hide user menu dropdown if open
            if (userMenuDropdown && !userMenuDropdown.classList.contains('hidden')) {
                userMenuDropdown.classList.add('hidden');
            }
        });
    }
    
    // Mobile notifications panel
    const mobileNotificationBtn = document.getElementById('mobile-notification-btn');
    const mobileNotificationPanel = document.getElementById('mobile-notification-panel');
    
    if (mobileNotificationBtn && mobileNotificationPanel) {
        mobileNotificationBtn.addEventListener('click', function(e) {
            e.preventDefault();
            mobileNotificationPanel.classList.toggle('hidden');
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (userMenuDropdown && !userMenuDropdown.classList.contains('hidden')) {
            if (!userMenuBtn.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.classList.add('hidden');
            }
        }
        
        if (notificationDropdown && !notificationDropdown.classList.contains('hidden')) {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
        }
    });
    
    // Flash message auto-close
    const closeAlertBtns = document.querySelectorAll('.close-alert');
    closeAlertBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            if (btn.parentElement) {
                btn.parentElement.style.display = 'none';
            }
        }, 5000);
    });
    
    // Payment method selection
    const paymentMethodOptions = document.querySelectorAll('.payment-method-option');
    const paymentMethodInput = document.getElementById('payment_method');
    
    if (paymentMethodOptions.length > 0 && paymentMethodInput) {
        paymentMethodOptions.forEach(function(option) {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                paymentMethodOptions.forEach(function(opt) {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Update hidden input value
                const methodValue = this.getAttribute('data-method');
                paymentMethodInput.value = methodValue;
                
                // Show/hide additional fields based on payment method
                const additionalFields = document.querySelectorAll('.payment-additional-fields');
                additionalFields.forEach(function(field) {
                    if (field.getAttribute('data-method') === methodValue) {
                        field.classList.remove('hidden');
                    } else {
                        field.classList.add('hidden');
                    }
                });
            });
        });
    }
    
    // File input preview for payment proof
    const proofInput = document.getElementById('proof_of_payment');
    const proofPreview = document.getElementById('proof_preview');
    
    if (proofInput && proofPreview) {
        proofInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    proofPreview.innerHTML = `
                        <div class="relative mt-2">
                            <img src="${e.target.result}" class="max-w-full h-auto rounded-md border" style="max-height: 200px;">
                            <button type="button" id="remove_proof" class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    
                    // Add event listener for remove button
                    document.getElementById('remove_proof').addEventListener('click', function() {
                        proofInput.value = '';
                        proofPreview.innerHTML = '';
                    });
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Toggle password visibility
    const togglePasswordBtns = document.querySelectorAll('.toggle-password');
    togglePasswordBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const target = document.getElementById(this.getAttribute('data-target'));
            if (target) {
                if (target.type === 'password') {
                    target.type = 'text';
                    this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    target.type = 'password';
                    this.innerHTML = '<i class="fas fa-eye"></i>';
                }
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check required fields
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    isValid = false;
                    // Add error class
                    field.classList.add('border-red-500');
                    // Show error message if it doesn't exist
                    let errorMsg = field.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('form-error')) {
                        errorMsg = document.createElement('div');
                        errorMsg.classList.add('form-error');
                        errorMsg.textContent = 'This field is required';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    }
                } else {
                    // Remove error class
                    field.classList.remove('border-red-500');
                    // Remove error message if it exists
                    let errorMsg = field.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('form-error')) {
                        errorMsg.remove();
                    }
                }
            });
            
            // Validate email fields
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(function(field) {
                if (field.value.trim() && !isValidEmail(field.value.trim())) {
                    isValid = false;
                    // Add error class
                    field.classList.add('border-red-500');
                    // Show error message if it doesn't exist
                    let errorMsg = field.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('form-error')) {
                        errorMsg = document.createElement('div');
                        errorMsg.classList.add('form-error');
                        errorMsg.textContent = 'Please enter a valid email address';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    } else {
                        errorMsg.textContent = 'Please enter a valid email address';
                    }
                }
            });
            
            // Validate password match if confirm password exists
            const passwordField = form.querySelector('input[name="password"]');
            const confirmPasswordField = form.querySelector('input[name="confirm_password"]');
            if (passwordField && confirmPasswordField) {
                if (passwordField.value !== confirmPasswordField.value) {
                    isValid = false;
                    // Add error class
                    confirmPasswordField.classList.add('border-red-500');
                    // Show error message if it doesn't exist
                    let errorMsg = confirmPasswordField.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('form-error')) {
                        errorMsg = document.createElement('div');
                        errorMsg.classList.add('form-error');
                        errorMsg.textContent = 'Passwords do not match';
                        confirmPasswordField.parentNode.insertBefore(errorMsg, confirmPasswordField.nextSibling);
                    } else {
                        errorMsg.textContent = 'Passwords do not match';
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Remove error styling on input
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(function(input) {
            input.addEventListener('input', function() {
                this.classList.remove('border-red-500');
                let errorMsg = this.nextElementSibling;
                if (errorMsg && errorMsg.classList.contains('form-error')) {
                    errorMsg.remove();
                }
            });
        });
    });
    
    // Search functionality
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');
    
    if (searchInput && searchResults) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length >= 2) {
                // Show search results container
                searchResults.classList.remove('hidden');
                
                // Perform search (this would typically be an AJAX call)
                // For demo purposes, we'll just show a loading indicator
                searchResults.innerHTML = '<div class="p-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
                
                // Simulated search results
                setTimeout(function() {
                    // Replace with actual AJAX call in production
                    searchResults.innerHTML = `
                        <div class="p-2 border-b">
                            <h3 class="font-medium text-gray-700">Search Results</h3>
                        </div>
                        <div class="p-2">
                            <a href="#" class="block p-2 hover:bg-gray-100 rounded">
                                <div class="font-medium">${query} - Sample Result 1</div>
                                <div class="text-sm text-gray-500">Organization: ACTS</div>
                            </a>
                            <a href="#" class="block p-2 hover:bg-gray-100 rounded">
                                <div class="font-medium">${query} - Sample Result 2</div>
                                <div class="text-sm text-gray-500">Organization: AITS</div>
                            </a>
                        </div>
                    `;
                }, 500);
            } else {
                // Hide search results if query is too short
                searchResults.classList.add('hidden');
            }
        });
        
        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
    }
});

// Helper functions
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Format currency
function formatCurrency(amount, currency = '₱') {
    return currency + ' ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Format date
function formatDate(dateString, format = 'full') {
    const date = new Date(dateString);
    
    if (format === 'full') {
        const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString('en-US', options);
    } else if (format === 'short') {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }
    
    return date.toLocaleDateString();
}

// Confirm action (like delete)
function confirmAction(message = 'Are you sure you want to proceed?') {
    return confirm(message);
}

// Load content via AJAX
function loadContent(url, targetElementId, callback = null) {
    const targetElement = document.getElementById(targetElementId);
    if (!targetElement) return;
    
    // Show loading indicator
    targetElement.innerHTML = '<div class="flex justify-center p-4"><i class="fas fa-spinner fa-spin text-2xl text-blue-800"></i></div>';
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            targetElement.innerHTML = html;
            if (callback && typeof callback === 'function') {
                callback();
            }
        })
        .catch(error => {
            targetElement.innerHTML = `<div class="text-center p-4 text-red-500">
                <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                <p>Error loading content: ${error.message}</p>
            </div>`;
        });
}

// Update payment status via AJAX
function updatePaymentStatus(paymentId, newStatus, token, callback = null) {
    fetch('api/update_payment_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `payment_id=${paymentId}&status=${newStatus}&token=${token}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (callback && typeof callback === 'function') {
                callback(true, data.message);
            }
        } else {
            if (callback && typeof callback === 'function') {
                callback(false, data.message);
            }
        }
    })
    .catch(error => {
        if (callback && typeof callback === 'function') {
            callback(false, 'An error occurred while updating the payment status.');
        }
    });
}
