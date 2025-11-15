<?php
include(__DIR__ . '/../config/session.php');
include(__DIR__ . '/../config/db.php');

// 1. Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
$message = null;
$message_type = '';

// 2. Xử lý khi giảng viên cho phép sinh viên làm lại bài
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_result'])) {
    $result_id_to_delete = filter_input(INPUT_POST, 'result_id', FILTER_VALIDATE_INT);

    if ($result_id_to_delete) {
        try {
            $stmt = $conn->prepare("DELETE FROM results WHERE id = ?");
            $stmt->bind_param("i", $result_id_to_delete);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Đã xóa kết quả cũ. Sinh viên có thể làm lại bài thi.";
                $message_type = 'success';
            } else {
                throw new Exception("Không tìm thấy kết quả để xóa hoặc đã có lỗi xảy ra.");
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $message = "Lỗi CSDL: Không thể xóa kết quả.";
            $message_type = 'error';
            error_log("Reset result error: " . $e->getMessage());
        }
    }
}

// 3. Lấy dữ liệu để hiển thị
$quizzes_list_result = $conn->query("SELECT id, title, class_name FROM quizzes ORDER BY title");

$selected_quiz_id = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
$results = [];
$quiz_title = '';
$class_name = '';

if ($selected_quiz_id) {
    // Lấy tên của bài thi được chọn
    $quiz_title_stmt = $conn->prepare("SELECT title, class_name FROM quizzes WHERE id = ?");
    $quiz_title_stmt->bind_param("i", $selected_quiz_id);
    $quiz_title_stmt->execute();
    $quiz_title_res = $quiz_title_stmt->get_result();
    if ($row = $quiz_title_res->fetch_assoc()) {
        $quiz_title = $row['title'];
        $class_name = $row['class_name'];
    }
    $quiz_title_stmt->close();

    // Lấy kết quả của sinh viên cho bài thi này
    $sql = "
        SELECT 
            r.id,
            r.score,
            r.submitted_at,
            u.fullname,
            u.student_id
        FROM results r
        JOIN users u ON r.user_id = u.id
        WHERE r.quiz_id = ?
        ORDER BY r.score DESC, r.submitted_at ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_quiz_id);
    $stmt->execute();
    $results_result = $stmt->get_result();
    while ($row = $results_result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xem Kết Quả - Quizzy Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
        }

        .sidebar {
            transition: transform 0.3s ease-in-out;
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 40;
                position: fixed;
                height: 100%;
                top: 0;
                left: 0;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: none;
            }

            .sidebar.open~.sidebar-overlay {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 30;
            }
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

        <!-- Nội dung chính -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="flex justify-between items-center p-4 bg-white border-b lg:justify-end">
                <button id="mobile-menu-button" class="lg:hidden p-2 rounded-md text-slate-500 hover:text-slate-900 hover:bg-slate-100">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <div class="flex items-center space-x-4">
                    <span class="font-semibold text-slate-700">Xin chào, <?= $admin_name ?></span>
                    <i class="bi bi-person-circle text-3xl text-slate-500"></i>
                </div>
            </header>

            <!-- Thân trang -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-100 p-6 md:p-8">
                <div class="container mx-auto max-w-6xl">
                    <h2 class="text-3xl font-bold text-slate-800 mb-6">Kết Quả Bài Kiểm Tra</h2>

                    <!-- Form chọn bài thi -->
                    <div class="bg-white p-6 rounded-2xl shadow-lg mb-8">
                        <form method="GET" action="">
                            <label for="quiz_id" class="block text-lg font-semibold text-slate-800 mb-2">Chọn bài kiểm tra để xem kết quả:</label>
                            <div class="flex items-center gap-4">
                                <select id="quiz_id" name="quiz_id" onchange="this.form.submit()" class="flex-grow w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">-- Vui lòng chọn một bài thi --</option>
                                    <?php if ($quizzes_list_result && $quizzes_list_result->num_rows > 0): ?>
                                        <?php while ($quiz = $quizzes_list_result->fetch_assoc()): ?>
                                            <option value="<?= $quiz['id'] ?>" <?= ($selected_quiz_id == $quiz['id'] ? 'selected' : '') ?>>
                                                <?= htmlspecialchars($quiz['title']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </form>
                    </div>

                    <?php if ($selected_quiz_id): ?>
                        <!-- Bảng kết quả -->
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                                <h3 class="text-l font-bold text-slate-800">
                                    Kết quả của bài kiểm tra:
                                    <span class="text-blue-600"><?= htmlspecialchars($quiz_title) ?></span>
                                </h3>
                                <p class="text-l font-bold text-slate-800">
                                    Lớp: <span class="text-blue-600"><?= htmlspecialchars($class_name) ?></span>
                                </p>
                                <?php if (!empty($results)): ?>
                                    <a href="export_results.php?quiz_id=<?= $selected_quiz_id ?>" class="mt-4 md:mt-0 inline-block bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 transition-colors">
                                        <i class="bi bi-file-earmark-excel-fill"></i> Xuất ra Excel
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left text-slate-500">
                                    <thead class="text-xs text-slate-700 uppercase bg-slate-100">
                                        <tr>
                                            <th scope="col" class="px-6 py-3">Họ và Tên</th>
                                            <th scope="col" class="px-6 py-3">MSSV</th>
                                            <th scope="col" class="px-6 py-3">Điểm (/10)</th>
                                            <th scope="col" class="px-6 py-3">Ngày nộp</th>
                                            <th scope="col" class="px-6 py-3">Chi tiết</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($results)): ?>
                                            <?php foreach ($results as $row): ?>
                                                <tr class="bg-white border-b hover:bg-slate-50">
                                                    <td class="px-6 py-4 font-medium text-slate-900"><?= htmlspecialchars($row['fullname']) ?></td>
                                                    <td class="px-6 py-4"><?= htmlspecialchars($row['student_id']) ?></td>
                                                    <td class="px-6 py-4 font-semibold <?= $row['score'] >= 5 ? 'text-green-600' : 'text-red-600' ?>"><?= htmlspecialchars($row['score']) ?></td>
                                                    <td class="px-6 py-4"><?= date("d/m/Y H:i", strtotime($row['submitted_at'])) ?></td>
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center gap-4">
                                                            <a href="view_submission.php?result_id=<?= $row['id'] ?>" class="font-medium text-blue-600 hover:underline">Xem bài</a>
                                                            <button type="button" class="reset-btn font-medium text-orange-600 hover:underline"
                                                                data-result-id="<?= $row['id'] ?>"
                                                                data-student-name="<?= htmlspecialchars($row['fullname']) ?>">
                                                                Cho làm lại
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-slate-500">Chưa có sinh viên nào làm bài thi này.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
        <div id="sidebar-overlay" class="sidebar-overlay lg:hidden"></div>
    </div>

    <div id="reset-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-orange-100">
                <i class="bi bi-question-diamond-fill text-2xl text-orange-600"></i>
            </div>
            <h3 class="text-2xl font-bold text-slate-800 mt-5">Xác nhận Cho Làm Lại</h3>
            <p class="text-slate-600 mt-2">Bạn có chắc chắn muốn cho sinh viên <strong id="student-name-placeholder" class="text-slate-900"></strong> làm lại bài thi này không? Kết quả hiện tại sẽ bị xóa vĩnh viễn.</p>
            <form id="reset-form" method="POST" class="mt-6">
                <input type="hidden" id="reset-result-id" name="result_id">
                <div class="flex justify-center gap-4">
                    <button type="button" class="cancel-modal-btn bg-slate-200 text-slate-800 font-bold py-2.5 px-6 rounded-lg">Hủy</button>
                    <button type="submit" name="reset_result" class="bg-orange-500 text-white font-bold py-2.5 px-6 rounded-lg hover:bg-orange-600">Xác Nhận</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', () => sidebar.classList.toggle('open'));
        }
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => sidebar.classList.remove('open'));
        }

        const resetModal = document.getElementById('reset-modal');
        const openResetBtns = document.querySelectorAll('.reset-btn');
        const cancelModalBtn = resetModal.querySelector('.cancel-modal-btn');
        const resetForm = document.getElementById('reset-form');

        openResetBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const resultId = btn.dataset.resultId;
                const studentName = btn.dataset.studentName;

                // Điền thông tin vào modal
                document.getElementById('student-name-placeholder').textContent = studentName;
                document.getElementById('reset-result-id').value = resultId;

                // Giữ lại quiz_id trên URL sau khi submit
                const currentUrl = new URL(window.location.href);
                resetForm.action = `results.php?quiz_id=${currentUrl.searchParams.get('quiz_id')}`;

                resetModal.classList.remove('hidden');
            });
        });

        const closeModal = () => {
            resetModal.classList.add('hidden');
        };

        cancelModalBtn.addEventListener('click', closeModal);
        resetModal.addEventListener('click', (e) => {
            if (e.target === resetModal) {
                closeModal();
            }
        });
    </script>
</body>

</html>