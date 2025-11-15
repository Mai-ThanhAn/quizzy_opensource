<?php
include(__DIR__ . '/../config/session.php');include(__DIR__ . '/../config/db.php');

// 1. Kiểm tra quyền Giảng viên (admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
$lecturer_id = (int)$_SESSION['user_id'];
$message = null;
$message_type = '';
$class_info = null;
$students = [];

// 2. Lấy class_id từ URL và xác thực
$class_id = trim($_GET['class_id'] ?? '');
if (empty($class_id)) {
    header("Location: manage_classes.php");
    exit();
}

try {
    // 3. Xác thực giảng viên là chủ sở hữu của lớp học này
    $stmt_class = $conn->prepare("SELECT class_id, class_name FROM class WHERE class_id = ? AND users_id = ?");
    $stmt_class->bind_param("si", $class_id, $lecturer_id);
    $stmt_class->execute();
    $class_res = $stmt_class->get_result();
    if ($class_res->num_rows === 0) {
        throw new Exception("Bạn không có quyền xem danh sách lớp này.");
    }
    $class_info = $class_res->fetch_assoc();
    $stmt_class->close();

    // Xử lý thêm sinh viên thủ công
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
        $student_id_to_add = trim($_POST['student_id'] ?? '');
        if (!empty($student_id_to_add)) {
            // Tìm user_id của sinh viên
            $stmt_find = $conn->prepare("SELECT id FROM users WHERE student_id = ? AND role = 'user'");
            $stmt_find->bind_param("s", $student_id_to_add);
            $stmt_find->execute();
            $user_res = $stmt_find->get_result();

            if ($user_row = $user_res->fetch_assoc()) {
                $user_id_found = $user_row['id'];

                // Kiểm tra xem đã có trong lớp chưa
                $stmt_check = $conn->prepare("SELECT idclass_relation FROM class_relation WHERE user_id = ? AND class_id = ?");
                $stmt_check->bind_param("is", $user_id_found, $class_id);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    throw new Exception("Sinh viên này đã có trong lớp.");
                }

                // Thêm vào lớp
                $stmt_add = $conn->prepare("INSERT INTO class_relation (user_id, class_id) VALUES (?, ?)");
                $stmt_add->bind_param("is", $user_id_found, $class_id);
                if ($stmt_add->execute()) {
                    $message = "Thêm sinh viên thành công!";
                    $message_type = 'success';
                } else {
                    throw new Exception("Lỗi khi thêm sinh viên vào lớp.");
                }
            } else {
                throw new Exception("Không tìm thấy sinh viên với MSSV này.");
            }
        } else {
            throw new Exception("Vui lòng nhập Mã số sinh viên.");
        }
    }

    // 4. Lấy danh sách sinh viên thuộc lớp này
    $stmt_students = $conn->prepare(
        "SELECT u.student_id, u.fullname, u.email, u.major 
         FROM users u
         JOIN class_relation cr ON u.id = cr.user_id
         WHERE cr.class_id = ?
         ORDER BY SUBSTRING_INDEX(u.fullname, ' ', -1) ASC"
    );
    $stmt_students->bind_param("s", $class_id);
    $stmt_students->execute();
    $students_result = $stmt_students->get_result();
    if ($students_result) {
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    $stmt_students->close();
} catch (Exception $e) {
    $message = $e->getMessage();
    $message_type = 'error';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách lớp: <?= htmlspecialchars($class_info['class_name'] ?? 'Không rõ') ?></title>
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
        <aside class="bg-slate-800 text-white w-72 space-y-6 py-7 px-4">
            <a href="index.php" class="text-white flex items-center space-x-3 px-4">
                <i class="bi bi-bookmark-star-fill text-3xl text-red-500"></i>
                <span class="text-2xl font-extrabold">Giảng Viên</span>
            </a>
            <nav class="pt-6">
                <a href="manage_classes.php" class="flex items-center space-x-3 py-3 px-4 rounded-lg text-slate-200 hover:bg-slate-700">
                    <i class="bi bi-arrow-left"></i><span>Quản lý Lớp học</span>
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
                <br class="container mx-auto max-w-6xl">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-3xl font-bold text-slate-800">Danh Sách Sinh Viên</h2>
                        <p class="text-slate-600">Lớp: <span class="font-semibold text-blue-600"><?= htmlspecialchars($class_info['class_name']) ?> (<?= htmlspecialchars($class_info['class_id']) ?>)</span></p>
                    </div>
                </div>
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1">
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <h3 class="text-xl font-bold text-slate-800 mb-4">Thêm sinh viên</h3>
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label for="student_id" class="block text-sm font-medium text-slate-700 mb-1">Mã số sinh viên:</label>
                                    <input type="text" id="student_id" name="student_id" placeholder="Nhập MSSV của sinh viên" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <button type="submit" name="add_student" class="w-full bg-blue-600 text-white font-bold py-2.5 px-4 rounded-lg hover:bg-blue-700">
                                    <i class="bi bi-plus-lg"></i> Thêm vào lớp
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                </br>
                <div class="lg:col-span-2">
                    <div class="bg-white p-6 rounded-2xl shadow-lg">
                        <h3 class="text-xl font-bold text-slate-800 mb-4">Danh sách sinh viên trong lớp</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-slate-500">
                                <thead class="text-xs text-slate-700 uppercase bg-slate-100">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 w-16">STT</th>
                                        <th scope="col" class="px-6 py-3">MSSV</th>
                                        <th scope="col" class="px-6 py-3">Họ và Tên</th>
                                        <th scope="col" class="px-6 py-3">Email</th>
                                        <th scope="col" class="px-6 py-3">Khóa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($students)): ?>
                                        <?php $stt = 1; foreach ($students as $student): ?>
                                            <tr class="bg-white border-b hover:bg-slate-50">
                                                <td class="px-4 py-4 text-center font-medium text-slate-900"><?= $stt++ ?></td>
                                                <td class="px-6 py-4 font-medium text-slate-900"><?= htmlspecialchars($student['student_id']) ?></td>
                                                <td class="px-6 py-4"><?= htmlspecialchars($student['fullname']) ?></td>
                                                <td class="px-6 py-4"><?= htmlspecialchars($student['email']) ?></td>
                                                <td class="px-6 py-4"><?= htmlspecialchars($student['major']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-slate-500">Lớp này chưa có sinh viên nào.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
        </div>
        </main>
    </div>
    </div>
</body>

</html>