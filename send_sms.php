<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('send_sms');

$db = Database::getInstance();
$userId = $auth->getUserId();

// Get SMS limit info
$limitInfo = $auth->getSMSLimitInfo();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Yuborish - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('/assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>SMS Yuborish</h1>
        
        <!-- SMS Limit Info -->
        <?php if ($limitInfo['limit'] > 0): ?>
            <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-left: 4px solid var(--info-color);">
                <p style="margin: 0; font-weight: 600; color: var(--text-color);">
                    SMS Limit: 
                    <span style="color: <?php echo $limitInfo['remaining'] > 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>;">
                        <?php echo $limitInfo['remaining']; ?> / <?php echo $limitInfo['limit']; ?>
                    </span>
                </p>
            </div>
        <?php elseif ($auth->isSuperAdmin()): ?>
            <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-left: 4px solid var(--success-color);">
                <p style="margin: 0; font-weight: 600; color: var(--text-color);">
                    Cheksiz SMS yuborish huquqi
                </p>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">Yangi SMS Yuborish</div>
            <form id="sendSMSForm">
                <div class="form-group">
                    <label for="phone">Telefon Raqami *</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="998901234567" required>
                    <small>998 kodini kiriting yoki kiritingmasangiz avtomatik qo'shiladi</small>
                </div>
                
                <div class="form-group">
                    <label for="message">SMS Matni *</label>
                    <textarea id="message" name="message" rows="5" class="form-control" placeholder="SMS matnini kiriting..." required></textarea>
                    <small id="charCount">0 / 160 belgi</small>
                </div>
                
                <div id="errorDiv" class="alert alert-error" style="display: none; margin-top: 1rem;"></div>
                <div id="successDiv" class="alert alert-success" style="display: none; margin-top: 1rem;"></div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-block" id="sendBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
                            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path>
                        </svg>
                        SMS Yuborish
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo base_url('/assets/js/main.js'); ?>"></script>
    <script>
        // Character count
        const messageEl = document.getElementById('message');
        const charCountEl = document.getElementById('charCount');
        
        function updateCharCount() {
            if (!messageEl || !charCountEl) return;
            const count = messageEl.value.length;
            charCountEl.textContent = count + ' / 160 belgi';
            if (count > 160) {
                charCountEl.style.color = '#ef4444';
            } else {
                charCountEl.style.color = '#64748b';
            }
        }
        
        if (messageEl) {
            messageEl.addEventListener('input', updateCharCount);
        }
        
        // Form submit
        const form = document.getElementById('sendSMSForm');
        const sendBtn = document.getElementById('sendBtn');
        const errorDiv = document.getElementById('errorDiv');
        const successDiv = document.getElementById('successDiv');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const phone = document.getElementById('phone').value.trim();
                const message = document.getElementById('message').value.trim();
                
                if (!phone || !message) {
                    errorDiv.textContent = 'Telefon raqami va SMS matni kiritilishi shart';
                    errorDiv.style.display = 'block';
                    successDiv.style.display = 'none';
                    return;
                }
                
                // Disable button and show loading
                sendBtn.disabled = true;
                const originalText = sendBtn.innerHTML;
                sendBtn.innerHTML = '<span class="loading">Yuborilmoqda...</span>';
                
                // Hide previous messages
                errorDiv.style.display = 'none';
                successDiv.style.display = 'none';
                
                // Send AJAX request
                const formData = new FormData();
                formData.append('phone', phone);
                formData.append('message', message);
                
                const apiUrl = '<?php echo base_url('/api/send_direct_sms.php'); ?>';
                
                fetch(apiUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error('HTTP Error: ' + response.status + ' - ' + text.substring(0, 100));
                        });
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        
                        if (data.success) {
                            // Re-enable button first
                            sendBtn.disabled = false;
                            sendBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>SMS Yuborish';
                            
                            // Show success message
                            successDiv.textContent = data.message;
                            successDiv.style.display = 'block';
                            errorDiv.style.display = 'none';
                            
                            // Clear form
                            document.getElementById('phone').value = '';
                            document.getElementById('message').value = '';
                            updateCharCount();
                            
                            // Scroll to success message
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                            successDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            
                            // Don't reload immediately - let user see the message
                            // Reload page after 4 seconds to update limit info
                            setTimeout(() => {
                                location.reload();
                            }, 4000);
                        } else {
                            errorDiv.textContent = data.message;
                            errorDiv.style.display = 'block';
                            successDiv.style.display = 'none';
                            sendBtn.disabled = false;
                            sendBtn.innerHTML = originalText;
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                        console.error('Response text:', text);
                        errorDiv.innerHTML = 'Javobni tahlil qilishda xatolik: ' + e.message;
                        errorDiv.style.display = 'block';
                        successDiv.style.display = 'none';
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    errorDiv.textContent = 'Xatolik yuz berdi: ' + error.message;
                    errorDiv.style.display = 'block';
                    successDiv.style.display = 'none';
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = originalText;
                });
            });
        }
    </script>
</body>
</html>

