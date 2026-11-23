<?php
// Shared by report.php (the report form) and report_notify.php (the GitHub webhook that emails reporters).
// Defines functions only. No Composer or AWS SDK: AWS calls are signed here (Signature Version 4) with the
// Elastic Beanstalk instance role's credentials, read from the instance metadata service.
//
// A reporter's optional email address is encrypted (libsodium secretbox) with a key derived from the
// Secrets Manager secret tirules/report-notify-key, and stored in a hidden HTML comment in the GitHub issue.
// Only this site can decrypt it. Never rotate that secret: every stored address would become unreadable.

if (preg_match('#^/report_lib(\.php)?$#', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '')) {
    http_response_code(404);
    exit;
}

const REPORT_REPO = 'dangothemango/tirules';
const REPORT_AWS_REGION = 'us-east-1';
const REPORT_NOTIFY_SECRET_ID = 'tirules/report-notify-key';
const REPORT_NOTIFY_FROM = 'tirules2.com <noreply@tirules2.com>';
const REPORT_NOTIFY_MARKER = '/<!-- tirules-notify:v1:([A-Za-z0-9_-]+) -->/';

// The GitHub token, from whichever way Elastic Beanstalk exposes environment properties to PHP.
function report_token(): string
{
    $t = getenv('GITHUB_ISSUES_TOKEN') ?: ($_SERVER['GITHUB_ISSUES_TOKEN'] ?? '')
        ?: ($_ENV['GITHUB_ISSUES_TOKEN'] ?? '') ?: (get_cfg_var('GITHUB_ISSUES_TOKEN') ?: '');
    return trim((string) $t);
}

// One HTTP request. Returns [status, body]; status 0 if the request could not be made.
function report_http(string $method, string $url, array $headers, ?string $body = null, int $timeout = 10): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$resp === false ? 0 : $status, $resp === false ? '' : (string) $resp];
}

// GET from the GitHub API with the site's token. Returns the decoded JSON, or null.
function report_github_get(string $path): ?array
{
    $token = report_token();
    if ($token === '') {
        return null;
    }
    [$status, $body] = report_http('GET', 'https://api.github.com' . $path, [
        'Authorization: Bearer ' . $token,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: tirules2-report-form',
    ]);
    $data = $status === 200 ? json_decode($body, true) : null;
    return is_array($data) ? $data : null;
}

// AWS credentials: environment variables (local testing), else the instance role via IMDSv2.
function report_aws_credentials(): ?array
{
    static $creds = null;
    if ($creds !== null) {
        return $creds ?: null;
    }
    if (getenv('AWS_ACCESS_KEY_ID') && getenv('AWS_SECRET_ACCESS_KEY')) {
        return $creds = ['key' => getenv('AWS_ACCESS_KEY_ID'), 'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
            'token' => getenv('AWS_SESSION_TOKEN') ?: ''];
    }
    $imds = 'http://169.254.169.254/latest';
    [$s, $token] = report_http('PUT', $imds . '/api/token', ['X-aws-ec2-metadata-token-ttl-seconds: 300'], '', 2);
    if ($s !== 200) {
        $creds = [];
        return null;
    }
    $h = ['X-aws-ec2-metadata-token: ' . $token];
    [$s, $role] = report_http('GET', $imds . '/meta-data/iam/security-credentials/', $h, null, 2);
    $role = trim(strtok($role, "\n") ?: '');
    [$s2, $json] = $role !== '' ? report_http('GET', $imds . '/meta-data/iam/security-credentials/' . $role, $h, null, 2) : [0, ''];
    $c = $s === 200 && $s2 === 200 ? json_decode($json, true) : null;
    if (!isset($c['AccessKeyId'], $c['SecretAccessKey'])) {
        $creds = [];
        return null;
    }
    return $creds = ['key' => $c['AccessKeyId'], 'secret' => $c['SecretAccessKey'], 'token' => $c['Token'] ?? ''];
}

// A Signature Version 4 signed POST to an AWS JSON API. Returns [status, decoded body or null].
function report_aws_post(string $service, string $host, string $path, string $contentType, string $body, array $extra = []): array
{
    $c = report_aws_credentials();
    if ($c === null) {
        return [0, null];
    }
    $amzDate = gmdate('Ymd\THis\Z');
    $date = substr($amzDate, 0, 8);
    $headers = ['content-type' => $contentType, 'host' => $host, 'x-amz-date' => $amzDate] + $extra;
    if ($c['token'] !== '') {
        $headers['x-amz-security-token'] = $c['token'];
    }
    ksort($headers);
    $canonicalHeaders = '';
    foreach ($headers as $k => $v) {
        $canonicalHeaders .= $k . ':' . trim($v) . "\n";
    }
    $signed = implode(';', array_keys($headers));
    $canonical = "POST\n" . $path . "\n\n" . $canonicalHeaders . "\n" . $signed . "\n" . hash('sha256', $body);
    $scope = $date . '/' . REPORT_AWS_REGION . '/' . $service . '/aws4_request';
    $toSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonical);
    $k = hash_hmac('sha256', $date, 'AWS4' . $c['secret'], true);
    $k = hash_hmac('sha256', REPORT_AWS_REGION, $k, true);
    $k = hash_hmac('sha256', $service, $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);
    $auth = 'AWS4-HMAC-SHA256 Credential=' . $c['key'] . '/' . $scope . ', SignedHeaders=' . $signed
        . ', Signature=' . hash_hmac('sha256', $toSign, $k);
    $out = ['Authorization: ' . $auth];
    foreach ($headers as $hk => $hv) {
        if ($hk !== 'host') {
            $out[] = $hk . ': ' . $hv;
        }
    }
    [$status, $resp] = report_http('POST', 'https://' . $host . $path, $out, $body);
    return [$status, json_decode($resp, true)];
}

// The 32-byte encryption key, derived from the Secrets Manager secret. Null if it can't be read.
// For local testing only, REPORT_NOTIFY_DEV_SECRET stands in for the secret under php -S.
function report_notify_key(): ?string
{
    static $key = null;
    if ($key !== null) {
        return $key ?: null;
    }
    $secret = PHP_SAPI === 'cli-server' ? (string) getenv('REPORT_NOTIFY_DEV_SECRET') : '';
    if ($secret === '') {
        [$status, $data] = report_aws_post('secretsmanager', 'secretsmanager.' . REPORT_AWS_REGION . '.amazonaws.com', '/',
            'application/x-amz-json-1.1', json_encode(['SecretId' => REPORT_NOTIFY_SECRET_ID]),
            ['x-amz-target' => 'secretsmanager.GetSecretValue']);
        $secret = $status === 200 ? (string) ($data['SecretString'] ?? '') : '';
        if ($secret === '') {
            error_log('report_lib: could not read the notify secret (status ' . $status . ')');
        }
    }
    if ($secret === '') {
        $key = '';
        return null;
    }
    return $key = sodium_crypto_generichash($secret, 'tirules-report-notify-v1', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
}

function report_b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

// The hidden comment that carries an encrypted address, or null if the key is unavailable.
function report_notify_marker(string $email): ?string
{
    $key = report_notify_key();
    if ($key === null) {
        return null;
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return '<!-- tirules-notify:v1:' . report_b64url($nonce . sodium_crypto_secretbox($email, $nonce, $key)) . ' -->';
}

// The address stored in an issue body, or null.
function report_notify_email(string $issueBody): ?string
{
    if (!preg_match(REPORT_NOTIFY_MARKER, $issueBody, $m) || ($key = report_notify_key()) === null) {
        return null;
    }
    $raw = base64_decode(strtr($m[1], '-_', '+/'), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $email = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
    return $email !== false && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

// Send a plain-text email through SES. Returns true on success. Never logs the address or the text.
function report_send_email(string $to, string $subject, string $text): bool
{
    [$status] = report_aws_post('ses', 'email.' . REPORT_AWS_REGION . '.amazonaws.com', '/v2/email/outbound-emails',
        'application/json', json_encode([
            'FromEmailAddress' => REPORT_NOTIFY_FROM,
            'Destination' => ['ToAddresses' => [$to]],
            'Content' => ['Simple' => [
                'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                'Body' => ['Text' => ['Data' => $text, 'Charset' => 'UTF-8']],
            ]],
        ]));
    if ($status !== 200) {
        error_log('report_lib: SES SendEmail returned ' . $status);
    }
    return $status === 200;
}
