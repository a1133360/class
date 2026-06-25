<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

$userId = currentUserId();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $startDate = $_POST['start_date'] ?: null;
    $endDate = $_POST['end_date'] ?: null;
    $isPublic = ($_POST['is_public'] ?? 'no') === 'yes' ? 'yes' : 'no';
    $budget = (float)($_POST['budget'] ?? 0);
    $memberIds = array_map('intval', $_POST['member_ids'] ?? []);
    $memberIds = array_values(array_unique(array_filter($memberIds, fn($id) => $id !== $userId)));

    if ($title === '') {
        $message = '請輸入行程名稱。';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO itineraries (title, destination, start_date, end_date, creator_id, budget, is_public, status, trip_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'active')
            ");
            $stmt->execute([$title, $destination ?: null, $startDate, $endDate, $userId, $budget, $isPublic]);
            $itineraryId = (int)$pdo->lastInsertId();

            $memberStmt = $pdo->prepare("
                INSERT INTO itinerary_members (itinerary_id, user_id, role)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE role = VALUES(role)
            ");
            $memberStmt->execute([$itineraryId, $userId, 'owner']);

            $inviteStmt = $pdo->prepare("
                INSERT INTO itinerary_invitations (itinerary_id, inviter_id, invitee_id, status)
                VALUES (?, ?, ?, 'accepted')
            ");

            foreach ($memberIds as $memberId) {
                $memberStmt->execute([$itineraryId, $memberId, 'member']);
                $inviteStmt->execute([$itineraryId, $userId, $memberId]);
            }

            $pdo->commit();
            header('Location: workspace.php?id=' . $itineraryId);
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = '建立失敗：' . $e->getMessage();
        }
    }
}

$usersStmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id != ? AND status = 'active' ORDER BY username");
$usersStmt->execute([$userId]);
$users = $usersStmt->fetchAll();

include 'header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800">建立新行程</h1>
            <p class="text-sm text-slate-500 mt-1">建立行程後即可進入工作區，新增景點、邀請朋友、記錄代墊與結算。</p>
        </div>

        <?php if ($message): ?>
            <div class="text-sm text-red-700 bg-red-50 border border-red-200 p-3 rounded-xl"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">行程名稱</label>
                <input type="text" name="title" required placeholder="例如：東京五日自由行"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">目的地 / 熱門地區統計</label>
                <input type="text" name="destination" placeholder="例如：台南、東京、清邁"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">出發日期</label>
                    <input type="date" name="start_date"
                           class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">結束日期</label>
                    <input type="date" name="end_date"
                           class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">預算 NT$</label>
                <input type="number" name="budget" min="0" step="1" value="0"
                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm">
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">公開範本</label>
                <div class="flex flex-wrap items-center gap-4 text-sm font-medium">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="is_public" value="no" checked class="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-slate-300 mr-2">
                        私人行程
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="is_public" value="yes" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-slate-300 mr-2">
                        送出公開範本審核
                    </label>
                </div>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">先邀請已註冊朋友</label>
                <?php if (empty($users)): ?>
                    <p class="text-sm text-slate-500">目前沒有其他可邀請的使用者。</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach ($users as $user): ?>
                            <label class="flex items-center text-sm font-medium bg-white px-3 py-2 border border-slate-200 rounded-lg cursor-pointer hover:border-indigo-300 transition">
                                <input type="checkbox" name="member_ids[]" value="<?php echo (int)$user['id']; ?>" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-slate-300 rounded mr-2">
                                <span>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <span class="block text-xs text-slate-400"><?php echo htmlspecialchars($user['email']); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl shadow-sm transition text-sm">
                建立行程
            </button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
