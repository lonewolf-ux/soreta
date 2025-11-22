<?php
/**
 * Admin Sidebar Component
 * Collapsible sidebar with persistent state
 */
?>
<!-- Sidebar -->
<nav id="sidebar" class="admin-sidebar">
    <div class="sidebar-header">
        <div class="d-flex align-items-center">
            <div class="sidebar-logo">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div class="sidebar-brand">
                <h4>Soreta Admin</h4>
                <small class="text-muted">Electronics</small>
            </div>
        </div>
        <button type="button" id="sidebarCollapse" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <div class="sidebar-content">
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="appointments.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'appointments.php' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-check"></i>
                    <span class="nav-text">Appointments</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="technicians.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'technicians.php' ? 'active' : '' ?>">
                    <i class="bi bi-tools"></i>
                    <span class="nav-text">Technicians</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="troubleshooting.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'troubleshooting.php' ? 'active' : '' ?>">
                    <i class="bi bi-wrench"></i>
                    <span class="nav-text">Troubleshooting</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="feedback.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'feedback.php' ? 'active' : '' ?>">
                    <i class="bi bi-star"></i>
                    <span class="nav-text">Feedback</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="customers.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span class="nav-text">Customers</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-divider"></div>

        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="settings.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="../auth/logout.php" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['user_name'], 0, 2)) ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </div>
</nav>

<style>
.admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 280px;
    background: var(--background);
    border-right: 1px solid var(--border);
    transition: var(--transition-slow);
    z-index: 1000;
    display: flex;
    flex-direction: column;
}

/* Shared admin layout: ensures main content is offset from the fixed sidebar */
.admin-layout {
    display: flex;
    min-height: 100vh;
}

.admin-main {
    flex: 1;
    margin-left: 280px; /* width of expanded sidebar */
    transition: var(--transition-slow);
}

.admin-sidebar.collapsed ~ .admin-main {
    margin-left: 80px; /* width of collapsed sidebar */
}

@media (max-width: 768px) {
    .admin-main {
        margin-left: 0;
    }
    .admin-sidebar.collapsed ~ .admin-main {
        margin-left: 0;
    }
}

/* Admin-specific contrast tweaks: keep variables local to admin layout so public site isn't affected */
.admin-layout {
    --admin-text-primary: #e6eef8;   /* slightly cool white for primary text */
    --admin-text-secondary: #cbd5e1; /* lighter secondary */
    --admin-text-muted: #9aa7b6;     /* higher contrast muted */
}

/* Apply the admin variables to common utility classes inside admin */
.admin-main .text-muted { color: var(--admin-text-muted) !important; }
.admin-main .text-secondary { color: var(--admin-text-secondary) !important; }
.admin-main { color: var(--admin-text-primary); }

/* Make card-title captions and small helper text more legible on dark panels */
.admin-main .card-title.text-muted, .admin-main .card-header .card-title.text-muted {
    color: var(--admin-text-muted) !important;
}
.admin-main .form-text { color: var(--admin-text-muted) !important; }

/* Slightly brighten table header text in admin */
.admin-main .table thead th { color: var(--admin-text-secondary) !important; }

/* Ensure badges on dark backgrounds remain readable */
.admin-main .badge { color: #fff !important; }

.sidebar-header {
    padding: var(--space-6);
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.sidebar-logo {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: var(--font-size-xl);
    margin-right: var(--space-3);
}

.sidebar-brand h4 {
    margin: 0;
    font-size: var(--font-size-lg);
    color: var(--text-primary);
}

.sidebar-brand small {
    font-size: var(--font-size-xs);
}

.sidebar-content {
    flex: 1;
    padding: var(--space-4) 0;
    overflow-y: auto;
}

.sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin: var(--space-1) var(--space-4);
}

.nav-link {
    display: flex;
    align-items: center;
    padding: var(--space-3) var(--space-4);
    color: var(--text-secondary);
    text-decoration: none;
    border-radius: var(--radius);
    transition: var(--transition);
    position: relative;
}

.nav-link:hover {
    background: var(--surface);
    color: var(--text-primary);
}

.nav-link.active {
    background: var(--primary);
    color: white;
}

.nav-link i {
    width: 20px;
    margin-right: var(--space-3);
    font-size: var(--font-size-lg);
}

.nav-text {
    flex: 1;
    font-weight: 500;
}

.badge-notification {
    position: absolute;
    right: var(--space-4);
    top: 50%;
    transform: translateY(-50%);
    font-size: var(--font-size-xs);
    padding: var(--space-1) var(--space-2);
}

.sidebar-divider {
    height: 1px;
    background: var(--border-light);
    margin: var(--space-4) 0;
}

.sidebar-footer {
    padding: var(--space-4);
    border-top: 1px solid var(--border-light);
}

.user-info {
    display: flex;
    align-items: center;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    margin-right: var(--space-3);
}

.user-name {
    font-weight: 600;
    font-size: var(--font-size-sm);
    color: var(--text-primary);
}

.user-role {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

/* Collapsed State */
.admin-sidebar.collapsed {
    width: 80px;
}

.admin-sidebar.collapsed .sidebar-brand,
.admin-sidebar.collapsed .nav-text,
.admin-sidebar.collapsed .user-details,
.admin-sidebar.collapsed .badge-notification {
    display: none;
}

.admin-sidebar.collapsed .nav-link {
    justify-content: center;
    padding: var(--space-4);
}

.admin-sidebar.collapsed .nav-link i {
    margin-right: 0;
    font-size: var(--font-size-xl);
}

.admin-sidebar.collapsed .sidebar-header {
    justify-content: center;
    padding: var(--space-4);
}

.admin-sidebar.collapsed #sidebarCollapse i {
    transform: rotate(180deg);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .admin-sidebar {
        transform: translateX(-100%);
    }
    
    .admin-sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .admin-sidebar.collapsed {
        width: 280px;
        transform: translateX(-100%);
    }
    
    .admin-sidebar.collapsed.mobile-open {
        transform: translateX(0);
    }
    
    .admin-sidebar.collapsed .sidebar-brand,
    .admin-sidebar.collapsed .nav-text,
    .admin-sidebar.collapsed .user-details {
        display: block;
    }
    
    .admin-sidebar.collapsed .nav-link {
        justify-content: flex-start;
        padding: var(--space-3) var(--space-4);
    }
    
    .admin-sidebar.collapsed .nav-link i {
        margin-right: var(--space-3);
        font-size: var(--font-size-lg);
    }
}

/* Dark mode support for sidebar consistency */
@media (prefers-color-scheme: dark) {
    .admin-sidebar {
        background: #1a1a1a;
        color: #ffffff;
    }

    .sidebar-header {
        border-bottom-color: #444444;
    }

    .sidebar-brand h4 {
        color: #ffffff;
    }

    .sidebar-brand small {
        color: #cbd5e1;
    }

    .nav-link {
        color: #cbd5e1;
    }

    .nav-link:hover {
        background: #333333;
        color: #ffffff;
    }

    .nav-link.active {
        background: #4361ee;
        color: #ffffff;
    }

    .sidebar-divider {
        background: #444444;
    }

    .sidebar-footer {
        border-top-color: #444444;
    }

    .user-name {
        color: #ffffff;
    }

    .user-role {
        color: #cbd5e1;
    }

    .badge-notification {
        background-color: #4361ee !important;
        color: #ffffff !important;
    }
}
</style>

<script>
// Sidebar State Management
class SidebarManager {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.collapseBtn = document.getElementById('sidebarCollapse');
        this.init();
    }

    init() {
        // Load saved state
        this.loadState();
        
        // Bind events
        this.collapseBtn.addEventListener('click', () => this.toggle());
        
        // Handle window resize
        window.addEventListener('resize', () => this.handleResize());
        
        // Load notification count
        this.loadNotificationCount();
    }

    toggle() {
        this.sidebar.classList.toggle('collapsed');
        this.saveState();
    }

    saveState() {
        const isCollapsed = this.sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
        
        // Update preferences in database
        this.updateDatabasePreference(isCollapsed);
    }

    loadState() {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            this.sidebar.classList.add('collapsed');
        }
    }

    async updateDatabasePreference(isCollapsed) {
        try {
            const response = await fetch('update_preferences.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    preferences: { sidebar_collapsed: isCollapsed },
                    csrf_token: '<?= CSRFProtection::generateToken() ?>'
                })
            });
            
            if (!response.ok) {
                console.error('Failed to update preferences');
            }
        } catch (error) {
            console.error('Error updating preferences:', error);
        }
    }

    handleResize() {
        if (window.innerWidth <= 768) {
            this.sidebar.classList.remove('collapsed');
        }
    }

    async loadNotificationCount() {
        // Notification functionality removed
    }
}

// Initialize sidebar when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new SidebarManager();
});
</script>