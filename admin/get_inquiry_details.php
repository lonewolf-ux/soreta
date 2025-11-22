<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    http_response_code(403);
    exit('Unauthorized');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid inquiry ID');
}

$db = new Database();
$pdo = $db->getConnection();

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM contact_inquiries WHERE id = ?");
$stmt->execute([$id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    http_response_code(404);
    exit('Inquiry not found');
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="fw-bold mb-3">Contact Information</h6>
        <div class="mb-2">
            <strong>Name:</strong> <?= htmlspecialchars($inquiry['name']) ?>
        </div>
        <div class="mb-2">
            <strong>Email:</strong>
            <a href="mailto:<?= htmlspecialchars($inquiry['email']) ?>">
                <?= htmlspecialchars($inquiry['email']) ?>
            </a>
        </div>
        <div class="mb-2">
            <strong>Phone:</strong>
            <?php if (!empty($inquiry['phone'])): ?>
                <a href="tel:<?= htmlspecialchars($inquiry['phone']) ?>">
                    <?= htmlspecialchars($inquiry['phone']) ?>
                </a>
            <?php else: ?>
                <span class="text-muted">Not provided</span>
            <?php endif; ?>
        </div>
        <div class="mb-2">
            <strong>Subject:</strong> <?= htmlspecialchars($inquiry['subject']) ?>
        </div>
        <div class="mb-2">
            <strong>Date:</strong> <?= date('F j, Y \a\t g:i A', strtotime($inquiry['created_at'])) ?>
        </div>
    </div>
    <div class="col-md-6">
        <h6 class="fw-bold mb-3">Message</h6>
        <div class="border rounded p-3 bg-light">
            <?= nl2br(htmlspecialchars($inquiry['message'])) ?>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="mailto:<?= htmlspecialchars($inquiry['email']) ?>?subject=Re: <?= urlencode($inquiry['subject']) ?>" class="btn btn-primary me-2">
        <i class="bi bi-reply me-1"></i>Reply via Email
    </a>
    <?php if (!empty($inquiry['phone'])): ?>
        <a href="tel:<?= htmlspecialchars($inquiry['phone']) ?>" class="btn btn-outline-primary me-2">
            <i class="bi bi-telephone me-1"></i>Call
        </a>
    <?php endif; ?>
    <a href="?delete=<?= $inquiry['id'] ?>" class="btn btn-outline-danger"
       onclick="return confirm('Are you sure you want to delete this inquiry?')">
        <i class="bi bi-trash me-1"></i>Delete
    </a>
</div>
