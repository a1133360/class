<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkAdmin();

$templates = $pdo->query("
    SELECT i.*, u.username AS creator_name, COUNT(s.id) AS spot_count
    FROM itineraries i
    LEFT JOIN users u ON i.creator_id = u.id
    LEFT JOIN spots s ON i.id = s.itinerary_id
    WHERE i.is_public = 'yes'
    GROUP BY i.id, u.username
    ORDER BY FIELD(i.status, 'pending', 'approved', 'rejected'), i.id DESC
")->fetchAll();

include 'header.php';
?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-900 text-white">
        <h1 class="text-base font-bold">公開範本審核</h1>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">範本</th>
                    <th class="px-6 py-3.5">建立者</th>
                    <th class="px-6 py-3.5">景點</th>
                    <th class="px-6 py-3.5">狀態</th>
                    <th class="px-6 py-3.5 text-right">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                <?php if (empty($templates)): ?>
                    <tr><td colspan="6" class="text-center py-10 text-slate-400">目前沒有公開範本。</td></tr>
                <?php endif; ?>
                <?php foreach ($templates as $template): ?>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-6 py-4 text-slate-400">#<?php echo (int)$template['id']; ?></td>
                        <td class="px-6 py-4 font-bold text-slate-900">
                            <a href="workspace.php?id=<?php echo (int)$template['id']; ?>" class="hover:text-indigo-600">
                                <?php echo htmlspecialchars($template['title']); ?>
                            </a>
                        </td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($template['creator_name'] ?? '未知'); ?></td>
                        <td class="px-6 py-4"><?php echo (int)$template['spot_count']; ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 text-xs rounded-full <?php echo $template['status'] === 'approved' ? 'bg-green-50 text-green-700' : ($template['status'] === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'); ?>">
                                <?php echo htmlspecialchars($template['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form action="api_approve_template.php" method="POST" class="inline-flex space-x-2">
                                <input type="hidden" name="itinerary_id" value="<?php echo (int)$template['id']; ?>">
                                <button type="submit" name="status" value="approved" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-2.5 py-1 rounded-lg transition">通過</button>
                                <button type="submit" name="status" value="rejected" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold px-2.5 py-1 rounded-lg transition">退回</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
