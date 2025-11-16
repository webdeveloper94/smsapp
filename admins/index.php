<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireSuperAdmin();

$db = Database::getInstance();
$success = $_GET['success'] ?? '';

// Get all admins
$admins = $db->query(
    "SELECT u.*, creator.first_name as creator_first_name, creator.last_name as creator_last_name
     FROM users u
     LEFT JOIN users creator ON u.created_by = creator.id
     WHERE u.role = 'admin'
     ORDER BY u.created_at DESC"
)->fetchAll();
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
            <a href="create.php" class="btn btn-primary">Yangi Admin</a>
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
                            <td colspan="8" class="text-center">Adminlar mavjud emas</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo $admin['id']; ?></td>
                                <td><?php echo htmlspecialchars($admin['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($admin['last_name']); ?></td>
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

