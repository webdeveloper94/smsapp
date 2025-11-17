<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

$auth = new Auth();
$auth->requirePermission('view_dashboard');

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

// Get groups based on role
if ($userRole === 'super_admin') {
    $groups = $db->query("SELECT g.*, u.first_name, u.last_name 
                         FROM groups g 
                         LEFT JOIN users u ON g.created_by = u.id 
                         ORDER BY g.created_at DESC")->fetchAll();
} else {
    $groups = $db->query("SELECT g.*, u.first_name, u.last_name 
                         FROM groups g 
                         LEFT JOIN users u ON g.created_by = u.id 
                         WHERE g.created_by = ? 
                         ORDER BY g.created_at DESC", 
                         [$userId])->fetchAll();
}

// Get statistics
$totalGroups = count($groups);

// Build contact statistics query based on role
if ($userRole === 'super_admin') {
    // Super admin sees all contacts
    $totalContacts = $db->query("SELECT COUNT(*) as count FROM contacts")->fetch()['count'];
    $pendingSMS = $db->query("SELECT COUNT(*) as count FROM contacts WHERE status = 'pending' AND send_date <= CURDATE()")->fetch()['count'];
    $sentSMS = $db->query("SELECT COUNT(*) as count FROM contacts WHERE status = 'sent'")->fetch()['count'];
} else {
    // Admin sees only contacts from their own groups
    $totalContacts = $db->query(
        "SELECT COUNT(*) as count 
         FROM contacts c 
         INNER JOIN groups g ON c.group_id = g.id 
         WHERE g.created_by = ?",
        [$userId]
    )->fetch()['count'];
    
    $pendingSMS = $db->query(
        "SELECT COUNT(*) as count 
         FROM contacts c 
         INNER JOIN groups g ON c.group_id = g.id 
         WHERE g.created_by = ? AND c.status = 'pending' AND c.send_date <= CURDATE()",
        [$userId]
    )->fetch()['count'];
    
    $sentSMS = $db->query(
        "SELECT COUNT(*) as count 
         FROM contacts c 
         INNER JOIN groups g ON c.group_id = g.id 
         WHERE g.created_by = ? AND c.status = 'sent'",
        [$userId]
    )->fetch()['count'];
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bosh Sahifa - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>Bosh Sahifa</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Jami Guruhlar</h3>
                <p class="stat-number"><?php echo $totalGroups; ?></p>
            </div>
            <div class="stat-card">
                <h3>Jami Kontaktlar</h3>
                <p class="stat-number"><?php echo $totalContacts; ?></p>
            </div>
            <div class="stat-card">
                <h3>Kutilayotgan SMS</h3>
                <p class="stat-number"><?php echo $pendingSMS; ?></p>
            </div>
            <div class="stat-card">
                <h3>Yuborilgan SMS</h3>
                <p class="stat-number"><?php echo $sentSMS; ?></p>
            </div>
        </div>

        <div class="actions-bar">
            <?php if ($auth->hasPermission('create_groups')): ?>
                <a href="<?php echo base_url('/groups/create.php'); ?>" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Yangi Guruh
                </a>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <h2>Guruhlar</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nomi</th>
                        <th>Tavsif</th>
                        <th>Yaratuvchi</th>
                        <th>Kontaktlar</th>
                        <th>Yuborish Sanasi</th>
                        <th>Yaratilgan</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($groups)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Guruhlar mavjud emas</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groups as $group): ?>
                            <?php
                            $contactCount = $db->query(
                                "SELECT COUNT(*) as count FROM contacts WHERE group_id = ?",
                                [$group['id']]
                            )->fetch()['count'];
                            
                            // Get group settings for default send date
                            $groupSettings = $db->query(
                                "SELECT default_send_date FROM group_settings WHERE group_id = ?",
                                [$group['id']]
                            )->fetch();
                            
                            // Get earliest send date from contacts
                            $earliestContactDate = $db->query(
                                "SELECT MIN(send_date) as earliest_date FROM contacts WHERE group_id = ? AND send_date IS NOT NULL",
                                [$group['id']]
                            )->fetch()['earliest_date'];
                            
                            // Determine send date
                            $sendDate = null;
                            if ($earliestContactDate) {
                                $sendDate = $earliestContactDate;
                            } elseif ($groupSettings && $groupSettings['default_send_date']) {
                                $sendDate = $groupSettings['default_send_date'];
                            }
                            
                            // Check if all contacts are sent
                            $allSent = false;
                            if ($contactCount > 0) {
                                $sentCount = $db->query(
                                    "SELECT COUNT(*) as count FROM contacts WHERE group_id = ? AND status = 'sent'",
                                    [$group['id']]
                                )->fetch()['count'];
                                $allSent = ($sentCount == $contactCount);
                            }
                            
                            // Format send date display
                            $sendDateDisplay = 'Belgilanmagan';
                            if ($sendDate) {
                                $sendDateObj = new DateTime($sendDate);
                                $today = new DateTime();
                                $today->setTime(0, 0, 0); // Set to start of day for comparison
                                $sendDateObj->setTime(0, 0, 0);
                                
                                // Only show "Yuborilgan" if date has passed AND all contacts are sent
                                if ($sendDateObj < $today && $allSent) {
                                    $sendDateDisplay = '<span class="status-badge status-sent">Yuborilgan</span>';
                                } elseif ($sendDateObj < $today) {
                                    // Date passed but not all sent
                                    $sendDateDisplay = '<span class="status-badge status-pending" style="background: #f59e0b; color: white;">' . date('d.m.Y', strtotime($sendDate)) . ' (O\'tgan)</span>';
                                } else {
                                    // Future date - always show the date
                                    $sendDateDisplay = date('d.m.Y', strtotime($sendDate));
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($group['id']); ?></td>
                                <td><?php echo htmlspecialchars($group['name']); ?></td>
                                <td><?php echo htmlspecialchars($group['description'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?></td>
                                <td><?php echo $contactCount; ?></td>
                                <td><?php echo $sendDateDisplay; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($group['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?php echo base_url('/groups/view.php?id=' . $group['id']); ?>" class="btn btn-sm btn-info">Ko'rish</a>
                                        <?php if ($userRole === 'super_admin' || $group['created_by'] == $userId): ?>
                                            <a href="<?php echo base_url('/groups/edit.php?id=' . $group['id']); ?>" class="btn btn-sm btn-warning">Tahrirlash</a>
                                            <a href="<?php echo base_url('/groups/delete.php?id=' . $group['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Haqiqatan ham o\'chirmoqchimisiz?')">O'chirish</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="<?php echo base_url('/assets/js/main.js'); ?>"></script>
</body>
</html>

