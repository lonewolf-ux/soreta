            </div> <!-- End of main content -->
        </div> <!-- End of content -->
    </div> <!-- End of admin-container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            sidebar.classList.toggle('active');
            content.classList.toggle('active');

            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('active');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });

        // Load sidebar state from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            if (isCollapsed) {
                sidebar.classList.add('active');
                content.classList.add('active');
            }
        });

        // Load notifications
        function loadNotifications() {
                fetch('<?= ROOT_PATH ?>notifications/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // API returns payload under data.data
                        const payload = (data.data && typeof data.data === 'object') ? data.data : data;
                        const count = payload.unread_count || 0;

                        // Legacy elements (if present)
                        const notificationCount = document.getElementById('notificationCount');
                        const notificationList = document.getElementById('notificationList');
                        if (notificationCount) {
                            notificationCount.textContent = count;
                            notificationCount.style.display = (count > 0) ? 'inline-block' : 'none';
                        }

                        if (notificationList) {
                            notificationList.innerHTML = '';
                            const items = payload.notifications || [];
                            if (items.length === 0) {
                                notificationList.innerHTML = '<li><a class="dropdown-item" href="#">No new notifications</a></li>';
                            } else {
                                items.forEach(notification => {
                                    const li = document.createElement('li');
                                    const a = document.createElement('a');
                                    a.className = 'dropdown-item';
                                    a.href = notification.link || '#';
                                    a.innerHTML = `${notification.message || ''}`;
                                    a.addEventListener('click', function(e) {
                                        markNotificationRead(notification.id);
                                    });
                                    li.appendChild(a);
                                    notificationList.appendChild(li);
                                });
                                notificationList.innerHTML += '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-center" href="notifications.php">View all</a></li>';
                            }
                        }

                        // Newer notification-component badge update (if present)
                        const badge = document.querySelector('.notification-badge');
                        const badgeCount = document.querySelector('.notification-count');
                        if (badge && badgeCount) {
                            if (count > 0) badge.classList.remove('d-none'); else badge.classList.add('d-none');
                            badgeCount.textContent = count;
                        }
                    }
                }).catch(err => {
                    console.error('Failed to load notifications:', err);
                });
        }

        // Mark single notification as read
        function markNotificationRead(notificationId) {
            fetch('<?= ROOT_PATH ?>notifications/mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            }).catch(error => {
                console.error('Error marking notification as read:', error);
            });
        }

        // Load notifications on page load and set interval to update every 60 seconds
        loadNotifications();
        setInterval(loadNotifications, 60000);

        // Also fetch a simple unread count for header badges
        async function loadNotificationCounts() {
            try {
                const response = await fetch('<?= ROOT_PATH ?>admin/get_notification_count.php');
                const data = await response.json();
                if (data.success) {
                    const payload = (data.data && typeof data.data === 'object') ? data.data : data;
                    const headerBadge = document.getElementById('headerNotificationBadge');
                    const sidebarBadge = document.getElementById('appointmentBadge');
                    const count = payload.unread_count || 0;
                    if (headerBadge) headerBadge.textContent = count;
                    if (sidebarBadge) {
                        if (count > 0) {
                            sidebarBadge.textContent = count;
                            sidebarBadge.style.display = 'inline-block';
                        } else {
                            sidebarBadge.style.display = 'none';
                        }
                    }

                    // Also update notification-component badge (if present)
                    try {
                        const notifBadge = document.querySelector('.notification-badge');
                        const notifCountEl = document.querySelector('.notification-count');
                        if (notifBadge && notifCountEl) {
                            if (count > 0) notifBadge.classList.remove('d-none'); else notifBadge.classList.add('d-none');
                            notifCountEl.textContent = count;
                        }
                    } catch (e) {
                        // ignore DOM errors
                        console.debug('Notification badge update skipped:', e);
                    }
                }
            } catch (err) {
                console.error('Failed to load notification counts', err);
            }
        }

        loadNotificationCounts();
        setInterval(loadNotificationCounts, 30000);
    </script>
</body>
</html>