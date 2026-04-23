<?php
require_once __DIR__ . '/db.php';

/**
 * Look up a discount code. Returns the row or null.
 * Only returns codes that are active and not over max_uses.
 */
function discount_lookup(string $code): ?array {
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    $stmt = getDB()->prepare("SELECT * FROM discount_codes WHERE code = ? AND active = 1");
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row) return null;
    if ($row['max_uses'] !== null && (int) $row['times_used'] >= (int) $row['max_uses']) {
        return null;
    }
    return $row;
}

/** Returns dollar amount to subtract from subtotal. */
function discount_amount(array $code, float $subtotal): float {
    if ($code['type'] === 'percent') {
        $d = $subtotal * ((float) $code['value'] / 100);
    } else {
        $d = (float) $code['value'];
    }
    return min($d, $subtotal);
}

function discount_increment_usage(int $id): void {
    getDB()->prepare("UPDATE discount_codes SET times_used = times_used + 1 WHERE id = ?")->execute([$id]);
}
