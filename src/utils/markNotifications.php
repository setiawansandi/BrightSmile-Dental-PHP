<?php
require_once __DIR__ . '/bootstrap.php'; // session + db + redirect

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($_SESSION['user_id'])) {
        $response['message'] = 'User not logged in.';
        echo json_encode($response);
        exit;
    }

    $current_user_id = $_SESSION['user_id'];
    $notification_id = $data['notification_id'] ?? null;

    if ($notification_id === null) {
        $response['message'] = 'Notification ID is required.';
        echo json_encode($response);
        exit;
    }

    $conn = db();

    // Mark the specific notification as read for the current user
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ? AND recipient_id = ? AND is_read = 0
    ");
    $stmt->bind_param('ii', $notification_id, $current_user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Notification marked as read.';
        } else {
            $response['message'] = 'Notification not found, already read, or not yours.';
        }
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }

    $stmt->close();
    $conn->close();

} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);