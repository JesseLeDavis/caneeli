<?php
/**
 * Builds the local test environment:
 *   1. writes .env.test (first run only): sample database, port 8080,
 *      email off, Stripe off unless your .env already has test keys
 *   2. recreates the sample database with the same tables as your local one
 *   3. fills it with sample products, orders, custom quotes, messages,
 *      signups, discount codes and shop activity
 *   4. draws sample product photos into assets/uploads/sample/
 *
 * Run: php dev/setup-test.php   (or dev/serve-test.sh --reset)
 * Safe to rerun: it only ever drops a database whose name ends in _test.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require __DIR__ . '/sample_images.php';

$root = dirname(__DIR__);
$base = parse_ini_file("$root/.env");
if (!$base) exit("Couldn't read .env\n");

// ── 1. .env.test ───────────────────────────────────────────────────────
$envTest = "$root/.env.test";
if (!is_file($envTest)) {
    $stripeTest = str_starts_with($base['STRIPE_SECRET_KEY'] ?? '', 'sk_test_');
    $lines = [
        '; Test environment overrides (read on top of .env by dev/serve-test.sh).',
        '; Not committed. Delete this file and rerun dev/setup-test.php to regenerate.',
        '',
        'DB_NAME="' . ($base['DB_NAME'] ?? 'caneeli') . '_test"',
        'SITE_URL="http://localhost:8080"',
        '',
        '; Admin login for the test site only',
        'ADMIN_USER="admin"',
        'ADMIN_PASS="caneeli-test"',
        '',
        '; Email is off: replies, order and shipping emails are skipped (and logged).',
        'SMTP_HOST=""',
        'OWNER_EMAIL=""',
        '',
        '; Checkout needs Stripe TEST keys (sk_test_ / pk_test_) from',
        '; https://dashboard.stripe.com/test/apikeys. Live keys are never copied here.',
        'STRIPE_PUBLIC_KEY="' . ($stripeTest ? $base['STRIPE_PUBLIC_KEY'] : '') . '"',
        'STRIPE_SECRET_KEY="' . ($stripeTest ? $base['STRIPE_SECRET_KEY'] : '') . '"',
        'STRIPE_WEBHOOK_SECRET="' . ($stripeTest ? ($base['STRIPE_WEBHOOK_SECRET'] ?? '') : '') . '"',
    ];
    file_put_contents($envTest, implode("\n", $lines) . "\n");
    echo "Wrote .env.test\n";
}
$env = array_merge($base, parse_ini_file($envTest));
if (str_starts_with($env['STRIPE_SECRET_KEY'] ?? '', 'sk_live_')) {
    exit(".env.test has a LIVE Stripe key. Use test keys (sk_test_) or leave it blank.\n");
}

$source = $base['DB_NAME'];
$target = $env['DB_NAME'];
if (!preg_match('/_test$/', $target) || $target === $source) {
    exit("Refusing to touch database '$target': the test database name must end in _test.\n");
}

// ── 2. Database ────────────────────────────────────────────────────────
$pdo = new PDO("mysql:host={$env['DB_HOST']};charset=utf8mb4", $env['DB_USER'], $env['DB_PASS'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("DROP DATABASE IF EXISTS `$target`");
$pdo->exec("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Same tables as the local database, so the test site never lags behind a
// migration you've already run locally.
$tables = $pdo->query("SHOW FULL TABLES FROM `$source` WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $t) {
    $create = $pdo->query("SHOW CREATE TABLE `$source`.`$t`")->fetch()['Create Table'];
    $create = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create);
    $pdo->exec("USE `$target`");
    $pdo->exec($create);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$pdo->exec("USE `$target`");
echo "Created database $target (" . count($tables) . " tables copied from $source)\n";

// ── 3. Sample photos ───────────────────────────────────────────────────
$imgDir = "$root/assets/uploads/sample";
if (!is_dir($imgDir)) mkdir($imgDir, 0775, true);
array_map('unlink', glob("$imgDir/*.svg"));

function photo(string $slug, int $n, string $kind, string $form, string $palette, int $seed): string {
    global $imgDir;
    $svg = $kind === 'glass' ? glass_svg($form, $seed, $palette) : ceramic_svg($form, $seed, $palette);
    file_put_contents("$imgDir/$slug-$n.svg", $svg);
    return "/assets/uploads/sample/$slug-$n.svg";
}

// ── 4. Products ────────────────────────────────────────────────────────
// [name, description, price, stock, status, featured, categories, signals, kind, form, palette/glaze, photos]
$catalog = [
    ['Koi Lamp', "A glass cube lamp with an orange koi circling the front panel, set on a maple base. Plugs in with an inline switch and takes a standard E12 bulb.", 145, 3, 'active', 1, ['Lighting', 'Stained Glass'], ['Ready to Ship', 'Solid Wood'], 'glass', 'lamp', 'sunset', 3],
    ['Pond Lamp', "Teal and sky-blue glass around a little goldfish. The light comes through soft and watery, like sun on a pond.", 155, 2, 'active', 1, ['Lighting', 'Stained Glass'], ['Ready to Ship', 'Solid Wood'], 'glass', 'lamp', 'pond', 2],
    ['Marigold Suncatcher', "An 8-inch round of amber and marigold glass. Hang it in a south window and it throws warm color across the room by late afternoon.", 68, 6, 'active', 1, ['Stained Glass', 'Wall Decor'], ['Ships in 3–5 Days'], 'glass', 'suncatcher', 'sunset', 2],
    ['Tidepool Suncatcher', "Cool blues and seafoam glass, soldered and finished with a black patina and a brass chain.", 68, 4, 'active', 0, ['Stained Glass', 'Wall Decor'], ['Ships in 3–5 Days'], 'glass', 'suncatcher', 'pond', 1],
    ['Meadow Window Panel', "A 12 × 18 inch panel in greens and buttercup yellow, framed in zinc with hanging hooks. Made to hang in front of a window, not to be installed in one.", 285, 1, 'active', 1, ['Stained Glass', 'Wall Decor'], ['Limited Edition', 'Ships in 3–5 Days'], 'glass', 'panel', 'meadow', 2],
    ['Berry Patch Panel', "Plum, rose and rust glass in a loose patchwork. One of a kind: the next one will be different.", 260, 0, 'sold_out', 0, ['Stained Glass', 'Wall Decor'], ['Limited Edition'], 'glass', 'panel', 'berry', 1],
    ['Frost Night Light', "Clear textured glass that scatters the light into a soft glow. A small warm bulb is included.", 42, 9, 'active', 0, ['Lighting', 'Stained Glass'], ['Ready to Ship'], 'glass', 'nightlight', 'clear', 1],
    ['Ember Night Light', "Orange and gold glass for a campfire glow in a hallway or kid's room. Bulb included.", 42, 0, 'sold_out', 0, ['Lighting', 'Stained Glass'], ['Ready to Ship'], 'glass', 'nightlight', 'sunset', 1],
    ['Lagoon Night Light', "Blues and teals with a few clear pieces so it doesn't get too dark. Bulb included.", 44, 5, 'active', 0, ['Lighting'], ['Ready to Ship'], 'glass', 'nightlight', 'pond', 1],
    ['Oatmeal Bud Vase', "A small wheel-thrown vase in a speckled oatmeal glaze. Holds water. About 6 inches tall.", 38, 7, 'active', 1, ['Ceramics'], ['Ready to Ship'], 'ceramic', 'vase', 'oatmeal', 2],
    ['Celadon Vase', "Taller vase in a soft green celadon that pools darker where it's thickest. About 10 inches tall.", 72, 2, 'active', 0, ['Ceramics'], ['Ships in 3–5 Days'], 'ceramic', 'vase', 'celadon', 2],
    ['Rust Drip Mug', "A 12 oz mug with a rust glaze that breaks to dark brown at the rim. Dishwasher and microwave safe.", 34, 12, 'active', 0, ['Ceramics'], ['Ready to Ship'], 'ceramic', 'mug', 'rust', 2],
    ['Inkwell Mug', "Deep blue-black glaze over speckled stoneware. 12 oz. Dishwasher and microwave safe.", 34, 3, 'active', 0, ['Ceramics'], ['Ready to Ship'], 'ceramic', 'mug', 'ink', 1],
    ['Butter Mug', "Pale yellow glaze, a little drippy at the foot. 12 oz.", 34, 0, 'sold_out', 0, ['Ceramics'], [], 'ceramic', 'mug', 'butter', 1],
    ['Trinket Dish', "A shallow dish for rings and keys, about 5 inches across.", 24, 15, 'active', 0, ['Ceramics', 'Other'], ['Ready to Ship'], 'ceramic', 'dish', 'celadon', 1],
    ['Little Planter', "Small planter with a drainage hole and a matching saucer. Fits a 4 inch nursery pot.", 46, 4, 'active', 0, ['Ceramics', 'Other'], ['Ready to Ship'], 'ceramic', 'planter', 'oatmeal', 1],
    ['Sunrise Lamp (custom size)', "Draft listing for a larger lamp. Not visible in the shop until it's marked active.", 240, 1, 'draft', 0, ['Lighting', 'Stained Glass'], ['Made to Order'], 'glass', 'lamp', 'berry', 1],
    ['Old Holiday Ornament', "Last season's ornament, archived so it stays out of the shop.", 18, 0, 'archived', 0, ['Other'], [], 'glass', 'suncatcher', 'berry', 1],
];

$insProduct = $pdo->prepare("INSERT INTO products
    (name, description, craft_signals, price, stock_qty, category, image_path, active, status, featured, sort_order, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insImage = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
$insCat   = $pdo->prepare("INSERT INTO product_categories (product_id, category) VALUES (?, ?)");

$products = [];
foreach ($catalog as $i => [$name, $desc, $price, $stock, $status, $featured, $cats, $signals, $kind, $form, $pal, $photoCount]) {
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    $paths = [];
    for ($n = 1; $n <= $photoCount; $n++) {
        $paths[] = photo($slug, $n, $kind, $form, $pal, crc32($slug) + $n * 7919);
    }
    $created = date('Y-m-d H:i:s', strtotime('-' . (120 - $i * 6) . ' days'));
    $insProduct->execute([
        $name, $desc, $signals ? json_encode($signals) : null, $price, $stock, $cats[0], $paths[0],
        in_array($status, ['active', 'sold_out'], true) ? 1 : 0, $status, $featured, $i + 1, $created, $created,
    ]);
    $id = (int) $pdo->lastInsertId();
    foreach ($paths as $n => $p) $insImage->execute([$id, $p, $n]);
    foreach ($cats as $c) $insCat->execute([$id, $c]);
    $products[$id] = ['name' => $name, 'price' => $price, 'status' => $status];
}
echo 'Added ' . count($products) . " products with sample photos\n";

// ── 5. Customers & orders ──────────────────────────────────────────────
// Fictional people at example.com, so no email can reach a real inbox.
$people = [
    ['Maya Okafor', 'Portland, OR 97214', '2210 SE Alder St'], ['Theo Lindqvist', 'Eugene, OR 97405', '88 Crest Dr'],
    ['Priya Raman', 'Seattle, WA 98103', '4519 Fremont Ave N'], ['Sam Whitaker', 'Austin, TX 78704', '1302 S 5th St'],
    ['Lena Duarte', 'Bend, OR 97701', '61 NW Riverside Blvd'], ['Jonah Feld', 'Brooklyn, NY 11215', '377 7th St'],
    ['Rosa Castellanos', 'Tucson, AZ 85719', '2040 E 9th St'], ['Owen Price', 'Salem, OR 97301', '915 Court St NE'],
    ['Hana Mori', 'Oakland, CA 94610', '3300 Lakeshore Ave'], ['Dev Malhotra', 'Denver, CO 80206', '1245 N Gaylord St'],
    ['Claire Benoit', 'Asheville, NC 28801', '19 Haywood St'], ['Marcus Hale', 'Boise, ID 83702', '507 W Bannock St'],
];
$emailOf = fn(string $n) => strtolower(str_replace(' ', '.', $n)) . '@example.com';

$insOrder = $pdo->prepare("INSERT INTO orders
    (stripe_session_id, stripe_payment_intent, customer_name, customer_email, status, total, amount_paid, shipping_address,
     tracking_number, notes, discount_code, discount_amount, created_at, fulfilled_at, confirmation_email_sent_at, shipping_email_sent_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price_at_purchase) VALUES (?, ?, ?, ?, ?)");

$rand = new Rand(20261115);
$sellable = array_keys(array_filter($products, fn($p) => in_array($p['status'], ['active', 'sold_out'], true)));
$orderCount = 0;
for ($k = 0; $k < 26; $k++) {
    $daysAgo = (int) round(58 * pow($k / 25, 1.3)); // more recent orders than old ones
    $created = date('Y-m-d H:i:s', strtotime("-$daysAgo days -" . (int) $rand->range(0, 600) . ' minutes'));
    [$name, $cityLine, $street] = $people[$k % count($people)];
    $status = $daysAgo > 12 ? $rand->pick(['fulfilled', 'fulfilled', 'fulfilled', 'cancelled']) : ($daysAgo > 3 ? $rand->pick(['fulfilled', 'paid']) : 'paid');
    if ($k === 0) $status = 'pending';

    $lines = [];
    $n = $rand->f() < 0.35 ? 2 : 1;
    for ($j = 0; $j < $n; $j++) {
        $pid = $rand->pick($sellable);
        $lines[$pid] = [$pid, $products[$pid]['name'], $rand->f() < 0.15 ? 2 : 1, $products[$pid]['price']];
    }
    $subtotal = array_sum(array_map(fn($l) => $l[2] * $l[3], $lines));
    $code = $k % 7 === 3 ? 'WELCOME10' : null;
    $discount = $code ? round($subtotal * 0.10, 2) : 0;
    $total = $subtotal - $discount;
    $fulfilled = $status === 'fulfilled' ? date('Y-m-d H:i:s', strtotime("$created +" . (int) $rand->range(3, 9) . ' days')) : null;

    $insOrder->execute([
        'cs_test_sample_' . bin2hex(random_bytes(10)), 'pi_test_sample_' . bin2hex(random_bytes(8)),
        $name, $emailOf($name), $status, $total, $status === 'pending' ? 0 : $total,
        "$name\n$street\n$cityLine\nUS",
        $status === 'fulfilled' ? '9400 1' . str_pad((string) (int) $rand->range(0, 999999999), 9, '0') . ' 2' . (int) $rand->range(10, 99) : null,
        $k === 2 ? 'Asked for extra bubble wrap, it\'s a gift.' : null,
        $code, $discount, $created, $fulfilled,
        $status === 'pending' ? null : $created, $fulfilled,
    ]);
    $oid = (int) $pdo->lastInsertId();
    foreach ($lines as $l) $insItem->execute([$oid, ...$l]);
    $orderCount++;
}
echo "Added $orderCount orders\n";

// ── 6. Custom quotes (commissions) ─────────────────────────────────────
$insQuote = $pdo->prepare("INSERT INTO custom_quotes
    (token, customer_name, customer_email, title, description, lead_time, total, deposit_amount, status, terms_accepted_at,
     quote_email_sent_at, deposit_email_sent_at, balance_email_sent_at, order_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$quotes = [
    ['Nadia Brooks', 'Front door transom', "Arched transom panel, 34 × 14 inches, in amber and clear seedy glass with a simple sunburst. Zinc frame, ready for your installer.", '6–8 weeks', 640, 200, 'draft'],
    ['Eli Navarro', 'Pair of koi lamps', "Two koi lamps facing each other, one orange and one white koi, on walnut bases.", '4–5 weeks', 310, 100, 'sent'],
    ['Grace Liu', 'Memorial suncatcher', "12 inch round suncatcher in blues and greens with a small dog silhouette in the center.", '3–4 weeks', 180, 60, 'deposit_paid'],
    ['Ben Carter', 'Kitchen cabinet inserts', "Four small panels for upper cabinet doors, textured clear glass with one blue accent each.", '8–10 weeks', 920, 300, 'balance_requested'],
    ['Ivy Sandoval', 'Wedding cake topper', "Small stained glass heart on a ceramic base, ivory and gold.", '2–3 weeks', 120, 40, 'paid'],
];
foreach ($quotes as $q => [$name, $title, $desc, $lead, $total, $deposit, $status]) {
    $created = date('Y-m-d H:i:s', strtotime('-' . (30 - $q * 6) . ' days'));
    $sent = $status !== 'draft' ? $created : null;
    $depositPaid = in_array($status, ['deposit_paid', 'balance_requested', 'paid'], true);
    $orderId = null;
    if ($depositPaid) {
        $paid = $status === 'paid';
        $insOrder->execute([
            'cs_test_sample_' . bin2hex(random_bytes(10)), 'pi_test_sample_' . bin2hex(random_bytes(8)),
            $name, $emailOf($name), $paid ? 'paid' : 'deposit_paid', $total, $paid ? $total : $deposit,
            "$name\n12 Sample Lane\nPortland, OR 97202\nUS", null, null, null, 0, $created, null, $created, null,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE orders SET balance_due = ? WHERE id = ?")->execute([$paid ? 0 : $total - $deposit, $orderId]);
        $insItem->execute([$orderId, null, $title, 1, $total]);
    }
    $insQuote->execute([
        bin2hex(random_bytes(16)), $name, $emailOf($name), $title, $desc, $lead, $total, $deposit, $status,
        $depositPaid ? $created : null, $sent, $depositPaid ? $created : null,
        in_array($status, ['balance_requested', 'paid'], true) ? date('Y-m-d H:i:s', strtotime('-2 days')) : null,
        $orderId, $created,
    ]);
    if ($orderId) {
        $pdo->prepare("UPDATE orders SET quote_id = ? WHERE id = ?")->execute([(int) $pdo->lastInsertId(), $orderId]);
    }
}
echo 'Added ' . count($quotes) . " custom quotes\n";

// ── 7. Messages, signups, discount codes ───────────────────────────────
$insMsg = $pdo->prepare("INSERT INTO contact_messages (name, email, message, reply, replied_at, is_read, is_spam, spam_reason, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$messages = [
    ['Nadia Brooks', "Hi Annie! I'd love a transom window for over our front door. It's about 34 inches wide. Is that something you do? Budget is around $600.", null, 1, 0, null, 9],
    ['Tom Ashby', "Do you ship to Canada? I'd love the koi lamp for my mum.", null, 0, 0, null, 1],
    ['Keiko Tan', "The pond lamp arrived today and it's even prettier in person. Thank you for the little note!", "That makes my week, thank you Keiko! Enjoy it.", 1, 0, null, 4],
    ['Rafael Ortiz', "Are your mugs food safe? Thinking of getting four as a wedding gift.", null, 0, 0, null, 0],
    ['Sara Quinn', "Would you ever do a class or workshop? I'm in Eugene.", null, 1, 0, null, 6],
    ['SEO Experts', "We can get your website to page one of Google guaranteed!!! Reply for our packages http://example.com/seo", null, 0, 1, 'Contains a link; marketing language', 2],
    ['Crypto Bonus', "Claim your bonus now http://example.com/claim http://example.com/now", null, 0, 1, 'Multiple links', 3],
];
foreach ($messages as [$name, $text, $reply, $read, $spam, $why, $days]) {
    $at = date('Y-m-d H:i:s', strtotime("-$days days -3 hours"));
    $insMsg->execute([$name, $emailOf($name), $text, $reply, $reply ? $at : null, $read, $spam, $why, '127.0.0.1', $at]);
}
$insSignup = $pdo->prepare("INSERT INTO email_signups (email, created_at) VALUES (?, ?)");
for ($s = 0; $s < 34; $s++) {
    $insSignup->execute(["friend$s@example.com", date('Y-m-d H:i:s', strtotime('-' . (int) ($s * 2.4) . ' days'))]);
}
$pdo->exec("INSERT INTO discount_codes (code, type, value, max_uses, times_used, active) VALUES
    ('WELCOME10', 'percent', 10, NULL, 4, 1),
    ('FRIENDS20', 'flat', 20, 25, 7, 1),
    ('HOLIDAY15', 'percent', 15, 100, 0, 0)");
echo "Added messages, 34 email signups and 3 discount codes\n";

// ── 8. Shop activity for Insights ──────────────────────────────────────
$insView = $pdo->prepare("INSERT INTO product_views (product_id, session_id, referrer, viewed_at) VALUES (?, ?, ?, ?)");
$insCart = $pdo->prepare("INSERT INTO cart_events (product_id, event_type, session_id, created_at) VALUES (?, ?, ?, ?)");
$visible = array_keys(array_filter($products, fn($p) => in_array($p['status'], ['active', 'sold_out'], true)));
$views = 0;
for ($v = 0; $v < 420; $v++) {
    $pid = $visible[(int) floor(pow($rand->f(), 1.8) * count($visible))]; // a few pieces get most views
    $sess = 'sample' . (int) $rand->range(0, 160);
    $at = date('Y-m-d H:i:s', strtotime('-' . (int) $rand->range(0, 89) . ' days -' . (int) $rand->range(0, 1400) . ' minutes'));
    $insView->execute([$pid, $sess, $rand->pick(['https://www.instagram.com/', 'https://www.google.com/', null, 'https://www.tiktok.com/']), $at]);
    $views++;
    if ($rand->f() < 0.14) {
        $insCart->execute([$pid, 'add', $sess, $at]);
        if ($rand->f() < 0.25) $insCart->execute([$pid, 'remove', $sess, $at]);
        elseif ($rand->f() < 0.55) $insCart->execute([$pid, 'checkout_start', $sess, $at]);
    }
}
echo "Added $views product views and cart activity\n\n";

echo "Done. Start it with: dev/serve-test.sh\n";
echo "Admin login: {$env['ADMIN_USER']} / {$env['ADMIN_PASS']}\n";
