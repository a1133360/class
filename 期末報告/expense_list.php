<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

$userId = currentUserId();
$itineraryStmt = $pdo->prepare("
    SELECT DISTINCT i.id, i.title, i.trip_status
    FROM itineraries i
    LEFT JOIN itinerary_members im ON i.id = im.itinerary_id
    WHERE i.creator_id = ? OR im.user_id = ?
    ORDER BY i.id DESC
");
$itineraryStmt->execute([$userId, $userId]);
$itineraries = $itineraryStmt->fetchAll();

$selectedItineraryId = (int)($_GET['itinerary_id'] ?? ($itineraries[0]['id'] ?? 0));
$selectedItinerary = null;
foreach ($itineraries as $itinerary) {
    if ((int)$itinerary['id'] === $selectedItineraryId) {
        $selectedItinerary = $itinerary;
        break;
    }
}

$members = [];
$recentExpenses = [];
if ($selectedItineraryId) {
    requireItineraryMember($pdo, $selectedItineraryId);
    $memberStmt = $pdo->prepare("
        SELECT u.id, u.username
        FROM itinerary_members im
        JOIN users u ON im.user_id = u.id
        WHERE im.itinerary_id = ?
        UNION
        SELECT u.id, u.username
        FROM itineraries i
        JOIN users u ON i.creator_id = u.id
        WHERE i.id = ?
        ORDER BY username
    ");
    $memberStmt->execute([$selectedItineraryId, $selectedItineraryId]);
    $members = $memberStmt->fetchAll();

    $expenseStmt = $pdo->prepare("
        SELECT e.*, u.username AS paid_by_name
        FROM expenses e
        JOIN users u ON e.paid_by = u.id
        WHERE e.itinerary_id = ?
        ORDER BY e.created_at DESC, e.id DESC
        LIMIT 10
    ");
    $expenseStmt->execute([$selectedItineraryId]);
    $recentExpenses = $expenseStmt->fetchAll();
}

include 'header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800">新增代墊</h1>
            <p class="text-sm text-slate-500 mt-1">記錄行程花費、類別與分攤成員；送出後會回到本頁並保留目前行程。</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="text-sm text-green-700 bg-green-50 border border-green-200 p-3 rounded-xl">代墊已新增。</div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="text-sm text-red-700 bg-red-50 border border-red-200 p-3 rounded-xl">新增失敗，請確認欄位與分攤成員。</div>
        <?php endif; ?>

        <?php if (empty($itineraries)): ?>
            <div class="text-sm text-slate-500 bg-slate-50 border border-slate-200 p-4 rounded-xl">目前沒有行程，請先建立行程。</div>
        <?php else: ?>
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <select name="itinerary_id" class="flex-1 px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm bg-white">
                    <?php foreach ($itineraries as $itinerary): ?>
                        <option value="<?php echo (int)$itinerary['id']; ?>" <?php echo (int)$itinerary['id'] === $selectedItineraryId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($itinerary['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-slate-900 text-white font-semibold text-sm px-4 py-2 rounded-xl">切換行程</button>
            </form>

            <?php if (($selectedItinerary['trip_status'] ?? '') === 'finished'): ?>
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 p-3 rounded-xl">此行程已結束，不能再新增代墊。</div>
            <?php else: ?>
                <form action="api_add_expense.php" method="POST" class="space-y-4" onsubmit="syncCategory()">
                    <input type="hidden" name="itinerary_id" value="<?php echo $selectedItineraryId; ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">花費類別</label>
                            <select id="category_select" class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm bg-white">
                                <option value="餐飲">餐飲</option>
                                <option value="交通">交通</option>
                                <option value="住宿">住宿</option>
                                <option value="門票">門票</option>
                                <option value="購物">購物</option>
                                <option value="其他">其他</option>
                                <option value="custom">自訂類別</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">自訂類別</label>
                            <input type="text" id="custom_category" placeholder="例如：保險、租車、伴手禮"
                                   class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                            <input type="hidden" name="category" id="category" value="餐飲">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">金額 NT$</label>
                        <input type="number" name="amount" step="0.01" min="1" required placeholder="0.00"
                               class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">說明</label>
                        <input type="text" name="description" placeholder="例如：晚餐、機場捷運、飯店訂金"
                               class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">分攤成員</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-1">
                            <?php foreach ($members as $member): ?>
                                <label class="flex items-center text-sm font-medium bg-white px-3 py-2 border border-slate-200 rounded-lg cursor-pointer shadow-sm hover:border-indigo-200 transition select-none">
                                    <input type="checkbox" name="split_users[]" value="<?php echo (int)$member['id']; ?>" checked class="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-slate-300 rounded mr-2">
                                    <?php echo htmlspecialchars($member['username']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl shadow-sm transition text-sm">
                        新增代墊
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($recentExpenses)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-bold text-slate-800">最近新增</h2>
                <a href="settlement_report.php?itinerary_id=<?php echo $selectedItineraryId; ?>" class="text-xs font-bold text-indigo-600">查看報表</a>
            </div>
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($recentExpenses as $expense): ?>
                        <tr>
                            <td class="px-6 py-3">
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($expense['description'] ?: $expense['category']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($expense['category']); ?> · <?php echo htmlspecialchars($expense['paid_by_name']); ?></div>
                            </td>
                            <td class="px-6 py-3 text-right font-bold">NT$ <?php echo number_format((float)$expense['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function syncCategory() {
    const selected = document.getElementById('category_select').value;
    const custom = document.getElementById('custom_category').value.trim();
    document.getElementById('category').value = selected === 'custom' && custom ? custom : selected;
}
</script>

<?php include 'footer.php'; ?>
