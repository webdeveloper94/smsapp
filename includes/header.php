<?php
if (!isset($auth)) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/auth.php';
    $auth = new Auth();
}
?>
<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <h2><?php echo APP_NAME; ?></h2>
        </div>
        <nav class="main-nav">
            <?php if ($auth->hasPermission('view_dashboard')): ?>
                <a href="<?php echo base_url('/index.php'); ?>">Bosh Sahifa</a>
            <?php endif; ?>
            <?php if ($auth->hasPermission('view_groups')): ?>
                <a href="<?php echo base_url('/groups/index.php'); ?>">Guruhlar</a>
            <?php endif; ?>
            <?php if ($auth->hasPermission('view_debts')): ?>
                <a href="<?php echo base_url('/debts/index.php'); ?>">Qarzlar</a>
            <?php endif; ?>
            <?php if ($auth->hasPermission('view_reports')): ?>
                <a href="<?php echo base_url('/reports/index.php'); ?>">Hisobotlar</a>
            <?php endif; ?>
            <?php if ($auth->hasPermission('send_sms')): ?>
                <a href="<?php echo base_url('/send_sms.php'); ?>">SMS Yuborish</a>
            <?php endif; ?>
            <?php if ($auth->isSuperAdmin()): ?>
                <a href="<?php echo base_url('/admins/index.php'); ?>">Adminlar</a>
            <?php endif; ?>
            <a href="<?php echo base_url('/profile.php'); ?>">Profil</a>
            <a href="<?php echo base_url('/logout.php'); ?>">Chiqish</a>
        </nav>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
            <span class="role-badge"><?php echo $auth->isSuperAdmin() ? 'Super Admin' : 'Admin'; ?></span>
        </div>
    </div>
</header>

