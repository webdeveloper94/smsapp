<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

$contactId = $_GET['id'] ?? 0;

// Get contact
$contact = $db->query(
    "SELECT c.*, g.created_by 
     FROM contacts c 
     JOIN groups g ON c.group_id = g.id 
     WHERE c.id = ?",
    [$contactId]
)->fetch();

if (!$contact) {
    header('Location: ../../groups/index.php');
    exit;
}

// Check permissions
if ($userRole !== 'super_admin' && $contact['created_by'] != $userId) {
    header('Location: ../../groups/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        // Log activity
        $auth->logActivity($userId, 'delete', 'contacts', $contactId, "Contact deleted");
        
        $db->query("DELETE FROM contacts WHERE id = ?", [$contactId]);
        
        header("Location: ../../groups/view.php?id={$contact['group_id']}&success=1");
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
    <title>Kontaktni O'chirish - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h1>Kontaktni O'chirish</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <p><strong>Diqqat!</strong> Bu kontaktni o'chirmoqchimisiz?</p>
            <p><strong>Ism:</strong> <?php echo htmlspecialchars($contact['name'] ?? '-'); ?></p>
            <p><strong>Telefon:</strong> <?php echo htmlspecialchars($contact['phone']); ?></p>

            <form method="POST" style="margin-top: 1.5rem;">
                <button type="submit" name="confirm" value="1" class="btn btn-danger">Ha, o'chirish</button>
                <a href="../../groups/view.php?id=<?php echo $contact['group_id']; ?>" class="btn btn-secondary">Bekor qilish</a>
            </form>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>

