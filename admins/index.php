<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireSuperAdmin();

$db = Database::getInstance();
$success = $_GET['success'] ?? '';

// Get all admins with SMS count
$currentMonth = date('Y-m');

// Check if user_sms_count table exists
$tableExists = false;
try {
    $db->query("SELECT 1 FROM user_sms_count LIMIT 1");
    $tableExists = true;
} catch (PDOException $e) {
    // Table doesn't exist, will use simple query
    $tableExists = false;
}

if ($tableExists) {
    $admins = $db->query(
        "SELECT u.*, creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                COALESCE(usc.sent_count, 0) as sent_sms_count
         FROM users u
         LEFT JOIN users creator ON u.created_by = creator.id
         LEFT JOIN user_sms_count usc ON u.id = usc.user_id AND usc.`year_month` = ?
         WHERE u.role = 'admin'
         ORDER BY u.created_at DESC",
        [$currentMonth]
    )->fetchAll();
} else {
    // Table doesn't exist yet, use simple query
    $admins = $db->query(
        "SELECT u.*, creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                0 as sent_sms_count
         FROM users u
         LEFT JOIN users creator ON u.created_by = creator.id
         WHERE u.role = 'admin'
         ORDER BY u.created_at DESC"
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adminlar - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="actions-bar">
            <h1>Adminlar</h1>
            <div>
                <a href="permissions.php" class="btn btn-info" style="margin-right: 0.5rem;">Huquqlar</a>
                <a href="create.php" class="btn btn-primary">Yangi Admin</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">Amal muvaffaqiyatli bajarildi!</div>
        <?php endif; ?>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ism</th>
                        <th>Familiya</th>
                        <th>SMS Limit</th>
                        <th>Yuborilgan (oy)</th>
                        <th>Yaratuvchi</th>
                        <th>Oxirgi Kirish</th>
                        <th>Yaratilgan</th>
                        <th>Holat</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($admins)): ?>
                        <tr>
                            <td colspan="10" class="text-center">Adminlar mavjud emas</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <?php
                            $limitText = $admin['sms_limit'] === null || $admin['sms_limit'] == -1 
                                ? '<span class="text-success">Cheksiz</span>' 
                                : number_format($admin['sms_limit'], 0, ',', ' ');
                            $sentCount = (int)$admin['sent_sms_count'];
                            $remaining = $admin['sms_limit'] !== null && $admin['sms_limit'] != -1 
                                ? max(0, $admin['sms_limit'] - $sentCount) 
                                : -1;
                            $limitClass = '';
                            if ($admin['sms_limit'] !== null && $admin['sms_limit'] != -1) {
                                $percent = ($sentCount / $admin['sms_limit']) * 100;
                                if ($percent >= 100) {
                                    $limitClass = 'text-danger';
                                } elseif ($percent >= 80) {
                                    $limitClass = 'text-warning';
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo $admin['id']; ?></td>
                                <td><?php echo htmlspecialchars($admin['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($admin['last_name']); ?></td>
                                <td>
                                    <?php echo $limitText; ?>
                                    <?php if ($admin['sms_limit'] !== null && $admin['sms_limit'] != -1): ?>
                                        <br><small class="<?php echo $limitClass; ?>">
                                            Qolgan: <?php echo number_format($remaining, 0, ',', ' '); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $limitClass; ?>">
                                    <?php echo number_format($sentCount, 0, ',', ' '); ?>
                                </td>
                                <td><?php echo htmlspecialchars(($admin['creator_first_name'] ?? '') . ' ' . ($admin['creator_last_name'] ?? '')); ?></td>
                                <td><?php echo $admin['last_login'] ? date('d.m.Y H:i', strtotime($admin['last_login'])) : 'Hech qachon'; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($admin['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $admin['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $admin['is_active'] ? 'Faol' : 'Nofaol'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-warning">Tahrirlash</a>
                                    <a href="delete.php?id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-danger">O'chirish</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>

