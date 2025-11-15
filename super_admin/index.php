<?php
include(__DIR__ . '/../config/session.php');
include(__DIR__ . '/../config/db.php');

// 1. KIỂM TRA QUYỀN SUPER ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    // SỬA LỖI: Đường dẫn chuyển hướng về trang đăng nhập
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Super Admin';
$message = null;
$message_type = '';

// 2. XỬ LÝ HÀNH ĐỘNG DUYỆT TÀI KHOẢN GIẢNG VIÊN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_lecturer'])) {
    $lecturer_id_to_approve = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if ($lecturer_id_to_approve) {
        try {
            // Cập nhật trạng thái 'active' của giảng viên thành 1
            $stmt = $conn->prepare("UPDATE users SET active = 1 WHERE id = ? AND role = 'admin'");
            $stmt->bind_param("i", $lecturer_id_to_approve);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Duyệt tài khoản giảng viên thành công!";
                $message_type = 'success';
            } else {
                throw new Exception("Không tìm thấy tài khoản hoặc không thể cập nhật.");
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $message = "Lỗi CSDL khi duyệt tài khoản.";
            $message_type = 'error';
            error_log("Approve lecturer error: " . $e->getMessage());
        }
    }
}
// Xử lý xóa sinh viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {

    $student_id_to_delete = filter_input(INPUT_POST, 'user_id_delete', FILTER_VALIDATE_INT);

    if ($student_id_to_delete) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->bind_param("i", $student_id_to_delete);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Xóa tài khoản sinh viên thành công!";
            $message_type = 'success';
        } else {
            $message = "Không thể xóa tài khoản sinh viên.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}
// 3. LẤY DỮ LIỆU ĐỂ HIỂN THỊ
// Danh sách 1: Giảng viên chờ duyệt
$pending_lecturers = [];
$sql_pending = "SELECT u.id, u.fullname, u.email FROM users u WHERE u.role = 'admin' AND u.active = 0 ORDER BY u.created_at ASC";
$pending_result = $conn->query($sql_pending);
if ($pending_result) {
    while ($row = $pending_result->fetch_assoc()) {
        $pending_lecturers[] = $row;
    }
}

// Danh sách 2: Tất cả tài khoản bị khóa/chưa kích hoạt
$locked_accounts = [];
$sql_locked = "SELECT id, fullname, username, role FROM users WHERE active = 0 ORDER BY role, created_at DESC";
$locked_result = $conn->query($sql_locked);
if ($locked_result) {
    while ($row = $locked_result->fetch_assoc()) {
        $locked_accounts[] = $row;
    }
}

// Danh sách 3: Toàn bộ giảng viên trong hệ thống
$all_lecturers = [];
$sql_lecturers = "SELECT u.id, u.fullname, u.email, u.active, ld.department 
                  FROM users u 
                  LEFT JOIN lecturer_details ld ON u.id = ld.user_id 
                  WHERE u.role = 'admin' 
                  ORDER BY u.fullname ASC";
$lecturers_result = $conn->query($sql_lecturers);
if ($lecturers_result) {
    while ($row = $lecturers_result->fetch_assoc()) {
        $all_lecturers[] = $row;
    }
}

// Danh sách 4: Toàn bộ sinh viên trong hệ thống
$all_students = [];
$sql_students = "SELECT id, fullname, student_id, major FROM users WHERE role = 'student' ORDER BY fullname ASC";
$students_result = $conn->query($sql_students);
if ($students_result) {
    while ($row = $students_result->fetch_assoc()) {
        $all_students[] = $row;
    }
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzy - Super Admin</title>
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
        <aside id="sidebar" class="sidebar bg-slate-800 text-white w-72 space-y-6 py-7 px-4 absolute inset-y-0 left-0 transform lg:relative lg:translate-x-0">
            <a href="#" class="text-white flex items-center space-x-3 px-4">
                <i class="bi bi-shield-lock-fill text-3xl text-yellow-400"></i>
                <span class="text-2xl font-extrabold">Super Admin</span>
            </a>
            <nav class="pt-6">
                <h3 class="px-4 text-slate-400 text-sm uppercase font-semibold mb-3">Chức Năng</h3>
                <div class="px-4 text-sm text-slate-300 space-y-4">
                    <ul class="list-disc list-inside space-y-2">
                        <li><strong>Duyệt Giảng viên:</strong> Kích hoạt các tài khoản giảng viên mới đăng ký.</li>
                        <li><strong>Quản lý Tài khoản:</strong> Xem và quản lý toàn bộ tài khoản trong hệ thống.</li>
                    </ul>
                </div>
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
                <div class="container mx-auto">
                    <!-- Lời chào -->
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-8 rounded-2xl shadow-lg mb-8 text-center">
                        <h2 class="text-4xl font-bold mb-2">Ngày Mới Tốt Lành!</h2>
                        <p class="text-lg opacity-90">Chào mừng bạn đến với hệ thống Quizzy, Bạn đang đăng nhập dưới quyền quản trị viên.</p>
                    </div>

                    <?php if ($message): ?>
                        <div id="alert-message" class="mb-6 p-4 rounded-lg flex justify-between items-center <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <span><?= htmlspecialchars($message) ?></span>
                            <button onclick="document.getElementById('alert-message').style.display='none'"><i class="bi bi-x-lg"></i></button>
                        </div>
                    <?php endif; ?>

                    <!-- Bố cục 4 ô chức năng -->
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">

                        <!-- Card 1: Duyệt tài khoản giảng viên -->
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <h3 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="bi bi-person-check-fill text-orange-500"></i> Tài Khoản Chờ Duyệt</h3>
                            <div class="overflow-x-auto max-h-96">
                                <table class="w-full text-sm text-left text-slate-500">
                                    <thead class="text-xs text-slate-700 uppercase bg-slate-50 sticky top-0">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Họ Tên</th>
                                            <th scope="col" class="px-4 py-3">Vai trò</th>
                                            <th scope="col" class="px-4 py-3">Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <?php if (!empty($pending_lecturers)): ?>
                                            <?php foreach ($pending_lecturers as $lecturer): ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($lecturer['fullname']) ?></td>
                                                    <td class="px-4 py-3"><span class="bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Giảng viên</span></td>
                                                    <td class="px-4 py-3">
                                                        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn duyệt tài khoản này?');">
                                                            <input type="hidden" name="user_id" value="<?= $lecturer['id'] ?>">
                                                            <button type="submit" name="approve_lecturer" class="font-medium text-green-600 hover:underline">Duyệt</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="px-4 py-4 text-center text-slate-500">Không có tài khoản nào chờ duyệt.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Card 2: Tài khoản bị khóa -->
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <h3 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="bi bi-person-fill-lock text-red-500"></i> Tài Khoản Bị Khóa</h3>
                            <div class="overflow-x-auto max-h-96">
                                <table class="w-full text-sm text-left text-slate-500">
                                    <thead class="text-xs text-slate-700 uppercase bg-slate-50 sticky top-0">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Họ Tên</th>
                                            <th scope="col" class="px-4 py-3">Tên đăng nhập</th>
                                            <th scope="col" class="px-4 py-3">Vai trò</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <?php if (!empty($locked_accounts)): ?>
                                            <?php foreach ($locked_accounts as $account): ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($account['fullname']) ?></td>
                                                    <td class="px-4 py-3"><?= htmlspecialchars($account['username']) ?></td>
                                                    <td class="px-4 py-3">
                                                        <?php if ($account['role'] === 'admin'): ?>
                                                            <span class="bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Giảng viên</span>
                                                        <?php else: ?>
                                                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Sinh viên</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="px-4 py-4 text-center text-slate-500">Không có tài khoản nào bị khóa.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Card 3: Danh sách giảng viên -->
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <h3 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="bi bi-person-video3 text-teal-500"></i> Giảng Viên Hệ Thống</h3>
                            <div class="overflow-x-auto max-h-96">
                                <table class="w-full text-sm text-left text-slate-500">
                                    <thead class="text-xs text-slate-700 uppercase bg-slate-50 sticky top-0">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Họ Tên</th>
                                            <th scope="col" class="px-4 py-3">Email</th>
                                            <th scope="col" class="px-4 py-3">Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <?php if (!empty($all_lecturers)): ?>
                                            <?php foreach ($all_lecturers as $lecturer): ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($lecturer['fullname']) ?></td>
                                                    <td class="px-4 py-3"><?= htmlspecialchars($lecturer['email']) ?></td>
                                                    <td class="px-4 py-3">
                                                        <?php if ($lecturer['active']): ?>
                                                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Hoạt động</span>
                                                        <?php else: ?>
                                                            <span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Chờ duyệt</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="px-4 py-4 text-center text-slate-500">Chưa có giảng viên nào trong hệ thống.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Card 4: Danh sách sinh viên -->
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <h3 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="bi bi-people-fill text-blue-500"></i> Sinh Viên Hệ Thống</h3>
                            <div class="overflow-x-auto max-h-96">
                                <table class="w-full text-sm text-left text-slate-500">
                                    <thead class="text-xs text-slate-700 uppercase bg-slate-50 sticky top-0">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">MSSV</th>
                                            <th scope="col" class="px-4 py-3">Họ Tên</th>
                                            <th scope="col" class="px-4 py-3">Chuyên ngành</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <?php if (!empty($all_students)): ?>
                                            <?php foreach ($all_students as $student): ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($student['student_id']) ?></td>
                                                    <td class="px-4 py-3"><?= htmlspecialchars($student['fullname']) ?></td>
                                                    <td class="px-4 py-3"><?= htmlspecialchars($student['major']) ?></td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-3">
                                                            <button type="button" class="edit-student-btn font-medium text-blue-600 hover:underline"
                                                                data-id="<?= $student['id'] ?>"
                                                                data-name="<?= htmlspecialchars($student['fullname']) ?>">Sửa</button>

                                                            <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA VĨNH VIỄN sinh viên này?');" class="inline">
                                                                <input type="hidden" name="user_id_delete" value="<?= $student['id'] ?>">
                                                                <button type="submit" name="delete_student" class="font-medium text-red-600 hover:underline">Xóa</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="px-4 py-4 text-center text-slate-500">Chưa có sinh viên nào trong hệ thống.</td>
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
        <div id="sidebar-overlay" class="sidebar-overlay lg:hidden"></div>
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
    </script>
</body>

</html>