<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

$groupId = $_GET['id'] ?? 0;

// Get group
$group = $db->query(
    "SELECT * FROM groups WHERE id = ?",
    [$groupId]
)->fetch();

if (!$group) {
    header('Location: index.php');
    exit;
}

// Check permissions
if ($userRole !== 'super_admin' && $group['created_by'] != $userId) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        // Log activity before delete
        $auth->logActivity($userId, 'delete', 'groups', $groupId, "Group deleted: " . $group['name']);
        
        // Delete group (cascade will delete contacts and settings)
        $db->query("DELETE FROM groups WHERE id = ?", [$groupId]);
        
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
    <title>Guruhni O'chirish - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Guruhni O'chirish</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <p><strong>Diqqat!</strong> Bu guruhni o'chirish barcha kontaktlar va sozlamalarni ham o'chiradi.</p>
            <p><strong>Guruh:</strong> <?php echo htmlspecialchars($group['name']); ?></p>
            
            <?php
            $contactCount = $db->query(
                "SELECT COUNT(*) as count FROM contacts WHERE group_id = ?",
                [$groupId]
            )->fetch()['count'];
            ?>
            <p><strong>Kontaktlar soni:</strong> <?php echo $contactCount; ?></p>

            <form method="POST" style="margin-top: 1.5rem;">
                <button type="submit" name="confirm" value="1" class="btn btn-danger">Ha, o'chirish</button>
                <a href="view.php?id=<?php echo $groupId; ?>" class="btn btn-secondary">Bekor qilish</a>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>

