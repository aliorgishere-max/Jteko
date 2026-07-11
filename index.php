<?php
error_reporting(0);
ini_set('display_errors', 0);

define('API_KEY', '8642722220:AAEnzJ5yZsJf7jkFEiVAA6lw8ufa43Zuh6g');
define('ADMIN_ID', 7664331942); // ادمین اصلی (غیرقابل حذف)

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}

$update = json_decode(file_get_contents('php://input'));
if (!isset($update)) exit;

if (isset($update->message)) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $text = $message->text;
    $from_id = $message->from->id;
    $message_id = $message->message_id;
} elseif (isset($update->callback_query)) {
    $callback_query = $update->callback_query;
    $chat_id = $callback_query->message->chat->id;
    $data = $callback_query->data;
    $from_id = $callback_query->from->id;
    $message_id = $callback_query->message->message_id;
}

if (!file_exists('db.json')) {
    $init = [
        'users' => [],
        'services' => [],
        'configs' => [],
        'channels' => ['Ultra_mindiran'],
        'status' => 'on',
        'orders' => [],
        'admins' => [] // لیست ادمین‌های اضافی
    ];
    file_put_contents('db.json', json_encode($init, JSON_PRETTY_PRINT));
}

$db = json_decode(file_get_contents('db.json'), true);

// اگر کلید admins در دیتابیس نبود، ایجاد کن
if (!isset($db['admins'])) {
    $db['admins'] = [];
    file_put_contents('db.json', json_encode($db));
}

// تابع چک کردن ادمین بودن (هم ADMIN_ID و هم لیست ادمین‌ها)
function isAdmin($user_id) {
    global $db;
    if ($user_id == ADMIN_ID) return true;
    if (in_array($user_id, $db['admins'])) return true;
    return false;
}

if (!isset($db['users'][$from_id])) {
    $db['users'][$from_id] = [
        'step' => 'none',
        'wallet' => 0,
        'my_services' => []
    ];
    file_put_contents('db.json', json_encode($db));
}

if ($db['status'] == 'off' && !isAdmin($from_id)) {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "⚠️ ربات موقتا در دست تعمیر است. لطفا بعدا مراجعه کنید."
    ]);
    exit;
}

function checkJoin($user_id) {
    global $db;
    foreach ($db['channels'] as $ch) {
        $check = bot('getChatMember', ['chat_id' => "@".$ch, 'user_id' => $user_id]);
        if ($check && isset($check->result)) {
            $status = $check->result->status;
            if ($status == 'left' || $status == 'kicked') {
                return false;
            }
        } else {
            return false;
        }
    }
    return true;
}

$main_keyboard = json_encode([
    'keyboard' => [
        [['text' => "🛍️ خرید سرویس"]],
        [['text' => "💳 کیف پول"], ['text' => "📦 سرویس‌های من"]],
        [['text' => "☎️ تماس با پشتیبانی"]]
    ],
    'resize_keyboard' => true
]);

if (isset($text) && !checkJoin($from_id) && $text != '/start') {
    $db['users'][$from_id]['step'] = 'none';
    file_put_contents('db.json', json_encode($db));
    $buttons = [];
    foreach ($db['channels'] as $ch) {
        $buttons[] = [['text' => "📢 عضویت در کانال", 'url' => "https://t.me/".$ch]];
    }
    $buttons[] = [['text' => "✅ بررسی عضویت", 'callback_data' => "check_join"]];
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "🔒 برای استفاده از ربات KoryConfig ابتدا باید در کانال‌های زیر عضو شوید:",
        'reply_markup' => json_encode(['inline_keyboard' => $buttons])
    ]);
    exit;
}

if (isset($data) && $data == 'check_join') {
    if (checkJoin($from_id)) {
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🎉 عضویت شما تایید شد! به ربات KoryConfig خوش آمدید.",
            'reply_markup' => $main_keyboard
        ]);
    } else {
        bot('answerCallbackQuery', [
            'callback_query_id' => $callback_query->id,
            'text' => "❌ هنوز در کانال‌ها عضو نشده‌اید!",
            'show_alert' => true
        ]);
    }
    exit;
}

if (isset($text)) {
    if ($text == '/start') {
        $db['users'][$from_id]['step'] = 'none';
        file_put_contents('db.json', json_encode($db));
        if (!checkJoin($from_id)) {
            $buttons = [];
            foreach ($db['channels'] as $ch) {
                $buttons[] = [['text' => "📢 عضویت در کانال", 'url' => "https://t.me/".$ch]];
            }
            $buttons[] = [['text' => "✅ بررسی عضویت", 'callback_data' => "check_join"]];
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "🔒 برای استفاده از ربات KoryConfig ابتدا باید در کانال‌های زیر عضو شوید:",
                'reply_markup' => json_encode(['inline_keyboard' => $buttons])
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "👋 سلام به ربات KoryConfig خوش آمدید!\n\nلطفا از منوی زیر گزینه مورد نظر خود را انتخاب کنید 👇",
                'reply_markup' => $main_keyboard
            ]);
        }
    }
    
    elseif ($text == '🛍️ خرید سرویس') {
        if (empty($db['services'])) {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "📭 در حال حاضر هیچ سرویسی تعریف نشده است."
            ]);
            exit;
        }
        $inline = [];
        foreach ($db['services'] as $id => $service) {
            $inline[] = [['text' => $service['name']." | ".number_format($service['price'])." ریال", 'callback_data' => "buy_".$id]];
        }
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "👇 سرویس مورد نظر خود را انتخاب کنید:",
            'reply_markup' => json_encode(['inline_keyboard' => $inline])
        ]);
    }
    
    elseif ($text == '📦 سرویس‌های من') {
        $myserv = $db['users'][$from_id]['my_services'];
        if (empty($myserv)) {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "📭 شما هنوز هیچ سرویسی خریداری نکرده‌اید."
            ]);
        } else {
            $txt = "📦 لیست سرویس‌های خریداری شده شما:\n\n";
            $idx = 1;
            foreach ($myserv as $srv) {
                $txt .= "🔹 **سرویس:** {$srv['name']}\n";
                $txt .= "🔑 `{$srv['config']}`\n";
                $txt .= "📅 **تاریخ:** {$srv['date']}\n";
                $txt .= "──────────────────\n";
                $idx++;
            }
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $txt,
                'parse_mode' => 'Markdown'
            ]);
        }
    }
    
    elseif ($text == '💳 کیف پول') {
        $wallet = number_format($db['users'][$from_id]['wallet']);
        $db['users'][$from_id]['step'] = 'charge_amount';
        file_put_contents('db.json', json_encode($db));
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "💳 **موجودی فعلی شما:** $wallet ریال\n\n💰 لطفا مبلغی که می‌خواهید به موجودی خود اضافه کنید را به **ریال** و به صورت عدد لاتین ارسال کنید:",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 بازگشت"]]], 'resize_keyboard' => true])
        ]);
    }
    
    elseif ($text == '🔙 بازگشت') {
        $db['users'][$from_id]['step'] = 'none';
        file_put_contents('db.json', json_encode($db));
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🏠 به منوی اصلی بازگشتید.",
            'reply_markup' => $main_keyboard
        ]);
    }
    
    elseif ($text == '☎️ تماس با پشتیبانی') {
        $db['users'][$from_id]['step'] = 'support';
        file_put_contents('db.json', json_encode($db));
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "☎️ پیام خود را ارسال کنید تا به مدیریت منتقل شود:",
            'reply_markup' => json_encode(['keyboard' => [[['text' => "🔙 بازگشت"]]], 'resize_keyboard' => true])
        ]);
    }
    
    elseif ($text == '/panel' && isAdmin($from_id)) {
        $db['users'][$from_id]['step'] = 'none';
        file_put_contents('db.json', json_encode($db));
        $ucount = count($db['users']);
        $scount = count($db['services']);
        $st = $db['status'] == 'on' ? '🟢 روشن' : '🔴 خاموش';
        $p_text = "⚙️ **پنل مدیریت ربات KoryConfig**\n\n👥 تعداد کاربران: $ucount\n📦 تعداد سرویس‌ها: $scount\nوضعیت ربات: $st";
        $p_keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => "📊 آمار و وضعیت", 'callback_data' => "p_stats"], ['text' => "💡 تغییر وضعیت ربات", 'callback_data' => "p_toggle"]],
                [['text' => "➕ افزودن کانال اجباری", 'callback_data' => "p_addch"], ['text' => "❌ حذف کانال اجباری", 'callback_data' => "p_delch"]],
                [['text' => "➕ ساخت سرویس جدید", 'callback_data' => "p_addsrv"], ['text' => "⚙️ مدیریت سرویس‌ها", 'callback_data' => "p_managesrv"]],
                [['text' => "📢 ارسال پیام همگانی", 'callback_data' => "p_sendall"]],
                [['text' => "👥 مدیریت ادمین‌ها", 'callback_data' => "p_manage_admins"]] // دکمه جدید
            ]
        ]);
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $p_text,
            'parse_mode' => 'Markdown',
            'reply_markup' => $p_keyboard
        ]);
    }
    
    elseif (strpos($text, "/reply_") === 0 && isAdmin($from_id)) {
        $parts = explode("_", $text);
        $target = $parts[1];
        $db['users'][$from_id]['step'] = "replyto_" . $target;
        file_put_contents('db.json', json_encode($db));
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✍️ پیام خود را برای کاربر $target ارسال کنید:"
        ]);
    }
    
    else {
        $step = $db['users'][$from_id]['step'];
        
        if ($step == 'charge_amount') {
            if (!is_numeric($text) || $text <= 0) {
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ لطفا یک عدد معتبر ارسال کنید."]);
                exit;
            }
            $db['users'][$from_id]['step'] = 'send_receipt_' . $text;
            file_put_contents('db.json', json_encode($db));
            
            $f_amount = number_format($text);
            $card_msg = "💳 **درخواست افزایش موجودی**\n\n"
                      . "💵 مبلغ: $f_amount ریال\n\n"
                      . "لطفا مبلغ فوق را به شماره کارت زیر واریز نمایید:\n\n"
                      . "✨ `5859471029562323` ✨\n"
                      . "👤 **عماد صادقی**\n\n"
                      . "⚠️ پس از واریز، دکمه **«تایید پرداخت»** را بزنید و در مرحله بعد عکس رسید را بفرستید.";
            
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $card_msg,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[['text' => "✅ تایید پرداخت", 'callback_data' => "confirm_pay"]]]
                ])
            ]);
        }
        
        elseif (strpos($step, 'receipt_upload_') === 0) {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ لطفا فقط عکس رسید پرداخت را ارسال کنید."]);
        }
        
        elseif ($step == 'support') {
            bot('sendMessage', [
                'chat_id' => ADMIN_ID,
                'text' => "📬 **پیام جدید پشتیبانی**\n👤 فرستنده: $from_id\n\n💬 متن پیام:\n$text\n\n📥 جهت پاسخ دادن روی دستور زیر کلیک کنید:\n/reply_$from_id"
            ]);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ پیام شما با موفقیت به پشتیبانی ارسال شد.",
                'reply_markup' => $main_keyboard
            ]);
            $db['users'][$from_id]['step'] = 'none';
            file_put_contents('db.json', json_encode($db));
        }
        
        elseif (strpos($step, 'replyto_') === 0) {
            $target = str_replace('replyto_', '', $step);
            bot('sendMessage', [
                'chat_id' => $target,
                'text' => "☎️ **پاسخ پشتیبانی:**\n\n$text"
            ]);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ پاسخ شما به کاربر $target ارسال شد."
            ]);
            $db['users'][$from_id]['step'] = 'none';
            file_put_contents('db.json', json_encode($db));
        }
        
        elseif (isAdmin($from_id)) {
            if ($step == 'p_addch_step') {
                $db['channels'][] = str_replace('@', '', $text);
                $db['users'][$from_id]['step'] = 'none';
                file_put_contents('db.json', json_encode($db));
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ کانال @$text با موفقیت اضافه شد."]);
            }
            
            elseif ($step == 'p_addsrv_name') {
                $new_id = uniqid();
                $db['services'][$new_id] = ['name' => $text, 'price' => 0];
                $db['users'][$from_id]['step'] = 'p_addsrv_price_' . $new_id;
                file_put_contents('db.json', json_encode($db));
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "💰 اکنون قیمت سرویس را به **ریال** وارد کنید:"]);
            }
            
            elseif (strpos($step, 'p_addsrv_price_') === 0) {
                $srv_id = str_replace('p_addsrv_price_', '', $step);
                if (!is_numeric($text)) {
                    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ لطفا قیمت را به صورت عدد وارد کنید."]);
                    exit;
                }
                $db['services'][$srv_id]['price'] = (int)$text;
                $db['users'][$from_id]['step'] = 'none';
                file_put_contents('db.json', json_encode($db));
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ سرویس با موفقیت ساخته شد. حالا می‌توانید از مدیریت سرویس‌ها برای آن کانفیگ اضافه کنید."]);
            }
            
            elseif (strpos($step, 'p_addconf_') === 0) {
                $srv_id = str_replace('p_addconf_', '', $step);
                $db['configs'][$srv_id][] = $text;
                $db['users'][$from_id]['step'] = 'none';
                file_put_contents('db.json', json_encode($db));
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ کانفیگ با موفقیت به این سرویس اضافه شد."]);
            }
            
            elseif (strpos($step, 'p_editprice_') === 0) {
                $srv_id = str_replace('p_editprice_', '', $step);
                if (!is_numeric($text)) {
                    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ قیمت نامعتبر است."]);
                    exit;
                }
                $db['services'][$srv_id]['price'] = (int)$text;
                $db['users'][$from_id]['step'] = 'none';
                file_put_contents('db.json', json_encode($db));
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ قیمت سرویس آپدیت شد."]);
            }
            
            elseif ($step == 'p_sendall_step') {
                $db['users'][$from_id]['step'] = 'none';
                file_put_contents('db.json', json_encode($db));
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "📢 پروسه ارسال پیام همگانی شروع شد..."]);
                foreach ($db['users'] as $u_id => $u_data) {
                    bot('sendMessage', ['chat_id' => $u_id, 'text' => $text]);
                }
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ پیام همگانی به همه اعضا ارسال شد."]);
            }

            // مدیریت افزودن ادمین جدید (مرحله دریافت آیدی)
            elseif ($step == 'p_add_admin_step') {
                if (!is_numeric($text)) {
                    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ لطفا آیدی عددی کاربر را وارد کنید."]);
                    exit;
                }
                $new_admin = (int)$text;
                if ($new_admin == ADMIN_ID) {
                    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ این کاربر ادمین اصلی است و قبلاً در لیست وجود دارد."]);
                } elseif (in_array($new_admin, $db['admins'])) {
                    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ این کاربر قبلاً به عنوان ادمین اضافه شده است."]);
                } else {
                    $db['admins'][] = $new_admin;
                    file_put_contents('db.json', json_encode($db));
                    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ کاربر با آیدی $new_admin به لیست ادمین‌ها اضافه شد."]);
                }
                $db['users'][$from_id]['step'] = 'none';
                file_put_contents('db.json', json_encode($db));
            }
        }
    }
}

if (isset($update->message->photo)) {
    $step = $db['users'][$from_id]['step'];
    if (strpos($step, 'receipt_upload_') === 0) {
        $amount = str_replace('receipt_upload_', '', $step);
        $photo = $update->message->photo;
        $file_id = $photo[count($photo) - 1]->file_id;
        
        $db['users'][$from_id]['step'] = 'none';
        file_put_contents('db.json', json_encode($db));
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "⏳ رسید شما برای مدیریت ارسال شد. پس از تایید موجودی شما افزایش می‌یابد.",
            'reply_markup' => $main_keyboard
        ]);
        
        $f_amount = number_format($amount);
        bot('sendPhoto', [
            'chat_id' => ADMIN_ID,
            'photo' => $file_id,
            'caption' => "💵 **رسید پرداخت جدید**\n\n👤 کاربر: $from_id\n💰 مبلغ درخواستی: $f_amount ریال",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "✅ تایید فاکتور", 'callback_data' => "adm_verify_{$from_id}_{$amount}"], ['text' => "❌ لغو فاکتور", 'callback_data' => "adm_reject_{$from_id}"]]
                ]
            ])
        ]);
    }
}

if (isset($data)) {
    if ($data == 'confirm_pay') {
        $step = $db['users'][$from_id]['step'];
        if (strpos($step, 'send_receipt_') === 0) {
            $amount = str_replace('send_receipt_', '', $step);
            $db['users'][$from_id]['step'] = 'receipt_upload_' . $amount;
            file_put_contents('db.json', json_encode($db));
            bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "📸 لطفا عکس رسید واریزی خود را ارسال کنید:"
            ]);
        }
    }
    
    elseif (strpos($data, 'adm_verify_') === 0) {
        $ex = explode("_", $data);
        $u_id = $ex[2];
        $amount = $ex[3];
        
        $db['users'][$u_id]['wallet'] += (int)$amount;
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ حساب کاربر $u_id شارژ شد."]);
        
        $f_amount = number_format($amount);
        bot('sendMessage', [
            'chat_id' => $u_id,
            'text' => "🎉 **فاکتور تایید شد!**\n\n💰 مبلغ $f_amount ریال با موفقیت به حساب شما اضافه شد."
        ]);
        file_put_contents('db.json', json_encode($db));
    }
    
    elseif (strpos($data, 'adm_reject_') === 0) {
        $ex = explode("_", $data);
        $u_id = $ex[2];
        
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ فاکتور رد شد."]);
        
        bot('sendMessage', [
            'chat_id' => $u_id,
            'text' => "🔴 **فاکتور لغو شد!**\n\nپرداخت شما توسط مدیریت تایید نشد و پولی به حساب شما واریز نگردید."
        ]);
    }
    
    elseif (strpos($data, 'buy_') === 0) {
        $srv_id = str_replace('buy_', '', $data);
        if (!isset($db['services'][$srv_id])) {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ سرویس یافت نشد."]);
            exit;
        }
        $service = $db['services'][$srv_id];
        $price = $service['price'];
        $user_wallet = $db['users'][$from_id]['wallet'];
        
        if ($user_wallet < $price) {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ **موجودی شما کافی نیست!**\n\n💰 قیمت سرویس: " . number_format($price) . " ریال\n💳 موجودی شما: " . number_format($user_wallet) . " ریال\n\nلطفا ابتدا حساب خود را شارژ کنید."
            ]);
            exit;
        }
        
        if (empty($db['configs'][$srv_id])) {
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "📭 متاسفانه در حال حاضر کانفیگ موجودی برای این سرویس وجود ندارد. لطفا به پشتیبانی پیام دهید."]);
            exit;
        }
        
        $config = array_shift($db['configs'][$srv_id]);
        $db['users'][$from_id]['wallet'] -= $price;
        
        $srv_info = [
            'name' => $service['name'],
            'config' => $config,
            'date' => date('Y-m-d H:i')
        ];
        $db['users'][$from_id]['my_services'][] = $srv_info;
        file_put_contents('db.json', json_encode($db));
        
        bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
        
        $bill = "🛍️ **فاکتور خرید موفق KoryConfig**\n\n"
              . "📦 **سرویس:** {$service['name']}\n"
              . "💰 **مبلغ کسر شده:** " . number_format($price) . " ریال\n"
              . "💳 **باقیمانده کیف پول:** " . number_format($db['users'][$from_id]['wallet']) . " ریال\n"
              . "──────────────────\n"
              . "🔑 **کانفیگ اختصاصی شما:**\n\n"
              . "`$config`\n\n"
              . "✨ از خرید شما سپاسگزاریم! کانفیگ در منوی سرویس‌های من نیز ذخیره شد.";
              
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $bill,
            'parse_mode' => 'Markdown'
        ]);
    }
    
    // مدیریت بخش ادمین‌ها
    if ($data == 'p_manage_admins' && isAdmin($from_id)) {
        $admin_list = "👥 **لیست ادمین‌ها:**\n\n";
        $admin_list .= "🔹 **ادمین اصلی:** `" . ADMIN_ID . "` (غیرقابل حذف)\n";
        if (!empty($db['admins'])) {
            foreach ($db['admins'] as $admin) {
                $admin_list .= "🔸 ادمین: `$admin`\n";
            }
        } else {
            $admin_list .= "🔸 هیچ ادمین اضافی ثبت نشده است.\n";
        }
        
        $inline_keyboard = [];
        // دکمه حذف برای هر ادمین (به جز ادمین اصلی)
        foreach ($db['admins'] as $admin) {
            $inline_keyboard[] = [['text' => "❌ حذف ادمین `$admin`", 'callback_data' => "p_remove_admin_$admin"]];
        }
        // دکمه افزودن ادمین جدید
        $inline_keyboard[] = [['text' => "➕ افزودن ادمین جدید", 'callback_data' => "p_add_admin"]];
        // دکمه بازگشت به پنل
        $inline_keyboard[] = [['text' => "🔙 بازگشت به پنل", 'callback_data' => "p_back_to_panel"]];
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $admin_list,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['inline_keyboard' => $inline_keyboard])
        ]);
    }
    
    elseif ($data == 'p_add_admin' && isAdmin($from_id)) {
        $db['users'][$from_id]['step'] = 'p_add_admin_step';
        file_put_contents('db.json', json_encode($db));
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "👤 لطفا آیدی عددی کاربر جدید را وارد کنید:"
        ]);
    }
    
    elseif (strpos($data, 'p_remove_admin_') === 0 && isAdmin($from_id)) {
        $admin_to_remove = str_replace('p_remove_admin_', '', $data);
        if ($admin_to_remove == ADMIN_ID) {
            bot('answerCallbackQuery', [
                'callback_query_id' => $callback_query->id,
                'text' => "❌ نمی‌توانید ادمین اصلی را حذف کنید!",
                'show_alert' => true
            ]);
        } elseif (in_array($admin_to_remove, $db['admins'])) {
            $db['admins'] = array_values(array_diff($db['admins'], [$admin_to_remove]));
            file_put_contents('db.json', json_encode($db));
            bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ ادمین با آیدی $admin_to_remove با موفقیت حذف شد."
            ]);
        } else {
            bot('answerCallbackQuery', [
                'callback_query_id' => $callback_query->id,
                'text' => "❌ این ادمین در لیست وجود ندارد!",
                'show_alert' => true
            ]);
        }
    }
    
    elseif ($data == 'p_back_to_panel' && isAdmin($from_id)) {
        // بازگشت به پنل مدیریت (دوباره منوی اصلی پنل رو نمایش بده)
        $ucount = count($db['users']);
        $scount = count($db['services']);
        $st = $db['status'] == 'on' ? '🟢 روشن' : '🔴 خاموش';
        $p_text = "⚙️ **پنل مدیریت ربات KoryConfig**\n\n👥 تعداد کاربران: $ucount\n📦 تعداد سرویس‌ها: $scount\nوضعیت ربات: $st";
        $p_keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => "📊 آمار و وضعیت", 'callback_data' => "p_stats"], ['text' => "💡 تغییر وضعیت ربات", 'callback_data' => "p_toggle"]],
                [['text' => "➕ افزودن کانال اجباری", 'callback_data' => "p_addch"], ['text' => "❌ حذف کانال اجباری", 'callback_data' => "p_delch"]],
                [['text' => "➕ ساخت سرویس جدید", 'callback_data' => "p_addsrv"], ['text' => "⚙️ مدیریت سرویس‌ها", 'callback_data' => "p_managesrv"]],
                [['text' => "📢 ارسال پیام همگانی", 'callback_data' => "p_sendall"]],
                [['text' => "👥 مدیریت ادمین‌ها", 'callback_data' => "p_manage_admins"]]
            ]
        ]);
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $p_text,
            'parse_mode' => 'Markdown',
            'reply_markup' => $p_keyboard
        ]);
    }
    
    elseif ($from_id == ADMIN_ID) {
        if ($data == 'p_stats') {
            $ucount = count($db['users']);
            $scount = count($db['services']);
            $st = $db['status'] == 'on' ? '🟢 روشن' : '🔴 خاموش';
            bot('answerCallbackQuery', [
                'callback_query_id' => $callback_query->id,
                'text' => "📊 آمار:\nکاربران: $ucount\nسرویس‌ها: $scount\nوضعیت: $st",
                'show_alert' => true
            ]);
        }
        
        elseif ($data == 'p_toggle') {
            $db['status'] = ($db['status'] == 'on') ? 'off' : 'on';
            file_put_contents('db.json', json_encode($db));
            bot('answerCallbackQuery', [
                'callback_query_id' => $callback_query->id,
                'text' => "وضعیت ربات تغییر کرد به: " . $db['status'],
                'show_alert' => true
            ]);
        }
        
        elseif ($data == 'p_addch') {
            $db['users'][$from_id]['step'] = 'p_addch_step';
            file_put_contents('db.json', json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "📣 آیدی کانال را بدون @ ارسال کنید:"]);
        }
        
        elseif ($data == 'p_delch') {
            if (empty($db['channels'])) {
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ هیچ کانالی تعریف نشده است."]);
                exit;
            }
            $inline = [];
            foreach ($db['channels'] as $ch) {
                $inline[] = [['text' => "@".$ch, 'callback_data' => "p_removech_".$ch]];
            }
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ کانالی که می‌خواهید حذف شود را انتخاب کنید:", 'reply_markup' => json_encode(['inline_keyboard' => $inline])]);
        }
        
        elseif (strpos($data, 'p_removech_') === 0) {
            $ch = str_replace('p_removech_', '', $data);
            $db['channels'] = array_values(array_diff($db['channels'], [$ch]));
            file_put_contents('db.json', json_encode($db));
            bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ کانال @$ch حذف شد."]);
        }
        
        elseif ($data == 'p_addsrv') {
            $db['users'][$from_id]['step'] = 'p_addsrv_name';
            file_put_contents('db.json', json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "📝 نام سرویس جدید را وارد کنید (مثلا: مولتی لوکیشن):"]);
        }
        
        elseif ($data == 'p_managesrv') {
            if (empty($db['services'])) {
                bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ هیچ سرویسی وجود ندارد."]);
                exit;
            }
            $inline = [];
            foreach ($db['services'] as $id => $srv) {
                $inline[] = [['text' => $srv['name'], 'callback_data' => "p_srvopt_".$id]];
            }
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "⚙️ سرویس مورد نظر را برای مدیریت انتخاب کنید:", 'reply_markup' => json_encode(['inline_keyboard' => $inline])]);
        }
        
        elseif (strpos($data, 'p_srvopt_') === 0) {
            $srv_id = str_replace('p_srvopt_', '', $data);
            $srv = $db['services'][$srv_id];
            $ccount = isset($db['configs'][$srv_id]) ? count($db['configs'][$srv_id]) : 0;
            
            $text_opt = "📦 **سرویس:** {$srv['name']}\n💰 **قیمت:** " . number_format($srv['price']) . " ریال\n🔋 **تعداد کانفیگ موجود:** $ccount";
            $inline = [
                [['text' => "➕ افزودن کانفیگ", 'callback_data' => "p_addconf_".$srv_id], ['text' => "🗑️ حذف کانفیگ‌ها", 'callback_data' => "p_clearconf_".$srv_id]],
                [['text' => "💵 تغییر قیمت", 'callback_data' => "p_editprice_".$srv_id], ['text' => "❌ حذف کل سرویس", 'callback_data' => "p_delsrv_".$srv_id]]
            ];
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => $text_opt, 'parse_mode' => 'Markdown', 'reply_markup' => json_encode(['inline_keyboard' => $inline])]);
        }
        
        elseif (strpos($data, 'p_addconf_') === 0) {
            $srv_id = str_replace('p_addconf_', '', $data);
            $db['users'][$from_id]['step'] = 'p_addconf_' . $srv_id;
            file_put_contents('db.json', json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "🔑 متن کانفیگ (V2ray Link) را ارسال کنید:"]);
        }
        
        elseif (strpos($data, 'p_clearconf_') === 0) {
            $srv_id = str_replace('p_clearconf_', '', $data);
            $db['configs'][$srv_id] = [];
            file_put_contents('db.json', json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ تمامی کانفیگ‌های این سرویس پاک شدند."]);
        }
        
        elseif (strpos($data, 'p_editprice_') === 0) {
            $srv_id = str_replace('p_editprice_', '', $data);
            $db['users'][$from_id]['step'] = 'p_editprice_' . $srv_id;
            file_put_contents('db.json', json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "💰 قیمت جدید را به **ریال** بفرستید:"]);
        }
        
        elseif (strpos($data, 'p_delsrv_') === 0) {
            $srv_id = str_replace('p_delsrv_', '', $data);
            unset($db['services'][$srv_id]);
            unset($db['configs'][$srv_id]);
            file_put_contents('db.json', json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ سرویس به طور کامل حذف شد."]);
        }
        
        elseif ($data == 'p_sendall') {
            $db['users'][$from_id]['step'] = 'p_sendall_step';
            file_put_contents('db.json', json_encode($db));
            bot('sendMessage', ['chat_id' => $chat_id, 'text' => "📢 متن پیام همگانی خود را ارسال کنید:"]);
        }
    }
}
?>