-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 09, 2026 at 05:35 PM
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
-- Database: `ubook`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `admin_id`, `booking_id`, `action`, `old_status`, `new_status`, `created_at`) VALUES
(1, 0, 25, 'delete', 'pending', NULL, '2026-07-05 10:46:32'),
(2, 0, 30, 'status_change', 'pending', 'rejected', '2026-07-05 11:09:15'),
(3, 0, 72, 'auto_reject_expired', 'pending', 'rejected', '2026-07-05 12:00:19'),
(4, 0, 44, 'status_change', 'pending', 'confirmed', '2026-07-05 13:02:13'),
(5, 0, 32, 'status_change', 'pending', 'rejected', '2026-07-05 13:02:40'),
(6, 0, 28, 'status_change', 'pending', 'rejected', '2026-07-05 13:04:12'),
(7, 0, 55, 'status_change', 'pending', 'rejected', '2026-07-05 13:07:15'),
(8, 0, 54, 'status_change', 'confirmed', 'rejected', '2026-07-05 13:12:06'),
(9, 0, 9, 'status_change', 'rejected', 'rejected', '2026-07-05 13:13:52'),
(10, 0, 1, 'status_change', 'rejected', 'rejected', '2026-07-05 13:15:13'),
(11, 0, 16, 'auto_reject_expired', 'pending', 'rejected', '2026-07-07 04:22:14'),
(12, 0, 57, 'auto_reject_expired', 'pending', 'rejected', '2026-07-07 04:22:18'),
(13, 0, 76, 'auto_reject_expired', 'pending', 'rejected', '2026-07-07 04:22:22'),
(14, 0, 77, 'auto_reject_expired', 'pending', 'rejected', '2026-07-07 04:22:26'),
(15, 0, 42, 'auto_reject_expired', 'pending', 'rejected', '2026-07-07 05:05:24'),
(16, 0, 30, 'delete', 'rejected', NULL, '2026-07-08 12:42:40'),
(17, 0, 19, 'auto_reject_expired', 'pending', 'rejected', '2026-07-08 14:23:04'),
(18, 0, 78, 'auto_reject_expired', 'pending', 'rejected', '2026-07-09 10:33:22');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `venue_id` varchar(20) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `duration_hours` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `status` enum('pending','confirmed','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `student_name`, `venue_id`, `booking_date`, `start_time`, `duration_hours`, `comment`, `status`, `created_at`) VALUES
(1, '1221103276', 'Aun', 'bas101', '2026-06-29', '10:00:00', 2, 'Need extra balls', 'rejected', '2026-06-28 06:28:15'),
(2, '1221101651', 'Wenhui', 'mme123', '2026-06-30', '14:00:00', 3, 'Projector required', 'rejected', '2026-06-28 06:28:15'),
(3, '0', 'AI Assistant', 'lec131', '2026-07-01', '09:00:00', 1, 'Demo booking for testing', 'rejected', '2026-06-28 06:28:15'),
(4, '1221101649', 'Kai Qing', 'vol789', '2026-06-29', '16:00:00', 2, 'Net check', 'rejected', '2026-06-28 06:28:15'),
(5, '241UT24100', 'Dayana', 'fld112', '2026-07-02', '08:30:00', 1, 'Morning football training', 'confirmed', '2026-06-28 06:28:15'),
(6, '1221103276', 'Aun', 'mme123', '2026-07-01', '16:00:00', 3, '', 'rejected', '2026-06-29 16:37:39'),
(7, '1221103276', 'Aun', 'cpl616', '2026-07-01', '08:00:00', 3, '', 'rejected', '2026-06-29 16:47:33'),
(8, '1221103276', 'Aun', 'mme123', '2026-08-31', '20:07:00', 4, '', 'rejected', '2026-06-29 17:02:01'),
(9, '1221103276', 'Aun', 'fld112', '2026-06-30', '14:00:00', 2, '', 'rejected', '2026-06-29 17:44:08'),
(10, '1221103276', 'Aun', 'cpl616', '2026-07-05', '11:00:00', 3, 'Need 30 PCs for programming lab', 'rejected', '2026-06-29 18:22:58'),
(11, '1221103276', 'Aun', 'fld112', '2026-07-14', '07:00:00', 2, 'Early morning football training', 'pending', '2026-06-29 18:22:58'),
(12, '1221101651', 'Wenhui', 'exm456', '2026-07-07', '09:00:00', 2, 'Mock exam for 50 students', 'confirmed', '2026-06-29 18:22:58'),
(13, '1221101651', 'Wenhui', 'sml415', '2026-07-03', '15:00:00', 4, 'IoT workshop session', 'rejected', '2026-06-29 18:22:58'),
(14, '1221101649', 'Kai Qing', 'vol789', '2026-07-03', '17:00:00', 2, 'Evening volleyball practice', 'rejected', '2026-06-29 18:22:58'),
(15, '1221101649', 'Kai Qing', 'lec131', '2026-07-09', '13:00:00', 1, 'Guest speaker presentation', 'confirmed', '2026-06-29 18:22:58'),
(16, '241UT24100', 'Dayana', 'mme123', '2026-07-06', '10:00:00', 3, 'Annual sports day briefing', 'rejected', '2026-06-29 18:22:58'),
(17, '241UT24100', 'Dayana', 'bas101', '2026-07-04', '08:00:00', 1, 'Basketball tryouts', 'confirmed', '2026-06-29 18:22:58'),
(18, 'admin_booking', 'Booking Administrator', 'lec131', '2026-07-10', '14:00:00', 2, 'Admin team meeting', 'confirmed', '2026-06-29 18:22:58'),
(19, 'admin_review', 'Review Administrator', 'exm456', '2026-07-08', '16:00:00', 1, 'Review panel session', 'rejected', '2026-06-29 18:22:58'),
(20, '0', 'AI Assistant', 'sml415', '2026-07-02', '12:00:00', 2, 'AI demo showcase', 'rejected', '2026-06-29 18:22:58'),
(21, '0', 'AI Assistant', 'bas101', '2026-07-15', '18:00:00', 2, 'Staff vs Students basketball match', 'confirmed', '2026-06-29 18:22:58'),
(22, '1221103276', 'Aun', 'mme123', '2026-08-31', '10:00:00', 3, 'National Day celebration event – booking the main hall for student gathering.', 'rejected', '2026-06-29 18:24:50'),
(23, '1221103276', 'Aun', 'fld112', '2026-08-31', '15:00:00', 2, 'Football friendly match on National Day.', 'pending', '2026-06-29 18:24:50'),
(24, '1221103276', 'Aun', 'lec131', '2026-09-16', '09:00:00', 4, 'Malaysia Day seminar – talks on unity and culture.', 'rejected', '2026-06-29 18:24:50'),
(26, '1221103276', 'Aun', 'sml415', '2026-12-25', '11:00:00', 2, 'Christmas coding workshop – building holiday-themed apps.', 'rejected', '2026-06-29 18:24:50'),
(27, '1221103276', 'Aun', 'exm456', '2026-12-25', '13:00:00', 1, 'Christmas choir practice in exam hall.', 'confirmed', '2026-06-29 18:24:50'),
(28, '1221103276', 'Aun', 'vol789', '2027-01-01', '08:00:00', 3, 'New Year volleyball marathon.', 'rejected', '2026-06-29 18:24:50'),
(29, '1221103276', 'Aun', 'cpl616', '2027-01-01', '12:00:00', 2, 'New Year gaming tournament in computer lab.', 'confirmed', '2026-06-29 18:24:50'),
(31, '1221103276', 'Aun', 'lec131', '2027-02-06', '15:00:00', 2, 'Lion dance practice in lecture hall.', 'confirmed', '2026-06-29 18:24:50'),
(32, '1221103276', 'Aun', 'fld112', '2027-05-01', '07:00:00', 1, 'Labour Day morning football training.', 'rejected', '2026-06-29 18:24:50'),
(33, '1221103276', 'Aun', 'bas101', '2027-05-01', '16:00:00', 2, 'Labour Day basketball match between staff and students.', 'confirmed', '2026-06-29 18:24:50'),
(34, '1221103276', 'Aun', 'bas101', '2026-07-01', '09:00:00', 2, 'Morning basketball practice', 'confirmed', '2026-06-29 18:37:49'),
(35, '1221103276', 'Aun', 'cpl616', '2026-07-01', '14:00:00', 3, 'Coding club session', 'rejected', '2026-06-29 18:37:49'),
(36, '1221103276', 'Aun', 'lec131', '2026-07-02', '10:00:00', 1, 'Guest lecture prep', 'confirmed', '2026-06-29 18:37:49'),
(37, '1221103276', 'Aun', 'fld112', '2026-07-02', '17:00:00', 2, 'Evening football with friends', 'rejected', '2026-06-29 18:37:49'),
(38, '1221103276', 'Aun', 'mme123', '2026-07-03', '08:00:00', 4, 'All-day seminar on student leadership', 'confirmed', '2026-06-29 18:37:49'),
(39, '1221103276', 'Aun', 'vol789', '2026-07-03', '13:00:00', 2, 'Volleyball practice', 'rejected', '2026-06-29 18:37:49'),
(40, '1221103276', 'Aun', 'exm456', '2026-07-04', '11:00:00', 2, 'Mock exam for 40 students', 'confirmed', '2026-06-29 18:37:49'),
(41, '1221103276', 'Aun', 'sml415', '2026-07-04', '15:00:00', 1, 'VR demo session', 'rejected', '2026-06-29 18:37:49'),
(42, '1221103276', 'Aun', 'bas101', '2026-07-07', '07:00:00', 1, 'Early bird basketball', 'rejected', '2026-06-29 18:37:49'),
(43, '1221103276', 'Aun', 'cpl616', '2026-07-07', '16:00:00', 2, 'IT support workshop', 'confirmed', '2026-06-29 18:37:49'),
(44, '1221103276', 'Aun', 'lec131', '2026-07-08', '12:00:00', 3, 'Study group for finals', 'confirmed', '2026-06-29 18:37:49'),
(45, '1221103276', 'Aun', 'fld112', '2026-07-08', '09:00:00', 2, 'Football tournament', 'confirmed', '2026-06-29 18:37:49'),
(46, '1221103276', 'Aun', 'mme123', '2026-07-09', '10:00:00', 2, 'Cultural festival planning', 'rejected', '2026-06-29 18:37:49'),
(47, '1221103276', 'Aun', 'vol789', '2026-07-09', '18:00:00', 2, 'Sunset volleyball', 'confirmed', '2026-06-29 18:37:49'),
(48, '1221103276', 'Aun', 'exm456', '2026-07-10', '08:00:00', 1, 'Morning exam invigilation', 'pending', '2026-06-29 18:37:49'),
(49, '1221103276', 'Aun', 'sml415', '2026-07-10', '14:00:00', 4, 'IoT hackathon preparation', 'rejected', '2026-06-29 18:37:49'),
(50, '1221103276', 'Aun', 'bas101', '2026-07-05', '10:00:00', 2, 'Basketball – slot 1', 'rejected', '2026-06-29 18:37:49'),
(51, '1221103276', 'Aun', 'bas101', '2026-07-05', '12:00:00', 2, 'Basketball – slot 2 (adjacent, non-overlap)', 'rejected', '2026-06-29 18:37:49'),
(52, '1221103276', 'Aun', 'bas101', '2026-07-05', '14:00:00', 2, 'Basketball – slot 3 (adjacent)', 'confirmed', '2026-06-29 18:37:49'),
(53, '1221103276', 'Aun', 'bas101', '2026-07-05', '11:00:00', 1, 'OVERLAP TEST – should conflict', 'rejected', '2026-06-29 18:37:49'),
(54, '1221103276', 'Aun', 'lec131', '2026-07-06', '09:00:00', 2, 'Lecture hall morning', 'rejected', '2026-06-29 18:37:49'),
(55, '1221103276', 'Aun', 'cpl616', '2026-07-06', '12:00:00', 2, 'Computer lab afternoon', 'rejected', '2026-06-29 18:37:49'),
(56, '1221103276', 'Aun', 'fld112', '2026-07-06', '15:00:00', 2, 'Field late afternoon', 'confirmed', '2026-06-29 18:37:49'),
(57, '1221103276', 'Aun', 'vol789', '2026-07-06', '17:00:00', 1, 'Volleyball evening', 'rejected', '2026-06-29 18:37:49'),
(58, '1221103276', 'Aun', 'mme123', '2026-07-12', '09:00:00', 4, 'Full morning workshop', 'confirmed', '2026-06-29 18:37:49'),
(59, '1221103276', 'Aun', 'exm456', '2026-07-12', '14:00:00', 4, 'Afternoon exam marathon', 'confirmed', '2026-06-29 18:37:49'),
(60, '1221103276', 'Aun', 'bas101', '2026-07-13', '08:00:00', 3, 'Saturday basketball league', 'confirmed', '2026-06-29 18:37:49'),
(61, '1221103276', 'Aun', 'fld112', '2026-07-14', '10:00:00', 2, 'Sunday football fun', 'pending', '2026-06-29 18:37:49'),
(62, '1221103276', 'Aun', 'sml415', '2026-07-14', '14:00:00', 2, 'Sunday coding workshop', 'confirmed', '2026-06-29 18:37:49'),
(63, '1221103276', 'Aun', 'lec131', '2026-07-15', '11:00:00', 1, 'Need HDMI cable for projector', 'pending', '2026-06-29 18:37:49'),
(64, '1221103276', 'Aun', 'cpl616', '2026-07-15', '13:00:00', 2, 'Require 20 PCs for workshop', 'confirmed', '2026-06-29 18:37:49'),
(65, '1221103276', 'Aun', 'vol789', '2026-07-16', '16:00:00', 2, 'Volleyball net needs repair – please check', 'pending', '2026-06-29 18:37:49'),
(66, '1221103276', 'Aun', 'mme123', '2026-07-30', '10:00:00', 3, 'Planning for next month’s festival', 'pending', '2026-06-29 18:37:49'),
(67, '1221103276', 'Aun', 'exm456', '2026-07-30', '14:00:00', 2, 'Final exam scheduling', 'confirmed', '2026-06-29 18:37:49'),
(68, '1221103276', 'Aun', 'bas101', '2026-08-14', '08:00:00', 4, 'Pre-season basketball training camp', 'pending', '2026-06-29 18:37:49'),
(69, '1221103276', 'Aun', 'fld112', '2026-06-29', '10:00:00', 2, '', 'rejected', '2026-06-29 19:09:47'),
(70, '1221103276', 'Aun', 'sml415', '2026-07-25', '10:00:00', 2, '', 'pending', '2026-06-29 23:28:05'),
(71, '1221103276', 'Aun', 'lec131', '2026-07-01', '16:00:00', 2, '', 'rejected', '2026-06-30 02:48:25'),
(72, '1221103276', 'Aun', 'mme123', '2026-07-05', '14:00:00', 2, '', 'rejected', '2026-07-04 11:47:51'),
(73, 'admin_booking', 'Booking Administrator', 'fld112', '2026-07-05', '17:00:00', 3, '', 'rejected', '2026-07-05 07:20:28'),
(74, '1221103276', 'Aun', 'exm456', '2026-07-05', '15:00:00', 2, '', 'rejected', '2026-07-05 07:24:10'),
(75, '1221103276', 'Aun', 'mme123', '2026-07-05', '16:00:00', 2, '', 'rejected', '2026-07-05 07:32:05'),
(76, '1221103276', 'Aun', 'exm456', '2026-07-06', '16:00:00', 2, '', 'rejected', '2026-07-05 07:34:05'),
(77, '1221103276', 'Aun', 'sml415', '2026-07-06', '16:00:00', 2, '', 'rejected', '2026-07-05 07:38:56'),
(78, '1221103276', 'Aun', 'vol789', '2026-07-09', '09:00:00', 2, 'Play for fun', 'rejected', '2026-07-05 07:54:48'),
(79, '1221103276', 'Aun', 'cpl616', '2026-07-30', '10:04:00', 2, '', 'pending', '2026-07-05 08:00:22'),
(80, '1221103276', 'Aun', 'vol789', '2026-07-21', '18:57:00', 2, '', 'rejected', '2026-07-05 08:57:28'),
(81, '1221103276', 'Aun', 'exm456', '2026-07-14', '18:08:00', 2, '', 'rejected', '2026-07-05 09:06:17'),
(82, '1221103276', 'Aun', 'fld112', '2026-07-18', '14:18:00', 1, 'Prepare 100 chair', 'pending', '2026-07-06 17:49:31'),
(83, '1221103276', 'Aun', 'lec131', '2026-07-13', '10:00:00', 2, '', 'pending', '2026-07-06 18:19:11'),
(84, '1221103276', 'Aun', 'exm456', '2026-07-22', '10:00:00', 2, '', 'pending', '2026-07-06 18:21:22'),
(85, '1221103276', 'Aun', 'sml415', '2026-07-20', '10:00:00', 2, '', 'confirmed', '2026-07-06 18:21:22');

-- --------------------------------------------------------

--
-- Table structure for table `chat_groups`
--

CREATE TABLE `chat_groups` (
  `event_name` varchar(255) NOT NULL,
  `cover_url` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `category` varchar(50) DEFAULT 'General',
  `created_by` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_groups`
--

INSERT INTO `chat_groups` (`event_name`, `cover_url`, `description`, `created_at`, `category`, `created_by`) VALUES
('C++ Competition', 'https://www.infoworld.com/wp-content/uploads/2024/06/shutterstock_2064800414-100956534-orig.jpg?quality=50&strip=all&w=1024', 'Group chat for C++ Competition', '2026-07-07 01:34:35', 'General', 1221103276),
('Entrepreneurship Hub', 'https://cdn.britannica.com/07/239807-050-8938E81C/composite-image-tech-businessman-small-business-construction-business.jpg', 'Share startup ideas, pitch competitions, and network with aspiring founders.', '2026-06-30 03:46:04', 'General', 0),
('Exam Prep', 'https://images.shiksha.com/mediadata/images/articles/1686291734phpfLr3LW.jpeg', 'Discuss exam schedules and study sessions.', '2026-06-28 14:28:15', 'Study', 0),
('Football Match', 'https://ss-i.thgim.com/public/incoming/ps7jzn/article70014868.ece/alternates/LANDSCAPE_1200/2025-09-05T021330Z_1747398781_UP1EL9503QMS7_RTRMADP_3_SOCCER-WORLDCUP-ARG-VEN.JPG', 'Group for organising football games on the field.', '2026-06-28 14:28:15', 'Sports', 0),
('Gaming Zone', 'https://www.pcworld.com/wp-content/uploads/2025/04/Amazon-Gaming-Zone-1.png', 'Connect with fellow gamers – arrange LAN parties, discuss strategies, and share game recommendations.', '2026-06-30 03:46:04', 'General', 0),
('Hackathon 2026', 'https://images.rawpixel.com/image_social_landscape/cHJpdmF0ZS9sci9pbWFnZXMvd2Vic2l0ZS8yMDI1LTA0L3NyLWltYWdlLTAzMDQyNS1mbGswMi1zLTAwOV8xLmpwZw.jpg', 'Team formation and collaboration for the annual 48-hour hackathon.', '2026-06-30 02:22:58', 'General', 0),
('Music Club', 'https://www.publicdomainpictures.net/pictures/620000/velka/image-1721763635SwL.jpg', 'For all music lovers – share playlists, discuss instruments, and plan jam sessions.', '2026-06-30 03:46:03', 'General', 0),
('Photography Club', 'data:image/jpeg;base64,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', 'For shutterbugs – share your photos, discuss techniques, and plan photo walks around campus.', '2026-06-30 03:46:04', 'General', 0),
('Study Group - Finals', 'https://www.vedantu.com/seo/content-images/3b375296-3a84-439e-9d25-d4697cb7c02b.jpg', 'Final exam preparation group for all majors – share notes and quiz each other.', '2026-06-30 02:22:58', 'Study', 0),
('Travel & Exploration', 'https://images.squarespace-cdn.com/content/v1/5f24290fd0d0910ecab2b02e/b2b65c78-ad81-42a1-a304-8b696750716d/shutterstock_611011652-222.jpg', 'Share travel stories, plan trips, and discover hidden gems around Malaysia.', '2026-06-30 03:46:04', 'General', 0),
('Volleyball Club', 'https://dotorg.brightspotcdn.com/dims4/default/6b3ef88/2147483647/strip/true/crop/3000x2000+0+0/resize/800x533!/quality/90/?url=http%3A%2F%2Fsoi-brightspot.s3.amazonaws.com%2Fdotorg%2F3a%2F80%2Fc263d7cb4e4b9ee2f20a6249c7f8%2Fie-sovoleywc-051025-01801.jpg', 'Volleyball enthusiasts – arrange matches and training.', '2026-06-28 14:28:15', 'General', 0);

-- --------------------------------------------------------

--
-- Table structure for table `chat_group_messages`
--

CREATE TABLE `chat_group_messages` (
  `id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_group_messages`
--

INSERT INTO `chat_group_messages` (`id`, `event_name`, `user_id`, `message`, `created_at`, `is_deleted`) VALUES
(1, 'Football Match', '1221103276', 'Who is up for a game this Saturday?', '2026-06-28 08:28:15', 0),
(2, 'Football Match', '1221101651', 'I am in! What time?', '2026-06-28 09:28:15', 0),
(3, 'Football Match', '0', 'I can join if it is after 4pm.', '2026-06-28 10:28:15', 0),
(4, 'Exam Prep', '1221103276', 'Has anyone studied chapter 5?', '2026-06-28 11:28:15', 0),
(5, 'Exam Prep', '0', 'I am reviewing it now.', '2026-06-28 12:28:15', 0),
(6, 'Volleyball Club', '1221101651', 'Practice tomorrow at 5pm on court vol789.', '2026-06-27 14:28:15', 0),
(7, 'Volleyball Club', '1221101649', 'I\'ll bring the net.', '2026-06-27 18:28:15', 0),
(8, 'Football Match', '1221103276', '@AI Booking request:\nVenue: Computer Lab\nDate: 2026-07-01\nTime: 08:00\nDuration: 3h', '2026-06-30 00:47:37', 0),
(9, 'Football Match', '0', '✅ Booking request submitted for Computer Lab on 2026-07-01 at 08:00 for 3 hour(s). A confirmation email will be sent to 1221103276@student.mmu.edu.my.', '2026-06-30 00:47:37', 0),
(10, 'Exam Prep', '1221103276', '@AI Booking request:\nVenue: Main Hall\nDate: 2026-08-31\nTime: 20:07\nDuration: 4h', '2026-06-30 01:02:06', 0),
(12, 'Football Match', '241UT24100', 'I can bring the balls and cones for tomorrow.', '2026-06-29 23:22:58', 0),
(13, 'Football Match', '1221101649', 'What time is kickoff? I might be late.', '2026-06-30 00:22:58', 0),
(14, 'Football Match', '1221103276', '4pm sharp! Please be on time.', '2026-06-30 01:22:58', 0),
(15, 'Exam Prep', '1221101651', 'Anyone free to study calculus tomorrow at 10am?', '2026-06-29 14:22:58', 0),
(16, 'Exam Prep', '1221103276', 'I will join at 10am. Let me know the room.', '2026-06-29 16:22:58', 0),
(17, 'Exam Prep', '0', 'I can generate practice questions if you need.', '2026-06-29 18:22:58', 0),
(18, 'Volleyball Club', '241UT24100', 'Practice cancelled today due to rain. Reschedule to Sunday?', '2026-06-29 22:22:58', 0),
(19, 'Volleyball Club', '1221101649', 'Sunday works for me.', '2026-06-29 23:22:58', 0),
(20, 'Study Group - Finals', '1221101651', 'Let us start with Chapter 5: Linear Algebra.', '2026-06-29 02:22:58', 0),
(21, 'Study Group - Finals', '1221101649', 'I have a summary sheet for that chapter.', '2026-06-29 03:22:58', 0),
(22, 'Study Group - Finals', '241UT24100', 'Can we meet at the library instead?', '2026-06-29 04:22:58', 0),
(23, 'Study Group - Finals', '1221103276', 'Sure, 2nd floor study room.', '2026-06-29 05:22:58', 0),
(24, 'Study Group - Finals', '0', 'Don\'t forget to check the mock test I uploaded!', '2026-06-29 08:22:58', 0),
(25, 'Hackathon 2026', '1221101649', 'Who wants to form a team for the Hackathon?', '2026-06-28 02:22:58', 0),
(26, 'Hackathon 2026', '1221103276', 'I am in! I will handle the backend with Python.', '2026-06-28 03:22:58', 0),
(27, 'Hackathon 2026', '241UT24100', 'I can do the frontend using React.', '2026-06-28 04:22:58', 0),
(28, 'Hackathon 2026', 'admin_super', 'Great initiative! I can mentor you on the deployment aspect.', '2026-06-28 05:22:58', 0),
(29, 'Hackathon 2026', '1221101649', 'Perfect. Let us meet this weekend to brainstorm ideas.', '2026-06-28 06:22:58', 0),
(30, 'Campus Basketball League', '1221103276', 'We need 5 more teams for the tournament.', '2026-06-28 20:22:58', 0),
(31, 'Campus Basketball League', '1221101651', 'The Engineering faculty is joining!', '2026-06-28 22:22:58', 0),
(32, 'Campus Basketball League', '241UT24100', 'I can help with the scheduling.', '2026-06-29 00:22:58', 0),
(33, 'Campus Basketball League', '0', 'I can create a bracket generator if needed.', '2026-06-29 02:22:58', 0),
(34, 'Music Club', '1221103276', 'Anyone play the guitar? Let’s jam this weekend!', '2026-06-27 03:46:04', 0),
(35, 'Music Club', '1221101651', 'I play drums – count me in.', '2026-06-28 03:46:04', 0),
(36, 'Music Club', '0', 'I can generate chord progressions if you need.', '2026-06-28 03:46:04', 0),
(37, 'Music Club', '1221101649', 'I sing! Can we do a cover of Bohemian Rhapsody?', '2026-06-29 03:46:04', 0),
(38, 'Music Club', '241UT24100', 'I’ll bring my acoustic guitar.', '2026-06-29 15:46:04', 0),
(39, 'Music Club', 'staff_002', 'I can book the music room for practice.', '2026-06-29 21:46:04', 0),
(40, 'Movie Buffs', '1221103210', 'Has anyone seen Dune 2? Mind-blowing visuals!', '2026-06-26 03:46:04', 0),
(41, 'Movie Buffs', '1221108765', 'I loved it – the sound design was epic.', '2026-06-27 03:46:04', 0),
(42, 'Movie Buffs', '1221104321', 'Let’s do a movie night in the Main Hall – I have a projector.', '2026-06-28 03:46:04', 0),
(43, 'Movie Buffs', 'admin_content', 'I can arrange the licensing for public screening.', '2026-06-29 03:46:04', 0),
(44, 'Movie Buffs', '1221105678', 'Count me in! What movie are we watching?', '2026-06-29 15:46:04', 0),
(45, 'Movie Buffs', 'ext_001', 'I’ll bring snacks.', '2026-06-29 21:46:04', 0),
(46, 'Gaming Zone', '1221106789', 'Anyone up for Valorant tonight?', '2026-06-28 03:46:04', 0),
(47, 'Gaming Zone', '1221103456', 'I’m in – I’ll be on at 9pm.', '2026-06-28 03:46:04', 0),
(48, 'Gaming Zone', '0', 'I can help with game strategy guides.', '2026-06-29 03:46:04', 0),
(49, 'Gaming Zone', '1221109012', 'We should arrange a campus-wide e-sports tournament.', '2026-06-29 03:46:04', 0),
(50, 'Gaming Zone', 'admin_system', 'I can set up the network for a LAN party in the computer lab.', '2026-06-29 15:46:04', 0),
(51, 'Gaming Zone', '1221105566', 'Let’s do it! I’ll bring my mechanical keyboard.', '2026-06-29 21:46:04', 0),
(52, 'Entrepreneurship Hub', '1221107788', 'I have a startup idea for a campus delivery service.', '2026-06-25 03:46:04', 0),
(53, 'Entrepreneurship Hub', '1221109900', 'That’s great – let’s brainstorm together.', '2026-06-26 03:46:04', 0),
(54, 'Entrepreneurship Hub', '1221102233', 'We should pitch at the upcoming innovation challenge.', '2026-06-27 03:46:04', 0),
(55, 'Entrepreneurship Hub', 'ext_002', 'I can mentor you on the business plan.', '2026-06-28 03:46:04', 0),
(56, 'Entrepreneurship Hub', 'admin_events', 'I’ll help arrange a pitch session in the Main Hall.', '2026-06-29 03:46:04', 0),
(57, 'Entrepreneurship Hub', '1221104455', 'Count me in – I’ll handle the marketing.', '2026-06-29 15:46:04', 0),
(58, 'Photography Club', '1221101651', 'Check out this sunrise shot I took at the field.', '2026-06-27 03:46:04', 0),
(59, 'Photography Club', '1221101649', 'Amazing colours! What camera did you use?', '2026-06-27 03:46:04', 0),
(60, 'Photography Club', 'staff_001', 'I have a Canon DSLR – happy to teach basic photography.', '2026-06-28 03:46:04', 0),
(61, 'Photography Club', '241UT24100', 'Let’s do a photo walk this weekend around the lake.', '2026-06-29 03:46:04', 0),
(62, 'Photography Club', '1221109876', 'I’ll bring my drone for aerial shots.', '2026-06-29 15:46:04', 0),
(63, 'Photography Club', 'staff_003', 'I can help with post-processing tips.', '2026-06-29 21:46:04', 0),
(64, 'Debate Society', '1221103276', 'Topic: \"Should university education be free?\" – let’s debate.', '2026-06-28 03:46:04', 0),
(65, 'Debate Society', '1221101651', 'I’ll take the pro side.', '2026-06-28 03:46:04', 0),
(66, 'Debate Society', '0', 'I can provide statistics for both sides.', '2026-06-29 03:46:04', 0),
(67, 'Debate Society', '1221102345', 'We should have a mock debate this Friday.', '2026-06-29 03:46:04', 0),
(68, 'Debate Society', 'admin_review', 'I’ll judge – this is going to be interesting.', '2026-06-29 15:46:04', 0),
(69, 'Debate Society', '1221106789', 'I’ll prepare opening statements.', '2026-06-29 21:46:04', 0),
(70, 'Dance Crew', '1221103210', 'Anyone interested in learning K-pop choreography?', '2026-06-29 03:46:04', 0),
(71, 'Dance Crew', '1221108765', 'I’m in! We can practise in the Main Hall.', '2026-06-29 03:46:04', 0),
(72, 'Dance Crew', '1221104321', 'I know a great routine for Butter by BTS.', '2026-06-29 15:46:04', 0),
(73, 'Dance Crew', '1221105678', 'Let’s plan a flash mob for orientation week.', '2026-06-29 15:46:04', 0),
(74, 'Dance Crew', '1221107890', 'I’ll bring the speakers.', '2026-06-29 21:46:04', 0),
(75, 'Dance Crew', '1221105566', 'Can we invite the music club to collaborate?', '2026-06-30 00:46:04', 0),
(76, 'Bookworms', '1221101123', 'I just finished \"The Alchemist\" – such a beautiful story.', '2026-06-27 03:46:04', 0),
(77, 'Bookworms', '1221103344', 'I loved it too – let’s read \"Atomic Habits\" next.', '2026-06-28 03:46:04', 0),
(78, 'Bookworms', 'staff_002', 'We can have a weekly book club in the library.', '2026-06-28 03:46:04', 0),
(79, 'Bookworms', '1221109900', 'Count me in – I’ll bring snacks.', '2026-06-29 03:46:04', 0),
(80, 'Bookworms', 'ext_001', 'I’ll donate some books for the club.', '2026-06-29 15:46:04', 0),
(81, 'Bookworms', '1221107788', 'Let’s make a reading list for the semester.', '2026-06-29 21:46:04', 0),
(82, 'Fitness & Wellness', '1221105678', 'Who’s up for a morning run at the field tomorrow 6am?', '2026-06-29 03:46:04', 0),
(83, 'Fitness & Wellness', '1221107890', 'I’ll join – need to get back in shape.', '2026-06-29 03:46:04', 0),
(84, 'Fitness & Wellness', 'staff_001', 'I can guide you on proper warm-up routines.', '2026-06-29 15:46:04', 0),
(85, 'Fitness & Wellness', '1221102345', 'Let’s do a group yoga session on Sunday.', '2026-06-29 15:46:04', 0),
(86, 'Fitness & Wellness', '1221109012', 'I’ll bring yoga mats.', '2026-06-29 21:46:04', 0),
(87, 'Fitness & Wellness', '1221103456', 'Great idea – let’s make it a weekly thing.', '2026-06-30 00:46:04', 0),
(88, 'Travel & Exploration', '1221104321', 'I’m planning a trip to Penang next month – anyone keen?', '2026-06-27 03:46:04', 0),
(89, 'Travel & Exploration', '1221105678', 'I’d love to join – I know some great food places.', '2026-06-28 03:46:04', 0),
(90, 'Travel & Exploration', '1221109900', 'We can visit the heritage sites.', '2026-06-28 03:46:04', 0),
(91, 'Travel & Exploration', 'ext_002', 'I can be your guide – I’ve travelled all over Malaysia.', '2026-06-29 03:46:04', 0),
(92, 'Travel & Exploration', '1221106677', 'Let’s create a group itinerary.', '2026-06-29 15:46:04', 0),
(93, 'Travel & Exploration', '1221104455', 'I’ll research the best places to stay.', '2026-06-29 21:46:04', 0),
(94, 'Anime & Manga Club', '1221101649', 'Has anyone seen the latest episode of Attack on Titan?', '2026-06-28 03:46:04', 0),
(95, 'Anime & Manga Club', '1221106543', 'Yes! The animation was insane.', '2026-06-28 03:46:04', 0),
(96, 'Anime & Manga Club', '0', 'I can recommend similar shows based on your taste.', '2026-06-29 03:46:04', 0),
(97, 'Anime & Manga Club', '1221108765', 'Let’s host a cosplay event next month.', '2026-06-29 03:46:04', 0),
(98, 'Anime & Manga Club', 'admin_content', 'I can help with event logistics.', '2026-06-29 15:46:04', 0),
(99, 'Anime & Manga Club', '1221103344', 'Count me in – I’ll dress as Naruto.', '2026-06-29 21:46:04', 0),
(100, 'Cooking & Foodies', '1221103276', 'I make a mean nasi lemak – anyone want to learn?', '2026-06-29 03:46:04', 0),
(101, 'Cooking & Foodies', '1221101651', 'I’d love to! Let’s have a cooking session.', '2026-06-29 03:46:04', 0),
(102, 'Cooking & Foodies', 'staff_003', 'We can use the home economics kitchen.', '2026-06-29 15:46:04', 0),
(103, 'Cooking & Foodies', '1221109012', 'I can bring ingredients.', '2026-06-29 15:46:04', 0),
(104, 'Cooking & Foodies', 'ext_002', 'I’ll share my mother’s famous curry recipe.', '2026-06-29 21:46:04', 0),
(105, 'Cooking & Foodies', '1221107788', 'Let’s make it a potluck – everyone brings a dish.', '2026-06-30 00:46:04', 0),
(106, 'Football Match', '1221103276', 'I’ll bring the extra jerseys for tomorrow’s match.', '2026-06-30 02:48:00', 0),
(107, 'Exam Prep', '1221103276', 'I’ve compiled a summary of chapter 6 – sharing it now.', '2026-06-30 01:48:00', 0),
(108, 'Volleyball Club', '1221103276', 'Great practice yesterday! Let’s do it again on Saturday.', '2026-06-30 00:48:00', 0),
(109, 'Study Group - Finals', '1221103276', 'I’ve booked the library room for our study session tomorrow at 10am.', '2026-06-29 23:48:00', 0),
(110, 'Hackathon 2026', '1221103276', 'I’ll handle the backend API – let’s use Flask.', '2026-06-29 22:48:00', 0),
(111, 'Campus Basketball League', '1221103276', 'We have 6 teams confirmed – need 2 more.', '2026-06-29 21:48:00', 0),
(112, 'Music Club', '1221103276', 'I can play the keyboard – let me know if you need a pianist.', '2026-06-29 20:48:00', 0),
(113, 'Movie Buffs', '1221103276', 'I vote for Interstellar – it’s a masterpiece.', '2026-06-29 19:48:00', 0),
(114, 'Gaming Zone', '1221103276', 'Who’s up for a CS:GO match tonight?', '2026-06-29 18:48:00', 0),
(115, 'Entrepreneurship Hub', '1221103276', 'I’ve drafted a business plan – can someone review it?', '2026-06-29 17:48:00', 0),
(116, 'Photography Club', '1221103276', 'I’ll bring my tripod for the sunset shoot.', '2026-06-29 16:48:00', 0),
(117, 'Debate Society', '1221103276', 'I’ll argue that social media does more harm than good.', '2026-06-29 15:48:00', 0),
(118, 'Dance Crew', '1221103276', 'I’ve choreographed a new routine – who wants to learn?', '2026-06-29 14:48:00', 0),
(119, 'Bookworms', '1221103276', 'I just finished \"1984\" – let’s discuss it at the next meeting.', '2026-06-29 13:48:00', 0),
(120, 'Fitness & Wellness', '1221103276', 'Morning run tomorrow at 6am – who’s coming?', '2026-06-29 12:48:00', 0),
(121, 'Travel & Exploration', '1221103276', 'I’ve found cheap flights to Langkawi – interested?', '2026-06-29 11:48:00', 0),
(122, 'Anime & Manga Club', '1221103276', 'I cosplayed as Levi last year – photos here.', '2026-06-29 10:48:00', 0),
(123, 'Cooking & Foodies', '1221103276', 'I’ll bring my famous chocolate cake to the potluck.', '2026-06-29 09:48:00', 0),
(124, 'Volleyball Club', '1221103276', '@AI Booking request:\nVenue: Volleyball Court\nDate: 2026-07-09\nTime: 09:00\nDuration: 2h\nComment: Play for fun', '2026-07-05 15:54:52', 0),
(125, 'Volleyball Club', '0', '✅ Booking request submitted for Volleyball Court on 2026-07-09 at 09:00 for 2 hour(s). A confirmation email will be sent to 1221103276@student.mmu.edu.my.', '2026-07-05 15:54:52', 0),
(126, 'Gaming Zone', '1221103276', '@AI Booking request:\nVenue: Computer Lab\nDate: 2026-07-30\nTime: 10:04\nDuration: 2h', '2026-07-05 16:00:26', 0),
(127, 'Gaming Zone', '0', '✅ Booking request submitted for Computer Lab on 2026-07-30 at 10:04 for 2 hour(s). A confirmation email will be sent to 1221103276@student.mmu.edu.my.', '2026-07-05 16:00:26', 0),
(128, 'Gaming Zone', '1221103276', '😂', '2026-07-05 16:40:35', 0),
(129, 'Gaming Zone', '1221103276', '@ai', '2026-07-05 16:42:05', 0),
(130, 'Gaming Zone', '1221103276', '@AI', '2026-07-05 16:42:11', 0),
(131, 'Gaming Zone', '1221103276', '@AI', '2026-07-05 16:56:11', 0),
(132, 'Volleyball Club', '1221103276', '@AI', '2026-07-05 16:57:00', 0),
(133, 'Football Match', '1221103276', '@AI Booking request:\n**Venue:** Volleyball Court\n**Date:** 2026-07-21\n**Time:** 18:57\n**Duration:** 2h', '2026-07-05 16:57:32', 0),
(134, 'Football Match', '0', '✅ Booking request submitted for Volleyball Court on 2026-07-21 at 18:57 for 2 hour(s). A confirmation email will be sent to you.', '2026-07-05 16:57:32', 0),
(135, 'Exam Prep', '1221103276', '@AI Booking request:\n**Venue:** Exam Hall\n**Date:** 2026-07-14\n**Time:** 18:08\n**Duration:** 2h', '2026-07-05 17:06:20', 0),
(136, 'Exam Prep', '0', '✅ Booking request submitted for Exam Hall on 2026-07-14 at 18:08 for 2 hour(s). A confirmation email will be sent to you.', '2026-07-05 17:06:20', 0),
(137, 'Photography Club', '0', '✨', '2026-07-05 18:09:28', 0),
(138, 'Photography Club', '0', '😂', '2026-07-05 18:09:36', 0),
(139, 'C++ Competition', '-1', 'Aun joined the group.', '2026-07-07 01:34:35', 0),
(140, 'C++ Competition', '-1', 'Review Administrator joined the group.', '2026-07-07 01:37:37', 0),
(141, 'C++ Competition', '1221103276', '😂', '2026-07-07 01:38:46', 0),
(142, 'C++ Competition', '1221103276', '✨', '2026-07-07 01:38:50', 0),
(143, 'C++ Competition', '1221103276', '❤️', '2026-07-07 01:38:54', 0),
(144, 'C++ Competition', '1221103276', 'halo my name is aun yi qi', '2026-07-07 01:39:49', 0),
(146, 'C++ Competition', '-1', 'Wenhui joined the group.', '2026-07-07 01:45:06', 0),
(149, 'C++ Competition', '1221103276', '@AI Booking request:\n**Venue:** Field\n**Date:** 2026-07-18\n**Time:** 14:18\n**Duration:** 1h\n**Comment:** Prepare 100 chair', '2026-07-07 01:49:35', 0),
(150, 'C++ Competition', '0', '✅ Booking request submitted for Field on 2026-07-18 at 14:18 for 1 hour(s). A confirmation email will be sent to you.', '2026-07-07 01:49:35', 0);

-- --------------------------------------------------------

--
-- Table structure for table `chat_group_participants`
--

CREATE TABLE `chat_group_participants` (
  `id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_group_participants`
--

INSERT INTO `chat_group_participants` (`id`, `event_name`, `user_id`, `joined_at`) VALUES
(1, 'Football Match', '1221103276', '2026-06-28 14:28:15'),
(2, 'Football Match', '1221101651', '2026-06-28 14:28:15'),
(3, 'Football Match', '0', '2026-06-28 14:28:15'),
(4, 'Exam Prep', '1221103276', '2026-06-28 14:28:15'),
(5, 'Exam Prep', '0', '2026-06-28 14:28:15'),
(6, 'Volleyball Club', '1221101651', '2026-06-28 14:28:15'),
(7, 'Volleyball Club', '1221103276', '2026-06-28 14:28:15'),
(8, 'Volleyball Club', '1221101649', '2026-06-28 14:28:15'),
(9, 'Study Group - Finals', '1221103276', '2026-06-30 02:22:58'),
(10, 'Study Group - Finals', '1221101651', '2026-06-30 02:22:58'),
(11, 'Study Group - Finals', '1221101649', '2026-06-30 02:22:58'),
(12, 'Study Group - Finals', '241UT24100', '2026-06-30 02:22:58'),
(13, 'Study Group - Finals', '0', '2026-06-30 02:22:58'),
(14, 'Hackathon 2026', '1221103276', '2026-06-30 02:22:58'),
(15, 'Hackathon 2026', '1221101649', '2026-06-30 02:22:58'),
(16, 'Hackathon 2026', '241UT24100', '2026-06-30 02:22:58'),
(17, 'Hackathon 2026', 'admin_super', '2026-06-30 02:22:58'),
(18, 'Campus Basketball League', '1221103276', '2026-06-30 02:22:58'),
(19, 'Campus Basketball League', '1221101651', '2026-06-30 02:22:58'),
(20, 'Campus Basketball League', '0', '2026-06-30 02:22:58'),
(21, 'Campus Basketball League', '241UT24100', '2026-06-30 02:22:58'),
(22, 'Music Club', '1221103276', '2026-06-30 03:46:03'),
(23, 'Music Club', '1221101651', '2026-06-30 03:46:03'),
(24, 'Music Club', '1221101649', '2026-06-30 03:46:03'),
(25, 'Music Club', '241UT24100', '2026-06-30 03:46:03'),
(26, 'Music Club', '1221109876', '2026-06-30 03:46:03'),
(27, 'Music Club', '1221106543', '2026-06-30 03:46:03'),
(28, 'Music Club', 'staff_002', '2026-06-30 03:46:03'),
(29, 'Music Club', '0', '2026-06-30 03:46:03'),
(30, 'Movie Buffs', '1221103210', '2026-06-30 03:46:04'),
(31, 'Movie Buffs', '1221108765', '2026-06-30 03:46:04'),
(32, 'Movie Buffs', '1221104321', '2026-06-30 03:46:04'),
(33, 'Movie Buffs', '1221105678', '2026-06-30 03:46:04'),
(34, 'Movie Buffs', '1221107890', '2026-06-30 03:46:04'),
(35, 'Movie Buffs', '1221102345', '2026-06-30 03:46:04'),
(36, 'Movie Buffs', 'admin_content', '2026-06-30 03:46:04'),
(37, 'Movie Buffs', 'ext_001', '2026-06-30 03:46:04'),
(38, 'Gaming Zone', '1221106789', '2026-06-30 03:46:04'),
(39, 'Gaming Zone', '1221103456', '2026-06-30 03:46:04'),
(40, 'Gaming Zone', '1221109012', '2026-06-30 03:46:04'),
(41, 'Gaming Zone', '1221101123', '2026-06-30 03:46:04'),
(42, 'Gaming Zone', '1221103344', '2026-06-30 03:46:04'),
(43, 'Gaming Zone', '1221105566', '2026-06-30 03:46:04'),
(44, 'Gaming Zone', 'admin_system', '2026-06-30 03:46:04'),
(45, 'Gaming Zone', '0', '2026-06-30 03:46:04'),
(46, 'Entrepreneurship Hub', '1221107788', '2026-06-30 03:46:04'),
(47, 'Entrepreneurship Hub', '1221109900', '2026-06-30 03:46:04'),
(48, 'Entrepreneurship Hub', '1221102233', '2026-06-30 03:46:04'),
(49, 'Entrepreneurship Hub', '1221104455', '2026-06-30 03:46:04'),
(50, 'Entrepreneurship Hub', '1221106677', '2026-06-30 03:46:04'),
(51, 'Entrepreneurship Hub', '1221108899', '2026-06-30 03:46:04'),
(52, 'Entrepreneurship Hub', 'ext_002', '2026-06-30 03:46:04'),
(53, 'Entrepreneurship Hub', 'admin_events', '2026-06-30 03:46:04'),
(54, 'Photography Club', '1221101651', '2026-06-30 03:46:04'),
(55, 'Photography Club', '1221101649', '2026-06-30 03:46:04'),
(56, 'Photography Club', '241UT24100', '2026-06-30 03:46:04'),
(57, 'Photography Club', '1221109876', '2026-06-30 03:46:04'),
(58, 'Photography Club', '1221106543', '2026-06-30 03:46:04'),
(59, 'Photography Club', 'staff_001', '2026-06-30 03:46:04'),
(60, 'Photography Club', 'staff_003', '2026-06-30 03:46:04'),
(61, 'Debate Society', '1221103276', '2026-06-30 03:46:04'),
(62, 'Debate Society', '1221101651', '2026-06-30 03:46:04'),
(63, 'Debate Society', '1221102345', '2026-06-30 03:46:04'),
(64, 'Debate Society', '1221106789', '2026-06-30 03:46:04'),
(65, 'Debate Society', '1221103456', '2026-06-30 03:46:04'),
(66, 'Debate Society', '0', '2026-06-30 03:46:04'),
(67, 'Debate Society', 'admin_review', '2026-06-30 03:46:04'),
(68, 'Dance Crew', '1221103210', '2026-06-30 03:46:04'),
(69, 'Dance Crew', '1221108765', '2026-06-30 03:46:04'),
(70, 'Dance Crew', '1221104321', '2026-06-30 03:46:04'),
(71, 'Dance Crew', '1221105678', '2026-06-30 03:46:04'),
(72, 'Dance Crew', '1221107890', '2026-06-30 03:46:04'),
(73, 'Dance Crew', '1221103344', '2026-06-30 03:46:04'),
(74, 'Dance Crew', '1221105566', '2026-06-30 03:46:04'),
(75, 'Bookworms', '1221101123', '2026-06-30 03:46:04'),
(76, 'Bookworms', '1221103344', '2026-06-30 03:46:04'),
(77, 'Bookworms', '1221107788', '2026-06-30 03:46:04'),
(78, 'Bookworms', '1221109900', '2026-06-30 03:46:04'),
(79, 'Bookworms', '1221102233', '2026-06-30 03:46:04'),
(80, 'Bookworms', 'staff_002', '2026-06-30 03:46:04'),
(81, 'Bookworms', 'ext_001', '2026-06-30 03:46:04'),
(82, 'Fitness & Wellness', '1221105678', '2026-06-30 03:46:04'),
(83, 'Fitness & Wellness', '1221107890', '2026-06-30 03:46:04'),
(84, 'Fitness & Wellness', '1221102345', '2026-06-30 03:46:04'),
(85, 'Fitness & Wellness', '1221106789', '2026-06-30 03:46:04'),
(86, 'Fitness & Wellness', '1221103456', '2026-06-30 03:46:04'),
(87, 'Fitness & Wellness', '1221109012', '2026-06-30 03:46:04'),
(88, 'Fitness & Wellness', 'staff_001', '2026-06-30 03:46:04'),
(89, 'Travel & Exploration', '1221104321', '2026-06-30 03:46:04'),
(90, 'Travel & Exploration', '1221105678', '2026-06-30 03:46:04'),
(91, 'Travel & Exploration', '1221109900', '2026-06-30 03:46:04'),
(92, 'Travel & Exploration', '1221102233', '2026-06-30 03:46:04'),
(93, 'Travel & Exploration', '1221104455', '2026-06-30 03:46:04'),
(94, 'Travel & Exploration', '1221106677', '2026-06-30 03:46:04'),
(95, 'Travel & Exploration', 'ext_002', '2026-06-30 03:46:04'),
(96, 'Anime & Manga Club', '1221101649', '2026-06-30 03:46:04'),
(97, 'Anime & Manga Club', '1221106543', '2026-06-30 03:46:04'),
(98, 'Anime & Manga Club', '1221108765', '2026-06-30 03:46:04'),
(99, 'Anime & Manga Club', '1221101123', '2026-06-30 03:46:04'),
(100, 'Anime & Manga Club', '1221103344', '2026-06-30 03:46:04'),
(101, 'Anime & Manga Club', '0', '2026-06-30 03:46:04'),
(102, 'Anime & Manga Club', 'admin_content', '2026-06-30 03:46:04'),
(103, 'Cooking & Foodies', '1221103276', '2026-06-30 03:46:04'),
(104, 'Cooking & Foodies', '1221101651', '2026-06-30 03:46:04'),
(105, 'Cooking & Foodies', '1221103210', '2026-06-30 03:46:04'),
(107, 'Cooking & Foodies', '1221107788', '2026-06-30 03:46:04'),
(108, 'Cooking & Foodies', 'staff_003', '2026-06-30 03:46:04'),
(109, 'Cooking & Foodies', 'ext_002', '2026-06-30 03:46:04'),
(111, 'Gaming Zone', '1221103276', '2026-06-30 03:48:00'),
(113, 'Photography Club', '1221103276', '2026-06-30 03:48:00'),
(115, 'Bookworms', '1221103276', '2026-06-30 03:48:00'),
(116, 'Fitness & Wellness', '1221103276', '2026-06-30 03:48:00'),
(117, 'Travel & Exploration', '1221103276', '2026-06-30 03:48:00'),
(125, 'Photography Club', '0', '2026-07-05 18:09:28'),
(127, 'C++ Competition', '1221103276', '2026-07-07 01:34:35'),
(128, 'C++ Competition', '0', '2026-07-07 01:37:37'),
(134, 'C++ Competition', '1221101651', '2026-07-07 01:45:06');

-- --------------------------------------------------------

--
-- Table structure for table `chat_group_typing`
--

CREATE TABLE `chat_group_typing` (
  `event_name` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_group_typing`
--

INSERT INTO `chat_group_typing` (`event_name`, `user_id`, `updated_at`) VALUES
('C++ Competition', 0, 1783359680),
('C++ Competition', 1221101651, 1783359922),
('C++ Competition', 1221103276, 1783360025),
('Exam Prep', 1221103276, 1783242358),
('Football Match', 1221103276, 1783241833),
('Gaming Zone', 1221103276, 1783241770),
('Volleyball Club', 1221103276, 1783241820);

-- --------------------------------------------------------

--
-- Table structure for table `comment_likes`
--

CREATE TABLE `comment_likes` (
  `comment_id` int(11) NOT NULL,
  `user_id` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comment_likes`
--

INSERT INTO `comment_likes` (`comment_id`, `user_id`) VALUES
(1, '0'),
(1, '1221101651'),
(3, '1221103276'),
(4, '1221101651'),
(4, '1221103276'),
(5, '1221103276'),
(6, '1221101649'),
(7, '1221103276'),
(8, '0'),
(8, '1221101649'),
(8, '1221103276'),
(11, '1221101651'),
(11, 'admin_super'),
(13, '1221101649'),
(13, '241UT24100'),
(15, '0'),
(15, '1221103276'),
(16, '0'),
(16, '1221101649'),
(17, '1221103276'),
(19, 'admin_booking'),
(19, 'admin_review'),
(21, '1221101651'),
(21, '1221103276'),
(21, '241UT24100'),
(24, '1221101649'),
(24, '1221101651');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `username` varchar(100) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp(),
  `locked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`username`, `attempts`, `last_attempt`, `locked_until`) VALUES
('admin', 1, '2026-07-05 07:03:59', NULL),
('wen hui', 2, '2026-07-06 17:42:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_otp`
--

CREATE TABLE `password_reset_otp` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_otp`
--

INSERT INTO `password_reset_otp` (`id`, `email`, `otp`, `created_at`, `expires_at`, `used`) VALUES
(1, '1221103276@student.mmu.edu.my', '441928', '2026-06-28 14:36:16', '2026-06-28 14:46:16', 1);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rating` int(11) NOT NULL,
  `review` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `name`, `rating`, `review`, `created_at`) VALUES
(1, 'Aun', 5, 'The basketball court is well-maintained and perfect for our practice sessions.', '2026-06-26 14:28:15'),
(2, 'Wenhui', 4, 'Main Hall is spacious but the sound system could be improved.', '2026-06-23 14:28:15'),
(3, 'Kai Qing', 3, 'Computer lab has old machines, but they work for basic tasks.', '2026-06-18 14:28:15'),
(4, 'Dayana', 5, 'The field is great for football!', '2026-06-27 14:28:15'),
(5, 'Wenhui', 2, 'Computer Lab has extremely slow PCs. Needs urgent upgrade.', '2026-06-28 02:22:58'),
(6, 'Kai Qing', 4, 'Exam Hall is very quiet and comfortable for studying.', '2026-06-27 02:22:58'),
(7, 'Dayana', 3, 'Volleyball court is okay but the net is tearing.', '2026-06-26 02:22:58'),
(8, 'Super Administrator', 5, 'Main Hall is the best venue on campus for large events!', '2026-06-29 02:22:58'),
(9, 'Aun', 4, 'The field is great for football. Just wish we had goal posts.', '2026-06-24 02:22:58'),
(10, 'Aun', 5, 'Basketball court surface has improved a lot!', '2026-06-20 02:22:58'),
(11, 'Wenhui', 3, 'Smart Lab has cool tech but limited seating.', '2026-06-25 02:22:58'),
(12, 'Dayana', 4, 'Lecture Hall projectors are clear and loud.', '2026-06-28 02:22:58'),
(13, 'Kai Qing', 1, 'Computer Lab printer is always broken!', '2026-06-22 02:22:58'),
(14, 'James Tan', 4, 'Great place to shoot hoops, but the concrete is a bit rough.', '2026-06-29 02:45:21'),
(15, 'Sarah Lee', 5, 'I love the atmosphere here! Perfect for evening games.', '2026-06-28 02:45:21'),
(16, 'Mike Chong', 3, 'The hoops are uneven, one is higher than the other.', '2026-06-27 02:45:21'),
(17, 'Emily Wong', 4, 'Good for casual play, but lines need repainting.', '2026-06-25 02:45:21'),
(18, 'Dr. Ahmad Fauzi', 5, 'Excellent facility for PE classes.', '2026-06-23 02:45:21'),
(19, 'Daniel Raj', 2, 'Very slow computers, frequent crashes.', '2026-06-29 02:45:21'),
(20, 'Sofia Binti Ahmad', 3, 'The lab is okay for basic assignments, but the printers never work.', '2026-06-28 02:45:21'),
(21, 'Owen Lim', 4, 'Good for coding, but need more RAM on some machines.', '2026-06-26 02:45:21'),
(22, 'Lisa Goh', 1, 'Terrible experience, spent 30 minutes just to log in.', '2026-06-24 02:45:21'),
(23, 'Ms. Chan Mei Ling', 4, 'Adequate for most coursework, but the software is outdated.', '2026-06-22 02:45:21'),
(24, 'Ryan Teoh', 5, 'Very quiet and well-lit, perfect for exams.', '2026-06-29 02:45:21'),
(25, 'Megan Kaur', 4, 'Seats are comfortable, but the air conditioning is too cold.', '2026-06-27 02:45:21'),
(26, 'Ethan Ng', 5, 'Best place for serious study sessions.', '2026-06-25 02:45:21'),
(27, 'Olivia Chew', 3, 'The acoustics are echoey, hard to concentrate during loud exams.', '2026-06-23 02:45:21'),
(28, 'Prof. Abdul Rahman', 5, 'Excellent exam hall, well managed.', '2026-06-20 02:45:21'),
(29, 'Liam Yap', 5, 'Expansive and great for football and athletics.', '2026-06-29 02:45:21'),
(30, 'Chloe Tan', 4, 'The grass is often overgrown, but still playable.', '2026-06-28 02:45:21'),
(31, 'Noah Chan', 4, 'Good for running drills, but needs goal posts.', '2026-06-26 02:45:21'),
(32, 'Mia Koh', 3, 'Some uneven patches, could cause injuries.', '2026-06-24 02:45:21'),
(33, 'System Administrator', 5, 'We use it for all our outdoor events – fantastic space.', '2026-06-21 02:45:21'),
(34, 'Lucas Ooi', 5, 'Projector and sound system work perfectly.', '2026-06-29 02:45:21'),
(35, 'Amelia Foo', 4, 'Comfortable seating, but the microphones are sometimes faulty.', '2026-06-27 02:45:21'),
(36, 'Julian Quah', 5, 'Great for guest lectures, the stage is well designed.', '2026-06-25 02:45:21'),
(37, 'Isabella Yong', 3, 'Not enough power outlets for laptops.', '2026-06-23 02:45:21'),
(38, 'Content Manager', 4, 'Well-maintained hall, suitable for presentations.', '2026-06-19 02:45:21'),
(39, 'Raju Krishnan (Alumni)', 5, 'Brings back memories – huge and versatile space.', '2026-06-29 02:45:21'),
(40, 'Dr. Smith (Visiting)', 4, 'Good for large gatherings, but the stage lighting could be improved.', '2026-06-28 02:45:21'),
(41, 'Aun', 5, 'Our go-to venue for all major events!', '2026-06-27 02:45:21'),
(42, 'Wenhui', 4, 'Spacious and clean, but the sound system sometimes echoes.', '2026-06-24 02:45:21'),
(43, 'Events Coordinator', 5, 'Perfect for weddings, conferences, and concerts.', '2026-06-20 02:45:21'),
(44, 'Kai Qing', 5, 'The VR headsets are incredible!', '2026-06-29 02:45:21'),
(45, 'Dayana', 4, 'Great IoT equipment, but we need more sensors.', '2026-06-28 02:45:21'),
(46, 'James Tan', 3, 'Cool tech, but the lab is often booked and hard to get.', '2026-06-26 02:45:21'),
(47, 'Sarah Lee', 5, 'Smart boards are very interactive, love them.', '2026-06-24 02:45:21'),
(48, 'Dr. Ahmad Fauzi', 4, 'Good for innovative teaching, but some devices are not calibrated.', '2026-06-22 02:45:21'),
(49, 'Mike Chong', 3, 'The net is too loose, needs tightening.', '2026-06-29 02:45:21'),
(50, 'Emily Wong', 4, 'Fun place to play, but the court surface is slippery when wet.', '2026-06-27 02:45:21'),
(51, 'Daniel Raj', 5, 'Best volleyball court on campus, we have regular tournaments.', '2026-06-25 02:45:21'),
(52, 'Sofia Binti Ahmad', 4, 'Good for training, but the lines are faded.', '2026-06-23 02:45:21'),
(53, 'Owen Lim', 3, 'Could use some shade, it gets really hot in the afternoon.', '2026-06-20 02:45:21'),
(54, 'Lisa Goh', 2, 'Computer Lab is a nightmare – always out of order.', '2026-06-28 02:45:21'),
(55, 'Ryan Teoh', 5, 'Main Hall is the crown jewel of our campus.', '2026-06-26 02:45:21'),
(56, 'Megan Kaur', 4, 'Exam Hall is well lit and organised.', '2026-06-25 02:45:21'),
(57, 'Ethan Ng', 3, 'Basketball court is okay for practice, but not for official matches.', '2026-06-24 02:45:21'),
(58, 'Olivia Chew', 5, 'The field is huge! Love playing football there.', '2026-06-22 02:45:21'),
(59, 'Liam Yap', 4, 'Lecture Hall is great for lectures, but the Wi-Fi is spotty.', '2026-06-21 02:45:21'),
(60, 'Chloe Tan', 5, 'Smart Lab is my favourite place to learn new tech.', '2026-06-20 02:45:21'),
(61, 'Noah Chan', 3, 'Volleyball court needs better lighting for night games.', '2026-06-19 02:45:21'),
(62, 'Mia Koh', 4, 'Main Hall is always clean and well maintained.', '2026-06-18 02:45:21'),
(63, 'Lucas Ooi', 2, 'Field is often waterlogged after rain.', '2026-06-17 02:45:21'),
(64, 'James Tan', 4, 'Good court for casual play, but the surface is a bit rough on the knees.', '2026-06-28 03:41:49'),
(65, 'Sarah Lee', 5, 'I love playing here every evening – the atmosphere is electric!', '2026-06-26 03:41:49'),
(66, 'Mike Chong', 3, 'The hoops are rusty and the backboards are cracked.', '2026-06-24 03:41:49'),
(67, 'Emily Wong', 4, 'Decent court, but the lines need repainting for official games.', '2026-06-22 03:41:49'),
(68, 'Dr. Ahmad Fauzi', 5, 'Excellent facility for PE lessons, very spacious.', '2026-06-20 03:41:49'),
(69, 'Daniel Raj', 2, 'Too many cracks on the concrete – dangerous for fast breaks.', '2026-06-18 03:41:49'),
(70, 'Sofia Binti Ahmad', 4, 'Great for shooting practice, but the netting is torn.', '2026-06-16 03:41:49'),
(71, 'Owen Lim', 5, 'Best basketball court on campus – always clean and well lit.', '2026-06-14 03:41:49'),
(72, 'Lisa Goh', 3, 'Can get crowded during peak hours, but overall okay.', '2026-06-12 03:41:49'),
(73, 'Ryan Teoh', 4, 'Nice court, but the wind can be a factor outdoors.', '2026-06-10 03:41:49'),
(74, 'Megan Kaur', 5, 'Perfect for our 3x3 tournaments – plenty of space.', '2026-06-08 03:41:49'),
(75, 'Ethan Ng', 4, 'The hoops are just the right height, good for practice.', '2026-06-06 03:41:49'),
(76, 'Olivia Chew', 3, 'Needs more shade, it gets scorching at noon.', '2026-06-04 03:41:49'),
(77, 'Liam Yap', 5, 'I’ve had many great matches here – highly recommend.', '2026-06-02 03:41:49'),
(78, 'Chloe Tan', 4, 'Good court, but the surrounding area is a bit messy.', '2026-05-31 03:41:49'),
(79, 'Noah Chan', 2, 'Computers are ancient – takes forever to boot up.', '2026-06-29 03:41:49'),
(80, 'Mia Koh', 3, 'The lab is fine for basic tasks, but the printers are always broken.', '2026-06-27 03:41:49'),
(81, 'Lucas Ooi', 4, 'Good for coding assignments, but need more RAM.', '2026-06-25 03:41:49'),
(82, 'Amelia Foo', 1, 'Worst lab on campus – viruses everywhere!', '2026-06-23 03:41:49'),
(83, 'Julian Quah', 5, 'I love the quiet environment – great for focused work.', '2026-06-21 03:41:49'),
(84, 'Isabella Yong', 4, 'The lab assistant is very helpful, but the software is outdated.', '2026-06-19 03:41:49'),
(85, 'Raju Krishnan (Alumni)', 3, 'Same old machines from my time – needs an upgrade.', '2026-06-17 03:41:49'),
(86, 'Dr. Smith (Visiting)', 4, 'Adequate for simple programming, but not for intensive simulations.', '2026-06-15 03:41:49'),
(87, 'Aun', 2, 'The computers freeze often – very frustrating.', '2026-06-13 03:41:49'),
(88, 'Wenhui', 5, 'Actually, I find it decent for group projects – enough PCs for everyone.', '2026-06-11 03:41:49'),
(89, 'Kai Qing', 3, 'Some computers don’t have the required software installed.', '2026-06-09 03:41:49'),
(90, 'Dayana', 4, 'Good for quick internet access, but I bring my own laptop.', '2026-06-07 03:41:49'),
(91, 'System Administrator', 5, 'We maintain this lab regularly – it’s reliable for student use.', '2026-06-05 03:41:49'),
(92, 'Content Manager', 3, 'The lab is clean but the equipment is showing its age.', '2026-06-03 03:41:49'),
(93, 'Events Coordinator', 4, 'We use it for workshops – gets the job done.', '2026-06-01 03:41:49'),
(94, 'James Tan', 5, 'Quietest hall on campus – perfect for studying.', '2026-06-28 03:41:49'),
(95, 'Sarah Lee', 4, 'Seats are comfortable, but the air conditioning is too strong.', '2026-06-26 03:41:49'),
(96, 'Mike Chong', 3, 'The acoustics are echoey – can be distracting.', '2026-06-24 03:41:49'),
(97, 'Emily Wong', 5, 'Best place for mock exams – very professional setup.', '2026-06-22 03:41:49'),
(98, 'Dr. Ahmad Fauzi', 4, 'Well-lit and spacious, but the temperature fluctuates.', '2026-06-20 03:41:49'),
(99, 'Daniel Raj', 5, 'I always get good grades when I study here!', '2026-06-18 03:41:49'),
(100, 'Sofia Binti Ahmad', 3, 'The lighting is a bit harsh – can cause eye strain.', '2026-06-16 03:41:49'),
(101, 'Owen Lim', 4, 'Good for large groups, but the ventilation could be better.', '2026-06-14 03:41:49'),
(102, 'Lisa Goh', 5, 'Immaculately clean and organised – top marks!', '2026-06-12 03:41:49'),
(103, 'Ryan Teoh', 4, 'The exam invigilators are strict, but the hall itself is great.', '2026-06-10 03:41:49'),
(104, 'Megan Kaur', 2, 'Too many desks crammed together – not enough elbow room.', '2026-06-08 03:41:49'),
(105, 'Ethan Ng', 5, 'This hall is the gold standard for exams.', '2026-06-06 03:41:49'),
(106, 'Olivia Chew', 4, 'Good hall, but the clock is hard to see from the back.', '2026-06-04 03:41:49'),
(107, 'Liam Yap', 3, 'The hall is okay, but the chairs are uncomfortable for long exams.', '2026-06-02 03:41:49'),
(108, 'Chloe Tan', 5, 'Perfect venue for our final exams – highly recommended.', '2026-05-31 03:41:49'),
(109, 'Noah Chan', 5, 'Huge field – great for football and running.', '2026-06-29 03:41:49'),
(110, 'Mia Koh', 4, 'Grass is a bit long sometimes, but still playable.', '2026-06-27 03:41:49'),
(111, 'Lucas Ooi', 3, 'Needs goal posts – can’t play official matches without them.', '2026-06-25 03:41:49'),
(112, 'Amelia Foo', 5, 'We had our sports day here – amazing space!', '2026-06-23 03:41:49'),
(113, 'Julian Quah', 4, 'Good for drills, but the ground is uneven in some areas.', '2026-06-21 03:41:49'),
(114, 'Isabella Yong', 2, 'The field floods after heavy rain – becomes unusable.', '2026-06-19 03:41:49'),
(115, 'Raju Krishnan (Alumni)', 5, 'Reminds me of my university days – love this field.', '2026-06-17 03:41:49'),
(116, 'Dr. Smith (Visiting)', 4, 'Adequate for outdoor activities, but the markings are faded.', '2026-06-15 03:41:49'),
(117, 'Aun', 5, 'Best place for football – we have weekly matches here.', '2026-06-13 03:41:49'),
(118, 'Wenhui', 3, 'The grass is often overgrown – needs better maintenance.', '2026-06-11 03:41:49'),
(119, 'Kai Qing', 4, 'Good for casual play, but not great for competitive games.', '2026-06-09 03:41:49'),
(120, 'Dayana', 5, 'I love jogging here in the evenings – very serene.', '2026-06-07 03:41:49'),
(121, 'System Administrator', 4, 'We use it for outdoor events – it’s versatile.', '2026-06-05 03:41:49'),
(122, 'Content Manager', 3, 'Could use more benches and shade structures.', '2026-06-03 03:41:49'),
(123, 'Events Coordinator', 5, 'Perfect for large-scale outdoor festivals.', '2026-06-01 03:41:49'),
(124, 'James Tan', 5, 'Excellent acoustics and a clear projector.', '2026-06-28 03:41:49'),
(125, 'Sarah Lee', 4, 'Seats are comfortable, but the microphones are sometimes faulty.', '2026-06-26 03:41:49'),
(126, 'Mike Chong', 3, 'The stage is a bit cramped, but fine for lectures.', '2026-06-24 03:41:49'),
(127, 'Emily Wong', 5, 'One of the best lecture halls on campus – very modern.', '2026-06-22 03:41:49'),
(128, 'Dr. Ahmad Fauzi', 4, 'Good for large classes, but the Wi-Fi is weak.', '2026-06-20 03:41:49'),
(129, 'Daniel Raj', 5, 'Perfect for presentations – the screen is huge.', '2026-06-18 03:41:49'),
(130, 'Sofia Binti Ahmad', 3, 'The air conditioning is too loud – hard to hear sometimes.', '2026-06-16 03:41:49'),
(131, 'Owen Lim', 4, 'Great hall, but the power outlets are scarce.', '2026-06-14 03:41:49'),
(132, 'Lisa Goh', 5, 'I’ve attended many seminars here – always a great experience.', '2026-06-12 03:41:49'),
(133, 'Ryan Teoh', 4, 'The seats are a bit stiff, but overall good.', '2026-06-10 03:41:49'),
(134, 'Megan Kaur', 2, 'The lighting is poor – makes it hard to see the board.', '2026-06-08 03:41:49'),
(135, 'Ethan Ng', 5, 'Best hall for guest lectures – the stage is well designed.', '2026-06-06 03:41:49'),
(136, 'Olivia Chew', 4, 'Good hall, but the temperature varies too much.', '2026-06-04 03:41:49'),
(137, 'Liam Yap', 3, 'The hall is okay, but the seating arrangement is outdated.', '2026-06-02 03:41:49'),
(138, 'Chloe Tan', 5, 'I love teaching here – the students can see everything clearly.', '2026-05-31 03:41:49'),
(139, 'Noah Chan', 5, 'Huge space, perfect for concerts and weddings!', '2026-06-29 03:41:49'),
(140, 'Mia Koh', 4, 'The stage is massive, but the curtains are dusty.', '2026-06-27 03:41:49'),
(141, 'Lucas Ooi', 3, 'The sound system is great, but the lighting needs updating.', '2026-06-25 03:41:49'),
(142, 'Amelia Foo', 5, 'We held our graduation here – unforgettable.', '2026-06-23 03:41:49'),
(143, 'Julian Quah', 4, 'Good for large meetings, but the chairs are uncomfortable.', '2026-06-21 03:41:49'),
(144, 'Isabella Yong', 5, 'The most versatile hall on campus – highly recommended.', '2026-06-19 03:41:49'),
(145, 'Raju Krishnan (Alumni)', 3, 'It’s changed a lot since my time, but still the heart of the campus.', '2026-06-17 03:41:49'),
(146, 'Dr. Smith (Visiting)', 4, 'Excellent for conferences – the audio-visual setup is top-notch.', '2026-06-15 03:41:49'),
(147, 'Aun', 5, 'We use it for all our major events – it never disappoints.', '2026-06-13 03:41:49'),
(148, 'Wenhui', 4, 'The hall is grand, but the parking nearby is limited.', '2026-06-11 03:41:49'),
(149, 'Kai Qing', 3, 'It’s too echoey – can be hard to hear speeches.', '2026-06-09 03:41:49'),
(150, 'Dayana', 5, 'Beautiful hall, perfect for formal dinners.', '2026-06-07 03:41:49'),
(151, 'System Administrator', 5, 'We maintain it well – it’s the pride of our campus.', '2026-06-05 03:41:49'),
(152, 'Content Manager', 4, 'Great for exhibitions, but the lighting could be softer.', '2026-06-03 03:41:49'),
(153, 'Events Coordinator', 5, 'Our go-to venue for all large events – highly reliable.', '2026-06-01 03:41:49'),
(154, 'James Tan', 5, 'The VR headset is mind-blowing – best lab on campus!', '2026-06-28 03:41:49'),
(155, 'Sarah Lee', 4, 'IoT equipment is cutting-edge, but we need more sensors.', '2026-06-26 03:41:49'),
(156, 'Mike Chong', 3, 'The lab is cool, but often fully booked – hard to get a slot.', '2026-06-24 03:41:49'),
(157, 'Emily Wong', 5, 'I love the interactive whiteboard – makes learning fun.', '2026-06-22 03:41:49'),
(158, 'Dr. Ahmad Fauzi', 4, 'Great for innovative teaching, but some devices are not calibrated.', '2026-06-20 03:41:49'),
(159, 'Daniel Raj', 5, 'The 3D printers are awesome – we printed a model of our project.', '2026-06-18 03:41:49'),
(160, 'Sofia Binti Ahmad', 3, 'The lab is a bit small, but the tech is impressive.', '2026-06-16 03:41:49'),
(161, 'Owen Lim', 4, 'The smart boards are very responsive – great for demos.', '2026-06-14 03:41:49'),
(162, 'Lisa Goh', 5, 'My favourite place on campus – I spend hours here experimenting.', '2026-06-12 03:41:49'),
(163, 'Ryan Teoh', 4, 'Good lab, but the air conditioning is often broken.', '2026-06-10 03:41:49'),
(164, 'Megan Kaur', 2, 'The computers are slow for the VR software – needs an upgrade.', '2026-06-08 03:41:49'),
(165, 'Ethan Ng', 5, 'The lab is a game-changer for our engineering projects.', '2026-06-06 03:41:49'),
(166, 'Olivia Chew', 4, 'Great space, but we need more headsets for group work.', '2026-06-04 03:41:49'),
(167, 'Liam Yap', 3, 'The lab is nice, but the booking system is a hassle.', '2026-06-02 03:41:49'),
(168, 'Chloe Tan', 5, 'I’ve learned so much here – highly recommended.', '2026-05-31 03:41:49'),
(169, 'Noah Chan', 4, 'The court is good, but the net is too loose.', '2026-06-29 03:41:49'),
(170, 'Mia Koh', 5, 'We have regular tournaments here – always a blast.', '2026-06-27 03:41:49'),
(171, 'Lucas Ooi', 3, 'The surface is slippery when wet – dangerous.', '2026-06-25 03:41:49'),
(172, 'Amelia Foo', 4, 'Good for practice, but the lines are barely visible.', '2026-06-23 03:41:49'),
(173, 'Julian Quah', 5, 'Best court for volleyball – the sand is well maintained.', '2026-06-21 03:41:49'),
(174, 'Isabella Yong', 3, 'The court is okay, but there is no shade – gets too hot.', '2026-06-19 03:41:49'),
(175, 'Raju Krishnan (Alumni)', 4, 'I remember playing here in my college days – still great.', '2026-06-17 03:41:49'),
(176, 'Dr. Smith (Visiting)', 5, 'Excellent court for a friendly match – well marked.', '2026-06-15 03:41:49'),
(177, 'Aun', 4, 'We love playing here, but the net height is inconsistent.', '2026-06-13 03:41:49'),
(178, 'Wenhui', 3, 'The court is fine, but the surrounding area needs cleaning.', '2026-06-11 03:41:49'),
(179, 'Kai Qing', 5, 'Perfect for practice – the court is in great condition.', '2026-06-09 03:41:49'),
(180, 'Dayana', 4, 'Good court, but the lighting for night games is poor.', '2026-06-07 03:41:49'),
(181, 'System Administrator', 5, 'We keep it in top shape – one of the best courts.', '2026-06-05 03:41:49'),
(182, 'Content Manager', 4, 'Nice court, but the changing rooms nearby are not great.', '2026-06-03 03:41:49'),
(183, 'Events Coordinator', 3, 'The court is okay, but the parking is a bit far.', '2026-06-01 03:41:49'),
(184, 'James Tan', 5, 'Basketball court has the best vibe for evening games.', '2026-06-29 03:41:49'),
(185, 'Sarah Lee', 4, 'Computer lab is okay for basic internet but not for heavy work.', '2026-06-28 03:41:49'),
(186, 'Mike Chong', 3, 'Exam hall is too cold, bring a jacket.', '2026-06-27 03:41:49'),
(187, 'Emily Wong', 5, 'Field is huge and well maintained.', '2026-06-26 03:41:49'),
(188, 'Dr. Ahmad Fauzi', 4, 'Lecture hall has good acoustics.', '2026-06-25 03:41:49'),
(189, 'Daniel Raj', 5, 'Main hall is magnificent for any event.', '2026-06-24 03:41:49'),
(190, 'Sofia Binti Ahmad', 3, 'Smart lab is fun but limited equipment.', '2026-06-23 03:41:49'),
(191, 'Owen Lim', 4, 'Volleyball court is good for casual games.', '2026-06-22 03:41:49'),
(192, 'Lisa Goh', 2, 'Basketball court needs a new surface.', '2026-06-21 03:41:49'),
(193, 'Ryan Teoh', 5, 'Computer lab has a great atmosphere for coding.', '2026-06-20 03:41:49'),
(194, 'Megan Kaur', 4, 'Exam hall is the quietest place to study.', '2026-06-19 03:41:49'),
(195, 'Ethan Ng', 3, 'Field is often muddy after rain.', '2026-06-18 03:41:49'),
(196, 'Olivia Chew', 5, 'Lecture hall has the best projectors.', '2026-06-17 03:41:49'),
(197, 'Liam Yap', 4, 'Main hall is central to all campus activities.', '2026-06-16 03:41:49'),
(198, 'Chloe Tan', 3, 'Smart lab is good but the aircon is noisy.', '2026-06-15 03:41:49'),
(199, 'Noah Chan', 5, 'Volleyball court is my favourite spot.', '2026-06-14 03:41:49'),
(200, 'Mia Koh', 4, 'Basketball court is okay but the hoops are uneven.', '2026-06-13 03:41:49'),
(201, 'Lucas Ooi', 2, 'Computer lab is outdated – needs new PCs.', '2026-06-12 03:41:49'),
(202, 'Amelia Foo', 5, 'Exam hall is perfect for serious study.', '2026-06-11 03:41:49'),
(203, 'Julian Quah', 4, 'Field is great for morning runs.', '2026-06-10 03:41:49'),
(204, 'Isabella Yong', 3, 'Lecture hall is fine but the seats are uncomfortable.', '2026-06-09 03:41:49'),
(205, 'Raju Krishnan (Alumni)', 5, 'Main hall brings back great memories.', '2026-06-08 03:41:49'),
(206, 'Dr. Smith (Visiting)', 4, 'Smart lab is impressive for a university.', '2026-06-07 03:41:49'),
(207, 'Aun', 5, 'Volleyball court is well kept.', '2026-06-06 03:41:49'),
(208, 'Wenhui', 3, 'Basketball court could use more shade.', '2026-06-05 03:41:49'),
(209, 'Kai Qing', 4, 'Computer lab is decent but the software is old.', '2026-06-04 03:41:49'),
(210, 'Dayana', 5, 'Exam hall is the best study spot.', '2026-06-03 03:41:49'),
(211, 'System Administrator', 4, 'Field needs better drainage.', '2026-06-02 03:41:49'),
(212, 'Content Manager', 5, 'Lecture hall is great for workshops.', '2026-06-01 03:41:49'),
(213, 'Events Coordinator', 3, 'Main hall is good but parking is an issue.', '2026-05-31 03:41:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` varchar(20) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `email`, `profile_image`, `role`, `password`, `phone`, `created_at`) VALUES
('0', 'ai_assistant', 'AI Assistant', 'ai@ubook.com', NULL, 'bot', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', NULL, '2026-06-28 06:28:15'),
('1221101123', 'olivia', 'Olivia Chew', '1221101123@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=6', 'user', 'temp123', '012-3456791', '2026-06-29 18:42:12'),
('1221101649', 'kaiqing', 'Kai Qing', '1221101651@student.mmu.edu.my', NULL, 'user', 'temp123', NULL, '2026-06-28 06:28:15'),
('1221101651', 'Wenhui', 'Wenhui', '1221101649@student.mmu.edu.my', NULL, 'user', '$2y$10$rONjCy/d0HjP.Blg53.nBO/c/TSm9tEzs8VmVwIlZs18suEeUsD5O', NULL, '2026-06-28 06:28:15'),
('1221102233', 'lucas', 'Lucas Ooi', '1221102233@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456796', '2026-06-29 18:42:12'),
('1221102345', 'lisa', 'Lisa Goh', '1221102345@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=4', 'user', 'temp123', '012-3456787', '2026-06-29 18:42:12'),
('1221103210', 'mike', 'Mike Chong', '1221103210@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=1', 'user', 'temp123', '012-3456782', '2026-06-29 18:42:12'),
('1221103276', 'Aun', 'Aun', '1221103276@student.mmu.edu.my', NULL, 'user', '$2y$10$5Wg6UhS3pFYSe2NTaCwQqeAnj.oHdCb7TYPFasWx7bKycwQMIOGr.', NULL, '2026-06-28 06:28:15'),
('1221103344', 'liam', 'Liam Yap', '1221103344@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456792', '2026-06-29 18:42:12'),
('1221103456', 'megan', 'Megan Kaur', '1221103456@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=5', 'user', 'temp123', '012-3456789', '2026-06-29 18:42:12'),
('1221104321', 'daniel', 'Daniel Raj', '1221104321@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456784', '2026-06-29 18:42:12'),
('1221104455', 'amelia', 'Amelia Foo', '1221104455@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=9', 'user', 'temp123', '012-3456797', '2026-06-29 18:42:12'),
('1221105566', 'chloe', 'Chloe Tan', '1221105566@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=7', 'user', 'temp123', '012-3456793', '2026-06-29 18:42:12'),
('1221105678', 'sofia', 'Sofia Binti Ahmad', '1221105678@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=3', 'user', 'temp123', '012-3456785', '2026-06-29 18:42:12'),
('1221106543', 'sarah', 'Sarah Lee', '1221106543@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456781', '2026-06-29 18:42:12'),
('1221106677', 'julian', 'Julian Quah', '1221106677@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456798', '2026-06-29 18:42:12'),
('1221106789', 'ryan', 'Ryan Teoh', '1221106789@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456788', '2026-06-29 18:42:12'),
('1221107788', 'noah', 'Noah Chan', '1221107788@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456794', '2026-06-29 18:42:12'),
('1221107890', 'owen', 'Owen Lim', '1221107890@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456786', '2026-06-29 18:42:12'),
('1221108765', 'emily', 'Emily Wong', '1221108765@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=2', 'user', 'temp123', '012-3456783', '2026-06-29 18:42:12'),
('1221108899', 'isabella', 'Isabella Yong', '1221108899@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=10', 'user', 'temp123', '012-3456799', '2026-06-29 18:42:12'),
('1221108989', 'Ean', 'ghjk', 'aunyiqi168@gmail.com', NULL, 'user', '$2y$10$.fAMVNi2VIeyhdCkUAWx0.Z4U86QgEz2hfwX8HRaQ2oiv0tGTqQhO', '0146420998', '2026-06-29 18:09:34'),
('1221109012', 'ethan', 'Ethan Ng', '1221109012@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456790', '2026-06-29 18:42:12'),
('1221109876', 'james', 'James Tan', '1221109876@student.mmu.edu.my', NULL, 'user', 'temp123', '012-3456780', '2026-06-29 18:42:12'),
('1221109900', 'mia', 'Mia Koh', '1221109900@student.mmu.edu.my', 'https://i.pravatar.cc/150?img=8', 'user', 'temp123', '012-3456795', '2026-06-29 18:42:12'),
('241UT24100', 'dayana', 'Dayana', 'nor.dayana.mirza@student.mmu.edu.my', NULL, 'user', 'temp123', NULL, '2026-06-28 06:28:15'),
('admin_booking', 'booking_admin', 'Booking Administrator', 'aunyiqi168@gmail.com', NULL, 'booking_admin', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '0123456787', '2026-06-28 06:28:15'),
('admin_content', 'content_admin', 'Content Manager', 'content@ubook.com', NULL, 'review_admin', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '012-3470002', '2026-06-29 18:42:12'),
('admin_events', 'events_admin', 'Events Coordinator', 'events@ubook.com', NULL, 'booking_admin', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '012-3470003', '2026-06-29 18:42:12'),
('admin_review', 'review_admin', 'Review Administrator', 'aunyiqi168@gmail.com', NULL, 'review_admin', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '0123456788', '2026-06-28 06:28:15'),
('admin_super', 'super_admin', 'Super Administrator', 'aunyiqi168@gmail.com', NULL, 'super_admin', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '0123456789', '2026-06-28 06:28:15'),
('admin_system', 'sys_admin', 'System Administrator', 'sysadmin@ubook.com', NULL, 'super_admin', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '012-3470001', '2026-06-29 18:42:12'),
('ext_001', 'alumni_raju', 'Raju Krishnan (Alumni)', 'raju@alumni.mmu.edu.my', NULL, 'user', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '012-3480001', '2026-06-29 18:42:12'),
('ext_002', 'guest_smith', 'Dr. Smith (Visiting)', 'smith@external.com', 'https://i.pravatar.cc/150?img=12', 'user', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '012-3480002', '2026-06-29 18:42:12'),
('staff_001', 'dr_ahmad', 'Dr. Ahmad Fauzi', 'ahmad@mmu.edu.my', NULL, 'staff', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '012-3460001', '2026-06-29 18:42:12'),
('staff_002', 'ms_chan', 'Ms. Chan Mei Ling', 'meiling@mmu.edu.my', 'https://i.pravatar.cc/150?img=11', 'staff', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '012-3460002', '2026-06-29 18:42:12'),
('staff_003', 'prof_rahman', 'Prof. Abdul Rahman', 'rahman@mmu.edu.my', NULL, 'staff', '$2y$10$zEflL3EH5mtgPEGed0GgNOtRjWt9mMxdnnmNrXQLp/7mxSVKFdzS2', '012-3460003', '2026-06-29 18:42:12');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `name`, `description`, `image_url`) VALUES
('bas101', 'Basketball Court', 'Outdoor concrete court with two hoops, simple and slightly worn surface. The lines may be faded and the equipment is basic. Mostly used for casual games, PE lessons, and student activities.', 'https://marvel-b1-cdn.bc0a.com/f00000000213893/wausautile.com/media/slides/HoopsPark05.jpg'),
('cpl616', 'Computer Lab', 'Modest room with around 30 aging PCs, basic monitors, and essential software.', 'https://img.magnific.com/premium-photo/bright-computer-lab-with-modern-equipment-technology_889056-39214.jpg'),
('exm456', 'Exam Hall', 'Quiet, spacious hall with orderly rows of simple desks and chairs. Lighting is adequate and ventilation is fair. Used strictly for examinations and assessments.', 'https://www.teachingcollege.fse.manchester.ac.uk/wp-content/uploads/2022/07/exam-hall.jpg'),
('fld112', 'Field', 'Wide open grassy area with uneven patches and slightly overgrown sections. Used for football, running drills, and outdoor school activities.', 'https://soccer-fields.com/wp-content/uploads/2025/07/image-4.png'),
('lec131', 'Lecture Hall', 'Medium-sized hall with tiered seating and basic audio-visual equipment. Suitable for teaching, presentations, and group lectures.', 'https://i.pinimg.com/originals/fa/40/df/fa40df3d4641603432dc6cd50d29c20b.jpg'),
('mme123', 'Main Hall', 'Large multipurpose indoor space with a simple stage and seating. Used for assemblies, events, and school gatherings.', 'https://visualdisplaysltd.com/application/files/5015/7555/3801/Meeting_-_visual-displays-limited-dnp_LaserPanel_Meetingroom2.jpg'),
('sml415', 'Smart Lab', 'Compact learning space with modern but limited technology such as computers, VR headsets, and IoT tools. Used for IT lessons and practical experiments.', 'https://images.pexels.com/photos/1181467/pexels-photo-1181467.jpeg'),
('vol789', 'Volleyball Court', 'An outdoor hard court marked with a central net and basic boundary lines. The surface shows some wear from regular use, but it remains stable and playable for student activities. It is commonly used for PE lessons, training drills, and friendly matches among students.', 'https://tgctexas.com/wp-content/uploads/2024/06/IMG_1518-scaled.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `venue_comments`
--

CREATE TABLE `venue_comments` (
  `id` int(11) NOT NULL,
  `venue_id` varchar(20) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `comment` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venue_comments`
--

INSERT INTO `venue_comments` (`id`, `venue_id`, `user_id`, `comment`, `created_at`) VALUES
(1, 'bas101', '1221103276', 'Great court for casual games!', '2026-06-27 06:28:15'),
(2, 'bas101', '1221101651', 'The hoops are a bit rusty but usable.', '2026-06-26 06:28:15'),
(3, 'mme123', '1221103276', 'Perfect for our event, plenty of space.', '2026-06-25 06:28:15'),
(4, 'lec131', '0', 'Lecture hall has comfortable seating.', '2026-06-24 06:28:15'),
(5, 'vol789', '1221101649', 'Volleyball court needs a new net.', '2026-06-23 06:28:15'),
(6, 'fld112', '241UT24100', 'Grass is a bit long but okay for training.', '2026-06-28 00:28:15'),
(7, 'sml415', '1221103276', 'large venue', '2026-06-29 17:54:20'),
(8, 'bas101', '1221101651', 'Love playing here on weekends with friends.', '2026-06-28 18:22:58'),
(9, 'bas101', '1221101649', 'The lines need repainting urgently.', '2026-06-26 18:22:58'),
(10, 'cpl616', '1221101649', 'Please update the software to the latest version.', '2026-06-27 18:22:58'),
(11, 'cpl616', '241UT24100', 'Printers are always jammed. Please fix them!', '2026-06-28 18:22:58'),
(12, 'cpl616', '1221103276', 'Good for basic coding assignments.', '2026-06-24 18:22:58'),
(13, 'exm456', '1221103276', 'Very strict invigilation, but the seats are comfortable.', '2026-06-25 18:22:58'),
(14, 'exm456', 'admin_super', 'Well-maintained hall for formal exams.', '2026-06-22 18:22:58'),
(15, 'fld112', '1221101651', 'Perfect for our 5-a-side football matches!', '2026-06-27 18:22:58'),
(16, 'fld112', '241UT24100', 'Grass is too long, needs mowing.', '2026-06-23 18:22:58'),
(17, 'lec131', '1221101649', 'Acoustics are great for lectures.', '2026-06-28 18:22:58'),
(18, 'lec131', 'admin_booking', 'Booking process for this hall is always smooth.', '2026-06-26 18:22:58'),
(19, 'mme123', '1221103276', 'Stage lighting needs fixing before the next event.', '2026-06-24 18:22:58'),
(20, 'mme123', '241UT24100', 'Huge space for graduation ceremonies.', '2026-06-21 18:22:58'),
(21, 'sml415', '1221101649', 'The VR headsets are absolutely awesome!', '2026-06-27 18:22:58'),
(22, 'sml415', '241UT24100', 'Need more IoT sensor kits for group projects.', '2026-06-25 18:22:58'),
(23, 'sml415', '1221101651', 'Smart board is very responsive.', '2026-06-28 18:22:58'),
(24, 'vol789', '1221103276', 'Can we add a shade cover? Its too hot at noon.', '2026-06-26 18:22:58'),
(25, 'vol789', '1221101651', 'Net height is perfect for official matches.', '2026-06-23 18:22:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venue_id` (`venue_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chat_groups`
--
ALTER TABLE `chat_groups`
  ADD PRIMARY KEY (`event_name`);

--
-- Indexes for table `chat_group_messages`
--
ALTER TABLE `chat_group_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_name` (`event_name`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `chat_group_participants`
--
ALTER TABLE `chat_group_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_user` (`event_name`,`user_id`);

--
-- Indexes for table `chat_group_typing`
--
ALTER TABLE `chat_group_typing`
  ADD PRIMARY KEY (`event_name`,`user_id`);

--
-- Indexes for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`comment_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `password_reset_otp`
--
ALTER TABLE `password_reset_otp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_otp` (`email`,`otp`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `venue_comments`
--
ALTER TABLE `venue_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venue_id` (`venue_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `chat_group_messages`
--
ALTER TABLE `chat_group_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `chat_group_participants`
--
ALTER TABLE `chat_group_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `password_reset_otp`
--
ALTER TABLE `password_reset_otp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=214;

--
-- AUTO_INCREMENT for table `venue_comments`
--
ALTER TABLE `venue_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `venue_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `venue_comments`
--
ALTER TABLE `venue_comments`
  ADD CONSTRAINT `venue_comments_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `venue_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
