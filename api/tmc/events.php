<?php
/**
 * TMC API — Events & QR Code Check-In
 * GET  /tmc-api/events             → list upcoming events
 * POST /tmc-api/events             → create event
 * POST /tmc-api/events/checkin     → QR code check-in
 * GET  /tmc-api/events/checkin?event_id=X → attendance list
 *
 * Supabase tables:
 * CREATE TABLE tmc_events (
 *   id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   title       text NOT NULL,
 *   description text,
 *   location    text,
 *   starts_at   timestamptz NOT NULL,
 *   ends_at     timestamptz,
 *   capacity    int,
 *   qr_code     text,   -- base64 QR or URL token
 *   active      boolean DEFAULT true,
 *   created_at  timestamptz DEFAULT now()
 * );
 *
 * CREATE TABLE tmc_event_checkins (
 *   id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   event_id   uuid REFERENCES tmc_events(id),
 *   member_id  uuid REFERENCES tmc_members(id),
 *   name       text,   -- for walk-ins without member account
 *   checked_in_at timestamptz DEFAULT now()
 * );
 */
require_once __DIR__ . '/_bootstrap.php';

$supabase = new SupabaseClient();
$method   = $_SERVER['REQUEST_METHOD'];

// Route: /events/checkin
$isCheckin = str_contains($_SERVER['REQUEST_URI'] ?? '', 'checkin');

// --- GET events or attendance ---
if ($method === 'GET') {
    if ($isCheckin) {
        $eventId = $_GET['event_id'] ?? null;
        if (!$eventId) respond(['error' => 'Missing event_id'], 400);
        $result = $supabase->select('tmc_event_checkins', "event_id=eq.{$eventId}&order=checked_in_at.asc");
    } else {
        $result = $supabase->select('tmc_events', 'active=eq.true&order=starts_at.asc');
    }
    respond($result['ok'] ? $result['data'] : ['error' => 'Fetch failed'], $result['ok'] ? 200 : 500);
}

// --- POST: create event ---
if ($method === 'POST' && !$isCheckin) {
    $body = requestBody();
    if (empty($body['title']) || empty($body['starts_at'])) {
        respond(['error' => 'title and starts_at are required'], 400);
    }

    // Generate a simple QR token (URL to check-in endpoint)
    $token = bin2hex(random_bytes(16));
    $qrUrl = ($_ENV['APP_URL'] ?? '') . "/tmc-api/events/checkin?token={$token}";

    $result = $supabase->insert('tmc_events', [
        'title'       => $body['title'],
        'description' => $body['description'] ?? null,
        'location'    => $body['location'] ?? null,
        'starts_at'   => $body['starts_at'],
        'ends_at'     => $body['ends_at'] ?? null,
        'capacity'    => $body['capacity'] ?? null,
        'qr_code'     => $qrUrl,
        'active'      => true,
    ]);

    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to create event'], $result['ok'] ? 201 : 500);
}

// --- POST: QR check-in ---
if ($method === 'POST' && $isCheckin) {
    $body = requestBody();
    if (empty($body['event_id'])) respond(['error' => 'Missing event_id'], 400);

    $result = $supabase->insert('tmc_event_checkins', [
        'event_id'  => $body['event_id'],
        'member_id' => $body['member_id'] ?? null,
        'name'      => $body['name'] ?? 'Walk-in Guest',
    ]);

    respond($result['ok'] ? ['message' => 'Checked in!', 'data' => $result['data']] : ['error' => 'Check-in failed'], $result['ok'] ? 201 : 500);
}

respond(['error' => 'Method not allowed'], 405);
