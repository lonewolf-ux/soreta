<style>
/* ========== ANNOUNCEMENT BAR ========== */
.announcement-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1101;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.announcement-bar.dismissed { transform: translateY(-100%); opacity: 0; pointer-events: none; }
.announcement-bar-inner { flex: 1; max-width: 1200px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.announcement-marquee { display: flex; overflow: hidden; width: 100%; }
.announcement-marquee-content {
    display: flex; align-items: center; gap: 2rem; padding-right: 2rem;
    animation: marquee 30s linear infinite; white-space: nowrap;
}
.announcement-marquee:hover .announcement-marquee-content { animation-play-state: paused; }
@keyframes marquee {
    0% { transform: translateX(0); }
    100% { transform: translateX(-100%); }
}
.announcement-item {
    display: inline-flex; align-items: center; gap: 0.4rem;
    text-decoration: none; color: #93c5fd; font-size: 0.85rem; font-weight: 500;
    transition: color 0.2s ease;
}
.announcement-item:hover { color: #bfdbfe; text-decoration: underline; }
.announcement-dot { width: 6px; height: 6px; background: var(--primary); border-radius: 50%; flex-shrink: 0; }
.announcement-separator { color: #475569; font-size: 0.75rem; }
.announcement-static {
    display: inline-flex; align-items: center; gap: 0.5rem;
    text-decoration: none; color: #93c5fd; font-size: 0.875rem; font-weight: 500;
}
.announcement-static:hover { color: #bfdbfe; }
.announcement-static:hover .announcement-text { text-decoration: underline; }
.announcement-badge {
    background: var(--primary); color: white; padding: 0.15rem 0.5rem;
    border-radius: 10px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
}
.announcement-static i { font-size: 1.1rem; transition: transform 0.2s ease; }
.announcement-static:hover i { transform: translateX(2px); }
.announcement-close {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: #94a3b8; cursor: pointer;
    padding: 0.25rem; display: flex; align-items: center; justify-content: center;
    border-radius: 4px; font-size: 1.1rem; transition: all 0.2s ease;
}
.announcement-close:hover { background: rgba(255,255,255,0.1); color: #bfdbfe; }
@media (max-width: 768px) {
    .announcement-bar { padding: 0.4rem 0.75rem; min-height: 36px; }
    .announcement-static, .announcement-item { font-size: 0.8rem; }
    .announcement-marquee-content { gap: 1.5rem; animation-duration: 25s; }
}

/* ========== FLOATING MEGA MENU NAVBAR ========== */
.mega-navbar {
    position: fixed;
    top: 52px;
    left: 1rem;
    right: 1rem;
    z-index: 1100;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.3);
    transition: all 0.25s ease;
}
.mega-navbar.no-announcement { top: 12px; }
.mega-navbar.scrolled {
    background: rgba(15, 23, 42, 0.98);
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}
@media (min-width: 1440px) {
    .mega-navbar {
        left: 50%;
        right: auto;
        transform: translateX(-50%);
        width: calc(100% - 2rem);
        max-width: 1400px;
    }
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
.mega-navbar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
}
.mega-navbar-brand img { 
    height: 40px; 
    width: 40px; 
    border-radius: 50%; 
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.2);
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.mega-navbar-brand-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.mega-navbar-brand-text {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f1f5f9;
    letter-spacing: -0.02em;
}
.mega-navbar-nav {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin: 0;
    padding: 0;
    list-style: none;
}
.mega-navbar-nav > li { position: relative; }
.mega-nav-link {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.625rem 1rem;
    font-size: 0.9375rem;
    font-weight: 500;
    color: #f1f5f9;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.mega-nav-link:hover { color: var(--primary); background: rgba(255,255,255,0.1); }
.mega-nav-link.active { color: var(--primary); }
.mega-nav-link .chevron { font-size: 0.75rem; transition: transform 0.15s ease; opacity: 0.6; }
.mega-navbar-nav > li:hover .mega-nav-link .chevron { transform: rotate(180deg); }
.mega-dropdown {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(10px);
    min-width: 280px;
    background: #1e293b;
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    padding: 1.25rem;
    opacity: 0;
    visibility: hidden;
    transition: all 0.25s ease;
    z-index: 1000;
}
.mega-navbar-nav > li:hover .mega-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}
.mega-dropdown.mega-dropdown-wide {
    min-width: 580px;
    left: 0;
    transform: translateY(10px);
}
.mega-navbar-nav > li:hover .mega-dropdown.mega-dropdown-wide { transform: translateY(0); }
.mega-menu-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
.mega-menu-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.15s ease;
}
.mega-menu-item:hover { background: rgba(255,255,255,0.08); }
.mega-menu-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    transition: transform 0.15s ease;
}
.mega-menu-item:hover .mega-menu-icon { transform: scale(1.05); }
.mega-menu-icon.icon-blue { background: rgba(37,99,235,0.15); color: #60a5fa; }
.mega-menu-icon.icon-green { background: rgba(16,185,129,0.15); color: #34d399; }
.mega-menu-icon.icon-purple { background: rgba(139,92,246,0.15); color: #a78bfa; }
.mega-menu-icon.icon-orange { background: rgba(245,158,11,0.15); color: #fbbf24; }
.mega-menu-content { flex: 1; min-width: 0; }
.mega-menu-title {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #f1f5f9;
    margin-bottom: 0.25rem;
}
.mega-menu-desc { font-size: 0.8125rem; color: #94a3b8; line-height: 1.5; }
.mega-menu-section { padding: 0.75rem 0; }
.mega-menu-section:first-child { padding-top: 0; }
.mega-menu-section:last-child { padding-bottom: 0; }
.mega-menu-section + .mega-menu-section { border-top: 1px solid rgba(255,255,255,0.1); }
.mega-navbar-actions { display: flex; align-items: center; gap: 0.75rem; }
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
    transition: all 0.15s ease;
    white-space: nowrap;
}
.mega-nav-btn-ghost { color: #f1f5f9; background: transparent; border: none; }
.mega-nav-btn-ghost:hover { color: var(--primary); background: rgba(255,255,255,0.1); }
.mega-nav-btn-primary {
    color: #ffffff;
    background: var(--primary);
    border: none;
    box-shadow: 0 2px 8px rgba(37,99,235,0.25);
}
.mega-nav-btn-primary:hover {
    background: color-mix(in srgb, var(--primary) 90%, #000);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37,99,235,0.35);
    color: #ffffff;
}
.mega-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.15s ease;
    text-transform: uppercase;
}
.mega-user-avatar:hover { border-color: var(--primary); transform: scale(1.05); }
.mega-user-wrapper { position: relative; }
.mega-user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 240px;
    background: #1e293b;
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.25s ease;
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
    background: linear-gradient(135deg, rgba(37,99,235,0.15), rgba(124,58,237,0.05));
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.mega-user-name { font-weight: 700; color: #f1f5f9; font-size: 1rem; margin-bottom: 0.25rem; }
.mega-user-email { font-size: 0.8125rem; color: #94a3b8; }
.mega-user-menu { padding: 0.5rem; }
.mega-user-menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    font-size: 0.9375rem;
    font-weight: 500;
    color: #f1f5f9;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.15s ease;
}
.mega-user-menu-item:hover { background: rgba(255,255,255,0.08); color: var(--primary); }
.mega-user-menu-item i { font-size: 1.125rem; color: #94a3b8; width: 20px; text-align: center; }
.mega-user-menu-item:hover i { color: var(--primary); }
.mega-user-menu-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 0.5rem 0; }
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
    color: #f1f5f9;
    transition: all 0.15s ease;
}
.mega-mobile-toggle:hover { background: rgba(255,255,255,0.1); }
.mega-mobile-toggle i { font-size: 1.5rem; }
.mega-mobile-nav {
    display: none;
    position: fixed;
    top: 136px;
    left: 0;
    right: 0;
    bottom: 0;
    background: #0f172a;
    z-index: 1099;
    overflow-y: auto;
    padding: 1rem;
    transform: translateX(100%);
    transition: transform 0.25s ease;
}
.mega-mobile-nav.no-announcement { top: 96px; }
.mega-mobile-nav.open { transform: translateX(0); }
.mega-mobile-section { padding: 1rem 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
.mega-mobile-section:last-child { border-bottom: none; }
.mega-mobile-section-title {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
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
    color: #f1f5f9;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.15s ease;
}
.mega-mobile-link:hover, .mega-mobile-link.active { color: var(--primary); background: rgba(255,255,255,0.08); }
.mega-mobile-link i { font-size: 1.25rem; width: 24px; text-align: center; color: #94a3b8; }
.mega-mobile-link:hover i, .mega-mobile-link.active i { color: var(--primary); }
.mega-mobile-actions { display: flex; flex-direction: column; gap: 0.75rem; padding-top: 1rem; }
.mega-mobile-actions .mega-nav-btn { justify-content: center; padding: 0.875rem 1.5rem; }
.mega-nav-btn-outline { color: #f1f5f9; background: transparent; border: 1.5px solid rgba(255,255,255,0.2); }
.mega-nav-btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.mega-navbar-spacer { height: 136px; }
.mega-navbar-spacer.no-announcement { height: 96px; }
@media (max-width: 1024px) {
    .mega-navbar { left: 0.75rem; right: 0.75rem; }
    .mega-navbar-nav { display: none; }
    .mega-mobile-toggle { display: flex; }
    .mega-mobile-nav { display: block; }
    .mega-navbar-actions .mega-nav-btn-ghost { display: none; }
}
@media (max-width: 640px) {
    .mega-navbar { left: 0.5rem; right: 0.5rem; top: 48px; border-radius: 12px; }
    .mega-navbar.no-announcement { top: 8px; }
    .mega-navbar-inner { padding: 0 1rem; height: 56px; }
    .mega-navbar-brand-text { font-size: 1.125rem; }
    .mega-navbar-spacer { height: 116px; }
    .mega-navbar-spacer.no-announcement { height: 76px; }
    .mega-mobile-nav { top: 116px; }
    .mega-mobile-nav.no-announcement { top: 76px; }
}
</style>