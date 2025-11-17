<?php
/**
 * Setup Script - Dastlabki o'rnatish
 * Bu faylni bir marta ishga tushiring
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';

$db = Database::getInstance();

echo "SMS App - Dastlabki O'rnatish\n";
echo "==============================\n\n";

// Check if super admin exists
$superAdmin = $db->query("SELECT * FROM users WHERE role = 'super_admin'")->fetch();

// Define all available permissions
$allPermissions = [
    'view_dashboard',
    'view_groups',
    'create_groups',
    'edit_groups',
    'delete_groups',
    'view_contacts',
    'create_contacts',
    'edit_contacts',
    'delete_contacts',
    'send_sms',
    'view_reports',
    'view_debts',
    'create_debts',
    'edit_debts',
    'delete_debts'
];

if ($superAdmin) {
    echo "Super Admin allaqachon mavjud.\n";
    
    // Check if permissions table exists
    try {
        $permissionsTableExists = $db->query("SHOW TABLES LIKE 'user_permissions'")->fetch();
        
        if ($permissionsTableExists) {
            // Grant all permissions to existing super admin
            $grantedCount = 0;
            foreach ($allPermissions as $permission) {
                $existing = $db->query(
                    "SELECT id FROM user_permissions WHERE user_id = ? AND permission_key = ?",
                    [$superAdmin['id'], $permission]
                )->fetch();
                
                if (!$existing) {
                    $db->query(
                        "INSERT INTO user_permissions (user_id, permission_key, is_allowed) VALUES (?, ?, 1)",
                        [$superAdmin['id'], $permission]
                    );
                    $grantedCount++;
                }
            }
            
            if ($grantedCount > 0) {
                echo "✓ $grantedCount ta yangi huquq qo'shildi!\n";
            } else {
                echo "✓ Barcha huquqlar allaqachon berilgan.\n";
            }
        } else {
            echo "⚠ Permissions jadvali mavjud emas. Avval 'database/add_permissions.sql' ni import qiling.\n";
        }
    } catch (Exception $e) {
        echo "⚠ Xatolik: " . $e->getMessage() . "\n";
        echo "   Permissions jadvali mavjud emas bo'lishi mumkin.\n";
    }
    
    echo "\nParolni o'zgartirish uchun profil sahifasidan foydalaning.\n";
    exit;
}

// Create default super admin
$defaultPassword = 'admin123';
$hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

try {
    $db->getConnection()->beginTransaction();
    
    // Create super admin
    $db->query(
        "INSERT INTO users (password, role, first_name, last_name) VALUES (?, 'super_admin', ?, ?)",
        [$hashedPassword, 'Super', 'Admin']
    );
    
    $superAdminId = $db->getConnection()->lastInsertId();
    
    // Define all available permissions
    $allPermissions = [
        'view_dashboard',
        'view_groups',
        'create_groups',
        'edit_groups',
        'delete_groups',
        'view_contacts',
        'create_contacts',
        'edit_contacts',
        'delete_contacts',
        'send_sms',
        'view_reports'
    ];
    
    // Grant all permissions to super admin
    foreach ($allPermissions as $permission) {
        $db->query(
            "INSERT INTO user_permissions (user_id, permission_key, is_allowed) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE is_allowed = 1",
            [$superAdminId, $permission]
        );
    }
    
    $db->getConnection()->commit();
    
    echo "✓ Super Admin yaratildi!\n";
    echo "✓ Barcha huquqlar berildi!\n";
    echo "  Parol: admin123\n";
    echo "  DIQQAT: Birinchi kirishdan keyin parolni o'zgartiring!\n\n";
    echo "Endi /login.php sahifasiga kiring va parolni kiriting.\n";
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo "Xatolik: " . $e->getMessage() . "\n";
}

