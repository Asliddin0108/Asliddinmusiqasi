<?php
ob_start();
http_response_code(200);

// Asosiy sozlamalar
define('API_KEY', getenv('8253736025:AAHmMPac7DmA_fi01urRtI0wwAfd7SAYArE'));
define('ADMIN_ID', getenv('8238730404'));


// Fayl yo'llari
$DIR_STAT = __DIR__ . '/stat';
$DIR_ROYXAT = __DIR__ . '/royxat';
$DIR_PENDING = __DIR__ . '/pending';
$DIR_STATISTIKA = __DIR__ . '/statistika';
$DIR_QADAM = __DIR__ . '/qadam';

// Kataloglarni yaratish
if (!is_dir($DIR_STAT)) mkdir($DIR_STAT, 0777, true);
if (!is_dir($DIR_ROYXAT)) mkdir($DIR_ROYXAT, 0777, true);
if (!is_dir($DIR_PENDING)) mkdir($DIR_PENDING, 0777, true);
if (!is_dir($DIR_STATISTIKA)) mkdir($DIR_STATISTIKA, 0777, true);
if (!is_dir($DIR_QADAM)) mkdir($DIR_QADAM, 0777, true);

// Fayl yo'llari
$STEP_FILE = "$DIR_STAT/step.txt";
$KANAL_FILE = "$DIR_STAT/kanal.txt";
$ADMIN_FILE = "$DIR_STAT/adminlar.txt";
$ROYXAT_FILE = "$DIR_ROYXAT/royxat.txt";
$PENDING_FILE = "$DIR_PENDING/pending.txt";

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res, true);
    }
}

$update = json_decode(file_get_contents('php://input'), true);
$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;
$chat_join_request = $update['chat_join_request'] ?? null;

// Universal kontekst
$chat_id = $message['chat']['id'] ?? ($callback['message']['chat']['id'] ?? ($chat_join_request['chat']['id'] ?? null));
$from_id = $message['from']['id'] ?? ($callback['from']['id'] ?? ($chat_join_request['from']['id'] ?? null));
$first_name = $message['from']['first_name'] ?? ($callback['from']['first_name'] ?? ($chat_join_request['from']['first_name'] ?? ''));
$username = $message['from']['username'] ?? ($callback['from']['username'] ?? ($chat_join_request['from']['username'] ?? ''));
$message_id = $message['message_id'] ?? null;
$text = $message['text'] ?? null;
$data = $callback['data'] ?? null;
$cb_message_id = $callback['message']['message_id'] ?? null;
$type = $message['chat']['type'] ?? 'private';

// Eski o'zgaruvchilar (compatibility)
$cid = $chat_id;
$mid = $message_id;

// Fayllardan ma'lumotlar
$kinobaza = file_exists($ROYXAT_FILE) ? trim(file_get_contents($ROYXAT_FILE)) : '';
$kanal_raw = file_exists($KANAL_FILE) ? trim(file_get_contents($KANAL_FILE)) : '';
$pending_raw = file_exists($PENDING_FILE) ? trim(file_get_contents($PENDING_FILE)) : '';
$adminlar_raw = file_exists($ADMIN_FILE) ? trim(file_get_contents($ADMIN_FILE)) : '';
$step_now = file_exists($STEP_FILE) ? trim(file_get_contents($STEP_FILE)) : '';

// Adminlar ro'yxati
$adminlar = array_filter(array_map('trim', explode("\n", $adminlar_raw)));
if (!in_array(ADMIN_ID, $adminlar)) {
    $adminlar[] = ADMIN_ID;
    file_put_contents($ADMIN_FILE, implode("\n", $adminlar));
}

// Bot username
$botname = bot('getme', ['bot'])['result']['username'] ?? '';

$date = date("d.m.Y");
$soat = date("H:i");

// Klaviaturalar
$bosh = json_encode([
    'inline_keyboard' => [
        [['text' => "➕ Guruhga qo'shish", 'url' => "https://t.me/$botname?startgroup=new"]]
    ]
]);

$ortga = json_encode([
    'inline_keyboard' => [
        [['text' => "❌", 'callback_data' => "del"]],
        [['text' => "➕ Guruhga qo'shish", 'url' => "https://t.me/$botname?startgroup=new"]]
    ]
]);

// Admin panel klaviaturalari
$adminPanel = json_encode([
    'keyboard' => [
        ['📢 Kanallar boshqaruvi', '📊 Statistika'],
        ['👥 Adminlarni boshqarish', '🔙 Orqaga']
    ],
    'resize_keyboard' => true
]);

$channelPanel = json_encode([
    'keyboard' => [
        ['➕ Kanal qo\'shish', '➖ Kanal o\'chirish'],
        ['📋 Kanallar ro\'yxati', '🔙 Orqaga']
    ],
    'resize_keyboard' => true
]);

$adminManagePanel = json_encode([
    'keyboard' => [
        ['➕ Admin qo\'shish', '➖ Admin o\'chirish'],
        ['📋 Adminlar ro\'yxati', '🔙 Orqaga']
    ],
    'resize_keyboard' => true
]);

$backButton = json_encode([
    'keyboard' => [['🔙 Orqaga']],
    'resize_keyboard' => true
]);

// ===== FUNKSIYALAR =====

// Majburiy a'zolikni tekshirish
function checkMembership($user_id, $kanal_raw, $pending_raw) {
    $channels = array_values(array_filter(array_map('trim', explode("\n", $kanal_raw))));
    $pendings = array_values(array_filter(array_map('trim', explode("\n", $pending_raw))));
    $not_member = [];

    foreach ($channels as $channel) {
        if ($channel === '') continue;

        // 1) @username (oddiy kanal)
        if (preg_match('/^@[\w_]+$/', $channel)) {
            $resp = bot('getChatMember', [
                'chat_id' => $channel,
                'user_id' => $user_id,
            ]);
            if (!($resp['ok'] ?? false)) { 
                $not_member[] = $channel; 
                continue; 
            }
            $status = $resp['result']['status'] ?? 'left';
            if (!in_array($status, ['member','administrator','creator','restricted'])) {
                $not_member[] = $channel;
            }
        }
        // 2) -100id++https://t.me/zayafka_linki (zayafka kanal)
        elseif (preg_match('/^-100\d+\+\+https:\/\/t\.me\/[A-Za-z0-9_+]+$/', $channel)) {
            [$chatId, $invite] = explode('++', $channel, 2);
            $resp = bot('getChatMember', [
                'chat_id' => $chatId,
                'user_id' => $user_id,
            ]);
            if (!($resp['ok'] ?? false)) { 
                $not_member[] = $channel; 
                continue; 
            }
            $status = $resp['result']['status'] ?? 'left';
            $is_pending = in_array($chatId . '++' . $user_id, $pendings, true);
            if (!in_array($status, ['member','administrator','creator','restricted']) && !$is_pending) {
                $not_member[] = $channel;
            }
        }
    }
    return $not_member;
}

// Inline klaviatura qurish
function buildJoinKeyboardAndText($not_member) {
    $inline = [];
    $i = 1;
    $lines = [];
    
    foreach ($not_member as $channel) {
        if (preg_match('/^@[\w_]+$/', $channel)) {
            $lines[] = $i . '. ' . $channel;
            $inline[] = [[
                'text' => "👉 $i-kanalga a'zo bo'lish",
                'url'  => 'https://t.me/' . substr($channel, 1)
            ]];
        } else {
            [$chatId, $link] = explode('++', $channel, 2);
            $lines[] = $i . '. A\'zo bo\'lish';
            $inline[] = [[
                'text' => "👉 $i-kanalga a'zo bo'lish",
                'url'  => $link
            ]];
        }
        $i++;
    }
    
    $lines[] = '';
    $lines[] = 'A\'zo bo\'lgach, quyidagi tugmani bosing.';
    $inline[] = [['text' => '✅ Tekshirish', 'callback_data' => 'check_membership']];

    $text = "❌ Botdan foydalanish uchun quyidagi kanallarga a'zo bo'ling:\n\n" . implode("\n", $lines);
    return [
        'text' => $text,
        'reply_markup' => json_encode(['inline_keyboard' => $inline], JSON_UNESCAPED_UNICODE)
    ];
}

// Adminga zayafka haqida xabar yuborish
function sendJoinRequestToAdmin($chat_join_request) {
    global $adminlar;
    
    $user_id = $chat_join_request['from']['id'];
    $first_name = $chat_join_request['from']['first_name'];
    $username = $chat_join_request['from']['username'] ?? 'Yo\'q';
    $chat_id = $chat_join_request['chat']['id'];
    $chat_title = $chat_join_request['chat']['title'] ?? 'Noma\'lum';
    
    $message = "🆕 **Yangi Zayafka**\n\n";
    $message .= "👤 **Foydalanuvchi:** $first_name\n";
    $message .= "🆔 **ID:** `$user_id`\n";
    $message .= "📛 **Username:** @$username\n\n";
    $message .= "📢 **Kanal:** $chat_title\n";
    $message .= "🆔 **Kanal ID:** `$chat_id`\n\n";
    $message .= "⏰ **Vaqt:** " . date('H:i d.m.Y');
    
    $keyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ Qabul qilish', 'callback_data' => "accept_$chat_id++$user_id"],
                ['text' => '❌ Rad etish', 'callback_data' => "decline_$chat_id++$user_id"]
            ]
        ]
    ]);
    
    foreach ($adminlar as $admin_id) {
        bot('sendMessage', [
            'chat_id' => $admin_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard
        ]);
    }
}

// ===== ASOSIY KOD =====

// Chat Join Request ni qayta ishlash
if ($chat_join_request) {
    $req_chat_id = $chat_join_request['chat']['id'];
    $req_user_id = $chat_join_request['from']['id'];
    $channel_key = $req_chat_id . '++' . $req_user_id;

    // Kanal majburiy kanallar ro'yxatida ekanligini tekshirish
    $channels = array_values(array_filter(array_map('trim', explode("\n", $kanal_raw))));
    foreach ($channels as $channel) {
        if (preg_match('/^-100\d+\+\+https:\/\/t\.me\/[A-Za-z0-9_+]+$/', $channel)) {
            [$chatId, $invite] = explode('++', $channel, 2);
            if ($chatId == $req_chat_id) {
                // Pending ga qo'shish
                $pendings = array_values(array_filter(array_map('trim', explode("\n", $pending_raw))));
                if (!in_array($channel_key, $pendings, true)) {
                    $pending_new = trim($pending_raw . "\n" . $channel_key);
                    file_put_contents($PENDING_FILE, $pending_new);
                    
                    // Adminlarga xabar yuborish
                    sendJoinRequestToAdmin($chat_join_request);
                }
                break;
            }
        }
    }
    exit;
}

// Statistika: foydalanuvchini bazaga yozish
if ($from_id && ($message || $callback)) {
    $lichka_file = "$DIR_STATISTIKA/lichka.db";
    $lichka = file_exists($lichka_file) ? file_get_contents($lichka_file) : '';
    if (strpos($lichka, (string)$from_id) === false) {
        file_put_contents($lichka_file, trim($lichka . "\n$from_id"));
    }
}

// ===== START KOMANDASI =====
if (($text == "/start" || $text == "/start@$botname") && $type == "private") {
    
    // Admin panel
    if (in_array($from_id, $adminlar)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "👋 Salom admin! Admin panelga xush kelibsiz.",
            'parse_mode' => 'HTML',
            'reply_markup' => $adminPanel
        ]);
        exit();
    }
    
    // Majburiy a'zolik tekshiruvi
    if ($kanal_raw !== '') {
        $not_member = checkMembership($from_id, $kanal_raw, $pending_raw);
        if (!empty($not_member)) {
            $b = buildJoinKeyboardAndText($not_member);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $b['text'],
                'parse_mode' => 'HTML',
                'reply_markup' => $b['reply_markup']
            ]);
            exit();
        }
    }
    
    // Asosiy menyu
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "<b>🔥 Assalomu alaykum. @$botname ga Xush kelibsiz. Bot orqali quyidagilarni yuklab olishingiz mumkin:\n\n• Instagram - stories, post va IGTV + audio bilan\n• TikTok - suv belgisiz video;\n• YouTube - video;\n\nShazam funksiya:\n• Qo'shiq nomi yoki ijrochi ismi\n• Qo'shiq matni\n\n😎 Bot guruhlarda ham ishlay oladi!</b>",
        'parse_mode' => 'html',
        'reply_markup' => $bosh,
    ]);
    exit();
}

// ===== ADMIN PANEL =====

// Admin panel menyusi
if (in_array($from_id, $adminlar)) {
    
    // Asosiy admin panel
    if ($text === '🔙 Orqaga') {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "👋 Admin panelga xush kelibsiz!",
            'parse_mode' => 'HTML',
            'reply_markup' => $adminPanel
        ]);
        exit();
    }
    
    // 📢 Kanallar boshqaruvi
    if ($text === '📢 Kanallar boshqaruvi') {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📢 Ushbu bo'limda majburiy kanallarni boshqarishingiz mumkin.",
            'reply_markup' => $channelPanel,
            'parse_mode' => 'HTML'
        ]);
        exit();
    }
    
    // ➕ Kanal qo'shish
    if ($text === '➕ Kanal qo\'shish') {
        file_put_contents($STEP_FILE, 'addchannel');
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "➕ Majburiy kanal qo'shish uchun:\n1) @username (masalan, @ChannelName)\n2) Yoki zayafka: <code>-100KANAL_ID++https://t.me/zayafka_linki</code>",
            'parse_mode' => 'HTML',
            'reply_markup' => $backButton
        ]);
        exit();
    }
    
    // ➖ Kanal o'chirish
    if ($text === '➖ Kanal o\'chirish') {
        file_put_contents($STEP_FILE, 'removechannel');
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "➖ O'chirish uchun to'liq qiymatni yuboring:\n@username YOKI <code>-100id++https://t.me/zayafka</code>",
            'parse_mode' => 'HTML',
            'reply_markup' => $backButton
        ]);
        exit();
    }
    
    // 📋 Kanallar ro'yxati
    if ($text === '📋 Kanallar ro\'yxati') {
        if ($kanal_raw !== '') {
            $channels = array_filter(array_map('trim', explode("\n", $kanal_raw)));
            $list = "📋 Majburiy kanallar/zayafkalar:\n\n";
            $i = 1;
            foreach ($channels as $channel) {
                $list .= "$i. " . htmlspecialchars($channel) . "\n";
                $i++;
            }
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $list,
                'parse_mode' => 'HTML'
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '❌ Hozircha hech narsa qo\'shilmagan.',
                'parse_mode' => 'HTML'
            ]);
        }
        exit();
    }
    
    // 📊 Statistika
    if ($text === '📊 Statistika') {
        $userlar_file = "$DIR_STATISTIKA/lichka.db";
        $userlar = file_exists($userlar_file) ? count(array_filter(explode("\n", file_get_contents($userlar_file)))) : 0;
        
        $stat_text = "📊 **Bot Statistikasi**\n\n";
        $stat_text .= "👥 **Foydalanuvchilar:** $userlar\n";
        $stat_text .= "📢 **Kanallar:** " . count(array_filter(explode("\n", $kanal_raw))) . "\n";
        $stat_text .= "⏳ **Pending zayafkalar:** " . count(array_filter(explode("\n", $pending_raw))) . "\n";
        $stat_text .= "👑 **Adminlar:** " . count($adminlar) . "\n\n";
        $stat_text .= "📅 **Sana:** $date\n";
        $stat_text .= "⏰ **Soat:** $soat";
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $stat_text,
            'parse_mode' => 'Markdown'
        ]);
        exit();
    }
    
    // 👥 Adminlarni boshqarish
    if ($text === '👥 Adminlarni boshqarish') {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "👥 Adminlarni boshqarish bo'limi",
            'reply_markup' => $adminManagePanel,
            'parse_mode' => 'HTML'
        ]);
        exit();
    }
    
    // ➕ Admin qo'shish
    if ($text === '➕ Admin qo\'shish') {
        file_put_contents($STEP_FILE, 'addadmin');
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "➕ Yangi admin qo'shish uchun uning ID sini yuboring:",
            'parse_mode' => 'HTML',
            'reply_markup' => $backButton
        ]);
        exit();
    }
    
    // ➖ Admin o'chirish
    if ($text === '➖ Admin o\'chirish') {
        file_put_contents($STEP_FILE, 'removeadmin');
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "➖ Admin o'chirish uchun uning ID sini yuboring:",
            'parse_mode' => 'HTML',
            'reply_markup' => $backButton
        ]);
        exit();
    }
    
    // 📋 Adminlar ro'yxati
    if ($text === '📋 Adminlar ro\'yxati') {
        $list = "👑 **Adminlar ro'yxati:**\n\n";
        $i = 1;
        foreach ($adminlar as $admin) {
            $list .= "$i. `$admin`\n";
            $i++;
        }
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $list,
            'parse_mode' => 'Markdown'
        ]);
        exit();
    }
    
    // Step bajarish (kanal qo'shish/o'chirish)
    if ($step_now === 'addchannel' && $text) {
        if (preg_match('/^@[\w_]+$/', $text) || preg_match('/^-100\d+\+\+https:\/\/t\.me\/[A-Za-z0-9_+]+$/', $text)) {
            $exists = array_map('trim', explode("\n", $kanal_raw));
            if (!in_array($text, $exists, true)) {
                $kanal_new = trim($kanal_raw . "\n" . $text);
                $kanal_new = trim($kanal_new);
                file_put_contents($KANAL_FILE, $kanal_new);
                bot('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "✅ <code>$text</code> qo'shildi!",
                    'parse_mode' => 'HTML',
                    'reply_markup' => $channelPanel
                ]);
            } else {
                bot('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => '❌ Bu kanal yoki zayafka allaqachon mavjud!',
                    'parse_mode' => 'HTML',
                    'reply_markup' => $channelPanel
                ]);
            }
            file_put_contents($STEP_FILE, '');
        } else {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ Noto'g'ri format. Qayta yuboring:\n@username yoki <code>-100id++https://t.me/zayafka</code>",
                'parse_mode' => 'HTML'
            ]);
        }
        exit();
    }
    
    if ($step_now === 'removechannel' && $text) {
        if (strpos($kanal_raw, $text) !== false) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $kanal_raw)), function($v) use ($text){return $v !== trim($text);}));
            file_put_contents($KANAL_FILE, implode("\n", $lines));
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ <code>$text</code> o'chirildi!",
                'parse_mode' => 'HTML',
                'reply_markup' => $channelPanel
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '❌ Bu qiymat ro\'yxatda topilmadi.',
                'parse_mode' => 'HTML',
                'reply_markup' => $channelPanel
            ]);
        }
        file_put_contents($STEP_FILE, '');
        exit();
    }
    
    // Step bajarish (admin qo'shish/o'chirish)
    if ($step_now === 'addadmin' && $text && is_numeric($text)) {
        if (!in_array($text, $adminlar)) {
            $adminlar[] = $text;
            file_put_contents($ADMIN_FILE, implode("\n", $adminlar));
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ Admin `$text` qo'shildi!",
                'parse_mode' => 'Markdown',
                'reply_markup' => $adminManagePanel
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '❌ Bu admin allaqachon mavjud!',
                'parse_mode' => 'HTML',
                'reply_markup' => $adminManagePanel
            ]);
        }
        file_put_contents($STEP_FILE, '');
        exit();
    }
    
    if ($step_now === 'removeadmin' && $text && is_numeric($text)) {
        if ($text != ADMIN_ID && in_array($text, $adminlar)) {
            $adminlar = array_values(array_filter($adminlar, function($v) use ($text){return $v != $text;}));
            file_put_contents($ADMIN_FILE, implode("\n", $adminlar));
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ Admin `$text` o'chirildi!",
                'parse_mode' => 'Markdown',
                'reply_markup' => $adminManagePanel
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $text == ADMIN_ID ? '❌ Asosiy adminni o\'chirib bo\'lmaydi!' : '❌ Bu admin topilmadi!',
                'parse_mode' => 'HTML',
                'reply_markup' => $adminManagePanel
            ]);
        }
        file_put_contents($STEP_FILE, '');
        exit();
    }
}

// ===== CALLBACK HANDLER =====

if ($data) {
    
    // Tekshirish tugmasi
    if ($data === 'check_membership') {
        $not_member = checkMembership($from_id, $kanal_raw, $pending_raw);
        if (empty($not_member)) {
            bot('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $cb_message_id,
                'text' => "✅ Siz barcha kanallarga a'zo bo'lgansiz!",
                'parse_mode' => 'HTML'
            ]);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "<b>🔥 Assalomu alaykum. @$botname ga Xush kelibsiz. Bot orqali quyidagilarni yuklab olishingiz mumkin:\n\n• Instagram - stories, post va IGTV + audio bilan\n• TikTok - suv belgisiz video;\n• YouTube - video;\n\nShazam funksiya:\n• Qo'shiq nomi yoki ijrochi ismi\n• Qo'shiq matni\n\n😎 Bot guruhlarda ham ishlay oladi!</b>",
                'parse_mode' => 'html',
                'reply_markup' => $bosh,
            ]);
        } else {
            $b = buildJoinKeyboardAndText($not_member);
            bot('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $cb_message_id,
                'text' => $b['text'],
                'parse_mode' => 'HTML',
                'reply_markup' => $b['reply_markup']
            ]);
        }
        exit();
    }
    
    // Zayafkani qabul qilish
    if (strpos($data, 'accept_') === 0) {
        $parts = explode('_', $data, 2);
        $channel_user = $parts[1];
        
        // Zayafkani qabul qilish
        bot('approveChatJoinRequest', [
            'chat_id' => explode('++', $channel_user)[0],
            'user_id' => explode('++', $channel_user)[1]
        ]);
        
        // Pending dan o'chirish
        $pendings = array_values(array_filter(array_map('trim', explode("\n", $pending_raw))));
        $pendings = array_values(array_filter($pendings, function($v) use ($channel_user){return $v != $channel_user;}));
        file_put_contents($PENDING_FILE, implode("\n", $pendings));
        
        // Xabarni yangilash
        bot('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
            'text' => '✅ Zayafka qabul qilindi!'
        ]);
        
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $cb_message_id,
            'text' => "✅ **Zayafka qabul qilindi!**\n\n" . substr($callback['message']['text'], 0, strpos($callback['message']['text'], '⏰')),
            'parse_mode' => 'Markdown'
        ]);
        exit();
    }
    
    // Zayafkani rad etish
    if (strpos($data, 'decline_') === 0) {
        $parts = explode('_', $data, 2);
        $channel_user = $parts[1];
        
        // Zayafkani rad etish
        bot('declineChatJoinRequest', [
            'chat_id' => explode('++', $channel_user)[0],
            'user_id' => explode('++', $channel_user)[1]
        ]);
        
        // Pending dan o'chirish
        $pendings = array_values(array_filter(array_map('trim', explode("\n", $pending_raw))));
        $pendings = array_values(array_filter($pendings, function($v) use ($channel_user){return $v != $channel_user;}));
        file_put_contents($PENDING_FILE, implode("\n", $pendings));
        
        // Xabarni yangilash
        bot('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
            'text' => '❌ Zayafka rad etildi!'
        ]);
        
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $cb_message_id,
            'text' => "❌ **Zayafka rad etildi!**\n\n" . substr($callback['message']['text'], 0, strpos($callback['message']['text'], '⏰')),
            'parse_mode' => 'Markdown'
        ]);
        exit();
    }
    
    // Xabarni o'chirish
    if ($data === "del") {
        bot('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $cb_message_id
        ]);
        exit();
    }
}

// ===== MEDIA YUKLAB OLISH FUNKSIYALARI =====

$matin = "📥 Yuklab olindi ushbu bot orqali";

// Instagram saqlash
if (strpos($text, "instagram.com") !== false) {
    // Majburiy a'zolik tekshiruvi
    if ($type == "private" && $kanal_raw !== '') {
        $not_member = checkMembership($from_id, $kanal_raw, $pending_raw);
        if (!empty($not_member)) {
            $b = buildJoinKeyboardAndText($not_member);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $b['text'],
                'parse_mode' => 'HTML',
                'reply_markup' => $b['reply_markup']
            ]);
            exit();
        }
    }
    
    $api_url = "?url=".urlencode($text);
    $json = json_decode(file_get_contents($api_url), true);
    $video_url = $json['videos'][0]['url'];
    
    bot('sendMessage', ['chat_id' => $cid, 'text' => "📥"]);
    sleep(2.8);
    bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
    sleep(0.3);
    
    bot('sendVideo', [
        'chat_id' => $cid,
        'video' => $video_url,
        'caption' => "$matin @$botname",
        'parse_mode' => 'html',
        'reply_markup' => $ortga,
    ]);
    exit();
}

// TikTok saqlash
if (strpos($text, "vt.tiktok.com") !== false || strpos($text, "tiktok.com") !== false) {
    // Majburiy a'zolik tekshiruvi
    if ($type == "private" && $kanal_raw !== '') {
        $not_member = checkMembership($from_id, $kanal_raw, $pending_raw);
        if (!empty($not_member)) {
            $b = buildJoinKeyboardAndText($not_member);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $b['text'],
                'parse_mode' => 'HTML',
                'reply_markup' => $b['reply_markup']
            ]);
            exit();
        }
    }
    
    $api = $text;
    $TikTok = json_decode(file_get_contents("https://tikwm.com/api/?url=$api"));
    $tiktok = $TikTok->data;
    $play = $tiktok->play;
    
    bot('sendMessage', ['chat_id' => $cid, 'text' => "📥"]);
    sleep(2.8);
    bot('deleteMessage', ['chat_id' => $cid, 'message_id' => $mid + 1]);
    sleep(3);
    
    bot('sendVideo', [
        'chat_id' => $cid,
        'video' => $play,
        'caption' => "$matin @$botname",
        'parse_mode' => 'html',
        'reply_markup' => $ortga,
    ]);
    exit();
}

// YouTube saqlash
if(strpos($text, "youtube.com") !== false || strpos($text, "youtu.be") !== false){
    // Majburiy a'zolik tekshiruvi
    if ($type == "private" && $kanal_raw !== '') {
        $not_member = checkMembership($from_id, $kanal_raw, $pending_raw);
        if (!empty($not_member)) {
            $b = buildJoinKeyboardAndText($not_member);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $b['text'],
                'parse_mode' => 'HTML',
                'reply_markup' => $b['reply_markup']
            ]);
            exit();
        }
    }
    
    $video_url = $text;
    $api_url = "?url=" . urlencode($video_url); 
    $natija = json_decode(file_get_contents($api_url), true);
    $video_url = $natija['video_with_audio'][0]['url'];
    
    bot('sendMessage',[
        'chat_id'=>$cid , 
        'text'=>"🎥Iltimos kuting...",
    ]);
    sleep(0.3);
    bot('deletemessage',[
        'chat_id'=>$cid , 
        'message_id'=>$mid + 1,
    ]);
    sleep(0.2); 
    bot('sendVideo', [
        'chat_id' => $cid,
        'video' => $video_url,
        'caption' => "📥Yuklab olindi ushbu bot orqali @$botname",
        'reply_markup' => $ortga,
    ]);
    exit();
}

// Musiqa saqlash
if ($text && !strpos($text, "http") && $text != "/start" && $text != "/start@$botname") {
    // Majburiy a'zolik tekshiruvi
    if ($type == "private" && $kanal_raw !== '') {
        $not_member = checkMembership($from_id, $kanal_raw, $pending_raw);
        if (!empty($not_member)) {
            $b = buildJoinKeyboardAndText($not_member);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $b['text'],
                'parse_mode' => 'HTML',
                'reply_markup' => $b['reply_markup']
            ]);
            exit();
        }
    }
    
    $api_url = "?music=" . urlencode($text);
    $response = file_get_contents($api_url);
    $music_data = json_decode($response, true);
    
    if (!$music_data || !isset($music_data['holat']) || $music_data['holat'] !== true) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "<b>😔 Afsuski hech narsa topilmadi</b>",
            'parse_mode' => "html",
        ]);
        exit();
    }
    
    $msctitle = "";
    $inline_keyboard = [];
    $results = $music_data['natijalar'];
    $max_results = min(10, count($results));
    
    for ($index = 0; $index < $max_results; $index++) {
        $music = $results[$index];
        $number = $index + 1;
        $title_parts = explode(' - ', $music['nomi']);
        $artist = htmlspecialchars($title_parts[0]);
        $title = htmlspecialchars(explode(' » ', $music['nomi'])[0]);
        $size = htmlspecialchars($music['hajmi']);
        
        $msctitle .= "<b>$number</b>. <i>$artist - $title</i> (<i>$size</i>)\n";
        
        $row = floor($index / 5);
        if (!isset($inline_keyboard[$row])) $inline_keyboard[$row] = [];
        $inline_keyboard[$row][] = ['text' => $number, 'callback_data' => "music_$index:$text"];
    }
    
    $inline_keyboard[] = [['text' => "❌", 'callback_data' => "del"]];
    
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "<b>🎙 <i>$text</i></b>\n\n$msctitle\n\n⏰ $soat  📅 $date",
        'parse_mode' => "html",
        'reply_markup' => json_encode(['inline_keyboard' => $inline_keyboard]),
    ]);
    exit();
}

// Callback handler - musiqa yuklash
if (isset($data) && strpos($data, 'music_') === 0) {
    $parts = explode(':', $data);
    $index = str_replace('music_', '', $parts[0]);
    $search_query = $parts[1] ?? '';
    
    bot('answerCallbackQuery', [
        'callback_query_id' => $callback['id'],
        'text' => "Iltimos kuting, musiqa yuklanmoqda...",
        'show_alert' => false
    ]);
    
    $api_url = "?music=" . urlencode($search_query);
    $response = file_get_contents($api_url);
    $music_data = json_decode($response, true);
    
    if ($music_data && isset($music_data['natijalar'][$index])) {
        $music = $music_data['natijalar'][$index];
        $audio_url = $music['yuklash'];
        $caption = "<b>" . htmlspecialchars(explode(' - ', $music['nomi'])[0]) . "</b> - <i>" . htmlspecialchars(explode(' » ', $music['nomi'])[0]) . "</i>\n\n@$botname orqali yuklab olindi\n\n⏰ $soat  📅 $date";
        
        bot('sendAudio', [
            'chat_id' => $chat_id,
            'audio' => $audio_url,
            'caption' => $caption,
            'parse_mode' => "html",
            'reply_markup' => $ortga
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Xatolik: Musiqa topilmadi",
            'parse_mode' => "html"
        ]);
    }
    exit();
}

?>