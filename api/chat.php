<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);

// GET রিকোয়েস্ট: চ্যাট হিস্ট্রি লোড করা
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($user_id) {
            $stmt = $db->prepare("
                SELECT id, sender, message, created_at 
                FROM chat_messages 
                WHERE user_id = :user_id 
                ORDER BY id ASC 
                LIMIT 100
            ");
            $stmt->execute([':user_id' => $user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // লগইন না থাকলে সেশন থেকে গেস্টের মেসেজ আইডিগুলো লোড করা
            $guest_ids = $_SESSION['guest_chat_ids'] ?? [];
            if (!empty($guest_ids)) {
                $placeholders = implode(',', array_fill(0, count($guest_ids), '?'));
                $stmt = $db->prepare("
                    SELECT id, sender, message, created_at 
                    FROM chat_messages 
                    WHERE id IN ($placeholders) 
                    ORDER BY id ASC
                ");
                $stmt->execute($guest_ids);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $messages = [];
            }
        }

        echo json_encode([
            'success' => true,
            'status' => 'success',
            'messages' => $messages
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Could not load chat history.']);
    }
    exit;
}

// POST রিকোয়েস্ট: কাস্টমারের মেসেজ সেভ করা এবং বটের রিপ্লাই তৈরি ও সেভ করা
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    $customer_message = trim($input['message'] ?? '');
    if ($customer_message === '') {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Message cannot be empty.']);
        exit;
    }

    try {
        // ১. কাস্টমারের মেসেজ ডাটাবেসে সেভ করা
        $insCust = $db->prepare("
            INSERT INTO chat_messages (user_id, sender, message) 
            VALUES (:user_id, 'customer', :message)
        ");
        $insCust->execute([
            ':user_id' => $user_id,
            ':message' => $customer_message
        ]);
        $cust_msg_id = (int)$db->lastInsertId();

        // ২. বটের উত্তর তৈরি করা (ডাটাবেস প্রোডাক্ট ও FAQ ভিত্তিক)
        $lower = mb_strtolower($customer_message);
        $bot_reply = '';

        if (preg_match('/(hi|hello|hey|salam|assalamu|হ্যালো|হাই|সালাম)/u', $lower)) {
            $bot_reply = "Hello! Welcome to Handicraft by Sonia 💜 How can I help you today? You can ask about our handmade jewellery, prices, delivery charges, or custom orders!";
        } elseif (preg_match('/(delivery|shipping|charge|courier|ডেলিভারি|চার্জ)/u', $lower)) {
            $bot_reply = "We deliver all over Bangladesh! 🚚 Delivery charge is ৳70 inside Chattogram and ৳120 outside Chattogram. Cash on Delivery, bKash, Nagad, and Rocket are available.";
        } elseif (preg_match('/(custom|customize|design|কাস্টম|ডিজাইন)/u', $lower)) {
            $bot_reply = "Yes! We love making customized handmade jewellery and bridal accessories. Please visit our 'Custom Order' page to upload your reference image and share your design details.";
        } elseif (preg_match('/(payment|bkash|nagad|rocket|cod|পেমেন্ট|বিকাশ)/u', $lower)) {
            $bot_reply = "We accept Cash on Delivery (COD), bKash, Nagad, and Rocket during checkout.";
        } elseif (preg_match('/(contact|phone|number|email|address|যোগাযোগ|নাম্বার|ঠিকানা)/u', $lower)) {
            $bot_reply = "You can reach us at:\n📞 Phone: 01537312870\n📧 Email: handicraft.by.sonia@gmail.com\n📍 Address: South Khulshi, 1 No Road, Chattogram.";
        } elseif (preg_match('/(price|product|item|jewellery|gajra|kalira|churi|jhapta|pin|দাম|প্রোডাক্ট)/u', $lower)) {
            $prodStmt = $db->query("SELECT name, price, stock FROM products ORDER BY id ASC LIMIT 6");
            $prods = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($prods) {
                $list = [];
                foreach ($prods as $p) {
                    $list[] = "• " . $p['name'] . " — ৳" . number_format((float)$p['price'], 0) . " (" . ($p['stock'] > 0 ? "In Stock" : "Out of Stock") . ")";
                }
                $bot_reply = "Here are some of our popular handmade items:\n" . implode("\n", $list) . "\n\nVisit our Shop page to explore all products!";
            }
        }

        // যদি উপরের কীওয়ার্ডে না মিলে, তবে ডাটাবেসের faqs টেবিল থেকে মিল খোঁজা
        if ($bot_reply === '') {
            $faqStmt = $db->prepare("
                SELECT answer FROM faqs 
                WHERE status = 'active' AND (question LIKE :q OR answer LIKE :q) 
                ORDER BY sort_order ASC LIMIT 1
            ");
            $faqStmt->execute([':q' => '%' . $customer_message . '%']);
            $faqRow = $faqStmt->fetch(PDO::FETCH_ASSOC);
            if ($faqRow) {
                $bot_reply = $faqRow['answer'];
            } else {
                $bot_reply = "Thank you for your message! 💜 For specific design inquiries, please check our Shop or Custom Order page, or call us directly at 01537312870.";
            }
        }

        // ৩. বটের মেসেজ ডাটাবেসে সেভ করা
        $insBot = $db->prepare("
            INSERT INTO chat_messages (user_id, sender, message) 
            VALUES (:user_id, 'bot', :message)
        ");
        $insBot->execute([
            ':user_id' => $user_id,
            ':message' => $bot_reply
        ]);
        $bot_msg_id = (int)$db->lastInsertId();

        // গেস্ট ইউজার হলে সেশনে মেসেজ আইডি সেভ রাখা যাতে রিলোড দিলেও চ্যাট হিস্ট্রি থাকে
        if (!$user_id) {
            if (!isset($_SESSION['guest_chat_ids'])) {
                $_SESSION['guest_chat_ids'] = [];
            }
            $_SESSION['guest_chat_ids'][] = $cust_msg_id;
            $_SESSION['guest_chat_ids'][] = $bot_msg_id;
        }

        echo json_encode([
            'success' => true,
            'status' => 'success',
            'customer_message' => [
                'id' => $cust_msg_id,
                'sender' => 'customer',
                'message' => $customer_message,
                'created_at' => date('h:i A')
            ],
            'bot_message' => [
                'id' => $bot_msg_id,
                'sender' => 'bot',
                'message' => $bot_reply,
                'created_at' => date('h:i A')
            ],
            'reply' => $bot_reply
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Failed to save chat message.']);
    }
    exit;
}