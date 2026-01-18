-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1
-- 生成日期： 2026-01-05 12:39:24
-- 服务器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `fyp_management`
--

-- --------------------------------------------------------

--
-- 表的结构 `academic_year`
--

CREATE TABLE `academic_year` (
  `fyp_academicid` int(11) NOT NULL,
  `fyp_acdyear` varchar(12) DEFAULT NULL,
  `fyp_intake` varchar(12) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `academic_year`
--

INSERT INTO `academic_year` (`fyp_academicid`, `fyp_acdyear`, `fyp_intake`, `fyp_datecreated`) VALUES
(1, '2024/2025', 'MAR', '2025-12-08 13:30:52'),
(2, '2024/2025', 'JUN', '2025-12-08 13:30:52'),
(3, '2024/2025', 'SEP', '2025-12-08 13:30:52'),
(4, '2024/2025', 'DEC', '2025-12-08 13:30:52'),
(5, '2025/2026', 'MAR', '2025-12-08 13:30:52'),
(6, '2025/2026', 'JUN', '2025-12-08 13:30:52'),
(7, '2025/2026', 'SEP', '2025-12-08 13:30:52'),
(8, '2025/2026', 'DEC', '2025-12-08 13:30:52'),
(9, '2026/2027', 'MAR', '2025-12-08 13:30:52'),
(10, '2026/2027', 'JUN', '2025-12-08 13:30:52');

-- --------------------------------------------------------

--
-- 表的结构 `announcement`
--

CREATE TABLE `announcement` (
  `fyp_annouceid` int(11) NOT NULL,
  `fyp_supervisorid` varchar(12) DEFAULT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_subject` varchar(56) DEFAULT NULL,
  `fyp_description` varchar(120) DEFAULT NULL,
  `fyp_receiver` varchar(50) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `announcement`
--

INSERT INTO `announcement` (`fyp_annouceid`, `fyp_supervisorid`, `fyp_academicid`, `fyp_subject`, `fyp_description`, `fyp_receiver`, `fyp_datecreated`) VALUES
(6, 'Dr. Xavier', NULL, 'cepat cepat la capat cepat cpt', '123', 'My Supervisees', '2025-12-15 08:07:19'),
(7, 'Dr. Xavier', NULL, 'cepat please', '12345', 'Project: click this', '2025-12-15 08:07:34'),
(8, 'Dr. Xavier', NULL, 'latter i will check', 'come in my room', 'My Supervisees', '2025-12-16 07:32:32'),
(9, 'Dr. Xavier', NULL, 'i need to check your progress', 'lai lail lai', 'Project: click this', '2025-12-16 07:33:13'),
(10, 'Dr. Xavier', NULL, 'fast fast complete', '1234', 'My Supervisees', '2025-12-16 07:33:36'),
(11, 'Dr. Xavier', NULL, 'chong zi xuan', '124sadasdas ', 'Project: click this', '2025-12-16 10:42:29'),
(12, 'Dr. Victor', NULL, 'i need to check your progress', 'hi', 'Project: table design', '2025-12-19 05:15:28');

-- --------------------------------------------------------

--
-- 表的结构 `appointment_meeting`
--

CREATE TABLE `appointment_meeting` (
  `fyp_appointmentid` int(11) NOT NULL,
  `fyp_studid` varchar(12) NOT NULL,
  `fyp_pairingid` int(11) DEFAULT NULL,
  `fyp_scheduleid` int(11) NOT NULL,
  `fyp_supervisorid` int(11) NOT NULL,
  `fyp_status` enum('Pending','Approved','Rejected','Completed','Cancelled') DEFAULT 'Pending',
  `fyp_reason` text DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `appointment_meeting`
--

INSERT INTO `appointment_meeting` (`fyp_appointmentid`, `fyp_studid`, `fyp_pairingid`, `fyp_scheduleid`, `fyp_supervisorid`, `fyp_status`, `fyp_reason`, `fyp_datecreated`) VALUES
(2, 'TP001', NULL, 2, 1, '', 'help', '2025-12-23 14:01:58');

-- --------------------------------------------------------

--
-- 表的结构 `assessment_criteria`
--

CREATE TABLE `assessment_criteria` (
  `fyp_assessmentcriteriaid` int(11) NOT NULL,
  `fyp_assessmentcriterianame` varchar(56) DEFAULT NULL,
  `fyp_description` varchar(500) DEFAULT NULL,
  `fyp_min` int(11) DEFAULT NULL,
  `fyp_max` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `assessment_criteria`
--

INSERT INTO `assessment_criteria` (`fyp_assessmentcriteriaid`, `fyp_assessmentcriterianame`, `fyp_description`, `fyp_min`, `fyp_max`) VALUES
(1, 'Poor', 'Below exp', 0, 39),
(2, 'Pass', 'Meets exp', 40, 59),
(3, 'Credit', 'Good', 60, 74),
(4, 'Distinction', 'Excellent', 75, 100),
(5, 'Fail', 'No sub', 0, 0),
(6, 'Weak', 'Weak', 1, 30),
(7, 'Moderate', 'Moderate', 31, 60),
(8, 'Good', 'Good', 61, 80),
(9, 'Excellent', 'Excellent', 81, 100),
(10, 'Perfect', 'Perfect', 100, 100);

-- --------------------------------------------------------

--
-- 表的结构 `assessment_items`
--

CREATE TABLE `assessment_items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `item_weightage` int(11) NOT NULL,
  `graded_by` enum('Supervisor','Moderator','Coordinator') NOT NULL,
  `semester` varchar(20) DEFAULT 'FYP1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `assignment`
--

CREATE TABLE `assignment` (
  `fyp_assignmentid` int(11) NOT NULL,
  `fyp_supervisorid` int(11) NOT NULL,
  `fyp_title` varchar(255) NOT NULL,
  `fyp_description` text DEFAULT NULL,
  `fyp_deadline` datetime DEFAULT NULL,
  `fyp_weightage` int(11) DEFAULT 0,
  `fyp_assignment_type` enum('Individual','Group') DEFAULT 'Individual',
  `fyp_status` enum('Active','Closed') DEFAULT 'Active',
  `fyp_target_id` varchar(50) DEFAULT 'ALL',
  `fyp_datecreated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `assignment`
--

INSERT INTO `assignment` (`fyp_assignmentid`, `fyp_supervisorid`, `fyp_title`, `fyp_description`, `fyp_deadline`, `fyp_weightage`, `fyp_assignment_type`, `fyp_status`, `fyp_target_id`, `fyp_datecreated`) VALUES
(1, 1, 'proposal', 'submit on time', '2025-12-26 23:59:00', 0, 'Group', 'Active', NULL, '2025-12-24 09:42:33'),
(2, 1, 'proposal 1', 'submit as fast', '2025-12-25 23:41:00', 0, 'Group', 'Active', '2', '2025-12-24 10:31:40'),
(3, 1, 'Project Proposal Submission', '12314214124', '2026-01-15 17:19:00', 10, 'Individual', 'Active', 'TP007', '2026-01-04 10:19:55');

-- --------------------------------------------------------

--
-- 表的结构 `assignment_submission`
--

CREATE TABLE `assignment_submission` (
  `fyp_submissionid` int(11) NOT NULL,
  `fyp_assignmentid` int(11) NOT NULL,
  `fyp_studid` varchar(12) NOT NULL,
  `fyp_marks` int(11) DEFAULT 0,
  `fyp_feedback` text DEFAULT NULL,
  `fyp_graded_date` datetime DEFAULT NULL,
  `fyp_submission_status` enum('Not Turned In','Viewed','Turned In','Late Turned In','Graded','Need Revision','Resubmitted') DEFAULT 'Not Turned In',
  `fyp_submission_date` datetime DEFAULT NULL,
  `fyp_submitted_file` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `assignment_submission`
--

INSERT INTO `assignment_submission` (`fyp_submissionid`, `fyp_assignmentid`, `fyp_studid`, `fyp_marks`, `fyp_feedback`, `fyp_graded_date`, `fyp_submission_status`, `fyp_submission_date`, `fyp_submitted_file`) VALUES
(1, 2, 'TP001', 0, 'change your file name', '2025-12-25 06:45:31', 'Need Revision', '2025-12-25 06:02:23', 'uploads/assignments/1766638943_TCC4223 Assignment Guidelines.pdf');

-- --------------------------------------------------------

--
-- 表的结构 `attachment`
--

CREATE TABLE `attachment` (
  `fyp_attachid` int(11) NOT NULL,
  `fyp_announceid` int(11) DEFAULT NULL,
  `fyp_attachementname` varchar(500) DEFAULT NULL,
  `fyp_attachmentlink` varchar(500) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `attachment`
--

INSERT INTO `attachment` (`fyp_attachid`, `fyp_announceid`, `fyp_attachementname`, `fyp_attachmentlink`, `fyp_datecreated`) VALUES
(1, 1, 'guideline.pdf', '/files/g.pdf', '2025-12-08 13:30:53'),
(2, 2, 'template.docx', '/files/t.docx', '2025-12-08 13:30:53'),
(3, 3, 'slides.pptx', '/files/s.pptx', '2025-12-08 13:30:53'),
(4, 4, 'manual.pdf', '/files/m.pdf', '2025-12-08 13:30:53'),
(5, 5, 'map.jpg', '/files/m.jpg', '2025-12-08 13:30:53'),
(6, 6, 'cal.pdf', '/files/c.pdf', '2025-12-08 13:30:53'),
(7, 7, 'rules.pdf', '/files/r.pdf', '2025-12-08 13:30:53'),
(8, 8, 'notice.png', '/files/n.png', '2025-12-08 13:30:53'),
(9, 9, 'code.zip', '/files/c.zip', '2025-12-08 13:30:53'),
(10, 10, 'list.xlsx', '/files/l.xlsx', '2025-12-08 13:30:53');

-- --------------------------------------------------------

--
-- 表的结构 `criteria_mark`
--

CREATE TABLE `criteria_mark` (
  `fyp_criteriamarkid` int(11) NOT NULL,
  `fyp_criteriaid` int(11) DEFAULT NULL,
  `fyp_initialwork` decimal(10,3) DEFAULT NULL,
  `fyp_finalwork` decimal(10,3) DEFAULT NULL,
  `fyp_markbymoderator` char(1) DEFAULT NULL,
  `fyp_avgmark` decimal(10,3) DEFAULT NULL,
  `fyp_scaledmark` decimal(10,3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `criteria_mark`
--

INSERT INTO `criteria_mark` (`fyp_criteriamarkid`, `fyp_criteriaid`, `fyp_initialwork`, `fyp_finalwork`, `fyp_markbymoderator`, `fyp_avgmark`, `fyp_scaledmark`) VALUES
(1, 1, 8.000, 8.500, 'Y', 8.250, 8.250),
(2, 2, 7.000, 7.500, 'Y', 7.250, 7.250),
(3, 3, 14.000, 15.000, 'Y', 14.500, 14.500),
(4, 4, 24.000, 25.000, 'Y', 24.500, 24.500),
(5, 5, 8.000, 8.000, 'N', 8.000, 8.000),
(6, 6, 17.000, 18.000, 'Y', 17.500, 17.500),
(7, 7, 8.000, 9.000, 'Y', 8.500, 8.500),
(8, 8, 4.000, 4.000, 'N', 4.000, 4.000),
(9, 9, 4.000, 4.500, 'N', 4.250, 4.250),
(10, 10, 4.000, 4.000, 'N', 4.000, 4.000);

-- --------------------------------------------------------

--
-- 表的结构 `document`
--

CREATE TABLE `document` (
  `fyp_docid` int(11) NOT NULL,
  `fyp_docname` varchar(32) DEFAULT NULL,
  `fyp_doccategory` int(11) DEFAULT NULL,
  `fyp_pairingid` int(11) DEFAULT NULL,
  `fyp_docstatus` varchar(12) DEFAULT NULL,
  `fyp_remark` varchar(500) DEFAULT NULL,
  `fyp_remarkmd` varchar(500) DEFAULT NULL,
  `fyp_studid` varchar(12) DEFAULT NULL,
  `fyp_alternatelink` varchar(500) DEFAULT NULL,
  `fyp_docfilepath` varchar(500) DEFAULT NULL,
  `fyp_fileid` varchar(12) DEFAULT NULL,
  `fyp_version` varchar(12) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `document`
--

INSERT INTO `document` (`fyp_docid`, `fyp_docname`, `fyp_doccategory`, `fyp_pairingid`, `fyp_docstatus`, `fyp_remark`, `fyp_remarkmd`, `fyp_studid`, `fyp_alternatelink`, `fyp_docfilepath`, `fyp_fileid`, `fyp_version`, `fyp_datecreated`) VALUES
(1, 'Prop.pdf', 1, 1, 'Submitted', NULL, NULL, 'TP001', NULL, NULL, NULL, 'v1', '2025-12-08 13:30:53'),
(2, 'Req.pdf', 1, 2, 'Submitted', NULL, NULL, 'TP002', NULL, NULL, NULL, 'v1', '2025-12-08 13:30:53'),
(3, 'Des.pdf', 1, 3, 'Draft', NULL, NULL, 'TP003', NULL, NULL, NULL, 'v0', '2025-12-08 13:30:53'),
(4, 'Code.zip', 2, 4, 'Submitted', NULL, NULL, 'TP004', NULL, NULL, NULL, 'v1', '2025-12-08 13:30:53'),
(5, 'Rpt.pdf', 1, 5, 'Reviewed', NULL, NULL, 'TP005', NULL, NULL, NULL, 'v2', '2025-12-08 13:30:53'),
(6, 'Slide.ppt', 3, 6, 'Submitted', NULL, NULL, 'TP006', NULL, NULL, NULL, 'v1', '2025-12-08 13:30:53'),
(7, 'Ethic.pdf', 4, 7, 'Approved', NULL, NULL, 'TP007', NULL, NULL, NULL, 'v1', '2025-12-08 13:30:53'),
(8, 'Data.csv', 2, 8, 'Submitted', NULL, NULL, 'TP008', NULL, NULL, NULL, 'v1', '2025-12-08 13:30:53'),
(9, 'Log.doc', 1, 9, 'Submitted', NULL, NULL, 'TP009', NULL, NULL, NULL, 'v1', '2025-12-08 13:30:53'),
(10, 'Final.pdf', 1, 10, 'Submitted', NULL, NULL, 'TP010', NULL, NULL, NULL, 'v1', '2025-12-08 13:30:53');

-- --------------------------------------------------------

--
-- 表的结构 `fyp_maintenance`
--

CREATE TABLE `fyp_maintenance` (
  `fyp_maintainid` int(11) NOT NULL,
  `fyp_subject` varchar(100) DEFAULT NULL,
  `fyp_category` varchar(100) DEFAULT NULL,
  `fyp_value` varchar(24) DEFAULT NULL,
  `fyp_createdby` varchar(12) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `fyp_maintenance`
--

INSERT INTO `fyp_maintenance` (`fyp_maintainid`, `fyp_subject`, `fyp_category`, `fyp_value`, `fyp_createdby`, `fyp_datecreated`) VALUES
(1, 'Max Students', 'General', '5', 'admin', '2025-12-08 13:30:52'),
(2, 'Min Students', 'General', '1', 'admin', '2025-12-08 13:30:52'),
(3, 'Submission Day', 'Schedule', 'Friday', 'admin', '2025-12-08 13:30:52'),
(4, 'Grace Period', 'Submission', '3 Days', 'admin', '2025-12-08 13:30:52'),
(5, 'Max File Size', 'Upload', '10MB', 'admin', '2025-12-08 13:30:52'),
(6, 'Allowed Ext', 'Upload', 'pdf,zip', 'admin', '2025-12-08 13:30:52'),
(7, 'System Email', 'Notify', 'admin@uni.edu', 'admin', '2025-12-08 13:30:52'),
(8, 'Debug Mode', 'System', '0', 'admin', '2025-12-08 13:30:52'),
(9, 'Theme', 'UI', 'Dark', 'admin', '2025-12-08 13:30:52'),
(10, 'Maintenance', 'System', 'Off', 'admin', '2025-12-08 13:30:52');

-- --------------------------------------------------------

--
-- 表的结构 `fyp_registration`
--

CREATE TABLE `fyp_registration` (
  `fyp_regid` int(11) NOT NULL,
  `fyp_studid` varchar(32) DEFAULT NULL,
  `fyp_regacdid` int(11) DEFAULT NULL,
  `fyp_projectphase` int(11) DEFAULT NULL,
  `fyp_regnumber` int(11) DEFAULT NULL,
  `fyp_supervisorid` varchar(12) DEFAULT NULL,
  `fyp_pairingid` int(11) DEFAULT NULL,
  `fyp_projectid` int(11) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `fyp_registration`
--

INSERT INTO `fyp_registration` (`fyp_regid`, `fyp_studid`, `fyp_regacdid`, `fyp_projectphase`, `fyp_regnumber`, `fyp_supervisorid`, `fyp_pairingid`, `fyp_projectid`, `fyp_datecreated`) VALUES
(22, 'TP001', NULL, NULL, NULL, '1', NULL, 5, '2025-12-14 08:57:19'),
(23, 'TP003', NULL, NULL, NULL, '1', NULL, 5, '2025-12-14 08:57:19'),
(24, 'TP009', NULL, NULL, NULL, '1', NULL, 5, '2025-12-14 08:57:19'),
(26, 'TP007', NULL, NULL, NULL, '1', NULL, 7, '2025-12-14 10:14:30'),
(27, 'TP008', NULL, NULL, NULL, '5', NULL, 9, '2025-12-19 05:14:18');

-- --------------------------------------------------------

--
-- 表的结构 `grade_criteria`
--

CREATE TABLE `grade_criteria` (
  `fyp_id` int(11) NOT NULL,
  `fyp_acdemicid` int(11) DEFAULT NULL,
  `fyp_criterianame` varchar(100) DEFAULT NULL,
  `fyp_createdby` varchar(12) DEFAULT NULL,
  `fyp_createddate` datetime DEFAULT NULL,
  `fyp_editedby` varchar(12) DEFAULT NULL,
  `fyp_editeddate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `grade_criteria`
--

INSERT INTO `grade_criteria` (`fyp_id`, `fyp_acdemicid`, `fyp_criterianame`, `fyp_createdby`, `fyp_createddate`, `fyp_editedby`, `fyp_editeddate`) VALUES
(1, 1, 'Standard Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(2, 1, 'Pass/Fail', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(3, 2, 'Standard Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(4, 2, 'Research Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(5, 3, 'Standard Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(6, 3, 'Internship Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(7, 4, 'Standard Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(8, 4, 'Final Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(9, 5, 'Standard Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(10, 5, 'Special Grade', 'admin', '2025-12-08 13:30:52', NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `grade_maintenance`
--

CREATE TABLE `grade_maintenance` (
  `fyp_id` int(11) NOT NULL,
  `fyp_acdemicid` int(11) DEFAULT NULL,
  `fyp_gradecriteriaid` int(11) DEFAULT NULL,
  `fyp_grade` varchar(5) DEFAULT NULL,
  `fyp_frommark` int(11) DEFAULT NULL,
  `fyp_tomark` int(11) DEFAULT NULL,
  `fyp_createdby` varchar(12) DEFAULT NULL,
  `fyp_createddate` datetime DEFAULT NULL,
  `fyp_editedby` varchar(12) DEFAULT NULL,
  `fyp_editeddate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `grade_maintenance`
--

INSERT INTO `grade_maintenance` (`fyp_id`, `fyp_acdemicid`, `fyp_gradecriteriaid`, `fyp_grade`, `fyp_frommark`, `fyp_tomark`, `fyp_createdby`, `fyp_createddate`, `fyp_editedby`, `fyp_editeddate`) VALUES
(1, 1, 1, 'A+', 90, 100, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(2, 1, 1, 'A', 80, 89, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(3, 1, 1, 'B+', 75, 79, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(4, 1, 1, 'B', 70, 74, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(5, 1, 1, 'C+', 65, 69, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(6, 1, 1, 'C', 60, 64, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(7, 1, 1, 'D', 50, 59, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(8, 1, 1, 'F', 0, 49, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(9, 2, 2, 'Pass', 50, 100, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(10, 2, 2, 'Fail', 0, 49, 'admin', '2025-12-08 13:30:52', NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `group_request`
--

CREATE TABLE `group_request` (
  `request_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `inviter_id` varchar(12) NOT NULL,
  `invitee_id` varchar(12) NOT NULL,
  `request_status` enum('Pending','Accepted','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `group_request`
--

INSERT INTO `group_request` (`request_id`, `group_id`, `inviter_id`, `invitee_id`, `request_status`) VALUES
(8, 2, 'TP001', 'TP003', 'Accepted'),
(10, 2, 'TP001', 'TP007', 'Rejected'),
(11, 2, 'TP001', 'TP004', 'Rejected'),
(14, 2, 'TP001', 'TP009', 'Accepted'),
(16, 3, 'TP006', 'TP007', 'Rejected'),
(18, 6, 'TP004', 'TP005', 'Pending');

-- --------------------------------------------------------

--
-- 表的结构 `item`
--

CREATE TABLE `item` (
  `fyp_itemid` int(11) NOT NULL,
  `fyp_itemname` varchar(56) DEFAULT NULL,
  `fyp_isdocument` int(11) DEFAULT NULL,
  `fyp_ismoderation` int(11) DEFAULT NULL,
  `fyp_originalmarkallocation` decimal(10,3) DEFAULT NULL,
  `fyp_startdate` datetime DEFAULT NULL,
  `fyp_finaldeadline` datetime DEFAULT NULL,
  `fyp_markbymoderator` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `item`
--

INSERT INTO `item` (`fyp_itemid`, `fyp_itemname`, `fyp_isdocument`, `fyp_ismoderation`, `fyp_originalmarkallocation`, `fyp_startdate`, `fyp_finaldeadline`, `fyp_markbymoderator`) VALUES
(1, 'Proposal', 1, 0, 10.000, '2025-12-08 13:30:53', '2026-01-07 13:30:53', 0),
(2, 'Requirement', 1, 0, 10.000, '2025-12-08 13:30:53', '2026-02-06 13:30:53', 0),
(3, 'Design', 1, 1, 20.000, '2025-12-08 13:30:53', '2026-03-08 13:30:53', 1),
(4, 'Implementation', 0, 1, 30.000, '2025-12-08 13:30:53', '2026-04-07 13:30:53', 1),
(5, 'Testing', 1, 0, 10.000, '2025-12-08 13:30:53', '2026-05-07 13:30:53', 0),
(6, 'Final Report', 1, 1, 20.000, '2025-12-08 13:30:53', '2026-06-06 13:30:53', 1),
(7, 'Presentation', 0, 1, 10.000, '2025-12-08 13:30:53', '2026-06-11 13:30:53', 1),
(8, 'Logbook 1', 1, 0, 5.000, '2025-12-08 13:30:53', '2026-01-22 13:30:53', 0),
(9, 'Logbook 2', 1, 0, 5.000, '2025-12-08 13:30:53', '2026-03-18 13:30:53', 0),
(10, 'Poster', 1, 0, 5.000, '2025-12-08 13:30:53', '2026-05-27 13:30:53', 0);

-- --------------------------------------------------------

--
-- 表的结构 `item_mark`
--

CREATE TABLE `item_mark` (
  `fyp_itemmarkid` int(11) NOT NULL,
  `fyp_itemid` int(11) DEFAULT NULL,
  `fyp_finalsupervisor` decimal(10,3) DEFAULT NULL,
  `fyp_finalmoderator` decimal(10,3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `item_mark`
--

INSERT INTO `item_mark` (`fyp_itemmarkid`, `fyp_itemid`, `fyp_finalsupervisor`, `fyp_finalmoderator`) VALUES
(1, 1, 8.500, 8.000),
(2, 2, 7.500, 7.000),
(3, 3, 15.000, 14.000),
(4, 4, 25.000, 24.000),
(5, 5, 8.000, 8.000),
(6, 6, 18.000, 17.000),
(7, 7, 9.000, 8.000),
(8, 8, 4.000, 4.000),
(9, 9, 4.500, 4.000),
(10, 10, 4.000, 4.000);

-- --------------------------------------------------------

--
-- 表的结构 `item_marking_criteria`
--

CREATE TABLE `item_marking_criteria` (
  `fyp_itemid` int(11) NOT NULL,
  `fyp_criteriaid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `item_marking_criteria`
--

INSERT INTO `item_marking_criteria` (`fyp_itemid`, `fyp_criteriaid`) VALUES
(1, 1),
(1, 2),
(2, 3),
(2, 4),
(3, 5),
(3, 6),
(4, 7),
(4, 8),
(5, 9),
(5, 10);

-- --------------------------------------------------------

--
-- 表的结构 `item_mark_criteria_mark`
--

CREATE TABLE `item_mark_criteria_mark` (
  `fyp_itemmarkid` int(11) NOT NULL,
  `fyp_criteriamarkid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `item_mark_criteria_mark`
--

INSERT INTO `item_mark_criteria_mark` (`fyp_itemmarkid`, `fyp_criteriamarkid`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5),
(6, 6),
(7, 7),
(8, 8),
(9, 9),
(10, 10);

-- --------------------------------------------------------

--
-- 表的结构 `last_activity`
--

CREATE TABLE `last_activity` (
  `fyp_activityid` int(11) NOT NULL,
  `fyp_pairingid` int(11) DEFAULT NULL,
  `fyp_studid` varchar(12) DEFAULT NULL,
  `fyp_description` varchar(100) DEFAULT NULL,
  `fyp_status` varchar(50) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `last_activity`
--

INSERT INTO `last_activity` (`fyp_activityid`, `fyp_pairingid`, `fyp_studid`, `fyp_description`, `fyp_status`, `fyp_datecreated`) VALUES
(1, 1, 'TP001', 'Login', 'Success', '2025-12-08 13:30:53'),
(2, 2, 'TP002', 'Upload', 'Success', '2025-12-08 13:30:53'),
(3, 3, 'TP003', 'Logout', 'Success', '2025-12-08 13:30:53'),
(4, 4, 'TP004', 'View', 'Success', '2025-12-08 13:30:53'),
(5, 5, 'TP005', 'Edit', 'Success', '2025-12-08 13:30:53'),
(6, 6, 'TP006', 'Login', 'Success', '2025-12-08 13:30:53'),
(7, 7, 'TP007', 'Upload', 'Success', '2025-12-08 13:30:53'),
(8, 8, 'TP008', 'Logout', 'Success', '2025-12-08 13:30:53'),
(9, 9, 'TP009', 'View', 'Success', '2025-12-08 13:30:53'),
(10, 10, 'TP010', 'Edit', 'Success', '2025-12-08 13:30:53');

-- --------------------------------------------------------

--
-- 表的结构 `marking_criteria`
--

CREATE TABLE `marking_criteria` (
  `fyp_criteriaid` int(11) NOT NULL,
  `fyp_criterianame` varchar(56) DEFAULT NULL,
  `fyp_percentallocation` decimal(10,3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `marking_criteria`
--

INSERT INTO `marking_criteria` (`fyp_criteriaid`, `fyp_criterianame`, `fyp_percentallocation`) VALUES
(1, 'Introduction', 10.000),
(2, 'Lit Review', 20.000),
(3, 'Methodology', 20.000),
(4, 'Results', 20.000),
(5, 'Conclusion', 10.000),
(6, 'Reference', 5.000),
(7, 'Q&A', 15.000),
(8, 'Demo', 30.000),
(9, 'Format', 5.000),
(10, 'Innovation', 10.000);

-- --------------------------------------------------------

--
-- 表的结构 `marking_criteria_assessment_criteria`
--

CREATE TABLE `marking_criteria_assessment_criteria` (
  `fyp_criteriaid` int(11) NOT NULL,
  `fyp_assessmentcriteriaid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `marking_criteria_assessment_criteria`
--

INSERT INTO `marking_criteria_assessment_criteria` (`fyp_criteriaid`, `fyp_assessmentcriteriaid`) VALUES
(1, 1),
(1, 2),
(2, 2),
(2, 3),
(3, 3),
(3, 4),
(4, 4),
(4, 5),
(5, 1),
(5, 2);

-- --------------------------------------------------------

--
-- 表的结构 `moderation_criteria`
--

CREATE TABLE `moderation_criteria` (
  `fyp_mdcriteriaid` int(11) NOT NULL,
  `fyp_criterianame` varchar(100) DEFAULT NULL,
  `fyp_criteriadesc` varchar(100) DEFAULT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_createdby` varchar(12) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `moderation_criteria`
--

INSERT INTO `moderation_criteria` (`fyp_mdcriteriaid`, `fyp_criterianame`, `fyp_criteriadesc`, `fyp_academicid`, `fyp_createdby`, `fyp_datecreated`) VALUES
(1, 'Scope', 'Check Scope', 1, 'admin', '2025-12-08 13:30:53'),
(2, 'Title', 'Check Title', 1, 'admin', '2025-12-08 13:30:53'),
(3, 'Complexity', 'Check Tech', 1, 'admin', '2025-12-08 13:30:53'),
(4, 'Ethics', 'Check Ethics', 1, 'admin', '2025-12-08 13:30:53'),
(5, 'Feasibility', 'Check Time', 1, 'admin', '2025-12-08 13:30:53'),
(6, 'Scope', 'Check Scope', 2, 'admin', '2025-12-08 13:30:53'),
(7, 'Title', 'Check Title', 2, 'admin', '2025-12-08 13:30:53'),
(8, 'Complexity', 'Check Tech', 2, 'admin', '2025-12-08 13:30:53'),
(9, 'Ethics', 'Check Ethics', 2, 'admin', '2025-12-08 13:30:53'),
(10, 'Feasibility', 'Check Time', 2, 'admin', '2025-12-08 13:30:53');

-- --------------------------------------------------------

--
-- 表的结构 `pairing`
--

CREATE TABLE `pairing` (
  `fyp_pairingid` int(11) NOT NULL,
  `fyp_supervisorid` varchar(12) DEFAULT NULL,
  `fyp_type` enum('Individual','Group') DEFAULT NULL,
  `fyp_projectid` int(11) DEFAULT NULL,
  `fyp_moderatorid` varchar(12) DEFAULT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `pairing`
--

INSERT INTO `pairing` (`fyp_pairingid`, `fyp_supervisorid`, `fyp_type`, `fyp_projectid`, `fyp_moderatorid`, `fyp_academicid`, `fyp_datecreated`) VALUES
(1, '1', 'Individual', 1, '2', 1, '2025-12-08 13:30:53'),
(2, '2', 'Individual', 2, '1', 1, '2025-12-08 13:30:53'),
(3, '3', 'Individual', 3, '4', 1, '2025-12-08 13:30:53'),
(4, '1', 'Individual', 4, '3', 1, '2025-12-08 13:30:53'),
(5, '5', 'Individual', 5, '6', 1, '2025-12-08 13:30:53'),
(6, '6', 'Individual', 6, '5', 2, '2025-12-08 13:30:53'),
(7, '7', 'Individual', 7, '8', 2, '2025-12-08 13:30:53'),
(8, '8', 'Individual', 8, '7', 2, '2025-12-08 13:30:53'),
(9, '9', 'Individual', 9, '10', 2, '2025-12-08 13:30:53'),
(10, '10', 'Individual', 10, '9', 2, '2025-12-08 13:30:53'),
(15, '1', NULL, 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `programme`
--

CREATE TABLE `programme` (
  `fyp_progid` int(11) NOT NULL,
  `fyp_progname` varchar(56) DEFAULT NULL,
  `fyp_prognamefull` varchar(150) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `programme`
--

INSERT INTO `programme` (`fyp_progid`, `fyp_progname`, `fyp_prognamefull`, `fyp_datecreated`) VALUES
(1, 'SE', 'Software Engineering', '2025-12-08 13:30:52'),
(2, 'CS', 'Cyber Security', '2025-12-08 13:30:52'),
(3, 'DS', 'Data Science', '2025-12-08 13:30:52'),
(4, 'AI', 'Artificial Intelligence', '2025-12-08 13:30:52'),
(5, 'IT', 'Information Technology', '2025-12-08 13:30:52'),
(6, 'FTECH', 'Financial Technology', '2025-12-08 13:30:52'),
(7, 'GADE', 'Game Development', '2025-12-08 13:30:52'),
(8, 'MCOM', 'Mobile Computing', '2025-12-08 13:30:52'),
(9, 'CLD', 'Cloud Computing', '2025-12-08 13:30:52'),
(10, 'IOT', 'Internet of Things', '2025-12-08 13:30:52');

-- --------------------------------------------------------

--
-- 表的结构 `project`
--

CREATE TABLE `project` (
  `fyp_projectid` int(11) NOT NULL,
  `fyp_projecttitle` varchar(56) DEFAULT NULL,
  `fyp_description` varchar(268) DEFAULT NULL,
  `fyp_projectcat` varchar(16) DEFAULT NULL,
  `fyp_projecttype` enum('Individual','Group') DEFAULT NULL,
  `fyp_projectstatus` enum('Taken','Open') DEFAULT NULL,
  `fyp_requirement` varchar(120) DEFAULT NULL,
  `fyp_coursereq` varchar(56) DEFAULT 'FIST',
  `fyp_contactperson` varchar(100) DEFAULT NULL,
  `fyp_contactpersonname` varchar(500) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL,
  `fyp_supervisorid` int(11) NOT NULL,
  `fyp_academicid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `project`
--

INSERT INTO `project` (`fyp_projectid`, `fyp_projecttitle`, `fyp_description`, `fyp_projectcat`, `fyp_projecttype`, `fyp_projectstatus`, `fyp_requirement`, `fyp_coursereq`, `fyp_contactperson`, `fyp_contactpersonname`, `fyp_datecreated`, `fyp_supervisorid`, `fyp_academicid`) VALUES
(5, 'click this', 'laiwan', 'Networking', 'Group', 'Taken', 'sadsadsad', 'FIST', 'x@uni.edu', 'Dr. Xavier', '2025-12-14 15:19:17', 1, NULL),
(6, 'testing testing 123', 'safasfasfsa', 'Networking', 'Group', 'Open', 'cacacacs', 'FIST', 'x@uni.edu', 'Dr. Xavier', '2025-12-14 16:11:43', 1, NULL),
(7, 'hello guys', '21e12e12e21', 'Software Eng.', 'Individual', 'Taken', '12e12e21e12e', 'FIST', 'x@uni.edu', 'Dr. Xavier', '2025-12-14 16:12:23', 1, NULL),
(8, 'my project is very hard', 'come on', 'Networking', 'Individual', 'Open', 'my baby', 'FIST', 'v@uni.edu', 'Dr. Victor', '2025-12-15 15:00:20', 5, NULL),
(9, 'table design', 'abc', 'Networking', 'Individual', 'Taken', 'abc', 'FIST', 'v@uni.edu', 'Dr. Victor', '2025-12-19 12:10:21', 5, NULL),
(10, 'testing testing 123', 'sfsfasfasf', 'Software Eng.', 'Individual', 'Open', '124124214', 'FIST', 'x@uni.edu', 'Dr. Xavier', '2026-01-04 18:17:39', 1, 10),
(11, 'testing testing 123', 'safasasdasd', 'Software Eng.', 'Individual', 'Open', 'asdasdasd', 'FIST', 'x@uni.edu', 'Dr. Xavier', '2026-01-04 18:32:23', 1, 1);

-- --------------------------------------------------------

--
-- 表的结构 `project_request`
--

CREATE TABLE `project_request` (
  `fyp_requestid` int(11) NOT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_studid` varchar(12) DEFAULT NULL,
  `fyp_pairingid` int(11) DEFAULT NULL,
  `fyp_supervisorid` varchar(12) DEFAULT NULL,
  `fyp_projectid` int(11) DEFAULT NULL,
  `fyp_docid` int(11) DEFAULT NULL,
  `fyp_requeststatus` enum('Pending','Approve','Reject') DEFAULT 'Pending',
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `project_request`
--

INSERT INTO `project_request` (`fyp_requestid`, `fyp_academicid`, `fyp_studid`, `fyp_pairingid`, `fyp_supervisorid`, `fyp_projectid`, `fyp_docid`, `fyp_requeststatus`, `fyp_datecreated`) VALUES
(7, NULL, 'TP001', NULL, '1', 5, NULL, 'Approve', '2025-12-14 08:57:00'),
(10, NULL, 'TP007', NULL, '1', 7, NULL, 'Reject', '2025-12-14 09:56:53'),
(11, NULL, 'TP007', NULL, '1', 7, NULL, 'Approve', '2025-12-14 10:12:51'),
(12, NULL, 'TP008', NULL, '5', 9, NULL, 'Approve', '2025-12-19 05:13:46');

-- --------------------------------------------------------

--
-- 表的结构 `quota`
--

CREATE TABLE `quota` (
  `fyp_quotaid` int(11) NOT NULL,
  `fyp_supervisorid` varchar(12) DEFAULT NULL,
  `fyp_numofstudent` int(11) DEFAULT 0,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `quota`
--

INSERT INTO `quota` (`fyp_quotaid`, `fyp_supervisorid`, `fyp_numofstudent`, `fyp_academicid`, `fyp_datecreated`) VALUES
(1, '1', 5, 1, '2025-12-08 13:30:53'),
(2, '2', 5, 1, '2025-12-08 13:30:53'),
(3, '3', 5, 1, '2025-12-08 13:30:53'),
(4, '4', 5, 1, '2025-12-08 13:30:53'),
(5, '5', 5, 1, '2025-12-08 13:30:53'),
(6, '6', 5, 2, '2025-12-08 13:30:53'),
(7, '7', 5, 2, '2025-12-08 13:30:53'),
(8, '8', 5, 2, '2025-12-08 13:30:53'),
(9, '9', 5, 2, '2025-12-08 13:30:53'),
(10, '10', 5, 2, '2025-12-08 13:30:53');

-- --------------------------------------------------------

--
-- 表的结构 `schedule_meeting`
--

CREATE TABLE `schedule_meeting` (
  `fyp_scheduleid` int(11) NOT NULL,
  `fyp_supervisorid` int(11) NOT NULL,
  `fyp_date` date NOT NULL,
  `fyp_day` varchar(20) NOT NULL,
  `fyp_fromtime` time NOT NULL,
  `fyp_totime` time NOT NULL,
  `fyp_location` varchar(100) DEFAULT NULL,
  `fyp_status` enum('Available','Booked') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `schedule_meeting`
--

INSERT INTO `schedule_meeting` (`fyp_scheduleid`, `fyp_supervisorid`, `fyp_date`, `fyp_day`, `fyp_fromtime`, `fyp_totime`, `fyp_location`, `fyp_status`) VALUES
(2, 1, '2025-12-23', 'Tuesday', '16:01:00', '15:01:00', 'R001', 'Available');

-- --------------------------------------------------------

--
-- 表的结构 `set`
--

CREATE TABLE `set` (
  `fyp_setid` int(11) NOT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_projectphase` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `set`
--

INSERT INTO `set` (`fyp_setid`, `fyp_academicid`, `fyp_projectphase`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 2, 1),
(4, 2, 2),
(5, 3, 1),
(6, 3, 2),
(7, 4, 1),
(8, 4, 2),
(9, 5, 1),
(10, 5, 2);

-- --------------------------------------------------------

--
-- 表的结构 `set_item`
--

CREATE TABLE `set_item` (
  `fyp_setid` int(11) NOT NULL,
  `fyp_itemid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `set_item`
--

INSERT INTO `set_item` (`fyp_setid`, `fyp_itemid`) VALUES
(1, 1),
(1, 2),
(2, 3),
(2, 4),
(3, 1),
(3, 2),
(4, 3),
(4, 4),
(5, 1),
(5, 2);

-- --------------------------------------------------------

--
-- 表的结构 `student`
--

CREATE TABLE `student` (
  `fyp_studid` varchar(12) NOT NULL,
  `fyp_studfullid` varchar(10) DEFAULT NULL,
  `fyp_studname` varchar(56) DEFAULT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_progid` int(11) DEFAULT NULL,
  `fyp_group` enum('Group','Individual') NOT NULL DEFAULT 'Individual',
  `fyp_email` varchar(56) DEFAULT NULL,
  `fyp_contactno` varchar(12) DEFAULT NULL,
  `fyp_profileimg` longtext DEFAULT NULL,
  `fyp_userid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `student`
--

INSERT INTO `student` (`fyp_studid`, `fyp_studfullid`, `fyp_studname`, `fyp_academicid`, `fyp_progid`, `fyp_group`, `fyp_email`, `fyp_contactno`, `fyp_profileimg`, `fyp_userid`) VALUES
('TP001', 'TP055001', 'Alice Tan', 1, 1, 'Group', 'tp001@email.com', '0123456789', 'data:image/jpg;base64,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', 101);
INSERT INTO `student` (`fyp_studid`, `fyp_studfullid`, `fyp_studname`, `fyp_academicid`, `fyp_progid`, `fyp_group`, `fyp_email`, `fyp_contactno`, `fyp_profileimg`, `fyp_userid`) VALUES
('TP002', 'TP055002', 'Bob Lee', 1, 2, 'Individual', 'tp002@email.com', '0123456788', 'data:image/png;base64,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n5GcnMybb75JcXExd955J7D70v2uXbsOOQA/3HTg+y2b6JGczqvr1hICJpw3kZGjTuPCCy7m8cf+xbXXXcvpp5/O8OHDmT9/PnPnzmXEiBHU1NQYJT976tGjBz/++GOj+8zOzuabb75psvTHbreza9cufvrpJ4LBILt27eKSSy7BarWSkJDAyy+/TFxcHHPmzMHlcjF9+nRWr17NJ598QmVlJX369OGtt95q8jlffPHFxnLWe0pISGDDhg2Ule0O+muqa/hp9Sp+uicy6bRf6wz+lNed0nrHn56eTteuXcnJyWnx37VCNEeCY/GbZjabycnJYdSoUfz4448UFRURDocPqm/s+mXFTPvPQi69txdh3Y/VakHTNXQt0mIMFBQU9INs8wagE0ZRQyiaGRQTum5CBXQ92qNBN6HrUOOvxew0ETDtYMC4JLr3P53Vi7ezaOY2Nq/fRShw5KJkVVVwxNvodUI27uFZdHG7MMVX4FN2okQXGNQUE7quREok9EA0c1m/zhh21x1rgE440rcNBVB1HTSNsNfEusXFfPfJz82WU7RuncmLL75oLNFcv5+woihYLBbq6up49tlngUgWLCsrC7/fj6qqjfaNVRSFv/71r/Tr1w+v10tSUlKT+1cUhYcffhi/329kiGO1xrGstclk4rrrrmtycYf9MXbsWP75z3/StWvXBs8xlqWuny1+8cUXue+++w5pf7qus337dh555BGefvppJk+ezDPPPMNJJ53E1VdffcBXaT744AP69evH888/TyAQ4M9//jPBYBCz2Wxc+Xn66acPfNXLI2BlaSmXdO7HjG35bA9EAr6zzz2Hz2d8xtp1a7npppuASDY+OSmZk4afxJ133onTGce8efMoKSnhjTfeYNmyZWzevBmXy4XT6Wzy5GXkyJH8/PPPTR5P//79UVV1r9KyQCBASUkJrVu35rTTTqOkpASHw0HHjh0ZMGAAb7zxBp07d6ZNmza89tprxiIie9bEt2rVik8++aTRfZ900kls2LCh2Z+bt66O/OJiauudFLhcLvr16yf9jcWvToLj49iR7sP4a40Ph8O4XC769u3L+++/T3x8PIFA4MCzczr8OGsb3Qe1wT0mE6+/HNUeAiWMqltRNQeqbiGoeJsNkJv7Fa9jJqybohlSBRQttmsANAV01YSCSlgDxaTi031Y24cZ0L4VQ87P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4XDw2GOPNfijrqoqzz//PO3ataOuro63336bl19+udljGDZsGC6Xi0AgYLRw0+v93DZs2MCHH37Y7Db2VygU4pZbbuHhhx/m73//O48//jhJSUl89tlnzJ07l9NOO41HH330sC3ccddddxlBbTAY3K+VKZOSkvjHP/7Btm3b8Hq9dOjQwTiB0nWdiooKzjzzTKPk5VizubaGwspdjG+VyftfNb2IzLizxvPU0082ef9JJ53EO++8A0BJSQklJSUsXLgQgFtuuYVx48YZGfvTTjuNxYsXs2bNGj7++GMSExMxm81N/tyHDx/OHXfc0eS+x44dyz/+8Y8m7+/Tpw833ngjGzduZPHixdhsNpxOJyeffDLXXXcdbdu2Zc6cOcxbsICfN2xm1vsfsr5gAz6vj17OBJx2G4tKGta+5+bmkpOTY6yKKMSvSYJjcdxQVZXMzEzOOussHA4Hs2bNMtp3HYzCzVVMfeY7Lrt1MIntnYTDdShKCEVxGH16W7wPciNil84VJdIGLhztdqErKqh2wrqCalYIhCOty3RVJxAOo2kQVlV0XUE3WzC7NMxxtShEumKghFHUMCY0UDTCmomQZo2tcR2tsTChmyKlHZGnGQT06P939yaOvAYH9jrUL30wqSqhYAibamfLGh+fvryEoK/5rP/JJ5/M//3f/xkT38LhcINeubHuFF999ZUxxmq1MnHiRJxOJ16vd6+6v7vvvpvhw4djNpspLi5ush9tfX/84x8JhULY7fYGi26YTCZqamq4++67WzTwi5WKvPTSS9TW1jJkyBCjxdq99957UL2+90e7du34/e9/TyAQIBQK4XQ6sVgs+5z8+oc//IGcnBwURaFXr15GnbSiKPh8Ph577LFGa76PFUHg/U0bSZw+ne3VNVhVhYC292eh34D+vPzSS01up6qqqskrXQMHDuTWW281WgA6HA5atWrFaaedxllnncWNN96Iz+fj2Wef5d1336WgoIDNmzcbC+Dk5OSwc2fjWWuXy0V2dnaT7QVtNhvdunVrMD7WG/yjjz4iNzeXTz/9lPfee4/+/d0Mcp/If55/jrTW8Wz5dj7eLxbw0HczqdrjJKpLly60adNGulSII0KCY3FcMZvNtGvXjjPOOIP27dvzwQcfsHz58oPe3vql5bz6z+/5/Z9PIilHQTPXokWzXnvWl7aUJremgxbtmqFj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', 102);
INSERT INTO `student` (`fyp_studid`, `fyp_studfullid`, `fyp_studname`, `fyp_academicid`, `fyp_progid`, `fyp_group`, `fyp_email`, `fyp_contactno`, `fyp_profileimg`, `fyp_userid`) VALUES
('TP003', 'TP055003', 'Charlie Ng', 1, 3, 'Group', 'tp003@email.com', '0123456787', 'data:image/jpg;base64,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', 103),
('TP004', 'TP055004', 'David Wong', 1, 4, 'Individual', 'tp004@email.com', '0123456786', NULL, 104),
('TP005', 'TP055005', 'Eve Lim', 1, 1, 'Individual', 'tp005@email.com', '0123456785', NULL, 105),
('TP006', 'TP055006', 'Frank Liu', 2, 2, 'Group', 'tp006@email.com', '0123456784', NULL, 106),
('TP007', 'TP055007', 'Grace Chen', 2, 3, 'Individual', 'tp007@email.com', '0123456783', NULL, 107),
('TP008', 'TP055008', 'Heidi Klum', 2, 4, 'Individual', 'tp008@email.com', '0123456782', NULL, 108),
('TP009', 'TP055009', 'Ivan Drago', 2, 1, 'Group', 'tp009@email.com', '0123456781', NULL, 109),
('TP010', 'TP055010', 'Judy Hopps', 2, 2, 'Individual', 'tp010@email.com', '0123456780', NULL, 110);

-- --------------------------------------------------------

--
-- 表的结构 `student_group`
--

CREATE TABLE `student_group` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `leader_id` varchar(12) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('Recruiting','Full') DEFAULT 'Recruiting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `student_group`
--

INSERT INTO `student_group` (`group_id`, `group_name`, `leader_id`, `created_at`, `status`) VALUES
(2, 'MAMBA OUT', 'TP001', '2025-12-14 15:28:54', 'Full'),
(3, 'MAMBA123', 'TP006', '2025-12-14 15:35:45', 'Recruiting'),
(6, 'MAN!', 'TP004', '2026-01-04 18:08:18', 'Recruiting');

-- --------------------------------------------------------

--
-- 表的结构 `student_marks`
--

CREATE TABLE `student_marks` (
  `mark_id` int(11) NOT NULL,
  `student_id` varchar(12) NOT NULL,
  `item_id` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `grader_id` int(11) NOT NULL,
  `graded_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 表的结构 `student_moderation`
--

CREATE TABLE `student_moderation` (
  `fyp_studmdid` int(11) NOT NULL,
  `fyp_studid` varchar(12) DEFAULT NULL,
  `fyp_mdcriteriaid` int(11) DEFAULT NULL,
  `fyp_comply` int(11) DEFAULT NULL,
  `fyp_regacdid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `student_moderation`
--

INSERT INTO `student_moderation` (`fyp_studmdid`, `fyp_studid`, `fyp_mdcriteriaid`, `fyp_comply`, `fyp_regacdid`) VALUES
(1, 'TP001', 1, 1, 1),
(2, 'TP002', 2, 1, 1),
(3, 'TP003', 3, 1, 1),
(4, 'TP004', 4, 0, 1),
(5, 'TP005', 5, 1, 1),
(6, 'TP006', 6, 1, 2),
(7, 'TP007', 7, 1, 2),
(8, 'TP008', 8, 0, 2),
(9, 'TP009', 9, 1, 2),
(10, 'TP010', 10, 1, 2);

-- --------------------------------------------------------

--
-- 表的结构 `student_num`
--

CREATE TABLE `student_num` (
  `fyp_studnumid` int(11) NOT NULL,
  `fyp_projectid` int(11) DEFAULT NULL,
  `fyp_progid` int(11) DEFAULT NULL,
  `fyp_numofstudent` int(11) DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `student_num`
--

INSERT INTO `student_num` (`fyp_studnumid`, `fyp_projectid`, `fyp_progid`, `fyp_numofstudent`, `fyp_datecreated`) VALUES
(1, 1, 1, 50, '2025-12-08 13:30:53'),
(2, 2, 2, 40, '2025-12-08 13:30:53'),
(3, 3, 3, 30, '2025-12-08 13:30:53'),
(4, 4, 4, 20, '2025-12-08 13:30:53'),
(5, 5, 5, 60, '2025-12-08 13:30:53'),
(6, 6, 6, 25, '2025-12-08 13:30:53'),
(7, 7, 7, 35, '2025-12-08 13:30:53'),
(8, 8, 8, 15, '2025-12-08 13:30:53'),
(9, 9, 9, 20, '2025-12-08 13:30:53'),
(10, 10, 10, 10, '2025-12-08 13:30:53');

-- --------------------------------------------------------

--
-- 表的结构 `supervised_programme`
--

CREATE TABLE `supervised_programme` (
  `fyp_spid` int(11) NOT NULL,
  `fyp_quotaid` int(11) DEFAULT NULL,
  `fyp_programme` varchar(56) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `supervised_programme`
--

INSERT INTO `supervised_programme` (`fyp_spid`, `fyp_quotaid`, `fyp_programme`) VALUES
(1, 1, 'SE'),
(2, 1, 'CS'),
(3, 2, 'DS'),
(4, 3, 'AI'),
(5, 4, 'IT'),
(6, 5, 'SE'),
(7, 6, 'CS'),
(8, 7, 'DS'),
(9, 8, 'AI'),
(10, 9, 'IT');

-- --------------------------------------------------------

--
-- 表的结构 `supervisor`
--

CREATE TABLE `supervisor` (
  `fyp_supervisorid` int(11) NOT NULL,
  `fyp_name` varchar(9999) DEFAULT NULL,
  `fyp_roomno` varchar(10) DEFAULT NULL,
  `fyp_programme` varchar(50) DEFAULT NULL,
  `fyp_email` varchar(100) DEFAULT NULL,
  `fyp_contactno` varchar(12) DEFAULT NULL,
  `fyp_specialization` varchar(100) DEFAULT NULL,
  `fyp_areaofinterest` varchar(100) DEFAULT NULL,
  `fyp_ismoderator` int(11) DEFAULT NULL,
  `fyp_profileimg` longtext DEFAULT NULL,
  `fyp_datecreated` datetime DEFAULT NULL,
  `fyp_userid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `supervisor`
--

INSERT INTO `supervisor` (`fyp_supervisorid`, `fyp_name`, `fyp_roomno`, `fyp_programme`, `fyp_email`, `fyp_contactno`, `fyp_specialization`, `fyp_areaofinterest`, `fyp_ismoderator`, `fyp_profileimg`, `fyp_datecreated`, `fyp_userid`) VALUES
(1, 'Dr. Xavier', 'R001', 'SE', 'x@uni.edu', '0111111111', 'AI', 'Deep Learning', 1, 'data:image/jpeg;base64,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eZeIP+TlvAf/AGI3i3/04eH69NoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPN/j/8A8iJpf/Y7eDf/AFI9Or0ivN/j/wD8iJpf/Y7eDf8A1I9Or0igAooooAKKKKACiiigDl/hd/yTfwv/ANgi0/8ARS11Fcv8Lv8Akm/hf/sEWn/opa6igAooooAKKKKACiiigDl/h3/yA73/ALD2s/8ApxuK6iuX+Hf/ACA73/sPaz/6cbiuooAKKKKACiiigAooooA+E/2l/wDktviP/tz/APSSGij9pf8A5Lb4j/7c/wD0khooA+l/2Tv+TWPg3/2T/wAPf+m6CvVa8q/ZO/5NY+Df/ZP/AA9/6boK9VoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPLvhB/yUL44f9j7af8AqL6HXqNeXfCD/koXxw/7H20/9RfQ69RoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPMvEH/ACct4D/7Ebxb/wCnDw/XpteZeIP+TlvAf/YjeLf/AE4eH69NoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPN/j/AP8AIiaX/wBjt4N/9SPTq9Irzf4//wDIiaX/ANjt4N/9SPTq9IoAKKKKACiiigAooooA5f4Xf8k38L/9gi0/9FLXUVy/wu/5Jv4X/wCwRaf+ilrqKACiiigAooooAKKKKAOX+Hf/ACA73/sPaz/6cbiuorl/h3/yA73/ALD2s/8ApxuK6igAooooAKKKKACiiigD4T/aX/5Lb4j/AO3P/wBJIaKP2l/+S2+I/wDtz/8ASSGigD6X/ZO/5NY+Df8A2T/w9/6boK9Vryr9k7/k1j4N/wDZP/D3/pugr1WgAooooAKKKKACiiigAooooAKKKKACiiigAooooA8u+EH/ACUL44f9j7af+ovodeo15d8IP+ShfHD/ALH20/8AUX0OvUaACiiigAooooAKKKKACiiigAooooAKKKKACiiigDzLxB/yct4D/wCxG8W/+nDw/XpteZeIP+TlvAf/AGI3i3/04eH69NoAKKKKACiiigAooooAKKKKACiiigAooooAKKKKAPN/j/8A8iJpf/Y7eDf/AFI9Or0ivN/j/wD8iJpf/Y7eDf8A1I9Or0igAooooAKKKKACiiigDl/hd/yTfwv/ANgi0/8A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', NULL, 201);
INSERT INTO `supervisor` (`fyp_supervisorid`, `fyp_name`, `fyp_roomno`, `fyp_programme`, `fyp_email`, `fyp_contactno`, `fyp_specialization`, `fyp_areaofinterest`, `fyp_ismoderator`, `fyp_profileimg`, `fyp_datecreated`, `fyp_userid`) VALUES
(2, 'Dr. Yvonne', 'R002', 'CS', 'y@uni.edu', '0111111112', 'Security', 'Blockchain', 1, NULL, NULL, 202),
(3, 'Mr. Zach', 'R003', 'DS', 'z@uni.edu', '0111111113', 'Data', 'Big Data', 1, NULL, NULL, 203),
(4, 'Ms. Walter', 'R004', 'AI', 'w@uni.edu', '0111111114', 'Robotics', 'Automation', 0, NULL, NULL, 204),
(5, 'Dr. Victor', 'R005', 'IT', 'v@uni.edu', '0111111115', 'Web', 'Full Stack', 1, NULL, NULL, 205),
(6, 'Ms. Uma', 'R006', 'SE', 'u@uni.edu', '0111111116', 'Testing', 'QA', 0, NULL, NULL, 206),
(7, 'Dr. Tom', 'R007', 'CS', 't@uni.edu', '0111111117', 'Network', '5G', 1, NULL, NULL, 207),
(8, 'Mr. Sarah', 'R008', 'DS', 's@uni.edu', '0111111118', 'Stats', 'Analysis', 0, NULL, NULL, 208),
(9, 'Dr. Rachel', 'R009', 'AI', 'r@uni.edu', '0111111119', 'NLP', 'Chatbots', 1, NULL, NULL, 209),
(10, 'Mr. Quinn', 'R010', 'IT', 'q@uni.edu', '0111111110', 'Cloud', 'AWS', 1, NULL, NULL, 210);

-- --------------------------------------------------------

--
-- 表的结构 `supervisor_type`
--

CREATE TABLE `supervisor_type` (
  `fyp_id` int(11) NOT NULL,
  `fyp_supervisorid` varchar(12) DEFAULT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_isfulltime` int(11) DEFAULT NULL,
  `fyp_createdby` varchar(12) DEFAULT NULL,
  `fyp_createddate` datetime DEFAULT NULL,
  `fyp_editedby` varchar(12) DEFAULT NULL,
  `fyp_editeddate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `supervisor_type`
--

INSERT INTO `supervisor_type` (`fyp_id`, `fyp_supervisorid`, `fyp_academicid`, `fyp_isfulltime`, `fyp_createdby`, `fyp_createddate`, `fyp_editedby`, `fyp_editeddate`) VALUES
(1, '1', 1, 1, 'admin', NULL, NULL, NULL),
(2, '2', 1, 1, 'admin', NULL, NULL, NULL),
(3, '3', 1, 0, 'admin', NULL, NULL, NULL),
(4, '4', 1, 1, 'admin', NULL, NULL, NULL),
(5, '5', 1, 1, 'admin', NULL, NULL, NULL),
(6, '6', 2, 1, 'admin', NULL, NULL, NULL),
(7, '7', 2, 1, 'admin', NULL, NULL, NULL),
(8, '8', 2, 0, 'admin', NULL, NULL, NULL),
(9, '9', 2, 1, 'admin', NULL, NULL, NULL),
(10, '10', 2, 1, 'admin', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `supervisor_type_history`
--

CREATE TABLE `supervisor_type_history` (
  `fyp_id` int(11) NOT NULL,
  `fyp_supervisorid` varchar(12) DEFAULT NULL,
  `fyp_isfulltime` int(11) DEFAULT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_createdby` varchar(12) DEFAULT NULL,
  `fyp_createddate` datetime DEFAULT NULL,
  `fyp_editedby` varchar(12) DEFAULT NULL,
  `fyp_editeddate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `supervisor_type_history`
--

INSERT INTO `supervisor_type_history` (`fyp_id`, `fyp_supervisorid`, `fyp_isfulltime`, `fyp_academicid`, `fyp_createdby`, `fyp_createddate`, `fyp_editedby`, `fyp_editeddate`) VALUES
(1, '1', 1, 1, 'admin', NULL, NULL, NULL),
(2, '2', 1, 1, 'admin', NULL, NULL, NULL),
(3, '3', 0, 1, 'admin', NULL, NULL, NULL),
(4, '4', 1, 1, 'admin', NULL, NULL, NULL),
(5, '5', 1, 1, 'admin', NULL, NULL, NULL),
(6, '6', 1, 2, 'admin', NULL, NULL, NULL),
(7, '7', 1, 2, 'admin', NULL, NULL, NULL),
(8, '8', 0, 2, 'admin', NULL, NULL, NULL),
(9, '9', 1, 2, 'admin', NULL, NULL, NULL),
(10, '10', 1, 2, 'admin', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `threshold_record`
--

CREATE TABLE `threshold_record` (
  `fyp_id` int(11) NOT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_mark` decimal(10,0) DEFAULT NULL,
  `fyp_createdby` varchar(12) DEFAULT NULL,
  `fyp_createddate` datetime DEFAULT NULL,
  `fyp_editedby` varchar(12) DEFAULT NULL,
  `fyp_editeddate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `threshold_record`
--

INSERT INTO `threshold_record` (`fyp_id`, `fyp_academicid`, `fyp_mark`, `fyp_createdby`, `fyp_createddate`, `fyp_editedby`, `fyp_editeddate`) VALUES
(1, 1, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(2, 2, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(3, 3, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(4, 4, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(5, 5, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(6, 6, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(7, 7, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(8, 8, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(9, 9, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL),
(10, 10, 50, 'admin', '2025-12-08 13:30:52', NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `total_mark`
--

CREATE TABLE `total_mark` (
  `fyp_studid` varchar(12) NOT NULL,
  `fyp_projectid` int(11) NOT NULL,
  `fyp_totalmark` decimal(10,3) DEFAULT NULL,
  `fyp_totalfinalsupervisor` decimal(10,3) DEFAULT NULL,
  `fyp_totalfinalmoderator` decimal(10,3) DEFAULT NULL,
  `fyp_setid` int(11) NOT NULL,
  `fyp_projectphase` int(11) DEFAULT NULL,
  `fyp_academicid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `total_mark`
--

INSERT INTO `total_mark` (`fyp_studid`, `fyp_projectid`, `fyp_totalmark`, `fyp_totalfinalsupervisor`, `fyp_totalfinalmoderator`, `fyp_setid`, `fyp_projectphase`, `fyp_academicid`) VALUES
('TP001', 1, 85.500, 86.000, 85.000, 1, 1, 1),
('TP002', 2, 75.000, 76.000, 74.000, 2, 1, 1),
('TP003', 3, 65.500, 66.000, 65.000, 3, 1, 1),
('TP004', 4, 55.000, 56.000, 54.000, 4, 1, 1),
('TP005', 5, 90.000, 91.000, 89.000, 5, 1, 1),
('TP006', 6, 82.500, 83.000, 82.000, 6, 1, 2),
('TP007', 7, 78.000, 79.000, 77.000, 7, 1, 2),
('TP008', 8, 60.000, 61.000, 59.000, 8, 1, 2),
('TP009', 9, 88.000, 89.000, 87.000, 9, 1, 2),
('TP010', 10, 92.000, 93.000, 91.000, 10, 1, 2);

-- --------------------------------------------------------

--
-- 表的结构 `total_mark_item_mark`
--

CREATE TABLE `total_mark_item_mark` (
  `fyp_totalmarkid` int(11) NOT NULL,
  `fyp_itemmarkid` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `total_mark_item_mark`
--

INSERT INTO `total_mark_item_mark` (`fyp_totalmarkid`, `fyp_itemmarkid`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5),
(6, 6),
(7, 7),
(8, 8),
(9, 9),
(10, 10);

-- --------------------------------------------------------

--
-- 表的结构 `user`
--

CREATE TABLE `user` (
  `fyp_userid` int(11) NOT NULL,
  `fyp_username` varchar(50) NOT NULL,
  `fyp_passwordhash` varchar(255) NOT NULL,
  `fyp_usertype` enum('student','lecturer','coordinator') NOT NULL,
  `fyp_datecreated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `user`
--

INSERT INTO `user` (`fyp_userid`, `fyp_username`, `fyp_passwordhash`, `fyp_usertype`, `fyp_datecreated`) VALUES
(1, 'hisham', '1234', 'coordinator', '2025-12-01 13:02:59'),
(2, 'chong', '1234', 'student', '2025-12-01 14:05:26'),
(101, 'std_alice', 'hash1', 'student', '2025-12-08 13:30:52'),
(102, 'std_bob', 'hash2', 'student', '2025-12-08 13:30:52'),
(103, 'std_charlie', 'hash3', 'student', '2025-12-08 13:30:52'),
(104, 'std_david', 'hash4', 'student', '2025-12-08 13:30:52'),
(105, 'std_eve', 'hash5', 'student', '2025-12-08 13:30:52'),
(106, 'std_frank', 'hash6', 'student', '2025-12-08 13:30:52'),
(107, 'std_grace', 'hash7', 'student', '2025-12-08 13:30:52'),
(108, 'std_heidi', 'hash8', 'student', '2025-12-08 13:30:52'),
(109, 'std_ivan', 'hash9', 'student', '2025-12-08 13:30:52'),
(110, 'std_judy', 'hash10', 'student', '2025-12-08 13:30:52'),
(201, 'sup_xavier', 'hash11', 'lecturer', '2025-12-08 13:30:52'),
(202, 'sup_yvonne', 'hash12', 'lecturer', '2025-12-08 13:30:52'),
(203, 'sup_zach', 'hash13', 'lecturer', '2025-12-08 13:30:52'),
(204, 'sup_walter', 'hash14', 'lecturer', '2025-12-08 13:30:52'),
(205, 'sup_victor', 'hash15', 'lecturer', '2025-12-08 13:30:52'),
(206, 'sup_uma', 'hash16', 'lecturer', '2025-12-08 13:30:52'),
(207, 'sup_tom', 'hash17', 'lecturer', '2025-12-08 13:30:52'),
(208, 'sup_sarah', 'hash18', 'lecturer', '2025-12-08 13:30:52'),
(209, 'sup_rachel', 'hash19', 'lecturer', '2025-12-08 13:30:52'),
(210, 'sup_quinn', 'hash20', 'lecturer', '2025-12-08 13:30:52');

-- --------------------------------------------------------

--
-- 表的结构 `workload_formula`
--

CREATE TABLE `workload_formula` (
  `fyp_id` int(11) NOT NULL,
  `fyp_academicid` int(11) DEFAULT NULL,
  `fyp_formula` varchar(100) DEFAULT NULL,
  `fyp_isfulltime` int(11) DEFAULT NULL,
  `fyp_semester` varchar(32) DEFAULT NULL,
  `fyp_createdby` varchar(12) DEFAULT NULL,
  `fyp_createddate` datetime DEFAULT NULL,
  `fyp_editedby` varchar(12) DEFAULT NULL,
  `fyp_editeddate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 转存表中的数据 `workload_formula`
--

INSERT INTO `workload_formula` (`fyp_id`, `fyp_academicid`, `fyp_formula`, `fyp_isfulltime`, `fyp_semester`, `fyp_createdby`, `fyp_createddate`, `fyp_editedby`, `fyp_editeddate`) VALUES
(1, 1, 'Standard', 1, '1', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(2, 1, 'Standard', 1, '2', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(3, 2, 'Research', 1, '1', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(4, 2, 'Research', 1, '2', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(5, 3, 'Reduced', 0, '1', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(6, 3, 'Reduced', 0, '2', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(7, 4, 'Intensive', 1, '1', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(8, 4, 'Intensive', 1, '2', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(9, 5, 'Standard', 1, '1', 'admin', '2025-12-08 13:30:52', NULL, NULL),
(10, 5, 'Standard', 1, '2', 'admin', '2025-12-08 13:30:52', NULL, NULL);

--
-- 转储表的索引
--

--
-- 表的索引 `academic_year`
--
ALTER TABLE `academic_year`
  ADD PRIMARY KEY (`fyp_academicid`);

--
-- 表的索引 `announcement`
--
ALTER TABLE `announcement`
  ADD PRIMARY KEY (`fyp_annouceid`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `appointment_meeting`
--
ALTER TABLE `appointment_meeting`
  ADD PRIMARY KEY (`fyp_appointmentid`),
  ADD KEY `fyp_studid` (`fyp_studid`),
  ADD KEY `fyp_scheduleid` (`fyp_scheduleid`);

--
-- 表的索引 `assessment_criteria`
--
ALTER TABLE `assessment_criteria`
  ADD PRIMARY KEY (`fyp_assessmentcriteriaid`);

--
-- 表的索引 `assessment_items`
--
ALTER TABLE `assessment_items`
  ADD PRIMARY KEY (`item_id`);

--
-- 表的索引 `assignment`
--
ALTER TABLE `assignment`
  ADD PRIMARY KEY (`fyp_assignmentid`),
  ADD KEY `fyp_supervisorid` (`fyp_supervisorid`);

--
-- 表的索引 `assignment_submission`
--
ALTER TABLE `assignment_submission`
  ADD PRIMARY KEY (`fyp_submissionid`),
  ADD KEY `fyp_assignmentid` (`fyp_assignmentid`),
  ADD KEY `fyp_studid` (`fyp_studid`);

--
-- 表的索引 `attachment`
--
ALTER TABLE `attachment`
  ADD PRIMARY KEY (`fyp_attachid`);

--
-- 表的索引 `criteria_mark`
--
ALTER TABLE `criteria_mark`
  ADD PRIMARY KEY (`fyp_criteriamarkid`),
  ADD KEY `fyp_criteriaid` (`fyp_criteriaid`);

--
-- 表的索引 `document`
--
ALTER TABLE `document`
  ADD PRIMARY KEY (`fyp_docid`),
  ADD KEY `fyp_pairingid` (`fyp_pairingid`),
  ADD KEY `fyp_studid` (`fyp_studid`);

--
-- 表的索引 `fyp_maintenance`
--
ALTER TABLE `fyp_maintenance`
  ADD PRIMARY KEY (`fyp_maintainid`);

--
-- 表的索引 `fyp_registration`
--
ALTER TABLE `fyp_registration`
  ADD PRIMARY KEY (`fyp_regid`);

--
-- 表的索引 `grade_criteria`
--
ALTER TABLE `grade_criteria`
  ADD PRIMARY KEY (`fyp_id`);

--
-- 表的索引 `grade_maintenance`
--
ALTER TABLE `grade_maintenance`
  ADD PRIMARY KEY (`fyp_id`),
  ADD KEY `fyp_gradecriteriaid` (`fyp_gradecriteriaid`);

--
-- 表的索引 `group_request`
--
ALTER TABLE `group_request`
  ADD PRIMARY KEY (`request_id`);

--
-- 表的索引 `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`fyp_itemid`);

--
-- 表的索引 `item_mark`
--
ALTER TABLE `item_mark`
  ADD PRIMARY KEY (`fyp_itemmarkid`),
  ADD KEY `fyp_itemid` (`fyp_itemid`);

--
-- 表的索引 `item_marking_criteria`
--
ALTER TABLE `item_marking_criteria`
  ADD PRIMARY KEY (`fyp_itemid`,`fyp_criteriaid`),
  ADD KEY `fyp_criteriaid` (`fyp_criteriaid`);

--
-- 表的索引 `item_mark_criteria_mark`
--
ALTER TABLE `item_mark_criteria_mark`
  ADD PRIMARY KEY (`fyp_itemmarkid`,`fyp_criteriamarkid`),
  ADD KEY `fyp_criteriamarkid` (`fyp_criteriamarkid`);

--
-- 表的索引 `last_activity`
--
ALTER TABLE `last_activity`
  ADD PRIMARY KEY (`fyp_activityid`),
  ADD KEY `fyp_pairingid` (`fyp_pairingid`),
  ADD KEY `fyp_studid` (`fyp_studid`);

--
-- 表的索引 `marking_criteria`
--
ALTER TABLE `marking_criteria`
  ADD PRIMARY KEY (`fyp_criteriaid`);

--
-- 表的索引 `marking_criteria_assessment_criteria`
--
ALTER TABLE `marking_criteria_assessment_criteria`
  ADD PRIMARY KEY (`fyp_criteriaid`,`fyp_assessmentcriteriaid`),
  ADD KEY `fyp_assessmentcriteriaid` (`fyp_assessmentcriteriaid`);

--
-- 表的索引 `moderation_criteria`
--
ALTER TABLE `moderation_criteria`
  ADD PRIMARY KEY (`fyp_mdcriteriaid`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `pairing`
--
ALTER TABLE `pairing`
  ADD PRIMARY KEY (`fyp_pairingid`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `programme`
--
ALTER TABLE `programme`
  ADD PRIMARY KEY (`fyp_progid`);

--
-- 表的索引 `project`
--
ALTER TABLE `project`
  ADD PRIMARY KEY (`fyp_projectid`);

--
-- 表的索引 `project_request`
--
ALTER TABLE `project_request`
  ADD PRIMARY KEY (`fyp_requestid`),
  ADD KEY `fyp_academicid` (`fyp_academicid`),
  ADD KEY `fyp_studid` (`fyp_studid`),
  ADD KEY `fyp_pairingid` (`fyp_pairingid`),
  ADD KEY `fyp_projectid` (`fyp_projectid`);

--
-- 表的索引 `quota`
--
ALTER TABLE `quota`
  ADD PRIMARY KEY (`fyp_quotaid`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `schedule_meeting`
--
ALTER TABLE `schedule_meeting`
  ADD PRIMARY KEY (`fyp_scheduleid`),
  ADD KEY `fyp_supervisorid` (`fyp_supervisorid`);

--
-- 表的索引 `set`
--
ALTER TABLE `set`
  ADD PRIMARY KEY (`fyp_setid`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `set_item`
--
ALTER TABLE `set_item`
  ADD PRIMARY KEY (`fyp_setid`,`fyp_itemid`),
  ADD KEY `fyp_itemid` (`fyp_itemid`);

--
-- 表的索引 `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`fyp_studid`),
  ADD KEY `fyp_academicid` (`fyp_academicid`),
  ADD KEY `fyp_progid` (`fyp_progid`),
  ADD KEY `fyp_userid` (`fyp_userid`);

--
-- 表的索引 `student_group`
--
ALTER TABLE `student_group`
  ADD PRIMARY KEY (`group_id`);

--
-- 表的索引 `student_marks`
--
ALTER TABLE `student_marks`
  ADD PRIMARY KEY (`mark_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `student_id` (`student_id`);

--
-- 表的索引 `student_moderation`
--
ALTER TABLE `student_moderation`
  ADD PRIMARY KEY (`fyp_studmdid`),
  ADD KEY `fyp_studid` (`fyp_studid`),
  ADD KEY `fyp_mdcriteriaid` (`fyp_mdcriteriaid`);

--
-- 表的索引 `student_num`
--
ALTER TABLE `student_num`
  ADD PRIMARY KEY (`fyp_studnumid`),
  ADD KEY `fyp_progid` (`fyp_progid`);

--
-- 表的索引 `supervised_programme`
--
ALTER TABLE `supervised_programme`
  ADD PRIMARY KEY (`fyp_spid`),
  ADD KEY `fyp_quotaid` (`fyp_quotaid`);

--
-- 表的索引 `supervisor`
--
ALTER TABLE `supervisor`
  ADD PRIMARY KEY (`fyp_supervisorid`),
  ADD KEY `fyp_userid` (`fyp_userid`);

--
-- 表的索引 `supervisor_type`
--
ALTER TABLE `supervisor_type`
  ADD PRIMARY KEY (`fyp_id`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `supervisor_type_history`
--
ALTER TABLE `supervisor_type_history`
  ADD PRIMARY KEY (`fyp_id`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `threshold_record`
--
ALTER TABLE `threshold_record`
  ADD PRIMARY KEY (`fyp_id`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `total_mark`
--
ALTER TABLE `total_mark`
  ADD PRIMARY KEY (`fyp_studid`,`fyp_projectid`,`fyp_setid`),
  ADD KEY `fyp_projectid` (`fyp_projectid`),
  ADD KEY `fyp_setid` (`fyp_setid`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 表的索引 `total_mark_item_mark`
--
ALTER TABLE `total_mark_item_mark`
  ADD PRIMARY KEY (`fyp_totalmarkid`),
  ADD KEY `fyp_itemmarkid` (`fyp_itemmarkid`);

--
-- 表的索引 `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`fyp_userid`),
  ADD UNIQUE KEY `fyp_username` (`fyp_username`);

--
-- 表的索引 `workload_formula`
--
ALTER TABLE `workload_formula`
  ADD PRIMARY KEY (`fyp_id`),
  ADD KEY `fyp_academicid` (`fyp_academicid`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `announcement`
--
ALTER TABLE `announcement`
  MODIFY `fyp_annouceid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- 使用表AUTO_INCREMENT `appointment_meeting`
--
ALTER TABLE `appointment_meeting`
  MODIFY `fyp_appointmentid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `assessment_items`
--
ALTER TABLE `assessment_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `assignment`
--
ALTER TABLE `assignment`
  MODIFY `fyp_assignmentid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `assignment_submission`
--
ALTER TABLE `assignment_submission`
  MODIFY `fyp_submissionid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `fyp_registration`
--
ALTER TABLE `fyp_registration`
  MODIFY `fyp_regid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- 使用表AUTO_INCREMENT `group_request`
--
ALTER TABLE `group_request`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- 使用表AUTO_INCREMENT `pairing`
--
ALTER TABLE `pairing`
  MODIFY `fyp_pairingid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- 使用表AUTO_INCREMENT `project`
--
ALTER TABLE `project`
  MODIFY `fyp_projectid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- 使用表AUTO_INCREMENT `project_request`
--
ALTER TABLE `project_request`
  MODIFY `fyp_requestid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- 使用表AUTO_INCREMENT `schedule_meeting`
--
ALTER TABLE `schedule_meeting`
  MODIFY `fyp_scheduleid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `student_group`
--
ALTER TABLE `student_group`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `student_marks`
--
ALTER TABLE `student_marks`
  MODIFY `mark_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `user`
--
ALTER TABLE `user`
  MODIFY `fyp_userid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=211;

--
-- 限制导出的表
--

--
-- 限制表 `announcement`
--
ALTER TABLE `announcement`
  ADD CONSTRAINT `announcement_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `appointment_meeting`
--
ALTER TABLE `appointment_meeting`
  ADD CONSTRAINT `appointment_meeting_ibfk_1` FOREIGN KEY (`fyp_studid`) REFERENCES `student` (`fyp_studid`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_meeting_ibfk_2` FOREIGN KEY (`fyp_scheduleid`) REFERENCES `schedule_meeting` (`fyp_scheduleid`) ON DELETE CASCADE;

--
-- 限制表 `assignment`
--
ALTER TABLE `assignment`
  ADD CONSTRAINT `assignment_ibfk_1` FOREIGN KEY (`fyp_supervisorid`) REFERENCES `supervisor` (`fyp_supervisorid`) ON DELETE CASCADE;

--
-- 限制表 `assignment_submission`
--
ALTER TABLE `assignment_submission`
  ADD CONSTRAINT `assignment_submission_ibfk_1` FOREIGN KEY (`fyp_assignmentid`) REFERENCES `assignment` (`fyp_assignmentid`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_submission_ibfk_2` FOREIGN KEY (`fyp_studid`) REFERENCES `student` (`fyp_studid`) ON DELETE CASCADE;

--
-- 限制表 `criteria_mark`
--
ALTER TABLE `criteria_mark`
  ADD CONSTRAINT `criteria_mark_ibfk_1` FOREIGN KEY (`fyp_criteriaid`) REFERENCES `marking_criteria` (`fyp_criteriaid`);

--
-- 限制表 `document`
--
ALTER TABLE `document`
  ADD CONSTRAINT `document_ibfk_1` FOREIGN KEY (`fyp_pairingid`) REFERENCES `pairing` (`fyp_pairingid`),
  ADD CONSTRAINT `document_ibfk_2` FOREIGN KEY (`fyp_studid`) REFERENCES `student` (`fyp_studid`);

--
-- 限制表 `grade_maintenance`
--
ALTER TABLE `grade_maintenance`
  ADD CONSTRAINT `grade_maintenance_ibfk_1` FOREIGN KEY (`fyp_gradecriteriaid`) REFERENCES `grade_criteria` (`fyp_id`);

--
-- 限制表 `item_mark`
--
ALTER TABLE `item_mark`
  ADD CONSTRAINT `item_mark_ibfk_1` FOREIGN KEY (`fyp_itemid`) REFERENCES `item` (`fyp_itemid`);

--
-- 限制表 `item_marking_criteria`
--
ALTER TABLE `item_marking_criteria`
  ADD CONSTRAINT `item_marking_criteria_ibfk_1` FOREIGN KEY (`fyp_itemid`) REFERENCES `item` (`fyp_itemid`),
  ADD CONSTRAINT `item_marking_criteria_ibfk_2` FOREIGN KEY (`fyp_criteriaid`) REFERENCES `marking_criteria` (`fyp_criteriaid`);

--
-- 限制表 `item_mark_criteria_mark`
--
ALTER TABLE `item_mark_criteria_mark`
  ADD CONSTRAINT `item_mark_criteria_mark_ibfk_1` FOREIGN KEY (`fyp_itemmarkid`) REFERENCES `item_mark` (`fyp_itemmarkid`),
  ADD CONSTRAINT `item_mark_criteria_mark_ibfk_2` FOREIGN KEY (`fyp_criteriamarkid`) REFERENCES `criteria_mark` (`fyp_criteriamarkid`);

--
-- 限制表 `last_activity`
--
ALTER TABLE `last_activity`
  ADD CONSTRAINT `last_activity_ibfk_1` FOREIGN KEY (`fyp_pairingid`) REFERENCES `pairing` (`fyp_pairingid`),
  ADD CONSTRAINT `last_activity_ibfk_2` FOREIGN KEY (`fyp_studid`) REFERENCES `student` (`fyp_studid`);

--
-- 限制表 `marking_criteria_assessment_criteria`
--
ALTER TABLE `marking_criteria_assessment_criteria`
  ADD CONSTRAINT `marking_criteria_assessment_criteria_ibfk_1` FOREIGN KEY (`fyp_criteriaid`) REFERENCES `marking_criteria` (`fyp_criteriaid`),
  ADD CONSTRAINT `marking_criteria_assessment_criteria_ibfk_2` FOREIGN KEY (`fyp_assessmentcriteriaid`) REFERENCES `assessment_criteria` (`fyp_assessmentcriteriaid`);

--
-- 限制表 `moderation_criteria`
--
ALTER TABLE `moderation_criteria`
  ADD CONSTRAINT `moderation_criteria_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `pairing`
--
ALTER TABLE `pairing`
  ADD CONSTRAINT `pairing_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `project_request`
--
ALTER TABLE `project_request`
  ADD CONSTRAINT `project_request_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`),
  ADD CONSTRAINT `project_request_ibfk_2` FOREIGN KEY (`fyp_studid`) REFERENCES `student` (`fyp_studid`),
  ADD CONSTRAINT `project_request_ibfk_3` FOREIGN KEY (`fyp_pairingid`) REFERENCES `pairing` (`fyp_pairingid`),
  ADD CONSTRAINT `project_request_ibfk_4` FOREIGN KEY (`fyp_projectid`) REFERENCES `project` (`fyp_projectid`);

--
-- 限制表 `quota`
--
ALTER TABLE `quota`
  ADD CONSTRAINT `quota_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `schedule_meeting`
--
ALTER TABLE `schedule_meeting`
  ADD CONSTRAINT `schedule_meeting_ibfk_1` FOREIGN KEY (`fyp_supervisorid`) REFERENCES `supervisor` (`fyp_supervisorid`) ON DELETE CASCADE;

--
-- 限制表 `set`
--
ALTER TABLE `set`
  ADD CONSTRAINT `set_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `set_item`
--
ALTER TABLE `set_item`
  ADD CONSTRAINT `set_item_ibfk_1` FOREIGN KEY (`fyp_setid`) REFERENCES `set` (`fyp_setid`),
  ADD CONSTRAINT `set_item_ibfk_2` FOREIGN KEY (`fyp_itemid`) REFERENCES `item` (`fyp_itemid`);

--
-- 限制表 `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`),
  ADD CONSTRAINT `student_ibfk_2` FOREIGN KEY (`fyp_progid`) REFERENCES `programme` (`fyp_progid`),
  ADD CONSTRAINT `student_ibfk_3` FOREIGN KEY (`fyp_userid`) REFERENCES `user` (`fyp_userid`);

--
-- 限制表 `student_marks`
--
ALTER TABLE `student_marks`
  ADD CONSTRAINT `student_marks_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `assessment_items` (`item_id`),
  ADD CONSTRAINT `student_marks_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student` (`fyp_studid`);

--
-- 限制表 `student_moderation`
--
ALTER TABLE `student_moderation`
  ADD CONSTRAINT `student_moderation_ibfk_1` FOREIGN KEY (`fyp_studid`) REFERENCES `student` (`fyp_studid`),
  ADD CONSTRAINT `student_moderation_ibfk_2` FOREIGN KEY (`fyp_mdcriteriaid`) REFERENCES `moderation_criteria` (`fyp_mdcriteriaid`);

--
-- 限制表 `student_num`
--
ALTER TABLE `student_num`
  ADD CONSTRAINT `student_num_ibfk_1` FOREIGN KEY (`fyp_progid`) REFERENCES `programme` (`fyp_progid`);

--
-- 限制表 `supervised_programme`
--
ALTER TABLE `supervised_programme`
  ADD CONSTRAINT `supervised_programme_ibfk_1` FOREIGN KEY (`fyp_quotaid`) REFERENCES `quota` (`fyp_quotaid`);

--
-- 限制表 `supervisor`
--
ALTER TABLE `supervisor`
  ADD CONSTRAINT `supervisor_ibfk_1` FOREIGN KEY (`fyp_userid`) REFERENCES `user` (`fyp_userid`);

--
-- 限制表 `supervisor_type`
--
ALTER TABLE `supervisor_type`
  ADD CONSTRAINT `supervisor_type_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `supervisor_type_history`
--
ALTER TABLE `supervisor_type_history`
  ADD CONSTRAINT `supervisor_type_history_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `threshold_record`
--
ALTER TABLE `threshold_record`
  ADD CONSTRAINT `threshold_record_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `total_mark`
--
ALTER TABLE `total_mark`
  ADD CONSTRAINT `total_mark_ibfk_1` FOREIGN KEY (`fyp_studid`) REFERENCES `student` (`fyp_studid`),
  ADD CONSTRAINT `total_mark_ibfk_2` FOREIGN KEY (`fyp_projectid`) REFERENCES `project` (`fyp_projectid`),
  ADD CONSTRAINT `total_mark_ibfk_3` FOREIGN KEY (`fyp_setid`) REFERENCES `set` (`fyp_setid`),
  ADD CONSTRAINT `total_mark_ibfk_4` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);

--
-- 限制表 `total_mark_item_mark`
--
ALTER TABLE `total_mark_item_mark`
  ADD CONSTRAINT `total_mark_item_mark_ibfk_1` FOREIGN KEY (`fyp_itemmarkid`) REFERENCES `item_mark` (`fyp_itemmarkid`);

--
-- 限制表 `workload_formula`
--
ALTER TABLE `workload_formula`
  ADD CONSTRAINT `workload_formula_ibfk_1` FOREIGN KEY (`fyp_academicid`) REFERENCES `academic_year` (`fyp_academicid`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
