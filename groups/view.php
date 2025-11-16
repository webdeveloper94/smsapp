<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $auth->getUserId();
$userRole = $auth->getUserRole();

$groupId = $_GET['id'] ?? 0;

// Get group
$group = $db->query(
    "SELECT g.*, u.first_name, u.last_name 
     FROM groups g 
     LEFT JOIN users u ON g.created_by = u.id 
     WHERE g.id = ?",
    [$groupId]
)->fetch();

if (!$group) {
    header('Location: index.php');
    exit;
}

// Check permissions
if ($userRole !== 'super_admin' && $group['created_by'] != $userId) {
    header('Location: index.php');
    exit;
}

// Get group settings
$settings = $db->query(
    "SELECT * FROM group_settings WHERE group_id = ?",
    [$groupId]
)->fetch();

// Pagination and Search
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20; // Contacts per page
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build search condition
$searchCondition = '';
$searchParams = [];
if (!empty($search)) {
    $searchCondition = "AND (name LIKE ? OR phone LIKE ? OR message LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $searchParams = [$searchTerm, $searchTerm, $searchTerm];
}

// Get total count
$totalCountQuery = "SELECT COUNT(*) as total FROM contacts WHERE group_id = ? $searchCondition";
$totalCount = $db->query($totalCountQuery, array_merge([$groupId], $searchParams))->fetch()['total'];
$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

// Get contacts with pagination
// Note: LIMIT and OFFSET must be integers, so we use intval() for safety
$perPage = intval($perPage);
$offset = intval($offset);
$contactsQuery = "SELECT * FROM contacts WHERE group_id = ? $searchCondition ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$contacts = $db->query($contactsQuery, array_merge([$groupId], $searchParams))->fetchAll();

$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guruh: <?php echo htmlspecialchars($group['name']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">Guruh muvaffaqiyatli yaratildi!</div>
        <?php endif; ?>

        <div class="actions-bar">
            <h1><?php echo htmlspecialchars($group['name']); ?></h1>
            <div>
                <a href="edit.php?id=<?php echo $groupId; ?>" class="btn btn-warning">Tahrirlash</a>
                <a href="contacts/add.php?group_id=<?php echo $groupId; ?>" class="btn btn-primary">Kontakt Qo'shish</a>
                <a href="index.php" class="btn btn-secondary">Orqaga</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Guruh Ma'lumotlari</div>
            <p><strong>Tavsif:</strong> <?php echo htmlspecialchars($group['description'] ?? 'Tavsif yo\'q'); ?></p>
            <p><strong>Yaratuvchi:</strong> <?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?></p>
            <p><strong>Yaratilgan:</strong> <?php echo date('d.m.Y H:i', strtotime($group['created_at'])); ?></p>
            <?php if ($settings): ?>
                <p><strong>Standart SMS Matni:</strong> <?php echo htmlspecialchars($settings['default_message'] ?? 'Yo\'q'); ?></p>
                <p><strong>Standart Yuborish Sanasi:</strong> <?php echo $settings['default_send_date'] ? date('d.m.Y', strtotime($settings['default_send_date'])) : 'Belgilanmagan'; ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-header-content">
                    <span>Kontaktlar (<?php echo $totalCount; ?>)</span>
                    <form method="GET" action="" class="search-form">
                        <input type="hidden" name="id" value="<?php echo $groupId; ?>">
                        <div class="search-box">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Qidirish (ism, telefon, SMS matni)..." class="search-input">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.35-4.35"></path>
                                </svg>
                                <span class="btn-text">Qidirish</span>
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="view.php?id=<?php echo $groupId; ?>" class="btn btn-sm btn-secondary">Tozalash</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (empty($contacts)): ?>
                <p>Kontaktlar mavjud emas. <?php if (!empty($search)): ?>
                    <a href="view.php?id=<?php echo $groupId; ?>">Qidiruvni tozalash</a>
                <?php else: ?>
                    <a href="contacts/add.php?group_id=<?php echo $groupId; ?>">Birinchi kontaktni qo'shing</a>
                <?php endif; ?></p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ism</th>
                            <th>Telefon</th>
                            <th>SMS Matni</th>
                            <th>Yuborish Sanasi</th>
                            <th>Holat</th>
                            <th>Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td><?php echo $contact['id']; ?></td>
                                <td><?php echo htmlspecialchars($contact['name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($contact['phone']); ?></td>
                                <td><?php echo htmlspecialchars($contact['message'] ?? ($settings['default_message'] ?? '-')); ?></td>
                                <td><?php echo $contact['send_date'] ? date('d.m.Y', strtotime($contact['send_date'])) : ($settings['default_send_date'] ? date('d.m.Y', strtotime($settings['default_send_date'])) : 'Belgilanmagan'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $contact['status']; ?>">
                                        <?php 
                                        echo $contact['status'] === 'pending' ? 'Kutilmoqda' : 
                                             ($contact['status'] === 'sent' ? 'Yuborilgan' : 'Xatolik');
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="openSMSModal(<?php echo $contact['id']; ?>, '<?php echo htmlspecialchars($contact['phone'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($contact['message'] ?? ($settings['default_message'] ?? ''), ENT_QUOTES); ?>')" class="btn btn-sm btn-success">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.25rem;">
                                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path>
                                            </svg>
                                            <span class="btn-text">SMS</span>
                                        </button>
                                        <a href="contacts/edit.php?id=<?php echo $contact['id']; ?>" class="btn btn-sm btn-warning">Tahrirlash</a>
                                        <a href="contacts/delete.php?id=<?php echo $contact['id']; ?>" class="btn btn-sm btn-danger">O'chirish</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?id=<?php echo $groupId; ?>&page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-btn">
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
                            <a href="?id=<?php echo $groupId; ?>&page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-btn">
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

    <!-- SMS Modal -->
    <div id="smsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>SMS Yuborish</h2>
                <button class="modal-close" onclick="closeSMSModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Telefon Raqami</label>
                    <input type="text" id="modalPhone" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label for="modalMessage">SMS Matni *</label>
                    <textarea id="modalMessage" rows="5" required placeholder="SMS matnini kiriting..."></textarea>
                    <small id="charCount">0 / 160 belgi</small>
                </div>
                <div id="modalError" class="alert alert-error" style="display: none; margin-top: 1rem;"></div>
                <div id="modalSuccess" class="alert alert-success" style="display: none; margin-top: 1rem;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSMSModal()">Bekor qilish</button>
                <button type="button" class="btn btn-primary" onclick="sendSMS()" id="sendSMSBtn">
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
        let currentContactId = null;

        // Wait for DOM to be fully loaded
        function initSMSModal() {
            // Check if all elements exist
            const phoneEl = document.getElementById('modalPhone');
            const messageEl = document.getElementById('modalMessage');
            const errorDiv = document.getElementById('modalError');
            const successDiv = document.getElementById('modalSuccess');
            const modal = document.getElementById('smsModal');
            const sendBtn = document.getElementById('sendSMSBtn');
            
            if (!phoneEl || !messageEl || !errorDiv || !successDiv || !modal || !sendBtn) {
                console.error('Modal elements not found!', {
                    phoneEl: !!phoneEl,
                    messageEl: !!messageEl,
                    errorDiv: !!errorDiv,
                    successDiv: !!successDiv,
                    modal: !!modal,
                    sendBtn: !!sendBtn
                });
                return false;
            }
            
            // Add event listener for message input
            if (messageEl) {
                messageEl.addEventListener('input', updateCharCount);
            }
            
            return true;
        }

        function openSMSModal(contactId, phone, defaultMessage) {
            // Ensure elements are available
            const phoneEl = document.getElementById('modalPhone');
            const messageEl = document.getElementById('modalMessage');
            const errorDiv = document.getElementById('modalError');
            const successDiv = document.getElementById('modalSuccess');
            const modal = document.getElementById('smsModal');
            
            if (!phoneEl || !messageEl || !errorDiv || !successDiv || !modal) {
                console.error('Modal elements not found in openSMSModal!');
                console.log('Available elements:', {
                    phoneEl: !!phoneEl,
                    messageEl: !!messageEl,
                    errorDiv: !!errorDiv,
                    successDiv: !!successDiv,
                    modal: !!modal
                });
                alert('Xatolik: Modal elementlari topilmadi. Sahifani yangilang (Ctrl+F5).');
                return;
            }
            
            currentContactId = contactId;
            phoneEl.value = phone || '';
            messageEl.value = defaultMessage || '';
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';
            if (messageEl) {
                messageEl.focus();
                updateCharCount();
            }
            if (modal) modal.style.display = 'flex';
        }

        function closeSMSModal() {
            const modal = document.getElementById('smsModal');
            const messageEl = document.getElementById('modalMessage');
            const errorDiv = document.getElementById('modalError');
            const successDiv = document.getElementById('modalSuccess');
            
            if (modal) modal.style.display = 'none';
            currentContactId = null;
            if (messageEl) messageEl.value = '';
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';
        }

        function updateCharCount() {
            const messageEl = document.getElementById('modalMessage');
            const charCountEl = document.getElementById('charCount');
            
            if (!messageEl || !charCountEl) return;
            
            const message = messageEl.value;
            const count = message.length;
            charCountEl.textContent = count + ' / 160 belgi';
            if (count > 160) {
                charCountEl.style.color = '#ef4444';
            } else {
                charCountEl.style.color = '#64748b';
            }
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSMSModal);
        } else {
            // DOM already loaded
            initSMSModal();
        }

        function sendSMS() {
            const messageEl = document.getElementById('modalMessage');
            let errorDiv = document.getElementById('modalError');
            let successDiv = document.getElementById('modalSuccess');
            const sendBtn = document.getElementById('sendSMSBtn');

            // Check required elements
            if (!messageEl) {
                alert('Xatolik: SMS matn maydoni topilmadi.');
                return;
            }
            
            if (!sendBtn) {
                alert('Xatolik: Yuborish tugmasi topilmadi.');
                return;
            }

            // Create error/success divs if they don't exist
            const modalBody = document.querySelector('#smsModal .modal-body');
            if (modalBody) {
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.id = 'modalError';
                    errorDiv.className = 'alert alert-error';
                    errorDiv.style.display = 'none';
                    const charCount = document.getElementById('charCount');
                    if (charCount && charCount.parentNode) {
                        charCount.parentNode.insertBefore(errorDiv, charCount.nextSibling);
                    } else {
                        modalBody.appendChild(errorDiv);
                    }
                }
                
                if (!successDiv) {
                    successDiv = document.createElement('div');
                    successDiv.id = 'modalSuccess';
                    successDiv.className = 'alert alert-success';
                    successDiv.style.display = 'none';
                    if (errorDiv && errorDiv.nextSibling) {
                        errorDiv.parentNode.insertBefore(successDiv, errorDiv.nextSibling);
                    } else {
                        modalBody.appendChild(successDiv);
                    }
                }
            }

            const message = messageEl.value.trim();

            if (!message) {
                errorDiv.textContent = 'SMS matnini kiriting';
                errorDiv.style.display = 'block';
                if (successDiv) successDiv.style.display = 'none';
                return;
            }

            // Disable button and show loading
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="loading">Yuborilmoqda...</span>';

            // Hide previous messages
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';

            // Send AJAX request
            const formData = new FormData();
            formData.append('contact_id', currentContactId);
            formData.append('message', message);

            const apiUrl = '<?php echo base_url('/api/send_sms.php'); ?>';
            console.log('Sending SMS to:', apiUrl);
            console.log('Contact ID:', currentContactId);
            console.log('Message:', message);
            
            fetch(apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // Session cookie'lar uchun
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    // Response text'ni olish
                    return response.text().then(text => {
                        console.error('HTTP Error Response:', text);
                        throw new Error('HTTP Error: ' + response.status + ' - ' + text.substring(0, 100));
                    });
                }
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        successDiv.textContent = data.message;
                        successDiv.style.display = 'block';
                        setTimeout(() => {
                            closeSMSModal();
                            location.reload();
                        }, 1500);
                    } else {
                        let errorMsg = data.message || 'Noma\'lum xatolik';
                        if (data.response) {
                            errorMsg += '\nAPI Javobi: ' + (typeof data.response === 'string' ? data.response.substring(0, 200) : JSON.stringify(data.response).substring(0, 200));
                        }
                        errorDiv.innerHTML = errorMsg.replace(/\n/g, '<br>');
                        errorDiv.style.display = 'block';
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>Yuborish';
                    }
                } catch (e) {
                    console.error('Parse error:', e);
                    console.error('Response text:', text);
                    errorDiv.innerHTML = 'Javobni tahlil qilishda xatolik: ' + e.message + '<br>Javob: ' + text.substring(0, 500);
                    errorDiv.style.display = 'block';
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>Yuborish';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                errorDiv.innerHTML = 'Xatolik yuz berdi: ' + error.message + '<br>Iltimos, brauzer console\'ni tekshiring (F12)';
                errorDiv.style.display = 'block';
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>Yuborish';
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('smsModal');
            if (event.target == modal) {
                closeSMSModal();
            }
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSMSModal();
            }
        });
    </script>
</body>
</html>

