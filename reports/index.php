<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requirePermission('view_reports');

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : null;
$filterType = isset($_GET['sms_type']) && $_GET['sms_type'] !== '' ? $_GET['sms_type'] : null; // 'group' or 'debt'
$filterGroupId = isset($_GET['group_id']) && $_GET['group_id'] !== '' ? intval($_GET['group_id']) : null;

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

// Build SMS type filter condition
$typeCondition = '';
if ($filterType === 'group') {
    $typeCondition = "AND sms_type = 'group'";
} elseif ($filterType === 'debt') {
    $typeCondition = "AND sms_type = 'debt'";
}

// Build group filter condition (only for group SMS)
$groupCondition = '';
if ($filterGroupId !== null) {
    $groupCondition = "AND group_id = ?";
}

// Get all admins and super_admins for filter dropdown (only for super_admin)
$admins = [];
if ($userRole === 'super_admin') {
    // Get all admins and super_admins, not just those with SMS in the date range
    $admins = $db->query(
        "SELECT id, first_name, last_name, role 
         FROM users 
         WHERE role IN ('admin', 'super_admin') AND is_active = 1
         ORDER BY role DESC, first_name, last_name"
    )->fetchAll();
}

// Get all groups for filter (only for super_admin or if user has groups)
$groups = [];
if ($userRole === 'super_admin') {
    $groups = $db->query("SELECT id, name FROM groups ORDER BY name")->fetchAll();
} else {
    $groups = $db->query("SELECT id, name FROM groups WHERE created_by = ? ORDER BY name", [$userId])->fetchAll();
}

// Get statistics from both sms_logs and debt_sms_logs
$groupFilterForStats = '';
$groupFilterParamsForStats = [];
if ($filterGroupId !== null) {
    $groupFilterForStats = "AND c.group_id = ?";
    $groupFilterParamsForStats = [$filterGroupId];
}

$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM (
        SELECT sl.status, sl.sent_at, sl.sent_by 
        FROM sms_logs sl
        LEFT JOIN contacts c ON sl.contact_id = c.id
        WHERE DATE(sl.sent_at) BETWEEN ? AND ?
        $userCondition
        $groupFilterForStats
        " . ($filterType === 'debt' ? "AND 1=0" : "") . "
        UNION ALL
        SELECT dsl.status, dsl.sent_at, dsl.sent_by 
        FROM debt_sms_logs dsl
        WHERE DATE(dsl.sent_at) BETWEEN ? AND ?
        $userCondition
        " . ($filterType === 'group' ? "AND 1=0" : "") . "
    ) as all_logs
";

$statsParams = array_merge($params, $groupFilterParamsForStats, $params);
$stats = $db->query($statsQuery, $statsParams)->fetch();

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
$perPage = 10; // 10 ta yozuv har sahifada
$offset = ($page - 1) * $perPage;

// Build logs query with filters
$groupFilterSQL = '';
$groupFilterParams = [];
if ($filterGroupId !== null) {
    $groupFilterSQL = "AND c.group_id = ?";
    $groupFilterParams = [$filterGroupId];
}

$logsQuery = "
    SELECT 
        id,
        phone,
        message,
        status,
        error_message,
        sent_at,
        sent_by,
        first_name,
        last_name,
        contact_name,
        group_name,
        debtor_name,
        sms_type
    FROM (
        SELECT 
            sl.id,
            sl.phone,
            sl.message,
            sl.status,
            sl.error_message,
            sl.sent_at,
            sl.sent_by,
            u.first_name,
            u.last_name,
            c.name as contact_name,
            g.name as group_name,
            g.id as group_id,
            NULL as debtor_name,
            'group' as sms_type
        FROM sms_logs sl
        LEFT JOIN contacts c ON sl.contact_id = c.id
        LEFT JOIN groups g ON c.group_id = g.id
        LEFT JOIN users u ON sl.sent_by = u.id
        WHERE DATE(sl.sent_at) BETWEEN ? AND ?
        $userCondition
        $groupFilterSQL
        " . ($filterType === 'debt' ? "AND 1=0" : "") . "
        UNION ALL
        SELECT 
            dsl.id,
            dsl.phone,
            dsl.message,
            dsl.status,
            dsl.error_message,
            dsl.sent_at,
            dsl.sent_by,
            u2.first_name,
            u2.last_name,
            NULL as contact_name,
            NULL as group_name,
            NULL as group_id,
            d.name as debtor_name,
            'debt' as sms_type
        FROM debt_sms_logs dsl
        LEFT JOIN debtors d ON dsl.debtor_id = d.id
        LEFT JOIN users u2 ON dsl.sent_by = u2.id
        WHERE DATE(dsl.sent_at) BETWEEN ? AND ?
        $userCondition
        " . ($filterType === 'group' ? "AND 1=0" : "") . "
    ) as all_logs
    WHERE 1=1
    $typeCondition
    ORDER BY sent_at DESC
    LIMIT $perPage OFFSET $offset
";

$logsParams = array_merge($params, $groupFilterParams, $params);
$logs = $db->query($logsQuery, $logsParams)->fetchAll();

// Get total count for pagination
$totalCountQuery = "
    SELECT COUNT(*) as total 
    FROM (
        SELECT sl.sent_at, sl.sent_by, 'group' as sms_type, c.group_id
        FROM sms_logs sl
        LEFT JOIN contacts c ON sl.contact_id = c.id
        WHERE DATE(sl.sent_at) BETWEEN ? AND ?
        $userCondition
        $groupFilterSQL
        " . ($filterType === 'debt' ? "AND 1=0" : "") . "
        UNION ALL
        SELECT dsl.sent_at, dsl.sent_by, 'debt' as sms_type, NULL as group_id
        FROM debt_sms_logs dsl
        WHERE DATE(dsl.sent_at) BETWEEN ? AND ?
        $userCondition
        " . ($filterType === 'group' ? "AND 1=0" : "") . "
    ) as all_logs
";
$totalCountResult = $db->query($totalCountQuery, $logsParams)->fetch();
$totalCount = (int)($totalCountResult['total'] ?? 0);
$totalPages = $totalCount > 0 ? ceil($totalCount / $perPage) : 1;
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
                                <?php 
                                $currentRole = '';
                                foreach ($admins as $index => $admin): 
                                    // Group by role
                                    if ($currentRole !== $admin['role']) {
                                        // Close previous optgroup if exists
                                        if ($currentRole !== '') {
                                            echo '</optgroup>';
                                        }
                                        $currentRole = $admin['role'];
                                        if ($currentRole === 'super_admin') {
                                            echo '<optgroup label="Super Adminlar">';
                                        } else {
                                            echo '<optgroup label="Adminlar">';
                                        }
                                    }
                                ?>
                                    <option value="<?php echo $admin['id']; ?>" <?php echo $filterUserId == $admin['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($currentRole !== ''): ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="sms_type">SMS Turi</label>
                        <select id="sms_type" name="sms_type" class="form-control">
                            <option value="">Barcha</option>
                            <option value="group" <?php echo $filterType === 'group' ? 'selected' : ''; ?>>Guruh</option>
                            <option value="debt" <?php echo $filterType === 'debt' ? 'selected' : ''; ?>>Qarz</option>
                        </select>
                    </div>
                    <div class="form-group" id="group_filter_container" style="<?php echo $filterType !== 'group' ? 'display: none;' : ''; ?>">
                        <label for="group_id">Guruh</label>
                        <select id="group_id" name="group_id" class="form-control">
                            <option value="">Barcha Guruhlar</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>" <?php echo $filterGroupId == $group['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($group['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                                <th>#</th>
                                <th>Sana va Vaqt</th>
                                <th>Telefon</th>
                                <th>Turi</th>
                                <th>Kontakt/Qarzdor</th>
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
                            <?php $i = $offset; // Pagination bilan to'g'ri tartib raqami uchun ?>
                            <?php foreach ($logs as $log): $i++; ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($log['sent_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['phone']); ?></td>
                                    <td>
                                        <?php if ($log['sms_type'] === 'group'): ?>
                                            <span class="status-badge status-pending" style="background: #3b82f6; color: white;">Guruh</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending" style="background: #f59e0b; color: white;">Qarz</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['sms_type'] === 'group'): ?>
                                            <?php echo htmlspecialchars($log['contact_name'] ?? '-'); ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($log['debtor_name'] ?? '-'); ?>
                                        <?php endif; ?>
                                    </td>
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
                            <a href="?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?><?php echo $filterUserId !== null ? '&user_id=' . $filterUserId : ''; ?><?php echo $filterType !== null ? '&sms_type=' . urlencode($filterType) : ''; ?><?php echo $filterGroupId !== null ? '&group_id=' . $filterGroupId : ''; ?>&page=<?php echo $page - 1; ?>" class="pagination-btn">
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
                            <a href="?date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?><?php echo $filterUserId !== null ? '&user_id=' . $filterUserId : ''; ?><?php echo $filterType !== null ? '&sms_type=' . urlencode($filterType) : ''; ?><?php echo $filterGroupId !== null ? '&group_id=' . $filterGroupId : ''; ?>&page=<?php echo $page + 1; ?>" class="pagination-btn">
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
    <script>
        // Show/hide group filter based on SMS type
        document.addEventListener('DOMContentLoaded', function() {
            const smsTypeSelect = document.getElementById('sms_type');
            const groupFilterContainer = document.getElementById('group_filter_container');
            const groupIdSelect = document.getElementById('group_id');
            
            if (smsTypeSelect && groupFilterContainer) {
                smsTypeSelect.addEventListener('change', function() {
                    if (this.value === 'group') {
                        groupFilterContainer.style.display = 'block';
                    } else {
                        groupFilterContainer.style.display = 'none';
                        groupIdSelect.value = '';
                    }
                });
            }
        });
    </script>
</body>
</html>

