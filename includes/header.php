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
            <a href="<?php echo base_url('/index.php'); ?>">Bosh Sahifa</a>
            <a href="<?php echo base_url('/groups/index.php'); ?>">Guruhlar</a>
            <a href="<?php echo base_url('/reports/index.php'); ?>">Hisobotlar</a>
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

