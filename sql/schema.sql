CREATE DATABASE IF NOT EXISTS abstrack
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE abstrack;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  group_name VARCHAR(10) DEFAULT 'G1',
  first_login_at TIMESTAMP NULL DEFAULT NULL,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('COURS', 'TD', 'TP', 'ENLIGNE') NOT NULL,
  teacher VARCHAR(100),
  room VARCHAR(50),
  day_of_week ENUM('Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi'),
  time_start TIME,
  time_end TIME,
  UNIQUE KEY unique_module_slot (name, type, day_of_week, time_start)
);

CREATE TABLE IF NOT EXISTS weeks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  week_number TINYINT NOT NULL,
  date_start DATE NOT NULL,
  date_end DATE NOT NULL,
  semester VARCHAR(20) DEFAULT '2025-S2',
  UNIQUE KEY unique_week_semester (semester, week_number)
);

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  module_id INT NOT NULL,
  week_id INT NOT NULL,
  status ENUM('present', 'absent', 'unknown') DEFAULT 'unknown',
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_record (student_id, module_id, week_id),
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_week FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password_hash VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
ON DUPLICATE KEY UPDATE teacher = VALUES(teacher), room = VALUES(room), time_end = VALUES(time_end);

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
ON DUPLICATE KEY UPDATE week_number = VALUES(week_number);
