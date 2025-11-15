<?php
include(__DIR__ . '/config/session.php');
include(__DIR__ . '/config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $current_password = hash('sha256', trim($_POST['current_password']));
    $new_password = hash('sha256', trim($_POST['new_password']));
    $confirm_password = hash('sha256', trim($_POST['confirm_password']));

    if ($new_password !== $confirm_password) {
        $message = "Mật khẩu mới và mật khẩu xác nhận không khớp!";
    } else {
        try {
            $stmt_check = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_check->bind_param("i", $user_id);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            $user = $result->fetch_assoc();
            $stmt_check->close();

            if ($user && $user['password'] === $current_password) {
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->bind_param("si", $new_password, $user_id);
                if ($stmt_update->execute()) {
                    $message = "Đổi mật khẩu thành công!";
                    $message_type = 'success';
                } else {
                    throw new Exception("Không thể cập nhật mật khẩu.");
                }
                $stmt_update->close();
            } else {
                $message = "Mật khẩu hiện tại không chính xác!";
            }
        } catch (Exception $e) {
            $message = "Đã xảy ra lỗi. Vui lòng thử lại.";
            error_log("Change password error: " . $e->getMessage());
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi Mật Khẩu - Quizzy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Be Vietnam Pro', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex flex-col items-center justify-center p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="/index.php" class="inline-flex items-center gap-2 text-3xl font-bold text-blue-600">
                <i class="bi bi-card-checklist"></i>
                <span>Quizzy</span>
            </a>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-xl">
            <form method="post" action="" class="space-y-6">
                
                <?php if ($message): ?>
                    <div class="p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700' ?>" role="alert">
                        <p><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="current_password" class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu hiện tại</label>
                    <div class="relative">
                        <input type="password" id="current_password" name="current_password" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                    </div>
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu mới</label>
                    <div class="relative">
                        <input type="password" id="new_password" name="new_password" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                    </div>
                </div>
                
                 <div>
                    <label for="confirm_password" class="block text-sm font-medium text-slate-700 mb-1">Xác nhận mật khẩu mới</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" class="block w-full rounded-lg border border-slate-300 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                    </div>
                </div>

                <div>
                    <button type="submit" class="flex w-full justify-center rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-blue-700 transition-colors">
                        Cập Nhật Mật Khẩu
                    </button>
                </div>
            </form>
        </div>

         <div class="text-center mt-8 text-sm text-slate-500">
            <p>&copy; <?= date("Y") ?> Quizzy - Viện Công Nghệ Số, TDMU.</p>
        </div>
    </div>

</body>
</html>
