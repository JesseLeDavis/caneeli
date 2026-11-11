<?php
/**
 * Spam checks for the public contact form (pages/contact.php).
 *
 * The rule: suspicious messages are FLAGGED, never discarded. They're saved
 * with is_spam = 1 and show up under Spam in /admin/messages.php, so if a
 * check misfires on a real customer, Annie can still find the message.
 * Bots are shown the same "thanks" as everyone else so they learn nothing.
 *
 * The one hard "no" is the rate limit: past CONTACT_MAX_PER_HOUR from one
 * address, the visitor sees an error (with their text kept) and nothing is
 * stored, so a single bot can't flood the table.
 */

require_once __DIR__ . '/login_throttle.php'; // login_client_ip(): CDN-aware client IP

const CONTACT_MAX_PER_HOUR   = 5;     // submissions per IP, spam included
const CONTACT_MIN_SECONDS    = 3;     // faster than this from page load = bot
const CONTACT_MAX_LINKS      = 2;     // more links than this = link-dropping
const CONTACT_MAX_NAME       = 100;   // characters (also the input's maxlength)
const CONTACT_MAX_MESSAGE    = 5000;  // characters (also the textarea's maxlength)

// Hidden trap field. Deliberately a meaningless name: browsers and password
// managers autofill fields that look like "website", "url", "company", etc.,
// which would have flagged real people.
const CONTACT_HONEYPOT_FIELD = 'cn_extra';
const CONTACT_TIME_FIELD     = 'cn_t';

/**
 * Reasons this submission looks like spam (empty array = looks fine).
 * Runs after the required-field checks, so name/email/message are present.
 */
function contact_spam_reasons(array $post, string $name, string $message): array {
    $reasons = [];

    if (trim($post[CONTACT_HONEYPOT_FIELD] ?? '') !== '') {
        $reasons[] = 'filled the hidden field';
    }

    // Unix time written into the form when it was rendered. A missing or
    // garbled value means the form was posted without being loaded.
    $loadedAt = $post[CONTACT_TIME_FIELD] ?? '';
    if (!ctype_digit($loadedAt)) {
        $reasons[] = 'posted without loading the form';
    } elseif (time() - (int) $loadedAt < CONTACT_MIN_SECONDS) {
        $reasons[] = 'sent within ' . CONTACT_MIN_SECONDS . 's of the page loading';
    }

    $linkPattern = '~https?://|www\.|\[url~i';
    $links = preg_match_all($linkPattern, $message);
    if ($links > CONTACT_MAX_LINKS) {
        $reasons[] = "$links links in the message";
    }
    if (preg_match($linkPattern, $name)) {
        $reasons[] = 'link in the name';
    }

    return $reasons;
}

/** Submissions from this IP in the last hour (0 if the column isn't there yet). */
function contact_recent_count(PDO $pdo, string $ip): int {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM contact_messages
              WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0; // migrate_contact_spam.sql not run yet
    }
}

/**
 * Save a submission. If the spam columns don't exist yet (code deployed
 * before the migration), fall back to the original insert so real messages
 * are never lost; spam is dropped in that case, as before.
 */
function contact_save(PDO $pdo, string $name, string $email, string $message, array $spamReasons, string $ip): void {
    try {
        $pdo->prepare(
            "INSERT INTO contact_messages (name, email, message, is_spam, spam_reason, ip)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $name, $email, $message,
            $spamReasons ? 1 : 0,
            $spamReasons ? mb_substr(implode('; ', $spamReasons), 0, 255) : null,
            $ip,
        ]);
    } catch (PDOException $e) {
        error_log('contact form (run migrate_contact_spam.sql): ' . $e->getMessage());
        if (!$spamReasons) {
            $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)")
                ->execute([$name, $email, $message]);
        }
    }
}
