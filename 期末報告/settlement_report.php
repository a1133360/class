<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

$userId = currentUserId();
$itineraryStmt = $pdo->prepare("
    SELECT DISTINCT i.id, i.title
    FROM itineraries i
    LEFT JOIN itinerary_members im ON i.id = im.itinerary_id
    WHERE i.creator_id = ? OR im.user_id = ?
    ORDER BY i.id DESC
");
$itineraryStmt->execute([$userId, $userId]);
$itineraries = $itineraryStmt->fetchAll();

$selectedItineraryId = (int)($_GET['itinerary_id'] ?? 0);
if ($selectedItineraryId) {
    requireItineraryMember($pdo, $selectedItineraryId);
}

$params = [$userId, $userId];
$whereItinerary = '';
if ($selectedItineraryId) {
    $whereItinerary = ' AND e.itinerary_id = ?';
    $params[] = $selectedItineraryId;
}

$stmt = $pdo->prepare("
    SELECT
        es.id AS split_id,
        es.share_amount,
        e.description,
        e.category,
        i.title AS itinerary_title,
        creditor.username AS creditor
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    JOIN itineraries i ON e.itinerary_id = i.id
    JOIN users creditor ON e.paid_by = creditor.id
    WHERE es.user_id = ?
      AND es.is_settled = 0
      AND e.paid_by != ?
      $whereItinerary
    ORDER BY i.id DESC, e.created_at DESC
");
$stmt->execute($params);
$debts = $stmt->fetchAll();

$receiveParams = [$userId, $userId];
if ($selectedItineraryId) {
    $receiveParams[] = $selectedItineraryId;
}
$receiveStmt = $pdo->prepare("
    SELECT
        es.share_amount,
        e.description,
        e.category,
        i.title AS itinerary_title,
        debtor.username AS debtor
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    JOIN itineraries i ON e.itinerary_id = i.id
    JOIN users debtor ON es.user_id = debtor.id
    WHERE e.paid_by = ?
      AND es.is_settled = 0
      AND es.user_id != ?
      $whereItinerary
    ORDER BY i.id DESC, e.created_at DESC
");
$receiveStmt->execute($receiveParams);
$receivables = $receiveStmt->fetchAll();

$debtTotal = array_sum(array_map(fn($item) => (float)$item['share_amount'], $debts));
$receiveTotal = array_sum(array_map(fn($item) => (float)$item['share_amount'], $receivables));

$chartRows = [];
if ($selectedItineraryId) {
    $chartStmt = $pdo->prepare("
        SELECT category, COALESCE(SUM(amount), 0) AS total
        FROM expenses
        WHERE itinerary_id = ?
        GROUP BY category
        ORDER BY total DESC
    ");
    $chartStmt->execute([$selectedItineraryId]);
    $chartRows = $chartStmt->fetchAll();
} else {
    $chartStmt = $pdo->prepare("
        SELECT e.category, COALESCE(SUM(e.amount), 0) AS total
        FROM expenses e
        JOIN itineraries i ON e.itinerary_id = i.id
        LEFT JOIN itinerary_members im ON i.id = im.itinerary_id
        WHERE i.creator_id = ? OR im.user_id = ?
        GROUP BY e.category
        ORDER BY total DESC
    ");
    $chartStmt->execute([$userId, $userId]);
    $chartRows = $chartStmt->fetchAll();
}
$chartLabels = array_column($chartRows, 'category');
$chartAmounts = array_map('floatval', array_column($chartRows, 'total'));

include 'header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-end gap-3">
            <div class="flex-1">
                <h1 class="text-xl font-bold text-slate-800">清單及報表</h1>
                <p class="text-sm text-slate-500 mt-1">查看每筆未結清款項、應收款項，並可直接標記已結算。</p>
            </div>
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <select name="itinerary_id" class="px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm bg-white">
                    <option value="0">全部行程</option>
                    <?php foreach ($itineraries as $itinerary): ?>
                        <option value="<?php echo (int)$itinerary['id']; ?>" <?php echo (int)$itinerary['id'] === $selectedItineraryId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($itinerary['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-slate-900 text-white font-semibold text-sm px-4 py-2 rounded-xl">查看</button>
            </form>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="mt-4 text-sm text-green-700 bg-green-50 border border-green-200 p-3 rounded-xl">已標記為已結算。</div>
        <?php endif; ?>
    </div>

    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">我需要付款</p>
            <p class="text-3xl font-black text-red-600 mt-2">NT$ <?php echo number_format($debtTotal, 0); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">我可收款</p>
            <p class="text-3xl font-black text-emerald-700 mt-2">NT$ <?php echo number_format($receiveTotal, 0); ?></p>
        </div>
    </section>

    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-lg font-bold text-slate-800 mb-4">旅遊消費圓餅圖</h2>
        <?php if (empty($chartRows)): ?>
            <p class="text-sm text-slate-400 text-center py-10">尚無消費資料。</p>
        <?php else: ?>
            <div class="max-w-md mx-auto">
                <canvas id="personalExpenseChart" height="260"></canvas>
            </div>
        <?php endif; ?>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-800">我欠別人的款項</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-400 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3.5">項目</th>
                            <th class="px-6 py-3.5">收款人</th>
                            <th class="px-6 py-3.5 text-right">金額</th>
                            <th class="px-6 py-3.5 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($debts)): ?>
                            <tr><td colspan="4" class="text-center py-10 text-slate-400 text-xs">沒有未結清欠款。</td></tr>
                        <?php endif; ?>
                        <?php foreach ($debts as $debt): ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($debt['description'] ?: $debt['category']); ?></div>
                                    <div class="text-xs text-slate-400"><?php echo htmlspecialchars($debt['itinerary_title']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-indigo-600">@<?php echo htmlspecialchars($debt['creditor']); ?></td>
                                <td class="px-6 py-4 text-right text-red-500 font-extrabold">NT$ <?php echo number_format((float)$debt['share_amount'], 2); ?></td>
                                <td class="px-6 py-4 text-right">
                                    <form action="api_settle_debt.php" method="POST">
                                        <input type="hidden" name="split_id" value="<?php echo (int)$debt['split_id']; ?>">
                                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-3 py-1.5 rounded-xl shadow-sm transition">
                                            標記已結算
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-800">別人欠我的款項</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-400 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3.5">項目</th>
                            <th class="px-6 py-3.5">付款人</th>
                            <th class="px-6 py-3.5 text-right">金額</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($receivables)): ?>
                            <tr><td colspan="3" class="text-center py-10 text-slate-400 text-xs">沒有待收款項。</td></tr>
                        <?php endif; ?>
                        <?php foreach ($receivables as $item): ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($item['description'] ?: $item['category']); ?></div>
                                    <div class="text-xs text-slate-400"><?php echo htmlspecialchars($item['itinerary_title']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-indigo-600">@<?php echo htmlspecialchars($item['debtor']); ?></td>
                                <td class="px-6 py-4 text-right text-amber-600 font-extrabold">NT$ <?php echo number_format((float)$item['share_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php if (!empty($chartRows)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('personalExpenseChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{
            data: <?php echo json_encode($chartAmounts); ?>,
            backgroundColor: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#64748b', '#a855f7', '#14b8a6']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
