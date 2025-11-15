<?php
include(__DIR__ . '/config/session.php');
include(__DIR__ . '/config/db.php');

$review_data = [];
$quiz_title = "Xem Lại Bài Làm";
$final_score = 0;
$error_message = null;

try {
    // 1. Kiểm tra đăng nhập và lấy result_id từ URL
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit();
    }
    $user_id = (int)$_SESSION['user_id'];

    $result_id = filter_input(INPUT_GET, 'result_id', FILTER_VALIDATE_INT);
    if (!$result_id) {
        throw new Exception("ID kết quả không hợp lệ.");
    }

    // 2. Xác thực quyền sở hữu và quyền xem lại
    $auth_sql = "SELECT r.quiz_id, q.title, q.can_review, r.score 
                 FROM results r 
                 JOIN quizzes q ON r.quiz_id = q.id 
                 WHERE r.id = ? AND r.user_id = ?";
    $auth_stmt = $conn->prepare($auth_sql);
    $auth_stmt->bind_param("ii", $result_id, $user_id);
    $auth_stmt->execute();
    $auth_res = $auth_stmt->get_result();
    $quiz_info = $auth_res->fetch_assoc();
    $auth_stmt->close();

    if (!$quiz_info) {
        throw new Exception("Không tìm thấy kết quả hoặc bạn không có quyền xem kết quả này.");
    }
    if ($quiz_info['can_review'] != 1) {
        throw new Exception("Bài kiểm tra này không cho phép xem lại kết quả.");
    }

    $quiz_id = (int)$quiz_info['quiz_id'];
    $quiz_title = $quiz_info['title'];
    $final_score = $quiz_info['score'];

    // 3. Lấy tất cả dữ liệu cần thiết trong một câu truy vấn
    $data_sql = "
        SELECT 
            q.id AS question_id,
            q.question_text,
            a.id AS answer_id,
            a.answer_text,
            a.is_correct,
            ua.chosen_answer_id
        FROM questions q
        JOIN answers a ON q.id = a.question_id
        LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.result_id = ?
        WHERE q.quiz_id = ?
        ORDER BY q.id, a.id;
    ";
    $data_stmt = $conn->prepare($data_sql);
    $data_stmt->bind_param("ii", $result_id, $quiz_id);
    $data_stmt->execute();
    $data_res = $data_stmt->get_result();
    
    // 4. Cấu trúc lại dữ liệu để dễ dàng hiển thị
    while($row = $data_res->fetch_assoc()) {
        $qid = $row['question_id'];
        if (!isset($review_data[$qid])) {
            $review_data[$qid] = [
                'question_text' => $row['question_text'],
                'user_choice' => $row['chosen_answer_id'],
                'options' => []
            ];
        }
        $review_data[$qid]['options'][] = [
            'id' => $row['answer_id'],
            'text' => $row['answer_text'],
            'is_correct' => $row['is_correct']
        ];
    }
    $data_stmt->close();
    $conn->close();

} catch (Throwable $e) {
    error_log("review.php error: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xem Lại: <?= htmlspecialchars($quiz_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Be Vietnam Pro', sans-serif; }
    </style>
</head>
<body class="bg-slate-50">

<div class="container mx-auto max-w-4xl p-4 md:p-8">
    
    <?php if ($error_message): ?>
    </br>
    </br>
    </br>
        <div class="bg-white p-8 rounded-2xl shadow-2xl text-center">
            <i class="bi bi-exclamation-triangle-fill text-5xl text-red-500 mb-4"></i>
            <h2 class="text-2xl font-bold text-red-600">Đã xảy ra lỗi</h2>
            <p class="text-slate-600 mt-2"><?= htmlspecialchars($error_message) ?></p>
            <a href="/index.php" class="mt-6 inline-block py-2 px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                Quay về trang chủ
            </a>
        </div>
    <?php else: ?>
        <!-- Header -->
        <header class="bg-white p-6 rounded-2xl shadow-lg mb-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <a href="/index.php" class="text-sm text-blue-600 hover:underline mb-2 block"><i class="bi bi-arrow-left"></i> Quay lại trang chủ</a>
                    <h1 class="text-3xl font-bold text-slate-800"><?= htmlspecialchars($quiz_title) ?></h1>
                </div>
                <div class="text-center">
                    <p class="text-slate-600">Điểm số của bạn</p>
                    <p class="text-4xl font-bold text-blue-600"><?= htmlspecialchars($final_score) ?>/10</p>
                </div>
            </div>
        </header>

        <!-- Chú thích màu sắc -->
        <div class="flex flex-wrap gap-x-6 gap-y-2 mb-6 text-sm text-slate-600 bg-white p-4 rounded-lg shadow-md">
            <div class="flex items-center gap-2"><div class="w-4 h-4 rounded-full bg-green-200 border border-green-400"></div> Đáp án đúng</div>
            <div class="flex items-center gap-2"><div class="w-4 h-4 rounded-full bg-red-200 border border-red-400"></div> Lựa chọn sai của bạn</div>
            <div class="flex items-center gap-2"><div class="w-4 h-4 rounded-full bg-yellow-200 border border-yellow-400"></div> Đáp án đúng (Câu bạn chưa chọn đáp án)</div>
        </div>

        <!-- Danh sách câu hỏi và câu trả lời -->
        <div class="space-y-6">
            <?php foreach ($review_data as $index => $data): ?>
                <div class="bg-white p-6 rounded-2xl shadow-lg">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">
                        <strong>Câu <?= array_search($index, array_keys($review_data)) + 1 ?>:</strong>
                        <?= htmlspecialchars($data['question_text']) ?>
                    </h2>
                    <div class="flex flex-col gap-3">
                        <?php foreach ($data['options'] as $i => $option): 
                            $optionChar = chr(65 + $i);
                            $user_choice_id = (int)$data['user_choice'];
                            $option_id = (int)$option['id'];
                            
                            $class = 'border-slate-300 bg-white'; // Mặc định
                            $icon = '';

                            $is_user_choice = ($user_choice_id === $option_id);
                            $is_correct_answer = ($option['is_correct'] == 1);
                            $not_answered = ($user_choice_id === 0 || $user_choice_id === null);

                            if ($is_correct_answer) {
                                if ($not_answered) {
                                    // Bỏ qua câu hỏi, tô màu vàng cho đáp án đúng
                                    $class = 'border-yellow-500 bg-yellow-50 text-yellow-900 ring-2 ring-yellow-300';
                                    $icon = '<i class="bi bi-lightbulb-fill text-yellow-500"></i>';
                                } else {
                                    // Bất kể chọn đúng hay sai, đáp án đúng luôn màu xanh
                                    $class = 'border-green-500 bg-green-50 text-green-900 ring-2 ring-green-300';
                                    $icon = '<i class="bi bi-check-circle-fill text-green-500"></i>';
                                }
                            } elseif ($is_user_choice) { 
                                // Đây là lựa chọn sai của người dùng
                                $class = 'border-red-500 bg-red-50 text-red-900 ring-2 ring-red-300';
                                $icon = '<i class="bi bi-x-circle-fill text-red-500"></i>';
                            }
                        ?>
                            <div class="w-full text-left p-4 border rounded-lg flex items-start gap-4 <?= $class ?>">
                                <div class="flex-shrink-0 font-bold">
                                    <?= $optionChar ?>.
                                </div>
                                <div class="flex-grow"><?= htmlspecialchars($option['text']) ?></div>
                                <div class="flex-shrink-0 text-lg"><?= $icon ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>