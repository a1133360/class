<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();
include 'header.php';

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$email, $_SESSION['user_id']]);
    $success = true;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
        <h2 class="text-xl font-bold text-slate-800">個人資料</h2>

        <?php if ($success): ?>
            <p class="text-sm text-green-600 bg-green-50 border border-green-200 p-3 rounded-xl font-medium">資料已更新。</p>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">帳號</label>
                <input type="text" disabled value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-400 cursor-not-allowed">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">角色</label>
                <input type="text" disabled value="<?php echo htmlspecialchars($user['role'] === 'admin' ? '平台管理員' : '旅行者'); ?>" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-400 cursor-not-allowed">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-xl transition shadow-sm text-sm">儲存資料</button>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
