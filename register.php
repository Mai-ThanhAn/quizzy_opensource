<?php
include(__DIR__ . '/config/session.php');
include(__DIR__ . '/config/db.php');

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        $role_type = $_POST['role_type'] ?? 'student';
        $username = trim($_POST['username']);
        $password = hash('sha256', trim($_POST['password']));
        $confirm = hash('sha256', trim($_POST['confirm']));
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);

        if ($password !== $confirm) {
            throw new Exception("Mật khẩu xác nhận không khớp!");
        }

        // Kiểm tra username và email
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            throw new Exception("Tên đăng nhập hoặc email đã tồn tại!");
        }
        $check->close();

        if ($role_type === 'student') {
            $student_id = trim($_POST['student_id']);
            $major = trim($_POST['major']);
            $role = 'student'; // Role 'student' theo enum CSDL
            $active = 1; // Sinh viên được active ngay

            // Kiểm tra student_id
            $check_sid = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
            $check_sid->bind_param("s", $student_id);
            $check_sid->execute();
            if ($check_sid->get_result()->num_rows > 0) {
                throw new Exception("Mã số sinh viên đã tồn tại!");
            }
            $check_sid->close();

            $stmt = $conn->prepare("INSERT INTO users(username, password, fullname, email, student_id, major, role, active) VALUES(?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssi", $username, $password, $fullname, $email, $student_id, $major, $role, $active);

            if (!$stmt->execute()) {
                throw new Exception("Lỗi khi tạo tài khoản sinh viên.");
            }
            $stmt->close();

            $conn->commit();
            $_SESSION['register_success'] = "Đăng ký thành công! Vui lòng đăng nhập.";
            header("Location: login.php");
            exit();

        } elseif ($role_type === 'lecturer') {
            $school = trim($_POST['school']);
            $department = trim($_POST['department']);
            $role = 'admin'; // Giảng viên có vai trò 'admin'
            $active = 0; // Giảng viên cần được duyệt

            // Thêm vào bảng users
            $stmtUser = $conn->prepare("INSERT INTO users(username, password, fullname, email, role, active) VALUES(?, ?, ?, ?, ?, ?)");
            $stmtUser->bind_param("sssssi", $username, $password, $fullname, $email, $role, $active);
            if (!$stmtUser->execute()) {
                throw new Exception("Lỗi khi tạo tài khoản giảng viên.");
            }
            $new_user_id = $stmtUser->insert_id;
            $stmtUser->close();

            $stmtLecturer = $conn->prepare("INSERT INTO lecturer_details(school, department, users_id) VALUES (?, ?, ?)");
            $stmtLecturer->bind_param("ssi", $school, $department, $new_user_id);
            if (!$stmtLecturer->execute()) {
                throw new Exception("Lỗi khi lưu thông tin chi tiết của giảng viên.");
            }
            $stmtLecturer->close();

            $conn->commit();
            $_SESSION['register_success'] = "Đăng ký thành công! Tài khoản của bạn đang chờ quản trị viên phê duyệt.";
            header("Location: login.php");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Quizzy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Be Vietnam Pro', sans-serif; } </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex flex-col items-center justify-center p-4">

    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <a href="/index.php" class="inline-flex items-center gap-2 text-3xl font-bold text-blue-600">
                <i class="bi bi-card-checklist"></i><span>Quizzy</span>
            </a>
            <p class="text-slate-500 mt-2">Tạo tài khoản mới để bắt đầu.</p>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-xl">
            <form id="register-form" method="post" action="" class="space-y-4">
                
                <?php if ($message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                        <p><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Role Selector -->
                <div class="grid grid-cols-2 gap-2 rounded-lg bg-slate-100 p-1">
                    <div>
                        <input type="radio" name="role_type" id="role_student" value="student" class="sr-only" checked>
                        <label for="role_student" class="role-label text-center block w-full cursor-pointer rounded-md p-2 text-sm font-semibold text-slate-500">Tôi là Sinh viên</label>
                    </div>
                    <div>
                        <input type="radio" name="role_type" id="role_lecturer" value="lecturer" class="sr-only">
                        <label for="role_lecturer" class="role-label text-center block w-full cursor-pointer rounded-md p-2 text-sm font-semibold text-slate-500">Tôi là Giảng viên</label>
                    </div>
                </div>

                <!-- Common Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="fullname" class="block text-sm font-medium text-slate-700 mb-1">Họ và tên</label>
                        <input type="text" name="fullname" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Tên đăng nhập</label>
                        <input type="text" name="username" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                    </div>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                </div>

                <!-- Student Fields -->
                <div id="student-fields" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="student_id" class="block text-sm font-medium text-slate-700 mb-1">Mã số sinh viên</label>
                            <input type="text" name="student_id" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label for="major" class="block text-sm font-medium text-slate-700 mb-1">Chuyên ngành</label>
                            <input type="text" name="major" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                        </div>
                    </div>
                </div>

                <!-- Lecturer Fields -->
                <div id="lecturer-fields" class="hidden space-y-4">
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="school" class="block text-sm font-medium text-slate-700 mb-1">Trường</label>
                            <input type="text" name="school" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                         <div>
                            <label for="department" class="block text-sm font-medium text-slate-700 mb-1">Viện/Khoa/Bộ môn</label>
                            <input type="text" name="department" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <!-- Password Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu</label>
                        <input type="password" name="password" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                    </div>
                     <div>
                        <label for="confirm" class="block text-sm font-medium text-slate-700 mb-1">Xác nhận mật khẩu</label>
                        <input type="password" name="confirm" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                    </div>
                </div>
                
                <div class="!mt-6">
                    <button type="submit" class="flex w-full justify-center rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-blue-700 transition-colors">
                        Tạo tài khoản
                    </button>
                </div>
            </form>

            <p class="mt-8 text-center text-sm text-slate-500">
                Giảng viên đăng ký tài khoản vui lòng đợi
                <a class="font-semibold leading-6 text-yellow-500">tài khoản được kích hoạt</a>
            </p>
            <p class="mt-8 text-center text-sm text-slate-500">
                Đã có tài khoản?
                <a href="login.php" class="font-semibold leading-6 text-blue-600 hover:text-blue-500">Đăng nhập ngay</a>
            </p>
        </div>

         <div class="text-center mt-8 text-sm text-slate-500">
            <p>&copy; <?= date("Y") ?> Quizzy - Viện Công Nghệ Số, TDMU.</p>
        </div>
    </div>
    
    <script>
        const studentRadio = document.getElementById('role_student');
        const lecturerRadio = document.getElementById('role_lecturer');
        const studentFields = document.getElementById('student-fields');
        const lecturerFields = document.getElementById('lecturer-fields');
        const studentInputs = studentFields.querySelectorAll('input');
        const lecturerInputs = lecturerFields.querySelectorAll('input');

        function updateForm() {
            const selectedRole = document.querySelector('input[name="role_type"]:checked').value;
            const labels = document.querySelectorAll('.role-label');
            
            labels.forEach(label => {
                label.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
                label.classList.add('text-slate-500');
            });

            const activeLabel = document.querySelector(`label[for="role_${selectedRole}"]`);
            activeLabel.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
            activeLabel.classList.remove('text-slate-500');

            if (selectedRole === 'student') {
                studentFields.classList.remove('hidden');
                lecturerFields.classList.add('hidden');
                studentInputs.forEach(input => input.required = true);
                lecturerInputs.forEach(input => input.required = false);
            } else {
                studentFields.classList.add('hidden');
                lecturerFields.classList.remove('hidden');
                studentInputs.forEach(input => input.required = false);
                // SỬA ĐỔI: Chỉ yêu cầu các trường có trong CSDL
                lecturerInputs.forEach(input => {
                    // Bạn có thể đặt required=true nếu muốn, dựa trên CSDL
                    if(input.name === 'school' || input.name === 'department') {
                         input.required = true;
                    } else {
                         input.required = false;
                    }
                });
            }
        }

        studentRadio.addEventListener('change', updateForm);
        lecturerRadio.addEventListener('change', updateForm);
        updateForm(); // Cập nhật trạng thái ban đầu khi tải trang
    </script>
</body>
</html>