<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo   = getDB();
$error = '';
$flash = isset($_GET['msg']) ? htmlspecialchars((string) $_GET['msg']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission.';
    } else {
        $code      = strtoupper(trim($_POST['code'] ?? ''));
        $type      = $_POST['type'] ?? 'percent';
        $value     = (float) ($_POST['value'] ?? 0);
        $max_uses  = $_POST['max_uses'] !== '' ? (int) $_POST['max_uses'] : null;
        $active    = isset($_POST['active']) ? 1 : 0;
        $edit_id   = (int) ($_POST['id'] ?? 0);

        if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $code)) {
            $error = 'Code must be 2–40 characters: letters, numbers, _ or -';
        } elseif (!in_array($type, ['percent','flat'], true)) {
            $error = 'Invalid type.';
        } elseif ($value <= 0) {
            $error = 'Value must be greater than zero.';
        } elseif ($type === 'percent' && $value > 100) {
            $error = 'Percent cannot exceed 100.';
        } else {
            try {
                if ($edit_id) {
                    $pdo->prepare("UPDATE discount_codes SET code=?, type=?, value=?, max_uses=?, active=? WHERE id=?")
                        ->execute([$code, $type, $value, $max_uses, $active, $edit_id]);
                } else {
                    $pdo->prepare("INSERT INTO discount_codes (code, type, value, max_uses, active) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$code, $type, $value, $max_uses, $active]);
                }
                header('Location: /admin/discounts.php?msg=' . urlencode($edit_id ? 'Code updated.' : 'Code created.'));
                exit;
            } catch (\PDOException $e) {
                $error = 'That code already exists.';
            }
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM discount_codes WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$codes = $pdo->query("SELECT * FROM discount_codes ORDER BY active DESC, created_at DESC")->fetchAll();

$pageTitle = 'Discount Codes';
$activeNav = 'discounts';
require __DIR__ . '/layout-top.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Discount Codes</h1>
    </div>

    <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
    <?php if ($flash): ?><p class="success"><?php echo $flash; ?></p><?php endif; ?>

    <div class="form-card" style="margin-bottom:24px">
        <h3 style="font-family:'Syne',sans-serif;margin-bottom:16px"><?php echo $editing ? 'Edit code' : 'New code'; ?></h3>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:14px;margin-bottom:12px">
                <div>
                    <label>Code</label>
                    <input type="text" name="code" required maxlength="40"
                           value="<?php echo htmlspecialchars($editing['code'] ?? ''); ?>"
                           placeholder="FRIENDS50"
                           style="text-transform:uppercase;margin:0">
                </div>
                <div>
                    <label>Type</label>
                    <select name="type" style="margin:0">
                        <option value="percent" <?php echo ($editing['type'] ?? 'percent') === 'percent' ? 'selected' : ''; ?>>Percent (%)</option>
                        <option value="flat"    <?php echo ($editing['type'] ?? '')        === 'flat'    ? 'selected' : ''; ?>>Flat ($)</option>
                    </select>
                </div>
                <div>
                    <label>Value</label>
                    <input type="number" name="value" step="0.01" min="0.01" required
                           value="<?php echo htmlspecialchars($editing['value'] ?? ''); ?>" style="margin:0">
                </div>
                <div>
                    <label>Max uses <span style="font-weight:400;font-size:11px;opacity:.6">(blank = unlimited)</span></label>
                    <input type="number" name="max_uses" min="1"
                           value="<?php echo htmlspecialchars($editing['max_uses'] ?? ''); ?>" style="margin:0">
                </div>
            </div>

            <label style="margin-bottom:16px;display:block">
                <input type="checkbox" name="active" value="1" <?php echo (!$editing || $editing['active']) ? 'checked' : ''; ?>>
                Active
            </label>

            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save' : 'Create code'; ?></button>
                <?php if ($editing): ?>
                    <a href="/admin/discounts.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (!$codes): ?>
        <p>No codes yet.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Discount</th>
                <th>Uses</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($codes as $c): ?>
            <tr>
                <td style="font-family:'Syne',sans-serif;font-weight:700"><?php echo htmlspecialchars($c['code']); ?></td>
                <td>
                    <?php echo $c['type'] === 'percent' ? ((float) $c['value']) . '% off' : '$' . number_format($c['value'], 2) . ' off'; ?>
                </td>
                <td>
                    <?php echo (int) $c['times_used']; ?>
                    <?php echo $c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : ''; ?>
                </td>
                <td>
                    <?php if ($c['active']): ?>
                        <span class="badge-active">Active</span>
                    <?php else: ?>
                        <span class="badge-inactive">Disabled</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="td-actions">
                        <a href="/admin/discounts.php?edit=<?php echo $c['id']; ?>" class="btn btn-secondary" style="padding:8px 14px;font-size:12px">Edit</a>
                        <form method="POST" action="/admin/discount-delete.php" onsubmit="return confirm('Delete this code?')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                            <button type="submit" class="btn btn-danger" style="padding:8px 14px;font-size:12px">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</body>
</html>
