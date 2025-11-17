<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requirePermission('delete_debts');

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

$debtorId = $_GET['id'] ?? 0;

// Get debtor
$debtor = $db->query(
    "SELECT * FROM debtors WHERE id = ?",
    [$debtorId]
)->fetch();

if (!$debtor) {
    header('Location: index.php');
    exit;
}

// Check permissions
if ($userRole !== 'super_admin' && $debtor['created_by'] != $userId) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        // Log activity before delete
        $auth->logActivity($userId, 'delete', 'debtors', $debtorId, "Debtor deleted: " . $debtor['name']);
        
        // Soft delete - mark as deleted
        $db->query("UPDATE debtors SET status = 'deleted' WHERE id = ?", [$debtorId]);
        
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
    <title>Qarzdorni O'chirish - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Qarzdorni O'chirish</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <p><strong>Diqqat!</strong> Bu qarzdorni o'chirmoqchimisiz?</p>
            <p><strong>Ism:</strong> <?php echo htmlspecialchars($debtor['name']); ?></p>
            <p><strong>Telefon:</strong> <?php echo htmlspecialchars($debtor['phone']); ?></p>
            <p><strong>Qarz Summasi:</strong> <?php echo number_format($debtor['debt_amount'], 0, ',', ' '); ?> so'm</p>

            <form method="POST" style="margin-top: 1.5rem;">
                <button type="submit" name="confirm" value="1" class="btn btn-danger">Ha, o'chirish</button>
                <a href="index.php" class="btn btn-secondary">Bekor qilish</a>
            </form>
        </div>
    </div>

    <script src="<?php echo base_url('/assets/js/main.js'); ?>"></script>
</body>
</html>

