<?php
/**
 * TMC API — SMS Notifications (via Twilio)
 * POST /tmc-api/sms     → send SMS to one or all members
 *
 * Body:
 *   { "to": "+1234567890", "message": "..." }        → single recipient
 *   { "to": "all", "message": "..." }                → blast to all active members
 *   { "to": ["...", "..."], "message": "..." }        → multiple numbers
 *
 * Requires: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER in .env
 *
 * Supabase table: tmc_sms_log
 * CREATE TABLE tmc_sms_log (
 *   id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   to_number   text,
 *   message     text,
 *   status      text,
 *   twilio_sid  text,
 *   sent_at     timestamptz DEFAULT now()
 * );
 */
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$body = requestBody();
if (empty($body['message'])) respond(['error' => 'message is required'], 400);
if (empty($body['to']))      respond(['error' => 'to is required'], 400);

$sid    = $_ENV['TWILIO_ACCOUNT_SID'] ?? getenv('TWILIO_ACCOUNT_SID') ?? '';
$token  = $_ENV['TWILIO_AUTH_TOKEN'] ?? getenv('TWILIO_AUTH_TOKEN') ?? '';
$from   = $_ENV['TWILIO_FROM_NUMBER'] ?? getenv('TWILIO_FROM_NUMBER') ?? '';

if (!$sid || !$token || !$from) {
    respond(['error' => 'Twilio credentials not configured'], 500);
}

$supabase = new SupabaseClient();
$message  = $body['message'];
$to       = $body['to'];

// Resolve recipients
if ($to === 'all') {
    $res = $supabase->select('tmc_members', 'active=eq.true&phone=not.is.null&select=phone');
    $numbers = array_column($res['data'] ?? [], 'phone');
} elseif (is_array($to)) {
    $numbers = $to;
} else {
    $numbers = [$to];
}

$results = [];
foreach ($numbers as $number) {
    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, "{$sid}:{$token}");
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'From' => $from,
        'To'   => $number,
        'Body' => $message,
    ]));

    $response = json_decode(curl_exec($ch), true);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = $code >= 200 && $code < 300;

    // Log to Supabase
    $supabase->insert('tmc_sms_log', [
        'to_number'  => $number,
        'message'    => $message,
        'status'     => $ok ? 'sent' : 'failed',
        'twilio_sid' => $response['sid'] ?? null,
    ]);

    $results[] = [
        'to'     => $number,
        'status' => $ok ? 'sent' : 'failed',
        'sid'    => $response['sid'] ?? null,
    ];
}

respond(['sent' => count($results), 'results' => $results]);
