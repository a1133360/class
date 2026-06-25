<?php
require_once 'db_connect.php';
require_once 'session_check.php';
checkLogin();

$itineraryId = (int)($_GET['id'] ?? 0);
if (!$itineraryId) {
    die('<div style="color:red; padding:20px; font-weight:bold;">缺少行程 ID。</div>');
}

$stmt = $pdo->prepare("
    SELECT i.*, u.username AS creator_name
    FROM itineraries i
    LEFT JOIN users u ON i.creator_id = u.id
    WHERE i.id = ?
");
$stmt->execute([$itineraryId]);
$itinerary = $stmt->fetch();

if (!$itinerary) {
    die('<div style="color:red; padding:20px; font-weight:bold;">找不到行程。</div>');
}

$isMember = isItineraryMember($pdo, $itineraryId, currentUserId());
$isPublicTemplate = $itinerary['is_public'] === 'yes' && $itinerary['status'] === 'approved';
$canEdit = $isMember && $itinerary['trip_status'] !== 'finished';
$isOwner = (int)$itinerary['creator_id'] === currentUserId();

if (!$isMember && !$isPublicTemplate) {
    http_response_code(403);
    die('你沒有權限查看這個行程。');
}

function logWorkspaceEmail(PDO $pdo, string $recipient, string $subject, string $status, ?string $error = null) {
    $stmt = $pdo->prepare("
        INSERT INTO email_logs (recipient, subject, status, error_message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$recipient, $subject, $status, $error]);
}

function sendInviteEmail(PDO $pdo, string $email, string $title, int $itineraryId): bool {
    $subject = 'Travel Hub 行程邀請：' . $title;
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath . '/workspace.php?id=' . $itineraryId;
    $registerLink = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath . '/register.php';
    $message = "你被邀請加入 Travel Hub 行程「{$title}」。\n\n已有帳號請登入後開啟：{$link}\n尚未註冊請先建立帳號：{$registerLink}";
    $headers = "Content-Type: text/plain; charset=utf-8\r\n";
    $headers .= "From: Travel Hub <no-reply@chiyu.infinityfree.io>\r\n";

    $sent = @mail($email, $subject, $message, $headers);
    logWorkspaceEmail($pdo, $email, $subject, $sent ? 'sent' : 'failed', $sent ? null : 'mail() returned false on hosting server.');
    return $sent;
}

$inviteMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'invite' && $canEdit) {
    $keyword = trim($_POST['invite_keyword'] ?? '');

    if ($keyword === '') {
        $inviteMessage = '請輸入朋友的帳號或 Email。';
    } else {
        $userStmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $userStmt->execute([$keyword, $keyword]);
        $invitee = $userStmt->fetch();

        if ($invitee) {
            $memberStmt = $pdo->prepare("
                INSERT INTO itinerary_members (itinerary_id, user_id, role)
                VALUES (?, ?, 'member')
                ON DUPLICATE KEY UPDATE role = role
            ");
            $memberStmt->execute([$itineraryId, (int)$invitee['id']]);

            $logStmt = $pdo->prepare("
                INSERT INTO itinerary_invitations (itinerary_id, inviter_id, invitee_id, invitee_email, status)
                VALUES (?, ?, ?, ?, 'accepted')
            ");
            $logStmt->execute([$itineraryId, currentUserId(), (int)$invitee['id'], $invitee['email']]);
            sendInviteEmail($pdo, $invitee['email'], $itinerary['title'], $itineraryId);
            $inviteMessage = '已將 ' . $invitee['username'] . ' 加入行程，並嘗試寄出通知信。';
        } else {
            $logStmt = $pdo->prepare("
                INSERT INTO itinerary_invitations (itinerary_id, inviter_id, invitee_email, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $logStmt->execute([$itineraryId, currentUserId(), $keyword]);
            $sent = filter_var($keyword, FILTER_VALIDATE_EMAIL)
                ? sendInviteEmail($pdo, $keyword, $itinerary['title'], $itineraryId)
                : false;
            if (filter_var($keyword, FILTER_VALIDATE_EMAIL)) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $registerLink = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath . '/register.php';
                $mailSubject = rawurlencode('Travel Hub 行程邀請：' . $itinerary['title']);
                $mailBody = rawurlencode("你被邀請加入 Travel Hub 行程「{$itinerary['title']}」。\n\n請使用這個 Email 註冊，註冊後會自動加入行程：\n{$registerLink}");
                $mailto = 'mailto:' . rawurlencode($keyword) . '?subject=' . $mailSubject . '&body=' . $mailBody;
                $safeMailto = htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8');
                $inviteMessage = ($sent
                    ? '已建立待邀請紀錄並嘗試寄出 Email。'
                    : '已建立待邀請紀錄。')
                    . '對方用此 Email 註冊後會自動加入行程。'
                    . '<a href="' . $safeMailto . '" class="inline-block mt-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-3 py-1.5 rounded-lg">開啟信箱寄送邀請</a>';
            } else {
                $inviteMessage = '找不到此帳號。請輸入已註冊的帳號，或改輸入朋友的 Email 建立待邀請紀錄。';
            }
        }
    }
}

$memberStmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, im.role
    FROM itinerary_members im
    JOIN users u ON im.user_id = u.id
    WHERE im.itinerary_id = ?
    ORDER BY im.role DESC, u.username
");
$memberStmt->execute([$itineraryId]);
$members = $memberStmt->fetchAll();

include 'header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <?php if (isset($_GET['finished'])): ?>
            <div class="text-sm text-green-700 bg-green-50 border border-green-200 p-3 rounded-xl mb-4">行程已結束。你仍可查看清單與報表。</div>
        <?php endif; ?>
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900"><?php echo htmlspecialchars($itinerary['title']); ?></h1>
                <p class="text-sm text-slate-500 mt-1">
                    <?php if (!empty($itinerary['destination'])): ?>
                        <?php echo htmlspecialchars($itinerary['destination']); ?> ·
                    <?php endif; ?>
                    建立者：<?php echo htmlspecialchars($itinerary['creator_name'] ?? '未知'); ?>
                    · <?php echo $itinerary['trip_status'] === 'finished' ? '已結束' : '進行中'; ?>
                    · <?php echo $isPublicTemplate ? '公開範本' : '私人行程'; ?>
                    <?php if (!empty($itinerary['start_date'])): ?>
                        · <?php echo htmlspecialchars($itinerary['start_date']); ?> 出發
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="expense_list.php?itinerary_id=<?php echo $itineraryId; ?>" class="text-sm font-bold px-4 py-2 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700">新增代墊</a>
                <a href="budget_calculator.php?itinerary_id=<?php echo $itineraryId; ?>" class="text-sm font-bold px-4 py-2 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100">預算計算機</a>
                <a href="settlement_report.php?itinerary_id=<?php echo $itineraryId; ?>" class="text-sm font-bold px-4 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100">清單與報表</a>
                <?php if ($isOwner && $itinerary['trip_status'] !== 'finished'): ?>
                    <form action="api_finish_itinerary.php" method="POST" onsubmit="return confirm('確定要結束這個行程嗎？結束後不能再新增景點、共筆或代墊。');">
                        <input type="hidden" name="itinerary_id" value="<?php echo $itineraryId; ?>">
                        <button type="submit" class="text-sm font-bold px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-800">結束行程</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <aside class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4">成員與邀請</h2>
                <div class="space-y-2 mb-4">
                    <?php foreach ($members as $member): ?>
                        <div class="flex items-center justify-between text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
                            <span>
                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($member['username']); ?></span>
                                <span class="block text-xs text-slate-400"><?php echo htmlspecialchars($member['email']); ?></span>
                            </span>
                            <span class="text-xs text-slate-500"><?php echo $member['role'] === 'owner' ? '建立者' : '成員'; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($inviteMessage): ?>
                    <div class="text-sm text-indigo-700 bg-indigo-50 border border-indigo-200 p-3 rounded-xl mb-4"><?php echo $inviteMessage; ?></div>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="invite">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">朋友帳號或 Email</label>
                        <input type="text" name="invite_keyword" placeholder="friend@example.com 或 username"
                               class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                        <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-2 px-4 rounded-xl transition text-sm">邀請朋友</button>
                    </form>
                    <p class="text-xs text-slate-400 mt-3">已註冊使用者會直接加入；未註冊 Email 會建立待邀請紀錄，對方註冊後自動加入。</p>
                <?php else: ?>
                    <p class="text-sm text-slate-500">此行程無法再邀請或編輯。</p>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4">搜尋公開範本</h2>
                <div class="space-y-3">
                    <input type="text" id="search_keyword" placeholder="搜尋城市、主題或行程名稱"
                           class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                    <button onclick="searchTemplates()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-xl transition text-sm">搜尋範本</button>
                </div>
                <div id="search_results" class="mt-4 max-h-56 overflow-y-auto space-y-2"></div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4">新增景點</h2>
                <?php if (!$canEdit): ?>
                    <p class="text-sm text-slate-500">此行程已結束或你不是成員，不能新增景點。</p>
                <?php else: ?>
                    <form id="add_spot_form" onsubmit="addSpot(event)" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">景點名稱</label>
                            <input type="text" id="spot_name" required placeholder="輸入景點名稱，可搜尋 OpenStreetMap"
                                   class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                            <input type="hidden" id="place_id">
                            <input type="hidden" id="address">
                        </div>
                        <button type="button" onclick="searchPlaces()" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-medium py-2 px-4 rounded-xl transition text-sm">搜尋景點座標</button>
                        <div id="place_results" class="space-y-2 text-xs"></div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Lat</label>
                                <input type="text" id="lat" placeholder="23.000"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Lng</label>
                                <input type="text" id="lng" placeholder="120.000"
                                       class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">第幾天</label>
                            <input type="number" id="day_number" value="1" min="1"
                                   class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                        </div>

                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-xl transition text-sm">加入景點</button>
                    </form>
                <?php endif; ?>
            </div>
        </aside>

        <section class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
                <div class="text-sm text-slate-600 bg-slate-50 border border-slate-200 p-3 rounded-xl mb-4">
                    目前使用 OpenStreetMap + Leaflet 免費地圖。地點搜尋由 Nominatim 提供，請避免連續大量查詢。
                </div>
                <div id="map" class="w-full h-[420px] rounded-xl bg-slate-100 border border-slate-200 overflow-hidden"></div>
                <div id="dayTabs" class="flex flex-wrap gap-2 mt-3"></div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h2 class="text-xl font-black text-slate-800">共筆筆記</h2>
                    <span id="noteStatus" class="text-xs text-slate-400">尚未載入</span>
                </div>
                <textarea id="noteContent" <?php echo $canEdit ? '' : 'readonly'; ?>
                          class="w-full min-h-[180px] px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm"
                          placeholder="一起記錄行程想法、待辦、訂房資訊、集合時間..."></textarea>
                <?php if ($canEdit): ?>
                    <div class="flex justify-end mt-3">
                        <button onclick="saveNote()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-4 py-2 rounded-xl">儲存共筆</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h2 class="text-xl font-black text-slate-800">景點清單</h2>
                    <span class="text-xs font-semibold bg-slate-100 text-slate-600 px-3 py-1 rounded-full">ID: <?php echo $itineraryId; ?></span>
                </div>
                <div id="spot_list" class="space-y-4">
                    <p class="text-slate-400 text-center py-12">讀取景點中...</p>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
const itineraryId = <?php echo $itineraryId; ?>;
let map;
let markerLayer;
let routeLayer;
let spotCache = [];
let noteDirty = false;

function initLeafletMap() {
    map = L.map('map').setView([23.6978, 120.9605], 7);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    markerLayer = L.layerGroup().addTo(map);
    routeLayer = L.layerGroup().addTo(map);
    loadSpots();
}

function loadSpots() {
    fetch(`api_get_spots.php?itinerary_id=${itineraryId}`)
        .then(res => res.json())
        .then(data => {
            spotCache = Array.isArray(data) ? data : [];
            renderSpotList(spotCache);
            renderDayTabs(spotCache);
            renderMarkers(spotCache);
        })
        .catch(() => {
            document.getElementById('spot_list').innerHTML = '<p class="text-sm text-red-500">景點讀取失敗。</p>';
        });
}

function renderSpotList(data) {
    const spotList = document.getElementById('spot_list');
    if (!Array.isArray(data) || !data.length) {
        spotList.innerHTML = '<div class="text-center py-12 border-2 border-dashed border-slate-200 rounded-xl text-slate-400 text-sm">尚未新增景點。</div>';
        return;
    }
    spotList.innerHTML = data.map(spot => `
        <div class="bg-slate-50 hover:bg-indigo-50/40 p-4 rounded-xl border border-slate-100 transition">
            <span class="text-[10px] font-bold bg-indigo-600 text-white px-2 py-0.5 rounded mr-2 uppercase tracking-wider">DAY ${spot.day_number}</span>
            <h3 class="inline text-base font-bold text-slate-800">${escapeHtml(spot.spot_name)}</h3>
            <p class="text-xs text-slate-500 mt-1">${escapeHtml(spot.address || '')}</p>
            <p class="text-xs text-slate-400 mt-1">Lat ${spot.lat || '-'} · Lng ${spot.lng || '-'}</p>
        </div>
    `).join('');
}

function renderDayTabs(data) {
    const tabs = document.getElementById('dayTabs');
    const days = [...new Set(data.map(spot => Number(spot.day_number || 1)))].sort((a, b) => a - b);
    if (!days.length) {
        tabs.innerHTML = '';
        return;
    }
    tabs.innerHTML = days.map(day => `
        <button type="button" onclick="renderRouteForDay(${day})" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700">
            顯示第 ${day} 天路線
        </button>
    `).join('') + `
        <button type="button" onclick="renderMarkers(spotCache)" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200">
            顯示全部景點
        </button>
    `;
}

function renderMarkers(data) {
    if (!map || !markerLayer || !routeLayer) return;
    markerLayer.clearLayers();
    routeLayer.clearLayers();
    const bounds = [];
    data.forEach(spot => {
        if (!spot.lat || !spot.lng) return;
        const latLng = [Number(spot.lat), Number(spot.lng)];
        L.marker(latLng)
            .bindPopup(`<strong>${escapeHtml(spot.spot_name)}</strong><br>${escapeHtml(spot.address || '')}`)
            .addTo(markerLayer);
        bounds.push(latLng);
    });
    if (bounds.length) map.fitBounds(bounds, { padding: [28, 28] });
}

function renderRouteForDay(day) {
    if (!map || !routeLayer) return;
    markerLayer.clearLayers();
    routeLayer.clearLayers();
    const spots = spotCache.filter(spot => Number(spot.day_number) === day && spot.lat && spot.lng);
    if (spots.length < 2) {
        alert('同一天至少需要 2 個有座標的景點才能顯示路線。');
        renderMarkers(spotCache);
        return;
    }
    const points = spots.map(spot => [Number(spot.lat), Number(spot.lng)]);
    L.polyline(points, { color: '#4f46e5', weight: 5, opacity: 0.85 }).addTo(routeLayer);
    spots.forEach((spot, index) => {
        L.marker([Number(spot.lat), Number(spot.lng)])
            .bindPopup(`<strong>${index + 1}. ${escapeHtml(spot.spot_name)}</strong><br>${escapeHtml(spot.address || '')}`)
            .addTo(markerLayer);
    });
    map.fitBounds(points, { padding: [28, 28] });
}

function searchPlaces() {
    const keyword = document.getElementById('spot_name').value.trim();
    const results = document.getElementById('place_results');
    if (!keyword) {
        results.innerHTML = '<div class="text-red-500">請先輸入景點名稱。</div>';
        return;
    }
    results.innerHTML = '<div class="text-slate-400">搜尋中...</div>';
    const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&accept-language=zh-TW&q=${encodeURIComponent(keyword)}`;
    fetch(url)
        .then(res => res.json())
        .then(items => {
            if (!items.length) {
                results.innerHTML = '<div class="text-slate-500">找不到地點，請換個關鍵字或手動輸入座標。</div>';
                return;
            }
            results.innerHTML = items.map((item, index) => `
                <button type="button" onclick="selectPlace(${index})" class="block w-full text-left bg-slate-50 hover:bg-sky-50 border border-slate-200 rounded-lg p-2">
                    <span class="font-bold text-slate-700">${escapeHtml(item.name || keyword)}</span>
                    <span class="block text-slate-500 mt-1">${escapeHtml(item.display_name || '')}</span>
                </button>
            `).join('');
            window.nominatimResults = items;
        })
        .catch(() => {
            results.innerHTML = '<div class="text-red-500">搜尋失敗，請稍後再試。</div>';
        });
}

function selectPlace(index) {
    const item = (window.nominatimResults || [])[index];
    if (!item) return;
    document.getElementById('spot_name').value = item.name || document.getElementById('spot_name').value;
    document.getElementById('address').value = item.display_name || '';
    document.getElementById('place_id').value = item.osm_type && item.osm_id ? `${item.osm_type}-${item.osm_id}` : '';
    document.getElementById('lat').value = Number(item.lat).toFixed(8);
    document.getElementById('lng').value = Number(item.lon).toFixed(8);
    document.getElementById('place_results').innerHTML = '<div class="text-green-700 bg-green-50 border border-green-200 rounded-lg p-2">已帶入座標，可以按「加入景點」。</div>';
    if (map) map.setView([Number(item.lat), Number(item.lon)], 15);
}

function addSpot(e) {
    e.preventDefault();
    const formData = new FormData();
    formData.append('itinerary_id', itineraryId);
    formData.append('spot_name', document.getElementById('spot_name').value);
    formData.append('place_id', document.getElementById('place_id').value);
    formData.append('address', document.getElementById('address').value);
    formData.append('lat', document.getElementById('lat').value);
    formData.append('lng', document.getElementById('lng').value);
    formData.append('day_number', document.getElementById('day_number').value);

    fetch('api_add_spot.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status !== 'success') {
                alert(res.message || '新增景點失敗');
                return;
            }
            document.getElementById('add_spot_form').reset();
            document.getElementById('place_id').value = '';
            document.getElementById('address').value = '';
            document.getElementById('day_number').value = '1';
            document.getElementById('place_results').innerHTML = '';
            loadSpots();
        })
        .catch(() => alert('新增景點失敗，請稍後再試。'));
}

function loadNote() {
    if (noteDirty) return;
    fetch(`api_get_note.php?itinerary_id=${itineraryId}`)
        .then(res => res.json())
        .then(res => {
            if (res.status !== 'success') return;
            document.getElementById('noteContent').value = res.note.content || '';
            document.getElementById('noteStatus').textContent = res.note.updated_at
                ? `最後更新：${res.note.updated_at} ${res.note.updated_by_name || ''}`
                : '尚無共筆內容';
        });
}

function saveNote() {
    const formData = new FormData();
    formData.append('itinerary_id', itineraryId);
    formData.append('content', document.getElementById('noteContent').value);
    fetch('api_save_note.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status !== 'success') {
                alert(res.message || '共筆儲存失敗');
                return;
            }
            noteDirty = false;
            document.getElementById('noteStatus').textContent = '已儲存';
            loadNote();
        });
}

function searchTemplates() {
    const kw = document.getElementById('search_keyword').value;
    const resultDiv = document.getElementById('search_results');
    resultDiv.innerHTML = '<p class="text-xs text-slate-400">搜尋中...</p>';
    fetch(`api_search_templates.php?keyword=${encodeURIComponent(kw)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.length) {
                resultDiv.innerHTML = '<p class="text-xs text-slate-500 bg-slate-50 p-2 rounded-lg">找不到公開範本。</p>';
                return;
            }
            resultDiv.innerHTML = data.map(item => `
                <a href="workspace.php?id=${item.id}" class="block p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs hover:border-indigo-300 transition">
                    <span class="font-bold text-slate-700">${escapeHtml(item.title)}</span>
                    <span class="block text-slate-400 mt-1">景點 ${item.spot_count} 個</span>
                </a>
            `).join('');
        });
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));
}

document.getElementById('noteContent').addEventListener('input', () => {
    noteDirty = true;
    document.getElementById('noteStatus').textContent = '尚未儲存';
});
initLeafletMap();
loadNote();
setInterval(loadNote, 5000);
</script>

<?php include 'footer.php'; ?>
