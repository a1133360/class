<?php
require_once 'db_connect.php';
include 'header.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'suspended') {
            $error = '此帳號已被停權，請聯絡管理員。';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['status'] = $user['status'];
            header('Location: dashboard.php');
            exit();
        }
    } else {
        $error = '帳號或密碼錯誤。';
    }
}
?>
<div class="max-w-md mx-auto my-16">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div class="text-center">
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">登入 Travel Hub</h2>
            <p class="text-xs text-slate-400 mt-1">旅行者可建立共筆與分帳；管理員可進入後台。</p>
        </div>

        <?php if ($error): ?>
            <p class="text-sm text-red-600 bg-red-50 border border-red-200 p-3 rounded-lg font-medium"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">帳號</label>
                <input type="text" name="username" required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">密碼</label>
                <input type="password" name="password" required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl shadow-sm transition">登入</button>
        </form>
        <div class="text-center text-xs text-slate-400">
            還沒有帳號？<a href="register.php" class="text-indigo-600 font-semibold hover:underline">前往註冊</a>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
