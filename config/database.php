<?php
// Database configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'paytrack_db';
    private $username = 'root';
    private $password = '';
    private $conn;
    private $initialized = false;
    
    public function __construct() {
        // Use SQLite for development environment
        $this->initialized = file_exists('paytrack.db');
    }

    // Method to get the database connection
    public function getConnection() {
        $this->conn = null;

        try {
            // Use SQLite for development
            $this->conn = new PDO('sqlite:paytrack.db');
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    // Initialize database with required tables if they don't exist
    public function initializeDatabase() {
        $conn = $this->getConnection();
        
        try {
            // Users table
            $users_table = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id TEXT UNIQUE NOT NULL,
                full_name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'student',
                department TEXT,
                year_level TEXT,
                profile_image TEXT DEFAULT 'default.svg',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            // Organizations table
            $organizations_table = "
            CREATE TABLE IF NOT EXISTS organizations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                acronym TEXT NOT NULL,
                description TEXT,
                logo TEXT DEFAULT 'org_default.svg',
                adviser_id INTEGER,
                president_id INTEGER,
                treasurer_id INTEGER,
                membership_fee REAL DEFAULT 0.00,
                status TEXT DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (adviser_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (president_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (treasurer_id) REFERENCES users(id) ON DELETE SET NULL
            )";
            
            // Organization members table
            $org_members_table = "
            CREATE TABLE IF NOT EXISTS org_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                org_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role TEXT DEFAULT 'member',
                status TEXT DEFAULT 'pending',
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE (org_id, user_id)
            )";
            
            // Payment categories table
            $payment_categories_table = "
            CREATE TABLE IF NOT EXISTS payment_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                org_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                amount REAL NOT NULL,
                due_date DATE NULL,
                is_recurring BOOLEAN DEFAULT 0,
                recurrence TEXT DEFAULT 'one-time',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
            )";
            
            // Payments table
            $payments_table = "
            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                org_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                payment_method TEXT DEFAULT 'cash',
                reference_number TEXT,
                status TEXT DEFAULT 'pending',
                proof_of_payment TEXT,
                notes TEXT,
                payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                verified_by INTEGER,
                verified_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES payment_categories(id) ON DELETE CASCADE,
                FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
            )";
            
            // Notifications table
            $notifications_table = "
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                org_id INTEGER NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                type TEXT DEFAULT 'other',
                is_read INTEGER DEFAULT 0,
                related_id INTEGER NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
            )";
            
            // Execute the queries
            $conn->exec($users_table);
            $conn->exec($organizations_table);
            $conn->exec($org_members_table);
            $conn->exec($payment_categories_table);
            $conn->exec($payments_table);
            $conn->exec($notifications_table);
            
            // Create an admin user if it doesn't exist
            $admin_check = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $admin_check->execute();
            
            if ($admin_check->rowCount() == 0) {
                $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
                $admin_insert = $conn->prepare("
                    INSERT INTO users 
                    (student_id, full_name, email, password, role) 
                    VALUES 
                    ('ADMIN001', 'System Administrator', 'admin@mmsu.edu.ph', :password, 'admin')
                ");
                $admin_insert->bindParam(':password', $password_hash);
                $admin_insert->execute();
            }
            
            // Create a test student account
            $student_check = $conn->prepare("SELECT id FROM users WHERE student_id = 'ST123456' LIMIT 1");
            $student_check->execute();
            
            if ($student_check->rowCount() == 0) {
                $password_hash = password_hash('student123', PASSWORD_DEFAULT);
                $student_insert = $conn->prepare("
                    INSERT INTO users 
                    (student_id, full_name, email, password, role, department, year_level) 
                    VALUES 
                    ('ST123456', 'John Student', 'john.student@mmsu.edu.ph', :password, 'student', 'Computer Science', '3rd Year')
                ");
                $student_insert->bindParam(':password', $password_hash);
                $student_insert->execute();
            }
            
            // Create a test officer account
            $officer_check = $conn->prepare("SELECT id FROM users WHERE student_id = 'OF789012' LIMIT 1");
            $officer_check->execute();
            
            if ($officer_check->rowCount() == 0) {
                $password_hash = password_hash('officer123', PASSWORD_DEFAULT);
                $officer_insert = $conn->prepare("
                    INSERT INTO users 
                    (student_id, full_name, email, password, role, department, year_level) 
                    VALUES 
                    ('OF789012', 'Maria Officer', 'maria.officer@mmsu.edu.ph', :password, 'officer', 'Computer Science', '4th Year')
                ");
                $officer_insert->bindParam(':password', $password_hash);
                $officer_insert->execute();
            }
            
            // Create a test organization
            $org_check = $conn->prepare("SELECT id FROM organizations WHERE acronym = 'ACTS' LIMIT 1");
            $org_check->execute();
            
            if ($org_check->rowCount() == 0) {
                $org_insert = $conn->prepare("
                    INSERT INTO organizations 
                    (name, acronym, description, membership_fee) 
                    VALUES 
                    ('Association of Computer Technology Students', 'ACTS', 'The Association of Computer Technology Students is an organization for Computer Science and IT students at MMSU-CIT.', 250.00)
                ");
                $org_insert->execute();
                
                // Get the officer ID and org ID
                $officer_id_query = $conn->prepare("SELECT id FROM users WHERE student_id = 'OF789012' LIMIT 1");
                $officer_id_query->execute();
                $officer_id = $officer_id_query->fetch(PDO::FETCH_ASSOC)['id'];
                
                $org_id_query = $conn->prepare("SELECT id FROM organizations WHERE acronym = 'ACTS' LIMIT 1");
                $org_id_query->execute();
                $org_id = $org_id_query->fetch(PDO::FETCH_ASSOC)['id'];
                
                // Set the officer as president and treasurer
                $org_update = $conn->prepare("
                    UPDATE organizations 
                    SET president_id = :officer_id, treasurer_id = :officer_id
                    WHERE id = :org_id
                ");
                $org_update->bindParam(':officer_id', $officer_id);
                $org_update->bindParam(':org_id', $org_id);
                $org_update->execute();
                
                // Add the officer as an org member with officer role
                $member_insert = $conn->prepare("
                    INSERT INTO org_members
                    (org_id, user_id, role, status)
                    VALUES
                    (:org_id, :user_id, 'officer', 'active')
                ");
                $member_insert->bindParam(':org_id', $org_id);
                $member_insert->bindParam(':user_id', $officer_id);
                $member_insert->execute();
                
                // Get the student ID
                $student_id_query = $conn->prepare("SELECT id FROM users WHERE student_id = 'ST123456' LIMIT 1");
                $student_id_query->execute();
                $student_id = $student_id_query->fetch(PDO::FETCH_ASSOC)['id'];
                
                // Add the student as an org member
                $member_insert = $conn->prepare("
                    INSERT INTO org_members
                    (org_id, user_id, role, status)
                    VALUES
                    (:org_id, :user_id, 'member', 'active')
                ");
                $member_insert->bindParam(':org_id', $org_id);
                $member_insert->bindParam(':user_id', $student_id);
                $member_insert->execute();
                
                // Create some payment categories
                $categories = [
                    ['Membership Fee', 'Annual membership fee for ACTS members', 250.00, 'annual'],
                    ['Seminar Fee', 'Fee for the upcoming Web Development Seminar', 100.00, 'one-time'],
                    ['T-Shirt Fee', 'Payment for organization T-shirts', 350.00, 'one-time']
                ];
                
                $category_insert = $conn->prepare("
                    INSERT INTO payment_categories
                    (org_id, name, description, amount, is_recurring, recurrence)
                    VALUES
                    (:org_id, :name, :description, :amount, :is_recurring, :recurrence)
                ");
                
                foreach ($categories as $category) {
                    $is_recurring = ($category[3] != 'one-time') ? 1 : 0;
                    $category_insert->bindParam(':org_id', $org_id);
                    $category_insert->bindParam(':name', $category[0]);
                    $category_insert->bindParam(':description', $category[1]);
                    $category_insert->bindParam(':amount', $category[2]);
                    $category_insert->bindParam(':is_recurring', $is_recurring, PDO::PARAM_BOOL);
                    $category_insert->bindParam(':recurrence', $category[3]);
                    $category_insert->execute();
                }
            }
            
            return true;
        } catch (PDOException $e) {
            echo "Database initialization error: " . $e->getMessage();
            return false;
        }
    }
}
?>
