<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireSuperAdmin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($firstName) || empty($lastName)) {
        $error = 'Ism va familiyani kiriting';
    } elseif (empty($password) || strlen($password) < 6) {
        $error = 'Parol kamida 6 belgidan iborat bo\'lishi kerak';
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $db->query(
                "INSERT INTO users (password, role, first_name, last_name, created_by) VALUES (?, 'admin', ?, ?, ?)",
                [$hashedPassword, $firstName, $lastName, $userId]
            );
            
            $newAdminId = $db->getConnection()->lastInsertId();
            
            // Log activity
            $auth->logActivity($userId, 'create', 'users', $newAdminId, "Admin created: $firstName $lastName");
            
            header('Location: index.php?success=1');
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
    <title>Yangi Admin - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Yangi Admin Yaratish</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="first_name">Ism *</label>
                    <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="last_name">Familiya *</label>
                    <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Parol *</label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <small>Kamida 6 belgi</small>
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

