<?php
include(__DIR__ . '/../config/session.php');if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
$admin_name = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Quản Trị - Quizzy</title>
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

        /* Style cho sidebar trên mobile */
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
        <!-- Sidebar (Bên trái) -->
        <aside id="sidebar" class="sidebar bg-slate-800 text-white w-72 space-y-6 py-7 px-4 absolute inset-y-0 left-0 transform lg:relative lg:translate-x-0">
            <a href="#" class="text-white flex items-center space-x-3 px-4">
                <i class="bi bi-bookmark-star-fill text-3xl text-red-500"></i>
                <span class="text-2xl font-extrabold">Giảng Viên</span>
            </a>

            <nav class="pt-6">
                <h3 class="px-4 text-slate-400 text-sm uppercase font-semibold mb-3">Hướng Dẫn Sử Dụng Nhanh</h3>
                <div class="px-4 text-sm text-slate-300 space-y-4">
                    <p>Chào mừng bạn đến với hệ thống tạo bài kiểm tra, dưới đây là hướng dẫn nhanh sử dụng hệ thống:</p>
                    <ul class=" list-inside space-y-2">
                        <li><strong>Quản lý sinh viên và lớp học: </strong> Bạn cần tạo mới lớp học và thêm sinh viên của mình vào trước khi tạo bài kiểm tra.</li>
                        <li>Quản lý lớp học <i class="bi bi-arrow-right-short"></i> Thêm sinh viên</li>
                        <li>------------------</li>
                        <li><strong>Quản lý bài kiểm tra và ngân hàng câu hỏi: </strong> Sau khi có lớp bạn có thể tạo bài kiểm tra dựa trên ngân hàng câu hỏi bạn đã thêm.</li>
                        <li>Quản lý ngân hàng <i class="bi bi-arrow-right-short"></i> Quản lý bài kiểm tra</li>
                    </ul>
                </div>
            </nav>
            <div class="absolute bottom-5 w-full px-4">
                <!-- <a href="profile.php" class="flex items-center space-x-3 py-3 px-4 rounded-lg text-slate-200 hover:bg-slate-700 transition-colors">
                    <i class="bi bi bi-person-gear text-lg"></i>
                    <span>Thông Tin</span>
                </a> -->
                <a href="../index.php" class="flex items-center space-x-3 py-3 px-4 rounded-lg text-slate-200 hover:bg-slate-700 transition-colors">
                    <i class="bi bi-house-check text-lg"></i>
                    <span>Trang Chủ Sinh Viên</span>
                </a>
                <a href="../logout.php" class="flex items-center space-x-3 py-3 px-4 rounded-lg text-slate-200 hover:bg-slate-700 transition-colors">
                    <i class="bi bi-box-arrow-left text-lg"></i>
                    <span>Đăng Xuất</span>
                </a>
            </div>
        </aside>

        <!-- Nội dung chính (Ở giữa) -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="flex justify-between items-center p-4 bg-white border-b lg:justify-end">
                <!-- Nút Menu cho mobile -->
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
                        <h2 class="text-4xl font-bold mb-2">Quizzy Chúc Bạn Ngày Mới Tốt Lành.</h2>
                        <p class="text-lg opacity-90">Bạn Đang Đăng Nhập Với Quyền Giảng Viên</p>
                        </br>
                        <p class="text-md">Vui Lòng Kéo Xuống Để Xem Thêm Chức Năng</p>
                    </div>

                    <!-- Phần Bài Kiểm Tra -->
                    <h5 class="p-2">
                        <span class="text-2xl font-bold text-slate-800">Câu Hỏi Và Bài Kiểm Tra</span>
                    </h5>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-6">

                        <!-- Card 1: Quản lý ngân hàng-->
                        <a href="manage_banks.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1.5 transition-all group">
                            <div class="flex items-center justify-center bg-orange-100 text-orange-600 rounded-full w-16 h-16 mb-5">
                                <i class="bi bi bi-bank text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Quản lý ngân hàng</h3>
                            <p class="text-slate-600 mb-4">Xem, chỉnh sửa, và xóa ngân hàng câu hỏi.</p>
                            <span class="font-semibold text-orange-600 group-hover:underline">
                                Đi đến trang <i class="bi bi-arrow-right align-middle"></i>
                            </span>
                        </a>

                        <!-- Card 2: Quản lý bài kiểm tra -->
                        <a href="exams.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1.5 transition-all group">
                            <div class="flex items-center justify-center bg-blue-100 text-blue-600 rounded-full w-16 h-16 mb-5">
                                <i class="bi bi-collection-fill text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Quản lý bài kiểm tra</h3>
                            <p class="text-slate-600 mb-4">Tạo, chỉnh sửa, và xóa các bài kiểm tra của bạn.</p>
                            <span class="font-semibold text-blue-600 group-hover:underline">
                                Đi đến trang <i class="bi bi-arrow-right align-middle"></i>
                            </span>
                        </a>
                    </div>
                    </br>

                    <!-- Phần Điểm Số -->
                    <h5 class="p-2">
                        <span class="text-2xl font-bold text-slate-800">Điểm Số Và Sinh Viên</span>
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-6">

                        <!-- Card 1: Quản lý lớp học -->
                        <a href="manage_classes.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1.5 transition-all group">
                            <div class="flex items-center justify-center bg-red-100 text-red-600 rounded-full w-16 h-16 mb-5">
                                <i class="bi bi bi-people text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Quản lý lớp học</h3>
                            <p class="text-slate-600 mb-4">Tạo mới và quản lý lớp học của bạn.</p>
                            <span class="font-semibold text-red-600 group-hover:underline">
                                Đi đến trang <i class="bi bi-arrow-right align-middle"></i>
                            </span>
                        </a>

                        <!-- Card 1: Thêm Sinh viên -->
                        <a href="import_students.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1.5 transition-all group">
                            <div class="flex items-center justify-center bg-purple-100 text-purple-600 rounded-full w-16 h-16 mb-5">
                                <i class="bi bi-person-plus-fill text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Thêm sinh viên</h3>
                            <p class="text-slate-600 mb-4">Thêm sinh viên vào lớp học bằng file CSV.</p>
                            <span class="font-semibold text-purple-600 group-hover:underline">
                                Đi đến trang <i class="bi bi-arrow-right align-middle"></i>
                            </span>
                        </a>

                        <!-- Card 2: Xem điểm -->
                        <a href="results.php" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:-translate-y-1.5 transition-all group">
                            <div class="flex items-center justify-center bg-yellow-100 text-yellow-600 rounded-full w-16 h-16 mb-5">
                                <i class="bi bi-bar-chart-line-fill text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Kết quả bài kiểm tra</h3>
                            <p class="text-slate-600 mb-4">Xem và xuất kết quả của các bài kiểm tra bạn đã tạo.</p>
                            <span class="font-semibold text-yellow-600 group-hover:underline">
                                Đi đến trang <i class="bi bi-arrow-right align-middle"></i>
                            </span>
                        </a>
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
            mobileMenuButton.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
            });
        }
    </script>
</body>

</html>