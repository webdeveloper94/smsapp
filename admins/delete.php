<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireSuperAdmin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$adminId = $_GET['id'] ?? 0;

// Get admin
$admin = $db->query(
    "SELECT * FROM users WHERE id = ? AND role = 'admin'",
    [$adminId]
)->fetch();

if (!$admin) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        // Log activity
        $auth->logActivity($userId, 'delete', 'users', $adminId, "Admin deleted: " . $admin['first_name'] . ' ' . $admin['last_name']);
        
        // Deactivate instead of delete to preserve data integrity
        $db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$adminId]);
        
        header('Location: index.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = 'Xatolik yuz berdi: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adminni O'chirish - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Adminni O'chirish</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <p><strong>Diqqat!</strong> Bu adminni nofaol qiladi (o'chirmaydi).</p>
            <p><strong>Ism:</strong> <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></p>

            <?php
            $groupCount = $db->query(
                "SELECT COUNT(*) as count FROM groups WHERE created_by = ?",
                [$adminId]
            )->fetch()['count'];
            ?>
            <p><strong>Yaratgan guruhlar soni:</strong> <?php echo $groupCount; ?></p>

            <form method="POST" style="margin-top: 1.5rem;">
                <button type="submit" name="confirm" value="1" class="btn btn-danger">Ha, nofaol qilish</button>
                <a href="index.php" class="btn btn-secondary">Bekor qilish</a>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>

