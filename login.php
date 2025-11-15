<?php
include(__DIR__ . '/config/session.php');
include(__DIR__ . '/config/db.php');

$sesion_kill = '';
if (isset($_GET['timeout'])) {
    $sesion_kill = "Phiên làm việc đã hết hạn, vui lòng đăng nhập lại.";
}

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'superadmin':
            header('Location: /super_admin/index.php');
            break;
        case 'admin':
            header('Location: /admin/index.php');
            break;
        default:
            header('Location: index.php');
            break;
    }
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = hash('sha256', trim($_POST['password']));

    $sql = "SELECT id, fullname, role, active, major, student_id 
            FROM users WHERE username=? AND password=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($user['active'] != 1) {
            $message = "Tài khoản của bạn chưa được kích hoạt hoặc đã bị khóa.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['major'] = $user['major'];
            $_SESSION['student_id'] = $user['student_id'];

            switch ($user['role']) {
                case 'superadmin':
                    header("Location: /super_admin/index.php");
                    break;
                case 'admin':
                    header("Location: /admin/index.php");
                    break;
                default:
                    header("Location: index.php");
                    break;
            }
            exit();
        }
    } else {
        $message = "Sai tên đăng nhập hoặc mật khẩu!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Quizzy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex flex-col items-center justify-center p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-3xl font-bold text-blue-600">
                <i class="bi bi-card-checklist"></i>
                <span>Quizzy</span>
            </a>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-xl">
            <form method="post" action="" class="space-y-6">
                
                <?php if ($sesion_kill): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                            <p><?= htmlspecialchars($sesion_kill) ?></p>
                        </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                        <p><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Tên đăng nhập</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="bi bi-person text-slate-400"></i>
                        </div>
                        <input type="text" id="username" name="username" class="block w-full rounded-lg border-slate-300 pl-10 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500" required placeholder="Nhập tên đăng nhập">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                             <i class="bi bi-lock text-slate-400"></i>
                        </div>
                        <input type="password" id="password" name="password" class="block w-full rounded-lg border-slate-300 pl-10 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500" required placeholder="••••••••">
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <label for="remember-me" class="ml-2 block text-sm text-slate-900">Ghi nhớ đăng nhập</label>
                    </div>
                    <div class="text-sm">
                        <a href="#" class="font-medium text-blue-600 hover:text-blue-500">Quên mật khẩu?</a>
                    </div>
                </div>

                <div>
                    <button type="submit" class="flex w-full justify-center rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors">
                        Đăng nhập
                    </button>
                </div>
            </form>

            <p class="mt-8 text-center text-sm text-slate-500">
                Chưa có tài khoản?
                <a href="register.php" class="font-semibold leading-6 text-blue-600 hover:text-blue-500">Đăng ký ngay</a>
            </p>
        </div>

         <div class="text-center mt-8 text-sm text-slate-500">
            <p>&copy; <?= date("Y") ?> Quizzy - Viện Công Nghệ Số, TDMU.</p>
        </div>
    </div>

</body>
</html>