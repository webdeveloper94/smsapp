<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$error = '';
$success = '';

// Get user
$user = $db->query(
    "SELECT * FROM users WHERE id = ?",
    [$userId]
)->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';

    if (empty($firstName) || empty($lastName)) {
        $error = 'Ism va familiyani kiriting';
    } else {
        try {
            // If password change is requested
            if (!empty($password)) {
                if (empty($currentPassword)) {
                    $error = 'Joriy parolni kiriting';
                } elseif (!password_verify($currentPassword, $user['password'])) {
                    $error = 'Joriy parol noto\'g\'ri';
                } elseif (strlen($password) < 6) {
                    $error = 'Yangi parol kamida 6 belgidan iborat bo\'lishi kerak';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $db->query(
                        "UPDATE users SET first_name = ?, last_name = ?, password = ? WHERE id = ?",
                        [$firstName, $lastName, $hashedPassword, $userId]
                    );
                    $success = 'Profil muvaffaqiyatli yangilandi';
                    $user = $db->query("SELECT * FROM users WHERE id = ?", [$userId])->fetch();
                }
            } else {
                $db->query(
                    "UPDATE users SET first_name = ?, last_name = ? WHERE id = ?",
                    [$firstName, $lastName, $userId]
                );
                $success = 'Profil muvaffaqiyatli yangilandi';
                $user = $db->query("SELECT * FROM users WHERE id = ?", [$userId])->fetch();
            }
        } catch (Exception $e) {
            $error = 'Xatolik yuz berdi: ' . $e->getMessage();
        }
    }
}

// Get statistics
$groupCount = $db->query(
    "SELECT COUNT(*) as count FROM groups WHERE created_by = ?",
    [$userId]
)->fetch()['count'];

$contactCount = $db->query(
    "SELECT COUNT(*) as count FROM contacts c 
     JOIN groups g ON c.group_id = g.id 
     WHERE g.created_by = ?",
    [$userId]
)->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>Profil</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Yaratgan Guruhlar</h3>
                <p class="stat-number"><?php echo $groupCount; ?></p>
            </div>
            <div class="stat-card">
                <h3>Jami Kontaktlar</h3>
                <p class="stat-number"><?php echo $contactCount; ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Profil Ma'lumotlari</div>
            <form method="POST">
                <div class="form-group">
                    <label for="first_name">Ism *</label>
                    <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="last_name">Familiya *</label>
                    <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                </div>

                <div class="form-group">
                    <label>Rol</label>
                    <p><?php echo $user['role'] === 'super_admin' ? 'Super Admin' : 'Admin'; ?></p>
                </div>

                <div class="form-group">
                    <label>Oxirgi Kirish</label>
                    <p><?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Hech qachon'; ?></p>
                </div>

                <hr style="margin: 1.5rem 0;">

                <h3 style="margin-bottom: 1rem;">Parolni O'zgartirish (ixtiyoriy)</h3>

                <div class="form-group">
                    <label for="current_password">Joriy Parol</label>
                    <input type="password" id="current_password" name="current_password">
                    <small>Agar parolni o'zgartirmoqchi bo'lmasangiz, bo'sh qoldiring</small>
                </div>

                <div class="form-group">
                    <label for="password">Yangi Parol</label>
                    <input type="password" id="password" name="password" minlength="6">
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">Saqlash</button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo base_url('/assets/js/main.js'); ?>"></script>
</body>
</html>

