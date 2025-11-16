<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $defaultMessage = trim($_POST['default_message'] ?? '');
    $defaultSendDate = $_POST['default_send_date'] ?? null;

    if (empty($name)) {
        $error = 'Guruh nomini kiriting';
    } else {
        try {
            $db->getConnection()->beginTransaction();
            
            // Create group
            $db->query(
                "INSERT INTO groups (name, description, created_by) VALUES (?, ?, ?)",
                [$name, $description, $userId]
            );
            $groupId = $db->getConnection()->lastInsertId();

            // Create group settings if default message or date is provided
            if (!empty($defaultMessage) || !empty($defaultSendDate)) {
                $db->query(
                    "INSERT INTO group_settings (group_id, default_message, default_send_date) VALUES (?, ?, ?)",
                    [$groupId, $defaultMessage ?: null, $defaultSendDate ?: null]
                );
            }

            $db->getConnection()->commit();
            
            // Log activity
            $auth->logActivity($userId, 'create', 'groups', $groupId, "Group created: $name");
            
            $success = 'Guruh muvaffaqiyatli yaratildi';
            header("Location: view.php?id=$groupId&success=1");
            exit;
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
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
    <title>Yangi Guruh - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Yangi Guruh Yaratish</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="name">Guruh Nomi *</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Tavsif</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="default_message">Standart SMS Matni</label>
                    <textarea id="default_message" name="default_message" placeholder="Bu matn barcha kontaktlar uchun standart bo'ladi"><?php echo htmlspecialchars($_POST['default_message'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="default_send_date">Standart Yuborish Sanasi</label>
                    <input type="date" id="default_send_date" name="default_send_date" value="<?php echo htmlspecialchars($_POST['default_send_date'] ?? ''); ?>">
                    <small>Bu sana barcha kontaktlar uchun standart bo'ladi (har bir kontakt uchun alohida sana ham belgilash mumkin)</small>
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">Yaratish</button>
                    <a href="index.php" class="btn btn-secondary">Bekor qilish</a>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>

