<?php
require_once 'db_connect.php';
include 'header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $passwordRaw = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $passwordRaw === '') {
        $msg = "<p class='text-sm text-red-600 bg-red-50 border border-red-200 p-3 rounded-lg font-medium'>請完整填寫資料。</p>";
    } else {
        $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'user', 'active')");
            $stmt->execute([$username, $email, $password]);
            $newUserId = (int)$pdo->lastInsertId();

            $inviteStmt = $pdo->prepare("
                SELECT itinerary_id
                FROM itinerary_invitations
                WHERE invitee_email = ? AND status = 'pending'
            ");
            $inviteStmt->execute([$email]);
            $pendingInvites = $inviteStmt->fetchAll();

            if (!empty($pendingInvites)) {
                $memberStmt = $pdo->prepare("
                    INSERT INTO itinerary_members (itinerary_id, user_id, role)
                    VALUES (?, ?, 'member')
                    ON DUPLICATE KEY UPDATE role = role
                ");
                foreach ($pendingInvites as $invite) {
                    $memberStmt->execute([(int)$invite['itinerary_id'], $newUserId]);
                }

                $updateInviteStmt = $pdo->prepare("
                    UPDATE itinerary_invitations
                    SET invitee_id = ?, status = 'accepted'
                    WHERE invitee_email = ? AND status = 'pending'
                ");
                $updateInviteStmt->execute([$newUserId, $email]);
            }

            $pdo->commit();

            $extra = !empty($pendingInvites) ? '，並已自動加入受邀行程' : '';
            $msg = "<p class='text-sm text-green-600 bg-green-50 border border-green-200 p-3 rounded-lg font-medium'>註冊成功{$extra}，<a href='login.php' class='underline text-green-700 font-bold'>前往登入</a></p>";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = "<p class='text-sm text-red-600 bg-red-50 border border-red-200 p-3 rounded-lg font-medium'>註冊失敗，帳號或 Email 可能已被使用。</p>";
        }
    }
}
?>
<div class="max-w-md mx-auto my-12">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div class="text-center">
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">註冊旅行者帳號</h2>
            <p class="text-xs text-slate-400 mt-1">註冊後即可建立行程、邀請朋友、記錄開支。</p>
        </div>

        <?php echo $msg; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">帳號</label>
                <input type="text" name="username" required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Email</label>
                <input type="email" name="email" required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">密碼</label>
                <input type="password" name="password" required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl shadow-sm transition">註冊</button>
        </form>
        <div class="text-center text-xs text-slate-400">
            已經有帳號？<a href="login.php" class="text-indigo-600 font-semibold hover:underline">回到登入</a>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
