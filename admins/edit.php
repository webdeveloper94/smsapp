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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $smsLimit = isset($_POST['sms_limit']) && $_POST['sms_limit'] !== '' ? (int)$_POST['sms_limit'] : null;

    if (empty($firstName) || empty($lastName)) {
        $error = 'Ism va familiyani kiriting';
    } elseif ($smsLimit !== null && $smsLimit < 0) {
        $error = 'SMS limiti manfiy bo\'lishi mumkin emas';
    } else {
        try {
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error = 'Parol kamida 6 belgidan iborat bo\'lishi kerak';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $db->query(
                        "UPDATE users SET first_name = ?, last_name = ?, password = ?, is_active = ?, sms_limit = ? WHERE id = ?",
                        [$firstName, $lastName, $hashedPassword, $isActive, $smsLimit, $adminId]
                    );
                }
            } else {
                $db->query(
                    "UPDATE users SET first_name = ?, last_name = ?, is_active = ?, sms_limit = ? WHERE id = ?",
                    [$firstName, $lastName, $isActive, $smsLimit, $adminId]
                );
            }
            
            if (empty($error)) {
                // Log activity
                $auth->logActivity($userId, 'update', 'users', $adminId, "Admin updated: $firstName $lastName");
                
                header('Location: index.php?success=1');
                exit;
            }
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
    <title>Adminni Tahrirlash - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Adminni Tahrirlash</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="first_name">Ism *</label>
                    <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($admin['first_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="last_name">Familiya *</label>
                    <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($admin['last_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Yangi Parol (ixtiyoriy)</label>
                    <input type="password" id="password" name="password" minlength="6">
                    <small>Agar parolni o'zgartirmoqchi bo'lmasangiz, bo'sh qoldiring</small>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php echo $admin['is_active'] ? 'checked' : ''; ?>>
                        Faol
                    </label>
                </div>

                <div class="form-group">
                    <label for="sms_limit">SMS Limit (oyiga)</label>
                    <input type="number" id="sms_limit" name="sms_limit" min="0" value="<?php echo $admin['sms_limit'] !== null ? htmlspecialchars($admin['sms_limit']) : ''; ?>" placeholder="Cheksiz uchun bo'sh qoldiring">
                    <small>Admin oyiga nechta SMS yubora olishi. Bo'sh qoldirilsa cheksiz bo'ladi.</small>
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">Saqlash</button>
                    <a href="index.php" class="btn btn-secondary">Bekor qilish</a>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>

