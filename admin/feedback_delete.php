<?php
require_once '../includes/config.php';
checkAuth();
if (!isAdmin()) redirect('customer/dashboard.php');

session_start(); // Ensure session for messages

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    $_SESSION['message'] = 'Invalid feedback ID.';
    $_SESSION['message_type'] = 'danger';
    redirect('admin/feedback.php');
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Perform hard delete
    $stmt = $pdo->prepare('DELETE FROM troubleshooting_feedback WHERE id = ?');
    $stmt->execute([$id]);
    $_SESSION['message'] = 'Feedback deleted successfully.';
    $_SESSION['message_type'] = 'success';
} catch (Exception $e) {
    error_log('Failed to delete feedback: ' . $e->getMessage());
    $_SESSION['message'] = 'Failed to delete feedback. Please try again.';
    $_SESSION['message_type'] = 'danger';
}

redirect('admin/feedback.php');
?>
