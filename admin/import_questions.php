<?php
session_start();
include(__DIR__ . '/../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
$message = null;
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $bank_name = trim($_POST['bank_name'] ?? '');
        $file_path = $_FILES['csv_file']['tmp_name'];
        $lecturer_id = (int)$_SESSION['user_id'];

        if (!empty($bank_name) && is_uploaded_file($file_path)) {
            $conn->begin_transaction();
            try {
                $stmt_bank = $conn->prepare("INSERT INTO question_banks (name, user_id) VALUES (?, ?)");
                $stmt_bank->bind_param("si", $bank_name, $lecturer_id);
                $stmt_bank->execute();
                $bank_id = $stmt_bank->insert_id;
                $stmt_bank->close();

                if (!$bank_id) {
                    throw new Exception("Không thể tạo ngân hàng câu hỏi mới.");
                }

                $handle = fopen($file_path, "r");
                $is_header = true;
                $imported_count = 0;

                $stmtQ = $conn->prepare("INSERT INTO questions (question_text, bank_id) VALUES (?, ?)");
                $stmtA = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");

                while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                    if ($is_header) { $is_header = false; continue; }
                    if (count($data) < 6) continue;

                    [$question, $ans_a, $ans_b, $ans_c, $ans_d, $correct_answer_text] = $data;

                    $stmtQ->bind_param("si", $question, $bank_id);
                    $stmtQ->execute();
                    $question_id = $stmtQ->insert_id;

                    if ($question_id > 0) {
                        $answers = [$ans_a, $ans_b, $ans_c, $ans_d];
                        foreach ($answers as $text) {
                            $is_correct = (strcasecmp(trim($correct_answer_text), trim($text)) == 0) ? 1 : 0;
                            $stmtA->bind_param("isi", $question_id, $text, $is_correct);
                            $stmtA->execute();
                        }
                        $imported_count++;
                    }
                }
                fclose($handle);

                $conn->commit();
                $message = "Thành công! Đã nhập {$imported_count} câu hỏi vào ngân hàng '{$bank_name}'.";
                $message_type = 'success';

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Đã xảy ra lỗi: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Vui lòng đặt tên cho ngân hàng câu hỏi và chọn tệp CSV hợp lệ.";
            $message_type = 'error';
        }
    } else {
        $message = "Có lỗi xảy ra khi tải tệp lên.";
        $message_type = 'error';
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Ngân Hàng Câu Hỏi - Quizzy Admin</title>
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
                        <h2 class="text-3xl font-bold text-slate-800">Import Ngân Hàng Câu Hỏi</h2>
                        <a href="index.php" class="text-blue-600 hover:underline font-semibold flex items-center gap-2">
                           <i class="bi bi-arrow-left"></i> Quay lại
                        </a>
                    </div>
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white p-8 rounded-2xl shadow-lg">
                        <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            <div>
                                <label for="bank_name" class="block text-sm font-medium text-slate-700 mb-2">1. Đặt tên cho Ngân hàng câu hỏi:</label>
                                <input type="text" id="bank_name" name="bank_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Ví dụ: Đề cương Năng lực số HK1">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">2. Chọn tệp CSV chứa câu hỏi:</label>
                                <label for="csv_file" class="flex justify-center w-full px-6 py-10 border-2 border-slate-300 border-dashed rounded-lg cursor-pointer hover:bg-slate-50">
                                    <div class="text-center">
                                        <i class="bi bi-cloud-arrow-up-fill text-4xl text-slate-400"></i>
                                        <p class="mt-2 text-sm text-slate-600"><span class="font-semibold text-blue-600">Nhấn để chọn tệp</span></p>
                                        <p class="text-xs text-slate-500 mt-1">Chỉ chấp nhận tệp .csv</p>
                                        <span id="file-name" class="text-sm font-semibold text-green-600 mt-2 block"></span>
                                    </div>
                                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required class="hidden">
                                </label>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="bi bi-upload"></i> Tạo Ngân Hàng
                            </button>
                        </form>
                    </div>
                     <div class="mt-8 bg-slate-800 text-white p-6 rounded-2xl">
                        <h4 class="text-lg font-bold mb-3 flex items-center gap-2"><i class="bi bi-file-earmark-spreadsheet-fill"></i> Định dạng tệp CSV mẫu</h4>
                        <p class="text-slate-300 mb-4">Tệp phải có 6 cột, không có dòng tiêu đề. Cột cuối cùng là nội dung của đáp án đúng.</p>
                        <pre class="bg-slate-900 p-4 rounded-lg text-sm whitespace-pre-wrap"><code>question_text,answer_a,answer_b,answer_c,answer_d,correct_answer_text
"Thủ đô Việt Nam?",Hà Nội,TP.HCM,Đà Nẵng,Huế,"Hà Nội"
"1 + 1 bằng mấy?",1,2,3,4,"2"</code></pre>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        document.getElementById('csv_file').addEventListener('change', (e) => {
            const fileNameSpan = document.getElementById('file-name');
            fileNameSpan.textContent = e.target.files.length > 0 ? `Đã chọn: ${e.target.files[0].name}` : '';
        });
    </script>
</body>
</html>