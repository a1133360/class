<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

$userId = currentUserId();
$stmt = $pdo->prepare("
    SELECT
        i.*,
        u.username AS creator_name,
        COUNT(DISTINCT im2.user_id) AS member_count,
        COUNT(DISTINCT s.id) AS spot_count,
        COALESCE(expense_totals.expense_total, 0) AS expense_total
    FROM itineraries i
    LEFT JOIN users u ON i.creator_id = u.id
    LEFT JOIN itinerary_members im ON i.id = im.itinerary_id
    LEFT JOIN itinerary_members im2 ON i.id = im2.itinerary_id
    LEFT JOIN spots s ON i.id = s.itinerary_id
    LEFT JOIN (
        SELECT itinerary_id, SUM(amount) AS expense_total
        FROM expenses
        GROUP BY itinerary_id
    ) expense_totals ON i.id = expense_totals.itinerary_id
    WHERE i.creator_id = ? OR im.user_id = ?
    GROUP BY i.id, u.username
    ORDER BY i.id DESC
");
$stmt->execute([$userId, $userId]);
$itineraries = $stmt->fetchAll();

include 'header.php';
?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div>
            <h1 class="text-lg font-bold text-slate-800">我的行程</h1>
            <p class="text-xs text-slate-500 mt-1">管理景點、共筆、邀請朋友、預算與結算報表。</p>
        </div>
        <a href="itinerary_create.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs px-3 py-2 rounded-xl shadow-sm transition text-center">
            建立行程
        </a>
    </div>

    <?php if (isset($_GET['finished'])): ?>
        <div class="mx-6 mt-4 text-sm text-green-700 bg-green-50 border border-green-200 p-3 rounded-xl">行程已標記為結束，可以查看清單與報表。</div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-xs uppercase text-slate-400 font-bold border-b border-slate-100">
                <tr>
                    <th class="px-6 py-3.5">行程</th>
                    <th class="px-6 py-3.5">狀態</th>
                    <th class="px-6 py-3.5">統計</th>
                    <th class="px-6 py-3.5 text-right">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
                <?php if (empty($itineraries)): ?>
                    <tr><td colspan="4" class="text-center py-8 text-slate-400 text-xs">目前沒有行程。</td></tr>
                <?php endif; ?>

                <?php foreach ($itineraries as $iti): ?>
                    <tr class="hover:bg-slate-50/50 transition align-top">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800"><?php echo htmlspecialchars($iti['title']); ?></div>
                            <div class="text-xs text-slate-400 mt-1">
                                建立者：<?php echo htmlspecialchars($iti['creator_name'] ?? '未知'); ?>
                                <?php if (!empty($iti['destination'])): ?>
                                    · <?php echo htmlspecialchars($iti['destination']); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 space-y-1">
                            <span class="inline-block px-2.5 py-0.5 text-xs rounded-full <?php echo $iti['trip_status'] === 'finished' ? 'bg-slate-100 text-slate-700' : 'bg-green-50 text-green-700 border border-green-100'; ?>">
                                <?php echo $iti['trip_status'] === 'finished' ? '已結束' : '進行中'; ?>
                            </span>
                            <span class="inline-block px-2.5 py-0.5 text-xs rounded-full <?php echo $iti['is_public'] === 'yes' ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-slate-100 text-slate-600'; ?>">
                                <?php echo $iti['is_public'] === 'yes' ? '公開範本：' . htmlspecialchars($iti['status']) : '私人行程'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">
                            成員 <?php echo (int)$iti['member_count']; ?> 人<br>
                            景點 <?php echo (int)$iti['spot_count']; ?> 個<br>
                            花費 NT$ <?php echo number_format((float)$iti['expense_total'], 0); ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="workspace.php?id=<?php echo (int)$iti['id']; ?>" class="text-xs text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-xl font-bold transition shadow-sm">工作區</a>
                                <a href="budget_calculator.php?itinerary_id=<?php echo (int)$iti['id']; ?>" class="text-xs text-indigo-700 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-xl font-bold transition">預算</a>
                                <a href="settlement_report.php?itinerary_id=<?php echo (int)$iti['id']; ?>" class="text-xs text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-xl font-bold transition">報表</a>
                                <?php if ((int)$iti['creator_id'] === $userId && $iti['trip_status'] !== 'finished'): ?>
                                    <form action="api_finish_itinerary.php" method="POST" onsubmit="return confirm('確定要結束這個行程嗎？結束後不能再新增景點、共筆或代墊。');">
                                        <input type="hidden" name="itinerary_id" value="<?php echo (int)$iti['id']; ?>">
                                        <button type="submit" class="text-xs text-slate-700 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl font-bold transition">結束行程</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
