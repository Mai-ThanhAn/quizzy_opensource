<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
include(__DIR__ . '/config/session.php');
include(__DIR__ . '/config/db.php');

$student_classes = [];
$quizzes_by_class = [];
$user_results = [];
$message = '';
$message_type = 'error';

// Thêm logic để bắt lỗi từ URL
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'wrong_password') {
        $message = "Mật khẩu bài thi không chính xác. Vui lòng thử lại.";
        $message_type = 'error';
    }
    if ($_GET['error'] === 'password_required') {
        $message = "Bài thi này yêu cầu mật khẩu. Vui lòng nhập mật khẩu.";
        $message_type = 'info';
    }
}

// Kiểm tra thông báo thành công từ trang đăng ký
if (isset($_SESSION['register_success'])) {
    $message = $_SESSION['register_success'];
    $message_type = 'success';
    unset($_SESSION['register_success']); // Xóa session sau khi hiển thị
}

// Xử lý cập nhật thông tin cá nhân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        // Chỉ cho phép sửa Họ tên và Chuyên ngành
        $fullname = trim($_POST['fullname'] ?? '');
        $major = trim($_POST['major'] ?? '');

        if (!empty($fullname) && !empty($major)) {
            try {
                $stmt_update = $conn->prepare("UPDATE users SET fullname = ?, major = ? WHERE id = ?");
                $stmt_update->bind_param("ssi", $fullname, $major, $user_id);

                if ($stmt_update->execute()) {
                    // Cập nhật session để hiển thị ngay
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['major'] = $major;

                    // Gán thông báo thành công cho modal
                    $message = "Cập nhật thông tin thành công!";
                    $message_type = 'success';
                } else {
                    throw new Exception("Lỗi khi cập nhật thông tin.");
                }
                $stmt_update->close();
            } catch (Exception $e) {
                $message = $e->getMessage();
            }
        } else {
            $message = "Họ tên và Chuyên ngành không được để trống.";
        }
    }
}

if (isset($_SESSION['user_id'])) {

    $user_id = (int)$_SESSION['user_id'];
    // Xử lý khi sinh viên tham gia lớp học
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_class'])) {
        $class_id_to_join = trim($_POST['class_id'] ?? '');
        if (!empty($class_id_to_join)) {
            try {
                // Bước 1: Kiểm tra xem lớp học có tồn tại không
                $stmt_check_class = $conn->prepare("SELECT class_id FROM class WHERE class_id = ?");
                $stmt_check_class->bind_param("s", $class_id_to_join);
                $stmt_check_class->execute();
                if ($stmt_check_class->get_result()->num_rows === 0) {
                    throw new Exception("Mã lớp không tồn tại. Vui lòng kiểm tra lại.");
                }
                $stmt_check_class->close();

                // Bước 2: Kiểm tra xem sinh viên đã ở trong lớp chưa
                $stmt_check_relation = $conn->prepare("SELECT idclass_relation FROM class_relation WHERE user_id = ? AND class_id = ?");
                $stmt_check_relation->bind_param("is", $user_id, $class_id_to_join);
                $stmt_check_relation->execute();
                if ($stmt_check_relation->get_result()->num_rows > 0) {
                    throw new Exception("Bạn đã ở trong lớp này rồi.");
                }
                $stmt_check_relation->close();

                // Bước 3: Thêm sinh viên vào lớp
                $stmt_join = $conn->prepare("INSERT INTO class_relation (user_id, class_id) VALUES (?, ?)");
                $stmt_join->bind_param("is", $user_id, $class_id_to_join);
                if ($stmt_join->execute()) {
                    $message = "Tham gia lớp học thành công!";
                    $message_type = 'success';
                } else {
                    throw new Exception("Không thể tham gia lớp học. Vui lòng thử lại.");
                }
                $stmt_join->close();
            } catch (Exception $e) {
                $message = $e->getMessage();
            }
        } else {
            $message = "Vui lòng nhập mã lớp để tham gia.";
        }
    }
    // 1. Lấy danh sách các lớp mà sinh viên tham gia
    $sql_classes = "SELECT c.class_id, c.class_name, c.description 
                    FROM class_relation cr
                    JOIN class c ON cr.class_id = c.class_id
                    WHERE cr.user_id = ?";
    $stmt_classes = $conn->prepare($sql_classes);
    $stmt_classes->bind_param("i", $user_id);
    $stmt_classes->execute();
    $res_classes = $stmt_classes->get_result();
    while ($row = $res_classes->fetch_assoc()) {
        $student_classes[$row['class_id']] = $row;
    }
    $stmt_classes->close();

    // 2. Lấy kết quả các bài thi sinh viên đã làm
    $sql_results = "SELECT id, quiz_id FROM results WHERE user_id = ?";
    $stmt_results = $conn->prepare($sql_results);
    $stmt_results->bind_param("i", $user_id);
    $stmt_results->execute();
    $result_data = $stmt_results->get_result();
    while ($row = $result_data->fetch_assoc()) {
        $user_results[$row['quiz_id']] = $row;
    }
    $stmt_results->close();

    // 3. Lấy tất cả bài thi liên quan (thuộc lớp của SV)
    $sql_quizzes = "SELECT 
    id, title, description, time_limit, can_review, class_name, password, start_at, finished_at,
    password IS NOT NULL as has_password FROM quizzes WHERE class_name IS NULL";
    $class_ids = array_keys($student_classes);
    if (!empty($class_ids)) {
        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $sql_quizzes .= " OR class_name IN ($placeholders)";
    }
    $sql_quizzes .= " ORDER BY title ASC";

    $stmt_quizzes = $conn->prepare($sql_quizzes);
    if (!empty($class_ids)) {
        $stmt_quizzes->bind_param(str_repeat('s', count($class_ids)), ...$class_ids);
    }
    $stmt_quizzes->execute();
    $quizzes_result = $stmt_quizzes->get_result();

    // Gom nhóm bài thi theo từng lớp
    while ($quiz = $quizzes_result->fetch_assoc()) {
        $class_key = $quiz['class_name'] ?? 'general';
        if (!isset($quizzes_by_class[$class_key])) {
            $quizzes_by_class[$class_key] = [];
        }
        $quizzes_by_class[$class_key][] = $quiz;
    }
    $stmt_quizzes->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzy - Hệ Thống Trắc Nghiệm Online</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --background-light: #f8f9fa;
            --card-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.075);
            --card-hover-shadow: 0 1rem 2.5rem rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Be Vietnam Pro', sans-serif;
            background-color: var(--background-light);
        }

        .navbar {
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }

        .hero-section {
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.9) 0%, rgba(2, 48, 112, 0.95) 100%), url('https://images.unsplash.com/photo-1523240795612-9a054b0db644?q=80&w=2070&auto=format&fit=crop') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 80px 0;
        }

        .exam-card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .exam-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-hover-shadow);
        }

        .btn-gradient {
            background: linear-gradient(45deg, #0d6efd, #0dcaf0);
            border: none;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.5);
            color: white;
        }

        footer {
            background-color: #212529;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">
    <!-- Thanh điều hướng -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="/index.php"><i class="bi bi-card-checklist"></i> Quizzy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle fw-bold" href="#" id="studentInfoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Xin Chào, <?= htmlspecialchars($_SESSION['fullname']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="studentInfoDropdown">
                                <li>
                                    <a class="dropdown-item text-black" href="#" data-bs-toggle="modal" data-bs-target="#studentInfoModal">
                                        <i class="bi bi-person-circle me-2"></i>
                                        Thông tin cá nhân
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item text-black" href="/change_password.php">
                                        <i class="bi bi-unlock me-2"></i>
                                        Đổi mật khẩu
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>
                                        Đăng Xuất
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php if (($_SESSION['role']) == 'admin'): ?>
                            <li class="nav-item">
                                <a class="btn btn-warning ms-lg-2" href="/admin/index.php">Admin Panel <i class="bi bi-gear-fill me-2"></i></a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php">Đăng nhập</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-lg-2" href="/register.php">Đăng ký</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Banner -->
    <header class="hero-section text-center">
        <div class="container">
            <h5 class="mb-3">Trường Đại Học Thủ Dầu Một - Viện Công Nghệ Số</h5>
            <h1 class="display-4 mb-3 fw-bold">Hệ Thống Kiểm Tra Trắc Nghiệm</h1>
            <p class="lead mb-4">Chào mừng bạn đến với hệ thống kiểm tra trực tuyến.</p>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="/login.php" class="btn btn-light btn-lg fw-bold">Đăng nhập để bắt đầu</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container my-5 flex-grow-1">
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="d-flex justify-content-between align-items-center mb-5">
                <h5 class="p-3">
                    <span class="fs-4 fw-bold text-dark">Lớp Học Của Bạn</span>
                </h5>
                <button class="btn btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#joinClassModal">
                    <i class="bi bi-plus-circle-fill me-2"></i>Tham gia lớp học
                </button>
            </div>
            <?php if ($message && !isset($_POST['update_profile'])): ?>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        document.getElementById("modalMessage").innerText = "<?= htmlspecialchars($message) ?>";
                        document.getElementById("modalTitle").innerText =
                            "<?= $message_type === 'success' ? 'Thành công'
                                    : ($message_type === 'info' ? 'Thông báo'
                                        : 'Lỗi') ?>";

                        let modal = new bootstrap.Modal(document.getElementById("messageModal"));
                        modal.show();
                    });
                </script>
            <?php endif; ?>

            <p class="mt-3 text-center text-danger text-sm fw-bold">
                <i class="bi bi-shield-fill-exclamation"></i>
                Cảnh Báo Bảo Mật: Sinh viên đăng nhập bằng tài khoản mặc định vui lòng
                <a href="change_password.php" class="font-semibold leading-6 text-success">đổi mật khẩu.</a>
            </p>
            <p class="mt-3 text-danger fw-bold">
                <i class="bi bi-exclamation-diamond-fill"></i>
                Nhắc nhở:
            <ul class="list-unstyled">
                <li>Một số lưu ý trong quá trình thực hiện bài kiểm tra:</li>
                <li>- Phiên làm việc của bạn sẽ hết hạn sau 15 phút không hoạt động trên trang web, nếu bạn bị xóa phiên, bài làm sẽ tự động được nộp.</li>
                <li>- Không làm mới trang hoặc đóng trình duyệt khi đang làm bài kiểm tra.</li>
                <li>- Kiểm tra kỹ các câu trả lời trước khi nộp bài.</li>
                <li>- Nếu có bất kỳ sự cố xảy ra vui lòng thông báo đến giảng viên để được hỗ trợ.</li>
            </ul>
            </p>
            <p class="mt-4 text-center text-primary fw-bold">
                Đây là danh sách các lớp mà bạn tham gia. Vui lòng chọn vào các lớp để xem chi tiết bài kiểm tra.
            </p>

            <!-- Giao diện Tab lớp học -->
            <div class="card shadow-sm">
                <div class="card-header bg-white border-0">
                    <ul class="nav nav-tabs card-header-tabs" id="classTab" role="tablist">
                        <?php $i = 0; ?>
                        <?php foreach ($student_classes as $class_id => $class_details): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                                    id="class-<?= $class_id ?>-tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#tab-class-<?= $class_id ?>"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-class-<?= $class_id ?>"
                                    aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                                    <?= htmlspecialchars($class_details['class_name']) ?>
                                </button>
                            </li>
                            <?php $i++; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-body tab-content p-4" id="classTabContent">
                    <?php $i = 0; ?>
                    <?php foreach ($student_classes as $class_id => $class_details): ?>
                        <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
                            id="tab-class-<?= $class_id ?>"
                            role="tabpanel"
                            aria-labelledby="class-<?= $class_id ?>-tab">
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                <?php if (!empty($quizzes_by_class[$class_id])): ?>
                                    <?php foreach ($quizzes_by_class[$class_id] as $quiz):
                                        // Thời gian hiện tại
                                        $now = new DateTime();
                                        $start_at = !empty($quiz['start_at']) && $quiz['start_at'] !== '0000-00-00 00:00:00' ? new DateTime($quiz['start_at']) : null;
                                        $finished_at = !empty($quiz['finished_at']) && $quiz['finished_at'] !== '0000-00-00 00:00:00' ? new DateTime($quiz['finished_at']) : null;

                                        $has_completed = isset($user_results[$quiz['id']]);
                                        $can_review = $quiz['can_review'] == 1;

                                        // Xác định trạng thái
                                        $status = 'active';
                                        $status_text = 'Cho phép làm bài';
                                        $status_color = 'text-success';
                                        if ($start_at && $now < $start_at) {
                                            $status = 'upcoming';
                                            $status_text = 'Sắp diễn ra';
                                            $status_color = 'text-warning';
                                        } elseif ($finished_at && $now > $finished_at) {
                                            $status = 'finished';
                                            $status_text = 'Đã kết thúc';
                                            $status_color = 'text-danger';
                                        }

                                        // Nút hành động
                                        if ($has_completed) {
                                            $button_html = $can_review
                                                ? '<a href="review.php?result_id=' . $user_results[$quiz['id']]['id'] . '" class="btn btn-success w-100 fw-semibold"><i class="bi bi-eye-fill me-2"></i>Xem Lại</a>'
                                                : '<button class="btn btn-secondary w-100 fw-semibold" disabled><i class="bi bi-check-circle-fill me-2"></i>Đã Nộp</button>';
                                        } else {
                                            if ($status == 'upcoming') $button_html = '<button class="btn btn-info w-100 fw-semibold" disabled><i class="bi bi-hourglass-split me-2"></i>Chưa tới giờ</button>';
                                            elseif ($status == 'finished') $button_html = '<button class="btn btn-danger w-100 fw-semibold" disabled><i class="bi bi-x-circle-fill me-2"></i>Đã kết thúc</button>';
                                            elseif ($quiz['has_password']) $button_html = '<button type="button" class="btn btn-primary w-100 fw-semibold password-prompt-btn" data-bs-toggle="modal" data-bs-target="#passwordModal" data-quiz-id="' . $quiz['id'] . '"><i class="bi bi-lock-fill me-2"></i>Vào thi</button>';
                                            else $button_html = '<a href="quiz.php?exam_id=' . $quiz['id'] . '" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-pencil-square me-2"></i>Làm Bài</a>';
                                        }
                                    ?>
                                        <div class="col">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column">
                                                    <h5 class="card-title fw-bold"><?= htmlspecialchars($quiz['title']) ?></h5>
                                                    <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars($quiz['description']) ?></p>
                                                    <div class="small mt-3 pt-3 border-top">
                                                        <p class="text-muted mb-1"><i class="bi bi-hourglass-split""></i> Thời gian: <strong><?= $quiz['time_limit'] ?> phút</strong></p>
                                                        <p class=" text-muted mb-1"><i class="bi bi-calendar2-check-fill"></i> Mở lúc: <strong><?= $start_at ? $start_at->format('H:i, d/m/Y') : 'Không' ?></strong></p>
                                                        <p class="text-danger mb-1"><i class="bi bi-calendar2-x-fill"></i> Đóng lúc: <strong><?= $finished_at ? $finished_at->format('H:i, d/m/Y') : 'Không' ?></strong></p>
                                                        <p class="fw-bold <?= $status_color ?> mt-2"><i class="bi bi-broadcast"></i> Trạng thái: <?= $status_text ?></p>
                                                    </div>
                                                    <div class="mt-4 d-grid"><?= $button_html ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted col-12">Lớp này chưa có bài kiểm tra nào.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php $i++; ?>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-person-fill-x display-1 text-muted"></i>
                <h3 class="fw-bold mt-4">Vui Lòng Đăng Nhập</h3>
                <p class="lead text-muted">Bạn cần đăng nhập để xem các bài kiểm tra.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Nhập Mật khẩu -->
    <div class="modal fade" id="passwordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="quiz.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Yêu cầu mật khẩu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Bài kiểm tra này được bảo vệ bằng mật khẩu. Vui lòng nhập mật khẩu để tiếp tục.</p>
                        <input type="hidden" name="exam_id" id="modal_quiz_id">
                        <div class="mt-3">
                            <label for="quiz_password" class="form-label">Mật khẩu:</label>
                            <input type="password" class="form-control" id="quiz_password" name="quiz_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Xác nhận</button>
                    </div>
                </form>
                <div id="passwordError" class="text-danger mt-2"></div>
            </div>
        </div>
    </div>

    <!-- Modal Tham gia lớp học -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="modal fade" id="joinClassModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Tham gia lớp học</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mt-8 text-center text-danger text-sm text-slate-500">
                                Vui lòng nhập đúng mã lớp mà giảng viên cung cấp cho bạn.
                            </p>
                            <label for="class_id_input" class="form-label">Nhập mã lớp:</label>
                            <input type="text" class="form-control" id="class_id_input" name="class_id" required placeholder="Ví dụ: CNTT.CQ.01">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" name="join_class" class="btn btn-primary">Xác nhận</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal thông tin sinh viên -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="modal fade" id="studentInfoModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="modal-header text-white bg-primary">
                            <h5 class="modal-title">Thông Tin Sinh Viên</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <!-- THÊM MỚI: Hiển thị thông báo (nếu có) -->
                            <?php if (!empty($message) && isset($_POST['update_profile'])): ?>
                                <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-danger' ?>" role="alert">
                                    <?= htmlspecialchars($message) ?>
                                </div>
                            <?php endif; ?>

                            <div class="text-center mb-3">
                                <img src="<?= htmlspecialchars($_SESSION['avatar'] ?? 'https://placehold.co/120x120/EFEFEF/333?text=Avatar') ?>" class="rounded-circle border" alt="Avatar" width="120" height="120">
                            </div>

                            <div class="mb-3">
                                <label for="fullname" class="form-label fw-bold"><i class="bi bi-person-fill me-2 text-primary"></i>Họ và tên:</label>
                                <input type="text" class="form-control" id="fullname" name="fullname" value="<?= htmlspecialchars($_SESSION['fullname'] ?? 'Chưa cập nhật') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="student_id" class="form-label fw-bold"><i class="bi bi-person-badge-fill me-2 text-primary"></i>MSSV:</label>
                                <input type="text" class="form-control" id="student_id" name="student_id_display" value="<?= htmlspecialchars($_SESSION['student_id'] ?? 'N/A') ?>" disabled readonly>
                                <div class="form-text">Mã số sinh viên không thể thay đổi.</div>
                            </div>

                            <div class="mb-3">
                                <label for="major" class="form-label fw-bold"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Khóa:</label>
                                <input type="text" class="form-control" id="major" name="major" value="<?= htmlspecialchars($_SESSION['major'] ?? 'Chưa cập nhật') ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-between border-0 pb-3 px-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill"></i> Lưu thay đổi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Thông Báo -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTitle">Thông báo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p id="modalMessage" class="mb-0"></p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Đóng</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-auto">
        <div class="container">
            <p class="mb-0">&copy; <?= date("Y") ?> Quizzy - Viện Công Nghệ Số, TDMU. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

    <script>
        //JS để gán quiz_id cho modal mật khẩu
        const passwordModal = document.getElementById('passwordModal');
        if (passwordModal) {
            passwordModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const quizId = button.getAttribute('data-quiz-id');
                passwordModal.querySelector('#modal_quiz_id').value = quizId;
            });
        }
    </script>
    <script>
        // JS toggle card body
        document.querySelectorAll('.toggle-card').forEach(header => {
            header.addEventListener('click', () => {
                const targetId = header.getAttribute('data-target');
                const body = document.getElementById(targetId);
                if (body.classList.contains('show')) {
                    body.classList.remove('show');
                    header.querySelector('.toggle-icon').classList.replace('bi-chevron-up', 'bi-chevron-down');
                } else {
                    body.classList.add('show');
                    header.querySelector('.toggle-icon').classList.replace('bi-chevron-down', 'bi-chevron-up');
                }
            });
        });
    </script>

    <style>
        .cursor-pointer {
            cursor: pointer;
        }

        .collapse {
            display: none;
        }

        .collapse.show {
            display: block;
        }
    </style>
</body>

</html>