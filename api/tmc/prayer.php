<?php
/**
 * TMC API — Prayer Request Wall
 * GET    /tmc-api/prayer           → list active prayer requests
 * POST   /tmc-api/prayer           → submit prayer request
 * POST   /tmc-api/prayer/prayed    → mark "I prayed for this"
 * PATCH  /tmc-api/prayer?id=X      → update/answer prayer
 * DELETE /tmc-api/prayer?id=X      → remove prayer request
 *
 * Supabase table: tmc_prayer_requests
 * CREATE TABLE tmc_prayer_requests (
 *   id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   member_id    uuid REFERENCES tmc_members(id),
 *   name         text,        -- if anonymous or non-member
 *   request      text NOT NULL,
 *   category     text DEFAULT 'general',  -- general, healing, family, financial, etc.
 *   anonymous    boolean DEFAULT false,
 *   answered     boolean DEFAULT false,
 *   prayer_count int DEFAULT 0,
 *   active       boolean DEFAULT true,
 *   created_at   timestamptz DEFAULT now()
 * );
 *
 * CREATE TABLE tmc_prayer_support (
 *   id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   prayer_id  uuid REFERENCES tmc_prayer_requests(id),
 *   member_id  uuid REFERENCES tmc_members(id),
 *   prayed_at  timestamptz DEFAULT now()
 * );
 */
require_once __DIR__ . '/_bootstrap.php';

$supabase = new SupabaseClient();
$method   = $_SERVER['REQUEST_METHOD'];
$uri      = $_SERVER['REQUEST_URI'] ?? '';
$isPrayed = str_contains($uri, 'prayed');

if ($method === 'GET') {
    $category = $_GET['category'] ?? null;
    $answered = $_GET['answered'] ?? 'false';
    $filters  = ["active=eq.true", "answered=eq.{$answered}", 'order=created_at.desc', 'limit=50'];

    if ($category) $filters[] = "category=eq.{$category}";

    $result = $supabase->select('tmc_prayer_requests', implode('&', $filters));
    respond($result['ok'] ? $result['data'] : ['error' => 'Fetch failed'], $result['ok'] ? 200 : 500);
}

if ($method === 'POST' && !$isPrayed) {
    $body = requestBody();
    if (empty($body['request'])) respond(['error' => 'request text is required'], 400);

    $result = $supabase->insert('tmc_prayer_requests', [
        'member_id' => $body['member_id'] ?? null,
        'name'      => $body['anonymous'] ? 'Anonymous' : ($body['name'] ?? 'Anonymous'),
        'request'   => $body['request'],
        'category'  => $body['category'] ?? 'general',
        'anonymous' => (bool)($body['anonymous'] ?? false),
        'active'    => true,
    ]);

    respond($result['ok'] ? ['message' => 'Prayer request submitted!', 'data' => $result['data']] : ['error' => 'Submission failed'], $result['ok'] ? 201 : 500);
}

if ($method === 'POST' && $isPrayed) {
    $body = requestBody();
    if (empty($body['prayer_id'])) respond(['error' => 'prayer_id required'], 400);

    // Log the support
    $supabase->insert('tmc_prayer_support', [
        'prayer_id' => $body['prayer_id'],
        'member_id' => $body['member_id'] ?? null,
    ]);

    // Increment prayer_count
    $current = $supabase->select('tmc_prayer_requests', "id=eq.{$body['prayer_id']}&select=prayer_count");
    $count   = ($current['data'][0]['prayer_count'] ?? 0) + 1;
    $supabase->update('tmc_prayer_requests', "id=eq.{$body['prayer_id']}", ['prayer_count' => $count]);

    respond(['message' => 'Thank you for praying!', 'prayer_count' => $count]);
}

if ($method === 'PATCH') {
    $id = $_GET['id'] ?? null;
    if (!$id) respond(['error' => 'Missing id'], 400);
    $result = $supabase->update('tmc_prayer_requests', "id=eq.{$id}", requestBody());
    respond($result['ok'] ? $result['data'] : ['error' => 'Update failed'], $result['ok'] ? 200 : 500);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) respond(['error' => 'Missing id'], 400);
    // Soft delete
    $result = $supabase->update('tmc_prayer_requests', "id=eq.{$id}", ['active' => false]);
    respond($result['ok'] ? ['deleted' => true] : ['error' => 'Delete failed'], $result['ok'] ? 200 : 500);
}

respond(['error' => 'Method not allowed'], 405);
