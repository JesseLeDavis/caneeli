<?php
/**
 * The next series release: its name, when it drops, and helpers for the
 * countdown in the home page hero.
 *
 * To announce a different series later, change the constants below. The hero
 * shows the countdown until the release time, then "out now", and returns to
 * its usual headline SERIES_BAR_DAYS_AFTER days later.
 */

const SERIES_NAME       = 'Salmon Found a Friend';
const SERIES_TAGLINE    = 'No trout about it.';
const SERIES_RELEASE    = '2026-11-30 18:00';            // local time below
const SERIES_TIMEZONE   = 'America/Los_Angeles';          // 6pm Pacific
const SERIES_SHOP_URL   = '/pages/shop/';
const SERIES_BAR_DAYS_AFTER = 14;

function series_release_ts(): int {
    static $ts = null;
    return $ts ??= (new DateTimeImmutable(SERIES_RELEASE, new DateTimeZone(SERIES_TIMEZONE)))->getTimestamp();
}

/**
 * 'soon' before the drop, 'live' after it, 'over' once the hero goes back.
 * On the test site, ?drop=soon|live|over previews a state.
 */
function series_state(): string {
    if (defined('APP_ENV') && APP_ENV === 'test' && in_array($_GET['drop'] ?? '', ['soon', 'live', 'over'], true)) {
        return $_GET['drop'];
    }
    $now = time();
    if ($now < series_release_ts()) return 'soon';
    if ($now < series_release_ts() + SERIES_BAR_DAYS_AFTER * 86400) return 'live';
    return 'over';
}

/** "Nov 30, 6pm PT" style label for the release time. */
function series_release_label(): string {
    $d = (new DateTimeImmutable('@' . series_release_ts()))->setTimezone(new DateTimeZone(SERIES_TIMEZONE));
    $time = $d->format('i') === '00' ? $d->format('ga') : $d->format('g:ia');
    return $d->format('M j') . ', ' . $time . ' PT';
}

/** [days, hours, minutes, seconds] until the drop, never negative. */
function series_time_left(): array {
    $s = max(0, series_release_ts() - time());
    return [intdiv($s, 86400), intdiv($s % 86400, 3600), intdiv($s % 3600, 60), $s % 60];
}
