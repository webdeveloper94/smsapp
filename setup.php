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

if ($superAdmin) {
    echo "Super Admin allaqachon mavjud.\n";
    echo "Parolni o'zgartirish uchun profil sahifasidan foydalaning.\n";
    exit;
}

// Create default super admin
$defaultPassword = 'admin123';
$hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

try {
    $db->query(
        "INSERT INTO users (password, role, first_name, last_name) VALUES (?, 'super_admin', ?, ?)",
        [$hashedPassword, 'Super', 'Admin']
    );
    
    echo "✓ Super Admin yaratildi!\n";
    echo "  Parol: admin123\n";
    echo "  DIQQAT: Birinchi kirishdan keyin parolni o'zgartiring!\n\n";
    echo "Endi /login.php sahifasiga kiring va parolni kiriting.\n";
} catch (Exception $e) {
    echo "Xatolik: " . $e->getMessage() . "\n";
}

