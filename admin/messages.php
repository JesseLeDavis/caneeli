<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/emails/templates.php';

$pdo = getDB();

// Flash message passed back via query string after a reply attempt.
$flash = $_GET['sent'] ?? '';

// Inbox or Spam tab. Spam is whatever the contact form flagged
// (includes/contact_spam.php) plus anything marked by hand.
$view    = ($_GET['view'] ?? $_POST['view'] ?? '') === 'spam' ? 'spam' : 'inbox';
$backUrl = '/admin/messages.php' . ($view === 'spam' ? '?view=spam' : '');

// Actions: mark read/unread, spam/not spam, delete, reply.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if (($_POST['action'] ?? '') === 'empty_spam') {
        $pdo->exec("DELETE FROM contact_messages WHERE is_spam = 1");
        header('Location: /admin/messages.php?view=spam');
        exit;
    }
    if ($id && isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'read') {
            $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'unread') {
            $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'spam') {
            $pdo->prepare("UPDATE contact_messages SET is_spam = 1, is_read = 1, spam_reason = COALESCE(spam_reason, 'marked by you') WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'not_spam') {
            // Back to the inbox as unread, so it isn't missed.
            $pdo->prepare("UPDATE contact_messages SET is_spam = 0, is_read = 0 WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'reply') {
            $reply = trim($_POST['reply'] ?? '');
            $stmt  = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
            $stmt->execute([$id]);
            $msg = $stmt->fetch();

            if (!$msg || $reply === '') {
                header('Location: /admin/messages.php?sent=empty#m' . $id);
                exit;
            }

            $sent = send_mail(
                $msg['email'],
                $msg['name'] ?? '',
                'Re: your message to ' . SITE_NAME,
                build_reply_email($msg, $reply),
                '',
                null,
                OWNER_EMAIL !== '' ? OWNER_EMAIL : null // replies go back to Annie
            );

            if ($sent) {
                $pdo->prepare("UPDATE contact_messages SET reply = ?, replied_at = CURRENT_TIMESTAMP, is_read = 1 WHERE id = ?")
                    ->execute([$reply, $id]);
                header('Location: /admin/messages.php?sent=ok#m' . $id);
            } else {
                header('Location: /admin/messages.php?sent=fail#m' . $id);
            }
            exit;
        }
    }
    header('Location: ' . $backUrl);
    exit;
}

// If migrate_contact_spam.sql hasn't been run yet there's no is_spam column:
// show everything as the inbox, with no Spam tab.
$hasSpam = true;
try {
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE is_spam = ? ORDER BY created_at DESC");
    $stmt->execute([$view === 'spam' ? 1 : 0]);
    $messages  = $stmt->fetchAll();
    $spamCount = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_spam = 1")->fetchColumn();
} catch (PDOException $e) {
    $hasSpam   = false;
    $view      = 'inbox';
    $messages  = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
    $spamCount = 0;
}
$unread = 0;
if ($view === 'inbox') {
    foreach ($messages as $m) {
        if (!$m['is_read']) {
            $unread++;
        }
    }
}

$pageTitle = 'Messages';
$activeNav = 'messages';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo $view === 'spam' ? 'Spam' : 'Messages'; ?><?php echo $unread ? ' <span style="color:#C25B32">(' . $unread . ' new)</span>' : ''; ?></h1>
    </div>

    <?php if ($hasSpam): ?>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:18px">
            <a href="/admin/messages.php" class="btn <?php echo $view === 'inbox' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding:8px 16px;font-size:13px">Inbox</a>
            <a href="/admin/messages.php?view=spam" class="btn <?php echo $view === 'spam' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding:8px 16px;font-size:13px">Spam<?php echo $spamCount ? ' (' . $spamCount . ')' : ''; ?></a>
            <?php if ($view === 'spam' && $spamCount): ?>
                <form method="POST" style="margin-left:auto" onsubmit="return confirm('Permanently delete all <?php echo $spamCount; ?> spam messages?')">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="empty_spam">
                    <button type="submit" class="btn btn-danger" style="padding:8px 16px;font-size:13px">Delete all spam</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($view === 'spam'): ?>
            <p style="margin:0 0 18px;font-size:14px;color:rgba(45,45,45,0.6)">Messages the contact form flagged as likely spam. Skim now and then: if a real one landed here, hit <strong>Not spam</strong> to move it back to the inbox.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($flash === 'ok'): ?>
        <div class="success">Reply sent.</div>
    <?php elseif ($flash === 'fail'): ?>
        <div class="error">Couldn't send the reply — check the SMTP settings and try again.</div>
    <?php elseif ($flash === 'empty'): ?>
        <div class="error">Write a reply before sending.</div>
    <?php endif; ?>

    <?php if (!$messages): ?>
        <p style="color:rgba(45,45,45,0.55)"><?php echo $view === 'spam' ? 'No spam. Nice.' : 'No messages yet. Submissions from the contact page will appear here.'; ?></p>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px">
            <?php foreach ($messages as $m): ?>
            <div class="order-card" id="m<?php echo $m['id']; ?>" style="<?php echo $m['is_read'] ? '' : 'border-left:4px solid #C25B32'; ?>">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                    <div>
                        <strong style="font-size:16px"><?php echo htmlspecialchars($m['name']); ?></strong>
                        <?php if (!$m['is_read']): ?>
                            <span style="display:inline-block;margin-left:8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#C25B32">New</span>
                        <?php elseif (!empty($m['replied_at'])): ?>
                            <span style="display:inline-block;margin-left:8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#276749">Replied</span>
                        <?php endif; ?>
                        <div style="margin-top:2px;font-size:14px">
                            <a href="mailto:<?php echo htmlspecialchars($m['email']); ?>"><?php echo htmlspecialchars($m['email']); ?></a>
                        </div>
                    </div>
                    <div style="font-size:13px;color:rgba(45,45,45,0.55);white-space:nowrap">
                        <?php echo date('M j, Y g:ia', strtotime($m['created_at'])); ?>
                    </div>
                </div>

                <p style="margin:14px 0 0;font-size:15px;line-height:1.6;white-space:pre-line"><?php echo htmlspecialchars($m['message']); ?></p>

                <?php if (!empty($m['is_spam']) && !empty($m['spam_reason'])): ?>
                    <p style="margin:12px 0 0;font-size:12px;color:rgba(45,45,45,0.55)">Flagged: <?php echo htmlspecialchars($m['spam_reason']); ?></p>
                <?php endif; ?>

                <?php if (!empty($m['replied_at'])): ?>
                    <div style="margin-top:16px;padding:14px 16px;background:rgba(39,103,73,0.06);border-left:3px solid #276749;border-radius:8px">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:rgba(45,45,45,0.5);margin-bottom:6px">
                            Your reply &middot; <?php echo date('M j, Y g:ia', strtotime($m['replied_at'])); ?>
                        </div>
                        <p style="margin:0;font-size:14px;line-height:1.6;white-space:pre-line;color:var(--dark)"><?php echo htmlspecialchars($m['reply']); ?></p>
                    </div>
                <?php endif; ?>

                <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
                    <details style="flex:1 1 100%">
                        <summary class="btn btn-primary" style="padding:8px 16px;font-size:13px;display:inline-block;cursor:pointer;list-style:none"><?php echo !empty($m['replied_at']) ? 'Reply again' : 'Reply'; ?></summary>
                        <form method="POST" style="margin-top:12px;padding:16px;background:rgba(194,91,50,0.04);border-radius:12px">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="view" value="<?php echo $view; ?>">
                            <input type="hidden" name="action" value="reply">
                            <label style="display:block;font-size:12px;color:rgba(45,45,45,0.6);margin-bottom:6px">Your reply to <?php echo htmlspecialchars($m['email']); ?></label>
                            <textarea name="reply" rows="5" required placeholder="Write your reply…" style="width:100%;margin-bottom:6px;font-family:inherit"></textarea>
                            <p style="margin:0 0 12px;font-size:12px;color:rgba(45,45,45,0.55)">Sends from <?php echo htmlspecialchars(MAIL_FROM_EMAIL ?: 'your shop email'); ?>; their reply comes back to <?php echo htmlspecialchars(OWNER_EMAIL ?: 'you'); ?>.</p>
                            <button type="submit" class="btn btn-primary" style="width:100%">Send reply</button>
                        </form>
                    </details>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="view" value="<?php echo $view; ?>">
                        <input type="hidden" name="action" value="<?php echo $m['is_read'] ? 'unread' : 'read'; ?>">
                        <button type="submit" class="btn btn-secondary" style="padding:8px 16px;font-size:13px"><?php echo $m['is_read'] ? 'Mark unread' : 'Mark read'; ?></button>
                    </form>
                    <?php if ($hasSpam): ?>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                        <input type="hidden" name="view" value="<?php echo $view; ?>">
                        <input type="hidden" name="action" value="<?php echo $view === 'spam' ? 'not_spam' : 'spam'; ?>">
                        <button type="submit" class="btn btn-secondary" style="padding:8px 16px;font-size:13px"><?php echo $view === 'spam' ? 'Not spam' : 'Spam'; ?></button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Delete this message?')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="view" value="<?php echo $view; ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger" style="padding:8px 16px;font-size:13px">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
