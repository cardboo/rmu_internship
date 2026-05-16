-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 11, 2026 at 12:15 PM
-- Server version: 8.0.31
-- PHP Version: 8.1.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `internship_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
CREATE TABLE IF NOT EXISTS `academic_years` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_acyear_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `name`, `start_date`, `end_date`, `is_current`, `is_archived`, `created_at`) VALUES
(1, '2025/2026', '2025-09-01', '2026-05-30', 1, 0, '2026-05-07 14:48:34');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dept_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `created_at`) VALUES
(1, 'ICT', 'ICT', '2026-05-05 01:02:10'),
(2, 'Marine Engineering', 'MAR', '2026-05-05 01:02:10'),
(3, 'Nautical Science', 'NAU', '2026-05-05 01:02:10'),
(4, 'Transport', 'TRP', '2026-05-05 01:02:10'),
(5, 'Electrical', 'ELE', '2026-05-05 01:02:10'),
(6, 'Mechanical', 'MEC', '2026-05-05 01:02:10'),
(7, 'Accounting', 'ACC', '2026-05-05 01:02:10');

-- --------------------------------------------------------

--
-- Table structure for table `email_settings`
--

DROP TABLE IF EXISTS `email_settings`;
CREATE TABLE IF NOT EXISTS `email_settings` (
  `setting_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `is_secret` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_settings`
--

INSERT INTO `email_settings` (`setting_key`, `setting_value`, `is_secret`, `updated_at`) VALUES
('enabled', '0', 0, '2026-05-07 14:57:34'),
('from_address', 'noreply@rmu.edu.gh', 0, '2026-05-07 14:57:34'),
('from_name', 'RMU Internship Portal', 0, '2026-05-07 14:57:34'),
('last_test_at', '', 0, '2026-05-07 14:57:34'),
('last_test_msg', '', 0, '2026-05-07 14:57:34'),
('smtp_host', 'localhost', 0, '2026-05-07 14:57:34'),
('smtp_pass', '', 1, '2026-05-07 14:57:34'),
('smtp_port', '1025', 0, '2026-05-07 14:57:34'),
('smtp_secure', 'none', 0, '2026-05-07 14:57:34'),
('smtp_user', '', 0, '2026-05-07 14:57:34'),
('test_to', '', 0, '2026-05-07 14:57:34');

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

DROP TABLE IF EXISTS `evaluations`;
CREATE TABLE IF NOT EXISTS `evaluations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `placement_id` int NOT NULL,
  `score_responsibility` tinyint NOT NULL DEFAULT '0',
  `score_reliability` tinyint NOT NULL DEFAULT '0',
  `score_knowledge` tinyint NOT NULL DEFAULT '0',
  `score_output` tinyint NOT NULL DEFAULT '0',
  `score_quality` tinyint NOT NULL DEFAULT '0',
  `score_punctuality` tinyint NOT NULL DEFAULT '0',
  `score_overall_perf` tinyint NOT NULL DEFAULT '0',
  `score_overall_conduct` tinyint NOT NULL DEFAULT '0',
  `total_score` tinyint NOT NULL DEFAULT '0',
  `supervisor_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `organization` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `supervisor_title` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_ip` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_eval_placement` (`placement_id`),
  KEY `idx_eval_total` (`total_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internship_submissions`
--

DROP TABLE IF EXISTS `internship_submissions`;
CREATE TABLE IF NOT EXISTS `internship_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `performance_scan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `weekly_logs_scan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `final_evaluation_scan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `submission_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('Submitted','Under Review','Graded') COLLATE utf8mb4_general_ci DEFAULT 'Submitted',
  `submission_status` enum('Pending','Submitted','Approved','Rejected') COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `academic_year_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internship_submissions`
--

INSERT INTO `internship_submissions` (`id`, `user_id`, `performance_scan`, `weekly_logs_scan`, `final_evaluation_scan`, `submission_date`, `status`, `submission_status`, `academic_year_id`) VALUES
(1, 3, 'uploads/evidence/1776971095_perf_Elikem.pdf', 'uploads/evidence/1776971095_logs_Elikem.pdf', NULL, '2026-04-23 19:04:55', 'Submitted', 'Pending', 1);

-- --------------------------------------------------------

--
-- Table structure for table `job_titles`
--

DROP TABLE IF EXISTS `job_titles`;
CREATE TABLE IF NOT EXISTS `job_titles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `applies_to` enum('staff','hod','secretary','any') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_job_title_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_titles`
--

INSERT INTO `job_titles` (`id`, `name`, `applies_to`, `created_at`) VALUES
(1, 'Head of Department', 'hod', '2026-05-05 16:46:42'),
(2, 'Acting Head of Department', 'hod', '2026-05-05 16:46:42'),
(3, 'Department Secretary', 'secretary', '2026-05-05 16:46:42'),
(4, 'Senior Lecturer', 'staff', '2026-05-05 16:46:42'),
(5, 'Lecturer', 'staff', '2026-05-05 16:46:42'),
(6, 'Assistant Lecturer', 'staff', '2026-05-05 16:46:42'),
(7, 'Professor', 'staff', '2026-05-05 16:46:42'),
(8, 'Associate Professor', 'staff', '2026-05-05 16:46:42'),
(9, 'Tutor', 'staff', '2026-05-05 16:46:42'),
(10, 'Industrial Liaison Officer', 'staff', '2026-05-05 16:46:42'),
(11, 'System Administrator', 'any', '2026-05-05 16:46:42');

-- --------------------------------------------------------

--
-- Table structure for table `letter_templates`
--

DROP TABLE IF EXISTS `letter_templates`;
CREATE TABLE IF NOT EXISTS `letter_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `department_id` int DEFAULT NULL,
  `academic_year_id` int DEFAULT NULL,
  `semester_id` int DEFAULT NULL,
  `body` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lt_dept` (`department_id`),
  KEY `idx_lt_year` (`academic_year_id`),
  KEY `idx_lt_sem` (`semester_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `letter_templates`
--

INSERT INTO `letter_templates` (`id`, `name`, `department_id`, `academic_year_id`, `semester_id`, `body`, `created_at`, `updated_at`) VALUES
(1, 'Default attachment letter', NULL, NULL, NULL, 'We wish to introduce the above-named student who is currently pursuing a program in {student_program} at this University. As part of the requirements for the award of a degree, students are required to undergo a {weeks}-week industrial attachment to gain practical experience.\r\n\r\nWe would be grateful if you could offer the student the opportunity to train with your organization from {start_date} to {end_date}.\r\n\r\nWe look forward to a favorable response from you.', '2026-05-07 14:55:41', '2026-05-07 19:50:30');

-- --------------------------------------------------------

--
-- Table structure for table `logbooks`
--

DROP TABLE IF EXISTS `logbooks`;
CREATE TABLE IF NOT EXISTS `logbooks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `placement_id` int DEFAULT NULL,
  `week_number` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `activities` text COLLATE utf8mb4_general_ci NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `staff_comment` text COLLATE utf8mb4_general_ci,
  `is_reviewed` tinyint(1) DEFAULT '0',
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `academic_year_id` int DEFAULT NULL,
  `student_remarks` text COLLATE utf8mb4_general_ci,
  `supervisor_remarks` text COLLATE utf8mb4_general_ci,
  `supervisor_signed_at` timestamp NULL DEFAULT NULL,
  `supervisor_signed_by_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supervisor_signed_by_status` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_submitted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logbooks`
--

INSERT INTO `logbooks` (`id`, `student_id`, `placement_id`, `week_number`, `start_date`, `end_date`, `activities`, `submission_date`, `staff_comment`, `is_reviewed`, `file_path`, `academic_year_id`, `student_remarks`, `supervisor_remarks`, `supervisor_signed_at`, `supervisor_signed_by_name`, `supervisor_signed_by_status`, `is_submitted`) VALUES
(1, 5, NULL, 4, '2026-04-27', '2026-05-01', 'ww', '2026-04-23 19:16:52', NULL, 0, 'uploads/logbooks/log_5_w4_1776971812.pdf', 1, NULL, NULL, NULL, NULL, NULL, 0),
(2, 66, 1, 1, '2026-06-01', '2026-06-05', '', '2026-05-07 15:52:11', NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL, 1),
(3, 67, 2, 1, '2026-06-01', '2026-06-05', '', '2026-05-08 20:57:50', NULL, 0, NULL, 1, 'rwgetjkjtuktk', NULL, NULL, NULL, NULL, 1),
(4, 68, 3, 1, '2026-06-01', '2026-06-05', '', '2026-05-10 16:11:10', NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL, 1),
(5, 65, 4, 1, '2026-06-01', '2026-06-05', '', '2026-05-11 11:05:55', NULL, 0, NULL, 1, 'hthyjuuj', NULL, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `logbook_days`
--

DROP TABLE IF EXISTS `logbook_days`;
CREATE TABLE IF NOT EXISTS `logbook_days` (
  `id` int NOT NULL AUTO_INCREMENT,
  `logbook_id` int NOT NULL,
  `day_label` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `day_date` date NOT NULL,
  `activities` text COLLATE utf8mb4_general_ci,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_lbd_logbook` (`logbook_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logbook_days`
--

INSERT INTO `logbook_days` (`id`, `logbook_id`, `day_label`, `day_date`, `activities`, `sort_order`) VALUES
(1, 2, 'Monday', '2026-06-01', 'rhtejt', 1),
(2, 2, 'Tuesday', '2026-06-02', 'fshdgtj', 2),
(3, 2, 'Wednesday', '2026-06-03', 'sfhrykgu', 3),
(4, 2, 'Thursday', '2026-06-04', 'fshgfjyfj', 4),
(5, 2, 'Friday', '2026-06-05', 'fshftjytk', 5),
(6, 3, 'Monday', '2026-06-01', 'rwghtr', 1),
(7, 3, 'Tuesday', '2026-06-02', 'wrghyjtu', 2),
(8, 3, 'Wednesday', '2026-06-03', 'rwgetjtyjeujyrgethyj', 3),
(9, 3, 'Thursday', '2026-06-04', 'rhethryjtuy', 4),
(10, 3, 'Friday', '2026-06-05', 'rwgerhrtyjyr', 5),
(16, 4, 'Monday', '2026-06-01', 'hryhbdfbsfzgdtnj', 1),
(17, 4, 'Tuesday', '2026-06-02', 'fzbgsfymgjm', 2),
(18, 4, 'Wednesday', '2026-06-03', 'fsbdgthfryj', 3),
(19, 4, 'Thursday', '2026-06-04', 'xavdsfssd', 4),
(20, 4, 'Friday', '2026-06-05', 'gufvgjbhkv', 5),
(21, 5, 'Monday', '2026-06-01', 'fhgrjtklul', 1),
(22, 5, 'Tuesday', '2026-06-02', 'dafghjtrgthrygfhjt', 2),
(23, 5, 'Wednesday', '2026-06-03', 'fghgrjtuk', 3),
(24, 5, 'Thursday', '2026-06-04', 'tutukukik', 4),
(25, 5, 'Friday', '2026-06-05', 'tjtukikll', 5);

-- --------------------------------------------------------

--
-- Table structure for table `placements`
--

DROP TABLE IF EXISTS `placements`;
CREATE TABLE IF NOT EXISTS `placements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `request_id` int DEFAULT NULL,
  `company_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `company_address` text COLLATE utf8mb4_general_ci NOT NULL,
  `company_department` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supervisor_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `supervisor_email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `supervisor_title` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supervisor_phone` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','completed','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `academic_year_id` int DEFAULT NULL,
  `semester_id` int DEFAULT NULL,
  `supervisor_token` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supervisor_token_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_supervisor_token` (`supervisor_token`),
  KEY `idx_p_student` (`student_id`),
  KEY `idx_p_request` (`request_id`),
  KEY `idx_p_year` (`academic_year_id`),
  KEY `idx_p_sem` (`semester_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `placements`
--

INSERT INTO `placements` (`id`, `student_id`, `request_id`, `company_name`, `company_address`, `company_department`, `supervisor_name`, `supervisor_email`, `supervisor_title`, `supervisor_phone`, `start_date`, `end_date`, `status`, `academic_year_id`, `semester_id`, `supervisor_token`, `supervisor_token_expires_at`, `created_at`, `updated_at`) VALUES
(1, 66, 5, 'Regional Maritime University', 'no.7 lomoko avenue, tesano', 'IT Unit', 'Ismail Abdulai-Saiku', 'isabdulaisaiku@gmail.com', 'IT Manager', '0209123986', '2026-06-01', '2026-07-01', 'active', 1, NULL, 'bdbba471c1f552d9c99ffb27b2a9fc5858b2bf362aee33a5e7c2f0b42d6e72b6', '2026-07-07 00:52:51', '2026-05-07 15:50:33', '2026-05-08 00:52:51'),
(2, 67, 6, 'ghana Maritime Authority', 'no.7 lomoko avenue, tesano', 'IT UNIT', 'Ismail Abdulai-Saiku', 'ismail@gmail.com', 'IT manager', '0209123987', '2026-06-01', '2026-07-10', 'active', 1, NULL, 'f248ce0130d9cdc1d9e5d85e7c7766319ad867ce8d299527459514744991cb90', '2026-07-07 20:56:12', '2026-05-08 20:56:12', '2026-05-08 20:56:12'),
(3, 68, 8, 'Nestle', 'no.7 lomoko avenue, tesano', 'IT UNIT', 'Ismail Abdulai-Saiku', 'isabdulaisaiku@gmail.com', 'IT Manager', '0209123986', '2026-06-01', '2026-07-03', 'active', 1, NULL, '1f62baaaf029bfaf30658428ca59e207caf05b1f0c63f0a6621605377a270133', '2026-07-09 16:04:56', '2026-05-10 16:04:56', '2026-05-10 16:04:56'),
(4, 65, 9, 'Coca Cola', 'no.7 lomoko avenue, tesano', 'IT UNIT', 'Mubarak Kuriba', 'ismailabdulaisaiku@gmail.com', 'IT manager', '0209123986', '2026-06-01', '2026-06-26', 'active', 1, NULL, NULL, NULL, '2026-05-11 10:59:24', '2026-05-11 10:59:24');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
CREATE TABLE IF NOT EXISTS `programs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dept_program` (`department_id`,`name`),
  KEY `idx_program_dept` (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `department_id`, `name`, `code`, `created_at`) VALUES
(1, 1, 'BSc. Information Technology', 'BIT', '2026-05-05 01:02:10'),
(2, 1, 'BSc. Computer Science', 'BCS', '2026-05-05 01:02:10'),
(3, 2, 'BSc. Marine Engineering', 'BME', '2026-05-05 01:02:10'),
(4, 3, 'BSc. Nautical Science', 'BNS', '2026-05-05 01:02:10'),
(5, 4, 'BSc. Port & Shipping Administration', 'BPS', '2026-05-05 01:02:10'),
(6, 5, 'BSc. Electrical & Electronic Engineering', 'BEE', '2026-05-05 01:02:10'),
(7, 6, 'BSc. Mechanical Engineering', 'BMT', '2026-05-05 01:02:10'),
(8, 7, 'BSc. Accounting', 'BAC', '2026-05-05 01:02:10');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

DROP TABLE IF EXISTS `requests`;
CREATE TABLE IF NOT EXISTS `requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `company_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `company_address` text COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `academic_year_id` int DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_general_ci,
  `request_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `student_id`, `company_name`, `company_address`, `start_date`, `end_date`, `status`, `academic_year_id`, `rejection_reason`, `request_date`) VALUES
(1, 3, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-04-21', '2026-05-29', 'approved', 1, NULL, '2026-04-07 07:55:13'),
(2, 31, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-06-11', '2026-07-31', 'approved', 1, NULL, '2026-04-11 17:37:51'),
(3, 5, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-01-12', '2026-02-27', 'pending', 1, NULL, '2026-04-23 18:59:47'),
(4, 3, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-06-16', '2026-08-21', 'approved', 1, NULL, '2026-04-23 19:00:51'),
(5, 66, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-06-01', '2026-07-01', 'approved', 1, NULL, '2026-05-06 23:03:27'),
(6, 67, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-06-01', '2026-07-10', 'approved', NULL, NULL, '2026-05-08 20:51:46'),
(7, 67, 'shippers authority', 'no.7 lomoko avenue', '2026-06-01', '2026-07-10', 'rejected', NULL, 'date clash with already approved letter', '2026-05-08 20:59:50'),
(8, 68, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-06-01', '2026-07-01', 'approved', NULL, NULL, '2026-05-10 16:01:31'),
(9, 65, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-06-01', '2026-06-26', 'approved', NULL, NULL, '2026-05-11 10:56:04');

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

DROP TABLE IF EXISTS `semesters`;
CREATE TABLE IF NOT EXISTS `semesters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `academic_year_id` int NOT NULL,
  `label` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_year_label` (`academic_year_id`,`label`),
  KEY `idx_sem_year` (`academic_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`id`, `academic_year_id`, `label`, `start_date`, `end_date`, `sort_order`) VALUES
(1, 1, 'Semester 1', '2025-09-01', '2025-12-15', 1),
(2, 1, 'Semester 2', '2026-01-15', '2026-05-30', 2);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('academic_year_name', '2025/2026'),
('sem1_end', '2025-12-15'),
('sem1_start', '2025-09-01'),
('sem2_end', '2026-05-30'),
('sem2_start', '2026-01-15'),
('semester_end', '2026-05-30'),
('semester_start', '2026-01-10');

-- --------------------------------------------------------

--
-- Table structure for table `student_registry`
--

DROP TABLE IF EXISTS `student_registry`;
CREATE TABLE IF NOT EXISTS `student_registry` (
  `index_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department_id` int NOT NULL,
  `program_id` int NOT NULL,
  `level` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gender` enum('Male','Female') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `year_admitted` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_claimed` tinyint(1) NOT NULL DEFAULT '0',
  `claimed_user_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `academic_year_id_admitted` int DEFAULT NULL,
  PRIMARY KEY (`index_number`),
  KEY `idx_reg_dept` (`department_id`),
  KEY `idx_reg_program` (`program_id`),
  KEY `idx_reg_user` (`claimed_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_registry`
--

INSERT INTO `student_registry` (`index_number`, `full_name`, `email`, `department_id`, `program_id`, `level`, `gender`, `date_of_birth`, `year_admitted`, `is_claimed`, `claimed_user_id`, `created_at`, `updated_at`, `academic_year_id_admitted`) VALUES
('BAC0002028', 'Jane Smith', 'janesmith@st.rmu.edu.gh', 7, 8, '200', 'Male', NULL, NULL, 1, 62, '2026-05-11 09:28:14', '2026-05-11 09:28:14', NULL),
('BAC0002031', 'Benjamin Kalu', 'b.kalu@st.rmu.edu.gh', 7, 8, '300', 'Male', NULL, NULL, 1, 55, '2026-05-11 10:20:48', '2026-05-11 10:20:48', NULL),
('BAC0002032', 'Gloria Dampare', 'g.dampare@st.rmu.edu.gh', 7, 8, '300', 'Male', NULL, NULL, 1, 56, '2026-05-11 10:19:03', '2026-05-11 10:19:03', NULL),
('BAC0002033', 'Daniel Lartey', 'd.lartey@st.rmu.edu.gh', 7, 8, '300', 'Male', NULL, NULL, 1, 57, '2026-05-11 10:19:59', '2026-05-11 10:19:59', NULL),
('BAC0002034', 'Priscilla Nunoo', 'p.nunoo@st.rmu.edu.gh', 7, 8, '300', 'Male', NULL, NULL, 1, 58, '2026-05-11 09:29:06', '2026-05-11 09:29:06', NULL),
('BAC0002035', 'Samuel Turkson', 's.turkson@st.rmu.edu.gh', 7, 8, '300', 'Male', NULL, NULL, 1, 59, '2026-05-07 15:56:22', '2026-05-07 15:56:22', NULL),
('BCS10010926', 'Prince Geraldo', 'prince.geraldo@st.rmu.edu.gh', 1, 2, '300', 'Male', '2005-05-22', '2024', 1, 66, '2026-05-06 22:53:20', '2026-05-11 09:16:21', 1),
('BCS10010927', 'Princess Geraldo', 'princess.geraldo@st.rmu.edu.gh', 1, 2, '300', 'Female', '2006-05-08', '2023', 1, 67, '2026-05-08 20:38:12', '2026-05-08 20:45:12', NULL),
('BEE0000229', 'John Doe', 'johndoe@st.rmu.edu.gh', 5, 6, '100', 'Male', NULL, NULL, 1, 61, '2026-05-11 10:24:35', '2026-05-11 10:24:35', NULL),
('BEE0002021', 'Kelvin Blankson', 'k.blankson@st.rmu.edu.gh', 5, 6, '300', 'Male', NULL, NULL, 1, 41, '2026-05-11 10:17:42', '2026-05-11 10:17:42', NULL),
('BEE0002023', 'Richard Quaye', 'r.quaye@st.rmu.edu.gh', 5, 6, '300', 'Male', NULL, NULL, 1, 43, '2026-05-11 09:29:44', '2026-05-11 09:29:44', NULL),
('BEE0002024', 'Paulina Arthur', 'p.arthur@st.rmu.edu.gh', 5, 6, '300', 'Male', NULL, NULL, 1, 44, '2026-05-11 10:18:22', '2026-05-11 10:18:22', NULL),
('BEE0002025', 'Charles Buckman', 'c.buckman@st.rmu.edu.gh', 5, 6, '300', 'Male', NULL, NULL, 1, 45, '2026-05-11 10:18:54', '2026-05-11 10:18:54', NULL),
('BIT0000127', 'David Tetteh', 'david.t@st.rmu.edu.gh', 1, 1, '300', 'Male', NULL, NULL, 1, 9, '2026-05-11 09:24:41', '2026-05-11 09:24:41', NULL),
('BIT0002001', 'John Doe', 'j.doe@st.rmu.edu.gh', 1, 1, '300', 'Male', NULL, NULL, 1, 13, '2026-05-05 22:17:25', '2026-05-11 09:16:21', 1),
('BIT0002002', 'Sarah Smith', 's.smith@st.rmu.edu.gh', 1, 1, '300', 'Male', NULL, NULL, 1, 14, '2026-05-05 22:17:25', '2026-05-11 09:16:21', 1),
('BIT0002003', 'Michael Koffi', 'm.koffi@st.rmu.edu.gh', 1, 1, '300', 'Male', NULL, NULL, 1, 15, '2026-05-05 22:17:25', '2026-05-11 09:16:21', 1),
('BIT0002004', 'Prince Boateng', 'p.boateng@st.rmu.edu.gh', 1, 1, '300', 'Male', NULL, NULL, 1, 16, '2026-05-05 22:17:25', '2026-05-11 09:16:21', 1),
('BIT0002005', 'Emelda Gyamfi', 'e.gyamfi@st.rmu.edu.gh', 1, 1, '300', 'Male', NULL, NULL, 1, 17, '2026-05-05 22:17:25', '2026-05-11 09:16:21', 1),
('BIT0002022', 'Monica Sackey', 'm.sackey@st.rmu.edu.gh', 1, 1, '300', 'Male', NULL, NULL, 1, 42, '2026-05-11 09:29:38', '2026-05-11 09:29:38', NULL),
('BIT0002129', 'John Doe', 'johndoe2@st.rmu.edu.gh', 1, 1, '100', 'Male', NULL, NULL, 1, 63, '2026-05-11 09:26:32', '2026-05-11 09:26:32', NULL),
('BIT1000627', 'John Mensah', 'student@st.rmu.edu.gh', 1, 1, '300', 'Male', NULL, NULL, 1, 3, '2026-05-11 10:22:58', '2026-05-11 10:22:58', NULL),
('BIT10010026', 'Jerimiah Johnson', 'ismail.abdulai-saiku@st.rmu.edu.gh', 1, 1, '400', 'Male', '2001-04-08', '2019', 0, NULL, '2026-05-11 10:49:44', '2026-05-11 10:49:44', NULL),
('BIT10010926', 'Ismail Abdulai-Saiku', 'ismail.abdulai-saiku@st.rmu.edu.gh', 1, 1, '300', 'Male', '2001-04-08', '2023', 1, 65, '2026-05-05 16:59:59', '2026-05-11 09:16:21', 1),
('BIT1046026', 'Joana Obeng', 'joana.obeng@st.rmu.edu.gh', 1, 1, '400', 'Female', '2001-02-10', '2023', 1, 68, '2026-05-10 15:53:58', '2026-05-10 15:58:16', NULL),
('BME0002006', 'Kofi Asante', 'k.asante@st.rmu.edu.gh', 2, 3, '300', 'Male', NULL, NULL, 1, 20, '2026-05-11 09:30:28', '2026-05-11 09:30:28', NULL),
('BME0002007', 'Blessing Udoh', 'b.udoh@st.rmu.edu.gh', 2, 3, '300', 'Male', NULL, NULL, 1, 21, '2026-05-11 09:27:07', '2026-05-11 09:27:07', NULL),
('BME0002008', 'David Lamptey', 'd.lamptey@st.rmu.edu.gh', 2, 3, '300', 'Male', NULL, NULL, 1, 22, '2026-05-11 10:18:02', '2026-05-11 10:18:02', NULL),
('BME0002009', 'Cynthia Appiah', 'c.appiah@st.rmu.edu.gh', 2, 3, '300', 'Male', NULL, NULL, 1, 23, '2026-05-11 10:19:47', '2026-05-11 10:19:47', NULL),
('BME0002010', 'Peter Osei', 'p.osei@st.rmu.edu.gh', 2, 3, '300', 'Male', NULL, NULL, 1, 24, '2026-05-11 09:29:19', '2026-05-11 09:29:19', NULL),
('BME0002026', 'Grace Osei', 'grace.o@st.rmu.edu.gh', 2, 3, '400', 'Male', NULL, NULL, 1, 8, '2026-05-11 09:26:03', '2026-05-11 09:26:03', NULL),
('BME0002027', 'Kwame Mensah', 'kwame.m@st.rmu.edu.gh', 2, 3, '300', 'Male', NULL, NULL, 1, 5, '2026-05-11 10:21:28', '2026-05-11 10:21:28', NULL),
('BMT0000128', 'John Doe', 'john.d@st.rmu.edu.gh', 6, 7, '200', 'Male', NULL, NULL, 1, 7, '2026-05-11 10:23:33', '2026-05-11 10:23:33', NULL),
('BMT0002026', 'Joshua Cobbina', 'j.cobbina@st.rmu.edu.gh', 6, 7, '300', 'Male', NULL, NULL, 1, 48, '2026-05-11 10:20:58', '2026-05-11 10:20:58', NULL),
('BMT0002027', 'Anita Forson', 'a.forson@st.rmu.edu.gh', 6, 7, '300', 'Male', NULL, NULL, 1, 49, '2026-05-11 10:17:53', '2026-05-11 10:17:53', NULL),
('BMT0002028', 'Alfred Hammond', 'a.hammond@st.rmu.edu.gh', 6, 7, '300', 'Male', NULL, NULL, 1, 50, '2026-05-11 09:30:09', '2026-05-11 09:30:09', NULL),
('BMT0002029', 'Nancy Mills', 'n.mills@st.rmu.edu.gh', 6, 7, '300', 'Male', NULL, NULL, 1, 51, '2026-05-11 10:21:37', '2026-05-11 10:21:37', NULL),
('BMT0002030', 'Bright Adu', 'b.adu@st.rmu.edu.gh', 6, 7, '300', 'Male', NULL, NULL, 1, 52, '2026-05-11 10:20:09', '2026-05-11 10:20:09', NULL),
('BNS0000126', 'Ama Serwaa', 'ama.s@st.rmu.edu.gh', 3, 4, '400', 'Male', NULL, NULL, 1, 6, '2026-05-11 09:29:32', '2026-05-11 09:29:32', NULL),
('BNS0002011', 'Kwame Nkrumah', 'k.nkrumah@st.rmu.edu.gh', 3, 4, '300', 'Male', NULL, NULL, 1, 27, '2026-05-11 10:21:10', '2026-05-11 10:21:10', NULL),
('BNS0002012', 'Ama Serwaa', 'a.serwaa@st.rmu.edu.gh', 3, 4, '300', 'Male', NULL, NULL, 1, 28, '2026-05-11 09:25:09', '2026-05-11 09:25:09', NULL),
('BNS0002013', 'Yaw Owusu', 'y.owusu@st.rmu.edu.gh', 3, 4, '300', 'Male', NULL, NULL, 1, 29, '2026-05-11 09:29:50', '2026-05-11 09:29:50', NULL),
('BNS0002014', 'Doris Baah', 'd.baah@st.rmu.edu.gh', 3, 4, '300', 'Male', NULL, NULL, 1, 30, '2026-05-11 09:30:19', '2026-05-11 09:30:19', NULL),
('BNS0002015', 'Emmanuel Adjei', 'e.adjei@st.rmu.edu.gh', 3, 4, '300', 'Male', NULL, NULL, 1, 31, '2026-05-11 09:29:59', '2026-05-11 09:29:59', NULL),
('BNS0002028', 'Jane Smith', 'janesmith2@st.rmu.edu.gh', 3, 4, '200', 'Male', NULL, NULL, 1, 64, '2026-05-11 09:27:37', '2026-05-11 09:27:37', NULL),
('BPS0002016', 'George Ansah', 'g.ansah@st.rmu.edu.gh', 4, 5, '300', 'Male', NULL, NULL, 1, 34, '2026-05-11 10:20:18', '2026-05-11 10:20:18', NULL),
('BPS0002017', 'Rita Mensah', 'r.mensah@st.rmu.edu.gh', 4, 5, '300', 'Male', NULL, NULL, 1, 35, '2026-05-11 09:28:55', '2026-05-11 09:28:55', NULL),
('BPS0002018', 'Francis Tetteh', 'f.tetteh@st.rmu.edu.gh', 4, 5, '300', 'Male', NULL, NULL, 1, 36, '2026-05-11 10:19:21', '2026-05-11 10:19:21', NULL),
('BPS0002019', 'Alice Koomson', 'a.koomson@st.rmu.edu.gh', 4, 5, '300', 'Male', NULL, NULL, 1, 37, '2026-05-11 10:17:33', '2026-05-11 10:17:33', NULL),
('BPS0002020', 'Stephen Attah', 's.attah@st.rmu.edu.gh', 4, 5, '300', 'Male', NULL, NULL, 1, 38, '2026-05-11 09:25:46', '2026-05-11 09:25:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_otps`
--

DROP TABLE IF EXISTS `supervisor_otps`;
CREATE TABLE IF NOT EXISTS `supervisor_otps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `placement_id` int NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `purpose` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_placement` (`placement_id`),
  KEY `idx_otp_purpose` (`placement_id`,`purpose`),
  KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_otps`
--

INSERT INTO `supervisor_otps` (`id`, `placement_id`, `email`, `purpose`, `code_hash`, `expires_at`, `consumed_at`, `created_at`) VALUES
(1, 4, 'ismailabdulaisaiku@gmail.com', 'evaluation', '$2y$10$/8yOBa6WZYeRhh8CyEHKvu/Fv3KVn/KZy0.fCg3mTW0oPmNNF5ZXG', '2026-05-11 11:47:18', '2026-05-11 11:40:04', '2026-05-11 11:32:18'),
(2, 4, 'ismailabdulaisaiku@gmail.com', 'evaluation', '$2y$10$NeR8hOjjNT5DFLv3amb.iucmsZBmtWI6z9yY94JO1zaU3azAFqLoG', '2026-05-11 11:55:04', NULL, '2026-05-11 11:40:04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `profile_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `index_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `role` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `profile_pic` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'default.png',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gender` enum('Male','Female') COLLATE utf8mb4_general_ci DEFAULT 'Male',
  `program` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'BSc. Information Technology',
  `level` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '300',
  `job_title` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'AG. HEAD OF DEPARTMENT – ICT',
  `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Department of ICT',
  `signature_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `archived_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `profile_path`, `index_number`, `email`, `password`, `must_change_password`, `role`, `profile_pic`, `created_at`, `gender`, `program`, `level`, `job_title`, `department`, `signature_path`, `is_archived`, `archived_at`) VALUES
(3, 'John Mensah', 'profile_3_1775589998.jpg', 'BIT1000627', 'student@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', 'profile_3_1775589998.jpg', '2026-01-14 11:08:32', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL, 0, NULL),
(4, 'Isaac K. Acheampong', NULL, NULL, 'hod@test.com', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'hod', NULL, '2026-01-14 11:08:32', 'Male', 'BSc. Information Technology', '', 'HOD ICT', 'ICT', 'sig_4_1768685146.png', 0, NULL),
(5, 'Kwame Mensah', 'profile_5_1776969776.jpg', 'BME0002027', 'kwame.m@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', 'profile_5_1776969776.jpg', '2026-03-10 21:37:31', 'Male', 'BSc. Marine Engineering', '300', NULL, 'Marine Engineering', NULL, 0, NULL),
(6, 'Ama Serwaa', NULL, 'BNS0000126', 'ama.s@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-03-10 21:37:31', 'Male', 'BSc. Nautical Science', '400', NULL, 'Nautical Science', NULL, 0, NULL),
(7, 'John Doe', NULL, 'BMT0000128', 'john.d@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-03-10 21:37:31', 'Male', 'BSc. Mechanical Engineering', '200', NULL, 'Mechanical', NULL, 0, NULL),
(8, 'Grace Osei', NULL, 'BME0002026', 'grace.o@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-03-10 21:37:31', 'Male', 'BSc. Marine Engineering', '400', NULL, 'Marine Engineering', NULL, 0, NULL),
(9, 'David Tetteh', NULL, 'BIT0000127', 'david.t@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-03-10 21:37:31', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL, 0, NULL),
(10, 'Kofi Owusu', NULL, NULL, 'admin@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'admin', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'AG. HEAD OF DEPARTMENT – ICT', 'Administration', NULL, 0, NULL),
(11, 'Dr. Isaac Mensah', NULL, NULL, 'hod.ict@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'hod', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of ICT Department', 'ICT', 'sig_11_1778000570.png', 0, NULL),
(12, 'Abigail Tetteh', NULL, NULL, 'sec.ict@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'secretary', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'ICT', NULL, 0, NULL),
(13, 'John Doe', NULL, 'BIT0002001', 'j.doe@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL, 0, NULL),
(14, 'Sarah Smith', NULL, 'BIT0002002', 's.smith@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL, 0, NULL),
(15, 'Michael Koffi', NULL, 'BIT0002003', 'm.koffi@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL, 0, NULL),
(16, 'Prince Boateng', NULL, 'BIT0002004', 'p.boateng@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL, 0, NULL),
(17, 'Emelda Gyamfi', NULL, 'BIT0002005', 'e.gyamfi@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL, 0, NULL),
(18, 'Ing. Robert Annan', NULL, NULL, 'hod.marine@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'hod', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Marine Engineering', 'Marine Engineering', NULL, 0, NULL),
(19, 'Mary Quansah', NULL, NULL, 'sec.marine@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'secretary', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Marine Engineering', NULL, 0, NULL),
(20, 'Kofi Asante', NULL, 'BME0002006', 'k.asante@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Marine Engineering', '300', NULL, 'Marine Engineering', NULL, 0, NULL),
(21, 'Blessing Udoh', NULL, 'BME0002007', 'b.udoh@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Marine Engineering', '300', NULL, 'Marine Engineering', NULL, 0, NULL),
(22, 'David Lamptey', NULL, 'BME0002008', 'd.lamptey@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Marine Engineering', '300', NULL, 'Marine Engineering', NULL, 0, NULL),
(23, 'Cynthia Appiah', NULL, 'BME0002009', 'c.appiah@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Marine Engineering', '300', NULL, 'Marine Engineering', NULL, 0, NULL),
(24, 'Peter Osei', NULL, 'BME0002010', 'p.osei@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Marine Engineering', '300', NULL, 'Marine Engineering', NULL, 0, NULL),
(25, 'Capt. Samuel Addo', NULL, NULL, 'hod.nautical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'hod', NULL, '2026-04-11 15:24:48', 'Male', NULL, NULL, 'Head of Department', 'Nautical Science', 'sig_25_1775929286.png', 0, NULL),
(26, 'Grace Anim', NULL, NULL, 'sec.nautical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'secretary', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Nautical Science', NULL, 0, NULL),
(27, 'Kwame Nkrumah', NULL, 'BNS0002011', 'k.nkrumah@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Nautical Science', '300', NULL, 'Nautical Science', NULL, 0, NULL),
(28, 'Ama Serwaa', NULL, 'BNS0002012', 'a.serwaa@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Nautical Science', '300', NULL, 'Nautical Science', NULL, 0, NULL),
(29, 'Yaw Owusu', NULL, 'BNS0002013', 'y.owusu@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Nautical Science', '300', NULL, 'Nautical Science', NULL, 0, NULL),
(30, 'Doris Baah', NULL, 'BNS0002014', 'd.baah@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Nautical Science', '300', NULL, 'Nautical Science', NULL, 0, NULL),
(31, 'Emmanuel Adjei', 'profile_31_1775929925.png', 'BNS0002015', 'e.adjei@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', 'profile_31_1775929925.png', '2026-04-11 15:24:48', 'Male', 'BSc. Nautical Science', '300', NULL, 'Nautical Science', NULL, 0, NULL),
(32, 'Mr. Felix Doku', NULL, NULL, 'hod.transport@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'hod', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Transport Department', 'Transport', NULL, 0, NULL),
(33, 'Joyce Darko', NULL, NULL, 'sec.transport@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'secretary', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Transport', NULL, 0, NULL),
(34, 'George Ansah', NULL, 'BPS0002016', 'g.ansah@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Port & Shipping Administration', '300', NULL, 'Transport', NULL, 0, NULL),
(35, 'Rita Mensah', NULL, 'BPS0002017', 'r.mensah@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Port & Shipping Administration', '300', NULL, 'Transport', NULL, 0, NULL),
(36, 'Francis Tetteh', NULL, 'BPS0002018', 'f.tetteh@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Port & Shipping Administration', '300', NULL, 'Transport', NULL, 0, NULL),
(37, 'Alice Koomson', NULL, 'BPS0002019', 'a.koomson@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Port & Shipping Administration', '300', NULL, 'Transport', NULL, 0, NULL),
(38, 'Stephen Attah', NULL, 'BPS0002020', 's.attah@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Port & Shipping Administration', '300', NULL, 'Transport', NULL, 0, NULL),
(39, 'Dr. Kwesi Pratt', NULL, NULL, 'hod.electrical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'hod', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Electrical Department', 'Electrical', NULL, 0, NULL),
(40, 'Linda Ofori', NULL, NULL, 'sec.electrical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'secretary', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Electrical', NULL, 0, NULL),
(41, 'Kelvin Blankson', NULL, 'BEE0002021', 'k.blankson@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Electrical & Electronic Engineering', '300', NULL, 'Electrical', NULL, 0, NULL),
(42, 'Monica Sackey', NULL, 'BIT0002022', 'm.sackey@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL, 0, NULL),
(43, 'Richard Quaye', NULL, 'BEE0002023', 'r.quaye@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Electrical & Electronic Engineering', '300', NULL, 'Electrical', NULL, 0, NULL),
(44, 'Paulina Arthur', NULL, 'BEE0002024', 'p.arthur@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Electrical & Electronic Engineering', '300', NULL, 'Electrical', NULL, 0, NULL),
(45, 'Charles Buckman', NULL, 'BEE0002025', 'c.buckman@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Electrical & Electronic Engineering', '300', NULL, 'Electrical', NULL, 0, NULL),
(46, 'Ing. Kofi Amoah', NULL, NULL, 'hod.mechanical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'hod', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Mechanical Department', 'Mechanical', NULL, 0, NULL),
(47, 'Theresa Kyei', NULL, NULL, 'sec.mechanical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'secretary', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Mechanical', NULL, 0, NULL),
(48, 'Joshua Cobbina', NULL, 'BMT0002026', 'j.cobbina@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Mechanical Engineering', '300', NULL, 'Mechanical', NULL, 0, NULL),
(49, 'Anita Forson', NULL, 'BMT0002027', 'a.forson@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Mechanical Engineering', '300', NULL, 'Mechanical', NULL, 0, NULL),
(50, 'Alfred Hammond', NULL, 'BMT0002028', 'a.hammond@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Mechanical Engineering', '300', NULL, 'Mechanical', NULL, 0, NULL),
(51, 'Nancy Mills', NULL, 'BMT0002029', 'n.mills@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Mechanical Engineering', '300', NULL, 'Mechanical', NULL, 0, NULL),
(52, 'Bright Adu', NULL, 'BMT0002030', 'b.adu@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Mechanical Engineering', '300', NULL, 'Mechanical', NULL, 0, NULL),
(53, 'Mrs. Patience Aggrey', NULL, NULL, 'hod.accounting@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'hod', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Accounting', 'Accounting', NULL, 0, NULL),
(54, 'Mercy Oteng', NULL, NULL, 'sec.accounting@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'secretary', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Accounting', NULL, 0, NULL),
(55, 'Benjamin Kalu', NULL, 'BAC0002031', 'b.kalu@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Accounting', '300', NULL, 'Accounting', NULL, 0, NULL),
(56, 'Gloria Dampare', NULL, 'BAC0002032', 'g.dampare@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Accounting', '300', NULL, 'Accounting', NULL, 0, NULL),
(57, 'Daniel Lartey', NULL, 'BAC0002033', 'd.lartey@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Accounting', '300', NULL, 'Accounting', NULL, 0, NULL),
(58, 'Priscilla Nunoo', NULL, 'BAC0002034', 'p.nunoo@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Accounting', '300', NULL, 'Accounting', NULL, 0, NULL),
(59, 'Samuel Turkson', NULL, 'BAC0002035', 's.turkson@st.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 0, 'student', NULL, '2026-04-11 15:24:48', 'Male', 'BSc. Accounting', '300', NULL, 'Accounting', NULL, 0, NULL),
(61, 'John Doe', NULL, 'BEE0000229', 'johndoe@st.rmu.edu.gh', '$2y$10$Zocsob4xrYYxXiRS9so2.upEXBs1xXTWzJIWh5Lz5UXpKEeocHHKK', 0, 'student', NULL, '2026-04-25 18:08:41', 'Male', 'BSc. Electrical & Electronic Engineering', '100', NULL, 'Electrical', NULL, 0, NULL),
(62, 'Jane Smith', NULL, 'BAC0002028', 'janesmith@st.rmu.edu.gh', '$2y$10$Zocsob4xrYYxXiRS9so2.upEXBs1xXTWzJIWh5Lz5UXpKEeocHHKK', 0, 'student', NULL, '2026-04-25 18:08:41', 'Male', 'BSc. Accounting', '200', NULL, 'Accounting', NULL, 0, NULL),
(63, 'John Doe', NULL, 'BIT0002129', 'johndoe2@st.rmu.edu.gh', '$2y$10$TUfn86VYw.zoX8VTC14OiuSG5EjNxoFBUQHG.VNRr1cKOsUiETKMe', 0, 'student', NULL, '2026-04-25 18:13:58', 'Male', 'BSc. Information Technology', '100', NULL, 'ICT', NULL, 0, NULL),
(64, 'Jane Smith', NULL, 'BNS0002028', 'janesmith2@st.rmu.edu.gh', '$2y$10$TUfn86VYw.zoX8VTC14OiuSG5EjNxoFBUQHG.VNRr1cKOsUiETKMe', 0, 'student', NULL, '2026-04-25 18:13:58', 'Male', 'BSc. Nautical Science', '200', NULL, 'Nautical Science', NULL, 0, NULL),
(65, 'Ismail Abdulai-Saiku', NULL, 'BIT10010926', 'ismail.abdulai-saiku@st.rmu.edu.gh', '$2y$10$W.P9.yoLEEhPUTl4nNi70udMSAMEvKslH7TBKjlFMLwn/yHr7eGr.', 0, 'student', 'default.png', '2026-05-06 12:07:06', 'Male', 'BSc. Information Technology', '300', 'AG. HEAD OF DEPARTMENT – ICT', 'ICT', NULL, 0, NULL),
(66, 'Prince Geraldo', NULL, 'BCS10010926', 'prince.geraldo@st.rmu.edu.gh', '$2y$10$nltjB29sNhk0dghvvx2R7OhkejvfCEZMPTJDsWmYmZhLY4upQp8t6', 0, 'student', 'default.png', '2026-05-06 23:02:31', 'Male', 'BSc. Computer Science', '300', NULL, 'ICT', NULL, 0, NULL),
(67, 'Princess Geraldo', NULL, 'BCS10010927', 'princess.geraldo@st.rmu.edu.gh', '$2y$10$L4aIq2DOlGbfNPWSO0Wi3ul2PLujo6zMESLPJL0VJDpsOzjX546EC', 0, 'student', 'default.png', '2026-05-08 20:45:12', 'Female', 'BSc. Computer Science', '300', 'AG. HEAD OF DEPARTMENT – ICT', 'ICT', NULL, 0, NULL),
(68, 'Joana Obeng', NULL, 'BIT1046026', 'joana.obeng@st.rmu.edu.gh', '$2y$10$9bkNrryJYocxkTHEZkKwEOSM5vh5/u9UdYoiVW0IVt8FS5gojEroO', 0, 'student', 'default.png', '2026-05-10 15:58:16', 'Female', 'BSc. Information Technology', '400', 'AG. HEAD OF DEPARTMENT – ICT', 'ICT', NULL, 0, NULL),
(69, 'ismail abdulai saiku', NULL, NULL, 'hod.dot@rmu.edu.gh', '$2y$10$kP7MYcu376VFTw6X84PYrueXQYI0UkDv..se8xOGfpeKUNLR.M4.G', 0, 'hod', 'default.png', '2026-05-10 16:22:25', 'Male', NULL, NULL, 'Head of Department', 'Transport', NULL, 0, NULL);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `fk_eval_placement` FOREIGN KEY (`placement_id`) REFERENCES `placements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `internship_submissions`
--
ALTER TABLE `internship_submissions`
  ADD CONSTRAINT `internship_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `letter_templates`
--
ALTER TABLE `letter_templates`
  ADD CONSTRAINT `fk_lt_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lt_sem` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lt_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logbooks`
--
ALTER TABLE `logbooks`
  ADD CONSTRAINT `logbooks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logbook_days`
--
ALTER TABLE `logbook_days`
  ADD CONSTRAINT `fk_lbd_logbook` FOREIGN KEY (`logbook_id`) REFERENCES `logbooks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `placements`
--
ALTER TABLE `placements`
  ADD CONSTRAINT `fk_p_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_p_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_p_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_p_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `fk_program_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `semesters`
--
ALTER TABLE `semesters`
  ADD CONSTRAINT `fk_sem_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_registry`
--
ALTER TABLE `student_registry`
  ADD CONSTRAINT `fk_reg_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_reg_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
  ADD CONSTRAINT `fk_reg_user` FOREIGN KEY (`claimed_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supervisor_otps`
--
ALTER TABLE `supervisor_otps`
  ADD CONSTRAINT `fk_otp_placement` FOREIGN KEY (`placement_id`) REFERENCES `placements` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
