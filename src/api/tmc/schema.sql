-- ============================================================
-- The Model Church — Supabase Database Schema
-- Run this in your Supabase SQL Editor to set up all tables
-- https://supabase.com/dashboard → your project → SQL Editor
-- ============================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================
-- 1. MEMBERS
-- ============================================================
CREATE TABLE IF NOT EXISTS tmc_members (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  first_name  text NOT NULL,
  last_name   text NOT NULL,
  email       text UNIQUE,
  phone       text,
  address     text,
  joined_at   date,
  photo_url   text,
  bio         text,
  active      boolean DEFAULT true,
  created_at  timestamptz DEFAULT now()
);

-- ============================================================
-- 2. DONATIONS / GIVING
-- ============================================================
CREATE TABLE IF NOT EXISTS tmc_donations (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  member_id   uuid REFERENCES tmc_members(id),
  amount      numeric(10,2) NOT NULL,
  fund        text DEFAULT 'General',
  method      text DEFAULT 'online',
  notes       text,
  reference   text,
  donated_at  date DEFAULT CURRENT_DATE,
  created_at  timestamptz DEFAULT now()
);

-- ============================================================
-- 3. EVENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS tmc_events (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  title       text NOT NULL,
  description text,
  location    text,
  starts_at   timestamptz NOT NULL,
  ends_at     timestamptz,
  capacity    int,
  qr_code     text,
  active      boolean DEFAULT true,
  created_at  timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS tmc_event_checkins (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  event_id      uuid REFERENCES tmc_events(id),
  member_id     uuid REFERENCES tmc_members(id),
  name          text,
  checked_in_at timestamptz DEFAULT now()
);

-- ============================================================
-- 4. SMS LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS tmc_sms_log (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  to_number   text,
  message     text,
  status      text,
  twilio_sid  text,
  sent_at     timestamptz DEFAULT now()
);

-- ============================================================
-- 5. MEDIA / SERMON LIBRARY
-- ============================================================
CREATE TABLE IF NOT EXISTS tmc_media (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  title         text NOT NULL,
  type          text DEFAULT 'sermon',
  series        text,
  speaker       text,
  description   text,
  youtube_url   text,
  audio_url     text,
  thumbnail_url text,
  scripture     text,
  tags          text[],
  published     boolean DEFAULT true,
  preached_at   date,
  created_at    timestamptz DEFAULT now()
);

-- ============================================================
-- 6. VOLUNTEERS
-- ============================================================
CREATE TABLE IF NOT EXISTS tmc_volunteer_roles (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  title       text NOT NULL,
  description text,
  team        text,
  max_slots   int DEFAULT 10,
  active      boolean DEFAULT true,
  created_at  timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS tmc_volunteer_schedule (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  role_id     uuid REFERENCES tmc_volunteer_roles(id),
  member_id   uuid REFERENCES tmc_members(id),
  event_id    uuid REFERENCES tmc_events(id),
  date        date NOT NULL,
  status      text DEFAULT 'confirmed',
  notes       text,
  created_at  timestamptz DEFAULT now()
);

-- ============================================================
-- 7. SMALL GROUPS
-- ============================================================
CREATE TABLE IF NOT EXISTS tmc_small_groups (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name        text NOT NULL,
  description text,
  leader_id   uuid REFERENCES tmc_members(id),
  category    text,
  location    text,
  meets_at    text,
  max_size    int DEFAULT 20,
  active      boolean DEFAULT true,
  created_at  timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS tmc_group_members (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  group_id   uuid REFERENCES tmc_small_groups(id),
  member_id  uuid REFERENCES tmc_members(id),
  role       text DEFAULT 'member',
  joined_at  date DEFAULT CURRENT_DATE,
  UNIQUE(group_id, member_id)
);

-- ============================================================
-- 8. PRAYER REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS tmc_prayer_requests (
  id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  member_id    uuid REFERENCES tmc_members(id),
  name         text,
  request      text NOT NULL,
  category     text DEFAULT 'general',
  anonymous    boolean DEFAULT false,
  answered     boolean DEFAULT false,
  prayer_count int DEFAULT 0,
  active       boolean DEFAULT true,
  created_at   timestamptz DEFAULT now()
);

CREATE TABLE IF NOT EXISTS tmc_prayer_support (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  prayer_id  uuid REFERENCES tmc_prayer_requests(id),
  member_id  uuid REFERENCES tmc_members(id),
  prayed_at  timestamptz DEFAULT now()
);

-- ============================================================
-- Row Level Security (RLS) — Enable on all tables
-- Adjust policies based on your auth strategy
-- ============================================================
ALTER TABLE tmc_members           ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_donations         ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_events            ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_event_checkins    ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_sms_log           ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_media             ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_volunteer_roles   ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_volunteer_schedule ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_small_groups      ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_group_members     ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_prayer_requests   ENABLE ROW LEVEL SECURITY;
ALTER TABLE tmc_prayer_support    ENABLE ROW LEVEL SECURITY;

-- Public read on media, events, groups, prayer (anon key can read)
CREATE POLICY "Public read media"    ON tmc_media           FOR SELECT USING (published = true);
CREATE POLICY "Public read events"   ON tmc_events          FOR SELECT USING (active = true);
CREATE POLICY "Public read groups"   ON tmc_small_groups    FOR SELECT USING (active = true);
CREATE POLICY "Public read prayer"   ON tmc_prayer_requests FOR SELECT USING (active = true);

-- Service key has full access (used server-side)
CREATE POLICY "Service full access members"    ON tmc_members           FOR ALL USING (true);
CREATE POLICY "Service full access donations"  ON tmc_donations         FOR ALL USING (true);
CREATE POLICY "Service full access checkins"   ON tmc_event_checkins    FOR ALL USING (true);
CREATE POLICY "Service full access sms"        ON tmc_sms_log           FOR ALL USING (true);
CREATE POLICY "Service full access vol_roles"  ON tmc_volunteer_roles   FOR ALL USING (true);
CREATE POLICY "Service full access vol_sched"  ON tmc_volunteer_schedule FOR ALL USING (true);
CREATE POLICY "Service full access grp_members" ON tmc_group_members    FOR ALL USING (true);
CREATE POLICY "Service full access prayer_sup" ON tmc_prayer_support    FOR ALL USING (true);
