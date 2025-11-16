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
}

