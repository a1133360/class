<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
function navClass($page, $currentPage) {
    $base = 'px-3 py-2 rounded-lg transition';
    return $page === $currentPage
        ? $base . ' bg-indigo-50 text-indigo-700 font-bold'
        : $base . ' hover:bg-slate-100 hover:text-slate-900';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Hub 行程共筆與分帳平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans antialiased">

<nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <a href="dashboard.php" class="text-lg font-black tracking-tight text-indigo-600 hover:text-indigo-700 transition">
                Travel Hub
            </a>

            <div class="hidden md:flex items-center space-x-1 text-sm font-medium text-slate-600">
                <a href="dashboard.php" class="<?php echo navClass('dashboard.php', $currentPage); ?>">首頁</a>
                <a href="itinerary_list.php" class="<?php echo navClass('itinerary_list.php', $currentPage); ?>">我的行程</a>
                <a href="budget_calculator.php" class="<?php echo navClass('budget_calculator.php', $currentPage); ?>">預算計算機</a>
                <a href="expense_list.php" class="<?php echo navClass('expense_list.php', $currentPage); ?>">新增代墊</a>
                <a href="settlement_report.php" class="<?php echo navClass('settlement_report.php', $currentPage); ?>">清單與報表</a>
                <a href="profile.php" class="<?php echo navClass('profile.php', $currentPage); ?>">個人資料</a>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="admin_index.php" class="px-3 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition font-bold">管理員</a>
                <?php endif; ?>
            </div>

            <div class="flex items-center space-x-3 text-sm">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-indigo-700 font-semibold bg-indigo-50 border border-indigo-100 px-3 py-1.5 rounded-full">
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'user'); ?>
                    </span>
                    <a href="logout.php" class="text-slate-500 hover:text-red-500 transition font-medium">登出</a>
                <?php else: ?>
                    <a href="login.php" class="text-indigo-600 hover:text-indigo-700 font-semibold px-3 py-1.5">登入</a>
                    <a href="register.php" class="bg-indigo-600 text-white hover:bg-indigo-700 px-4 py-2 rounded-lg transition font-semibold shadow-sm">註冊</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
