<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requirePermission('create_debts');

$db = Database::getInstance();
$userId = $auth->getUserId();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $debtAmount = floatval($_POST['debt_amount'] ?? 0);
    $firstSendDate = $_POST['first_send_date'] ?? null;
    $reminderInterval = !empty($_POST['reminder_interval']) ? intval($_POST['reminder_interval']) : null;
    $message = trim($_POST['message'] ?? '');

    // Validate phone
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 9) {
        $phone = '998' . $phone;
    }

    if (empty($name)) {
        $error = 'Qarzdor ismini kiriting';
    } elseif (empty($phone) || strlen($phone) < 9) {
        $error = 'Telefon raqamini to\'g\'ri kiriting';
    } elseif ($debtAmount <= 0) {
        $error = 'Qarz summasini kiriting';
    } elseif (empty($firstSendDate)) {
        $error = 'Birinchi SMS yuborish sanasini kiriting';
    } else {
        try {
            $db->query(
                "INSERT INTO debtors (name, phone, debt_amount, first_send_date, reminder_interval, created_by) VALUES (?, ?, ?, ?, ?, ?)",
                [$name, $phone, $debtAmount, $firstSendDate, $reminderInterval, $userId]
            );
            
            // Log activity
            $auth->logActivity($userId, 'create', 'debtors', $db->getConnection()->lastInsertId(), "Debtor created: $name");
            
            $success = 'Qarzdor muvaffaqiyatli yaratildi';
            header("Location: index.php?success=1");
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
    <title>Yangi Qarzdor - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <h1>Yangi Qarzdor Yaratish</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="name">Qarzdor Ismi *</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Telefon Raqami *</label>
                    <input type="tel" id="phone" name="phone" required placeholder="901234567" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    <small>998 kodini kiriting yoki kiritingmasangiz avtomatik qo'shiladi</small>
                </div>

                <div class="form-group">
                    <label for="debt_amount">Qarz Summasi (so'm) *</label>
                    <input type="number" id="debt_amount" name="debt_amount" required min="0" step="0.01" value="<?php echo htmlspecialchars($_POST['debt_amount'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="first_send_date">Birinchi SMS Yuborish Sanasi *</label>
                    <input type="date" id="first_send_date" name="first_send_date" required value="<?php echo htmlspecialchars($_POST['first_send_date'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="reminder_interval">Kun Oralig'i (Qayta SMS yuborish)</label>
                    <input type="number" id="reminder_interval" name="reminder_interval" min="1" placeholder="Masalan: 7" value="<?php echo htmlspecialchars($_POST['reminder_interval'] ?? ''); ?>">
                    <small>Qarz to'lanmagan bo'lsa, necha kundan keyin qayta SMS yuboriladi. Bo'sh qoldirilsa, qayta yuborilmaydi.</small>
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">Yaratish</button>
                    <a href="index.php" class="btn btn-secondary">Bekor qilish</a>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo base_url('/assets/js/main.js'); ?>"></script>
</body>
</html>

