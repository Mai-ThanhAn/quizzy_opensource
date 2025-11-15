<?php
include(__DIR__ . '/../config/session.php');
include(__DIR__ . '/../config/db.php');

// 1. Kiểm tra quyền Giảng viên (admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
$lecturer_id = (int)$_SESSION['user_id'];
$message = null;
$message_type = '';

// 2. Xử lý khi giảng viên thêm lớp học mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $class_id = trim($_POST['class_id'] ?? '');
    $class_name = trim($_POST['class_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!empty($class_id) && !empty($class_name)) {
        try {
            $check_stmt = $conn->prepare("SELECT class_id FROM class WHERE class_id = ?");
            $check_stmt->bind_param("s", $class_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                throw new Exception("Mã lớp '{$class_id}' đã tồn tại trong hệ thống.");
            }
            $check_stmt->close();

            $stmt = $conn->prepare("INSERT INTO class (class_id, class_name, description, users_id, createdby) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $class_id, $class_name, $description, $lecturer_id, $lecturer_id);

            if ($stmt->execute()) {
                $message = "Thêm lớp học '{$class_name}' thành công!";
                $message_type = 'success';
            } else {
                throw new Exception("Không thể thực thi câu lệnh.");
            }
            $stmt->close();
        } catch (Exception $e) {
            $message = "Lỗi: " . $e->getMessage();
            $message_type = 'error';
            error_log("Add class error: " . $e->getMessage());
        }
    } else {
        $message = "Vui lòng nhập đầy đủ Mã lớp và Tên lớp học.";
        $message_type = 'error';
    }
}
// 3. XỬ LÝ: Xóa lớp học
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $class_id_to_delete = trim($_POST['class_id_delete'] ?? '');

    if (!empty($class_id_to_delete)) {
        $conn->begin_transaction(); // Bắt đầu giao dịch để đảm bảo an toàn dữ liệu
        try {
            // Bước 3.1: Xóa sinh viên khỏi lớp này trong bảng class_relation trước
            $stmt_rel = $conn->prepare("DELETE FROM class_relation WHERE class_id = ?");
            $stmt_rel->bind_param("s", $class_id_to_delete);
            $stmt_rel->execute();
            $stmt_rel->close();

            // Bước 3.2: Xóa lớp học trong bảng class (Chỉ xóa nếu giảng viên sở hữu lớp đó)
            $stmt_class = $conn->prepare("DELETE FROM class WHERE class_id = ? AND users_id = ?");
            $stmt_class->bind_param("si", $class_id_to_delete, $lecturer_id);
            $stmt_class->execute();

            if ($stmt_class->affected_rows > 0) {
                $conn->commit(); // Xác nhận xóa thành công cả 2 bảng
                $message = "Đã xóa lớp học và danh sách sinh viên liên quan!";
                $message_type = 'success';
            } else {
                throw new Exception("Không tìm thấy lớp hoặc bạn không có quyền xóa lớp này.");
            }
            $stmt_class->close();
        } catch (Exception $e) {
            $conn->rollback(); // Hoàn tác nếu có lỗi
            $message = "Lỗi CSDL: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 4.Lấy danh sách lớp và đếm số sinh viên trong mỗi lớp
$classes = [];
$sql_classes = "SELECT 
                    c.class_id, 
                    c.class_name, 
                    c.description,
                    (SELECT COUNT(cr.idclass_relation) FROM class_relation cr WHERE cr.class_id = c.class_id) as student_count
                FROM class c
                WHERE c.users_id = ? 
                ORDER BY c.class_name ASC";
$stmt_classes = $conn->prepare($sql_classes);
$stmt_classes->bind_param("i", $lecturer_id);
$stmt_classes->execute();
$classes_result = $stmt_classes->get_result();
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $classes[] = $row;
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
    <title>Quản Lý Lớp Học</title>
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
                    <h2 class="text-3xl font-bold text-slate-800 mb-6">Quản Lý Lớp Học</h2>
                    <p class="text-md font-italic mb-6 text-red-500">
                        Lớp học giúp sinh viên có thể xem được bài kiểm tra dành cho mình nếu sinh viên đã tham gia lớp đó.
                    </p>
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Cột trái: Form thêm lớp -->
                        <div class="lg:col-span-1">
                            <div class="bg-white p-6 rounded-2xl shadow-lg">
                                <h3 class="text-xl font-bold text-slate-800 mb-4">Thêm Lớp Học</h3>
                                <form method="POST" class="space-y-4">
                                    <div>
                                        <label for="class_id" class="block text-sm font-medium text-slate-700 mb-1">Mã lớp:</label>
                                        <input type="text" id="class_id" name="class_id" placeholder="Ví dụ: CNTT.CQ.01" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="class_name" class="block text-sm font-medium text-slate-700 mb-1">Tên lớp học:</label>
                                        <input type="text" id="class_name" name="class_name" placeholder="Ví dụ: Lập Trình Web" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Mô tả (tùy chọn):</label>
                                        <textarea id="description" name="description" rows="3" placeholder="Thông tin thêm về lớp học" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                                    </div>
                                    <button type="submit" name="add_class" class="w-full bg-blue-600 text-white font-bold py-2.5 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                        <i class="bi bi-plus-circle-fill"></i> Thêm Lớp
                                    </button>
                                </form>
                                <p class="mt-8 text-warning text-sm text-red-500">
                                    <i class="bi bi-exclamation-diamond-fill"></i>
                                    Lưu ý: Bạn có thể thêm sinh viên vào lớp bằng ba cách sau:
                                <ul class="mt-4 text-warning text-sm text-slate-500">
                                    <li>1. Thêm thông qua file CSV <a href="import_students.php" class="font-medium text-blue-600 hover:underline"> Tại đây.</a></li>
                                    <li>2. Thêm thủ công thông qua trang quản lý danh sách lớp.</li>
                                    <li>3. Hoặc sinh viên có thể tự ghi danh thông qua<a class="font-medium text-red-500"> mã lớp học.</a></li>
                                </ul>
                                </p>
                            </div>
                        </div>

                        <!-- Cột phải: Danh sách lớp -->
                        <div class="lg:col-span-2">
                            <div class="bg-white p-6 rounded-2xl shadow-lg">
                                <h3 class="text-xl font-bold text-slate-800 mb-4">Danh sách lớp học của bạn</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left text-slate-500">
                                        <thead class="text-xs text-slate-700 uppercase bg-slate-100">
                                            <tr>
                                                <th scope="col" class="px-6 py-3">Mã lớp</th>
                                                <th scope="col" class="px-6 py-3">Tên lớp</th>
                                                <th scope="col" class="px-6 py-3 text-center">Số Lượng SV</th>
                                                <th scope="col" class="px-6 py-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($classes)): ?>
                                                <?php foreach ($classes as $class_item): ?>
                                                    <tr class="bg-white border-b hover:bg-slate-50">
                                                        <td class="px-6 py-4 font-medium text-slate-900"><?= htmlspecialchars($class_item['class_id']) ?></td>
                                                        <td class="px-6 py-4"><?= htmlspecialchars($class_item['class_name']) ?></td>
                                                        <td class="px-6 py-4 text-center">
                                                            <span class="bg-blue-100 text-blue-800 font-medium px-3 py-1 rounded-full"><?= $class_item['student_count'] ?></span>
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            <div class="flex items-center gap-4">
                                                                <a href="view_class.php?class_id=<?= htmlspecialchars($class_item['class_id']) ?>" class="font-medium text-blue-600 hover:underline">
                                                                    Xem DS
                                                                </a>
                                                                <button type="button" class="delete-btn font-medium text-red-600 hover:underline"
                                                                    data-id="<?= htmlspecialchars($class_item['class_id']) ?>"
                                                                    data-name="<?= htmlspecialchars($class_item['class_name']) ?>">
                                                                    Xóa
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="px-6 py-4 text-center text-slate-500">Bạn chưa tạo lớp học nào.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
<!-- Modal Xác Nhận Xóa -->
<div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-50">
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full text-center">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
            <i class="bi bi-exclamation-triangle-fill text-2xl text-red-600"></i>
        </div>
        <h3 class="text-2xl font-bold text-slate-800 mt-5">Xác nhận Xóa Lớp</h3>
        <p class="text-slate-600 mt-2">Bạn có chắc chắn muốn xóa lớp <strong id="delete-class-name" class="text-slate-900"></strong>?</p>
        <p class="text-red-500 text-sm mt-1 font-semibold">Cảnh báo: Tất cả sinh viên sẽ bị xóa khỏi lớp này.</p>

        <form method="POST" class="mt-6">
            <input type="hidden" id="delete-class-id" name="class_id_delete">
            <input type="hidden" name="delete_class" value="1">
            <div class="flex justify-center gap-4">
                <button type="button" class="cancel-modal bg-slate-200 text-slate-800 font-bold py-2.5 px-6 rounded-lg hover:bg-slate-300">Hủy</button>
                <button type="submit" class="bg-red-600 text-white font-bold py-2.5 px-6 rounded-lg hover:bg-red-700">Xác Nhận Xóa</button>
            </div>
        </form>
    </div>
</div>

</html>
<script>
    const deleteModal = document.getElementById('delete-modal');
    const cancelBtns = document.querySelectorAll('.cancel-modal');
    const deleteBtns = document.querySelectorAll('.delete-btn');

    // Mở modal xóa
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            document.getElementById('delete-class-id').value = id;
            document.getElementById('delete-class-name').textContent = `"${name}"`;
            deleteModal.classList.remove('hidden');
        });
    });

    // Đóng modal
    cancelBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            deleteModal.classList.add('hidden');
        });
    });

    // Đóng khi click nền đen bên ngoài
    window.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            deleteModal.classList.add('hidden');
        }
    });
</script>