CREATE DATABASE IF NOT EXISTS toodue_db;
USE toodue_db; 

/* Lists Table */
CREATE TABLE IF NOT EXISTS lists (
   id INT AUTO_INCREMENT PRIMARY KEY,
   name VARCHAR(255) NOT NULL UNIQUE,
   is_pinned TINYINT(1) DEFAULT 0,
   created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

/* Tasks Table */
CREATE TABLE IF NOT EXISTS tasks (
   id INT AUTO_INCREMENT PRIMARY KEY,
   list_id INT NOT NULL,
   task_name VARCHAR(255) NOT NULL,
   is_completed TINYINT(1) DEFAULT 0,
   created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

   FOREIGN KEY (list_id) REFERENCES lists(id) ON DELETE CASCADE
);

/* Lists Populated Data */
INSERT INTO lists (id, name, is_pinned)
VALUES
(1, 'WebDev', 1),
(2, 'Personal List', 0),
(3, 'Fundamentals of Research', 1);

/* Tasks Populated Data*/
INSERT INTO tasks (list_id, task_name, is_completed)
VALUES
(1, 'Lab 5', 1),
(1, 'Paper Revision', 0),
(1, 'Admin Dashboard Design', 0),
(1, 'Inquiry Form Design', 0),
(1, 'Presentation Preparation', 0),
(2, 'Buy Vitamins', 0),
(2, 'Schedule Dentist Appointment', 1),
(2, 'Wash Bed Sheets', 1),
(2, 'Clean Study Desk', 0),
(3, 'Revise Chapter 1 and 2', 0),
(3, 'Meeting for Chapter 3', 1);