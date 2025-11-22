<script>
// Announcement Bar Dismiss
function dismissAnnouncementBar() {
    const bar = document.getElementById('announcementBar');
    const navbar = document.getElementById('megaNavbar');
    const spacer = document.getElementById('navbarSpacer');
    const mobileNav = document.getElementById('megaMobileNav');
    if (bar) {
        bar.classList.add('dismissed');
        navbar.classList.add('no-announcement');
        spacer.classList.add('no-announcement');
        if (mobileNav) mobileNav.classList.add('no-announcement');
        sessionStorage.setItem('announcementDismissed', 'true');
    }
}

// Check if announcement was previously dismissed
document.addEventListener('DOMContentLoaded', function() {
    const bar = document.getElementById('announcementBar');
    if (bar && sessionStorage.getItem('announcementDismissed') === 'true') {
        dismissAnnouncementBar();
    }
});

// Mobile Nav Toggle
let mobileNavOpen = false;
function toggleMobileNav() {
    mobileNavOpen = !mobileNavOpen;
    const nav = document.getElementById('megaMobileNav');
    const icon = document.getElementById('mobileToggleIcon');
    if (mobileNavOpen) {
        nav.classList.add('open');
        icon.className = 'bi bi-x-lg';
        document.body.style.overflow = 'hidden';
    } else {
        nav.classList.remove('open');
        icon.className = 'bi bi-list';
        document.body.style.overflow = '';
    }
}

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('megaNavbar');
    if (window.scrollY > 20) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Close mobile nav on link click
document.querySelectorAll('.mega-mobile-link, .mega-mobile-nav .mega-nav-btn').forEach(link => {
    link.addEventListener('click', function() {
        if (mobileNavOpen) toggleMobileNav();
    });
});

// Close mobile nav on resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 1024 && mobileNavOpen) toggleMobileNav();
});
</script>