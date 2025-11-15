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

// Lấy danh sách các lớp do giảng viên này tạo để hiển thị trong dropdown
$lecturer_classes = [];
$stmt_classes = $conn->prepare("SELECT class_id, class_name FROM class WHERE users_id = ? ORDER BY class_name ASC");
$stmt_classes->bind_param("i", $lecturer_id);
$stmt_classes->execute();
$res_classes = $stmt_classes->get_result();
if ($res_classes) {
    while ($row = $res_classes->fetch_assoc()) {
        $lecturer_classes[] = $row;
    }
}
$stmt_classes->close();


// 2. Xử lý khi admin tải tệp CSV lên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_path = $_FILES['csv_file']['tmp_name'];
        $class_id_to_import = trim($_POST['class_id'] ?? '');

        if (!empty($class_id_to_import) && is_uploaded_file($file_path)) {
            $conn->begin_transaction();
            try {
                $handle = fopen($file_path, "r");
                $is_header = true;
                $new_student_count = 0;
                $linked_student_count = 0;
                $skipped_count = 0;

                // Chuẩn bị các câu lệnh
                $stmt_find_user = $conn->prepare("SELECT id FROM users WHERE student_id = ? OR email = ?");
                // SỬA ĐỔI: Role mặc định là 'student' theo schema
                $stmt_insert_user = $conn->prepare("INSERT INTO users(student_id, username, password, fullname, email, major, role, active) VALUES (?, ?, ?, ?, ?, ?, 'student', 1)");
                $stmt_check_relation = $conn->prepare("SELECT idclass_relation FROM class_relation WHERE user_id = ? AND class_id = ?");
                $stmt_add_relation = $conn->prepare("INSERT INTO class_relation (user_id, class_id) VALUES (?, ?)");

                fgetcsv($handle, 0, ",", '"', "\\"); // dòng 1
                fgetcsv($handle, 0, ",", '"', "\\"); // dòng 2
                while (($data = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
                    if (count($data) < 4) continue;

                    $student_id = trim($data[0]);
                    $fullname = trim($data[1]);
                    $major = trim($data[2]);
                    $email = trim($data[3]);
                    $user_id_to_link = null;

                    if (empty($student_id) || empty($fullname) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $skipped_count++;
                        continue;
                    }

                    // Bước A: Tìm hoặc tạo người dùng
                    $stmt_find_user->bind_param("ss", $student_id, $email);
                    $stmt_find_user->execute();
                    $user_res = $stmt_find_user->get_result();

                    if ($user_row = $user_res->fetch_assoc()) {
                        $user_id_to_link = $user_row['id'];
                    } else {
                        $username = $student_id;
                        $password = hash('sha256', $student_id);
                        $stmt_insert_user->bind_param("ssssss", $student_id, $username, $password, $fullname, $email, $major);
                        $stmt_insert_user->execute();
                        $user_id_to_link = $stmt_insert_user->insert_id;
                        $new_student_count++;
                    }

                    // Bước B: Thêm sinh viên vào lớp (nếu chưa có)
                    if ($user_id_to_link) {
                        // SỬA ĐỔI: bind_param là "is" (integer, string) cho user_id và class_id
                        $stmt_check_relation->bind_param("is", $user_id_to_link, $class_id_to_import);
                        $stmt_check_relation->execute();
                        if ($stmt_check_relation->get_result()->num_rows > 0) {
                            $skipped_count++; // Đã có trong lớp rồi
                        } else {
                            // SỬA LỖI: bind_param chỉ có 2 giá trị "is"
                            $stmt_add_relation->bind_param("is", $user_id_to_link, $class_id_to_import);
                            $stmt_add_relation->execute();
                            $linked_student_count++;
                        }
                    }
                }
                fclose($handle);

                $conn->commit();
                $message = "Hoàn tất! Đã tạo {$new_student_count} tài khoản mới và thêm {$linked_student_count} sinh viên vào lớp. Bỏ qua {$skipped_count} sinh viên đã có trong lớp hoặc dữ liệu không hợp lệ.";
                $message_type = 'success';
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $message = "Lỗi CSDL: " . $exception->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Vui lòng chọn một lớp và chọn tệp CSV hợp lệ.";
            $message_type = 'error';
        }
    } else {
        $message = "Đã có lỗi xảy ra khi tải tệp lên.";
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
    <title>Thêm Sinh Viên - Quizzy Admin</title>
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
                <div class="container mx-auto max-w-4xl">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-3xl font-bold text-slate-800">Thêm Sinh Viên Vào Lớp</h2>
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
                                <label for="class_id" class="block text-sm font-medium text-slate-700 mb-2">1. Chọn lớp học (đây là lớp của bạn):</label>
                                <select id="class_id" name="class_id" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    <option value="" disabled selected>-- Chọn một lớp học --</option>
                                    <?php if (!empty($lecturer_classes)): ?>
                                        <?php foreach ($lecturer_classes as $class_item): ?>
                                            <option value="<?= htmlspecialchars($class_item['class_id']) ?>"><?= htmlspecialchars($class_item['class_name']) ?> (<?= htmlspecialchars($class_item['class_id']) ?>)</option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Bạn chưa tạo lớp nào. Vui lòng tạo lớp trước.</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">2. Tải lên tệp CSV chứa danh sách sinh viên:</label>
                                <a href="download.php" class="font-medium text-red-600 hover:underline"> Nhấn vào đây để tải về file CSV mẫu [.xlsx]</a></p>
                                <p class="texttext-yellow mt-4 text-md" style="color: blue;">Lưu ý:</p>
                                <p class="text-danger mt-1 text-md text-green-800"">[Hệ thống sẽ tạo tài khoản mặc định nếu sinh viên chưa có tài khoản]</p>
                                <p class=" text-danger mt-1 text-md text-green-800"">[Tài khoản và mật khẩu mặc định là MSSV của sinh viên]</p>
                                <label for="csv_file" class="flex justify-center w-full px-6 py-10 border-2 border-slate-300 border-dashed rounded-lg cursor-pointer hover:bg-slate-50">
                                    </br>
                                    <div class="text-center">
                                        <i class="bi bi-cloud-arrow-up-fill text-4xl text-slate-400"></i>
                                        <p class="mt-1 text-sm text-slate-600"><span class="font-semibold text-blue-600">Nhấn để chọn tệp</span></p>
                                        <p class="text-xs text-slate-500 mt-1">Chỉ chấp nhận tệp .csv</p>
                                        <span id="file-name" class="text-sm font-semibold text-green-600 mt-2 block"></span>
                                    </div>
                                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required class="hidden">
                                </label>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="bi bi-person-plus-fill"></i> Thêm Sinh Viên Vào Lớp
                            </button>
                        </form>
                        <p class="text-black mt-4 text-md">Sau khi nhập xong bạn có thể kiểm tra sinh viên của mình tại trang: <a href="manage_classes.php" class="font-medium text-blue-600 hover:underline"> Quản Lý Lớp Học.</a></p>
                    </div>
                    <div class="mt-8 bg-slate-800 text-white p-6 rounded-2xl">
                        <h4 class="text-lg font-bold mb-3 flex items-center gap-2"><i class="bi bi-file-earmark-spreadsheet-fill"></i> Định dạng tệp CSV mẫu</h4>
                        <p class="text-slate-300 mb-4">Tệp phải có 4 cột. Thứ tự cột như sau:</p>
                        <pre class="bg-slate-900 p-4 rounded-lg text-sm whitespace-pre-wrap"><code>student_id,fullname,major,email
2124802010123,Nguyễn Văn A,Kỹ thuật phần mềm,vana@email.com
2124802010456,Trần Thị B,Khoa học máy tính,thib@email.com</code></pre>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        document.getElementById('csv_file').addEventListener('change', (e) => {
            const fileNameSpan = document.getElementById('file-name');
            if (e.target.files.length > 0) {
                fileNameSpan.textContent = `Đã chọn: ${e.target.files[0].name}`;
            } else {
                fileNameSpan.textContent = '';
            }
        });
    </script>
</body>

</html>