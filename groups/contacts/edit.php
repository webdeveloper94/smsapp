<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';

$auth = new Auth();
$auth->requirePermission('edit_contacts');

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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $sendDate = $_POST['send_date'] ?? null;

    // Validate phone
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 9) {
        $phone = '998' . $phone;
    }

    if (empty($phone) || strlen($phone) < 9) {
        $error = 'Telefon raqamini to\'g\'ri kiriting';
    } else {
        try {
            $db->query(
                "UPDATE contacts SET phone = ?, name = ?, message = ?, send_date = ? WHERE id = ?",
                [$phone, $name ?: null, $message ?: null, $sendDate ?: null, $contactId]
            );
            
            // Log activity
            $auth->logActivity($userId, 'update', 'contacts', $contactId, "Contact updated");
            
            header("Location: ../../groups/view.php?id={$contact['group_id']}&success=1");
            exit;
        } catch (Exception $e) {
            $error = 'Xatolik yuz berdi: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontaktni Tahrirlash - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h1>Kontaktni Tahrirlash</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="name">Ism (ixtiyoriy)</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($contact['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Telefon Raqami *</label>
                    <input type="tel" id="phone" name="phone" required value="<?php echo htmlspecialchars(substr($contact['phone'], 3)); ?>">
                </div>

                <div class="form-group">
                    <label for="message">SMS Matni</label>
                    <textarea id="message" name="message"><?php echo htmlspecialchars($contact['message'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="send_date">Yuborish Sanasi</label>
                    <input type="date" id="send_date" name="send_date" value="<?php echo $contact['send_date'] ? date('Y-m-d', strtotime($contact['send_date'])) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Holat</label>
                    <p><?php 
                        echo $contact['status'] === 'pending' ? 'Kutilmoqda' : 
                            ($contact['status'] === 'sent' ? 'Yuborilgan' : 'Xatolik');
                        if ($contact['sent_at']) {
                            echo ' - ' . date('d.m.Y H:i', strtotime($contact['sent_at']));
                        }
                    ?></p>
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">Saqlash</button>
                    <a href="../../groups/view.php?id=<?php echo $contact['group_id']; ?>" class="btn btn-secondary">Bekor qilish</a>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>

