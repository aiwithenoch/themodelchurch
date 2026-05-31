<?php
/**
 * TMC API — Small Groups
 * GET    /tmc-api/groups           → list all groups
 * GET    /tmc-api/groups?id=X      → single group + members
 * POST   /tmc-api/groups           → create group
 * POST   /tmc-api/groups/join      → join a group
 * PATCH  /tmc-api/groups?id=X      → update group
 * DELETE /tmc-api/groups/leave     → leave group
 *
 * Supabase tables:
 * CREATE TABLE tmc_small_groups (
 *   id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   name        text NOT NULL,
 *   description text,
 *   leader_id   uuid REFERENCES tmc_members(id),
 *   category    text,   -- Bible Study, Prayer, Youth, Women, Men, etc.
 *   location    text,
 *   meets_at    text,   -- e.g. "Tuesdays at 7pm"
 *   max_size    int DEFAULT 20,
 *   active      boolean DEFAULT true,
 *   created_at  timestamptz DEFAULT now()
 * );
 *
 * CREATE TABLE tmc_group_members (
 *   id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   group_id   uuid REFERENCES tmc_small_groups(id),
 *   member_id  uuid REFERENCES tmc_members(id),
 *   role       text DEFAULT 'member',   -- member, leader, co-leader
 *   joined_at  date DEFAULT CURRENT_DATE,
 *   UNIQUE(group_id, member_id)
 * );
 */
require_once __DIR__ . '/_bootstrap.php';

$supabase = new SupabaseClient();
$method   = $_SERVER['REQUEST_METHOD'];
$uri      = $_SERVER['REQUEST_URI'] ?? '';
$isJoin   = str_contains($uri, 'join');
$isLeave  = str_contains($uri, 'leave');

if ($method === 'GET') {
    $id       = $_GET['id'] ?? null;
    $category = $_GET['category'] ?? null;

    if ($id) {
        $result = $supabase->select('tmc_small_groups', "id=eq.{$id}");
        // Also get group members
        $members = $supabase->select('tmc_group_members', "group_id=eq.{$id}&order=joined_at.asc");
        respond(['group' => $result['data'] ?? [], 'members' => $members['data'] ?? []]);
    }

    $filters = ['active=eq.true', 'order=name.asc'];
    if ($category) $filters[] = "category=eq.{$category}";

    $result = $supabase->select('tmc_small_groups', implode('&', $filters));
    respond($result['ok'] ? $result['data'] : ['error' => 'Fetch failed'], $result['ok'] ? 200 : 500);
}

if ($method === 'POST' && !$isJoin && !$isLeave) {
    $body = requestBody();
    if (empty($body['name'])) respond(['error' => 'name is required'], 400);

    $result = $supabase->insert('tmc_small_groups', [
        'name'        => $body['name'],
        'description' => $body['description'] ?? null,
        'leader_id'   => $body['leader_id'] ?? null,
        'category'    => $body['category'] ?? null,
        'location'    => $body['location'] ?? null,
        'meets_at'    => $body['meets_at'] ?? null,
        'max_size'    => (int)($body['max_size'] ?? 20),
        'active'      => true,
    ]);

    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to create group'], $result['ok'] ? 201 : 500);
}

if ($method === 'POST' && $isJoin) {
    $body = requestBody();
    foreach (['group_id', 'member_id'] as $f) {
        if (empty($body[$f])) respond(['error' => "Missing {$f}"], 400);
    }

    $result = $supabase->insert('tmc_group_members', [
        'group_id'  => $body['group_id'],
        'member_id' => $body['member_id'],
        'role'      => $body['role'] ?? 'member',
    ]);

    respond($result['ok'] ? ['message' => 'Joined group!'] : ['error' => 'Join failed'], $result['ok'] ? 201 : 500);
}

if ($method === 'DELETE' && $isLeave) {
    $body = requestBody();
    if (empty($body['group_id']) || empty($body['member_id'])) {
        respond(['error' => 'group_id and member_id required'], 400);
    }
    $result = $supabase->delete('tmc_group_members', "group_id=eq.{$body['group_id']}&member_id=eq.{$body['member_id']}");
    respond($result['ok'] ? ['message' => 'Left group'] : ['error' => 'Leave failed'], $result['ok'] ? 200 : 500);
}

if ($method === 'PATCH') {
    $id = $_GET['id'] ?? null;
    if (!$id) respond(['error' => 'Missing id'], 400);
    $result = $supabase->update('tmc_small_groups', "id=eq.{$id}", requestBody());
    respond($result['ok'] ? $result['data'] : ['error' => 'Update failed'], $result['ok'] ? 200 : 500);
}

respond(['error' => 'Method not allowed'], 405);
