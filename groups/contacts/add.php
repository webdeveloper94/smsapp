<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

$groupId = $_GET['group_id'] ?? 0;

// Get group
$group = $db->query(
    "SELECT * FROM groups WHERE id = ?",
    [$groupId]
)->fetch();

if (!$group) {
    header('Location: ../../groups/index.php');
    exit;
}

// Check permissions
if ($userRole !== 'super_admin' && $group['created_by'] != $userId) {
    header('Location: ../../groups/index.php');
    exit;
}

// Get group settings
$settings = $db->query(
    "SELECT * FROM group_settings WHERE group_id = ?",
    [$groupId]
)->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $sendDate = $_POST['send_date'] ?? null;

    // Use default message if not provided
    if (empty($message) && $settings && !empty($settings['default_message'])) {
        $message = $settings['default_message'];
    }

    // Use default date if not provided
    if (empty($sendDate) && $settings && !empty($settings['default_send_date'])) {
        $sendDate = $settings['default_send_date'];
    }

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
                "INSERT INTO contacts (group_id, phone, name, message, send_date) VALUES (?, ?, ?, ?, ?)",
                [$groupId, $phone, $name ?: null, $message ?: null, $sendDate ?: null]
            );
            
            // Log activity
            $auth->logActivity($userId, 'create', 'contacts', $db->getConnection()->lastInsertId(), "Contact added to group: $groupId");
            
            header("Location: ../../groups/view.php?id=$groupId&success=1");
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
    <title>Kontakt Qo'shish - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <h1>Kontakt Qo'shish</h1>
        <p><strong>Guruh:</strong> <?php echo htmlspecialchars($group['name']); ?></p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="name">Ism (ixtiyoriy)</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Telefon Raqami *</label>
                    <input type="tel" id="phone" name="phone" required placeholder="901234567" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    <small>998 kodini kiriting yoki kiritingmasangiz avtomatik qo'shiladi</small>
                </div>

                <div class="form-group">
                    <label for="message">SMS Matni</label>
                    <textarea id="message" name="message" placeholder="<?php echo $settings && $settings['default_message'] ? 'Standart matn: ' . htmlspecialchars($settings['default_message']) : 'Agar kiritmasangiz, guruhning standart matni ishlatiladi'; ?>"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="send_date">Yuborish Sanasi</label>
                    <input type="date" id="send_date" name="send_date" value="<?php echo htmlspecialchars($_POST['send_date'] ?? ($settings['default_send_date'] ?? '')); ?>">
                    <small><?php echo $settings && $settings['default_send_date'] ? 'Standart sana: ' . date('d.m.Y', strtotime($settings['default_send_date'])) : 'Agar kiritmasangiz, guruhning standart sanasi ishlatiladi'; ?></small>
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">Qo'shish</button>
                    <a href="../../groups/view.php?id=<?php echo $groupId; ?>" class="btn btn-secondary">Bekor qilish</a>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>

