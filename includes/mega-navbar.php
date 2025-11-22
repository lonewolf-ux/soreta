<?php
// mega-navbar.php - Unified navbar component for all pages

// Check if announcements should be displayed
$showAnnouncements = isset($announcements) && !empty($announcements);
?>

<!-- ANNOUNCEMENT BAR (only on index.php) -->
<?php if ($showAnnouncements): 
    $latestAnnouncement = $announcements[0];
    $enableMarquee = count($announcements) > 1;
?>
<div class="announcement-bar" id="announcementBar">
    <div class="announcement-bar-inner">
        <?php if ($enableMarquee): ?>
        <div class="announcement-marquee">
            <div class="announcement-marquee-content">
                <?php foreach ($announcements as $idx => $ann): ?>
                    <a href="<?= ROOT_PATH ?>announcements.php?id=<?= $ann['id'] ?>" class="announcement-item">
                        <span class="announcement-dot"></span>
                        <span class="announcement-text"><?= htmlspecialchars($ann['title']) ?></span>
                    </a>
                    <?php if ($idx < count($announcements) - 1): ?>
                        <span class="announcement-separator">•</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="announcement-marquee-content" aria-hidden="true">
                <?php foreach ($announcements as $idx => $ann): ?>
                    <a href="<?= ROOT_PATH ?>announcements.php?id=<?= $ann['id'] ?>" class="announcement-item">
                        <span class="announcement-dot"></span>
                        <span class="announcement-text"><?= htmlspecialchars($ann['title']) ?></span>
                    </a>
                    <?php if ($idx < count($announcements) - 1): ?>
                        <span class="announcement-separator">•</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <a href="<?= ROOT_PATH ?>announcements.php?id=<?= $latestAnnouncement['id'] ?>" class="announcement-static">
            <span class="announcement-badge">New</span>
            <span class="announcement-text"><?= htmlspecialchars($latestAnnouncement['title']) ?></span>
            <i class="bi bi-arrow-right-short"></i>
        </a>
        <?php endif; ?>
    </div>
    <button class="announcement-close" onclick="dismissAnnouncementBar()" aria-label="Dismiss">
        <i class="bi bi-x"></i>
    </button>
</div>
<?php endif; ?>

<!-- MEGA MENU NAVBAR -->
<nav class="mega-navbar <?= !$showAnnouncements ? 'no-announcement' : '' ?>" id="megaNavbar">
    <div class="mega-navbar-inner">
        <a href="<?= ROOT_PATH ?>index.php" class="mega-navbar-brand">
            <?php $logo = $settings['site_logo'] ?? '';
            if (!empty($logo) && is_string($logo)): ?>
                <img src="<?= htmlspecialchars($logo[0] === '/' ? $logo : ROOT_PATH . $logo) ?>" alt="<?= htmlspecialchars($settings['company_name'] ?? 'Soreta') ?>">
            <?php else: ?>
                <div class="mega-navbar-brand-icon"><i class="bi bi-lightning-charge-fill"></i></div>
            <?php endif; ?>
            <span class="mega-navbar-brand-text"><?= htmlspecialchars($settings['company_name'] ?? 'Soreta Electronics') ?></span>
        </a>

        <ul class="mega-navbar-nav">
            <li><a href="<?= ROOT_PATH ?>index.php" class="mega-nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Home</a></li>
            <li>
                <a href="<?= ROOT_PATH ?>services.php" class="mega-nav-link <?= basename($_SERVER['PHP_SELF']) === 'services.php' ? 'active' : '' ?>">Services <i class="bi bi-chevron-down chevron"></i></a>
                <div class="mega-dropdown mega-dropdown-wide">
                    <div class="mega-menu-grid">
                        <a href="<?= ROOT_PATH ?>services.php#installation" class="mega-menu-item">
                            <div class="mega-menu-icon icon-blue"><i class="bi bi-gear-wide-connected"></i></div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">Installation</div>
                                <div class="mega-menu-desc">Professional setup for automation & security systems</div>
                            </div>
                        </a>
                        <a href="<?= ROOT_PATH ?>services.php#repair" class="mega-menu-item">
                            <div class="mega-menu-icon icon-orange"><i class="bi bi-wrench-adjustable-circle"></i></div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">Repair</div>
                                <div class="mega-menu-desc">Fast diagnosis & component-level repair</div>
                            </div>
                        </a>
                        <a href="<?= ROOT_PATH ?>services.php#maintenance" class="mega-menu-item">
                            <div class="mega-menu-icon icon-green"><i class="bi bi-shield-check"></i></div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">Maintenance</div>
                                <div class="mega-menu-desc">Preventive care & system optimization</div>
                            </div>
                        </a>
                        <a href="<?= ROOT_PATH ?>troubleshooting.php" class="mega-menu-item">
                            <div class="mega-menu-icon icon-purple"><i class="bi bi-tools"></i></div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">DIY Troubleshooting</div>
                                <div class="mega-menu-desc">Step-by-step guides for common issues</div>
                            </div>
                        </a>
                    </div>
                    <div class="mega-menu-section">
                        <a href="<?= $isLoggedIn && $userRole === 'customer' ? ROOT_PATH . 'customer/book-appointment.php' : ROOT_PATH . 'auth/register.php' ?>" class="mega-menu-item" style="background: rgba(37,99,235,0.06);">
                            <div class="mega-menu-icon icon-blue"><i class="bi bi-calendar-plus"></i></div>
                            <div class="mega-menu-content">
                                <div class="mega-menu-title">Book Appointment <i class="bi bi-arrow-right" style="font-size:0.75rem;"></i></div>
                                <div class="mega-menu-desc">Schedule a service with our expert technicians</div>
                            </div>
                        </a>
                    </div>
                </div>
            </li>
            <li><a href="<?= ROOT_PATH ?>about.php" class="mega-nav-link <?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>">About</a></li>
            <li><a href="<?= ROOT_PATH ?>contact.php" class="mega-nav-link <?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>">Contact</a></li>
            <li><a href="<?= ROOT_PATH ?>troubleshooting.php" class="mega-nav-link <?= basename($_SERVER['PHP_SELF']) === 'troubleshooting.php' ? 'active' : '' ?>">Troubleshooting</a></li>
            <?php if ($isLoggedIn && $userRole === 'customer'): ?>
                <li><a href="<?= ROOT_PATH ?>customer/dashboard.php" class="mega-nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
            <?php elseif ($isLoggedIn && $userRole === 'admin'): ?>
                <li><a href="<?= ROOT_PATH ?>admin/dashboard.php" class="mega-nav-link">Admin</a></li>
            <?php endif; ?>
        </ul>

        <div class="mega-navbar-actions">
            <?php if ($isLoggedIn): ?>
                <!-- Notification Bell (only for logged-in users) -->
                <?php include(dirname(__DIR__) . '/includes/notification-bell.php'); ?>
                
                <div class="mega-user-wrapper">
                    <div class="mega-user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 2)) ?></div>
                    <div class="mega-user-dropdown">
                        <div class="mega-user-header">
                            <div class="mega-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                            <div class="mega-user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                        </div>
                        <div class="mega-user-menu">
                            <?php if ($userRole === 'customer'): ?>
                                <a href="<?= ROOT_PATH ?>customer/dashboard.php" class="mega-user-menu-item"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
                                <a href="<?= ROOT_PATH ?>customer/profile.php" class="mega-user-menu-item"><i class="bi bi-person"></i><span>My Profile</span></a>
                                <a href="<?= ROOT_PATH ?>customer/book-appointment.php" class="mega-user-menu-item"><i class="bi bi-calendar-plus"></i><span>Book Appointment</span></a>
                            <?php elseif ($userRole === 'admin'): ?>
                                <a href="<?= ROOT_PATH ?>admin/dashboard.php" class="mega-user-menu-item"><i class="bi bi-speedometer2"></i><span>Admin Dashboard</span></a>
                            <?php endif; ?>
                            <div class="mega-user-menu-divider"></div>
                            <a href="<?= ROOT_PATH ?>auth/logout.php" class="mega-user-menu-item"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= ROOT_PATH ?>auth/login.php" class="mega-nav-btn mega-nav-btn-ghost">Login</a>
                <a href="<?= ROOT_PATH ?>auth/register.php" class="mega-nav-btn mega-nav-btn-primary">Get Started</a>
            <?php endif; ?>
            <button class="mega-mobile-toggle" type="button" onclick="toggleMobileNav()">
                <i class="bi bi-list" id="mobileToggleIcon"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Navigation -->
<div class="mega-mobile-nav <?= !$showAnnouncements ? 'no-announcement' : '' ?>" id="megaMobileNav">
    <div class="mega-mobile-section">
        <div class="mega-mobile-section-title">Navigation</div>
        <a href="<?= ROOT_PATH ?>index.php" class="mega-mobile-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>"><i class="bi bi-house"></i>Home</a>
        <a href="<?= ROOT_PATH ?>services.php" class="mega-mobile-link <?= basename($_SERVER['PHP_SELF']) === 'services.php' ? 'active' : '' ?>"><i class="bi bi-grid"></i>Services</a>
        <a href="<?= ROOT_PATH ?>about.php" class="mega-mobile-link <?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>"><i class="bi bi-building"></i>About</a>
        <a href="<?= ROOT_PATH ?>contact.php" class="mega-mobile-link <?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>"><i class="bi bi-envelope"></i>Contact</a>
        <a href="<?= ROOT_PATH ?>troubleshooting.php" class="mega-mobile-link <?= basename($_SERVER['PHP_SELF']) === 'troubleshooting.php' ? 'active' : '' ?>"><i class="bi bi-tools"></i>Troubleshooting</a>
    </div>
    <div class="mega-mobile-section">
        <div class="mega-mobile-section-title">Our Services</div>
        <a href="<?= ROOT_PATH ?>services.php#installation" class="mega-mobile-link"><i class="bi bi-gear-wide-connected"></i>Installation</a>
        <a href="<?= ROOT_PATH ?>services.php#repair" class="mega-mobile-link"><i class="bi bi-wrench-adjustable-circle"></i>Repair</a>
        <a href="<?= ROOT_PATH ?>services.php#maintenance" class="mega-mobile-link"><i class="bi bi-shield-check"></i>Maintenance</a>
    </div>
    <?php if ($isLoggedIn): ?>
        <div class="mega-mobile-section">
            <div class="mega-mobile-section-title">Account</div>
            <?php if ($userRole === 'customer'): ?>
                <a href="<?= ROOT_PATH ?>customer/dashboard.php" class="mega-mobile-link"><i class="bi bi-speedometer2"></i>My Dashboard</a>
                <a href="<?= ROOT_PATH ?>customer/book-appointment.php" class="mega-mobile-link"><i class="bi bi-calendar-plus"></i>Book Appointment</a>
            <?php elseif ($userRole === 'admin'): ?>
                <a href="<?= ROOT_PATH ?>admin/dashboard.php" class="mega-mobile-link"><i class="bi bi-speedometer2"></i>Admin Dashboard</a>
            <?php endif; ?>
        </div>
        <div class="mega-mobile-actions">
            <a href="<?= ROOT_PATH ?>auth/logout.php" class="mega-nav-btn mega-nav-btn-outline"><i class="bi bi-box-arrow-right"></i>Logout</a>
        </div>
    <?php else: ?>
        <div class="mega-mobile-actions">
            <a href="<?= ROOT_PATH ?>auth/login.php" class="mega-nav-btn mega-nav-btn-outline">Login</a>
            <a href="<?= ROOT_PATH ?>auth/register.php" class="mega-nav-btn mega-nav-btn-primary">Get Started</a>
        </div>
    <?php endif; ?>
</div>

<div class="mega-navbar-spacer <?= !$showAnnouncements ? 'no-announcement' : '' ?>" id="navbarSpacer"></div>