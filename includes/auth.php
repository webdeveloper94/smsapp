<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login($password) {
        $stmt = $this->db->query(
            "SELECT id, password, role, first_name, last_name, is_active FROM users WHERE is_active = 1"
        );
        $users = $stmt->fetchAll();

        foreach ($users as $user) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Update last login
                $this->db->query(
                    "UPDATE users SET last_login = NOW() WHERE id = ?",
                    [$user['id']]
                );

                // Log activity
                $this->logActivity($user['id'], 'login', 'users', $user['id'], 'User logged in');
                
                return $user;
            }
        }
        
        return false;
    }

    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'User logged out');
        }
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isSuperAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';
    }

    public function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    public function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            redirect('/login.php');
        }
    }

    public function requireSuperAdmin() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            redirect('/index.php');
        }
    }

    public function logActivity($userId, $action, $tableName, $recordId = null, $details = null) {
        $this->db->query(
            "INSERT INTO activity_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)",
            [$userId, $action, $tableName, $recordId, $details]
        );
    }

    /**
     * Check if user has permission
     * Super admin always has all permissions
     * Admin needs explicit permission
     */
    public function hasPermission($permissionKey) {
        // Super admin always has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        $userId = $this->getUserId();
        if (!$userId) {
            return false;
        }

        // Check if permission exists and is allowed
        $permission = $this->db->query(
            "SELECT is_allowed FROM user_permissions WHERE user_id = ? AND permission_key = ?",
            [$userId, $permissionKey]
        )->fetch();

        // If permission is explicitly set, return its value
        if ($permission !== false) {
            return (bool)$permission['is_allowed'];
        }

        // If permission is not set, default to false (no permission)
        return false;
    }

    /**
     * Require permission or redirect
     */
    public function requirePermission($permissionKey, $redirectUrl = '/index.php') {
        $this->requireLogin();
        if (!$this->hasPermission($permissionKey)) {
            redirect($redirectUrl);
        }
    }

    /**
     * Get all permissions for a user
     */
    public function getUserPermissions($userId) {
        $permissions = $this->db->query(
            "SELECT permission_key, is_allowed FROM user_permissions WHERE user_id = ?",
            [$userId]
        )->fetchAll();

        $result = [];
        foreach ($permissions as $perm) {
            $result[$perm['permission_key']] = (bool)$perm['is_allowed'];
        }
        return $result;
    }

    /**
     * Set permission for a user
     */
    public function setPermission($userId, $permissionKey, $isAllowed = true) {
        $this->db->query(
            "INSERT INTO user_permissions (user_id, permission_key, is_allowed) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE is_allowed = ?",
            [$userId, $permissionKey, $isAllowed ? 1 : 0, $isAllowed ? 1 : 0]
        );
    }

    /**
     * Remove permission for a user
     */
    public function removePermission($userId, $permissionKey) {
        $this->db->query(
            "DELETE FROM user_permissions WHERE user_id = ? AND permission_key = ?",
            [$userId, $permissionKey]
        );
    }

    /**
     * Check if user has SMS limit available
     * Super admin always has unlimited (returns true)
     * Admin needs to check limit
     */
    public function checkSMSLimit() {
        // Super admin always has unlimited
        if ($this->isSuperAdmin()) {
            return ['allowed' => true, 'remaining' => -1, 'limit' => -1];
        }

        $userId = $this->getUserId();
        if (!$userId) {
            return ['allowed' => false, 'remaining' => 0, 'limit' => 0];
        }

        // Get user SMS limit
        $user = $this->db->query(
            "SELECT sms_limit FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        if (!$user) {
            return ['allowed' => false, 'remaining' => 0, 'limit' => 0];
        }

        $limit = $user['sms_limit'];
        
        // NULL or -1 means unlimited
        if ($limit === null || $limit == -1) {
            return ['allowed' => true, 'remaining' => -1, 'limit' => -1];
        }

        // Get current month SMS count
        $currentMonth = date('Y-m');
        
        // Check if table exists
        try {
            $smsCount = $this->db->query(
                "SELECT sent_count FROM user_sms_count WHERE user_id = ? AND `year_month` = ?",
                [$userId, $currentMonth]
            )->fetch();
        } catch (PDOException $e) {
            // Table doesn't exist yet
            $smsCount = false;
        }

        $sentCount = $smsCount ? (int)$smsCount['sent_count'] : 0;
        $remaining = max(0, $limit - $sentCount);

        return [
            'allowed' => $sentCount < $limit,
            'remaining' => $remaining,
            'limit' => $limit,
            'sent' => $sentCount
        ];
    }

    /**
     * Increment SMS count for user
     * @param int|null $userId - User ID. If null, uses current logged in user
     */
    public function incrementSMSCount($userId = null) {
        // If userId not provided, use current user
        if ($userId === null) {
            $userId = $this->getUserId();
        }
        
        if (!$userId) {
            return;
        }

        // Check if user is super_admin (super admin doesn't need counting)
        $user = $this->db->query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
        if ($user && $user['role'] === 'super_admin') {
            return;
        }

        $currentMonth = date('Y-m');
        
        // Log for debugging
        error_log("incrementSMSCount called for user_id: $userId, month: $currentMonth");
        
        // Check if table exists before inserting
        try {
            $result = $this->db->query(
                "INSERT INTO user_sms_count (user_id, `year_month`, sent_count) 
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE sent_count = sent_count + 1",
                [$userId, $currentMonth]
            );
            error_log("SMS count incremented successfully for user_id: $userId");
        } catch (PDOException $e) {
            // Table doesn't exist yet, try to create it
            error_log("user_sms_count table doesn't exist, attempting to create: " . $e->getMessage());
            try {
                $this->db->query(
                    "CREATE TABLE IF NOT EXISTS user_sms_count (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        `year_month` VARCHAR(7) NOT NULL,
                        sent_count INT DEFAULT 0,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_user_month (user_id, `year_month`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
                error_log("user_sms_count table created successfully");
                // Retry insert after creating table
                $this->db->query(
                    "INSERT INTO user_sms_count (user_id, `year_month`, sent_count) 
                     VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE sent_count = sent_count + 1",
                    [$userId, $currentMonth]
                );
                error_log("SMS count incremented successfully after table creation for user_id: $userId");
            } catch (PDOException $e2) {
                error_log("Failed to create user_sms_count table: " . $e2->getMessage());
                error_log("Error details: " . print_r($e2->getTraceAsString(), true));
            }
        }
    }

    /**
     * Get SMS limit info for user
     */
    public function getSMSLimitInfo() {
        return $this->checkSMSLimit();
    }
}

