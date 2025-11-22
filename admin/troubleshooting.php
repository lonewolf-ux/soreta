<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    redirect('customer/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();

// Fetch all troubleshooting entries with parent relationship
$stmt = $pdo->query("
    SELECT tg.*, 
           parent.title as parent_title,
           (SELECT COUNT(*) FROM troubleshooting_guide WHERE parent_id = tg.id) as children_count
    FROM troubleshooting_guide tg
    LEFT JOIN troubleshooting_guide parent ON tg.parent_id = parent.id
    ORDER BY tg.parent_id IS NULL DESC, tg.display_order ASC, tg.created_at DESC
");
$allEntries = $stmt->fetchAll();

// Organize into tree structure
$categories = [];
$children = [];

foreach ($allEntries as $entry) {
    if ($entry['parent_id'] === null) {
        $categories[] = $entry;
    } else {
        if (!isset($children[$entry['parent_id']])) {
            $children[$entry['parent_id']] = [];
        }
        $children[$entry['parent_id']][] = $entry;
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Troubleshooting Guide - Soreta Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/main.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/admin.css" rel="stylesheet">
    <style>
        /* Theme Variables */
        :root[data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f0f2f5;
            --bg-tertiary: #e4e6eb;
            --text-primary: #050505;
            --text-secondary: #65676b;
            --text-tertiary: #8a8d91;
            --border-color: #e4e6eb;
            --hover-overlay: rgba(0, 0, 0, 0.05);
            --card-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            --primary-blue: #1877f2;
        }

        :root[data-theme="dark"] {
            --bg-primary: #242526;
            --bg-secondary: #18191a;
            --bg-tertiary: #3a3b3c;
            --text-primary: #e4e6eb;
            --text-secondary: #b0b3b8;
            --text-tertiary: #8a8d91;
            --border-color: #3a3b3c;
            --hover-overlay: rgba(255, 255, 255, 0.1);
            --card-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            --primary-blue: #2d88ff;
        }

        * {
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        body {
            background: var(--bg-secondary);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .admin-main {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }

        .admin-sidebar.collapsed ~ .admin-main {
            margin-left: 80px;
        }

        .admin-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-content {
            padding: 1.5rem;
        }

        .card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }

        .card-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.25rem;
        }

        .card-title {
            color: var(--text-primary);
            margin: 0;
        }

        /* Tree Structure Styles */
        .tree-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .tree-item-header {
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .tree-item-header:hover {
            background: var(--hover-bg) !important;
        }

        .tree-item-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .tree-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .tree-item-info h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .tree-item-info p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .tree-item-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .tree-expand-icon {
            color: var(--text-secondary);
            transition: transform 0.2s;
        }

        .tree-expand-icon.expanded {
            transform: rotate(90deg);
        }

        .tree-children {
            padding: 0 1.25rem 1rem 4rem;
            display: none;
            border-top: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .tree-children.show {
            display: block;
        }

        .tree-child-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.75rem 1rem;
            margin-top: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tree-child-item:hover {
            background: var(--hover-bg) !important;
        }

        .tree-child-icon {
            width: 6px;
            height: 6px;
            background: var(--primary-color);
            border-radius: 50%;
            margin-right: 0.75rem;
        }

        .tree-child-title {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .tree-child-title h6 {
            margin: 0;
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .tree-child-title small {
            color: var(--text-secondary);
        }

        .theme-toggle {
            background: var(--bg-tertiary);
            border: none;
            color: var(--text-primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .theme-toggle:hover {
            background: var(--hover-bg);
        }

        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
            }

            .admin-header {
                padding: 1rem;
            }

            .admin-content {
                padding: 1rem;
            }

            .tree-children {
                padding-left: 2rem;
            }

            .tree-item-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'layout/sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left d-flex align-items-center gap-3">
                    <button class="btn btn-sm btn-outline-secondary d-md-none" id="mobileMenuToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h3 mb-0">Troubleshooting Guide</h1>
                </div>
                <div class="header-right d-flex align-items-center gap-2">
                    <button class="theme-toggle" id="themeToggle" title="Toggle theme">
                        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                    </button>
                    <?php include_once '../includes/notification-component.php'; ?>
                </div>
            </header>

            <div class="admin-content">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Decision Tree Structure</h5>
                            <small style="color: var(--text-secondary);">Organize troubleshooting guides in a hierarchical decision tree</small>
                        </div>
                        <a href="troubleshooting_edit.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Category
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <div class="empty-state">
                                <i class="bi bi-diagram-3"></i>
                                <h5>No troubleshooting categories yet</h5>
                                <p>Create top-level categories to start building your decision tree</p>
                                <a href="troubleshooting_edit.php" class="btn btn-primary mt-3">
                                    <i class="bi bi-plus-circle"></i> Create First Category
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <div class="tree-item">
                                    <div class="tree-item-header" onclick="toggleChildren(this)">
                                        <div class="tree-item-title">
                                            <div class="tree-icon">
                                                <i class="bi bi-folder-fill"></i>
                                            </div>
                                            <div class="tree-item-info">
                                                <h5><?= htmlspecialchars($category['title']) ?></h5>
                                                <p><?= htmlspecialchars(substr($category['description'], 0, 100)) ?><?= strlen($category['description']) > 100 ? '...' : '' ?></p>
                                            </div>
                                        </div>
                                        <div class="tree-item-actions">
                                            <span class="badge bg-<?= $category['is_active'] ? 'success' : 'secondary' ?>">
                                                <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                            <?php if ($category['children_count'] > 0): ?>
                                                <span class="badge bg-primary"><?= $category['children_count'] ?> sub-items</span>
                                            <?php endif; ?>
                                            <a href="troubleshooting_edit.php?id=<?= $category['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               onclick="event.stopPropagation()">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                                                                                    <a href="troubleshooting_edit.php?parent_id=<?= $category['id'] ?>" 
                                               class="btn btn-sm btn-outline-success"
                                               onclick="event.stopPropagation()">
                                                <i class="bi bi-plus"></i> Add Sub-item
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="event.stopPropagation(); confirmDelete(<?= $category['id'] ?>, '<?= htmlspecialchars(addslashes($category['title'])) ?>', <?= $category['children_count'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php if ($category['children_count'] > 0): ?>
                                                <i class="bi bi-chevron-right tree-expand-icon"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($children[$category['id']]) && !empty($children[$category['id']])): ?>
                                        <div class="tree-children">
                                            <?php foreach ($children[$category['id']] as $child): ?>
                                                <div class="tree-child-item">
                                                    <div class="tree-child-title">
                                                        <div class="tree-child-icon"></div>
                                                        <div>
                                                            <h6><?= htmlspecialchars($child['title']) ?></h6>
                                                            <small><?= htmlspecialchars(substr($child['description'], 0, 80)) ?><?= strlen($child['description']) > 80 ? '...' : '' ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-2 align-items-center">
                                                        <span class="badge bg-<?= $child['is_active'] ? 'success' : 'secondary' ?>">
                                                            <?= $child['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                        <?php if (!empty(trim($child['fix_steps']))): ?>
                                                            <span class="badge bg-info">Has Solution</span>
                                                        <?php endif; ?>
                                                        <a href="troubleshooting_edit.php?id=<?= $child['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                                onclick="confirmDelete(<?= $child['id'] ?>, '<?= htmlspecialchars(addslashes($child['title'])) ?>', 0)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Decision Tree Explanation -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-info-circle"></i> How Decision Trees Work</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <i class="bi bi-folder-fill" style="font-size: 2rem; color: var(--primary-blue);"></i>
                                    <h6 class="mt-2">1. Categories</h6>
                                    <p class="small" style="color: var(--text-secondary);">Top-level categories (e.g., "Power Issues", "Display Problems")</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <i class="bi bi-diagram-3" style="font-size: 2rem; color: var(--primary-blue);"></i>
                                    <h6 class="mt-2">2. Sub-items</h6>
                                    <p class="small" style="color: var(--text-secondary);">Specific problems under each category (e.g., "Won't Turn On", "Screen Flickering")</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <i class="bi bi-list-check" style="font-size: 2rem; color: var(--primary-blue);"></i>
                                    <h6 class="mt-2">3. Solutions</h6>
                                    <p class="small" style="color: var(--text-secondary);">Step-by-step fix instructions for each specific problem</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                        Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteMessage"></p>
                    <div class="alert alert-warning" id="childrenWarning" style="display: none;">
                        <i class="bi bi-info-circle"></i>
                        <strong>Warning:</strong> This category has sub-items. You must delete or move them first.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }

        // Delete confirmation
        function confirmDelete(id, title, childrenCount) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const messageEl = document.getElementById('deleteMessage');
            const warningEl = document.getElementById('childrenWarning');
            const confirmBtn = document.getElementById('confirmDeleteBtn');

            if (childrenCount > 0) {
                messageEl.innerHTML = `Are you sure you want to delete <strong>"${title}"</strong>?`;
                warningEl.style.display = 'block';
                confirmBtn.style.display = 'none';
            } else {
                messageEl.innerHTML = `Are you sure you want to delete <strong>"${title}"</strong>? This action cannot be undone.`;
                warningEl.style.display = 'none';
                confirmBtn.style.display = 'inline-block';
                confirmBtn.href = `troubleshooting_delete.php?id=${id}`;
            }

            modal.show();
        }

        // Toggle tree children
        function toggleChildren(header) {
            const treeItem = header.closest('.tree-item');
            const children = treeItem.querySelector('.tree-children');
            const expandIcon = header.querySelector('.tree-expand-icon');
            
            if (children) {
                children.classList.toggle('show');
                if (expandIcon) {
                    expandIcon.classList.toggle('expanded');
                }
            }
        }

        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('mobile-open');
                }
            });
        }
    </script>
</body>
</html>