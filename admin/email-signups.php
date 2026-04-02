<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

// Handle CSV export
if (isset($_GET['export'])) {
    $rows = $pdo->query("SELECT email, created_at FROM email_signups ORDER BY created_at DESC")->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="email-signups.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Signed Up']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['email'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && csrf_verify()) {
    $stmt = $pdo->prepare("DELETE FROM email_signups WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    header('Location: /admin/email-signups.php');
    exit;
}

$signups = $pdo->query("SELECT * FROM email_signups ORDER BY created_at DESC")->fetchAll();
$count   = count($signups);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Signups | Caneeli Admin</title>
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<div class="admin-header">
    <div class="admin-header__brand">
        <img src="/assets/images/logowi.svg" alt="Caneeli Designs" class="admin-header__logo">
        <span class="admin-header__admin-label">Admin</span>
    </div>
    <a href="/admin/logout.php" class="admin-header__logout">Log Out</a>
</div>

<div class="container">
    <div class="page-header">
        <h1>Email Signups (<?php echo $count; ?>)</h1>
        <div style="display:flex;gap:10px;align-items:center">
            <a href="/admin/dashboard.php" class="btn btn-secondary">&larr; Products</a>
            <?php if ($count): ?>
                <a href="/admin/email-signups.php?export=1" class="btn btn-primary">Export CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$count): ?>
        <p>No signups yet.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Email</th>
                <th>Signed Up</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($signups as $signup): ?>
            <tr>
                <td><?php echo htmlspecialchars($signup['email']); ?></td>
                <td><?php echo date('M j, Y g:ia', strtotime($signup['created_at'])); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Remove this email?')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="delete_id" value="<?php echo $signup['id']; ?>">
                        <button type="submit" class="btn btn-danger" style="padding:8px 14px;font-size:13px">Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</body>
</html>
