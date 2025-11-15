<?php
include(__DIR__ . '/../config/session.php');include(__DIR__ . '/../config/db.php');

$review_data = [];
$quiz_title = "Xem Lại Bài Làm";
$student_name = "Không rõ";
$final_score = 0;
$error_message = null;

try {
    // 1. Kiểm tra quyền Giảng viên (admin)
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception("Bạn không có quyền truy cập trang này.");
    }
    $admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';

    $result_id = filter_input(INPUT_GET, 'result_id', FILTER_VALIDATE_INT);
    if (!$result_id) {
        throw new Exception("ID kết quả không hợp lệ.");
    }

    // 2. Lấy thông tin cơ bản về bài làm (không cần kiểm tra quyền sở hữu)
    $info_sql = "SELECT r.quiz_id, q.title, r.score, u.fullname 
                 FROM results r 
                 JOIN quizzes q ON r.quiz_id = q.id 
                 JOIN users u ON r.user_id = u.id
                 WHERE r.id = ?";
    $info_stmt = $conn->prepare($info_sql);
    $info_stmt->bind_param("i", $result_id);
    $info_stmt->execute();
    $info_res = $info_stmt->get_result();
    $quiz_info = $info_res->fetch_assoc();
    $info_stmt->close();

    if (!$quiz_info) {
        throw new Exception("Không tìm thấy kết quả bài làm.");
    }

    $quiz_id = (int)$quiz_info['quiz_id'];
    $quiz_title = $quiz_info['title'];
    $student_name = $quiz_info['fullname'];
    $final_score = $quiz_info['score'];

    // 3. Lấy chi tiết câu hỏi, đáp án và lựa chọn của sinh viên
    $data_sql = "
        SELECT 
            q.id AS question_id, q.question_text,
            a.id AS answer_id, a.answer_text, a.is_correct,
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
    error_log("view_submission.php error: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bài làm của <?= htmlspecialchars($student_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Be Vietnam Pro', sans-serif; } </style>
</head>
<body class="bg-slate-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="bg-slate-800 text-white w-72 space-y-6 py-7 px-4">
            <a href="index.php" class="text-white flex items-center space-x-3 px-4">
                <i class="bi bi-card-checklist text-3xl"></i><span class="text-2xl font-extrabold">Quizzy Admin</span>
            </a>
            <nav class="pt-6">
                <a href="results.php" class="flex items-center space-x-3 py-3 px-4 rounded-lg text-slate-200 hover:bg-slate-700">
                    <i class="bi bi-arrow-left"></i><span>Xem Kết Quả</span>
                </a>
            </nav>
        </aside>
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex justify-between items-center p-4 bg-white border-b lg:justify-end">
                <div class="flex items-center space-x-4">
                    <span class="font-semibold text-slate-700">Xin chào, <?= $admin_name ?></span>
                    <i class="bi bi-person-circle text-3xl text-slate-500"></i>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto bg-slate-100 p-6 md:p-8">
                <div class="container mx-auto max-w-4xl">
                    <?php if ($error_message): ?>
                        <div class="bg-white p-8 rounded-2xl shadow-lg text-center">
                            <i class="bi bi-exclamation-triangle-fill text-5xl text-red-500 mb-4"></i>
                            <h2 class="text-2xl font-bold text-red-600">Đã xảy ra lỗi</h2>
                            <p class="text-slate-600 mt-2"><?= htmlspecialchars($error_message) ?></p>
                            <a href="results.php" class="mt-6 inline-block py-2 px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">Quay lại</a>
                        </div>
                    <?php else: ?>
                        <header class="bg-white p-6 rounded-2xl shadow-lg mb-8">
                            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                                <div>
                                    <h1 class="text-2xl font-bold text-slate-800">Bài làm của: <span class="text-blue-600"><?= htmlspecialchars($student_name) ?></span></h1>
                                    <p class="text-slate-600">Bài thi: <?= htmlspecialchars($quiz_title) ?></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-slate-600">Điểm số</p>
                                    <p class="text-4xl font-bold text-blue-600"><?= htmlspecialchars($final_score) ?>/10</p>
                                </div>
                            </div>
                        </header>
                        <div class="space-y-6">
                            <?php foreach ($review_data as $index => $data): ?>
                                <div class="bg-white p-6 rounded-2xl shadow-lg">
                                    <h2 class="text-lg font-semibold text-slate-900 mb-4">
                                        <strong>Câu <?= array_search($index, array_keys($review_data)) + 1 ?>:</strong>
                                        <?= htmlspecialchars($data['question_text']) ?>
                                    </h2>
                                    <div class="flex flex-col gap-3">
                                        <?php foreach ($data['options'] as $i => $option): 
                                            $user_choice_id = (int)$data['user_choice'];
                                            $option_id = (int)$option['id'];
                                            $class = 'border-slate-300 bg-white';
                                            $icon = '';
                                            if ($option['is_correct']) {
                                                $class = 'border-green-500 bg-green-50 text-green-900 ring-2 ring-green-300';
                                                $icon = '<i class="bi bi-check-circle-fill text-green-500"></i>';
                                            } elseif ($user_choice_id === $option_id) {
                                                $class = 'border-red-500 bg-red-50 text-red-900 ring-2 ring-red-300';
                                                $icon = '<i class="bi bi-x-circle-fill text-red-500"></i>';
                                            }
                                        ?>
                                            <div class="w-full text-left p-4 border rounded-lg flex items-start gap-4 <?= $class ?>">
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
            </main>
        </div>
    </div>
</body>
</html>
