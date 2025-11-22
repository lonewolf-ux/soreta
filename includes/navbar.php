<?php
// includes/navbar.php - Modern Mega-Menu Navigation Component
// Include this file in your pages: <?php include 'includes/navbar.php'; ?>

// Ensure required variables are available (defaults if not set by parent page)
$settings = $settings ?? [];
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? null;

// Set navigation variables
$navSettings = $settings;
$navIsLoggedIn = $isLoggedIn;
$navUserRole = $userRole;
$navCurrentPage = basename($_SERVER['PHP_SELF'], '.php');

// Determine root path for links
$navRootPath = '';
if (strpos($_SERVER['PHP_SELF'], '/customer/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/admin/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/auth/') !== false) {
    $navRootPath = '../';
}

// Ensure variables are always defined to prevent warnings
$navIsLoggedIn = $navIsLoggedIn ?? false;
$navUserRole = $navUserRole ?? null;
$navCurrentPage = $navCurrentPage ?? 'index';
$navRootPath = $navRootPath ?? '';
$navSettings = $navSettings ?? [];
?>

<!-- Mega Menu Navbar Styles -->
<style>
/* ========================================
   MODERN MEGA-MENU NAVIGATION
   Kinsta-inspired minimal design
   ======================================== */

:root {
    --nav-bg: #ffffff;
    --nav-bg-scrolled: rgba(255, 255, 255, 0.98);
    --nav-text: #1a1a2e;
    --nav-text-muted: #64748b;
    --nav-hover: var(--primary, #2563eb);
    --nav-border: #e2e8f0;
    --nav-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    --nav-shadow-scrolled: 0 4px 20px rgba(0, 0, 0, 0.08);
    --mega-bg: #ffffff;
    --mega-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
    --mega-border-radius: 16px;
    --transition-fast: 0.15s ease;
    --transition-normal: 0.25s ease;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --nav-bg: #0f172a;
        --nav-bg-scrolled: rgba(15, 23, 42, 0.98);
        --nav-text: #f1f5f9;
        --nav-text-muted: #94a3b8;
        --nav-border: #334155;
        --mega-bg: #1e293b;
    }
}

/* Main Navbar Container */
.mega-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1100;
    background: var(--nav-bg);
    border-bottom: 1px solid var(--nav-border);
    transition: all var(--transition-normal);
}

.mega-navbar.scrolled {
    background: var(--nav-bg-scrolled);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: var(--nav-shadow-scrolled);
}

.mega-navbar-inner {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 72px;
}

/* Logo */
.mega-navbar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    flex-shrink: 0;
}

.mega-navbar-brand img {
    height: 36px;
    width: auto;
}

.mega-navbar-brand-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--primary, #2563eb), #7c3aed);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.mega-navbar-brand-text {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--nav-text);
    letter-spacing: -0.02em;
}

/* Main Navigation */
.mega-navbar-nav {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.mega-navbar-nav > li {
    position: relative;
}

/* Nav Links */
.mega-nav-link {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.625rem 1rem;
    font-size: 0.9375rem;
    font-weight: 500;
    color: var(--nav-text);
    text-decoration: none;
    border-radius: 8px;
    transition: all var(--transition-fast);
    white-space: nowrap;
}

.mega-nav-link:hover {
    color: var(--nav-hover);
    background: rgba(37, 99, 235, 0.06);
}

.mega-nav-link.active {
    color: var(--nav-hover);
}

.mega-nav-link .chevron {
    font-size: 0.75rem;
    transition: transform var(--transition-fast);
    opacity: 0.6;
}

.mega-navbar-nav > li:hover .mega-nav-link .chevron {
    transform: rotate(180deg);
}

/* Mega Menu Dropdown */
.mega-dropdown {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(10px);
    min-width: 280px;
    background: var(--mega-bg);
    border: 1px solid var(--nav-border);
    border-radius: var(--mega-border-radius);
    box-shadow: var(--mega-shadow);
    padding: 1.25rem;
    opacity: 0;
    visibility: hidden;
    transition: all var(--transition-normal);
    z-index: 1000;
}

.mega-navbar-nav > li:hover .mega-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

/* Wide mega menu for services */
.mega-dropdown.mega-dropdown-wide {
    min-width: 600px;
    left: 0;
    transform: translateY(10px);
}

.mega-navbar-nav > li:hover .mega-dropdown.mega-dropdown-wide {
    transform: translateY(0);
}

/* Mega Menu Grid */
.mega-menu-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.mega-menu-grid.single-col {
    grid-template-columns: 1fr;
}

/* Mega Menu Items */
.mega-menu-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    text-decoration: none;
    transition: all var(--transition-fast);
}

.mega-menu-item:hover {
    background: rgba(37, 99, 235, 0.06);
}

.mega-menu-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    transition: transform var(--transition-fast);
}

.mega-menu-item:hover .mega-menu-icon {
    transform: scale(1.05);
}

.mega-menu-icon.icon-blue {
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
}

.mega-menu-icon.icon-green {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.mega-menu-icon.icon-purple {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}

.mega-menu-icon.icon-orange {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.mega-menu-icon.icon-red {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.mega-menu-icon.icon-teal {
    background: rgba(20, 184, 166, 0.1);
    color: #14b8a6;
}

.mega-menu-content {
    flex: 1;
    min-width: 0;
}

.mega-menu-title {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--nav-text);
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mega-menu-desc {
    font-size: 0.8125rem;
    color: var(--nav-text-muted);
    line-height: 1.5;
}

/* Mega Menu Section */
.mega-menu-section {
    padding: 0.75rem 0;
}

.mega-menu-section:first-child {
    padding-top: 0;
}

.mega-menu-section:last-child {
    padding-bottom: 0;
}

.mega-menu-section + .mega-menu-section {
    border-top: 1px solid var(--nav-border);
}

.mega-menu-section-title {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--nav-text-muted);
    padding: 0.5rem 1rem;
    margin-bottom: 0.25rem;
}

/* Simple Menu Link */
.mega-simple-link {
    display: block;
    padding: 0.625rem 1rem;
    font-size: 0.9375rem;
    font-weight: 500;
    color: var(--nav-text);
    text-decoration: none;
    border-radius: 8px;
    transition: all var(--transition-fast);
}

.mega-simple-link:hover {
    background: rgba(37, 99, 235, 0.06);
    color: var(--nav-hover);
}

/* Right Side Actions */
.mega-navbar-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.mega-nav-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    font-size: 0.9375rem;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
    transition: all var(--transition-fast);
    white-space: nowrap;
}

.mega-nav-btn-ghost {
    color: var(--nav-text);
    background: transparent;
    border: none;
}

.mega-nav-btn-ghost:hover {
    color: var(--nav-hover);
    background: rgba(37, 99, 235, 0.06);
}

.mega-nav-btn-outline {
    color: var(--nav-text);
    background: transparent;
    border: 1.5px solid var(--nav-border);
}

.mega-nav-btn-outline:hover {
    border-color: var(--nav-hover);
    color: var(--nav-hover);
    background: rgba(37, 99, 235, 0.04);
}

.mega-nav-btn-primary {
    color: #ffffff;
    background: var(--primary, #2563eb);
    border: none;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}

.mega-nav-btn-primary:hover {
    background: color-mix(in srgb, var(--primary, #2563eb) 90%, #000);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
    color: #ffffff;
}

/* User Avatar */
.mega-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary, #2563eb), #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all var(--transition-fast);
    text-transform: uppercase;
}

.mega-user-avatar:hover {
    border-color: var(--nav-hover);
    transform: scale(1.05);
}

/* User Dropdown */
.mega-user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 240px;
    background: var(--mega-bg);
    border: 1px solid var(--nav-border);
    border-radius: var(--mega-border-radius);
    box-shadow: var(--mega-shadow);
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all var(--transition-normal);
    z-index: 1000;
    overflow: hidden;
}

.mega-user-wrapper:hover .mega-user-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.mega-user-header {
    padding: 1.25rem;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(124, 58, 237, 0.05));
    border-bottom: 1px solid var(--nav-border);
}

.mega-user-name {
    font-weight: 700;
    color: var(--nav-text);
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.mega-user-email {
    font-size: 0.8125rem;
    color: var(--nav-text-muted);
}

.mega-user-menu {
    padding: 0.5rem;
}

.mega-user-menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    font-size: 0.9375rem;
    font-weight: 500;
    color: var(--nav-text);
    text-decoration: none;
    border-radius: 8px;
    transition: all var(--transition-fast);
}

.mega-user-menu-item:hover {
    background: rgba(37, 99, 235, 0.06);
    color: var(--nav-hover);
}

.mega-user-menu-item i {
    font-size: 1.125rem;
    color: var(--nav-text-muted);
    width: 20px;
    text-align: center;
}

.mega-user-menu-item:hover i {
    color: var(--nav-hover);
}

.mega-user-menu-divider {
    height: 1px;
    background: var(--nav-border);
    margin: 0.5rem 0;
}

/* Mobile Toggle */
.mega-mobile-toggle {
    display: none;
    width: 44px;
    height: 44px;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    color: var(--nav-text);
    transition: all var(--transition-fast);
}

.mega-mobile-toggle:hover {
    background: rgba(37, 99, 235, 0.06);
}

.mega-mobile-toggle i {
    font-size: 1.5rem;
}

/* Mobile Navigation */
.mega-mobile-nav {
    display: none;
    position: fixed;
    top: 72px;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--nav-bg);
    z-index: 1099;
    overflow-y: auto;
    padding: 1rem;
    transform: translateX(100%);
    transition: transform var(--transition-normal);
}

.mega-mobile-nav.open {
    transform: translateX(0);
}

.mega-mobile-section {
    padding: 1rem 0;
    border-bottom: 1px solid var(--nav-border);
}

.mega-mobile-section:last-child {
    border-bottom: none;
}

.mega-mobile-section-title {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--nav-text-muted);
    padding: 0.5rem 0;
    margin-bottom: 0.5rem;
}

.mega-mobile-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 0.5rem;
    font-size: 1rem;
    font-weight: 500;
    color: var(--nav-text);
    text-decoration: none;
    border-radius: 8px;
    transition: all var(--transition-fast);
}

.mega-mobile-link:hover,
.mega-mobile-link.active {
    color: var(--nav-hover);
    background: rgba(37, 99, 235, 0.06);
}

.mega-mobile-link i {
    font-size: 1.25rem;
    width: 24px;
    text-align: center;
    color: var(--nav-text-muted);
}

.mega-mobile-link:hover i,
.mega-mobile-link.active i {
    color: var(--nav-hover);
}

.mega-mobile-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-top: 1rem;
}

.mega-mobile-actions .mega-nav-btn {
    justify-content: center;
    padding: 0.875rem 1.5rem;
}

/* Responsive */
@media (max-width: 1024px) {
    .mega-navbar-nav {
        display: none;
    }

    .mega-mobile-toggle {
        display: flex;
    }

    .mega-mobile-nav {
        display: block;
    }

    .mega-navbar-actions {
        gap: 0.5rem;
    }

    .mega-navbar-actions .mega-nav-btn-outline,
    .mega-navbar-actions .mega-nav-btn-ghost {
        display: none;
    }
}

@media (max-width: 640px) {
    .mega-navbar-inner {
        padding: 0 1rem;
        height: 64px;
    }

    .mega-navbar-brand-text {
        font-size: 1.125rem;
    }

    .mega-mobile-nav {
        top: 64px;
    }
}

/* Notification Bell */
.mega-notification-wrapper {
    position: relative;
}

.mega-notification-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: 10px;
    color: var(--nav-text);
    cursor: pointer;
    transition: all var(--transition-fast);
    position: relative;
}

.mega-notification-btn:hover {
    background: rgba(37, 99, 235, 0.06);
    color: var(--nav-hover);
}

.mega-notification-btn i {
    font-size: 1.25rem;
}

.mega-notification-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
    border: 2px solid var(--nav-bg);
}

/* Spacer for fixed navbar */
.mega-navbar-spacer {
    height: 72px;
}

@media (max-width: 640px) {
    .mega-navbar-spacer {
        height: 64px;
    }
}
</style>

<!-- Mega Menu Navbar HTML -->
<nav class="mega-navbar" id="megaNavbar">
    <div class="mega-navbar-inner">
        <!-- Logo -->
        <a href="<?= $navRootPath ?>index.php" class="mega-navbar-brand">
            <?php
            $navLogo = $navSettings['site_logo'] ?? '';
            if (!empty($navLogo) && is_string($navLogo)): ?>
                <img src="<?= htmlspecialchars($navLogo[0] === '/' ? $navLogo : $navRootPath . $navLogo) ?>"
                     alt="<?= htmlspecialchars($navSettings['company_name'] ?? 'Soreta') ?>">
            <?php else: ?>
                <div class="mega-navbar-brand-icon">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
            <?php endif; ?>
            <span class="mega-navbar-brand-text"><?= htmlspecialchars($navSettings['company_name'] ?? 'Soreta Electronics') ?></span>
        </a>

        <!-- Main Navigation (Desktop) -->
        <ul class="mega-navbar-nav">
            <!-- Home -->
            <li>
                <a href="<?= $navRootPath ?>index.php" class="mega-nav-link <?= $navCurrentPage === 'index' ? 'active' : '' ?>">
                    Home
                </a>
            </li>

            <!-- Services with Mega Menu -->
            <li>
                <a href="<?= $navRootPath ?>services.php" class="mega-nav-link <?= $navCurrentPage === 'services' ? 'active' : '' ?>">
                    Services
                    <i class="bi bi-chevron-down chevron"></i>
                </a>
                <div class="mega-dropdown mega-dropdown-wide">
                    <div class="mega-menu-grid">
                        <a href="<?= $navRootPath ?>services.php#installation" class="mega-menu-item">
                            <div class="mega-menu-icon icon-blue">
                                <i class="bi bi-gear-wide-connected"></i>
                            </div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">Installation</div>
                                <div class="mega-menu-desc">Professional setup for home & office automation, security systems</div>
                            </div>
                        </a>
                        <a href="<?= $navRootPath ?>services.php#repair" class="mega-menu-item">
                            <div class="mega-menu-icon icon-orange">
                                <i class="bi bi-wrench-adjustable-circle"></i>
                            </div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">Repair</div>
                                <div class="mega-menu-desc">Fast diagnosis, component-level repair & emergency services</div>
                            </div>
                        </a>
                        <a href="<?= $navRootPath ?>services.php#maintenance" class="mega-menu-item">
                            <div class="mega-menu-icon icon-green">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">Maintenance</div>
                                <div class="mega-menu-desc">Preventive care, system optimization & 24/7 monitoring</div>
                            </div>
                        </a>
                        <a href="<?= $navRootPath ?>troubleshooting.php" class="mega-menu-item">
                            <div class="mega-menu-icon icon-purple">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">DIY Troubleshooting</div>
                                <div class="mega-menu-desc">Step-by-step guides to fix common issues yourself</div>
                            </div>
                        </a>
                    </div>
                    <div class="mega-menu-section">
                        <a href="<?= $navIsLoggedIn && $navUserRole === 'customer' ? $navRootPath . 'customer/book-appointment.php' : $navRootPath . 'auth/register.php' ?>"
                           class="mega-menu-item" style="background: rgba(37, 99, 235, 0.06);">
                            <div class="mega-menu-icon icon-blue">
                                <i class="bi bi-calendar-plus"></i>
                            </div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">Book Appointment <i class="bi bi-arrow-right" style="font-size: 0.75rem;"></i></div>
                                <div class="mega-menu-desc">Schedule a service visit with our expert technicians</div>
                            </div>
                        </a>
                    </div>
                </div>
            </li>

            <!-- About -->
            <li>
                <a href="<?= $navRootPath ?>about.php" class="mega-nav-link <?= $navCurrentPage === 'about' ? 'active' : '' ?>">
                    About
                </a>
            </li>

            <!-- Contact -->
            <li>
                <a href="<?= $navRootPath ?>contact.php" class="mega-nav-link <?= $navCurrentPage === 'contact' ? 'active' : '' ?>">
                    Contact
                </a>
            </li>

            <!-- Troubleshooting -->
            <li>
                <a href="<?= $navRootPath ?>troubleshooting.php" class="mega-nav-link <?= $navCurrentPage === 'troubleshooting' ? 'active' : '' ?>">
                    Troubleshooting
                </a>
            </li>

            <?php if ($navIsLoggedIn && $navUserRole === 'customer'): ?>
            <!-- Dashboard -->
            <li>
                <a href="<?= $navRootPath ?>customer/dashboard.php" class="mega-nav-link <?= $navCurrentPage === 'dashboard' ? 'active' : '' ?>">
                    Dashboard
                </a>
            </li>
            <?php elseif ($navIsLoggedIn && $navUserRole === 'admin'): ?>
            <!-- Admin -->
            <li>
                <a href="<?= $navRootPath ?>admin/dashboard.php" class="mega-nav-link">
                    Admin
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Right Side Actions -->
        <div class="mega-navbar-actions">
            <?php if ($navIsLoggedIn): ?>
                <!-- Notification Bell (optional - include your notification component) -->
                <div class="mega-notification-wrapper">
                    <button class="mega-notification-btn" type="button">
                        <i class="bi bi-bell"></i>
                        <!-- <span class="mega-notification-badge"></span> -->
                    </button>
                </div>

                <!-- User Avatar with Dropdown -->
                <div class="mega-user-wrapper" style="position: relative;">
                    <div class="mega-user-avatar">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 2)) ?>
                    </div>
                    <div class="mega-user-dropdown">
                        <div class="mega-user-header">
                            <div class="mega-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                            <div class="mega-user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                        </div>
                        <div class="mega-user-menu">
                            <?php if ($navUserRole === 'customer'): ?>
                            <a href="<?= $navRootPath ?>customer/dashboard.php" class="mega-user-menu-item">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="<?= $navRootPath ?>customer/profile.php" class="mega-user-menu-item">
                                <i class="bi bi-person"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="<?= $navRootPath ?>customer/book-appointment.php" class="mega-user-menu-item">
                                <i class="bi bi-calendar-plus"></i>
                                <span>Book Appointment</span>
                            </a>
                            <?php elseif ($navUserRole === 'admin'): ?>
                            <a href="<?= $navRootPath ?>admin/dashboard.php" class="mega-user-menu-item">
                                <i class="bi bi-speedometer2"></i>
                                <span>Admin Dashboard</span>
                            </a>
                            <?php endif; ?>
                            <div class="mega-user-menu-divider"></div>
                            <a href="<?= $navRootPath ?>auth/logout.php" class="mega-user-menu-item">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= $navRootPath ?>auth/login.php" class="mega-nav-btn mega-nav-btn-ghost">
                    Login
                </a>
                <a href="<?= $navRootPath ?>auth/register.php" class="mega-nav-btn mega-nav-btn-primary">
                    Get Started
                </a>
            <?php endif; ?>

            <!-- Mobile Toggle -->
            <button class="mega-mobile-toggle" type="button" onclick="toggleMobileNav()">
                <i class="bi bi-list" id="mobileToggleIcon"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Navigation Panel -->
<div class="mega-mobile-nav" id="megaMobileNav">
    <div class="mega-mobile-section">
        <div class="mega-mobile-section-title">Navigation</div>
        <a href="<?= $navRootPath ?>index.php" class="mega-mobile-link <?= $navCurrentPage === 'index' ? 'active' : '' ?>">
            <i class="bi bi-house"></i>
            Home
        </a>
        <a href="<?= $navRootPath ?>services.php" class="mega-mobile-link <?= $navCurrentPage === 'services' ? 'active' : '' ?>">
            <i class="bi bi-grid"></i>
            Services
        </a>
        <a href="<?= $navRootPath ?>about.php" class="mega-mobile-link <?= $navCurrentPage === 'about' ? 'active' : '' ?>">
            <i class="bi bi-building"></i>
            About
        </a>
        <a href="<?= $navRootPath ?>contact.php" class="mega-mobile-link <?= $navCurrentPage === 'contact' ? 'active' : '' ?>">
            <i class="bi bi-envelope"></i>
            Contact
        </a>
        <a href="<?= $navRootPath ?>troubleshooting.php" class="mega-mobile-link <?= $navCurrentPage === 'troubleshooting' ? 'active' : '' ?>">
            <i class="bi bi-tools"></i>
            Troubleshooting
        </a>
    </div>

    <div class="mega-mobile-section">
        <div class="mega-mobile-section-title">Our Services</div>
        <a href="<?= $navRootPath ?>services.php#installation" class="mega-mobile-link">
            <i class="bi bi-gear-wide-connected"></i>
            Installation
        </a>
        <a href="<?= $navRootPath ?>services.php#repair" class="mega-mobile-link">
            <i class="bi bi-wrench-adjustable-circle"></i>
            Repair
        </a>
        <a href="<?= $navRootPath ?>services.php#maintenance" class="mega-mobile-link">
            <i class="bi bi-shield-check"></i>
            Maintenance
        </a>
    </div>

    <?php if ($navIsLoggedIn): ?>
    <div class="mega-mobile-section">
        <div class="mega-mobile-section-title">Account</div>
        <?php if ($navUserRole === 'customer'): ?>
        <a href="<?= $navRootPath ?>customer/dashboard.php" class="mega-mobile-link">
            <i class="bi bi-speedometer2"></i>
            My Dashboard
        </a>
        <a href="<?= $navRootPath ?>customer/profile.php" class="mega-mobile-link">
            <i class="bi bi-person"></i>
            My Profile
        </a>
        <a href="<?= $navRootPath ?>customer/book-appointment.php" class="mega-mobile-link">
            <i class="bi bi-calendar-plus"></i>
            Book Appointment
        </a>
        <?php elseif ($navUserRole === 'admin'): ?>
        <a href="<?= $navRootPath ?>admin/dashboard.php" class="mega-mobile-link">
            <i class="bi bi-speedometer2"></i>
            Admin Dashboard
        </a>
        <?php endif; ?>
    </div>
    <div class="mega-mobile-actions">
        <a href="<?= $navRootPath ?>auth/logout.php" class="mega-nav-btn mega-nav-btn-outline">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>
    </div>
    <?php else: ?>
    <div class="mega-mobile-actions">
        <a href="<?= $navRootPath ?>auth/login.php" class="mega-nav-btn mega-nav-btn-outline">
            Login
        </a>
        <a href="<?= $navRootPath ?>auth/register.php" class="mega-nav-btn mega-nav-btn-primary">
            Get Started
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Navbar Spacer -->
<div class="mega-navbar-spacer"></div>

<!-- Navbar JavaScript -->
<script>
(function() {
    const navbar = document.getElementById('megaNavbar');
    const mobileNav = document.getElementById('megaMobileNav');
    const toggleIcon = document.getElementById('mobileToggleIcon');
    let isOpen = false;

    // Scroll effect
    window.addEventListener('scroll', function() {
        if (window.scrollY > 20) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Mobile nav toggle
    window.toggleMobileNav = function() {
        isOpen = !isOpen;
        if (isOpen) {
            mobileNav.classList.add('open');
            toggleIcon.className = 'bi bi-x-lg';
            document.body.style.overflow = 'hidden';
        } else {
            mobileNav.classList.remove('open');
            toggleIcon.className = 'bi bi-list';
            document.body.style.overflow = '';
        }
    };

    // Close mobile nav on link click
    document.querySelectorAll('.mega-mobile-link, .mega-mobile-nav .mega-nav-btn').forEach(link => {
        link.addEventListener('click', function() {
            if (isOpen) {
                toggleMobileNav();
            }
        });
    });

    // Close mobile nav on resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024 && isOpen) {
            toggleMobileNav();
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.mega-navbar')) {
            // Dropdowns auto-close via CSS :hover
        }
    });
})();
</script>
