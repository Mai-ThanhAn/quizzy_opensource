<?php
include(__DIR__ . '/../config/session.php');include(__DIR__ . '/../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
$lecturer_id = (int)$_SESSION['user_id'];
$message = null;
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bank'])) {
    $bank_id_to_delete = filter_input(INPUT_POST, 'bank_id', FILTER_VALIDATE_INT);

    if ($bank_id_to_delete) {
        try {
            $stmt = $conn->prepare("DELETE FROM question_banks WHERE id = ?");
            $stmt->bind_param("i", $bank_id_to_delete);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Xóa ngân hàng câu hỏi thành công!";
                $message_type = 'success';
            } else {
                throw new Exception("Không tìm thấy ngân hàng câu hỏi để xóa.");
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $message = "Lỗi CSDL: Không thể xóa ngân hàng câu hỏi.";
            $message_type = 'error';
            error_log("Delete bank error: " . $e->getMessage());
        }
    }
}
$banks = [];
$sql = "SELECT 
            b.id, 
            b.name, 
            b.created_at,
            (SELECT COUNT(q.id) FROM questions q WHERE q.bank_id = b.id) as question_count
        FROM question_banks b 
        WHERE b.user_id = ?  -- Chỉ lấy các ngân hàng của giảng viên này
        ORDER BY b.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $banks[] = $row;
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Ngân Hàng Câu Hỏi - Quizzy Admin</title>
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

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex justify-between items-center p-4 bg-white border-b lg:justify-end">
                <div class="flex items-center space-x-4">
                    <span class="font-semibold text-slate-700">Xin chào, <?= $admin_name ?></span>
                    <i class="bi bi-person-circle text-3xl text-slate-500"></i>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto bg-slate-100 p-6 md:p-8">
                <div class="container mx-auto max-w-6xl">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-3xl font-bold text-slate-800">Quản Lý Ngân Hàng Câu Hỏi</h2>
                        <a href="import_questions.php" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                           <i class="bi bi-plus-circle-fill"></i> Tạo Ngân Hàng Mới
                        </a>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-2xl shadow-lg">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-slate-500">
                                <thead class="text-xs text-slate-700 uppercase bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Tên Ngân Hàng Câu Hỏi</th>
                                        <th scope="col" class="px-6 py-3 text-center">Số Lượng Câu Hỏi</th>
                                        <th scope="col" class="px-6 py-3">Ngày Tạo</th>
                                        <th scope="col" class="px-6 py-3">Hành Động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($banks)): ?>
                                        <?php foreach ($banks as $bank): ?>
                                            <tr class="bg-white border-b hover:bg-slate-50">
                                                <td class="px-6 py-4 font-semibold text-slate-900">
                                                    <?= htmlspecialchars($bank['name']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="bg-blue-100 text-blue-800 font-medium px-3 py-1 rounded-full"><?= $bank['question_count'] ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-slate-600">
                                                    <?= date("d/m/Y", strtotime($bank['created_at'])) ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-4">
                                                        <a href="edit_bank.php?bank_id=<?= $bank['id'] ?>" class="font-medium text-blue-600 hover:underline">Quản lý</a>
                                                        <!-- THÊM MỚI: Nút Xóa -->
                                                        <button type="button" class="delete-btn font-medium text-red-600 hover:underline" 
                                                                data-id="<?= $bank['id'] ?>" 
                                                                data-name="<?= htmlspecialchars($bank['name']) ?>">
                                                            Xóa
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-slate-500">Chưa có ngân hàng câu hỏi nào. Hãy tạo một ngân hàng mới.</td>
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
    
    <!--  Modal Xóa Ngân Hàng -->
    <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="bi bi-exclamation-triangle-fill text-2xl text-red-600"></i>
            </div>
            <h3 class="text-2xl font-bold text-slate-800 mt-5">Xác nhận Xóa</h3>
            <p class="text-slate-600 mt-2">Bạn có chắc chắn muốn xóa ngân hàng <strong id="delete-bank-name" class="text-slate-900"></strong> không? Các câu hỏi sẽ không bị mất.</p>
            <form method="POST" class="mt-6">
                <input type="hidden" id="delete-bank-id" name="bank_id">
                <div class="flex justify-center gap-4">
                    <button type="button" class="cancel-btn bg-slate-200 text-slate-800 font-bold py-2.5 px-6 rounded-lg">Hủy</button>
                    <button type="submit" name="delete_bank" class="bg-red-600 text-white font-bold py-2.5 px-6 rounded-lg">Xác Nhận</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const deleteModal = document.getElementById('delete-modal');
        const openDeleteBtns = document.querySelectorAll('.delete-btn');
        const cancelDeleteBtns = deleteModal.querySelectorAll('.cancel-btn');

        openDeleteBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('delete-bank-id').value = btn.dataset.id;
                document.getElementById('delete-bank-name').textContent = `"${btn.dataset.name}"`;
                deleteModal.classList.remove('hidden');
            });
        });

        const closeModal = () => {
            deleteModal.classList.add('hidden');
        };

        cancelDeleteBtns.forEach(btn => btn.addEventListener('click', closeModal));
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                closeModal();
            }
        });
    </script>
</body>
</html>