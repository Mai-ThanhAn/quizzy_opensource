<?php
include(__DIR__ . '/../config/session.php');
include(__DIR__ . '/../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
$message = null;
$message_type = '';

$bank_id = filter_input(INPUT_GET, 'bank_id', FILTER_VALIDATE_INT);
if (!$bank_id) {
    header("Location: manage_banks.php");
    exit();
}

// Xử lý Xóa câu hỏi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    $question_id_to_delete = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
    if ($question_id_to_delete) {
        $stmt = $conn->prepare("DELETE FROM questions WHERE id = ? AND bank_id = ?");
        $stmt->bind_param("ii", $question_id_to_delete, $bank_id);
        if ($stmt->execute()) {
            $message = "Xóa câu hỏi thành công.";
            $message_type = 'success';
        } else {
            $message = "Lỗi khi xóa câu hỏi.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Xử lý Cập nhật đáp án đúng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_answer'])) {
    $question_id_to_update = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
    $new_correct_answer_id = filter_input(INPUT_POST, 'correct_answer_id', FILTER_VALIDATE_INT);

    if ($question_id_to_update && $new_correct_answer_id) {
        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("UPDATE answers SET is_correct = 0 WHERE question_id = ?");
            $stmt1->bind_param("i", $question_id_to_update);
            $stmt1->execute();
            $stmt1->close();

            $stmt2 = $conn->prepare("UPDATE answers SET is_correct = 1 WHERE id = ? AND question_id = ?");
            $stmt2->bind_param("ii", $new_correct_answer_id, $question_id_to_update);
            $stmt2->execute();
            $stmt2->close();
            
            $conn->commit();
            $message = "Cập nhật đáp án đúng thành công.";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Lỗi khi cập nhật đáp án.";
            $message_type = 'error';
        }
    }
}


// Lấy thông tin ngân hàng và các câu hỏi
$bank_name = "Ngân hàng câu hỏi";
$stmt_bank = $conn->prepare("SELECT name FROM question_banks WHERE id = ?");
$stmt_bank->bind_param("i", $bank_id);
$stmt_bank->execute();
if ($res = $stmt_bank->get_result()) {
    if ($row = $res->fetch_assoc()) {
        $bank_name = $row['name'];
    }
}
$stmt_bank->close();

$questions_data = [];
$sql_questions = "SELECT q.id as qid, q.question_text, a.id as aid, a.answer_text, a.is_correct 
                  FROM questions q 
                  JOIN answers a ON q.id = a.question_id 
                  WHERE q.bank_id = ? 
                  ORDER BY q.id, a.id";
$stmt_questions = $conn->prepare($sql_questions);
$stmt_questions->bind_param("i", $bank_id);
$stmt_questions->execute();
$result_questions = $stmt_questions->get_result();

while ($row = $result_questions->fetch_assoc()) {
    $qid = $row['qid'];
    if (!isset($questions_data[$qid])) {
        $questions_data[$qid] = [
            'id' => $qid,
            'text' => $row['question_text'],
            'answers' => []
        ];
    }
    $questions_data[$qid]['answers'][] = [
        'id' => $row['aid'],
        'text' => $row['answer_text'],
        'is_correct' => $row['is_correct']
    ];
}
$stmt_questions->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa: <?= htmlspecialchars($bank_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Be Vietnam Pro', sans-serif; } </style>
</head>
<body class="bg-slate-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="sidebar bg-slate-800 text-white w-72 space-y-6 py-7 px-4 absolute inset-y-0 left-0 transform lg:relative lg:translate-x-0">
             <a href="index.php" class="text-white flex items-center space-x-3 px-4">
                <i class="bi bi-card-checklist text-3xl"></i><span class="text-2xl font-extrabold">Giảng Viên</span>
            </a>
            <nav class="pt-6">
                <a href="index.php" class="flex items-center space-x-3 py-3 px-4 rounded-lg text-slate-200 hover:bg-slate-700">
                    <i class="bi bi-escape"></i><span>Quay Lại Trang Chủ</span>
                </a>
            </nav>
            <div class="absolute bottom-5 w-full px-4">
                 <a href="../logout.php" class="flex items-center space-x-3 py-3 px-4 rounded-lg text-slate-200 hover:bg-slate-700 transition-colors">
                    <i class="bi bi-box-arrow-left text-lg"></i><span>Đăng Xuất</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
             <header class="flex justify-between items-center p-4 bg-white border-b">
                 <div class="flex items-center ml-auto">
                    <span class="font-semibold text-slate-700">Xin chào, <?= $admin_name ?></span>
                    <i class="bi bi-person-circle text-3xl text-slate-500 ml-4"></i>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto bg-slate-100 p-6 md:p-8">
                <div class="container mx-auto max-w-4xl">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-3xl font-bold text-slate-800">Quản lý Ngân hàng câu hỏi</h2>
                            <p class="text-slate-600">Ngân hàng: <span class="font-semibold text-blue-600"><?= htmlspecialchars($bank_name) ?></span></p>
                        </div>
                        <a href="manage_banks.php" class="text-blue-600 hover:underline font-semibold flex items-center gap-2">
                           <i class="bi bi-arrow-left"></i> Quay lại danh sách
                        </a>
                    </div>
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <div class="space-y-6">
                        <?php if (!empty($questions_data)): ?>
                            <?php foreach ($questions_data as $question): ?>
                                <div class="bg-white p-6 rounded-2xl shadow-lg">
                                    <div class="flex justify-between items-start">
                                        <p class="text-lg font-semibold text-slate-800 mb-4"><?= htmlspecialchars($question['text']) ?></p>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa câu hỏi này? Hành động không thể hoàn tác.');">
                                            <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                            <button type="submit" name="delete_question" class="text-red-500 hover:text-red-700" title="Xóa câu hỏi">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                        <div class="space-y-3">
                                            <?php foreach($question['answers'] as $answer): ?>
                                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-slate-50 <?= $answer['is_correct'] ? 'bg-green-50 border-green-400' : 'border-slate-300' ?>">
                                                    <input type="radio" name="correct_answer_id" value="<?= $answer['id'] ?>" class="h-4 w-4 text-blue-600" <?= $answer['is_correct'] ? 'checked' : '' ?>>
                                                    <span class="ml-3 text-slate-700"><?= htmlspecialchars($answer['text']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-right mt-4">
                                            <button type="submit" name="update_answer" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">
                                                Lưu đáp án
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <div class="bg-white p-8 rounded-2xl shadow-lg text-center">
                                <p class="text-slate-500">Ngân hàng này chưa có câu hỏi nào.</p>
                             </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
