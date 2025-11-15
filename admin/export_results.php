<?php
include(__DIR__ . '/../config/session.php');include(__DIR__ . '/../config/db.php');

// 1. Kiểm tra quyền Admin và các tham số đầu vào
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    exit('Bạn không có quyền truy cập vào chức năng này.');
}

$quiz_id = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
if (!$quiz_id) {
    http_response_code(400); // Bad Request
    exit('ID bài thi không hợp lệ.');
}

$filename = "ket_qua_bai_thi_" . $quiz_id . "_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputs($output, "\xEF\xBB\xBF");

fputcsv($output, ['MSSV', 'Họ và Tên', 'Điểm (/10)', 'Ngày Nộp'], ",", '"', "\\");

try {
    $sql = "
        SELECT 
            u.student_id,
            u.fullname,
            r.score,
            r.submitted_at
        FROM results r
        JOIN users u ON r.user_id = u.id
        WHERE r.quiz_id = ?
        ORDER BY u.fullname ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $results_data = $stmt->get_result();

    if ($results_data->num_rows > 0) {
        while ($row = $results_data->fetch_assoc()) {
            $csv_row = [
                $row['student_id'],
                $row['fullname'],
                $row['score'],
                date("d/m/Y H:i:s", strtotime($row['submitted_at']))
            ];
            fputcsv($output, $csv_row, ",", '"', "\\");
        }
    }

    $stmt->close();
    $conn->close();
} catch (mysqli_sql_exception $e) {
    fputcsv($output, ['Có lỗi xảy ra khi truy vấn dữ liệu.']);
    error_log("Export results error: " . $e->getMessage());
}

fclose($output);
exit();
?>
