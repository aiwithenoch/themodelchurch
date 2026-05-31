<?php
/**
 * TMC API — Volunteer Scheduling
 * GET  /tmc-api/volunteers              → list volunteer opportunities
 * GET  /tmc-api/volunteers?member=X     → schedules for a member
 * POST /tmc-api/volunteers              → create opportunity
 * POST /tmc-api/volunteers/signup       → member signs up
 * PATCH /tmc-api/volunteers?id=X        → update opportunity
 *
 * Supabase tables:
 * CREATE TABLE tmc_volunteer_roles (
 *   id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   title       text NOT NULL,
 *   description text,
 *   team        text,   -- Worship, Ushering, Kids, Tech, etc.
 *   max_slots   int DEFAULT 10,
 *   active      boolean DEFAULT true,
 *   created_at  timestamptz DEFAULT now()
 * );
 *
 * CREATE TABLE tmc_volunteer_schedule (
 *   id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   role_id     uuid REFERENCES tmc_volunteer_roles(id),
 *   member_id   uuid REFERENCES tmc_members(id),
 *   event_id    uuid REFERENCES tmc_events(id),
 *   date        date NOT NULL,
 *   status      text DEFAULT 'confirmed',  -- confirmed, pending, cancelled
 *   notes       text,
 *   created_at  timestamptz DEFAULT now()
 * );
 */
require_once __DIR__ . '/_bootstrap.php';

$supabase = new SupabaseClient();
$method   = $_SERVER['REQUEST_METHOD'];
$isSignup = str_contains($_SERVER['REQUEST_URI'] ?? '', 'signup');

if ($method === 'GET') {
    if ($memberId = $_GET['member'] ?? null) {
        $result = $supabase->select('tmc_volunteer_schedule', "member_id=eq.{$memberId}&order=date.asc");
    } else {
        $team   = $_GET['team'] ?? null;
        $filter = 'active=eq.true&order=title.asc';
        if ($team) $filter .= "&team=eq.{$team}";
        $result = $supabase->select('tmc_volunteer_roles', $filter);
    }
    respond($result['ok'] ? $result['data'] : ['error' => 'Fetch failed'], $result['ok'] ? 200 : 500);
}

if ($method === 'POST' && !$isSignup) {
    $body = requestBody();
    if (empty($body['title'])) respond(['error' => 'title is required'], 400);

    $result = $supabase->insert('tmc_volunteer_roles', [
        'title'       => $body['title'],
        'description' => $body['description'] ?? null,
        'team'        => $body['team'] ?? null,
        'max_slots'   => (int)($body['max_slots'] ?? 10),
        'active'      => true,
    ]);

    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to create role'], $result['ok'] ? 201 : 500);
}

if ($method === 'POST' && $isSignup) {
    $body = requestBody();
    foreach (['role_id', 'member_id', 'date'] as $f) {
        if (empty($body[$f])) respond(['error' => "Missing {$f}"], 400);
    }

    $result = $supabase->insert('tmc_volunteer_schedule', [
        'role_id'   => $body['role_id'],
        'member_id' => $body['member_id'],
        'event_id'  => $body['event_id'] ?? null,
        'date'      => $body['date'],
        'status'    => 'confirmed',
        'notes'     => $body['notes'] ?? null,
    ]);

    respond($result['ok'] ? ['message' => 'Signed up!', 'data' => $result['data']] : ['error' => 'Signup failed'], $result['ok'] ? 201 : 500);
}

if ($method === 'PATCH') {
    $id = $_GET['id'] ?? null;
    if (!$id) respond(['error' => 'Missing id'], 400);
    $result = $supabase->update('tmc_volunteer_roles', "id=eq.{$id}", requestBody());
    respond($result['ok'] ? $result['data'] : ['error' => 'Update failed'], $result['ok'] ? 200 : 500);
}

respond(['error' => 'Method not allowed'], 405);
