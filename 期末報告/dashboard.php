<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();
include 'header.php';

$userId = currentUserId();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT i.id) AS itinerary_count, COALESCE(SUM(e.amount), 0) AS total_expense
    FROM itineraries i
    LEFT JOIN itinerary_members im ON i.id = im.itinerary_id
    LEFT JOIN expenses e ON i.id = e.itinerary_id
    WHERE i.creator_id = ? OR im.user_id = ?
");
$stmt->execute([$userId, $userId]);
$summary = $stmt->fetch();

$debtStmt = $pdo->prepare("
    SELECT COALESCE(SUM(es.share_amount), 0)
    FROM expense_splits es
    JOIN expenses e ON es.expense_id = e.id
    WHERE es.user_id = ? AND es.is_settled = 0 AND e.paid_by != ?
");
$debtStmt->execute([$userId, $userId]);
$myDebt = (float)$debtStmt->fetchColumn();
?>

<div class="space-y-8">
    <section class="bg-gradient-to-r from-indigo-600 to-sky-600 rounded-2xl p-8 text-white shadow-sm">
        <h1 class="text-2xl font-black tracking-tight">歡迎回來，<?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        <p class="text-indigo-100 text-sm mt-1">建立行程、搜尋公開範本、記錄代墊，並查看你的結算狀態。</p>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">我的行程</p>
            <p class="text-3xl font-black text-slate-900 mt-2"><?php echo (int)$summary['itinerary_count']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">總花費</p>
            <p class="text-3xl font-black text-slate-900 mt-2">NT$ <?php echo number_format((float)$summary['total_expense'], 0); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <p class="text-xs text-slate-500 font-semibold">我未結清的款項</p>
            <p class="text-3xl font-black text-red-600 mt-2">NT$ <?php echo number_format($myDebt, 0); ?></p>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <a href="itinerary_create.php" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:border-indigo-300 transition">
            <h2 class="font-bold text-slate-800 text-lg">建立行程</h2>
            <p class="text-slate-500 text-sm mt-2">規劃新旅行、設定日期、預算與公開範本。</p>
            <span class="inline-block text-sm font-bold text-indigo-600 mt-4">開始建立</span>
        </a>
        <a href="itinerary_list.php" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:border-indigo-300 transition">
            <h2 class="font-bold text-slate-800 text-lg">我的行程</h2>
            <p class="text-slate-500 text-sm mt-2">邀請朋友、編輯共筆、管理每日路線。</p>
            <span class="inline-block text-sm font-bold text-indigo-600 mt-4">查看行程</span>
        </a>
        <a href="expense_list.php" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:border-indigo-300 transition">
            <h2 class="font-bold text-slate-800 text-lg">新增代墊</h2>
            <p class="text-slate-500 text-sm mt-2">輸入花費類別、金額與分攤成員。</p>
            <span class="inline-block text-sm font-bold text-indigo-600 mt-4">新增花費</span>
        </a>
        <a href="settlement_report.php" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:border-indigo-300 transition">
            <h2 class="font-bold text-slate-800 text-lg">清單及報表</h2>
            <p class="text-slate-500 text-sm mt-2">查看欠款、應收、消費圓餅圖。</p>
            <span class="inline-block text-sm font-bold text-indigo-600 mt-4">查看報表</span>
        </a>
    </section>

    <section class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-end gap-3">
            <div class="flex-1">
                <label for="templateKeyword" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">搜尋公開行程範本</label>
                <input id="templateKeyword" type="text" placeholder="例如：台南兩天一夜、三天兩夜露營、東京美食"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
            </div>
            <button type="button" onclick="searchTemplates()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-5 rounded-xl shadow-sm transition text-sm">搜尋</button>
        </div>
        <div id="templateResults" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4"></div>
    </section>
</div>

<script>
function searchTemplates() {
    const keyword = document.getElementById('templateKeyword').value;
    const results = document.getElementById('templateResults');
    results.innerHTML = '<p class="text-sm text-slate-400">搜尋中...</p>';
    fetch(`api_search_templates.php?keyword=${encodeURIComponent(keyword)}`)
        .then(response => response.json())
        .then(items => {
            if (!items.length) {
                results.innerHTML = '<p class="text-sm text-slate-500">找不到符合的公開範本。</p>';
                return;
            }
            results.innerHTML = items.map(item => `
                <a href="workspace.php?id=${item.id}" class="block border border-slate-200 rounded-xl p-4 hover:border-indigo-300 hover:bg-indigo-50/30 transition">
                    <div class="font-bold text-slate-800">${escapeHtml(item.title)}</div>
                    <div class="text-xs text-slate-500 mt-1">建立者：${escapeHtml(item.creator_name || '未知')}，景點 ${item.spot_count} 個</div>
                </a>
            `).join('');
        });
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));
}
</script>

<?php include 'footer.php'; ?>
