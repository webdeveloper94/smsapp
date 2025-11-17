<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requirePermission('view_debts');

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

// Get debt SMS message template for current user
$debtSettings = $db->query(
    "SELECT sms_message_template FROM debt_settings WHERE user_id = ? LIMIT 1",
    [$userId]
)->fetch();
$defaultMessageTemplate = $debtSettings ? $debtSettings['sms_message_template'] : 'sizning megamarket do\'konidan qarzingiz bor iltimos qarzingizni to\'lang';

// Handle SMS template update
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    $newTemplate = trim($_POST['sms_template'] ?? '');
    if (empty($newTemplate)) {
        $error = 'SMS matn shablonini kiriting';
    } else {
        try {
            // Check if settings exist for this user
            $existing = $db->query("SELECT id FROM debt_settings WHERE user_id = ?", [$userId])->fetch();
            if ($existing) {
                $db->query("UPDATE debt_settings SET sms_message_template = ? WHERE user_id = ?", [$newTemplate, $userId]);
            } else {
                $db->query("INSERT INTO debt_settings (user_id, sms_message_template) VALUES (?, ?)", [$userId, $newTemplate]);
            }
            
            // Log activity
            $auth->logActivity($userId, 'update', 'debt_settings', null, "Debt SMS template updated for user");
            
            $success = 'SMS matn shabloni muvaffaqiyatli yangilandi!';
            $defaultMessageTemplate = $newTemplate;
        } catch (Exception $e) {
            $error = 'Xatolik yuz berdi: ' . $e->getMessage();
        }
    }
}

// Pagination and Search
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20; // Debtors per page
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterAdmin = isset($_GET['admin']) ? intval($_GET['admin']) : 0;

// Build search condition
$searchCondition = '';
$searchParams = [];
if (!empty($search)) {
    $searchCondition = "AND (d.name LIKE ? OR d.phone LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $searchParams = [$searchTerm, $searchTerm];
}

// Build admin filter condition
$adminCondition = '';
$adminParams = [];
if ($userRole === 'super_admin' && $filterAdmin > 0) {
    $adminCondition = "AND d.created_by = ?";
    $adminParams = [$filterAdmin];
}

// Get total count
$baseQuery = "FROM debtors d 
              LEFT JOIN users u ON d.created_by = u.id 
              WHERE d.status != 'deleted'";
$whereClause = $userRole === 'super_admin' 
    ? $baseQuery . " " . $adminCondition . " " . $searchCondition
    : $baseQuery . " AND d.created_by = ? " . $searchCondition;

$totalCountQuery = "SELECT COUNT(*) as total " . $whereClause;
$totalParams = [];
if ($userRole !== 'super_admin') {
    $totalParams[] = $userId;
}
$totalParams = array_merge($totalParams, $adminParams, $searchParams);
$totalCount = $db->query($totalCountQuery, $totalParams)->fetch()['total'];
$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

// Get debtors with pagination
$perPage = intval($perPage);
$offset = intval($offset);
$debtorsQuery = "SELECT d.*, u.first_name, u.last_name " . $whereClause . " ORDER BY d.created_at DESC LIMIT $perPage OFFSET $offset";
$debtors = $db->query($debtorsQuery, $totalParams)->fetchAll();

// Get all admins and super_admins for filter (super_admin only)
$allAdmins = [];
if ($userRole === 'super_admin') {
    $allAdmins = $db->query("SELECT id, first_name, last_name, role FROM users WHERE role IN ('admin', 'super_admin') ORDER BY role DESC, first_name, last_name")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qarzdorlar - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="actions-bar">
            <h1>Qarzdorlar</h1>
            <div>
                <button onclick="openSMSConfigModal()" class="btn btn-info" style="margin-right: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
                        <path d="M12 20h9"></path>
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                    </svg>
                    SMS Matn Sozlash
                </button>
                <?php if ($auth->hasPermission('create_debts')): ?>
                    <a href="create.php" class="btn btn-primary">Yangi Qarzdor</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <div class="card-header-content">
                    <span>Qarzdorlar (<?php echo $totalCount; ?>)</span>
                    <form method="GET" action="" class="search-form" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <?php if ($userRole === 'super_admin' && !empty($allAdmins)): ?>
                            <select name="admin" class="form-control" style="min-width: 200px;">
                                <option value="0">Barcha Adminlar</option>
                                <?php 
                                $currentRole = '';
                                foreach ($allAdmins as $index => $admin): 
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
                                    <option value="<?php echo $admin['id']; ?>" <?php echo $filterAdmin == $admin['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($currentRole !== ''): ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        <?php endif; ?>
                        <div class="search-box" style="flex: 1; min-width: 200px;">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Qidirish (ism, telefon)..." class="search-input">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.35-4.35"></path>
                                </svg>
                                <span class="btn-text">Qidirish</span>
                            </button>
                            <?php if (!empty($search) || $filterAdmin > 0): ?>
                                <a href="index.php" class="btn btn-sm btn-secondary">Tozalash</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ism</th>
                        <th>Telefon</th>
                        <th>Qarz Summasi</th>
                        <th>Birinchi SMS Sanasi</th>
                        <th>Kun Oralig'i</th>
                        <th>Oxirgi Yuborilgan</th>
                        <th>Holat</th>
                        <th>Yaratuvchi</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($debtors)): ?>
                        <tr>
                            <td colspan="10" class="text-center">
                                Qarzdorlar mavjud emas.
                                <?php if (!empty($search) || $filterAdmin > 0): ?>
                                    <a href="index.php">Qidiruvni tozalash</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($debtors as $debtor): ?>
                            <?php
                            // Format reminder interval
                            $reminderText = 'Yo\'q';
                            if ($debtor['reminder_interval'] !== null && $debtor['reminder_interval'] > 0) {
                                $reminderText = $debtor['reminder_interval'] . ' kun';
                            }
                            
                            // Format status
                            $statusBadge = '';
                            if ($debtor['status'] === 'active') {
                                $statusBadge = '<span class="status-badge status-pending">Faol</span>';
                            } elseif ($debtor['status'] === 'paid') {
                                $statusBadge = '<span class="status-badge status-sent">To\'langan</span>';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($debtor['id']); ?></td>
                                <td><?php echo htmlspecialchars($debtor['name']); ?></td>
                                <td><?php echo htmlspecialchars($debtor['phone']); ?></td>
                                <td><?php echo number_format($debtor['debt_amount'], 0, ',', ' '); ?> so'm</td>
                                <td><?php echo date('d.m.Y', strtotime($debtor['first_send_date'])); ?></td>
                                <td><?php echo $reminderText; ?></td>
                                <td><?php echo $debtor['last_sent_date'] ? date('d.m.Y', strtotime($debtor['last_sent_date'])) : 'Hali yuborilmagan'; ?></td>
                                <td><?php echo $statusBadge; ?></td>
                                <td><?php echo htmlspecialchars($debtor['first_name'] . ' ' . $debtor['last_name']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($auth->hasPermission('send_sms')): ?>
                                            <button onclick="openDebtSMSModal(<?php echo $debtor['id']; ?>, '<?php echo htmlspecialchars($debtor['phone'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($debtor['name'], ENT_QUOTES); ?>', <?php echo $debtor['debt_amount']; ?>)" class="btn btn-sm btn-success">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.25rem;">
                                                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path>
                                                </svg>
                                                <span class="btn-text">SMS</span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (($userRole === 'super_admin' || $debtor['created_by'] == $userId) && $auth->hasPermission('edit_debts')): ?>
                                            <a href="edit.php?id=<?php echo $debtor['id']; ?>" class="btn btn-sm btn-warning">Tahrirlash</a>
                                        <?php endif; ?>
                                        <?php if (($userRole === 'super_admin' || $debtor['created_by'] == $userId) && $auth->hasPermission('delete_debts')): ?>
                                            <a href="delete.php?id=<?php echo $debtor['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Haqiqatan ham o\'chirmoqchimisiz?')">O'chirish</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="margin-top: 1.5rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filterAdmin > 0 ? '&admin=' . $filterAdmin : ''; ?>" class="pagination-btn">
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
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filterAdmin > 0 ? '&admin=' . $filterAdmin : ''; ?>" class="pagination-btn">
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
    </div>

    <!-- SMS Template Config Modal -->
    <div id="smsConfigModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>SMS Matn Shablonini Sozlash</h2>
                <button class="modal-close" onclick="closeSMSConfigModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="update_template" value="1">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="sms_template">SMS Matn Shabloni *</label>
                        <textarea id="sms_template" name="sms_template" rows="4" required placeholder="Masalan: sizning megamarket do'konidan qarzingiz bor iltimos qarzingizni to'lang"><?php echo htmlspecialchars($defaultMessageTemplate); ?></textarea>
                        <small>
                            <strong>Eslatma:</strong> Matn boshida "Hurmatli [Ism]!" va oxirida "Qarzingiz: [Summa] so'm." avtomatik qo'shiladi.<br>
                            Siz faqat o'rtadagi qismni kiriting.
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Namuna SMS:</label>
                        <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
                            <strong>Hurmatli Alisher!</strong> <span id="previewTemplate"><?php echo htmlspecialchars($defaultMessageTemplate); ?></span> <strong>Qarzingiz: 50 000 so'm.</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeSMSConfigModal()">Bekor qilish</button>
                    <button type="submit" class="btn btn-primary">Saqlash</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SMS Modal -->
    <div id="debtSMSModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>SMS Yuborish</h2>
                <button class="modal-close" onclick="closeDebtSMSModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Qarzdor</label>
                    <input type="text" id="modalDebtorName" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Telefon Raqami</label>
                    <input type="text" id="modalDebtorPhone" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label for="modalDebtorMessage">SMS Matni *</label>
                    <textarea id="modalDebtorMessage" rows="5" required placeholder="SMS matnini kiriting..."></textarea>
                    <small id="debtCharCount">0 / 160 belgi</small>
                </div>
                <div id="modalDebtorError" class="alert alert-error" style="display: none; margin-top: 1rem;"></div>
                <div id="modalDebtorSuccess" class="alert alert-success" style="display: none; margin-top: 1rem;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDebtSMSModal()">Bekor qilish</button>
                <button type="button" class="btn btn-primary" onclick="sendDebtSMS()" id="sendDebtSMSBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
                        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path>
                    </svg>
                    Yuborish
                </button>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url('/assets/js/main.js'); ?>"></script>
    <script>
        let currentDebtorId = null;

        function updateDebtCharCount() {
            const messageEl = document.getElementById('modalDebtorMessage');
            const charCountEl = document.getElementById('debtCharCount');
            if (messageEl && charCountEl) {
                const length = messageEl.value.length;
                charCountEl.textContent = length + ' / 160 belgi';
            }
        }

        function openDebtSMSModal(debtorId, phone, name, debtAmount) {
            const nameEl = document.getElementById('modalDebtorName');
            const phoneEl = document.getElementById('modalDebtorPhone');
            const messageEl = document.getElementById('modalDebtorMessage');
            const errorDiv = document.getElementById('modalDebtorError');
            const successDiv = document.getElementById('modalDebtorSuccess');
            const modal = document.getElementById('debtSMSModal');
            
            if (!nameEl || !phoneEl || !messageEl || !errorDiv || !successDiv || !modal) {
                alert('Xatolik: Modal elementlari topilmadi. Sahifani yangilang.');
                return;
            }
            
            currentDebtorId = debtorId;
            nameEl.value = name || '';
            phoneEl.value = phone || '';
            
            // Generate default message using template
            const formattedAmount = debtAmount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            const template = '<?php echo addslashes($defaultMessageTemplate); ?>';
            let defaultMessage = "Hurmatli " + name + "! " + template + " Qarzingiz: " + formattedAmount + " so'm.";
            messageEl.value = defaultMessage;
            
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';
            
            updateDebtCharCount();
            modal.style.display = 'flex';
        }

        function closeDebtSMSModal() {
            const modal = document.getElementById('debtSMSModal');
            if (modal) {
                modal.style.display = 'none';
            }
            currentDebtorId = null;
        }

        function sendDebtSMS() {
            const messageEl = document.getElementById('modalDebtorMessage');
            let errorDiv = document.getElementById('modalDebtorError');
            let successDiv = document.getElementById('modalDebtorSuccess');
            const sendBtn = document.getElementById('sendDebtSMSBtn');

            if (!messageEl) {
                alert('Xatolik: SMS matn maydoni topilmadi.');
                return;
            }

            const message = messageEl.value.trim();
            if (!message) {
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.id = 'modalDebtorError';
                    errorDiv.className = 'alert alert-error';
                    messageEl.parentElement.appendChild(errorDiv);
                }
                errorDiv.textContent = 'SMS matnini kiriting';
                errorDiv.style.display = 'block';
                return;
            }

            if (!currentDebtorId) {
                alert('Xatolik: Qarzdor ID topilmadi.');
                return;
            }

            // Disable button
            if (sendBtn) {
                sendBtn.disabled = true;
                sendBtn.textContent = 'Yuborilmoqda...';
            }

            // Hide previous messages
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';

            // Send AJAX request
            const formData = new FormData();
            formData.append('debtor_id', currentDebtorId);
            formData.append('message', message);

            fetch('<?php echo base_url('/api/send_debt_sms.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (!successDiv) {
                        successDiv = document.createElement('div');
                        successDiv.id = 'modalDebtorSuccess';
                        successDiv.className = 'alert alert-success';
                        messageEl.parentElement.appendChild(successDiv);
                    }
                    successDiv.textContent = data.message;
                    successDiv.style.display = 'block';
                    
                    // Clear message after 2 seconds and close modal
                    setTimeout(() => {
                        closeDebtSMSModal();
                        // Reload page to update last_sent_date
                        window.location.reload();
                    }, 2000);
                } else {
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.id = 'modalDebtorError';
                        errorDiv.className = 'alert alert-error';
                        messageEl.parentElement.appendChild(errorDiv);
                    }
                    errorDiv.textContent = data.message || 'Xatolik yuz berdi';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.id = 'modalDebtorError';
                    errorDiv.className = 'alert alert-error';
                    messageEl.parentElement.appendChild(errorDiv);
                }
                errorDiv.textContent = 'Xatolik yuz berdi: ' + error.message;
                errorDiv.style.display = 'block';
            })
            .finally(() => {
                // Re-enable button
                if (sendBtn) {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>Yuborish';
                }
            });
        }

        // SMS Config Modal functions
        function openSMSConfigModal() {
            const modal = document.getElementById('smsConfigModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeSMSConfigModal() {
            const modal = document.getElementById('smsConfigModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Update preview in real-time
            const templateInput = document.getElementById('sms_template');
            const preview = document.getElementById('previewTemplate');
            if (templateInput && preview) {
                templateInput.addEventListener('input', function() {
                    preview.textContent = this.value;
                });
            }

            // SMS message character counter
            const messageEl = document.getElementById('modalDebtorMessage');
            if (messageEl) {
                messageEl.addEventListener('input', updateDebtCharCount);
            }

            // Close modal when clicking outside
            const debtModal = document.getElementById('debtSMSModal');
            if (debtModal) {
                debtModal.addEventListener('click', function(e) {
                    if (e.target === debtModal) {
                        closeDebtSMSModal();
                    }
                });
            }

            const configModal = document.getElementById('smsConfigModal');
            if (configModal) {
                configModal.addEventListener('click', function(e) {
                    if (e.target === configModal) {
                        closeSMSConfigModal();
                    }
                });
            }
        });
    </script>
</body>
</html>

