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

// Get settings
$settings = $db->query(
    "SELECT * FROM group_settings WHERE group_id = ?",
    [$groupId]
)->fetch();

$error = '';

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
            
            // Update group
            $db->query(
                "UPDATE groups SET name = ?, description = ? WHERE id = ?",
                [$name, $description, $groupId]
            );

            // Update or create settings
            if ($settings) {
                $db->query(
                    "UPDATE group_settings SET default_message = ?, default_send_date = ? WHERE group_id = ?",
                    [$defaultMessage ?: null, $defaultSendDate ?: null, $groupId]
                );
            } else if (!empty($defaultMessage) || !empty($defaultSendDate)) {
                $db->query(
                    "INSERT INTO group_settings (group_id, default_message, default_send_date) VALUES (?, ?, ?)",
                    [$groupId, $defaultMessage ?: null, $defaultSendDate ?: null]
                );
            }

            $db->getConnection()->commit();
            
            // Log activity
            $auth->logActivity($userId, 'update', 'groups', $groupId, "Group updated: $name");
            
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
    <title>Guruhni Tahrirlash - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Guruhni Tahrirlash</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="name">Guruh Nomi *</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($group['name']); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Tavsif</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($group['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="default_message">Standart SMS Matni</label>
                    <textarea id="default_message" name="default_message"><?php echo htmlspecialchars($settings['default_message'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="default_send_date">Standart Yuborish Sanasi</label>
                    <input type="date" id="default_send_date" name="default_send_date" value="<?php echo $settings['default_send_date'] ?? ''; ?>">
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">Saqlash</button>
                    <a href="view.php?id=<?php echo $groupId; ?>" class="btn btn-secondary">Bekor qilish</a>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>

