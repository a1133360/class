<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkAdmin();

if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'] === 'suspend' ? 'suspended' : 'active';
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'user'");
    $stmt->execute([$action, (int)$_GET['id']]);
    header('Location: admin_users.php');
    exit();
}

$users = $pdo->query("
    SELECT
        u.*,
        COUNT(DISTINCT im.itinerary_id) AS joined_itineraries,
        COUNT(DISTINCT i.id) AS created_itineraries
    FROM users u
    LEFT JOIN itinerary_members im ON u.id = im.user_id
    LEFT JOIN itineraries i ON u.id = i.creator_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.id DESC
")->fetchAll();

include 'header.php';
?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-900 text-white">
        <h1 class="text-base font-bold">使用者管理</h1>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">帳號</th>
                    <th class="px-6 py-3.5">Email</th>
                    <th class="px-6 py-3.5">行程</th>
                    <th class="px-6 py-3.5">狀態</th>
                    <th class="px-6 py-3.5 text-right">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="text-center py-10 text-slate-400">目前沒有一般使用者。</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-6 py-4 text-slate-400">#<?php echo (int)$user['id']; ?></td>
                        <td class="px-6 py-4 font-bold text-slate-900"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="px-6 py-4 text-xs"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="px-6 py-4 text-xs">
                            建立 <?php echo (int)$user['created_itineraries']; ?> 個<br>
                            加入 <?php echo (int)$user['joined_itineraries']; ?> 個
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 text-xs rounded-full <?php echo $user['status'] === 'active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'; ?>">
                                <?php echo $user['status'] === 'active' ? '啟用' : '停權'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right text-xs space-x-2">
                            <?php if ($user['status'] === 'active'): ?>
                                <a href="admin_users.php?action=suspend&id=<?php echo (int)$user['id']; ?>" class="text-red-600 hover:underline">停權</a>
                            <?php else: ?>
                                <a href="admin_users.php?action=reactive&id=<?php echo (int)$user['id']; ?>" class="text-emerald-600 hover:underline">恢復</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
