<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

$userId = currentUserId();
$message = '';

$itineraryStmt = $pdo->prepare("
    SELECT DISTINCT i.id, i.title, i.budget, i.trip_status
    FROM itineraries i
    LEFT JOIN itinerary_members im ON i.id = im.itinerary_id
    WHERE i.creator_id = ? OR im.user_id = ?
    ORDER BY i.id DESC
");
$itineraryStmt->execute([$userId, $userId]);
$itineraries = $itineraryStmt->fetchAll();
$selectedItineraryId = (int)($_GET['itinerary_id'] ?? $_POST['itinerary_id'] ?? ($itineraries[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedItineraryId) {
    requireItineraryMember($pdo, $selectedItineraryId);
    $action = $_POST['action'] ?? 'save_budget';

    if ($action === 'save_budget') {
        $budget = (float)($_POST['budget'] ?? 0);
        $stmt = $pdo->prepare("UPDATE itineraries SET budget = ? WHERE id = ?");
        $stmt->execute([$budget, $selectedItineraryId]);
        $message = '預算已更新。';
    }

    if ($action === 'add_budget_item') {
        $category = trim($_POST['category'] ?? '其他');
        $itemName = trim($_POST['item_name'] ?? '');
        $estimatedAmount = (float)($_POST['estimated_amount'] ?? 0);
        if ($itemName !== '' && $estimatedAmount >= 0) {
            $stmt = $pdo->prepare("
                INSERT INTO budget_items (itinerary_id, category, item_name, estimated_amount, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$selectedItineraryId, $category ?: '其他', $itemName, $estimatedAmount, $userId]);
            $message = '預計花費已加入計算機。';
        }
    }

    if ($action === 'delete_budget_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM budget_items WHERE id = ? AND itinerary_id = ?");
        $stmt->execute([$itemId, $selectedItineraryId]);
        $message = '預計花費已移除。';
    }
}

$selectedItinerary = null;
foreach ($itineraries as $itinerary) {
    if ((int)$itinerary['id'] === $selectedItineraryId) {
        $selectedItinerary = $itinerary;
        break;
    }
}

$totalExpense = 0;
$categoryRows = [];
$budgetItems = [];
$budgetItemTotal = 0;
$settlementSummary = ['unsettled_total' => 0, 'unsettled_count' => 0];

if ($selectedItineraryId) {
    requireItineraryMember($pdo, $selectedItineraryId);

    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE itinerary_id = ?");
    $totalStmt->execute([$selectedItineraryId]);
    $totalExpense = (float)$totalStmt->fetchColumn();

    $catStmt = $pdo->prepare("
        SELECT category, COUNT(*) AS count_items, COALESCE(SUM(amount), 0) AS total
        FROM expenses
        WHERE itinerary_id = ?
        GROUP BY category
        ORDER BY total DESC
    ");
    $catStmt->execute([$selectedItineraryId]);
    $categoryRows = $catStmt->fetchAll();

    $itemStmt = $pdo->prepare("
        SELECT *
        FROM budget_items
        WHERE itinerary_id = ?
        ORDER BY created_at DESC, id DESC
    ");
    $itemStmt->execute([$selectedItineraryId]);
    $budgetItems = $itemStmt->fetchAll();
    $budgetItemTotal = array_sum(array_map(fn($item) => (float)$item['estimated_amount'], $budgetItems));

    $settleStmt = $pdo->prepare("
        SELECT COALESCE(SUM(es.share_amount), 0) AS unsettled_total, COUNT(*) AS unsettled_count
        FROM expense_splits es
        JOIN expenses e ON es.expense_id = e.id
        WHERE e.itinerary_id = ? AND es.is_settled = 0 AND es.user_id != e.paid_by
    ");
    $settleStmt->execute([$selectedItineraryId]);
    $settlementSummary = $settleStmt->fetch();
}

include 'header.php';
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
        <div>
            <h1 class="text-xl font-bold text-slate-800">預算計算機</h1>
            <p class="text-sm text-slate-500 mt-1">把餐廳、景點門票、租車、住宿等預計花費加入計算機，再和實際代墊比較。</p>
        </div>

        <?php if ($message): ?>
            <div class="text-sm text-green-700 bg-green-50 border border-green-200 p-3 rounded-xl"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (empty($itineraries)): ?>
            <div class="text-sm text-slate-500 bg-slate-50 border border-slate-200 p-4 rounded-xl">目前沒有行程。</div>
        <?php else: ?>
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <select name="itinerary_id" class="flex-1 px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm bg-white">
                    <?php foreach ($itineraries as $itinerary): ?>
                        <option value="<?php echo (int)$itinerary['id']; ?>" <?php echo (int)$itinerary['id'] === $selectedItineraryId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($itinerary['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-slate-900 text-white font-semibold text-sm px-4 py-2 rounded-xl">查看</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($selectedItinerary): ?>
        <?php
        $budget = (float)$selectedItinerary['budget'];
        $projectedTotal = max($budgetItemTotal, $budget);
        $remaining = $projectedTotal - $totalExpense;
        $usedPercent = $projectedTotal > 0 ? min(100, round(($totalExpense / $projectedTotal) * 100)) : 0;
        ?>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <p class="text-xs text-slate-500 font-semibold">設定預算</p>
                <p class="text-2xl font-black text-slate-900 mt-2">NT$ <?php echo number_format($budget, 0); ?></p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <p class="text-xs text-slate-500 font-semibold">預計花費</p>
                <p class="text-2xl font-black text-slate-900 mt-2">NT$ <?php echo number_format($budgetItemTotal, 0); ?></p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <p class="text-xs text-slate-500 font-semibold">實際花費</p>
                <p class="text-2xl font-black text-indigo-700 mt-2">NT$ <?php echo number_format($totalExpense, 0); ?></p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <p class="text-xs text-slate-500 font-semibold">剩餘</p>
                <p class="text-2xl font-black <?php echo $remaining < 0 ? 'text-red-600' : 'text-green-700'; ?> mt-2">NT$ <?php echo number_format($remaining, 0); ?></p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <p class="text-xs text-slate-500 font-semibold">未結清</p>
                <p class="text-2xl font-black text-amber-600 mt-2">NT$ <?php echo number_format((float)$settlementSummary['unsettled_total'], 0); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-bold text-slate-800 mb-4">加入預計花費</h2>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="itinerary_id" value="<?php echo $selectedItineraryId; ?>">
                    <input type="hidden" name="action" value="add_budget_item">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <select name="category" class="px-4 py-2 border border-slate-300 rounded-xl text-sm bg-white">
                            <option>餐飲</option>
                            <option>交通</option>
                            <option>住宿</option>
                            <option>門票</option>
                            <option>購物</option>
                            <option>其他</option>
                        </select>
                        <input type="text" name="item_name" required placeholder="項目名稱"
                               class="px-4 py-2 border border-slate-300 rounded-xl text-sm">
                        <input type="number" name="estimated_amount" min="0" step="1" required placeholder="預估金額"
                               class="px-4 py-2 border border-slate-300 rounded-xl text-sm">
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm px-5 py-2 rounded-xl">加入計算機</button>
                </form>

                <div class="mt-5 divide-y divide-slate-100">
                    <?php if (empty($budgetItems)): ?>
                        <p class="text-sm text-slate-400 py-6 text-center">尚未加入預計花費。</p>
                    <?php endif; ?>
                    <?php foreach ($budgetItems as $item): ?>
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($item['category']); ?></div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold">NT$ <?php echo number_format((float)$item['estimated_amount'], 0); ?></span>
                                <form method="POST">
                                    <input type="hidden" name="itinerary_id" value="<?php echo $selectedItineraryId; ?>">
                                    <input type="hidden" name="action" value="delete_budget_item">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                                    <button type="submit" class="text-xs text-red-600 hover:underline">刪除</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col gap-3 mb-6">
                    <form method="POST" class="flex flex-col sm:flex-row gap-3">
                        <input type="hidden" name="itinerary_id" value="<?php echo $selectedItineraryId; ?>">
                        <input type="hidden" name="action" value="save_budget">
                        <input type="number" name="budget" min="0" step="1" value="<?php echo htmlspecialchars((string)$budget); ?>"
                               class="flex-1 px-4 py-2 border border-slate-300 rounded-xl text-sm">
                        <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm px-5 py-2 rounded-xl">儲存總預算</button>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        <a href="expense_list.php?itinerary_id=<?php echo $selectedItineraryId; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm px-5 py-2 rounded-xl">新增代墊</a>
                        <a href="settlement_report.php?itinerary_id=<?php echo $selectedItineraryId; ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-5 py-2 rounded-xl">查看報表</a>
                    </div>
                </div>

                <div class="mb-6">
                    <div class="flex justify-between text-xs text-slate-500 mb-1">
                        <span>預算使用率</span>
                        <span><?php echo $usedPercent; ?>%</span>
                    </div>
                    <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full <?php echo $remaining < 0 ? 'bg-red-500' : 'bg-indigo-600'; ?>" style="width: <?php echo $usedPercent; ?>%"></div>
                    </div>
                </div>

                <h2 class="text-lg font-bold text-slate-800 mb-3">實際花費類別</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-400 font-bold border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3">類別</th>
                                <th class="px-4 py-3">筆數</th>
                                <th class="px-4 py-3 text-right">金額</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($categoryRows)): ?>
                                <tr><td colspan="3" class="text-center py-8 text-slate-400">尚無花費資料。</td></tr>
                            <?php endif; ?>
                            <?php foreach ($categoryRows as $row): ?>
                                <tr>
                                    <td class="px-4 py-3 font-bold text-slate-800"><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td class="px-4 py-3"><?php echo (int)$row['count_items']; ?></td>
                                    <td class="px-4 py-3 text-right font-bold">NT$ <?php echo number_format((float)$row['total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
