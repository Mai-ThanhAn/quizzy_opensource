<?php
session_start();
ob_start();

$title = "Bài trắc nghiệm";
$questions = [];
$time_limit_minutes = 20;
$shuffle_questions =  true;
$shuffle_answers = false;
$user_id_for_js = 0;
$exam_id_for_js = 0;


try {
    include(__DIR__ . '/config/db.php');

    // 1. Kiểm tra đăng nhập
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    $user_id_for_js = $_SESSION['user_id'];
    
    // 2. Lấy ID bài thi từ GET hoặc POST
    $exam_id = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);

    if (!$exam_id) {
        header('Location: index.php');
        exit();
    }
    $exam_id_for_js = $exam_id;

    // 3. Kiểm tra xem sinh viên đã làm bài này chưa
    $checkStmt = $conn->prepare("SELECT id FROM results WHERE user_id = ? AND quiz_id = ?");
    $checkStmt->bind_param("ii", $_SESSION['user_id'], $exam_id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        header('Location: index.php?error=dond');
        exit();
    }
    $checkStmt->close();

    // 4. Lấy thông tin cài đặt của bài thi (bao gồm cả mật khẩu và các tùy chọn xáo trộn)
    $stmt = $conn->prepare("SELECT title, time_limit, shuffle_questions, shuffle_answers, password FROM quizzes WHERE id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $quiz_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$quiz_info) {
        throw new Exception("Không tìm thấy bài thi.");
    }

    $title = $quiz_info['title'];
    $time_limit_minutes = (int)$quiz_info['time_limit'];
    $shuffle_questions = (bool)$quiz_info['shuffle_questions'];
    $shuffle_answers = (bool)$quiz_info['shuffle_answers'];

    // 5. Logic kiểm tra mật khẩu
    if ($quiz_info['password'] !== null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // Nếu bài thi có mật khẩu nhưng người dùng truy cập trực tiếp qua link (GET) -> Chặn
            header('Location: index.php?error=password_required');
            exit();
        }

        $submitted_password = trim($_POST['quiz_password'] ?? '');
        $hashed_submitted_password = hash('sha256', $submitted_password);

        if ($hashed_submitted_password !== $quiz_info['password']) {
            // Nếu mật khẩu sai -> quay về trang chủ với thông báo lỗi
            header('Location: index.php?error=wrong_password');
            exit();
        }
    }

    // 6. Lấy danh sách câu hỏi và đáp án
    $sql = "
        SELECT q.id AS qid, q.question_text, a.id AS aid, a.answer_text
        FROM questions q
        JOIN answers a ON q.id = a.question_id
        WHERE q.quiz_id = ?
        ORDER BY q.id, a.id
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        $tmp = [];
        while ($r = $res->fetch_assoc()) {
            $qid = (int)$r['qid'];
            if (!isset($tmp[$qid])) {
                $tmp[$qid] = [
                    'id' => $qid,
                    'question' => $r['question_text'],
                    'options' => [],
                    'optionIds' => []
                ];
            }
            $tmp[$qid]['options'][] = $r['answer_text'];
            $tmp[$qid]['optionIds'][] = (int)$r['aid'];
        }


        $questions = array_values($tmp);
    }
    $stmt->close();

    $_SESSION['current_quiz_id'] = $exam_id;
    $conn->close();
} catch (Throwable $e) {
    error_log("quiz.php error: " . $e->getMessage());
    $questions = [];
    $title = "Lỗi tải đề thi";
}
ob_end_flush();
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Làm Bài: <?= htmlspecialchars($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #a8a8a8;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #888;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800">

    <div class="container mx-auto p-4 md:p-6 lg:p-8">

        <?php if (empty($questions)): ?>
            <div class="bg-white p-8 rounded-2xl shadow-lg text-center max-w-2xl mx-auto">
                <i class="bi bi-exclamation-triangle-fill text-5xl text-red-500 mb-4"></i>
                <h2 class="text-2xl font-bold text-red-600">Lỗi Tải Đề Thi</h2>
                <p class="text-slate-600 mt-2">Không có câu hỏi nào cho bài thi này hoặc đã xảy ra lỗi. Vui lòng quay lại và thử lại.</p>
                <a href="/index.php" class="mt-6 inline-block py-2 px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                    Về trang chủ
                </a>
            </div>
        <?php else: ?>
            <header class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                <h1 class="text-2xl md:text-3xl font-bold text-slate-800"><?= htmlspecialchars($title) ?></h1>
                <div id="timer-container" class="flex items-center gap-2 text-xl font-semibold bg-white px-4 py-2 rounded-lg shadow-sm border">
                    <i class="bi bi-clock-history text-blue-500"></i>
                    <span id="timer">--:--</span>
                </div>
            </header>

            <!-- Thanh tiến trình -->
            <div class="mb-6">
                <div class="flex justify-between mb-1">
                    <span class="text-base font-medium text-slate-700">Tiến trình</span>
                    <span id="progress-text" class="text-sm font-medium text-slate-700">0/<?= count($questions) ?> câu</span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2.5">
                    <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Cột chính: Câu hỏi và Đáp án -->
                <main class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-6 md:p-8 flex flex-col">
                    <div id="quiz-content" class="flex-grow">
                        <h2 id="question-text" class="text-xl font-semibold text-slate-900 mb-6 min-h-[56px]"></h2>
                        <div id="options-container" class="flex flex-col gap-4"></div>
                    </div>
                    <!-- Điều hướng câu hỏi -->
                    <div class="mt-8 pt-6 border-t border-slate-200 flex justify-between items-center">
                        <button id="prev-btn" class="px-5 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <i class="bi bi-arrow-left"></i> Trước
                        </button>
                        <div id="counter" class="text-slate-600 font-medium text-lg"></div>
                        <button id="next-btn" class="px-5 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                            Sau <i class="bi bi-arrow-right"></i>
                        </button>
                        <button id="submit-btn" class="hidden px-5 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                            <i class="bi bi-check-circle-fill"></i> Nộp bài
                        </button>
                    </div>
                </main>

                <!-- Cột phụ: Danh sách câu hỏi -->
                <aside class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-slate-800 mb-4 border-b pb-3">Danh sách câu hỏi</h3>
                    <div id="question-nav" class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-7 lg:grid-cols-5 gap-3 max-h-[400px] overflow-y-auto pr-2">
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Kết quả -->
    <div id="result-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 hidden z-50 transition-opacity duration-300 opacity-0">
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center max-w-sm w-full transform transition-all scale-95" id="modal-content">
            <i class="bi bi-award-fill text-yellow-400 text-7xl mb-4"></i>
            <h2 class="text-3xl font-bold text-slate-800 mb-2">Hoàn thành!</h2>
            <p class="text-slate-600 mb-6">Bạn đã hoàn thành bài kiểm tra.</p>
            <div class="space-y-3 text-lg bg-slate-50 p-4 rounded-lg">
                <p id="score-text" class="font-semibold flex justify-between"><span>Số câu đúng:</span> <span class="font-bold text-green-600"></span></p>
                <p id="score-percentage" class="font-bold text-blue-600 flex justify-between border-t pt-3 mt-3"><span>Điểm số:</span> <span class="text-2xl"></span></p>
            </div>
            <a href="/index.php" class="mt-8 inline-block w-full py-3 px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
                Quay về trang chủ
            </a>
        </div>
    </div>


    <script>
        const questionsRaw = <?= json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
        const timeLimitInMinutes = <?= $time_limit_minutes ?>;
        const userId = <?= $user_id_for_js ?>;
        const quizId = <?= $exam_id_for_js ?>;

        const shouldShuffleQuestions = <?= json_encode($shuffle_questions) ?>;
        const shouldShuffleAnswers = <?= json_encode($shuffle_answers) ?>;

        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]]; // ES6 swap
            }
        }

        let questions = questionsRaw;
        if (shouldShuffleAnswers) {
            questions.forEach(question => {
                const combinedOptions = question.options.map((option, index) => {
                    return {
                        text: option,
                        id: question.optionIds[index]
                    };
                });

                shuffleArray(combinedOptions);

                question.options = combinedOptions.map(item => item.text);
                question.optionIds = combinedOptions.map(item => item.id);
            });
        }

        if (shouldShuffleQuestions) {
            shuffleArray(questions);
        }


        if (questions && questions.length > 0) {
            let currentIndex = 0;
            let chosen = new Array(questions.length).fill(null);
            let isQuizSubmitted = false;
            let timerInterval;

            const qText = document.getElementById('question-text');
            const opts = document.getElementById('options-container');
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const submitBtn = document.getElementById('submit-btn');
            const counter = document.getElementById('counter');
            const qNav = document.getElementById('question-nav');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const timerDisplay = document.getElementById('timer');

            function startTimer(durationInMinutes) {
                let timer = durationInMinutes * 60;
                timerInterval = setInterval(() => {
                    let minutes = parseInt(timer / 60, 10);
                    let seconds = parseInt(timer % 60, 10);
                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;
                    timerDisplay.textContent = `${minutes}:${seconds}`;

                    if (--timer < 0) {
                        clearInterval(timerInterval);
                        timerDisplay.textContent = "Hết giờ!";
                        alert('Đã hết thời gian làm bài! Hệ thống sẽ tự động nộp bài của bạn.');
                        submitQuiz();
                    }
                }, 1000);
            }

            function buildPayload() {
                const payload = {
                    answers: {},
                    userId: userId,
                    quizId: quizId
                };
                questions.forEach((q, idx) => {
                    if (chosen[idx] !== null) payload.answers[q.id] = chosen[idx];
                });
                return payload;
            }

            window.addEventListener('beforeunload', (event) => {
                if (!isQuizSubmitted) {
                    const payload = buildPayload();
                    const blob = new Blob([JSON.stringify(payload)], {
                        type: 'application/json'
                    });
                    navigator.sendBeacon('/submit_quiz.php', blob);

                    event.preventDefault();
                    event.returnValue = 'Bạn có chắc chắn muốn rời khỏi trang? Bài làm của bạn sẽ được nộp với các câu trả lời hiện tại.';
                    return 'Bạn có chắc chắn muốn rời khỏi trang? Bài làm của bạn sẽ được nộp với các câu trả lời hiện tại.';
                }
            });

            function renderQuestionNav() {
                qNav.innerHTML = '';
                questions.forEach((_, index) => {
                    const navBtn = document.createElement('button');
                    navBtn.textContent = index + 1;
                    navBtn.className = 'w-10 h-10 flex items-center justify-center border rounded-md font-medium transition';
                    if (chosen[index] !== null) {
                        navBtn.classList.add('bg-blue-100', 'border-blue-300', 'text-blue-800');
                    } else {
                        navBtn.classList.add('bg-slate-50', 'hover:bg-slate-200');
                    }
                    if (index === currentIndex) {
                        navBtn.classList.add('bg-blue-600', 'text-white', 'border-blue-600', 'ring-2', 'ring-blue-300');
                        navBtn.classList.remove('bg-slate-50', 'hover:bg-slate-200', 'bg-blue-100', 'border-blue-300', 'text-blue-800');
                    }
                    navBtn.onclick = () => {
                        currentIndex = index;
                        render();
                    };
                    qNav.appendChild(navBtn);
                });
            }

            function updateProgress() {
                const answeredCount = chosen.filter(c => c !== null).length;
                const progress = (answeredCount / questions.length) * 100;
                progressBar.style.width = `${progress}%`;
                progressText.textContent = `${answeredCount}/${questions.length} câu`;
            }

            function render() {
                const q = questions[currentIndex];
                qText.innerHTML = `<strong>Câu ${currentIndex+1}:</strong> ${q.question}`;
                opts.innerHTML = '';
                q.options.forEach((opt, i) => {
                    const aid = q.optionIds[i];
                    const btn = document.createElement('button');
                    const isSelected = chosen[currentIndex] === aid;
                    const optionChar = String.fromCharCode(65 + i);
                    btn.className = `w-full text-left p-4 border rounded-lg transition text-slate-700 flex items-start gap-4 ${isSelected ? 'bg-blue-100 border-blue-500 ring-2 ring-blue-300' : 'bg-white hover:bg-slate-50 border-slate-300'}`;
                    btn.innerHTML = `
            <div class="flex-shrink-0 w-6 h-6 rounded-md flex items-center justify-center font-bold ${isSelected ? 'bg-blue-500 text-white' : 'bg-slate-200'}">
                ${optionChar}
            </div>
            <div>${opt}</div>`;
                    btn.onclick = () => {
                        chosen[currentIndex] = aid;
                        render();
                        setTimeout(() => {
                            if (currentIndex < questions.length - 1) {
                                currentIndex++;
                                render();
                            }
                        }, 300);
                    };
                    opts.appendChild(btn);
                });

                prevBtn.disabled = currentIndex === 0;
                nextBtn.disabled = currentIndex === questions.length - 1;
                nextBtn.classList.toggle('hidden', currentIndex === questions.length - 1);
                submitBtn.classList.toggle('hidden', currentIndex !== questions.length - 1);
                counter.textContent = `${currentIndex+1} / ${questions.length}`;

                renderQuestionNav();
                updateProgress();
            }

            prevBtn.onclick = () => {
                if (currentIndex > 0) {
                    currentIndex--;
                    render();
                }
            };
            nextBtn.onclick = () => {
                if (currentIndex < questions.length - 1) {
                    currentIndex++;
                    render();
                }
            };
            submitBtn.onclick = (event) => submitQuiz(event);

            startTimer(timeLimitInMinutes);
            render();

            function submitQuiz(event) {
                if (isQuizSubmitted) return;
                isQuizSubmitted = true;
                clearInterval(timerInterval);

                const unansweredCount = chosen.filter(c => c === null).length;
                if (event && event.type === 'click' && unansweredCount > 0) {
                    if (!confirm(`Bạn còn ${unansweredCount} câu chưa trả lời. Bạn có chắc chắn muốn nộp bài không?`)) {
                        isQuizSubmitted = false;
                        return;
                    }
                }

                const payload = buildPayload();

                submitBtn.disabled = true;
                submitBtn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Đang nộp...`;

                fetch('/submit_quiz.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const modal = document.getElementById('result-modal');
                            const modalContent = document.getElementById('modal-content');
                            document.querySelector('#score-text span:last-child').textContent = `${data.correct}/${data.total}`;
                            document.querySelector('#score-percentage span:last-child').textContent = `${data.score}/10`;
                            modal.classList.remove('hidden');
                            setTimeout(() => {
                                modal.classList.remove('opacity-0');
                                modalContent.classList.remove('scale-95');
                            }, 10);
                        } else {
                            alert('Lỗi: ' + (data.message || 'Không nộp được.'));
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = `<i class="bi bi-check-circle-fill"></i> Nộp bài`;
                            isQuizSubmitted = false;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Lỗi mạng khi nộp bài.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = `<i class="bi bi-check-circle-fill"></i> Nộp bài`;
                        isQuizSubmitted = false;
                    });
            }
        }
    </script>
</body>

</html>