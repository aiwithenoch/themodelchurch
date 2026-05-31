<?php
/**
 * TMC API — Online Giving / Donation Tracking
 * GET  /tmc-api/giving             → list donations
 * GET  /tmc-api/giving?member=X    → donations by member
 * POST /tmc-api/giving             → record a donation
 *
 * Supabase table: tmc_donations
 * CREATE TABLE tmc_donations (
 *   id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   member_id   uuid REFERENCES tmc_members(id),
 *   amount      numeric(10,2) NOT NULL,
 *   fund        text DEFAULT 'General',
 *   method      text DEFAULT 'online',   -- online, cash, check, card
 *   notes       text,
 *   reference   text,
 *   donated_at  date DEFAULT CURRENT_DATE,
 *   created_at  timestamptz DEFAULT now()
 * );
 */
require_once __DIR__ . '/_bootstrap.php';

$supabase = new SupabaseClient();
$method   = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $memberId = $_GET['member'] ?? null;
    $fund     = $_GET['fund'] ?? null;
    $from     = $_GET['from'] ?? null;
    $to       = $_GET['to'] ?? date('Y-m-d');

    $filters = ['order=donated_at.desc', 'limit=100'];
    if ($memberId) $filters[] = "member_id=eq.{$memberId}";
    if ($fund)     $filters[] = "fund=eq.{$fund}";
    if ($from)     $filters[] = "donated_at=gte.{$from}";

    $result = $supabase->select('tmc_donations', implode('&', $filters));
    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to fetch donations'], $result['ok'] ? 200 : 500);
}

if ($method === 'POST') {
    $body = requestBody();

    if (empty($body['amount']) || !is_numeric($body['amount'])) {
        respond(['error' => 'Missing or invalid amount'], 400);
    }

    $result = $supabase->insert('tmc_donations', [
        'member_id'  => $body['member_id'] ?? null,
        'amount'     => (float)$body['amount'],
        'fund'       => $body['fund'] ?? 'General',
        'method'     => $body['method'] ?? 'online',
        'notes'      => $body['notes'] ?? null,
        'reference'  => $body['reference'] ?? uniqid('TMC-'),
        'donated_at' => $body['donated_at'] ?? date('Y-m-d'),
    ]);

    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to record donation'], $result['ok'] ? 201 : 500);
}

respond(['error' => 'Method not allowed'], 405);
