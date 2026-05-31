<?php
/**
 * TMC API — Member Portal (self-service profile)
 * GET    /tmc-api/members          → list members
 * GET    /tmc-api/members?id=X     → get single member
 * POST   /tmc-api/members          → create member record
 * PATCH  /tmc-api/members?id=X     → update member
 *
 * Supabase table: tmc_members
 * CREATE TABLE tmc_members (
 *   id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   first_name  text NOT NULL,
 *   last_name   text NOT NULL,
 *   email       text UNIQUE,
 *   phone       text,
 *   address     text,
 *   joined_at   date,
 *   photo_url   text,
 *   bio         text,
 *   active      boolean DEFAULT true,
 *   created_at  timestamptz DEFAULT now()
 * );
 */
require_once __DIR__ . '/_bootstrap.php';

$supabase = new SupabaseClient();
$method   = $_SERVER['REQUEST_METHOD'];

// GET
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id) {
        $result = $supabase->select('tmc_members', "id=eq.{$id}");
    } else {
        $limit  = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $result = $supabase->select('tmc_members', "active=eq.true&order=last_name.asc&limit={$limit}&offset={$offset}");
    }

    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to fetch members'], $result['ok'] ? 200 : 500);
}

// POST — create member
if ($method === 'POST') {
    $body = requestBody();
    $required = ['first_name', 'last_name'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            respond(['error' => "Missing required field: {$field}"], 400);
        }
    }

    $result = $supabase->insert('tmc_members', [
        'first_name' => $body['first_name'],
        'last_name'  => $body['last_name'],
        'email'      => $body['email'] ?? null,
        'phone'      => $body['phone'] ?? null,
        'address'    => $body['address'] ?? null,
        'joined_at'  => $body['joined_at'] ?? date('Y-m-d'),
        'photo_url'  => $body['photo_url'] ?? null,
        'bio'        => $body['bio'] ?? null,
        'active'     => true,
    ]);

    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to create member'], $result['ok'] ? 201 : 500);
}

// PATCH — update member
if ($method === 'PATCH') {
    $id = $_GET['id'] ?? null;
    if (!$id) respond(['error' => 'Missing member id'], 400);

    $body   = requestBody();
    $result = $supabase->update('tmc_members', "id=eq.{$id}", $body);

    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to update member'], $result['ok'] ? 200 : 500);
}

respond(['error' => 'Method not allowed'], 405);
