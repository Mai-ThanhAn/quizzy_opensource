<?php
include(__DIR__ . '/../config/session.php');
include(__DIR__ . '/../config/db.php');

// 1️⃣ Kiểm tra quyền Giảng viên (admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
$user_id = (int)$_SESSION['user_id'];
$message = null;
$message_type = '';

// 2️⃣ Cập nhật thông tin khi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $school = trim($_POST['school'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $class = trim($_POST['class'] ?? '');

    if (!empty($fullname) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $conn->begin_transaction();
        try {
            // Cập nhật bảng users
            $stmt_user = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $stmt_user->bind_param("ssi", $fullname, $email, $user_id);
            $stmt_user->execute();
            $stmt_user->close();

            // Kiểm tra có dữ liệu trong lecturer_details chưa
            $check_stmt = $conn->prepare("SELECT users_id FROM lecturer_details WHERE users_id = ?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $update_stmt = $conn->prepare("UPDATE lecturer_details SET school = ?, department = ?, class = ? WHERE users_id = ?");
                $update_stmt->bind_param("sssi", $school, $department, $class, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO lecturer_details (school, department, class, users_id) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("sssi", $school, $department, $class, $user_id);
                $insert_stmt->execute();
                $insert_stmt->close();
            }

            $check_stmt->close();
            $conn->commit();

            $_SESSION['fullname'] = $fullname;
            $admin_name = $fullname;

            $message = "Cập nhật thông tin cá nhân thành công!";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Đã xảy ra lỗi khi cập nhật thông tin.";
            $message_type = 'error';
            error_log("Profile update error: " . $e->getMessage());
        }
    } else {
        $message = "Họ tên và Email là bắt buộc và Email phải hợp lệ.";
        $message_type = 'error';
    }
}

// 3️⃣ Lấy thông tin giảng viên
$sql = "SELECT u.fullname, u.email, ld.school, ld.department, ld.class
        FROM users u
        LEFT JOIN lecturer_details ld ON u.id = ld.users_id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$lecturer_info = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Tin Cá Nhân - Quizzy Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Be Vietnam Pro', sans-serif; }
    </style>
</head>
<body class="bg-slate-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="bg-slate-800 text-white w-72 space-y-6 py-7 px-4">
            <a href="index.php" class="text-white flex items-center space-x-3 px-4">
                <i class="bi bi-card-checklist text-3xl"></i>
                <span class="text-2xl font-extrabold">Quizzy Admin</span>
            </a>
            <nav class="pt-6">
                <a href="index.php" class="flex items-center space-x-3 py-3 px-4 rounded-lg text-slate-200 hover:bg-slate-700">
                    <i class="bi bi-house-door-fill"></i><span>Bảng điều khiển</span>
                </a>
            </nav>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex justify-between items-center p-4 bg-white border-b lg:justify-end">
                <div class="flex items-center space-x-4">
                    <span class="font-semibold text-slate-700">Xin chào, <?= $admin_name ?></span>
                    <i class="bi bi-person-circle text-3xl text-slate-500"></i>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-slate-100 p-6 md:p-8">
                <div class="container mx-auto max-w-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-3xl font-bold text-slate-800">Thông Tin Cá Nhân</h2>
                        <a href="index.php" class="text-blue-600 hover:underline font-semibold flex items-center gap-2">
                            <i class="bi bi-arrow-left"></i> Quay lại
                        </a>
                    </div>

                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php
                        $fullname_val   = htmlspecialchars($lecturer_info['fullname'] ?? '');
                        $email_val      = htmlspecialchars($lecturer_info['email'] ?? '');
                        $school_val     = htmlspecialchars($lecturer_info['school'] ?? '');
                        $department_val = htmlspecialchars($lecturer_info['department'] ?? '');
                        $class_val      = htmlspecialchars($lecturer_info['class'] ?? '');
                    ?>

                    <div class="bg-white p-8 rounded-2xl shadow-lg">
                        <!-- View Mode -->
                        <div id="profile-view">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                                <div>
                                    <p class="text-sm text-slate-500">Họ và tên</p>
                                    <p class="text-lg font-semibold text-slate-800"><?= $fullname_val ?: '<span class="text-slate-400">Chưa cập nhật</span>' ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-slate-500">Email</p>
                                    <p class="text-lg font-semibold text-slate-800"><?= $email_val ?: '<span class="text-slate-400">Chưa cập nhật</span>' ?></p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <p class="text-sm text-slate-500">Trường / Viện</p>
                                <p class="text-base text-slate-700"><?= $school_val ?: '<span class="text-slate-400">Chưa cập nhật</span>' ?></p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm text-slate-500">Khoa / Bộ môn</p>
                                    <p class="text-base text-slate-700"><?= $department_val ?: '<span class="text-slate-400">Chưa cập nhật</span>' ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-slate-500">Lớp phụ trách</p>
                                    <p class="text-base text-slate-700"><?= $class_val ?: '<span class="text-slate-400">Chưa cập nhật</span>' ?></p>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button id="edit-btn" type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded-lg">
                                    <i class="bi bi-pencil-fill mr-2"></i> Sửa thông tin
                                </button>
                            </div>
                        </div>

                        <!-- Edit Mode -->
                        <form id="profile-form" method="POST" class="hidden" novalidate>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                                <div>
                                    <label for="fullname" class="block text-sm font-medium text-slate-700 mb-1">Họ và tên</label>
                                    <input type="text" id="fullname" name="fullname" value="<?= $fullname_val ?>" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                                    <input type="email" id="email" name="email" value="<?= $email_val ?>" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="school" class="block text-sm font-medium text-slate-700 mb-1">Trường / Viện</label>
                                <input type="text" id="school" name="school" value="<?= $school_val ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="department" class="block text-sm font-medium text-slate-700 mb-1">Khoa / Bộ môn</label>
                                    <input type="text" id="department" name="department" value="<?= $department_val ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label for="class" class="block text-sm font-medium text-slate-700 mb-1">Lớp phụ trách</label>
                                    <input type="text" id="class" name="class" value="<?= $class_val ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <div class="pt-4 flex justify-end gap-3">
                                <button id="cancel-btn" type="button" class="bg-gray-300 hover:bg-gray-400 text-slate-800 font-semibold py-2 px-4 rounded-lg">Hủy</button>
                                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">
                                    <i class="bi bi-save-fill mr-2"></i> Lưu Thay Đổi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const editBtn = document.getElementById('edit-btn');
        const cancelBtn = document.getElementById('cancel-btn');
        const form = document.getElementById('profile-form');
        const viewBox = document.getElementById('profile-view');

        editBtn?.addEventListener('click', () => {
            viewBox.classList.add('hidden');
            form.classList.remove('hidden');
            form.querySelector('input[name="fullname"]').focus();
        });

        cancelBtn?.addEventListener('click', () => {
            form.classList.add('hidden');
            viewBox.classList.remove('hidden');
            form.reset();
        });
    </script>
</body>
</html>
