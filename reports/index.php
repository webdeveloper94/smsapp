<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : null;

// Build query based on role
$userCondition = '';
$params = [$dateFrom, $dateTo];
if ($userRole !== 'super_admin') {
    // Admin can only see their own SMS
    $userCondition = "AND sent_by = ?";
    $params[] = $userId;
} elseif ($filterUserId !== null) {
    // Super admin filtering by specific user
    $userCondition = "AND sent_by = ?";
    $params[] = $filterUserId;
}

// Get all admins for filter dropdown (only for super_admin)
$admins = [];
if ($userRole === 'super_admin') {
    // Get all admins, not just those with SMS in the date range
    $admins = $db->query(
        "SELECT id, first_name, last_name 
         FROM users 
         WHERE role = 'admin' AND is_active = 1
         ORDER BY first_name, last_name"
    )->fetchAll();
}

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM sms_logs 
    WHERE DATE(sent_at) BETWEEN ? AND ?
    $userCondition
";

$stats = $db->query($statsQuery, $params)->fetch();

// Ensure stats are not null
if (!$stats) {
    $stats = ['total' => 0, 'success_count' => 0, 'failed_count' => 0];
} else {
    $stats['total'] = (int)($stats['total'] ?? 0);
    $stats['success_count'] = (int)($stats['success_count'] ?? 0);
    $stats['failed_count'] = (int)($stats['failed_count'] ?? 0);
}

// Get detailed logs with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$logsQuery = "
    SELECT 
        sl.*,
        c.name as contact_name,
        g.name as group_name,
        u.first_name,
        u.last_name
    FROM sms_logs sl
    LEFT JOIN contacts c ON sl.contact_id = c.id
    LEFT JOIN groups g ON c.group_id = g.id
    LEFT JOIN users u ON sl.sent_by = u.id
    WHERE DATE(sl.sent_at) BETWEEN ? AND ?
    $userCondition
    ORDER BY sl.sent_at DESC
    LIMIT $perPage OFFSET $offset
";

$logs = $db->query($logsQuery, $params)->fetchAll();

// Get total count for pagination
$totalCountQuery = "
    SELECT COUNT(*) as total 
    FROM sms_logs 
    WHERE DATE(sent_at) BETWEEN ? AND ?
    $userCondition
";
$totalCount = $db->query($totalCountQuery, $params)->fetch()['total'];
$totalPages = ceil($totalCount / $perPage);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hisobotlar - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="actions-bar">
            <h1>SMS Hisobotlari</h1>
        </div>

        <!-- Filter Card -->
        <div class="card">
            <div class="card-header">Filtr</div>
            <form method="GET" action="" class="filter-form">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                    <div class="form-group">
                        <label for="date_from">Boshlanish Sanasi</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="date_to">Tugash Sanasi</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" required>
                    </div>
                    <?php if ($userRole === 'super_admin'): ?>
                        <div class="form-group">
                            <label for="user_id">Admin</label>
                            <select id="user_id" name="user_id" class="form-control">
                                <option value="">Barcha Adminlar</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo $admin['id']; ?>" <?php echo $filterUserId == $admin['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">Filtrlash</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Jami SMS</h3>
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <p style="margin-top: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    <?php echo date('d.m.Y', strtotime($dateFrom)); ?> - <?php echo date('d.m.Y', strtotime($dateTo)); ?>
                </p>
            </div>
            <div class="stat-card" style="border-top: 4px solid var(--success-color);">
                <h3>Muvaffaqiyatli</h3>
                <div class="stat-number" style="background: linear-gradient(135deg, var(--success-color) 0%, #34d399 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    <?php echo number_format($stats['success_count'] ?? 0); ?>
                </div>
                <p style="margin-top: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    <?php 
                    $total = $stats['total'] ?? 0;
                    $success = $stats['success_count'] ?? 0;
                    echo $total > 0 ? number_format(($success / $total) * 100, 1) : 0; 
                    ?>%
                </p>
            </div>
            <div class="stat-card" style="border-top: 4px solid var(--danger-color);">
                <h3>Xatolik</h3>
                <div class="stat-number" style="background: linear-gradient(135deg, var(--danger-color) 0%, #f87171 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    <?php echo number_format($stats['failed_count'] ?? 0); ?>
                </div>
                <p style="margin-top: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    <?php 
                    $total = $stats['total'] ?? 0;
                    $failed = $stats['failed_count'] ?? 0;
                    echo $total > 0 ? number_format(($failed / $total) * 100, 1) : 0; 
                    ?>%
                </p>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-content">
                    <span>Batafsil Hisobot (<?php echo $totalCount; ?>)</span>
                </div>
            </div>
            
            <?php if (empty($logs)): ?>
                <p>Bu oraliqda SMS yuborilmagan.</p>
            <?php else: ?>
                <div class="table-container" style="padding: 0; margin-top: 1rem;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sana va Vaqt</th>
                                <th>Telefon</th>
                                <th>Kontakt</th>
                                <th>Guruh</th>
                                <th>SMS Matni</th>
                                <th>Holat</th>
                                <?php if ($userRole === 'super_admin'): ?>
                                    <th>Yuboruvchi</th>
                                <?php endif; ?>
                                <th>Xatolik</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($log['sent_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($log['contact_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($log['group_name'] ?? '-'); ?></td>
                                    <td style="max-width: 200px; word-wrap: break-word;">
                                        <?php echo htmlspecialchars(mb_substr($log['message'], 0, 50)) . (mb_strlen($log['message']) > 50 ? '...' : ''); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $log['status'] === 'success' ? 'sent' : 'failed'; ?>">
                                            <?php echo $log['status'] === 'success' ? 'Muvaffaqiyatli' : 'Xatolik'; ?>
                                        </span>
                                    </td>
                                    <?php if ($userRole === 'super_admin'): ?>
                                        <td><?php echo htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? 'Sistema')); ?></td>
                                    <?php endif; ?>
                                    <td style="max-width: 200px; word-wrap: break-word; font-size: 0.85rem; color: var(--danger-color);">
                                        <?php echo $log['error_message'] ? htmlspecialchars($log['error_message']) : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?><?php echo $filterUserId !== null ? '&user_id=' . $filterUserId : ''; ?>&page=<?php echo $page - 1; ?>" class="pagination-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 18l-6-6 6-6"></path>
                                </svg>
                                Oldingi
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 18l-6-6 6-6"></path>
                                </svg>
                                Oldingi
                            </span>
                        <?php endif; ?>
                        
                        <div class="pagination-info">
                            <?php
                            $start = ($page - 1) * $perPage + 1;
                            $end = min($page * $perPage, $totalCount);
                            ?>
                            <span>Sahifa <?php echo $page; ?> / <?php echo $totalPages; ?> (<?php echo $start; ?>-<?php echo $end; ?> / <?php echo $totalCount; ?>)</span>
                        </div>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?><?php echo $filterUserId !== null ? '&user_id=' . $filterUserId : ''; ?>&page=<?php echo $page + 1; ?>" class="pagination-btn">
                                Keyingi
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18l6-6-6-6"></path>
                                </svg>
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                Keyingi
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18l6-6-6-6"></path>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo base_url('/assets/js/main.js'); ?>"></script>
</body>
</html>

