<?php
/**
 * Failed-login rate limiting for the admin panel.
 *
 * Two gates, because neither alone is enough here:
 *
 *   Per-IP  — the normal case. Five misses locks that address out for
 *             fifteen minutes. Scoped to the IP so a brute-force attempt
 *             from elsewhere can never lock Annie out of her own panel.
 *
 *   Global  — a backstop. Client IPs come from X-Forwarded-For (the CDN
 *             sits in front, so REMOTE_ADDR is Hostinger's edge and is the
 *             same for every visitor), and a determined attacker can spoof
 *             that header to get a fresh bucket on each try. The global
 *             gate caps total failures regardless of claimed IP. The
 *             threshold is deliberately high and the cool-off short, so
 *             mistyping a password a few times can't lock out the owner.
 *
 * Deliberately no sleep() on failure: it would tie up a PHP-FPM worker per
 * request, which on shared hosting hands an attacker a cheap way to exhaust
 * the pool. The database window does the work instead.
 */

const LOGIN_MAX_PER_IP      = 5;    // failures before this IP is locked
const LOGIN_IP_WINDOW_MIN   = 15;   // ...counted over this many minutes
const LOGIN_MAX_GLOBAL      = 50;   // failures from all sources combined
const LOGIN_GLOBAL_WINDOW_M = 15;
const LOGIN_GLOBAL_COOLOFF  = 5;    // minutes everyone waits once tripped

/**
 * Best-effort client IP.
 *
 * Behind Hostinger's CDN, REMOTE_ADDR is always the edge node, so without
 * X-Forwarded-For every visitor shares one throttle bucket and the first
 * failed login locks out the world. XFF is attacker-controlled, which is
 * exactly why the global gate above exists to cover for it.
 */
function login_client_ip(): string {
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fwd !== '') {
        // Leftmost entry is the original client as reported by the CDN.
        foreach (explode(',', $fwd) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/**
 * Seconds this caller must wait before another attempt is accepted,
 * or 0 if they may try now.
 */
function login_lockout_seconds(PDO $pdo, string $ip): int {
    // Timestamps come back as UNIX_TIMESTAMP, not strings for strtotime():
    // MySQL and PHP don't share a timezone (locally MySQL is on SYSTEM time,
    // PHP on UTC), and parsing a MySQL datetime in PHP's zone skewed the lock
    // by hours — enough that it expired the instant it was set.
    // ── Per-IP gate ──────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS n, UNIX_TIMESTAMP(MAX(attempted_at)) AS last
           FROM admin_login_attempts
          WHERE ip = ?
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    $stmt->execute([$ip, LOGIN_IP_WINDOW_MIN]);
    $row = $stmt->fetch();

    if ((int) ($row['n'] ?? 0) >= LOGIN_MAX_PER_IP) {
        $unlocks = (int) $row['last'] + LOGIN_IP_WINDOW_MIN * 60;
        $wait    = $unlocks - time();
        if ($wait > 0) return $wait;
    }

    // ── Global gate ──────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS n, UNIX_TIMESTAMP(MAX(attempted_at)) AS last
           FROM admin_login_attempts
          WHERE attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    $stmt->execute([LOGIN_GLOBAL_WINDOW_M]);
    $row = $stmt->fetch();

    if ((int) ($row['n'] ?? 0) >= LOGIN_MAX_GLOBAL) {
        $unlocks = (int) $row['last'] + LOGIN_GLOBAL_COOLOFF * 60;
        $wait    = $unlocks - time();
        if ($wait > 0) return $wait;
    }

    return 0;
}

/** Record a failed attempt, and prune rows that have aged out of the window. */
function login_record_failure(PDO $pdo, string $ip): void {
    $pdo->prepare("INSERT INTO admin_login_attempts (ip) VALUES (?)")
        ->execute([$ip]);

    // Cheap opportunistic cleanup — roughly one run in ten keeps the table
    // from growing without needing a cron job.
    if (random_int(1, 10) === 1) {
        $pdo->prepare(
            "DELETE FROM admin_login_attempts
              WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        )->execute([max(LOGIN_IP_WINDOW_MIN, LOGIN_GLOBAL_WINDOW_M) * 2]);
    }
}

/** Clear an address's failures after a successful login. */
function login_clear(PDO $pdo, string $ip): void {
    $pdo->prepare("DELETE FROM admin_login_attempts WHERE ip = ?")
        ->execute([$ip]);
}

/** "3 minutes" / "45 seconds" — for the lockout message. */
function login_wait_label(int $seconds): string {
    if ($seconds >= 60) {
        $mins = (int) ceil($seconds / 60);
        return $mins . ' minute' . ($mins === 1 ? '' : 's');
    }
    return $seconds . ' second' . ($seconds === 1 ? '' : 's');
}
