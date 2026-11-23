<?php
// GitHub webhook (/report_notify) for the dangothemango/tirules repo, events "Issues" and "Issue comments".
// Emails a reporter who left an address on the report form (report.php) when their issue is closed, or
// when the repo owner comments on it.
//
// CloudFront doesn't forward GitHub's signature header to the origin, so the payload is not trusted:
// only the issue number and comment ID are taken from it, and the issue, its state and the comment are
// read back from the GitHub API. Only issues the report form created (same author as the token's user)
// are considered, and each close and each comment is emailed at most once per instance.
require __DIR__ . '/report_lib.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

// Always answer GitHub quickly and the same way; the outcome goes to the error log only.
function notify_done(string $why): never
{
    if ($why !== '') {
        error_log('report_notify: ' . $why);
    }
    http_response_code(204);
    exit;
}

// Claim a one-time key (per close or per comment) and cap sends at 30 an hour. False if already used.
function notify_claim(string $key): bool
{
    $dir = sys_get_temp_dir();
    $seen = $dir . '/tirules_notify_' . sha1($key);
    if (is_file($seen)) {
        return false;
    }
    $file = $dir . '/tirules_notify_rate.json';
    $now = time();
    $times = is_file($file) ? (json_decode((string) @file_get_contents($file), true) ?: []) : [];
    $times = array_values(array_filter($times, fn($t) => is_int($t) && $t > $now - 3600));
    if (count($times) >= 30) {
        return false;
    }
    $times[] = $now;
    @file_put_contents($file, json_encode($times), LOCK_EX);
    @touch($seen);
    return true;
}

$payload = json_decode((string) file_get_contents('php://input', false, null, 0, 1048576), true);
$number = (int) ($payload['issue']['number'] ?? 0);
$action = (string) ($payload['action'] ?? '');
if ($number <= 0) {
    notify_done('');   // ping or unrelated event
}
$isComment = isset($payload['comment']['id']) && $action === 'created';
if (!$isComment && $action !== 'closed') {
    notify_done('');
}

$issue = report_github_get('/repos/' . REPORT_REPO . '/issues/' . $number);
$me = report_github_get('/user');
if ($issue === null || $me === null) {
    notify_done('could not read issue #' . $number . ' or the token user from GitHub');
}
if (($issue['user']['login'] ?? '') !== ($me['login'] ?? '-')) {
    notify_done('');   // not created by the report form
}
$email = report_notify_email((string) ($issue['body'] ?? ''));
if ($email === null) {
    notify_done('');   // the reporter didn't ask for email (or it can't be read)
}

$title = (string) ($issue['title'] ?? 'your report');
$url = (string) ($issue['html_url'] ?? ('https://github.com/' . REPORT_REPO . '/issues/' . $number));
$owner = explode('/', REPORT_REPO)[0];
$footer = "\n\n" . $url . "\n\n--\nYou asked to be emailed about this report on tirules2.com. "
    . "You'll only hear about this report, when it is closed or when the site owner replies.\n";

if ($isComment) {
    $comment = report_github_get('/repos/' . REPORT_REPO . '/issues/comments/' . (int) $payload['comment']['id']);
    if ($comment === null || !str_ends_with((string) ($comment['issue_url'] ?? ''), '/issues/' . $number)) {
        notify_done('could not read comment ' . (int) $payload['comment']['id'] . ' on #' . $number);
    }
    if (strcasecmp((string) ($comment['user']['login'] ?? ''), $owner) !== 0) {
        notify_done('');   // only the owner's replies are sent
    }
    if (!notify_claim('comment:' . $comment['id'])) {
        notify_done('comment ' . $comment['id'] . ' already sent, or the hourly cap was reached');
    }
    $text = trim((string) ($comment['body'] ?? ''));
    if (mb_strlen($text) > 3000) {
        $text = mb_substr($text, 0, 3000) . '…';
    }
    $subject = 'Reply to your tirules2.com report #' . $number;
    $body = "There's a reply to your report \"" . $title . "\":\n\n" . $text . $footer;
} else {
    if (($issue['state'] ?? '') !== 'closed') {
        notify_done('');   // reopened since, or not really closed
    }
    if (!notify_claim('closed:' . $number . ':' . ($issue['closed_at'] ?? ''))) {
        notify_done('close of #' . $number . ' already sent, or the hourly cap was reached');
    }
    $fixed = ($issue['state_reason'] ?? '') === 'completed';
    $subject = 'Your tirules2.com report #' . $number . ($fixed ? ' has been fixed' : ' has been closed');
    $body = 'Your report "' . $title . '" ' . ($fixed
        ? 'has been marked as fixed. The site may take a little while to show the change.'
        : 'has been closed without a change. The issue may say why.') . $footer;
}

notify_done(report_send_email($email, $subject, $body) ? '' : 'sending failed for #' . $number);
