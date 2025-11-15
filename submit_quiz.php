<?php
include(__DIR__ . '/config/session.php');
header('Content-Type: application/json; charset=utf-8');

try {
    include(__DIR__ . '/config/db.php');

    $body = json_decode(file_get_contents('php://input'), true);

    $user_id = $_SESSION['user_id'] ?? ($body['userId'] ?? null);
    $quiz_id = $_SESSION['current_quiz_id'] ?? ($body['quizId'] ?? null);

    if (!$user_id || !$quiz_id) {
        throw new RuntimeException('Không xác định được người dùng hoặc bài thi.');
    }

    $user_id = (int)$user_id;
    $quiz_id = (int)$quiz_id;

    if (!isset($body['answers']) || !is_array($body['answers'])) {
        $answers = [];
    } else {
        $answers = $body['answers'];
    }

    $chk = $conn->prepare("SELECT id FROM results WHERE user_id = ? AND quiz_id = ?");
    $chk->bind_param("ii", $user_id, $quiz_id);
    $chk->execute();
    $resChk = $chk->get_result();
    if ($resChk && $resChk->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã làm bài này rồi.']);
        exit();
    }
    $chk->close();

    $qSql = "SELECT q.id AS qid, a.id AS aid, a.is_correct
             FROM questions q
             JOIN answers a ON q.id = a.question_id
             WHERE q.quiz_id = ?
             ORDER BY q.id";
    $qStmt = $conn->prepare($qSql);
    $qStmt->bind_param("i", $quiz_id);
    $qStmt->execute();
    $qRes = $qStmt->get_result();

    $correctMap = [];
    $questionSet = [];
    while ($r = $qRes->fetch_assoc()) {
        $qid = (int)$r['qid'];
        $aid = (int)$r['aid'];
        $is_correct = (int)$r['is_correct'];
        $questionSet[$qid] = true;
        if ($is_correct) {
            if (!isset($correctMap[$qid])) $correctMap[$qid] = [];
            $correctMap[$qid][] = $aid;
        }
    }
    $qStmt->close();

    $totalQuestions = count($questionSet);
    if ($totalQuestions === 0) throw new RuntimeException('Bài thi chưa có câu hỏi.');

    $correctCount = 0;
    foreach ($answers as $qid => $chosenAid) {
        $qid = (int)$qid;
        $chosenAid = (int)$chosenAid;
        if (isset($correctMap[$qid]) && in_array($chosenAid, $correctMap[$qid], true)) {
            $correctCount++;
        }
    }

    $score = round(($correctCount / $totalQuestions) * 10, 2);

    // Bắt đầu transaction để lưu results + user_answers
    $conn->begin_transaction();

    $insRes = $conn->prepare("INSERT INTO results (user_id, quiz_id, score, submitted_at) VALUES (?, ?, ?, NOW())");
    $insRes->bind_param("iid", $user_id, $quiz_id, $score);
    if (!$insRes->execute()) throw new RuntimeException('Không thể lưu kết quả tổng.');
    $result_id = $insRes->insert_id;
    $insRes->close();

    // Insert user_answers
    if (!empty($answers)) {
        $insAns = $conn->prepare("INSERT INTO user_answers (result_id, question_id, chosen_answer_id) VALUES (?, ?, ?)");
        foreach ($answers as $qid => $chosenAid) {
            $insAns->bind_param("iii", $result_id, $qid, $chosenAid);
            if (!$insAns->execute()) {
                $conn->rollback();
                throw new RuntimeException('Lỗi khi lưu chi tiết câu trả lời.');
            }
        }
        $insAns->close();
    }

    $conn->commit();
    unset($_SESSION['current_quiz_id']);

    echo json_encode([
        'success' => true,
        'correct' => $correctCount,
        'total' => $totalQuestions,
        'score' => $score
    ]);
    exit();
} catch (Throwable $e) {
    if (isset($conn) && $conn->errno) {
        $conn->rollback();
    }
    error_log("submit_quiz.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi trong quá trình xử lý. Vui lòng thử lại.']);
    exit();
}
