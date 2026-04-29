-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2026 at 04:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
-- Table structure for table `internship_submissions`
--

CREATE TABLE `internship_submissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `performance_scan` varchar(255) DEFAULT NULL,
  `weekly_logs_scan` varchar(255) DEFAULT NULL,
  `final_evaluation_scan` varchar(255) DEFAULT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Submitted','Under Review','Graded') DEFAULT 'Submitted',
  `submission_status` enum('Pending','Submitted','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internship_submissions`
--

INSERT INTO `internship_submissions` (`id`, `user_id`, `performance_scan`, `weekly_logs_scan`, `final_evaluation_scan`, `submission_date`, `status`, `submission_status`) VALUES
(1, 3, 'uploads/evidence/1776971095_perf_Elikem.pdf', 'uploads/evidence/1776971095_logs_Elikem.pdf', NULL, '2026-04-23 19:04:55', 'Submitted', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `logbooks`
--

CREATE TABLE `logbooks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `activities` text NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `staff_comment` text DEFAULT NULL,
  `is_reviewed` tinyint(1) DEFAULT 0,
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logbooks`
--

INSERT INTO `logbooks` (`id`, `student_id`, `week_number`, `start_date`, `end_date`, `activities`, `submission_date`, `staff_comment`, `is_reviewed`, `file_path`) VALUES
(1, 5, 4, '2026-04-27', '2026-05-01', 'ww', '2026-04-23 19:16:52', NULL, 0, 'uploads/logbooks/log_5_w4_1776971812.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `company_address` text NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `student_id`, `company_name`, `company_address`, `start_date`, `end_date`, `status`, `rejection_reason`, `request_date`) VALUES
(1, 3, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-04-21', '2026-05-29', 'approved', NULL, '2026-04-07 07:55:13'),
(2, 31, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-06-11', '2026-07-31', 'approved', NULL, '2026-04-11 17:37:51'),
(3, 5, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-01-12', '2026-02-27', 'pending', NULL, '2026-04-23 18:59:47'),
(4, 3, 'TO WHOM IT MAY CONCERN', 'GENERAL SEARCH', '2026-06-16', '2026-08-21', 'approved', NULL, '2026-04-23 19:00:51');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
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
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `profile_path` varchar(255) DEFAULT NULL,
  `index_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `profile_pic` varchar(255) DEFAULT 'default.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gender` enum('Male','Female') DEFAULT 'Male',
  `program` varchar(100) DEFAULT 'BSc. Information Technology',
  `level` varchar(20) DEFAULT '300',
  `job_title` varchar(100) DEFAULT 'AG. HEAD OF DEPARTMENT – ICT',
  `department` varchar(100) DEFAULT 'Department of ICT',
  `signature_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `profile_path`, `index_number`, `email`, `password`, `role`, `profile_pic`, `created_at`, `gender`, `program`, `level`, `job_title`, `department`, `signature_path`) VALUES
(3, 'John Mensah', 'profile_3_1775589998.jpg', NULL, 'student@test.com', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-01-14 11:08:32', 'Male', 'BSc. Information Technology', '300', '', 'ICT', NULL),
(4, 'Isaac K. Acheampong', NULL, NULL, 'hod@test.com', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'hod', 'default.png', '2026-01-14 11:08:32', 'Male', 'BSc. Information Technology', '', 'HOD ICT', 'ICT', 'sig_4_1768685146.png'),
(5, 'Kwame Mensah', 'profile_5_1776969776.jpg', NULL, 'kwame.m@student.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-03-10 21:37:31', 'Male', 'BSc. Marine Engineering', '300', 'AG. HEAD OF DEPARTMENT – ICT', 'Marine Engineering', NULL),
(6, 'Ama Serwaa', NULL, NULL, 'ama.s@student.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-03-10 21:37:31', 'Male', 'BSc. Nautical Science', '400', 'AG. HEAD OF DEPARTMENT – ICT', 'Nautical Science', NULL),
(7, 'John Doe', NULL, NULL, 'john.d@student.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-03-10 21:37:31', 'Male', 'BSc. Computer Science', '200', 'AG. HEAD OF DEPARTMENT – ICT', 'Computer Science', NULL),
(8, 'Grace Osei', NULL, NULL, 'grace.o@student.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-03-10 21:37:31', 'Male', 'BSc. Marine Engineering', '400', 'AG. HEAD OF DEPARTMENT – ICT', 'Marine Engineering', NULL),
(9, 'David Tetteh', NULL, NULL, 'david.t@student.rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-03-10 21:37:31', 'Male', 'BSc. Port & Shipping Administration', '300', 'AG. HEAD OF DEPARTMENT – ICT', 'Logistics', NULL),
(10, 'Kofi Owusu', NULL, NULL, 'admin@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'admin', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'AG. HEAD OF DEPARTMENT – ICT', 'Administration', NULL),
(11, 'Dr. Isaac Mensah', NULL, NULL, 'hod.ict@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'hod', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of ICT Department', 'ICT', NULL),
(12, 'Abigail Tetteh', NULL, NULL, 'sec.ict@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'secretary', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'ICT', NULL),
(13, 'John Doe', NULL, 'BIT0002001', 'j.doe@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL),
(14, 'Sarah Smith', NULL, 'BIT0002002', 's.smith@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL),
(15, 'Michael Koffi', NULL, 'BIT0002003', 'm.koffi@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL),
(16, 'Prince Boateng', NULL, 'BIT0002004', 'p.boateng@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL),
(17, 'Emelda Gyamfi', NULL, 'BIT0002005', 'e.gyamfi@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'ICT', NULL),
(18, 'Ing. Robert Annan', NULL, NULL, 'hod.marine@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'hod', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Marine Engineering', 'Marine Engineering', NULL),
(19, 'Mary Quansah', NULL, NULL, 'sec.marine@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', '', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Marine Engineering', NULL),
(20, 'Kofi Asante', NULL, 'BME0002006', 'k.asante@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Marine Engineering', NULL),
(21, 'Blessing Udoh', NULL, 'BME0002007', 'b.udoh@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Marine Engineering', NULL),
(22, 'David Lamptey', NULL, 'BME0002008', 'd.lamptey@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Marine Engineering', NULL),
(23, 'Cynthia Appiah', NULL, 'BME0002009', 'c.appiah@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Marine Engineering', NULL),
(24, 'Peter Osei', NULL, 'BME0002010', 'p.osei@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Marine Engineering', NULL),
(25, 'Capt. Samuel Addo', NULL, NULL, 'hod.nautical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'hod', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Nautical Science', 'Nautical Science', 'sig_25_1775929286.png'),
(26, 'Grace Anim', NULL, NULL, 'sec.nautical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', '', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Nautical Science', NULL),
(27, 'Kwame Nkrumah', NULL, 'BNS0002011', 'k.nkrumah@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Nautical Science', NULL),
(28, 'Ama Serwaa', NULL, 'BNS0002012', 'a.serwaa@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Nautical Science', NULL),
(29, 'Yaw Owusu', NULL, 'BNS0002013', 'y.owusu@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Nautical Science', NULL),
(30, 'Doris Baah', NULL, 'BNS0002014', 'd.baah@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Nautical Science', NULL),
(31, 'Emmanuel Adjei', 'profile_31_1775929925.png', 'BNS0002015', 'e.adjei@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Nautical Science', NULL),
(32, 'Mr. Felix Doku', NULL, NULL, 'hod.transport@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'hod', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Transport Department', 'Transport', NULL),
(33, 'Joyce Darko', NULL, NULL, 'sec.transport@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', '', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Transport', NULL),
(34, 'George Ansah', NULL, 'BPS0002016', 'g.ansah@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Transport', NULL),
(35, 'Rita Mensah', NULL, 'BPS0002017', 'r.mensah@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Transport', NULL),
(36, 'Francis Tetteh', NULL, 'BPS0002018', 'f.tetteh@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Transport', NULL),
(37, 'Alice Koomson', NULL, 'BPS0002019', 'a.koomson@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Transport', NULL),
(38, 'Stephen Attah', NULL, 'BPS0002020', 's.attah@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Transport', NULL),
(39, 'Dr. Kwesi Pratt', NULL, NULL, 'hod.electrical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'hod', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Electrical Department', 'Electrical', NULL),
(40, 'Linda Ofori', NULL, NULL, 'sec.electrical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', '', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Electrical', NULL),
(41, 'Kelvin Blankson', NULL, 'BEE0002021', 'k.blankson@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Electrical', NULL),
(42, 'Monica Sackey', NULL, 'BEE0002022', 'm.sackey@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Electrical', NULL),
(43, 'Richard Quaye', NULL, 'BEE0002023', 'r.quaye@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Electrical', NULL),
(44, 'Paulina Arthur', NULL, 'BEE0002024', 'p.arthur@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Electrical', NULL),
(45, 'Charles Buckman', NULL, 'BEE0002025', 'c.buckman@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Electrical', NULL),
(46, 'Ing. Kofi Amoah', NULL, NULL, 'hod.mechanical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'hod', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Mechanical Department', 'Mechanical', NULL),
(47, 'Theresa Kyei', NULL, NULL, 'sec.mechanical@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', '', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Mechanical', NULL),
(48, 'Joshua Cobbina', NULL, 'BMT0002026', 'j.cobbina@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Mechanical', NULL),
(49, 'Anita Forson', NULL, 'BMT0002027', 'a.forson@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Mechanical', NULL),
(50, 'Alfred Hammond', NULL, 'BMT0002028', 'a.hammond@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Mechanical', NULL),
(51, 'Nancy Mills', NULL, 'BMT0002029', 'n.mills@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Mechanical', NULL),
(52, 'Bright Adu', NULL, 'BMT0002030', 'b.adu@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Mechanical', NULL),
(53, 'Mrs. Patience Aggrey', NULL, NULL, 'hod.accounting@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'hod', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Head of Accounting', 'Accounting', NULL),
(54, 'Mercy Oteng', NULL, NULL, 'sec.accounting@rmu.edu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', '', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', 'Department Secretary', 'Accounting', NULL),
(55, 'Benjamin Kalu', NULL, 'BAC0002031', 'b.kalu@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Accounting', NULL),
(56, 'Gloria Dampare', NULL, 'BAC0002032', 'g.dampare@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Accounting', NULL),
(57, 'Daniel Lartey', NULL, 'BAC0002033', 'd.lartey@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Accounting', NULL),
(58, 'Priscilla Nunoo', NULL, 'BAC0002034', 'p.nunoo@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Accounting', NULL),
(59, 'Samuel Turkson', NULL, 'BAC0002035', 's.turkson@st.edu.rmu.gh', '$2y$10$wJ/uE2tyDyItXEgFZ9UljOkr212d0Rk3a0/CrLYTd.taujS.RIuxO', 'student', 'default.png', '2026-04-11 15:24:48', 'Male', 'BSc. Information Technology', '300', NULL, 'Accounting', NULL),
(61, 'John Doe', NULL, NULL, 'johndoe@example.com', '$2y$10$Zocsob4xrYYxXiRS9so2.upEXBs1xXTWzJIWh5Lz5UXpKEeocHHKK', 'student', 'default.png', '2026-04-25 18:08:41', 'Male', '', '100', 'AG. HEAD OF DEPARTMENT – ICT', 'Computer Science', NULL),
(62, 'Jane Smith', NULL, NULL, 'janesmith@example.com', '$2y$10$Zocsob4xrYYxXiRS9so2.upEXBs1xXTWzJIWh5Lz5UXpKEeocHHKK', 'student', 'default.png', '2026-04-25 18:08:41', 'Male', '', '200', 'AG. HEAD OF DEPARTMENT – ICT', 'Mathematics', NULL),
(63, 'John Doe', NULL, NULL, 'johndoe2@example.com', '$2y$10$TUfn86VYw.zoX8VTC14OiuSG5EjNxoFBUQHG.VNRr1cKOsUiETKMe', 'student', 'default.png', '2026-04-25 18:13:58', 'Male', 'BSc. Information Technology', '100', 'AG. HEAD OF DEPARTMENT – ICT', 'ICT', NULL),
(64, 'Jane Smith', NULL, NULL, 'janesmith2@example.com', '$2y$10$TUfn86VYw.zoX8VTC14OiuSG5EjNxoFBUQHG.VNRr1cKOsUiETKMe', 'student', 'default.png', '2026-04-25 18:13:58', 'Male', 'BSc. Nautical Science', '200', 'AG. HEAD OF DEPARTMENT – ICT', 'Nautical Science', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `internship_submissions`
--
ALTER TABLE `internship_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `logbooks`
--
ALTER TABLE `logbooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `internship_submissions`
--
ALTER TABLE `internship_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `logbooks`
--
ALTER TABLE `logbooks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `internship_submissions`
--
ALTER TABLE `internship_submissions`
  ADD CONSTRAINT `internship_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logbooks`
--
ALTER TABLE `logbooks`
  ADD CONSTRAINT `logbooks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
