<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

// Actions: mark read/unread, delete.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id && isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'read') {
            $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'unread') {
            $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = ?")->execute([$id]);
        }
    }
    header('Location: /admin/messages.php');
    exit;
}

$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
$unread   = 0;
foreach ($messages as $m) {
    if (!$m['is_read']) {
        $unread++;
    }
}

$pageTitle = 'Messages';
$activeNav = 'messages';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Messages<?php echo $unread ? ' <span style="color:#C25B32">(' . $unread . ' new)</span>' : ''; ?></h1>
    </div>

    <?php if (!$messages): ?>
        <p style="color:rgba(45,45,45,0.55)">No messages yet. Submissions from the contact page will appear here.</p>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px">
            <?php foreach ($messages as $m): ?>
            <div class="order-card" style="<?php echo $m['is_read'] ? '' : 'border-left:4px solid #C25B32'; ?>">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                    <div>
                        <strong style="font-size:16px"><?php echo htmlspecialchars($m['name']); ?></strong>
                        <?php if (!$m['is_read']): ?>
                            <span style="display:inline-block;margin-left:8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#C25B32">New</span>
                        <?php endif; ?>
                        <div style="margin-top:2px;font-size:14px">
                            <a href="mailto:<?php echo htmlspecialchars($m['email']); ?>?subject=<?php echo rawurlencode('Re: your message to Caneeli Designs'); ?>"><?php echo htmlspecialchars($m['email']); ?></a>
                        </div>
                    </div>
                    <div style="font-size:13px;color:rgba(45,45,45,0.55);white-space:nowrap">
                        <?php echo date('M j, Y g:ia', strtotime($m['created_at'])); ?>
                    </div>
                </div>

                <p style="margin:14px 0 0;font-size:15px;line-height:1.6;white-space:pre-line"><?php echo htmlspecialchars($m['message']); ?></p>

                <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
                    <a href="mailto:<?php echo htmlspecialchars($m['email']); ?>?subject=<?php echo rawurlencode('Re: your message to Caneeli Designs'); ?>" class="btn btn-primary" style="padding:8px 16px;font-size:13px">Reply</a>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                        <input type="hidden" name="action" value="<?php echo $m['is_read'] ? 'unread' : 'read'; ?>">
                        <button type="submit" class="btn btn-secondary" style="padding:8px 16px;font-size:13px"><?php echo $m['is_read'] ? 'Mark unread' : 'Mark read'; ?></button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Delete this message?')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
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
