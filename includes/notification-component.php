<div class="notifications-wrapper">
    <!-- Notification bell with badge -->
    <div class="notification-bell-wrapper">
        <button class="notification-bell-btn" id="notificationDropdown">
            <i class="bi bi-bell-fill"></i>
            <span class="notification-badge d-none">
                <span class="notification-count">0</span>
            </span>
        </button>
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-dropdown-header">
                <h6 class="notification-dropdown-title">
                    <i class="bi bi-bell-fill me-2"></i>Notifications
                </h6>
                <button class="notification-close-btn" id="closeNotificationDropdown">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="notification-dropdown-body">
                <div class="notifications-list">
                    <!-- Notifications will be loaded here -->
                </div>
            </div>
            <div class="notification-dropdown-footer">
                <button class="notification-footer-btn mark-all-read">
                    <i class="bi bi-check2-all me-2"></i>Mark all as read
                </button>
                <?php // provide a CSRF token for JS POSTs ?>
                <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
            </div>
        </div>
    </div>
</div>

<style>
    /* Notification Bell Wrapper */
    .notification-bell-wrapper {
        position: relative;
    }

    /* Notification Bell Button */
    .notification-bell-btn {
        background: transparent !important;
        border: none !important;
        color: #f1f5f9 !important;
        font-size: 1.25rem;
        cursor: pointer;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 !important;
        transition: all 0.3s ease;
        text-decoration: none !important;
        position: relative;
    }

    .notification-bell-btn:hover,
    .notification-bell-btn:focus {
        background: rgba(255, 255, 255, 0.1) !important;
        color: #f1f5f9 !important;
        box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
        outline: none !important;
    }

    .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: linear-gradient(135deg, #ef4444, #dc2626) !important;
        color: white !important;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.2rem 0.45rem;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.2);
        animation: pulse-notification 2s infinite;
    }

    @keyframes pulse-notification {
        0%, 100% {
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.2);
        }
        50% {
            box-shadow: 0 0 0 6px rgba(239, 68, 68, 0.2);
        }
    }

    .notification-badge.d-none {
        display: none !important;
    }

    /* Notification Dropdown - Solid Glassmorphism */
    .notification-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        min-width: 380px;
        max-width: 420px;
        background: rgba(30, 41, 59, 0.98);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1050;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 600px;
    }

    .notification-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    /* Header */
    .notification-dropdown-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.15), transparent);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-dropdown-title {
        font-weight: 700;
        color: #f1f5f9;
        font-size: 1.125rem;
        margin: 0;
        display: flex;
        align-items: center;
    }

    .notification-dropdown-title i {
        color: #2563eb;
        font-size: 1.25rem;
    }

    .notification-close-btn {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 1.25rem;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: none;
    }

    .notification-close-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #f1f5f9;
    }

    /* Body */
    .notification-dropdown-body {
        max-height: 360px;
        overflow-y: auto;
        flex: 1;
    }

    .notification-dropdown-body::-webkit-scrollbar {
        width: 8px;
    }

    .notification-dropdown-body::-webkit-scrollbar-track {
        background: transparent;
    }

    .notification-dropdown-body::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }

    .notification-dropdown-body::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* Notifications List */
    .notifications-list {
        display: flex;
        flex-direction: column;
    }

    /* Notification Items */
    .notification-item {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        background: transparent;
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item:hover {
        background: rgba(255, 255, 255, 0.08);
        padding-left: 1.75rem;
    }

    /* Unread indicator */
    .notification-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 0;
        background: #2563eb;
        border-radius: 3px;
        transition: height 0.3s ease;
    }

    .notification-item[data-is-read="false"]::before {
        height: 60%;
    }

    /* Icon */
    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        background: rgba(37, 99, 235, 0.15);
        color: #2563eb;
    }

    .notification-item[data-is-read="true"] .notification-icon {
        background: rgba(255, 255, 255, 0.05);
        color: #94a3b8;
    }

    /* Content */
    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-message {
        font-size: 0.9375rem;
        color: #f1f5f9;
        line-height: 1.5;
        margin-bottom: 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notification-item[data-is-read="true"] .notification-message {
        color: #cbd5e0;
        font-weight: 400;
    }

    .notification-time {
        font-size: 0.8125rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* Actions */
    .notification-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .view-notification {
        background: transparent;
        color: #2563eb;
        border: 1px solid rgba(37, 99, 235, 0.3);
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        font-weight: 600;
        border-radius: 6px;
        transition: all 0.3s ease;
        cursor: pointer;
        white-space: nowrap;
    }

    .view-notification:hover {
        background: rgba(37, 99, 235, 0.15);
        border-color: rgba(37, 99, 235, 0.5);
        color: #2563eb;
    }

    /* Footer */
    .notification-dropdown-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.04);
        display: flex;
        justify-content: center;
    }

    .notification-footer-btn {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #f1f5f9;
        padding: 0.75rem 1.5rem;
        font-size: 0.9375rem;
        font-weight: 600;
        border-radius: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .notification-footer-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.3);
        color: #f1f5f9;
    }

    .notification-footer-btn:disabled,
    .notification-footer-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .notification-footer-btn.disabled:hover {
        background: transparent;
        border-color: rgba(255, 255, 255, 0.2);
    }

    /* Empty State */
    .notifications-list .notification-empty {
        padding: 3rem 1.5rem;
        text-align: center;
        color: #94a3b8;
    }

    .notifications-list .notification-empty i {
        font-size: 3rem;
        color: rgba(255, 255, 255, 0.1);
        display: block;
        margin-bottom: 1rem;
    }

    .notifications-list .notification-empty p {
        margin: 0;
        font-size: 0.9375rem;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .notification-dropdown {
            position: fixed;
            top: 60px;
            right: 10px;
            left: 10px;
            width: auto;
            min-width: unset;
            max-width: unset;
        }

        .notification-close-btn {
            display: flex;
        }

        .notification-dropdown-header {
            padding: 1rem 1.25rem;
        }

        .notification-item {
            padding: 0.875rem 1.25rem;
        }

        .notification-item:hover {
            padding-left: 1.5rem;
        }

        .notification-dropdown-body {
            max-height: calc(100vh - 200px);
        }
    }

    /* Ensure dropdown stays on top */
    .notification-dropdown.show {
        z-index: 1050;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notificationBtn = document.getElementById('notificationDropdown');
        const notificationDropdown = document.querySelector('.notification-dropdown');
        const closeBtn = document.getElementById('closeNotificationDropdown');
        const notificationsList = document.querySelector('.notifications-list');
        const notificationBadge = document.querySelector('.notification-badge');
        const notificationCount = document.querySelector('.notification-count');
        const markAllBtn = document.querySelector('.mark-all-read');
        let notifications = [];

        // Toggle notification dropdown
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });

        // Close notification dropdown
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.remove('show');
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });

        // Prevent dropdown from closing when clicking inside
        notificationDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Function to format the time difference
        function timeAgo(date) {
            const seconds = Math.floor((new Date() - new Date(date)) / 1000);
            let interval = Math.floor(seconds / 31536000);
            
            if (interval > 1) return interval + ' years ago';
            interval = Math.floor(seconds / 2592000);
            if (interval > 1) return interval + ' months ago';
            interval = Math.floor(seconds / 86400);
            if (interval > 1) return interval + ' days ago';
            interval = Math.floor(seconds / 3600);
            if (interval > 1) return interval + ' hours ago';
            interval = Math.floor(seconds / 60);
            if (interval > 1) return interval + ' minutes ago';
            return Math.floor(seconds) + ' seconds ago';
        }

        // Function to update notifications UI
        function updateNotificationsUI() {
            const unreadCount = (typeof window.__notification_unread_count !== 'undefined') ? window.__notification_unread_count : notifications.filter(n => !n.is_read).length;

            console.log('Updating UI - Unread count:', unreadCount);

            // Show/hide badge based on unread count
            if (unreadCount > 0) {
                notificationBadge.classList.remove('d-none');
                notificationCount.textContent = unreadCount > 99 ? '99+' : unreadCount;
            } else {
                notificationBadge.classList.add('d-none');
                notificationCount.textContent = '0';
            }

            // Render notifications
            if (notifications.length > 0) {
                notificationsList.innerHTML = notifications.map(notification => {
                    const timeText = notification.time || notification.created_at || '';
                    let viewBtn = '';
                    const isRead = notification.is_read ? 'true' : 'false';
                    
                    if (notification.related_type === 'appointment' && notification.related_id) {
                        viewBtn = `<div class="notification-actions"><button class="view-notification" data-related-id="${notification.related_id}" data-notif-id="${notification.id}">View</button></div>`;
                    }
                    
                    return `
                        <div class="notification-item" data-id="${notification.id}" data-is-read="${isRead}">
                            <div class="notification-icon">
                                <i class="bi bi-bell-fill"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">
                                    <i class="bi bi-clock" style="font-size: 0.75rem;"></i>
                                    ${timeText}
                                </div>
                            </div>
                            ${viewBtn}
                        </div>
                    `;
                }).join('');

                // Attach click handlers for view buttons
                document.querySelectorAll('.view-notification').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const relatedId = this.dataset.relatedId;
                        const notifId = this.dataset.notifId;
                        
                        // Mark the notification as read first
                        fetch('<?= ROOT_PATH ?>notifications/mark_read.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                notification_id: parseInt(notifId), 
                                csrf_token: document.querySelector('input[name="csrf_token"]').value 
                            })
                        })
                        .then(response => response.json())
                        .then(markData => {
                            if (window.__notification_unread_count > 0) {
                                window.__notification_unread_count--;
                            }
                            updateNotificationsUI();
                            
                            // Open the appointment view in a modal if available
                            const detailsContainer = document.getElementById('appointmentDetails');
                            if (detailsContainer) {
                                fetch('<?= ROOT_PATH ?>notifications/view_appointment.php?id=' + encodeURIComponent(relatedId) + '&fragment=1')
                                    .then(r => r.text())
                                    .then(html => {
                                        detailsContainer.innerHTML = html;
                                        const modalEl = document.getElementById('detailsModal');
                                        if (modalEl) new bootstrap.Modal(modalEl).show();
                                    });
                            } else {
                                window.location.href = '<?= ROOT_PATH ?>notifications/view_appointment.php?id=' + encodeURIComponent(relatedId);
                            }
                            
                            // Close dropdown after viewing
                            notificationDropdown.classList.remove('show');
                            
                            // Refresh list
                            setTimeout(() => {
                                fetchNotifications();
                            }, 100);
                        })
                        .catch(error => {
                            console.error('Error marking as read:', error);
                        });
                    });
                });
            } else {
                notificationsList.innerHTML = `
                    <div class="notification-empty">
                        <i class="bi bi-inbox"></i>
                        <p>No notifications</p>
                    </div>
                `;
            }

            // Update mark all button state
            if (markAllBtn) {
                if (unreadCount > 0) {
                    markAllBtn.disabled = false;
                    markAllBtn.classList.remove('disabled');
                } else {
                    markAllBtn.disabled = true;
                    markAllBtn.classList.add('disabled');
                }
            }
        }

        // Function to fetch notifications
        function fetchNotifications() {
            fetch('<?= ROOT_PATH ?>notifications/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const payload = (data.data && typeof data.data === 'object') ? data.data : data;
                        notifications = payload.notifications || [];
                        window.__notification_unread_count = payload.unread_count || 0;
                        updateNotificationsUI();
                    } else {
                        console.error('Failed to fetch notifications:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }

        // Mark all as read
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const unreadCount = (typeof window.__notification_unread_count !== 'undefined') ? window.__notification_unread_count : notifications.filter(n => !n.is_read).length;
            
            if (unreadCount === 0) return;

            fetch('<?= ROOT_PATH ?>notifications/mark_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    mark_all: true,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.__notification_unread_count = 0;
                    updateNotificationsUI();
                    setTimeout(() => {
                        fetchNotifications();
                    }, 100);
                }
            })
            .catch(error => {
                console.error('Error marking all as read:', error);
            });
        });

        // Initial fetch
        fetchNotifications();

        // Fetch notifications every 30 seconds
        setInterval(fetchNotifications, 30000);
    });
</script>