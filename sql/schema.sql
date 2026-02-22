CREATE TABLE IF NOT EXISTS students (
  id BIGSERIAL PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  group_name VARCHAR(10) DEFAULT 'G1',
  first_login_at TIMESTAMPTZ NULL,
  last_login_at TIMESTAMPTZ NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS modules (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type VARCHAR(20) NOT NULL CHECK (type IN ('COURS', 'TD', 'TP', 'ENLIGNE')),
  teacher VARCHAR(100),
  room VARCHAR(50),
  day_of_week VARCHAR(20) CHECK (day_of_week IN ('Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi')),
  time_start TIME,
  time_end TIME,
  CONSTRAINT unique_module_slot UNIQUE (name, type, day_of_week, time_start)
);

CREATE TABLE IF NOT EXISTS weeks (
  id BIGSERIAL PRIMARY KEY,
  week_number SMALLINT NOT NULL,
  date_start DATE NOT NULL,
  date_end DATE NOT NULL,
  semester VARCHAR(20) DEFAULT '2025-S2',
  CONSTRAINT unique_week_semester UNIQUE (semester, week_number)
);

CREATE TABLE IF NOT EXISTS attendance (
  id BIGSERIAL PRIMARY KEY,
  student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  module_id BIGINT NOT NULL REFERENCES modules(id) ON DELETE CASCADE,
  week_id BIGINT NOT NULL REFERENCES weeks(id) ON DELETE CASCADE,
  status VARCHAR(20) NOT NULL DEFAULT 'unknown' CHECK (status IN ('present', 'absent', 'unknown')),
  recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT unique_record UNIQUE (student_id, module_id, week_id)
);

CREATE TABLE IF NOT EXISTS teachers (
  id BIGSERIAL PRIMARY KEY,
  full_name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password_hash VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS admins (
  id BIGSERIAL PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login_at TIMESTAMPTZ NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO modules (name, type, teacher, room, day_of_week, time_start, time_end)
VALUES
('Securite Informatique', 'TD', 'Benhami', 'Salle cours', 'Samedi', '11:10:00', '12:40:00'),
('Systeme d''exploitation 2', 'TD', 'Haifaoui', 'Amphi Benbadis', 'Dimanche', '09:35:00', '11:05:00'),
('Systeme d''exploitation 2', 'TP', 'Tilmatine', 'lab 07', 'Samedi', '14:20:00', '15:50:00'),
('Recherche d''information', 'TD', 'BELATTAR', 'Salle 5 info', 'Samedi', '12:45:00', '14:15:00'),
('Donnees semi structurees', 'TP', 'Izountar', 'lab 1', 'Dimanche', '14:20:00', '15:50:00'),
('Business Intelligence', 'TD', 'Taibouni', 'Salle 1 info', 'Mercredi', '08:00:00', '09:30:00'),
('Redaction Scientifique', 'TD', 'Boufedji', '-', 'Mardi', '09:35:00', '11:05:00'),
('Projet', 'TD', 'Taibouni', 'Salle 3 info', 'Mercredi', '12:45:00', '14:15:00')
ON CONFLICT (name, type, day_of_week, time_start) DO UPDATE
SET teacher = EXCLUDED.teacher,
    room = EXCLUDED.room,
    time_end = EXCLUDED.time_end;

INSERT INTO weeks (week_number, date_start, date_end, semester)
VALUES
(1, '2025-09-06', '2025-09-12', '2025-S2'),
(2, '2025-09-13', '2025-09-19', '2025-S2'),
(3, '2025-09-20', '2025-09-26', '2025-S2'),
(4, '2025-09-27', '2025-10-03', '2025-S2'),
(5, '2025-10-04', '2025-10-10', '2025-S2'),
(6, '2025-10-11', '2025-10-17', '2025-S2'),
(7, '2025-10-18', '2025-10-24', '2025-S2'),
(8, '2025-10-25', '2025-10-31', '2025-S2'),
(9, '2025-11-01', '2025-11-07', '2025-S2'),
(10, '2025-11-08', '2025-11-14', '2025-S2'),
(11, '2025-11-15', '2025-11-21', '2025-S2'),
(12, '2025-11-22', '2025-11-28', '2025-S2'),
(13, '2025-11-29', '2025-12-05', '2025-S2'),
(14, '2025-12-06', '2025-12-12', '2025-S2')
ON CONFLICT (semester, week_number) DO UPDATE
SET date_start = EXCLUDED.date_start,
    date_end = EXCLUDED.date_end;
