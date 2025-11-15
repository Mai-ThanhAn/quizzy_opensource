<?php
session_start();include(__DIR__ . '/../config/session.php');
include('/config/db.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Chưa đăng nhập']);
    exit;
}

$user_id = $_SESSION['user_id'];
$question_id = intval($_POST['question_id']);
$answer_id = intval($_POST['answer_id']);

$stmt = $conn->prepare("SELECT is_correct FROM answers WHERE id=?");
$stmt->bind_param("i", $answer_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$is_correct = $res['is_correct'] ?? 0;

$stmt2 = $conn->prepare("
    REPLACE INTO student_answers (user_id, question_id, answer_id, is_correct)
    VALUES (?, ?, ?, ?)
");
$stmt2->bind_param("iiii", $user_id, $question_id, $answer_id, $is_correct);
$stmt2->execute();

echo json_encode(['success' => true]);
?>
