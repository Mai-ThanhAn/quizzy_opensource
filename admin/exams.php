<?php
include(__DIR__ . '/../config/session.php');
include(__DIR__ . '/../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
$lecturer_id = (int)$_SESSION['user_id'];
$message = null;
$message_type = '';

function format_datetime_for_db($datetime_local)
{
    if (empty($datetime_local)) return null;
    try {
        $dt = new DateTime($datetime_local);
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

// Xử lý Tạo bài thi từ ngân hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_from_bank'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $class_name = trim($_POST['class_name'] ?? '') ?: null;
    $time_limit = filter_input(INPUT_POST, 'time_limit', FILTER_VALIDATE_INT) ?: 20;
    $can_review = isset($_POST['can_review']) ? 1 : 0;
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $shuffle_answers = 1;
    $quiz_password = trim($_POST['quiz_password'] ?? '');
    $quiz_password = $quiz_password !== '' ? hash('sha256', $quiz_password) : null;
    $bank_id = filter_input(INPUT_POST, 'bank_id', FILTER_VALIDATE_INT);
    $num_questions = filter_input(INPUT_POST, 'num_questions', FILTER_VALIDATE_INT);
    $start_at = format_datetime_for_db($_POST['start_at'] ?? null);
    $finished_at = format_datetime_for_db($_POST['finished_at'] ?? null);

    if ($title && $bank_id && $num_questions > 0) {
        $conn->begin_transaction();
        try {
            // Thêm bài thi mới
            $stmt_quiz = $conn->prepare("
                INSERT INTO quizzes 
                (title, description, class_name, time_limit, can_review, shuffle_questions, shuffle_answers, password, start_at, finished_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_quiz->bind_param(
                "sssiiiisss",
                $title,
                $description,
                $class_name,
                $time_limit,
                $can_review,
                $shuffle_questions,
                $shuffle_answers,
                $quiz_password,
                $start_at,
                $finished_at
            );
            $stmt_quiz->execute();
            $new_quiz_id = $conn->insert_id;
            $stmt_quiz->close();

            // Chọn ngẫu nhiên câu hỏi
            $stmt_rand = $conn->prepare("SELECT id FROM questions WHERE bank_id = ? ORDER BY RAND() LIMIT ?");
            $stmt_rand->bind_param("ii", $bank_id, $num_questions);
            $stmt_rand->execute();
            $rand_result = $stmt_rand->get_result();
            $question_ids_from_bank = [];
            while ($row = $rand_result->fetch_assoc()) {
                $question_ids_from_bank[] = $row['id'];
            }
            $stmt_rand->close();

            if (count($question_ids_from_bank) < $num_questions) {
                throw new Exception("Ngân hàng không đủ câu hỏi để tạo bài thi.");
            }

            // Copy từng câu hỏi và đáp án
            $stmt_get_q = $conn->prepare("SELECT question_text FROM questions WHERE id = ?");
            $stmt_insert_q = $conn->prepare("INSERT INTO questions (quiz_id, question_text) VALUES (?, ?)");
            $stmt_get_a = $conn->prepare("SELECT answer_text, is_correct FROM answers WHERE question_id = ?");
            $stmt_insert_a = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");

            foreach ($question_ids_from_bank as $old_qid) {
                // Copy question
                $stmt_get_q->bind_param("i", $old_qid);
                $stmt_get_q->execute();
                $q_result = $stmt_get_q->get_result();
                if ($q_row = $q_result->fetch_assoc()) {
                    $stmt_insert_q->bind_param("is", $new_quiz_id, $q_row['question_text']);
                    $stmt_insert_q->execute();
                    $new_qid = $conn->insert_id;

                    // Copy answers
                    $stmt_get_a->bind_param("i", $old_qid);
                    $stmt_get_a->execute();
                    $a_result = $stmt_get_a->get_result();
                    while ($a_row = $a_result->fetch_assoc()) {
                        $stmt_insert_a->bind_param("isi", $new_qid, $a_row['answer_text'], $a_row['is_correct']);
                        $stmt_insert_a->execute();
                    }
                }
            }

            $stmt_get_q->close();
            $stmt_insert_q->close();
            $stmt_get_a->close();
            $stmt_insert_a->close();

            $conn->commit();
            $message = "Tạo bài thi '{$title}' thành công!";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Lỗi khi tạo bài thi: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Vui lòng điền đầy đủ thông tin hợp lệ.";
        $message_type = 'error';
    }
}

// Xử lý logic cho việc sửa bài thi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_quiz'])) {
    $quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
    if ($quiz_id) {
        try {
            // Lấy dữ liệu hiện tại
            $stmt_current = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
            $stmt_current->bind_param("i", $quiz_id);
            $stmt_current->execute();
            $current_quiz_data = $stmt_current->get_result()->fetch_assoc();
            $stmt_current->close();

            if (!$current_quiz_data) {
                throw new Exception("Không tìm thấy bài thi để cập nhật.");
            }

            // --- Dữ liệu mới từ form ---
            $new_class_name = trim($_POST['class_name'] ?? '') ?: null;
            $new_time_limit = filter_input(INPUT_POST, 'time_limit', FILTER_VALIDATE_INT) ?: 20;
            $new_can_review = isset($_POST['can_review']) ? 1 : 0;
            $new_shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
            $new_shuffle_answers = 1;

            // Mật khẩu: chỉ hash khi có nhập mới
            $new_password = trim($_POST['quiz_password'] ?? '');
            if ($new_password !== '') {
                $new_password = hash('sha256', $new_password);
            } else {
                $new_password = $current_quiz_data['password']; // giữ nguyên
            }

            // Xử lý thời gian (chuẩn hóa định dạng)
            $new_start_at = !empty($_POST['start_at']) 
                ? date('Y-m-d H:i:s', strtotime($_POST['start_at'])) 
                : null;
            $new_finished_at = !empty($_POST['finished_at']) 
                ? date('Y-m-d H:i:s', strtotime($_POST['finished_at'])) 
                : null;

            // So sánh thay đổi thực sự
            $has_changed = (
                $new_class_name !== $current_quiz_data['class_name'] ||
                $new_time_limit != $current_quiz_data['time_limit'] ||
                $new_can_review != $current_quiz_data['can_review'] ||
                $new_shuffle_questions != $current_quiz_data['shuffle_questions'] ||
                $new_shuffle_answers != $current_quiz_data['shuffle_answers'] ||
                $new_password !== $current_quiz_data['password'] ||
                ($new_start_at ?? '') !== ($current_quiz_data['start_at'] ?? '') ||
                ($new_finished_at ?? '') !== ($current_quiz_data['finished_at'] ?? '')
            );

            if ($has_changed) {
                $stmt = $conn->prepare("
                    UPDATE quizzes 
                    SET class_name = ?, 
                        time_limit = ?, 
                        can_review = ?, 
                        shuffle_questions = ?, 
                        shuffle_answers = ?, 
                        password = ?, 
                        start_at = ?, 
                        finished_at = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "siiiisssi",
                    $new_class_name,
                    $new_time_limit,
                    $new_can_review,
                    $new_shuffle_questions,
                    $new_shuffle_answers,
                    $new_password,
                    $new_start_at,
                    $new_finished_at,
                    $quiz_id
                );

                if ($stmt->execute()) {
                    $message = "Cập nhật bài thi thành công!";
                    $message_type = 'success';
                } else {
                    throw new Exception("Lỗi khi thực thi cập nhật.");
                }
                $stmt->close();
            } else {
                $message = "Không có thay đổi nào được ghi nhận.";
                $message_type = 'info';
            }
        } catch (Exception $e) {
            $message = "Lỗi khi cập nhật: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Thiếu mã bài thi hợp lệ.";
        $message_type = 'error';
    }
}

//Xử lý khi xóa bài thi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz'])) {
    $quiz_id_to_delete = filter_input(INPUT_POST, 'quiz_id_delete', FILTER_VALIDATE_INT);

    if ($quiz_id_to_delete) {
        try {
            $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
            $stmt->bind_param("i", $quiz_id_to_delete);

            if ($stmt->execute()) {
                $message = "Xóa bài kiểm tra thành công!";
                $message_type = 'success';
            } else {
                throw new Exception("Không thể thực thi câu lệnh xóa.");
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $message = "Lỗi CSDL: Không thể xóa bài kiểm tra.";
            $message_type = 'error';
            error_log("Delete quiz error: " . $e->getMessage());
        }
    } else {
        $message = "ID bài thi không hợp lệ để xóa.";
        $message_type = 'error';
    }
}

$quizzes = [];
$quizzes_result = $conn->query("SELECT id, title, description, class_name, time_limit, can_review, shuffle_questions, shuffle_answers, start_at, finished_at, password IS NOT NULL as has_password FROM quizzes ORDER BY id DESC");
if ($quizzes_result) {
    while ($row = $quizzes_result->fetch_assoc()) {
        $quizzes[] = $row;
    }
}

// Lấy danh sách ngân hàng câu hỏi
$question_banks = [];
$question_banks_result = $conn->query("SELECT id, name FROM question_banks WHERE user_id = {$lecturer_id} ORDER BY name");
if ($question_banks_result) {
    while ($row = $question_banks_result->fetch_assoc()) {
        $question_banks[] = $row;
    }
}

//Lấy danh sách các lớp do giảng viên này tạo
$lecturer_classes = [];
$stmt_classes = $conn->prepare("SELECT class_id, class_name FROM class WHERE users_id = ? ORDER BY class_name ASC");
$stmt_classes->bind_param("i", $lecturer_id);
$stmt_classes->execute();
$classes_result = $stmt_classes->get_result();
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $lecturer_classes[] = $row;
    }
}
$stmt_classes->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Bài Kiểm Tra - Quizzy Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="sidebar bg-slate-800 text-white w-72 space-y-6 py-7 px-4 absolute inset-y-0 left-0 transform lg:relative lg:translate-x-0">
            <a href="index.php" class="text-white flex items-center space-x-3 px-4">
                <i class="bi bi-bookmark-star-fill text-3xl text-red-500"></i>
                <span class="text-2xl font-extrabold">Giảng Viên</span>
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

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex justify-between items-center p-4 bg-white border-b">
                <div class="flex items-center ml-auto">
                    <span class="font-semibold text-slate-700">Xin chào, <?= $admin_name ?></span>
                    <i class="bi bi-person-circle text-3xl text-slate-500 ml-4"></i>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto bg-slate-100 p-6 md:p-8">
                <div class="container mx-auto max-w-7xl">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                        <h2 class="text-3xl font-bold text-slate-800">Quản Lý Bài Kiểm Tra</h2>
                        <button id="add-from-bank-btn" class="bg-blue-600 text-white font-bold py-2.5 px-5 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <i class="bi bi-lightning-charge-fill"></i> Tạo Mới Từ Ngân Hàng
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-blue-600">
                        <i class="bi bi-exclamation-diamond-fill"></i>
                        Gợi ý: Một số chức năng có thể sử dụng trong quá trình tạo bài kiểm tra:
                    <ul class="mt-2 text-sm text-slate-500">
                        <li>- Cài đặt thời gian kiểm tra</li>
                        <li>- Xáo thứ tự câu hỏi của đề</li>
                        <li>- Xáo đáp án của câu hỏi.</li>
                        <li>- Cho phép xem lại kết quả bài kiểm tra.</li>
                    </ul>
                    <p class="mt-2 text-sm text-blue-600">
                        Lưu ý: Nếu muốn cho sinh viên làm lại bài kiểm tra vui lòng truy cập 
                        <a href="results.php" class="mt-2 text-sm text-red-600"> trang quản lý điểm.</a>
                    </p>
                    </br>
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <div class="bg-white rounded-2xl shadow-lg">
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-slate-800">Danh Sách Bài Kiểm Tra Của Bạn</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-slate-500">
                                <thead class="text-xs text-slate-700 uppercase bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-3">Tên Bài Kiểm Tra</th>
                                        <th class="px-6 py-3 text-center">Thông Tin</th>
                                        <th class="px-6 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($quizzes)): ?>
                                        <?php foreach ($quizzes as $quiz): ?>
                                            <?php
                                            // Xử lý thời gian an toàn
                                            try {
                                                $start_at = (isset($quiz['start_at']) && $quiz['start_at'] && $quiz['start_at'] !== '0000-00-00 00:00:00') ? new DateTime($quiz['start_at']) : null;
                                            } catch (Exception $e) {
                                                $start_at = null;
                                            }

                                            try {
                                                $finished_at = (isset($quiz['finished_at']) && $quiz['finished_at'] && $quiz['finished_at'] !== '0000-00-00 00:00:00') ? new DateTime($quiz['finished_at']) : null;
                                            } catch (Exception $e) {
                                                $finished_at = null;
                                            }
                                            ?>
                                            <tr class="bg-white border-b hover:bg-slate-50">
                                                <td class="px-6 py-4 font-medium text-slate-900">
                                                    <p class="font-semibold flex items-center gap-2">
                                                        <?= htmlspecialchars($quiz['title']) ?>
                                                        <?php if ($quiz['has_password']): ?>
                                                            <i class="bi bi-lock-fill text-slate-400" title="Bài thi có mật khẩu"></i>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!empty($quiz['class_name'])): ?>
                                                        <span class="text-xs bg-blue-100 text-blue-800 font-medium px-2 py-0.5 rounded-full">Lớp: <?= htmlspecialchars($quiz['class_name']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-xs bg-gray-100 text-gray-800 font-medium px-2 py-0.5 rounded-full">Tất cả</span>
                                                    <?php endif; ?>
                                                    <!-- Hiển thị ngày mở và đóng -->
                                                    <div class="mt-1 text-xs text-slate-600">
                                                        <p>Mở lúc: <?= $start_at ? $start_at->format('H:i, d/m/Y') : 'Chưa đặt lịch' ?></p>
                                                        <p>Đóng lúc: <?= $finished_at ? $finished_at->format('H:i, d/m/Y') : 'Chưa đặt lịch' ?></p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div class="flex justify-center items-center gap-2 flex-wrap">
                                                        <span class="bg-slate-100 text-slate-800 text-xs font-medium px-2.5 py-1 rounded-full"><i class="bi bi-clock"></i> <?= $quiz['time_limit'] ?> phút</span>
                                                        <span class="<?= $quiz['can_review'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> text-xs font-medium px-2.5 py-1 rounded-full"><?= $quiz['can_review'] ? 'Cho xem lại' : 'Không xem lại' ?></span>
                                                        <span class="<?= $quiz['shuffle_questions'] ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-800' ?> text-xs font-medium px-2.5 py-1 rounded-full"><?= $quiz['shuffle_questions'] ? 'Trộn đề' : 'Không trộn đề' ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-4">
                                                        <button class="edit-btn font-medium text-blue-600 hover:underline"
                                                            data-id="<?= $quiz['id'] ?>"
                                                            data-title="<?= htmlspecialchars($quiz['title']) ?>"
                                                            data-start-at="<?= $start_at ? $start_at->format('Y-m-d\TH:i') : '' ?>"
                                                            data-finished-at="<?= $finished_at ? $finished_at->format('Y-m-d\TH:i') : '' ?>"
                                                            data-classname="<?= htmlspecialchars($quiz['class_name']) ?>"
                                                            data-time="<?= $quiz['time_limit'] ?>"
                                                            data-review="<?= $quiz['can_review'] ?>"
                                                            data-shuffle="<?= $quiz['shuffle_questions'] ?>"
                                                            data-shuffle-answers="<?= $quiz['shuffle_answers'] ?>">Điều chỉnh</button>

                                                        <button class="delete-btn font-medium text-red-600 hover:underline"
                                                            data-id="<?= $quiz['id'] ?>"
                                                            data-title="<?= htmlspecialchars($quiz['title']) ?>">Xóa</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-center">Chưa có bài kiểm tra nào.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Tạo Bài Kiểm Tra Ngân Hàng -->
    <div id="add-from-bank-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-50 overflow-auto">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <h3 class="text-2xl font-bold text-slate-800 mb-6">Tạo Mới Bài Kiểm Tra</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="create_from_bank" value="1">

                <!-- Tên bài kiểm tra -->
                <div>
                    <label class="block text-sm font-medium">Tên bài kiểm tra:</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg">
                </div>

                <!-- Chọn lớp -->
                <div>
                    <label class="block text-sm font-medium">Chọn lớp:</label>
                    <select name="class_name" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">-- Vui Lòng Chọn Lớp--</option>
                        <?php if (!empty($lecturer_classes)): ?>
                            <?php foreach ($lecturer_classes as $class): ?>
                                <option value="<?= htmlspecialchars($class['class_id']) ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Mô tả -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mô tả:</label>
                    <textarea name="description" rows="3" required class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>

                <!-- Ngân hàng + số câu hỏi -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Chọn ngân hàng câu hỏi:</label>
                        <select name="bank_id" required class="w-full px-4 py-2 border rounded-lg">
                            <?php if (!empty($question_banks)): ?>
                                <?php foreach ($question_banks as $bank): ?>
                                    <option value="<?= htmlspecialchars($bank['id']) ?>"><?= htmlspecialchars($bank['name']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option disabled>Chưa có ngân hàng nào</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Số câu hỏi:</label>
                        <input type="number" name="num_questions" value="20" min="1" required class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>

                <!-- Thời gian + mật khẩu -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Thời gian (phút):</label>
                        <input type="number" name="time_limit" value="20" min="1" required class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu:</label>
                        <input type="text" name="quiz_password" class="w-full px-4 py-2 border rounded-lg">
                        <p class="mt-1 text-sm text-slate-500">Có thể để trống nếu không cần</p>
                    </div>
                </div>

                <!-- Thời gian bắt đầu / kết thúc -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Thời gian bắt đầu (bỏ trống để bắt đầu ngay):</label>
                        <input type="datetime-local" name="start_at" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Thời gian kết thúc (bỏ trống nếu không giới hạn):</label>
                        <input type="datetime-local" name="finished_at" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>

                <!-- Options -->
                <div class="flex flex-col gap-2 pt-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="shuffle_questions_bank" name="shuffle_questions" value="1" class="h-4 w-4 rounded">
                        Xáo trộn thứ tự câu hỏi
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="can_review_bank" name="can_review" value="1" class="h-4 w-4 rounded">
                        Cho phép xem lại (Sinh viên)
                    </label>
                </div>

                <!-- Buttons -->
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" class="cancel-modal-btn bg-slate-100 font-bold py-2.5 px-4 rounded-lg">Hủy</button>
                    <button type="submit" class="bg-blue-600 text-white font-bold py-2.5 px-4 rounded-lg">Tạo Bài Thi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Chỉnh Sửa Cài Đặt -->
    <div id="edit-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-lg w-full">
            <h3 class="text-2xl font-bold text-slate-800 mb-6">Chỉnh Sửa Cài Đặt</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="edit_quiz" value="1">
                <input type="hidden" id="edit-quiz-id" name="quiz_id">
                <p><strong>Bài thi:</strong> <span id="edit-quiz-title"></span></p>
                <div>
                    <label class="block text-sm font-medium">Chỉ định cho lớp:</label>
                    <select id="edit-class-name" name="class_name" class="w-full px-4 py-2 border rounded-lg">
                        <?php if (!empty($lecturer_classes)): ?>
                            <?php foreach ($lecturer_classes as $class): ?>
                                <option value="<?= htmlspecialchars($class['class_id']) ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Thời gian (phút):</label>
                    <input type="number" id="edit-time-limit" name="time_limit" min="1" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Đặt Lại Mật Khẩu (bỏ trống nếu không đổi):</label>
                    <input type="text" name="quiz_password" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Thời gian bắt đầu:</label>
                        <input type="datetime-local" id="edit-start-at" name="start_at" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Thời gian kết thúc:</label>
                        <input type="datetime-local" id="edit-finished-at" name="finished_at" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="flex items-center justify-between pt-2">
                    <span class="text-sm font-medium text-slate-900">Cho phép xem lại</span>
                    <input type="checkbox" id="edit-can-review" name="can_review" value="1" class="h-4 w-4 rounded">
                </div>
                <div class="flex items-center justify-between pt-2">
                    <span class="text-sm font-medium text-slate-900">Xáo trộn câu hỏi</span>
                    <input type="checkbox" id="edit-shuffle-questions" name="shuffle_questions" value="1" class="h-4 w-4 rounded">
                </div>
                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" class="cancel-modal-btn bg-slate-100 font-bold py-2.5 px-4 rounded-lg">Hủy</button>
                    <button type="submit" class="bg-green-600 text-white font-bold py-2.5 px-4 rounded-lg">Lưu Thay Đổi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Xóa Bài Thi -->
    <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="bi bi-exclamation-triangle-fill text-2xl text-red-600"></i>
            </div>
            <h3 class="text-2xl font-bold text-slate-800 mt-5">Xác nhận Xóa</h3>
            <p class="text-slate-600 mt-2">Bạn có chắc chắn muốn xóa bài thi <strong id="delete-quiz-title" class="text-slate-900"></strong> không? Hành động này không thể hoàn tác.</p>
            <form method="POST" class="mt-6">
                <input type="hidden" id="delete-quiz-id" name="quiz_id_delete">
                <div class="flex justify-center gap-4">
                    <button type="button" class="cancel-modal-btn bg-slate-200 text-slate-800 font-bold py-2.5 px-6 rounded-lg">Hủy</button>
                    <button type="submit" name="delete_quiz" class="bg-red-600 text-white font-bold py-2.5 px-6 rounded-lg">Xác Nhận</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const bankModal = document.getElementById('add-from-bank-modal');
        const openBankModalBtn = document.getElementById('add-from-bank-btn');
        const editModal = document.getElementById('edit-modal');
        const openEditModalBtns = document.querySelectorAll('.edit-btn');
        const deleteModal = document.getElementById('delete-modal');
        const openDeleteModalBtns = document.querySelectorAll('.delete-btn');
        const cancelModalBtns = document.querySelectorAll('.cancel-modal-btn');

        openBankModalBtn.addEventListener('click', () => bankModal.classList.remove('hidden'));

        openEditModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('edit-quiz-id').value = btn.dataset.id;
                document.getElementById('edit-quiz-title').textContent = btn.dataset.title;
                document.getElementById('edit-class-name').value = btn.dataset.classname;
                document.getElementById('edit-time-limit').value = btn.dataset.time;
                document.getElementById('edit-can-review').checked = (btn.dataset.review == 1);
                document.getElementById('edit-shuffle-questions').checked = (btn.dataset.shuffle == 1);
                document.getElementById('edit-start-at').value = btn.dataset.startAt;
                document.getElementById('edit-finished-at').value = btn.dataset.finishedAt;
                editModal.classList.remove('hidden');
            });
        });

        openDeleteModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('delete-quiz-id').value = btn.dataset.id;
                document.getElementById('delete-quiz-title').textContent = `"${btn.dataset.title}"`;
                deleteModal.classList.remove('hidden');
            });
        });

        const closeAllModals = () => {
            bankModal.classList.add('hidden');
            editModal.classList.add('hidden');
            deleteModal.classList.add('hidden');
        };

        cancelModalBtns.forEach(btn => btn.addEventListener('click', closeAllModals));

        bankModal.addEventListener('click', (e) => {
            if (e.target === bankModal) closeAllModals();
        });
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) closeAllModals();
        });
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) closeAllModals();
        });
    </script>
</body>

</html>