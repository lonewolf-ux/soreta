<?php
require_once '../includes/config.php';
checkAuth();

header('Content-Type: application/json');

if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Generate new token for next submission
$_SESSION['csrf_token'] = CSRFProtection::generateToken();

if (empty($_POST['rating']) || !is_numeric($_POST['rating']) || $_POST['rating'] < 1 || $_POST['rating'] > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    exit;
}

$user_id = $_SESSION['user_id'];
$guide_id = (int)$_POST['troubleshooting_guide_id'];
$rating = (int)$_POST['rating'];
$comment = trim($_POST['comment'] ?? '');
$anonymous = isset($_POST['anonymous']) ? (int)$_POST['anonymous'] : 0;

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $is_edit = isset($_POST['edit_id']) && !empty($_POST['edit_id']);
    $edit_id = $is_edit ? (int)$_POST['edit_id'] : 0;

    if ($is_edit) {
        // Check if user owns this feedback
        $stmt = $pdo->prepare('SELECT id FROM troubleshooting_feedback WHERE id = ? AND user_id = ? AND troubleshooting_guide_id = ?');
        $stmt->execute([$edit_id, $user_id, $guide_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this feedback']);
            exit;
        }

        // Update feedback
        $stmt = $pdo->prepare('
            UPDATE troubleshooting_feedback 
            SET rating = ?, comment = ?, anonymous = ? 
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$rating, $comment, $anonymous, $edit_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Feedback updated successfully']);
    } else {
        // Check if user already rated this guide (prevent new insert if exists)
        $stmt = $pdo->prepare('SELECT id FROM troubleshooting_feedback WHERE user_id = ? AND troubleshooting_guide_id = ?');
        $stmt->execute([$user_id, $guide_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You have already submitted feedback for this guide. Use the edit option to update it.']);
            exit;
        }

            // Insert feedback
        $stmt = $pdo->prepare('
            INSERT INTO troubleshooting_feedback (user_id, troubleshooting_guide_id, rating, comment, anonymous, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$user_id, $guide_id, $rating, $comment, $anonymous]);
        $feedback_id = $pdo->lastInsertId();

        // Create notifications for all admin users
            try {
                // Get guide title
                $guideStmt = $pdo->prepare('SELECT title FROM troubleshooting_guide WHERE id = ?');
                $guideStmt->execute([$guide_id]);
                $guide = $guideStmt->fetch();
                $guide_title = $guide ? $guide['title'] : 'Unknown Guide';

                // Get customer name (or Anonymous)
                $userStmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
                $userStmt->execute([$user_id]);
                $user = $userStmt->fetch();
                $customer_name = $anonymous ? 'Anonymous' : ($user ? $user['name'] : 'Unknown Customer');

                // Get all admin user IDs
                $adminStmt = $pdo->prepare('SELECT id FROM users WHERE role = ?');
                $adminStmt->execute(['admin']);
                $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

                // Use NotificationManager to create notifications for each admin
                $notificationManager = new NotificationManager($pdo);
                foreach ($admins as $admin_id) {
                    $messageText = "Customer {$customer_name} submitted feedback for {$guide_title}. Rating: {$rating} stars.";
                    // preferred: type, message, relatedId, title
                    $notificationManager->create($admin_id, 'feedback_new', $messageText, $feedback_id, 'New Feedback Submitted');
                }
            } catch (Exception $e) {
                error_log('Notification creation error: ' . $e->getMessage());
                // Don't fail the feedback submission if notification fails
            }

        echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
    }
} catch (Exception $e) {
    error_log('Feedback submission error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to submit feedback. Please try again.']);
}
?>
