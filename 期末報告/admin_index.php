<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkAdmin();
include 'header.php';

$stats = [
    'total_amount' => (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn(),
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'admins' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'itineraries' => (int)$pdo->query("SELECT COUNT(*) FROM itineraries")->fetchColumn(),
    'active_itineraries' => (int)$pdo->query("SELECT COUNT(*) FROM itineraries WHERE trip_status = 'active'")->fetchColumn(),
    'finished_itineraries' => (int)$pdo->query("SELECT COUNT(*) FROM itineraries WHERE trip_status = 'finished'")->fetchColumn(),
    'pending_templates' => (int)$pdo->query("SELECT COUNT(*) FROM itineraries WHERE is_public = 'yes' AND status = 'pending'")->fetchColumn(),
    'spots' => (int)$pdo->query("SELECT COUNT(*) FROM spots")->fetchColumn(),
    'unsettled' => (float)$pdo->query("
        SELECT COALESCE(SUM(es.share_amount), 0)
        FROM expense_splits es
        JOIN expenses e ON es.expense_id = e.id
        WHERE es.is_settled = 0 AND es.user_id != e.paid_by
    ")->fetchColumn(),
    'sent_emails' => (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'sent'")->fetchColumn(),
    'failed_emails' => (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'failed'")->fetchColumn(),
];

$categoryRows = $pdo->query("
    SELECT category, COALESCE(SUM(amount), 0) AS total
    FROM expenses
    GROUP BY category
    ORDER BY total DESC
")->fetchAll();

$monthlyRows = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_label, COALESCE(SUM(amount), 0) AS total
    FROM expenses
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_label DESC
    LIMIT 6
")->fetchAll();

$growthRows = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_label, COUNT(*) AS total
    FROM users
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_label DESC
    LIMIT 6
")->fetchAll();

$popularRows = $pdo->query("
    SELECT COALESCE(NULLIF(destination, ''), '未填寫') AS destination, COUNT(*) AS total
    FROM itineraries
    GROUP BY COALESCE(NULLIF(destination, ''), '未填寫')
    ORDER BY total DESC
    LIMIT 8
")->fetchAll();

$categoryLabels = array_column($categoryRows, 'category');
$categoryAmounts = array_map('floatval', array_column($categoryRows, 'total'));
$monthLabels = array_reverse(array_column($monthlyRows, 'month_label'));
$monthAmounts = array_reverse(array_map('floatval', array_column($monthlyRows, 'total')));
$growthLabels = array_reverse(array_column($growthRows, 'month_label'));
$growthAmounts = array_reverse(array_map('intval', array_column($growthRows, 'total')));
$popularLabels = array_column($popularRows, 'destination');
$popularAmounts = array_map('intval', array_column($popularRows, 'total'));
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900">管理員儀表板</h1>
            <p class="text-sm text-slate-500 mt-1">監控全站交易、使用者成長、熱門地區、範本與 Email 狀態。</p>
        </div>
        <div class="flex gap-2">
            <a href="admin_templates.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-4 py-2 rounded-xl">範本審核</a>
            <a href="admin_users.php" class="bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-4 py-2 rounded-xl">使用者管理</a>
        </div>
    </div>

    <section class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm md:col-span-2">
            <p class="text-xs text-slate-500 font-semibold">全站總交易金額</p>
            <p class="text-3xl font-black text-slate-900 mt-2">NT$ <?php echo number_format($stats['total_amount'], 0); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">未結清金額</p>
            <p class="text-3xl font-black text-red-600 mt-2">NT$ <?php echo number_format($stats['unsettled'], 0); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">待審範本</p>
            <p class="text-3xl font-black text-amber-600 mt-2"><?php echo $stats['pending_templates']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">一般使用者</p>
            <p class="text-3xl font-black text-slate-900 mt-2"><?php echo $stats['users']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">管理員</p>
            <p class="text-3xl font-black text-slate-900 mt-2"><?php echo $stats['admins']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">行程總數</p>
            <p class="text-3xl font-black text-slate-900 mt-2"><?php echo $stats['itineraries']; ?></p>
            <p class="text-xs text-slate-400 mt-1">進行中 <?php echo $stats['active_itineraries']; ?> · 已結束 <?php echo $stats['finished_itineraries']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">Email 狀態</p>
            <p class="text-2xl font-black text-slate-900 mt-2">成功 <?php echo $stats['sent_emails']; ?></p>
            <p class="text-xs text-red-500 mt-1">失敗 <?php echo $stats['failed_emails']; ?></p>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <h2 class="font-bold text-slate-800 mb-4">類別交易金額</h2>
            <canvas id="categoryChart" height="220"></canvas>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <h2 class="font-bold text-slate-800 mb-4">近 6 個月交易金額</h2>
            <canvas id="monthlyChart" height="220"></canvas>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <h2 class="font-bold text-slate-800 mb-4">用戶增長趨勢</h2>
            <canvas id="growthChart" height="220"></canvas>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
            <h2 class="font-bold text-slate-800 mb-4">熱門旅遊地區</h2>
            <canvas id="popularChart" height="220"></canvas>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($categoryLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{ data: <?php echo json_encode($categoryAmounts); ?>, backgroundColor: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#64748b', '#a855f7'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($monthLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{ label: '交易金額', data: <?php echo json_encode($monthAmounts); ?>, backgroundColor: '#4f46e5' }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($growthLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{ label: '新增使用者', data: <?php echo json_encode($growthAmounts); ?>, borderColor: '#0ea5e9', backgroundColor: 'rgba(14,165,233,.15)', fill: true, tension: .35 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('popularChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($popularLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{ label: '行程數', data: <?php echo json_encode($popularAmounts); ?>, backgroundColor: '#10b981' }]
    },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
});
</script>

<?php include 'footer.php'; ?>
