-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 06, 2026 at 02:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `head_personnel_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `description`, `head_personnel_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Mathematics', 'Math', NULL, NULL, 'active', '2026-03-04 03:11:04', '2026-03-04 03:16:31'),
(2, 'Music, Arts, Physical Education, and Health', 'MAPEH', NULL, NULL, 'active', '2026-03-04 03:11:04', '2026-03-04 03:15:34'),
(3, 'Science', 'Sci', NULL, 11, 'active', '2026-03-04 03:11:04', '2026-03-04 05:46:47'),
(4, 'Technology and Livelihood Education', 'T.L.E', NULL, NULL, 'active', '2026-03-04 03:14:51', '2026-03-04 03:14:51'),
(5, 'English', 'ENG', NULL, NULL, 'active', '2026-03-04 03:17:07', '2026-03-04 03:17:07'),
(6, 'Aralin Panlipunan', 'A.P', NULL, 8, 'active', '2026-03-04 03:17:24', '2026-03-04 05:46:36'),
(7, 'Edukasyon sa Pagpapakatao', 'E.S.P', NULL, NULL, 'active', '2026-03-04 03:17:52', '2026-03-04 03:17:52'),
(8, 'Non-Teaching', 'NTP', NULL, 12, 'active', '2026-03-04 05:39:00', '2026-03-04 05:47:29'),
(9, 'Cabuco Extension', 'TMCNHS-Cab', NULL, 16, 'active', '2026-03-06 01:41:21', '2026-03-06 01:44:55');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `teacher_id`, `title`, `file_name`, `file_path`, `file_size`, `file_type`, `uploaded_at`) VALUES
(6, 12, 'Master File', '1772602117_MASTER_FILE_UPDATED__1_.xlsx', 'uploads/documents/1772602117_MASTER_FILE_UPDATED__1_.xlsx', 50196, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', '2026-03-04 05:28:37'),
(7, 12, 'Hugo Perez DTR', '1772602175_1772423295_HUGO_PEREZ_DTR.pdf', 'uploads/documents/1772602175_1772423295_HUGO_PEREZ_DTR.pdf', 44007, 'application/pdf', '2026-03-04 05:29:35');

-- --------------------------------------------------------

--
-- Table structure for table `leave_reports`
--

CREATE TABLE `leave_reports` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `absent_dates` text NOT NULL COMMENT 'Comma-separated YYYY-MM-DD dates',
  `days_absent` int(11) NOT NULL DEFAULT 0,
  `leave_type` varchar(50) NOT NULL DEFAULT 'Sick Leave' COMMENT 'Sick Leave | Vacation Leave | Emergency Leave | Maternity Leave | Other',
  `reason` text DEFAULT NULL,
  `scan_file_path` varchar(255) DEFAULT NULL,
  `scan_file_name` varchar(255) DEFAULT NULL,
  `filed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leave_reports`
--

INSERT INTO `leave_reports` (`id`, `teacher_id`, `last_name`, `first_name`, `absent_dates`, `days_absent`, `leave_type`, `reason`, `scan_file_path`, `scan_file_name`, `filed_at`) VALUES
(4, 12, 'Sindamon', 'Roviann', '2026-03-03', 1, 'Sick Leave', 'I got motion sickness', 'uploads/leave_reports/1772602264_leave_GENERAL_INTAKE_SHEET_2026.xlsx_-_Sheet1__1_.pdf', '1772602264_leave_GENERAL_INTAKE_SHEET_2026.xlsx_-_Sheet1__1_.pdf', '2026-03-04 05:31:04');

-- --------------------------------------------------------

--
-- Table structure for table `personnels`
--

CREATE TABLE `personnels` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` enum('','Jr.','Sr.','II','III','IV') DEFAULT '',
  `full_name` varchar(300) GENERATED ALWAYS AS (concat_ws(' ',`first_name`,nullif(`middle_name`,''),`last_name`,nullif(`suffix`,''))) VIRTUAL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `place_of_birth` varchar(200) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` char(11) DEFAULT NULL COMMENT 'PH mobile: exactly 11 digits, starts 09',
  `address` text DEFAULT NULL COMMENT 'Complete residence address + ZIP code',
  `profile_image` varchar(255) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employment_type` enum('Permanent','Temporary','Contractual','Part-time') DEFAULT 'Permanent',
  `date_hired` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `leave_days_allocation` int(11) DEFAULT 30,
  `tin_number` varchar(20) DEFAULT NULL COMMENT 'Tax Identification Number',
  `philhealth_number` varchar(20) DEFAULT NULL COMMENT '12-digit PhilHealth ID',
  `pagibig_number` varchar(20) DEFAULT NULL COMMENT 'Pag-IBIG / HDMF 12-digit MID',
  `sss_number` varchar(20) DEFAULT NULL COMMENT 'SSS (10 digits) or GSIS (11 digits)',
  `crn_number` varchar(20) DEFAULT NULL COMMENT 'Common Reference Number',
  `bp_number` varchar(20) DEFAULT NULL COMMENT 'Barangay / Business Permit Number',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `appointment_status` enum('Newly Hired','Regular','Transferee','Reinstated') DEFAULT 'Regular',
  `personnel_level` varchar(20) DEFAULT 'Teaching'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personnels`
--

INSERT INTO `personnels` (`id`, `user_id`, `last_name`, `first_name`, `middle_name`, `suffix`, `gender`, `civil_status`, `date_of_birth`, `place_of_birth`, `email`, `phone`, `address`, `profile_image`, `subject`, `department`, `employment_type`, `date_hired`, `status`, `leave_days_allocation`, `tin_number`, `philhealth_number`, `pagibig_number`, `sss_number`, `crn_number`, `bp_number`, `created_at`, `updated_at`, `appointment_status`, `personnel_level`) VALUES
(8, 11, 'Sarip', 'Aldikhen', 'Dangcogan', '', 'Male', 'Single', '2004-05-23', 'Cavite, Philippines', 'aldikhen.sarip2327@gmail.com', '09682176867', 'Goldcrest Ville, Brgy. San Agustin, TMC, Cavite, 4109', NULL, 'Master Teacher II', 'Science & Math', 'Permanent', '2020-02-02', 'active', 30, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04 02:15:48', '2026-03-04 02:15:48', 'Newly Hired', 'Teaching'),
(9, 12, 'Agrao', 'Jyrus', 'De Chavez', '', 'Male', 'Single', '2003-01-16', 'Cavite, Phillippines', 'agraojyrus123@gmail.com', '09231233312', 'Brgy. Hugo Perez', NULL, 'Teacher II', 'MAPEH', 'Permanent', '2020-02-01', 'active', 30, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04 02:22:22', '2026-03-04 02:22:22', 'Regular', 'Teaching'),
(10, 13, 'Macatangay', 'Joshua', 'Estriba', '', 'Male', 'Single', '2002-02-02', 'Cavite, Phillippines', 'joshuamacatangay@gmail.com', '09213123141', 'Purok 6 Asiongan, Brgy. Langkaan I,, Trece Martires City, Cavite, 4114', NULL, 'Teacher II', 'Science', 'Permanent', '2020-02-02', 'active', 30, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04 02:44:08', '2026-03-04 02:44:08', 'Newly Hired', 'Teaching'),
(11, 14, 'Tapiru', 'Jester Glen', 'Gelendez', '', 'Male', 'Single', '2004-07-10', 'Cavite, Phillippines', 'jestertapiru@gmail.com', '09882173122', 'Summer Field, Brgy. Osorio', NULL, 'Teacher III', 'Science', 'Permanent', '2020-02-02', 'active', 33, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04 03:00:36', '2026-03-05 02:26:53', 'Transferee', 'Teaching'),
(12, 15, 'Sidamon', 'Rovian', 'M.', '', 'Female', 'Married', NULL, '', 'roviannsidamon@tmcnhs.edu.ph', '09728388213', 'Trece Martires City, Cavite, 4109', NULL, 'Administrative Officer IV', 'Non-Teaching', 'Permanent', '2005-02-02', 'active', 30, '000-001-002-123', '1235-1251-2524', '2521-1268-2303', '129-4215-251', '281241215521', '912122', '2026-03-04 04:31:04', '2026-03-06 00:17:55', 'Regular', 'Non-Teaching'),
(13, 16, 'Luclucan', 'Alexander', 'P.', '', 'Male', 'Married', '1970-02-06', 'Cavite, Philippines', 'alexanderluclucan@tmcnhs.edu.ph', '09829183213', 'Brgy. Lapidario, Trece Maritires City, Cavite, 4109', NULL, 'Security Guard I', 'Non-Teaching', 'Permanent', '2010-05-05', 'active', 30, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04 05:52:16', '2026-03-04 06:21:42', 'Regular', 'Non-Teaching'),
(14, 17, 'Diloy', 'Cornello', 'P.', '', 'Male', 'Married', '1987-10-17', 'Cavite, Philippines', 'cornellodiloy@tmcnhs.edu.ph', '09827382919', 'Brgy. Luciano, Trece Martires City, Cavite, 4109', NULL, 'Administrative Aide I', 'Non-Teaching', 'Permanent', '2020-06-17', 'active', 30, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-04 05:59:12', '2026-03-04 06:21:54', 'Regular', 'Non-Teaching'),
(16, 19, 'Noceda', 'Victoria', 'V.', '', 'Female', 'Married', '1969-01-06', 'Cavite, Philippines', 'victoria.noceda@tmcnhs.edu.ph', '09217841253', 'idk', NULL, 'Head Teacher IV - OIC HEAD', 'Cabuco Extension', 'Permanent', '2001-02-27', 'active', 60, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-06 01:44:43', '2026-03-06 01:45:10', 'Regular', 'Teaching');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `level` enum('Teaching','Non-Teaching','Administrative','Managerial') NOT NULL DEFAULT 'Teaching',
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `title`, `level`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Master Teacher II', 'Teaching', NULL, 'active', '2026-03-04 03:11:04', '2026-03-04 03:11:04'),
(2, 'Teacher II', 'Teaching', NULL, 'active', '2026-03-04 03:11:04', '2026-03-04 03:11:04'),
(3, 'Teacher III', 'Teaching', NULL, 'active', '2026-03-04 03:11:04', '2026-03-04 03:11:04'),
(4, 'Department Head Teacher III', 'Teaching', NULL, 'active', '2026-03-04 03:20:03', '2026-03-04 03:20:03'),
(5, 'Teacher I', 'Teaching', NULL, 'active', '2026-03-04 03:25:45', '2026-03-04 03:33:53'),
(6, 'Master Teacher I', 'Teaching', NULL, 'active', '2026-03-04 03:25:58', '2026-03-04 03:25:58'),
(7, 'Teacher IV', 'Teaching', NULL, 'active', '2026-03-04 03:27:42', '2026-03-04 03:27:42'),
(8, 'Teacher V', 'Teaching', NULL, 'active', '2026-03-04 03:27:52', '2026-03-04 03:27:52'),
(9, 'Teacher VI', 'Teaching', NULL, 'active', '2026-03-04 03:27:58', '2026-03-04 03:27:58'),
(10, 'Teacher VII', 'Teaching', NULL, 'active', '2026-03-04 03:28:06', '2026-03-04 03:28:06'),
(11, 'Master Teacher III', 'Teaching', NULL, 'active', '2026-03-04 03:28:21', '2026-03-04 03:28:21'),
(12, 'Head Teacher III', 'Teaching', NULL, 'active', '2026-03-04 03:28:36', '2026-03-04 03:28:36'),
(13, 'Head Teacher III - OIC HEAD', 'Teaching', NULL, 'active', '2026-03-04 03:28:47', '2026-03-04 03:28:47'),
(18, 'Administrative Aide I', 'Non-Teaching', NULL, 'active', '2026-03-04 03:30:18', '2026-03-04 03:30:18'),
(19, 'Administrative Aide II', 'Non-Teaching', NULL, 'active', '2026-03-04 03:30:25', '2026-03-04 03:30:25'),
(20, 'Administrative Aide III', 'Non-Teaching', NULL, 'active', '2026-03-04 03:30:33', '2026-03-04 03:30:33'),
(21, 'Administrative Officer IV', 'Non-Teaching', NULL, 'active', '2026-03-04 03:30:54', '2026-03-04 03:30:54'),
(22, 'Administrative Aide IV', 'Non-Teaching', NULL, 'active', '2026-03-04 03:31:04', '2026-03-04 03:31:04'),
(23, 'Administrative Officer I', 'Non-Teaching', NULL, 'active', '2026-03-04 03:31:46', '2026-03-04 03:31:46'),
(24, 'Administrative Officer II', 'Non-Teaching', NULL, 'active', '2026-03-04 03:31:56', '2026-03-04 03:31:56'),
(25, 'Administrative Officer III', 'Non-Teaching', NULL, 'active', '2026-03-04 03:32:03', '2026-03-04 03:32:03'),
(26, 'Security Guard I', 'Non-Teaching', NULL, 'active', '2026-03-04 03:32:35', '2026-03-04 03:32:35'),
(27, 'Security Guard II', 'Non-Teaching', NULL, 'active', '2026-03-04 03:32:46', '2026-03-04 03:34:05'),
(28, 'Nurse I', 'Non-Teaching', NULL, 'active', '2026-03-04 03:33:15', '2026-03-04 03:33:15'),
(29, 'Nurse II', 'Non-Teaching', NULL, 'active', '2026-03-04 03:33:26', '2026-03-04 03:33:26'),
(30, 'LSB', 'Non-Teaching', NULL, 'active', '2026-03-04 03:34:33', '2026-03-04 03:34:33'),
(31, 'Principal IV', 'Non-Teaching', NULL, 'active', '2026-03-04 05:00:47', '2026-03-04 05:00:47'),
(33, 'Head Teacher IV - OIC HEAD', 'Teaching', NULL, 'active', '2026-03-06 01:43:50', '2026-03-06 01:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$38UDpoSDZV9QqGGMEgFSReSziwquoLrQnrG5AzohrNQP8d8pwIDYW', 'admin', '2026-02-27 01:43:41'),
(11, 'aldikhen123', '$2y$10$zTiHylTUqtf2rBKz1Sfj1uNjyr7DwFAe2gAo.ybrUXA4jQR.MbTqS', 'teacher', '2026-03-04 02:15:48'),
(12, 'jyrus123', '$2y$10$sB5LVHGmeIkngjdyz3dEueppNLaBx3QgKgVVDT5uBLCkQYL/ha2qO', 'teacher', '2026-03-04 02:22:22'),
(13, 'joshua123', '$2y$10$WV5fh69ISvjZ6r2ipr/ay.5JvhgVfj7HL86zqfkjvSCjAByjPWpDy', 'teacher', '2026-03-04 02:44:08'),
(14, 'jester123', '$2y$10$KFL76V6ZhWKyrYXfmZh5TudOf5tXfaO6GShrcSb.5z8h.iW4XD.Ou', 'teacher', '2026-03-04 03:00:36'),
(15, 'vian123', '$2y$10$e5IO/7.9DHiy52ZFR5h7tuVhJOQKVxBXnL.pxkWWdDnYZtnHLBhfC', 'teacher', '2026-03-04 04:31:04'),
(16, 'alexander123', '$2y$10$lzwg81AGynG/kD5oXWOb1OTLKZKUyDouUUIMa5.I/bY4FiuioFjpi', 'teacher', '2026-03-04 05:52:16'),
(17, 'cornello123', '$2y$10$H/wOaxJ4KakxB8acak1d0u260552UjNI/9JT5atnGKL.rRawPc./S', 'teacher', '2026-03-04 05:59:12'),
(19, 'victoria123', '$2y$10$97hRqqj1kAaG0u8nWCOH3.UE5SWZ2ah0zh03R0nY0zAKPQLD1Z3D6', 'teacher', '2026-03-06 01:44:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dept_name` (`name`),
  ADD KEY `fk_dept_head` (`head_personnel_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `leave_reports`
--
ALTER TABLE `leave_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_id` (`teacher_id`);

--
-- Indexes for table `personnels`
--
ALTER TABLE `personnels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_department` (`department`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `leave_reports`
--
ALTER TABLE `leave_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `personnels`
--
ALTER TABLE `personnels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_head` FOREIGN KEY (`head_personnel_id`) REFERENCES `personnels` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `personnels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_reports`
--
ALTER TABLE `leave_reports`
  ADD CONSTRAINT `leave_reports_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `personnels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `personnels`
--
ALTER TABLE `personnels`
  ADD CONSTRAINT `personnels_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
