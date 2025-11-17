<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireSuperAdmin();

$db = Database::getInstance();
$userId = $auth->getUserId();

$error = '';
$success = '';

// Define all available permissions
$allPermissions = [
    'view_dashboard' => 'Dashboard ko\'rish',
    'view_groups' => 'Guruhlarni ko\'rish',
    'create_groups' => 'Guruh yaratish',
    'edit_groups' => 'Guruh tahrirlash',
    'delete_groups' => 'Guruh o\'chirish',
    'view_contacts' => 'Kontaktlarni ko\'rish',
    'create_contacts' => 'Kontakt yaratish',
    'edit_contacts' => 'Kontakt tahrirlash',
    'delete_contacts' => 'Kontakt o\'chirish',
    'send_sms' => 'SMS yuborish',
    'view_reports' => 'Hisobotlarni ko\'rish',
    'view_debts' => 'Qarzdorlarni ko\'rish',
    'create_debts' => 'Qarzdor yaratish',
    'edit_debts' => 'Qarzdor tahrirlash',
    'delete_debts' => 'Qarzdor o\'chirish'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = intval($_POST['user_id'] ?? 0);
    
    if ($targetUserId <= 0) {
        $error = 'Noto\'g\'ri foydalanuvchi ID';
    } else {
        // Check if user exists and is admin
        $targetUser = $db->query(
            "SELECT id, role, first_name, last_name FROM users WHERE id = ? AND role = 'admin'",
            [$targetUserId]
        )->fetch();
        
        if (!$targetUser) {
            $error = 'Admin topilmadi';
        } else {
            // Process permissions
            $updatedCount = 0;
            foreach ($allPermissions as $key => $label) {
                $isAllowed = isset($_POST['permissions'][$key]) ? 1 : 0;
                $auth->setPermission($targetUserId, $key, $isAllowed);
                $updatedCount++;
            }
            
            // Log activity
            $auth->logActivity(
                $userId, 
                'update_permissions', 
                'user_permissions', 
                $targetUserId, 
                "Updated permissions for {$targetUser['first_name']} {$targetUser['last_name']}"
            );
            
            $success = "{$targetUser['first_name']} {$targetUser['last_name']} uchun huquqlar muvaffaqiyatli yangilandi!";
        }
    }
}

// Get selected user ID
$selectedUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Get all admins
$admins = $db->query(
    "SELECT id, first_name, last_name, is_active 
     FROM users 
     WHERE role = 'admin' 
     ORDER BY first_name, last_name"
)->fetchAll();

// Get permissions for selected user
$userPermissions = [];
if ($selectedUserId > 0) {
    $userPermissions = $auth->getUserPermissions($selectedUserId);
}

// Get selected user info
$selectedUser = null;
if ($selectedUserId > 0) {
    $selectedUser = $db->query(
        "SELECT id, first_name, last_name FROM users WHERE id = ?",
        [$selectedUserId]
    )->fetch();
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Huquqlari - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="actions-bar">
            <h1>Admin Huquqlari</h1>
            <a href="index.php" class="btn btn-secondary">Orqaga</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Admin Tanlash</div>
            <form method="GET" action="" style="margin-bottom: 2rem;">
                <div class="form-group">
                    <label for="user_id">Admin</label>
                    <select id="user_id" name="user_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Adminni tanlang...</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>" <?php echo $selectedUserId == $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                                <?php if (!$admin['is_active']): ?>
                                    (Nofaol)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($selectedUser): ?>
                <form method="POST" action="">
                    <input type="hidden" name="user_id" value="<?php echo $selectedUserId; ?>">
                    
                    <div class="card-header" style="margin-top: 1.5rem;">
                        <h3><?php echo htmlspecialchars($selectedUser['first_name'] . ' ' . $selectedUser['last_name']); ?> uchun huquqlar</h3>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1.5rem;">
                        <?php foreach ($allPermissions as $key => $label): ?>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="display: flex; align-items: center; cursor: pointer; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; transition: all 0.3s;">
                                    <input 
                                        type="checkbox" 
                                        name="permissions[<?php echo $key; ?>]" 
                                        value="1" 
                                        <?php echo isset($userPermissions[$key]) && $userPermissions[$key] ? 'checked' : ''; ?>
                                        style="margin-right: 0.75rem; width: 18px; height: 18px; cursor: pointer;"
                                    >
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Huquqlarni Saqlash
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info" style="margin-top: 1.5rem;">
                    Huquqlarni sozlash uchun adminni tanlang
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo base_url('/assets/js/main.js'); ?>"></script>
</body>
</html>

