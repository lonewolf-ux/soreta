<?php
require_once '../includes/config.php';
checkAuth();
if (!isAdmin()) redirect('customer/dashboard.php');

session_start(); // Ensure session for messages

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    $_SESSION['message'] = 'Invalid guide ID.';
    $_SESSION['message_type'] = 'danger';
    redirect('admin/troubleshooting.php');
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Check if has children
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM troubleshooting_guide WHERE parent_id = ?');
    $stmt->execute([$id]);
    $childCount = $stmt->fetchColumn();

    // Check if has feedback (use troubleshooting_feedback table if present)
    $feedbackCount = 0;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM troubleshooting_feedback WHERE troubleshooting_guide_id = ?');
        $stmt->execute([$id]);
        $feedbackCount = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Fallback to legacy `feedback` table if troubleshooting_feedback doesn't exist
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE troubleshooting_guide_id = ?');
            $stmt->execute([$id]);
            $feedbackCount = $stmt->fetchColumn();
        } catch (Exception $e2) {
            // ignore, treat as zero
            $feedbackCount = 0;
        }
    }

    if ($childCount > 0) {
        $_SESSION['message'] = 'Cannot delete guide with sub-guides. Please delete or reassign children first.';
        $_SESSION['message_type'] = 'warning';
    } elseif ($feedbackCount > 0) {
        $_SESSION['message'] = 'Cannot delete guide with associated feedback. Please delete feedback first.';
        $_SESSION['message_type'] = 'warning';
    } else {
        // Perform hard delete
        $stmt = $pdo->prepare('DELETE FROM troubleshooting_guide WHERE id = ?');
        $stmt->execute([$id]);
        $debugLine = '[' . date('c') . '] DELETE: id=' . $id . ' ; rowCount=' . $stmt->rowCount() . PHP_EOL;
        try { error_log($debugLine); } catch (Exception $e) {}
        @file_put_contents(__DIR__ . '/debug_troubleshooting.log', $debugLine, FILE_APPEND);
        $_SESSION['message'] = 'Guide deleted successfully.';
        $_SESSION['message_type'] = 'success';
    }
} catch (Exception $e) {
    error_log('Failed to delete troubleshooting entry: ' . $e->getMessage());
    $_SESSION['message'] = 'Failed to delete guide. Please try again.';
    $_SESSION['message_type'] = 'danger';
}

redirect('admin/troubleshooting.php');
