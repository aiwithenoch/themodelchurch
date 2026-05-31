<?php
/**
 * TMC API — Sermon & Media Library
 * GET  /tmc-api/media              → list all sermons/media
 * GET  /tmc-api/media?id=X         → single media item
 * GET  /tmc-api/media?series=X     → by series
 * POST /tmc-api/media              → add media item
 * PATCH /tmc-api/media?id=X        → update media item
 * DELETE /tmc-api/media?id=X       → remove media
 *
 * Supabase table: tmc_media
 * CREATE TABLE tmc_media (
 *   id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
 *   title         text NOT NULL,
 *   type          text DEFAULT 'sermon',  -- sermon, worship, podcast, video
 *   series        text,
 *   speaker       text,
 *   description   text,
 *   youtube_url   text,
 *   audio_url     text,
 *   thumbnail_url text,
 *   scripture     text,
 *   tags          text[],
 *   published     boolean DEFAULT true,
 *   preached_at   date,
 *   created_at    timestamptz DEFAULT now()
 * );
 */
require_once __DIR__ . '/_bootstrap.php';

$supabase = new SupabaseClient();
$method   = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id     = $_GET['id'] ?? null;
    $series = $_GET['series'] ?? null;
    $type   = $_GET['type'] ?? null;
    $limit  = (int)($_GET['limit'] ?? 20);

    $filters = ['published=eq.true', 'order=preached_at.desc', "limit={$limit}"];
    if ($id)     $filters = ["id=eq.{$id}"];
    if ($series) $filters[] = "series=eq.{$series}";
    if ($type)   $filters[] = "type=eq.{$type}";

    $result = $supabase->select('tmc_media', implode('&', $filters));
    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to fetch media'], $result['ok'] ? 200 : 500);
}

if ($method === 'POST') {
    $body = requestBody();
    if (empty($body['title'])) respond(['error' => 'title is required'], 400);

    $result = $supabase->insert('tmc_media', [
        'title'         => $body['title'],
        'type'          => $body['type'] ?? 'sermon',
        'series'        => $body['series'] ?? null,
        'speaker'       => $body['speaker'] ?? null,
        'description'   => $body['description'] ?? null,
        'youtube_url'   => $body['youtube_url'] ?? null,
        'audio_url'     => $body['audio_url'] ?? null,
        'thumbnail_url' => $body['thumbnail_url'] ?? null,
        'scripture'     => $body['scripture'] ?? null,
        'tags'          => $body['tags'] ?? [],
        'published'     => $body['published'] ?? true,
        'preached_at'   => $body['preached_at'] ?? date('Y-m-d'),
    ]);

    respond($result['ok'] ? $result['data'] : ['error' => 'Failed to add media'], $result['ok'] ? 201 : 500);
}

if ($method === 'PATCH') {
    $id = $_GET['id'] ?? null;
    if (!$id) respond(['error' => 'Missing media id'], 400);
    $result = $supabase->update('tmc_media', "id=eq.{$id}", requestBody());
    respond($result['ok'] ? $result['data'] : ['error' => 'Update failed'], $result['ok'] ? 200 : 500);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) respond(['error' => 'Missing media id'], 400);
    $result = $supabase->delete('tmc_media', "id=eq.{$id}");
    respond($result['ok'] ? ['deleted' => true] : ['error' => 'Delete failed'], $result['ok'] ? 200 : 500);
}

respond(['error' => 'Method not allowed'], 405);
