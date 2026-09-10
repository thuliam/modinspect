-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 10, 2026 at 05:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `modinspect`
--

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE `articles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `author_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `body` longtext NOT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `before_data`, `after_data`, `ip_address`, `created_at`) VALUES
(12, NULL, 'coverage_job_created', 'collector_job', 10, NULL, '{\"product_id\":76,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(13, NULL, 'coverage_job_created', 'collector_job', 11, NULL, '{\"product_id\":77,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(14, NULL, 'coverage_job_created', 'collector_job', 12, NULL, '{\"product_id\":78,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(15, NULL, 'coverage_job_created', 'collector_job', 13, NULL, '{\"product_id\":69,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(16, NULL, 'coverage_job_created', 'collector_job', 14, NULL, '{\"product_id\":70,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(17, NULL, 'coverage_job_created', 'collector_job', 15, NULL, '{\"product_id\":71,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(18, NULL, 'coverage_job_created', 'collector_job', 16, NULL, '{\"product_id\":2,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(19, NULL, 'coverage_job_created', 'collector_job', 17, NULL, '{\"product_id\":72,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(20, NULL, 'coverage_job_created', 'collector_job', 18, NULL, '{\"product_id\":73,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(21, NULL, 'coverage_job_created', 'collector_job', 19, NULL, '{\"product_id\":74,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(22, NULL, 'coverage_job_created', 'collector_job', 20, NULL, '{\"product_id\":75,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 15:54:43'),
(23, NULL, 'coverage_job_created', 'collector_job', 21, NULL, '{\"product_id\":20,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(24, NULL, 'coverage_job_created', 'collector_job', 22, NULL, '{\"product_id\":22,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(25, NULL, 'coverage_job_created', 'collector_job', 23, NULL, '{\"product_id\":1,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(26, NULL, 'coverage_job_created', 'collector_job', 24, NULL, '{\"product_id\":21,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(27, NULL, 'coverage_job_created', 'collector_job', 25, NULL, '{\"product_id\":23,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(28, NULL, 'coverage_job_created', 'collector_job', 26, NULL, '{\"product_id\":3,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(29, NULL, 'coverage_job_created', 'collector_job', 27, NULL, '{\"product_id\":25,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(30, NULL, 'coverage_job_created', 'collector_job', 28, NULL, '{\"product_id\":26,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(31, NULL, 'coverage_job_created', 'collector_job', 29, NULL, '{\"product_id\":96,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":97}', NULL, '2026-09-07 15:54:43'),
(32, NULL, 'coverage_job_created', 'collector_job', 30, NULL, '{\"product_id\":24,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":87}', NULL, '2026-09-07 15:54:43'),
(33, NULL, 'coverage_plan_run', 'coverage_plan', NULL, NULL, '{\"created\":21,\"skipped\":0,\"summary\":{\"products_scanned\":21,\"due_products\":21,\"blocked_by_active_jobs\":0,\"total_sample_gap\":420,\"total_fresh_gap\":168}}', NULL, '2026-09-07 15:54:43'),
(34, NULL, 'coverage_plan_run', 'coverage_plan', NULL, NULL, '{\"created\":0,\"skipped\":21,\"summary\":{\"products_scanned\":21,\"due_products\":0,\"blocked_by_active_jobs\":21,\"total_sample_gap\":420,\"total_fresh_gap\":168}}', NULL, '2026-09-07 15:54:49'),
(81, NULL, 'collection_job_completed', 'collector_job', 10, NULL, '{\"job_id\":10,\"product_id\":76,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 16:17:25'),
(82, NULL, 'collection_job_completed', 'collector_job', 11, NULL, '{\"job_id\":11,\"product_id\":77,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 16:17:25'),
(83, NULL, 'collection_job_completed', 'collector_job', 12, NULL, '{\"job_id\":12,\"product_id\":78,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 16:17:25'),
(84, NULL, 'collection_runner_run', 'collection_run', NULL, NULL, '{\"run_id\":\"20260907161725-b6c1d356\",\"dry_run\":false,\"jobs_scanned\":3,\"jobs_claimed\":3,\"jobs_completed\":3,\"jobs_failed\":0,\"jobs_skipped\":0,\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"duration_ms\":54,\"jobs\":[{\"job_id\":10,\"product_id\":76,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":11,\"product_id\":77,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":12,\"product_id\":78,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}]}', NULL, '2026-09-07 16:17:25'),
(208, NULL, 'coverage_job_created', 'collector_job', 148, NULL, '{\"product_id\":76,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 17:10:10'),
(209, NULL, 'coverage_job_created', 'collector_job', 149, NULL, '{\"product_id\":77,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 17:10:10'),
(210, NULL, 'coverage_job_created', 'collector_job', 150, NULL, '{\"product_id\":78,\"reasons\":[\"no_accepted_observations\",\"sample_gap\",\"fresh_sample_gap\",\"never_observed\"],\"priority\":99}', NULL, '2026-09-07 17:10:10'),
(211, NULL, 'coverage_plan_run', 'coverage_plan', NULL, NULL, '{\"created\":3,\"skipped\":0,\"summary\":{\"products_scanned\":21,\"due_products\":3,\"blocked_by_active_jobs\":18,\"total_sample_gap\":420,\"total_fresh_gap\":168}}', NULL, '2026-09-07 17:10:10'),
(212, NULL, 'collection_job_completed', 'collector_job', 13, NULL, '{\"job_id\":13,\"product_id\":69,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:19'),
(213, NULL, 'collection_job_completed', 'collector_job', 14, NULL, '{\"job_id\":14,\"product_id\":70,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:19'),
(214, NULL, 'collection_job_completed', 'collector_job', 15, NULL, '{\"job_id\":15,\"product_id\":71,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:19'),
(215, NULL, 'collection_runner_run', 'collection_run', NULL, NULL, '{\"run_id\":\"20260907171019-02ea68fe\",\"dry_run\":false,\"provider\":\"mock\",\"source_id\":9002,\"source_status\":\"enabled\",\"jobs_scanned\":3,\"jobs_claimed\":3,\"jobs_completed\":3,\"jobs_failed\":0,\"jobs_skipped\":0,\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"duration_ms\":39,\"jobs\":[{\"job_id\":13,\"product_id\":69,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":14,\"product_id\":70,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":15,\"product_id\":71,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}]}', NULL, '2026-09-07 17:10:19'),
(216, NULL, 'collection_job_completed', 'collector_job', 16, NULL, '{\"job_id\":16,\"product_id\":2,\"status\":\"completed\",\"candidates_created\":2,\"evidence_created\":2,\"extractions_created\":2,\"reviews_created\":2,\"green\":0,\"amber\":1,\"red\":1,\"skipped_wrong_product\":1,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(217, NULL, 'collection_job_completed', 'collector_job', 17, NULL, '{\"job_id\":17,\"product_id\":72,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(218, NULL, 'collection_job_completed', 'collector_job', 18, NULL, '{\"job_id\":18,\"product_id\":73,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(219, NULL, 'collection_job_completed', 'collector_job', 19, NULL, '{\"job_id\":19,\"product_id\":74,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(220, NULL, 'collection_job_completed', 'collector_job', 20, NULL, '{\"job_id\":20,\"product_id\":75,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(221, NULL, 'collection_job_completed', 'collector_job', 21, NULL, '{\"job_id\":21,\"product_id\":20,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(222, NULL, 'collection_job_completed', 'collector_job', 22, NULL, '{\"job_id\":22,\"product_id\":22,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(223, NULL, 'collection_job_completed', 'collector_job', 23, NULL, '{\"job_id\":23,\"product_id\":1,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":2,\"amber\":2,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(224, NULL, 'collection_job_completed', 'collector_job', 24, NULL, '{\"job_id\":24,\"product_id\":21,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(225, NULL, 'collection_job_completed', 'collector_job', 25, NULL, '{\"job_id\":25,\"product_id\":23,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(226, NULL, 'collection_job_completed', 'collector_job', 26, NULL, '{\"job_id\":26,\"product_id\":3,\"status\":\"completed\",\"candidates_created\":2,\"evidence_created\":2,\"extractions_created\":2,\"reviews_created\":2,\"green\":0,\"amber\":0,\"red\":2,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(227, NULL, 'collection_job_completed', 'collector_job', 27, NULL, '{\"job_id\":27,\"product_id\":25,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(228, NULL, 'collection_job_completed', 'collector_job', 28, NULL, '{\"job_id\":28,\"product_id\":26,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(229, NULL, 'collection_job_completed', 'collector_job', 29, NULL, '{\"job_id\":29,\"product_id\":96,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(230, NULL, 'collection_job_completed', 'collector_job', 30, NULL, '{\"job_id\":30,\"product_id\":24,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(231, NULL, 'collection_job_completed', 'collector_job', 148, NULL, '{\"job_id\":148,\"product_id\":76,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(232, NULL, 'collection_job_completed', 'collector_job', 149, NULL, '{\"job_id\":149,\"product_id\":77,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(233, NULL, 'collection_job_completed', 'collector_job', 150, NULL, '{\"job_id\":150,\"product_id\":78,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}', NULL, '2026-09-07 17:10:31'),
(234, NULL, 'collection_runner_run', 'collection_run', NULL, NULL, '{\"run_id\":\"20260907171031-9eae992e\",\"dry_run\":false,\"provider\":\"mock\",\"source_id\":9002,\"source_status\":\"enabled\",\"jobs_scanned\":18,\"jobs_claimed\":18,\"jobs_completed\":18,\"jobs_failed\":0,\"jobs_skipped\":0,\"candidates_created\":13,\"evidence_created\":13,\"extractions_created\":13,\"reviews_created\":13,\"green\":5,\"amber\":6,\"red\":7,\"duration_ms\":583,\"jobs\":[{\"job_id\":16,\"product_id\":2,\"status\":\"completed\",\"candidates_created\":2,\"evidence_created\":2,\"extractions_created\":2,\"reviews_created\":2,\"green\":0,\"amber\":1,\"red\":1,\"skipped_wrong_product\":1,\"errors\":[]},{\"job_id\":17,\"product_id\":72,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":18,\"product_id\":73,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":19,\"product_id\":74,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":20,\"product_id\":75,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":21,\"product_id\":20,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":22,\"product_id\":22,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":23,\"product_id\":1,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":2,\"amber\":2,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":24,\"product_id\":21,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":25,\"product_id\":23,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":26,\"product_id\":3,\"status\":\"completed\",\"candidates_created\":2,\"evidence_created\":2,\"extractions_created\":2,\"reviews_created\":2,\"green\":0,\"amber\":0,\"red\":2,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":27,\"product_id\":25,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":28,\"product_id\":26,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":29,\"product_id\":96,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":30,\"product_id\":24,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":148,\"product_id\":76,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":149,\"product_id\":77,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":150,\"product_id\":78,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}]}', NULL, '2026-09-07 17:10:32');

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `slug`, `created_at`) VALUES
(1, 'AMD', 'amd', '2026-07-27 13:52:23'),
(2, 'NVIDIA', 'nvidia', '2026-07-27 13:52:23'),
(3, 'Intel', 'intel', '2026-07-27 13:52:23'),
(4, 'ASUS', 'asus', '2026-07-27 15:55:41'),
(5, 'MSI', 'msi', '2026-07-27 15:55:41'),
(6, 'Gigabyte', 'gigabyte', '2026-07-27 15:55:41'),
(7, 'ASRock', 'asrock', '2026-07-27 15:55:41'),
(8, 'Corsair', 'corsair', '2026-07-27 15:55:41'),
(9, 'Kingston', 'kingston', '2026-07-27 15:55:41'),
(10, 'Samsung', 'samsung', '2026-07-27 15:55:41'),
(11, 'Western Digital', 'western-digital', '2026-07-27 15:55:41'),
(12, 'Cooler Master', 'cooler-master', '2026-07-27 15:55:41'),
(13, 'Thermaltake', 'thermaltake', '2026-07-27 15:55:41');

-- --------------------------------------------------------

--
-- Table structure for table `collection_runs`
--

CREATE TABLE `collection_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `run_id` varchar(80) NOT NULL,
  `source_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(120) NOT NULL,
  `job_type` varchar(80) NOT NULL,
  `execution_mode` enum('normal','dry_run') NOT NULL DEFAULT 'normal',
  `started_at` datetime NOT NULL,
  `finished_at` datetime NOT NULL,
  `duration_ms` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `jobs_scanned` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `jobs_claimed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `jobs_completed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `jobs_failed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `jobs_skipped` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `candidates_created` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `evidence_created` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `extractions_created` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reviews_created` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `green_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `amber_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `red_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `error_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `summary_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`summary_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `collection_runs`
--

INSERT INTO `collection_runs` (`id`, `run_id`, `source_id`, `provider`, `job_type`, `execution_mode`, `started_at`, `finished_at`, `duration_ms`, `jobs_scanned`, `jobs_claimed`, `jobs_completed`, `jobs_failed`, `jobs_skipped`, `candidates_created`, `evidence_created`, `extractions_created`, `reviews_created`, `green_count`, `amber_count`, `red_count`, `error_count`, `summary_json`, `created_at`) VALUES
(19, '20260907171019-02ea68fe', 9002, 'mock', 'coverage_mock_collection', 'normal', '2026-09-07 17:10:19', '2026-09-07 17:10:19', 39, 3, 3, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '{\"run_id\":\"20260907171019-02ea68fe\",\"dry_run\":false,\"provider\":\"mock\",\"source_id\":9002,\"source_status\":\"enabled\",\"jobs_scanned\":3,\"jobs_claimed\":3,\"jobs_completed\":3,\"jobs_failed\":0,\"jobs_skipped\":0,\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"duration_ms\":39,\"jobs\":[{\"job_id\":13,\"product_id\":69,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":14,\"product_id\":70,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":15,\"product_id\":71,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}]}', '2026-09-07 17:10:19'),
(20, '20260907171031-9eae992e', 9002, 'mock', 'coverage_mock_collection', 'normal', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 583, 18, 18, 18, 0, 0, 13, 13, 13, 13, 5, 6, 7, 0, '{\"run_id\":\"20260907171031-9eae992e\",\"dry_run\":false,\"provider\":\"mock\",\"source_id\":9002,\"source_status\":\"enabled\",\"jobs_scanned\":18,\"jobs_claimed\":18,\"jobs_completed\":18,\"jobs_failed\":0,\"jobs_skipped\":0,\"candidates_created\":13,\"evidence_created\":13,\"extractions_created\":13,\"reviews_created\":13,\"green\":5,\"amber\":6,\"red\":7,\"duration_ms\":583,\"jobs\":[{\"job_id\":16,\"product_id\":2,\"status\":\"completed\",\"candidates_created\":2,\"evidence_created\":2,\"extractions_created\":2,\"reviews_created\":2,\"green\":0,\"amber\":1,\"red\":1,\"skipped_wrong_product\":1,\"errors\":[]},{\"job_id\":17,\"product_id\":72,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":18,\"product_id\":73,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":19,\"product_id\":74,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":20,\"product_id\":75,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":21,\"product_id\":20,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":22,\"product_id\":22,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":23,\"product_id\":1,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":2,\"amber\":2,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":24,\"product_id\":21,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":25,\"product_id\":23,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":26,\"product_id\":3,\"status\":\"completed\",\"candidates_created\":2,\"evidence_created\":2,\"extractions_created\":2,\"reviews_created\":2,\"green\":0,\"amber\":0,\"red\":2,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":27,\"product_id\":25,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":28,\"product_id\":26,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":29,\"product_id\":96,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":30,\"product_id\":24,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":148,\"product_id\":76,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":149,\"product_id\":77,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]},{\"job_id\":150,\"product_id\":78,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}]}', '2026-09-07 17:10:31');

-- --------------------------------------------------------

--
-- Table structure for table `collector_jobs`
--

CREATE TABLE `collector_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_id` bigint(20) UNSIGNED NOT NULL,
  `job_type` varchar(80) NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `query_text` varchar(255) DEFAULT NULL,
  `status` enum('queued','running','completed','partial','failed') NOT NULL DEFAULT 'queued',
  `attempt_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `run_id` varchar(80) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `raw_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `valid_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `api_cost` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `collector_jobs`
--

INSERT INTO `collector_jobs` (`id`, `source_id`, `job_type`, `product_id`, `query_text`, `status`, `attempt_count`, `run_id`, `started_at`, `completed_at`, `raw_count`, `valid_count`, `error_message`, `api_cost`, `created_at`, `updated_at`) VALUES
(1, 9002, 'mock_fixture_collection', NULL, '5700x3d', 'completed', 0, NULL, '2026-09-07 15:29:26', '2026-09-07 15:29:26', 5, 4, NULL, 0.0000, '2026-09-07 15:29:26', '2026-09-07 15:29:26'),
(2, 9002, 'mock_fixture_collection', NULL, '5700x3d', 'completed', 0, NULL, '2026-09-07 15:31:31', '2026-09-07 15:31:31', 5, 4, NULL, 0.0000, '2026-09-07 15:31:31', '2026-09-07 15:31:31'),
(10, 9002, 'coverage_mock_collection', 76, 'AMD Radeon RX 6600 XT มือสอง', 'completed', 0, NULL, '2026-09-07 16:17:25', '2026-09-07 16:17:25', 0, 0, '{\"job_id\":10,\"product_id\":76,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 16:17:25'),
(11, 9002, 'coverage_mock_collection', 77, 'AMD Radeon RX 6700 XT มือสอง', 'completed', 0, NULL, '2026-09-07 16:17:25', '2026-09-07 16:17:25', 0, 0, '{\"job_id\":11,\"product_id\":77,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 16:17:25'),
(12, 9002, 'coverage_mock_collection', 78, 'AMD Radeon RX 7800 XT มือสอง', 'completed', 0, NULL, '2026-09-07 16:17:25', '2026-09-07 16:17:25', 0, 0, '{\"job_id\":12,\"product_id\":78,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 16:17:25'),
(13, 9002, 'coverage_mock_collection', 69, 'NVIDIA GeForce GTX 1660 Super มือสอง', 'completed', 1, '20260907171019-02ea68fe', '2026-09-07 17:10:19', '2026-09-07 17:10:19', 0, 0, '{\"job_id\":13,\"product_id\":69,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:19'),
(14, 9002, 'coverage_mock_collection', 70, 'NVIDIA GeForce RTX 2060 มือสอง', 'completed', 1, '20260907171019-02ea68fe', '2026-09-07 17:10:19', '2026-09-07 17:10:19', 0, 0, '{\"job_id\":14,\"product_id\":70,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:19'),
(15, 9002, 'coverage_mock_collection', 71, 'NVIDIA GeForce RTX 3060 Ti มือสอง', 'completed', 1, '20260907171019-02ea68fe', '2026-09-07 17:10:19', '2026-09-07 17:10:19', 0, 0, '{\"job_id\":15,\"product_id\":71,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:19'),
(16, 9002, 'coverage_mock_collection', 2, 'NVIDIA GeForce RTX 3070 มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 2, 1, '{\"job_id\":16,\"product_id\":2,\"status\":\"completed\",\"candidates_created\":2,\"evidence_created\":2,\"extractions_created\":2,\"reviews_created\":2,\"green\":0,\"amber\":1,\"red\":1,\"skipped_wrong_product\":1,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(17, 9002, 'coverage_mock_collection', 72, 'NVIDIA GeForce RTX 3080 มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":17,\"product_id\":72,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(18, 9002, 'coverage_mock_collection', 73, 'NVIDIA GeForce RTX 4060 Ti มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":18,\"product_id\":73,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(19, 9002, 'coverage_mock_collection', 74, 'NVIDIA GeForce RTX 4070 มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":19,\"product_id\":74,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(20, 9002, 'coverage_mock_collection', 75, 'NVIDIA GeForce RTX 4070 Ti มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":20,\"product_id\":75,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(21, 9002, 'coverage_mock_collection', 20, 'AMD Ryzen 5 5600 มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":21,\"product_id\":20,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(22, 9002, 'coverage_mock_collection', 22, 'AMD Ryzen 5 7600 มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":22,\"product_id\":22,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(23, 9002, 'coverage_mock_collection', 1, 'AMD Ryzen 7 5700X3D มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 4, '{\"job_id\":23,\"product_id\":1,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":2,\"amber\":2,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(24, 9002, 'coverage_mock_collection', 21, 'AMD Ryzen 7 5800X มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":24,\"product_id\":21,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(25, 9002, 'coverage_mock_collection', 23, 'AMD Ryzen 7 7800X3D มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":25,\"product_id\":23,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(26, 9002, 'coverage_mock_collection', 3, 'Intel Core i5-12400F มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 2, 0, '{\"job_id\":26,\"product_id\":3,\"status\":\"completed\",\"candidates_created\":2,\"evidence_created\":2,\"extractions_created\":2,\"reviews_created\":2,\"green\":0,\"amber\":0,\"red\":2,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(27, 9002, 'coverage_mock_collection', 25, 'Intel Core i5-13400F มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":27,\"product_id\":25,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(28, 9002, 'coverage_mock_collection', 26, 'Intel Core i5-13600K มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":28,\"product_id\":26,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(29, 9002, 'coverage_mock_collection', 96, 'Intel Core i7-12700F มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":29,\"product_id\":96,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(30, 9002, 'coverage_mock_collection', 24, 'Intel Core i3-12100F มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 0, 0, '{\"job_id\":30,\"product_id\":24,\"status\":\"completed\",\"candidates_created\":0,\"evidence_created\":0,\"extractions_created\":0,\"reviews_created\":0,\"green\":0,\"amber\":0,\"red\":0,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 15:54:43', '2026-09-07 17:10:31'),
(148, 9002, 'coverage_mock_collection', 76, 'AMD Radeon RX 6600 XT มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 3, 2, '{\"job_id\":148,\"product_id\":76,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 17:10:10', '2026-09-07 17:10:31'),
(149, 9002, 'coverage_mock_collection', 77, 'AMD Radeon RX 6700 XT มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 3, 2, '{\"job_id\":149,\"product_id\":77,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 17:10:10', '2026-09-07 17:10:31'),
(150, 9002, 'coverage_mock_collection', 78, 'AMD Radeon RX 7800 XT มือสอง', 'completed', 1, '20260907171031-9eae992e', '2026-09-07 17:10:31', '2026-09-07 17:10:31', 3, 2, '{\"job_id\":150,\"product_id\":78,\"status\":\"completed\",\"candidates_created\":3,\"evidence_created\":3,\"extractions_created\":3,\"reviews_created\":3,\"green\":1,\"amber\":1,\"red\":1,\"skipped_wrong_product\":0,\"errors\":[]}', 0.0000, '2026-09-07 17:10:10', '2026-09-07 17:10:31');

-- --------------------------------------------------------

--
-- Table structure for table `data_sources`
--

CREATE TABLE `data_sources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_key` varchar(120) NOT NULL,
  `name` varchar(120) NOT NULL,
  `domain` varchar(190) DEFAULT NULL,
  `source_type` enum('marketplace','forum','shop','user','research','manual') NOT NULL,
  `access_method` enum('api','feed','manual','browser','upload') NOT NULL,
  `allowed_collection_method` varchar(80) NOT NULL DEFAULT 'manual',
  `risk_level` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `reliability_score` decimal(5,2) NOT NULL DEFAULT 50.00,
  `evidence_quality` enum('high','medium','low','unknown') NOT NULL DEFAULT 'unknown',
  `freshness_expectation_days` int(10) UNSIGNED DEFAULT NULL,
  `request_budget_per_day` int(10) UNSIGNED DEFAULT NULL,
  `cost_budget_per_day` decimal(10,4) DEFAULT NULL,
  `terms_note` text DEFAULT NULL,
  `robots_note` text DEFAULT NULL,
  `policy_note` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_paused` tinyint(1) NOT NULL DEFAULT 0,
  `pause_reason` text DEFAULT NULL,
  `disabled_reason` text DEFAULT NULL,
  `last_success_at` datetime DEFAULT NULL,
  `last_blocked_at` datetime DEFAULT NULL,
  `last_failure_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `data_sources`
--

INSERT INTO `data_sources` (`id`, `source_key`, `name`, `domain`, `source_type`, `access_method`, `allowed_collection_method`, `risk_level`, `reliability_score`, `evidence_quality`, `freshness_expectation_days`, `request_budget_per_day`, `cost_budget_per_day`, `terms_note`, `robots_note`, `policy_note`, `is_active`, `is_paused`, `pause_reason`, `disabled_reason`, `last_success_at`, `last_blocked_at`, `last_failure_at`, `created_at`, `updated_at`) VALUES
(1, 'community_sample', 'Community sample', 'community.example', 'forum', 'manual', 'manual', 'medium', 65.00, 'medium', 14, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, '2026-07-27 14:09:55', NULL, NULL, '2026-07-27 14:09:55', '2026-09-07 17:02:31'),
(2, 'user_submission', 'User submission', NULL, 'user', 'upload', 'upload', 'low', 85.00, 'high', 14, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, '2026-07-27 14:09:55', NULL, NULL, '2026-07-27 14:09:55', '2026-09-07 17:02:31'),
(3, 'shop_partner_sample', 'Shop partner sample', 'shop.example', 'shop', 'feed', 'feed', 'low', 85.00, 'high', 14, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, NULL, '2026-07-27 14:09:55', NULL, NULL, '2026-07-27 14:09:55', '2026-09-07 17:02:31'),
(9002, 'mock_fixture_provider', 'Mock fixture provider', 'fixture.local', 'research', 'manual', 'manual', 'low', 85.00, 'high', 14, NULL, NULL, 'Local fixture-only source for dry-run and calibration tests.', NULL, NULL, 1, 0, NULL, NULL, '2026-09-07 17:10:31', NULL, NULL, '2026-09-07 15:29:26', '2026-09-07 17:10:31'),
(9302, 'gemini_google_search', 'Gemini Google Search POC', 'googleapis.com', 'research', 'api', 'api', 'medium', 60.00, 'medium', 7, 3, NULL, 'Provider source is inert until live and provider guards pass.', NULL, 'Phase 2D-A POC source; no bypass behavior allowed.', 1, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-07 17:45:37', '2026-09-07 17:45:37');

-- --------------------------------------------------------

--
-- Table structure for table `deal_checks`
--

CREATE TABLE `deal_checks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_price` decimal(12,2) NOT NULL,
  `condition_level` enum('like_new','good','fair','poor','unknown') NOT NULL DEFAULT 'unknown',
  `warranty_months` smallint(5) UNSIGNED DEFAULT NULL,
  `has_box` tinyint(1) NOT NULL DEFAULT 0,
  `has_receipt` tinyint(1) NOT NULL DEFAULT 0,
  `has_benchmark` tinyint(1) NOT NULL DEFAULT 0,
  `intended_use` varchar(100) DEFAULT NULL,
  `price_score` decimal(5,2) DEFAULT NULL,
  `risk_score` decimal(5,2) DEFAULT NULL,
  `value_score` decimal(5,2) DEFAULT NULL,
  `fit_score` decimal(5,2) DEFAULT NULL,
  `result_label` enum('below_range','in_range','above_range','insufficient') NOT NULL,
  `suggested_price_min` decimal(12,2) DEFAULT NULL,
  `suggested_price_max` decimal(12,2) DEFAULT NULL,
  `recommendation` text NOT NULL,
  `warning_note` text DEFAULT NULL,
  `consent_to_anonymous_observation` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deal_checks`
--

INSERT INTO `deal_checks` (`id`, `uuid`, `product_id`, `product_variant_id`, `user_price`, `condition_level`, `warranty_months`, `has_box`, `has_receipt`, `has_benchmark`, `intended_use`, `price_score`, `risk_score`, `value_score`, `fit_score`, `result_label`, `suggested_price_min`, `suggested_price_max`, `recommendation`, `warning_note`, `consent_to_anonymous_observation`, `created_at`, `updated_at`) VALUES
(1, '5b4daf00-01f4-42e9-ac9b-0dc320f76e6d', 1, NULL, 7500.00, 'good', 6, 1, 0, 1, NULL, NULL, NULL, NULL, NULL, 'in_range', 7200.00, 7800.00, 'ราคานี้อยู่ในช่วงตลาดที่ตรวจพบ', 'ข้อมูลเป็นราคาประกาศ ไม่ใช่ราคาปิดดีลจริง', 0, '2026-07-27 13:53:38', '2026-07-27 13:53:38'),
(2, '83e76441-dfef-4439-931c-20a15247a350', 20, NULL, 5500.00, 'unknown', NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 'above_range', 2600.00, 3200.00, 'ราคานี้สูงกว่าช่วงตลาดที่ตรวจพบ แต่ประกัน สภาพ และอุปกรณ์ที่ครบอาจรองรับส่วนต่างได้', 'เปรียบเทียบประกันและหลักฐานการทดสอบก่อนตัดสินใจ', 0, '2026-07-27 17:03:39', '2026-07-27 17:03:39'),
(3, 'e61fedaa-3de6-48d8-9e66-0939678e37c0', 25, NULL, 12900.00, 'fair', 2, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, 'above_range', 5200.00, 6200.00, 'ราคานี้สูงกว่าช่วงตลาดที่ตรวจพบ แต่ประกัน สภาพ และอุปกรณ์ที่ครบอาจรองรับส่วนต่างได้', 'เปรียบเทียบประกันและหลักฐานการทดสอบก่อนตัดสินใจ', 1, '2026-09-07 17:31:02', '2026-09-07 17:31:02');

-- --------------------------------------------------------

--
-- Table structure for table `extraction_runs`
--

CREATE TABLE `extraction_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `raw_observation_id` bigint(20) UNSIGNED NOT NULL,
  `evidence_id` bigint(20) UNSIGNED DEFAULT NULL,
  `provider_name` varchar(120) NOT NULL,
  `model_name` varchar(120) DEFAULT NULL,
  `prompt_version` varchar(80) DEFAULT NULL,
  `schema_version` varchar(80) NOT NULL DEFAULT 'market_observation_v1',
  `extracted_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`extracted_data`)),
  `confidence` decimal(6,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `extraction_runs`
--

INSERT INTO `extraction_runs` (`id`, `raw_observation_id`, `evidence_id`, `provider_name`, `model_name`, `prompt_version`, `schema_version`, `extracted_data`, `confidence`, `created_at`) VALUES
(1, 5, 1, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":7500,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"good\",\"warrantyMonths\":5,\"hasBox\":true,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"ขาย AMD Ryzen 7 5700X3D ประกัน 5 เดือน มีกล่อง ใช้งานปกติ\",\"price_text\":\"฿7,500\"}}', 0.9500, '2026-09-07 15:29:26'),
(2, 6, 2, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":7300,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"good\",\"warrantyMonths\":3,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"AMD 5700X3D มือสอง ใช้งานได้ดี ประกัน 3 เดือน\",\"price_text\":\"7300\"}}', 0.9500, '2026-09-07 15:29:26'),
(3, 7, 3, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":7900,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"like_new\",\"warrantyMonths\":null,\"hasBox\":true,\"hasReceipt\":true,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"R7 5700X3D สวย มีกล่องและใบเสร็จ\",\"price_text\":\"7900\"}}', 0.9500, '2026-09-07 15:29:26'),
(4, 8, 4, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":6000,\"currency\":\"THB\",\"listingType\":\"wanted\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":true,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"รับซื้อ 5700x3d งบ 6000\",\"price_text\":\"6000\"}}', 0.8000, '2026-09-07 15:29:26'),
(5, 9, 5, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":10500,\"currency\":\"THB\",\"listingType\":\"bundle\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":true,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"Ryzen5700X3D + B550 bundle พร้อมกล่อง\",\"price_text\":\"10500 บาท\"}}', 0.9500, '2026-09-07 15:29:26'),
(90, 199, 90, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":7800,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"good\",\"warrantyMonths\":2,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"ขาย RTX3070 8GB ใช้งานได้ดี ประกัน 2 เดือน\",\"price_text\":\"7,800\"}}', 0.9500, '2026-09-07 17:10:31'),
(91, 200, 91, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":500,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":true,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"จอง RTX 3070 มัดจำก่อน 500\",\"price_text\":\"500\"}}', 0.8000, '2026-09-07 17:10:31'),
(92, 206, 92, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":18500,\"currency\":\"THB\",\"listingType\":\"whole_pc\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":true,\"confidence\":0.8,\"raw\":{\"title\":\"คอมทั้งชุด i5 12400F RTX 3070\",\"price_text\":\"18500\"}}', 0.8000, '2026-09-07 17:10:31'),
(93, 207, 93, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":900,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"poor\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":true,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"Core i5 12400F เปิดไม่ติด ขายเป็นอะไหล่\",\"price_text\":\"900\"}}', 0.8000, '2026-09-07 17:10:31'),
(94, 208, 94, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":5400,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"AMD Radeon RX 6600 XT มือสอง\",\"price_text\":\"5400 บาท\"}}', 0.9500, '2026-09-07 17:10:31'),
(95, 209, 95, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":6100,\"currency\":\"THB\",\"listingType\":\"bundle\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":true,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"RX6600XT พร้อมกล่อง ราคาแบ่งได้\",\"price_text\":\"6100\"}}', 0.9500, '2026-09-07 17:10:31'),
(96, 210, 96, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":4200,\"currency\":\"THB\",\"listingType\":\"wanted\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":true,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"รับซื้อ RX 6600 XT งบ 4200\",\"price_text\":\"4200\"}}', 0.8000, '2026-09-07 17:10:31'),
(97, 211, 97, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":9300,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"AMD Radeon RX 6700 XT มือสอง\",\"price_text\":\"9300\"}}', 0.9500, '2026-09-07 17:10:31'),
(98, 212, 98, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":11500,\"currency\":\"THB\",\"listingType\":\"bundle\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"RX 6700 XT + PSU 650W พร้อมใช้งาน\",\"price_text\":\"11500\"}}', 0.9500, '2026-09-07 17:10:31'),
(99, 213, 99, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":2500,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"poor\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":true,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"RX6700XT เสีย เปิดไม่ติด ขายซ่อม\",\"price_text\":\"2500\"}}', 0.8000, '2026-09-07 17:10:31'),
(100, 214, 100, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":18200,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"AMD Radeon RX 7800 XT มือสอง\",\"price_text\":\"18200\"}}', 0.9500, '2026-09-07 17:10:31'),
(101, 215, 101, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":20000,\"currency\":\"THB\",\"listingType\":\"bundle\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"Radeon 7800 XT พร้อมของแถม\",\"price_text\":\"20000\"}}', 0.9500, '2026-09-07 17:10:31'),
(102, 216, 102, 'rule_based_fixture', NULL, 'rules_v1', 'market_observation_v1', '{\"askingPrice\":1000,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":true,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"จอง RX7800XT มัดจำ 1000 ก่อนนัดรับ\",\"price_text\":\"1000\"}}', 0.8000, '2026-09-07 17:10:31');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `article_id` bigint(20) UNSIGNED DEFAULT NULL,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email_hash` char(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_evidence`
--

CREATE TABLE `market_evidence` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `raw_observation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_id` bigint(20) UNSIGNED NOT NULL,
  `evidence_level` enum('A','B','C','D','E') NOT NULL DEFAULT 'C',
  `evidence_type` enum('url','snippet','screenshot','page_capture','upload','fixture') NOT NULL DEFAULT 'url',
  `source_url_hash` char(64) DEFAULT NULL,
  `storage_path` varchar(255) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content_hash` char(64) DEFAULT NULL,
  `captured_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `market_evidence`
--

INSERT INTO `market_evidence` (`id`, `raw_observation_id`, `source_id`, `evidence_level`, `evidence_type`, `source_url_hash`, `storage_path`, `excerpt`, `content_hash`, `captured_at`, `expires_at`, `is_public`, `created_at`) VALUES
(1, 5, 9002, 'B', 'fixture', '099c70e407d5ec68d24909f29a8f1720c701f1b42705ae24929341719adc28b5', NULL, 'ขาย AMD Ryzen 7 5700X3D ประกัน 5 เดือน มีกล่อง ใช้งานปกติ', '3245190b921096195e6cd3ef8d4242fb235c97d935ba56f61745fa09065c2e8c', '2026-09-07 09:00:00', NULL, 0, '2026-09-07 15:29:26'),
(2, 6, 9002, 'B', 'fixture', '632d500e73202efd675dc8a81ca85d06f64c9ee882332f587e1361779caee8ce', NULL, 'AMD 5700X3D มือสอง ใช้งานได้ดี ประกัน 3 เดือน', '799e58461b8c2a216850352aec734d5d2d5d9b0413f3c42686bfa45f85386cc4', '2026-09-07 09:02:00', NULL, 0, '2026-09-07 15:29:26'),
(3, 7, 9002, 'B', 'fixture', '5fa3b760d5e8ab5cd748748d35460f3431b227fdb09e5f3f1d5d2b118f5dd9f5', NULL, 'R7 5700X3D สวย มีกล่องและใบเสร็จ', '1f254adabd479503944dad0dbc11faa3b6b1d3b0167a717cd188ade787f3a35d', '2026-09-07 09:03:00', NULL, 0, '2026-09-07 15:29:26'),
(4, 8, 9002, 'D', 'fixture', '6b0393c061da4a89357688232599b1deac252b4f4960140307933ac8c612d4c1', NULL, 'รับซื้อ 5700x3d งบ 6000', '4acae8663207e139c355cd3b2943aa1d9bd0c9a77cdcc2a32b5ccdf1b1e8dab2', '2026-09-07 09:05:00', NULL, 0, '2026-09-07 15:29:26'),
(5, 9, 9002, 'B', 'fixture', '7291247a2a85c4a9efa034ccb6095565efd3ed00af3dd6e707f95168b2e7b276', NULL, 'Ryzen5700X3D + B550 bundle พร้อมกล่อง', 'd68c98be53e877caa6abb2dbc59fefbe964e3718490a0909d2c8fcc8209f5c0d', '2026-09-07 09:10:00', NULL, 0, '2026-09-07 15:29:26'),
(90, 199, 9002, 'B', 'fixture', 'bd698d8a03a4418c99e491af85217897adbd3ce032a401f1bbe7d0f2384afbcb', NULL, 'ขาย RTX3070 8GB ใช้งานได้ดี ประกัน 2 เดือน', '2fd994786a7e961dbf5cf5269d9128c7d2096856b4f28a24fe5f6d116df98c73', '2026-09-07 09:15:00', NULL, 0, '2026-09-07 17:10:31'),
(91, 200, 9002, 'D', 'fixture', '7478e711c8fe1aea24d6ec168bf4ff576e0647cdb790d34337b04a8b54e2456e', NULL, 'จอง RTX 3070 มัดจำก่อน 500', 'c7faa01f7c273509a310cde92e0aa71ef87cbdf4cdc94d8e730b7dbd3a492831', '2026-09-07 09:30:00', NULL, 0, '2026-09-07 17:10:31'),
(92, 206, 9002, 'C', 'fixture', '0d88690790f069330a5283c902a54ad2c34864c45ff45ae7b73e2616bc03a442', NULL, 'คอมทั้งชุด i5 12400F RTX 3070', '829f98f737066c17ecbe6b4e527fdf3fdd140651fdd3bb776f1de9d6a9f856b1', '2026-09-07 09:20:00', NULL, 0, '2026-09-07 17:10:31'),
(93, 207, 9002, 'C', 'fixture', 'a9d40fe22d2b0e62669007a877dd20ea0da8173906cb83825c62b3957947f7e2', NULL, 'Core i5 12400F เปิดไม่ติด ขายเป็นอะไหล่', '288f64484dfa7510feb902fd9fb69abe4366ba54eba44edb79d26cfd23260d53', '2026-09-07 09:25:00', NULL, 0, '2026-09-07 17:10:31'),
(94, 208, 9002, 'B', 'fixture', 'b66de7a552c8dc25e6cca963cf5791e4ea5430fdcdda17745c7318fcb16089cb', NULL, 'AMD Radeon RX 6600 XT มือสอง', '13a99e0d334d08dea7d8a30f3cfacd0e2f8b2899c774c6986b04152bfd8d3d4b', '2026-09-07 10:00:00', NULL, 0, '2026-09-07 17:10:31'),
(95, 209, 9002, 'C', 'fixture', '1590e9a673ea88bc753d58ebe508a3e1f53b0dd5885dec51dd18d5560e8f5386', NULL, 'RX6600XT พร้อมกล่อง ราคาแบ่งได้', '822587b1b9f129a7435aa16dfd47362000213d173b1a9d49cb5293670420db25', '2026-09-07 10:01:00', NULL, 0, '2026-09-07 17:10:31'),
(96, 210, 9002, 'D', 'fixture', 'ddbeb89df64650a12670e254a0020ae17aa6d3c0c7b313898bc2883f61e30ae4', NULL, 'รับซื้อ RX 6600 XT งบ 4200', 'a8c35b3cc9698f9893d87c43585a69a3f3ce985657a70f5b5d82cf2bdda0cfdd', '2026-09-07 10:02:00', NULL, 0, '2026-09-07 17:10:31'),
(97, 211, 9002, 'B', 'fixture', '479eca12452cb0d1e8eeec4bef5cf03643f0de2b8526371bbf42e2a99e51eebb', NULL, 'AMD Radeon RX 6700 XT มือสอง', 'c785591f3a3059825b029b6a87ee2ed460c0a632d9e3d9fe6162b21ecfa3cd1e', '2026-09-07 10:05:00', NULL, 0, '2026-09-07 17:10:31'),
(98, 212, 9002, 'C', 'fixture', 'e1d6f7f14d74eea48c87d8d1e2c29fc0c7e72ac21f4d55b7a816e7b28957e7bf', NULL, 'RX 6700 XT + PSU 650W พร้อมใช้งาน', '6409637221eae02fe6eb9d95124ae50e0fa93f50b82310c3fc71da6b4ac95f8f', '2026-09-07 10:06:00', NULL, 0, '2026-09-07 17:10:31'),
(99, 213, 9002, 'D', 'fixture', '67e614af2c0f41222a168ebb87ca5cfa6ac1a3885cda4c210c2a29cd6c2ace98', NULL, 'RX6700XT เสีย เปิดไม่ติด ขายซ่อม', '3c257af3b054a0999dca9dbebea767ee414e3da0ae5cf97ffcdd80cb7153f58f', '2026-09-07 10:07:00', NULL, 0, '2026-09-07 17:10:31'),
(100, 214, 9002, 'B', 'fixture', '09b11470d99d1cc0f1727b821510f5e489aeb79cc7f3a7934e01288bd5ece2de', NULL, 'AMD Radeon RX 7800 XT มือสอง', '077bf6b83c41439c8b62fc3a43222eae2c0fbdec6f0dd9248d2a7edeaf42bf75', '2026-09-07 10:10:00', NULL, 0, '2026-09-07 17:10:31'),
(101, 215, 9002, 'C', 'fixture', '7af88bfe144390b7c54c2f3bef006c954f9f23399192cfab2afd3c00aef448ba', NULL, 'Radeon 7800 XT พร้อมของแถม', '5e94eb176b11f738035111d7d8634ce84f4c8339de1872d58598682963593a45', '2026-09-07 10:11:00', NULL, 0, '2026-09-07 17:10:31'),
(102, 216, 9002, 'D', 'fixture', '174fdad2cfe79000c13f97bd3ba794b53a462c16cba71e814a33c6c16414d87a', NULL, 'จอง RX7800XT มัดจำ 1000 ก่อนนัดรับ', 'cd75a1687bcd49bb3e20c41642ea52a4c7707386b6b150b58e461981b93737e8', '2026-09-07 10:12:00', NULL, 0, '2026-09-07 17:10:31');

-- --------------------------------------------------------

--
-- Table structure for table `observation_review_decisions`
--

CREATE TABLE `observation_review_decisions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `price_observation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `raw_observation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `extraction_run_id` bigint(20) UNSIGNED DEFAULT NULL,
  `lane` enum('green','amber','red') NOT NULL,
  `decision` enum('review_required','approved','edited','rejected','quarantined') NOT NULL,
  `reason_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`reason_codes`)),
  `ai_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_value`)),
  `rule_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rule_result`)),
  `human_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`human_value`)),
  `reviewer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `observation_review_decisions`
--

INSERT INTO `observation_review_decisions` (`id`, `price_observation_id`, `raw_observation_id`, `extraction_run_id`, `lane`, `decision`, `reason_codes`, `ai_value`, `rule_result`, `human_value`, `reviewer_id`, `notes`, `created_at`) VALUES
(1, 5, 5, 1, 'green', 'review_required', '[\"passes_rules\"]', '{\"askingPrice\":7500,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"good\",\"warrantyMonths\":5,\"hasBox\":true,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"ขาย AMD Ryzen 7 5700X3D ประกัน 5 เดือน มีกล่อง ใช้งานปกติ\",\"price_text\":\"฿7,500\"}}', '{\"lane\":\"green\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"passes_rules\"]}', NULL, NULL, NULL, '2026-09-07 15:29:26'),
(2, 6, 6, 2, 'amber', 'review_required', '[\"low_confidence\"]', '{\"askingPrice\":7300,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"good\",\"warrantyMonths\":3,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"AMD 5700X3D มือสอง ใช้งานได้ดี ประกัน 3 เดือน\",\"price_text\":\"7300\"}}', '{\"lane\":\"amber\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"low_confidence\"]}', NULL, NULL, NULL, '2026-09-07 15:29:26'),
(3, 7, 7, 3, 'green', 'review_required', '[\"passes_rules\"]', '{\"askingPrice\":7900,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"like_new\",\"warrantyMonths\":null,\"hasBox\":true,\"hasReceipt\":true,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"R7 5700X3D สวย มีกล่องและใบเสร็จ\",\"price_text\":\"7900\"}}', '{\"lane\":\"green\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"passes_rules\"]}', NULL, NULL, NULL, '2026-09-07 15:29:26'),
(4, NULL, 8, 4, 'red', 'rejected', '[\"wanted_post\"]', '{\"askingPrice\":6000,\"currency\":\"THB\",\"listingType\":\"wanted\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":true,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"รับซื้อ 5700x3d งบ 6000\",\"price_text\":\"6000\"}}', '{\"lane\":\"red\",\"reviewState\":\"rejected\",\"reasonCodes\":[\"wanted_post\"]}', NULL, NULL, NULL, '2026-09-07 15:29:26'),
(5, 8, 9, 5, 'amber', 'review_required', '[\"bundle\"]', '{\"askingPrice\":10500,\"currency\":\"THB\",\"listingType\":\"bundle\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":true,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"Ryzen5700X3D + B550 bundle พร้อมกล่อง\",\"price_text\":\"10500 บาท\"}}', '{\"lane\":\"amber\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"bundle\"]}', NULL, NULL, NULL, '2026-09-07 15:29:26'),
(98, 281, 199, 90, 'amber', 'review_required', '[\"low_confidence\"]', '{\"askingPrice\":7800,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"good\",\"warrantyMonths\":2,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"ขาย RTX3070 8GB ใช้งานได้ดี ประกัน 2 เดือน\",\"price_text\":\"7,800\"}}', '{\"lane\":\"amber\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"low_confidence\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(99, NULL, 200, 91, 'red', 'rejected', '[\"deposit_only\"]', '{\"askingPrice\":500,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":true,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"จอง RTX 3070 มัดจำก่อน 500\",\"price_text\":\"500\"}}', '{\"lane\":\"red\",\"reviewState\":\"rejected\",\"reasonCodes\":[\"deposit_only\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(100, NULL, 206, 92, 'red', 'rejected', '[\"whole_pc\"]', '{\"askingPrice\":18500,\"currency\":\"THB\",\"listingType\":\"whole_pc\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":true,\"confidence\":0.8,\"raw\":{\"title\":\"คอมทั้งชุด i5 12400F RTX 3070\",\"price_text\":\"18500\"}}', '{\"lane\":\"red\",\"reviewState\":\"rejected\",\"reasonCodes\":[\"whole_pc\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(101, NULL, 207, 93, 'red', 'rejected', '[\"defective\"]', '{\"askingPrice\":900,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"poor\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":true,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"Core i5 12400F เปิดไม่ติด ขายเป็นอะไหล่\",\"price_text\":\"900\"}}', '{\"lane\":\"red\",\"reviewState\":\"rejected\",\"reasonCodes\":[\"defective\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(102, 282, 208, 94, 'green', 'review_required', '[\"passes_rules\"]', '{\"askingPrice\":5400,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"AMD Radeon RX 6600 XT มือสอง\",\"price_text\":\"5400 บาท\"}}', '{\"lane\":\"green\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"passes_rules\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(103, 283, 209, 95, 'amber', 'review_required', '[\"bundle\"]', '{\"askingPrice\":6100,\"currency\":\"THB\",\"listingType\":\"bundle\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":true,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"RX6600XT พร้อมกล่อง ราคาแบ่งได้\",\"price_text\":\"6100\"}}', '{\"lane\":\"amber\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"bundle\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(104, NULL, 210, 96, 'red', 'rejected', '[\"wanted_post\"]', '{\"askingPrice\":4200,\"currency\":\"THB\",\"listingType\":\"wanted\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":true,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"รับซื้อ RX 6600 XT งบ 4200\",\"price_text\":\"4200\"}}', '{\"lane\":\"red\",\"reviewState\":\"rejected\",\"reasonCodes\":[\"wanted_post\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(105, 284, 211, 97, 'green', 'review_required', '[\"passes_rules\"]', '{\"askingPrice\":9300,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"AMD Radeon RX 6700 XT มือสอง\",\"price_text\":\"9300\"}}', '{\"lane\":\"green\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"passes_rules\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(106, 285, 212, 98, 'amber', 'review_required', '[\"bundle\"]', '{\"askingPrice\":11500,\"currency\":\"THB\",\"listingType\":\"bundle\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"RX 6700 XT + PSU 650W พร้อมใช้งาน\",\"price_text\":\"11500\"}}', '{\"lane\":\"amber\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"bundle\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(107, NULL, 213, 99, 'red', 'rejected', '[\"defective\"]', '{\"askingPrice\":2500,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"poor\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":true,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"RX6700XT เสีย เปิดไม่ติด ขายซ่อม\",\"price_text\":\"2500\"}}', '{\"lane\":\"red\",\"reviewState\":\"rejected\",\"reasonCodes\":[\"defective\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(108, 286, 214, 100, 'green', 'review_required', '[\"passes_rules\"]', '{\"askingPrice\":18200,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"AMD Radeon RX 7800 XT มือสอง\",\"price_text\":\"18200\"}}', '{\"lane\":\"green\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"passes_rules\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(109, 287, 215, 101, 'amber', 'review_required', '[\"bundle\"]', '{\"askingPrice\":20000,\"currency\":\"THB\",\"listingType\":\"bundle\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":false,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.95,\"raw\":{\"title\":\"Radeon 7800 XT พร้อมของแถม\",\"price_text\":\"20000\"}}', '{\"lane\":\"amber\",\"reviewState\":\"review_required\",\"reasonCodes\":[\"bundle\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31'),
(110, NULL, 216, 102, 'red', 'rejected', '[\"deposit_only\"]', '{\"askingPrice\":1000,\"currency\":\"THB\",\"listingType\":\"single_item\",\"conditionLevel\":\"unknown\",\"warrantyMonths\":null,\"hasBox\":null,\"hasReceipt\":null,\"isWantedPost\":false,\"isDeposit\":true,\"isDefective\":false,\"isWholePc\":false,\"confidence\":0.8,\"raw\":{\"title\":\"จอง RX7800XT มัดจำ 1000 ก่อนนัดรับ\",\"price_text\":\"1000\"}}', '{\"lane\":\"red\",\"reviewState\":\"rejected\",\"reasonCodes\":[\"deposit_only\"]}', NULL, NULL, NULL, '2026-09-07 17:10:31');

-- --------------------------------------------------------

--
-- Table structure for table `price_histories`
--

CREATE TABLE `price_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `q1` decimal(12,2) NOT NULL,
  `median` decimal(12,2) NOT NULL,
  `q3` decimal(12,2) NOT NULL,
  `sample_size` int(10) UNSIGNED NOT NULL,
  `confidence_score` decimal(5,2) NOT NULL,
  `snapshot_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `price_histories`
--

INSERT INTO `price_histories` (`id`, `product_id`, `product_variant_id`, `q1`, `median`, `q3`, `sample_size`, `confidence_score`, `snapshot_date`, `created_at`) VALUES
(1, 1, NULL, 7600.00, 8000.00, 8400.00, 5, 55.00, '2026-05-01', '2026-07-27 13:52:23'),
(2, 1, NULL, 7400.00, 7800.00, 8100.00, 6, 62.00, '2026-06-01', '2026-07-27 13:52:23'),
(3, 1, NULL, 7200.00, 7500.00, 7800.00, 6, 68.00, '2026-07-01', '2026-07-27 13:52:23');

-- --------------------------------------------------------

--
-- Table structure for table `price_indices`
--

CREATE TABLE `price_indices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `price_type` enum('asking','sold','trade_in','new') NOT NULL DEFAULT 'asking',
  `price_low` decimal(12,2) NOT NULL,
  `q1` decimal(12,2) NOT NULL,
  `median` decimal(12,2) NOT NULL,
  `q3` decimal(12,2) NOT NULL,
  `price_high` decimal(12,2) NOT NULL,
  `fast_sale_min` decimal(12,2) DEFAULT NULL,
  `fast_sale_max` decimal(12,2) DEFAULT NULL,
  `market_min` decimal(12,2) DEFAULT NULL,
  `market_max` decimal(12,2) DEFAULT NULL,
  `premium_min` decimal(12,2) DEFAULT NULL,
  `premium_max` decimal(12,2) DEFAULT NULL,
  `sample_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `valid_sample_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `fresh_sample_ratio` decimal(5,2) NOT NULL DEFAULT 0.00,
  `confidence_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `confidence_label` enum('high','medium','medium_low','low','insufficient') NOT NULL DEFAULT 'insufficient',
  `formula_version` varchar(80) NOT NULL DEFAULT 'legacy-unversioned',
  `cohort_version` varchar(80) NOT NULL DEFAULT 'legacy-unversioned',
  `quartile_method_version` varchar(80) NOT NULL DEFAULT 'legacy-unversioned',
  `confidence_method_version` varchar(80) NOT NULL DEFAULT 'legacy-unversioned',
  `calculation_hash` char(64) DEFAULT NULL,
  `calculation_manifest` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_manifest`)),
  `provenance_status` enum('legacy_unavailable','recorded') NOT NULL DEFAULT 'legacy_unavailable',
  `last_calculated_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `price_indices`
--

INSERT INTO `price_indices` (`id`, `product_id`, `product_variant_id`, `price_type`, `price_low`, `q1`, `median`, `q3`, `price_high`, `fast_sale_min`, `fast_sale_max`, `market_min`, `market_max`, `premium_min`, `premium_max`, `sample_size`, `valid_sample_size`, `fresh_sample_ratio`, `confidence_score`, `confidence_label`, `formula_version`, `cohort_version`, `quartile_method_version`, `confidence_method_version`, `calculation_hash`, `calculation_manifest`, `provenance_status`, `last_calculated_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'asking', 6900.00, 7200.00, 7500.00, 7800.00, 8300.00, 6800.00, 7200.00, 7200.00, 7800.00, 7800.00, 8400.00, 8, 6, 87.50, 68.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 13:52:23', '2026-07-27 13:52:23', '2026-07-27 13:52:23'),
(2, 2, NULL, 'asking', 6500.00, 7200.00, 7800.00, 8500.00, 9500.00, 6600.00, 7200.00, 7200.00, 8500.00, 8500.00, 9600.00, 18, 15, 80.00, 82.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 13:52:23', '2026-07-27 13:52:23', '2026-07-27 13:52:23'),
(3, 3, NULL, 'asking', 2600.00, 2900.00, 3200.00, 3500.00, 3900.00, 2700.00, 3000.00, 2900.00, 3500.00, 3500.00, 4000.00, 12, 10, 90.00, 78.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 13:52:23', '2026-07-27 13:52:23', '2026-07-27 13:52:23'),
(4, 4, NULL, 'asking', 1800.00, 2100.00, 2300.00, 2500.00, 2800.00, NULL, NULL, 2100.00, 2500.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(5, 5, NULL, 'asking', 2800.00, 3200.00, 3500.00, 3900.00, 4300.00, NULL, NULL, 3200.00, 3900.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(6, 6, NULL, 'asking', 2100.00, 2400.00, 2700.00, 3000.00, 3400.00, NULL, NULL, 2400.00, 3000.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(7, 7, NULL, 'asking', 3200.00, 3600.00, 4000.00, 4400.00, 4900.00, NULL, NULL, 3600.00, 4400.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(8, 8, NULL, 'asking', 1600.00, 1800.00, 2000.00, 2200.00, 2500.00, NULL, NULL, 1800.00, 2200.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(9, 9, NULL, 'asking', 2600.00, 2900.00, 3200.00, 3500.00, 3900.00, NULL, NULL, 2900.00, 3500.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(10, 10, NULL, 'asking', 3300.00, 3700.00, 4100.00, 4500.00, 5000.00, NULL, NULL, 3700.00, 4500.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(11, 11, NULL, 'asking', 3900.00, 4300.00, 4800.00, 5300.00, 5900.00, NULL, NULL, 4300.00, 5300.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(12, 12, NULL, 'asking', 800.00, 950.00, 1100.00, 1250.00, 1450.00, NULL, NULL, 950.00, 1250.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(13, 13, NULL, 'asking', 1500.00, 1750.00, 2000.00, 2300.00, 2600.00, NULL, NULL, 1750.00, 2300.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(14, 14, NULL, 'asking', 1200.00, 1400.00, 1600.00, 1800.00, 2100.00, NULL, NULL, 1400.00, 1800.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(15, 15, NULL, 'asking', 1000.00, 1200.00, 1400.00, 1600.00, 1850.00, NULL, NULL, 1200.00, 1600.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(16, 16, NULL, 'asking', 2200.00, 2500.00, 2800.00, 3100.00, 3500.00, NULL, NULL, 2500.00, 3100.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(17, 17, NULL, 'asking', 1500.00, 1750.00, 2000.00, 2250.00, 2500.00, NULL, NULL, 1750.00, 2250.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(18, 18, NULL, 'asking', 1400.00, 1600.00, 1800.00, 2100.00, 2400.00, NULL, NULL, 1600.00, 2100.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(19, 19, NULL, 'asking', 650.00, 800.00, 950.00, 1100.00, 1300.00, NULL, NULL, 800.00, 1100.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 15:55:42', '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(35, 20, NULL, 'asking', 2300.00, 2600.00, 2900.00, 3200.00, 3500.00, NULL, NULL, 2600.00, 3200.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(36, 21, NULL, 'asking', 4200.00, 4600.00, 5000.00, 5500.00, 6000.00, NULL, NULL, 4600.00, 5500.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(37, 22, NULL, 'asking', 5200.00, 5700.00, 6200.00, 6800.00, 7400.00, NULL, NULL, 5700.00, 6800.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(38, 23, NULL, 'asking', 10500.00, 11200.00, 11900.00, 12600.00, 13400.00, NULL, NULL, 11200.00, 12600.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(39, 24, NULL, 'asking', 1900.00, 2200.00, 2500.00, 2800.00, 3100.00, NULL, NULL, 2200.00, 2800.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(40, 25, NULL, 'asking', 4700.00, 5200.00, 5700.00, 6200.00, 6800.00, NULL, NULL, 5200.00, 6200.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(41, 26, NULL, 'asking', 7300.00, 7900.00, 8500.00, 9200.00, 9900.00, NULL, NULL, 7900.00, 9200.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(42, 27, NULL, 'asking', 2100.00, 2400.00, 2700.00, 3000.00, 3400.00, NULL, NULL, 2400.00, 3000.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(43, 28, NULL, 'asking', 5200.00, 5700.00, 6200.00, 6800.00, 7500.00, NULL, NULL, 5700.00, 6800.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(44, 29, NULL, 'asking', 6500.00, 7100.00, 7800.00, 8500.00, 9300.00, NULL, NULL, 7100.00, 8500.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(45, 30, NULL, 'asking', 5900.00, 6500.00, 7100.00, 7800.00, 8500.00, NULL, NULL, 6500.00, 7800.00, NULL, NULL, 16, 13, 88.00, 80.00, 'high', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-07-27 16:59:50', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(46, 69, NULL, 'asking', 2800.00, 3300.00, 3800.00, 4300.00, 5000.00, NULL, NULL, 3300.00, 4300.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(47, 70, NULL, 'asking', 3600.00, 4200.00, 4800.00, 5400.00, 6200.00, NULL, NULL, 4200.00, 5400.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(48, 71, NULL, 'asking', 5800.00, 6600.00, 7300.00, 8200.00, 9200.00, NULL, NULL, 6600.00, 8200.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(49, 72, NULL, 'asking', 10500.00, 11800.00, 13000.00, 14500.00, 16500.00, NULL, NULL, 11800.00, 14500.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(50, 73, NULL, 'asking', 9500.00, 10800.00, 11900.00, 13200.00, 15000.00, NULL, NULL, 10800.00, 13200.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(51, 74, NULL, 'asking', 15500.00, 17000.00, 18500.00, 20500.00, 23000.00, NULL, NULL, 17000.00, 20500.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(52, 75, NULL, 'asking', 22000.00, 24000.00, 26000.00, 28500.00, 31500.00, NULL, NULL, 24000.00, 28500.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(53, 76, NULL, 'asking', 4200.00, 4800.00, 5400.00, 6100.00, 7000.00, NULL, NULL, 4800.00, 6100.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(54, 77, NULL, 'asking', 7500.00, 8400.00, 9300.00, 10400.00, 11800.00, NULL, NULL, 8400.00, 10400.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(55, 78, NULL, 'asking', 15000.00, 16500.00, 18200.00, 20000.00, 22500.00, NULL, NULL, 16500.00, 20000.00, NULL, NULL, 10, 8, 80.00, 72.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:21:30', '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(61, 96, NULL, 'asking', 6200.00, 6800.00, 7400.00, 8200.00, 9000.00, NULL, NULL, 6800.00, 8200.00, NULL, NULL, 12, 10, 85.00, 76.00, 'medium', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', 'legacy-unversioned', NULL, NULL, 'legacy_unavailable', '2026-09-07 15:28:21', '2026-09-07 15:28:21', '2026-09-07 15:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `price_observations`
--

CREATE TABLE `price_observations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `raw_observation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `price_type` enum('asking','sold','trade_in','new') NOT NULL DEFAULT 'asking',
  `asking_price` decimal(12,2) DEFAULT NULL,
  `price_value` decimal(12,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'THB',
  `condition_level` enum('new','like_new','good','fair','poor','unknown') NOT NULL DEFAULT 'unknown',
  `warranty_months` smallint(5) UNSIGNED DEFAULT NULL,
  `has_box` tinyint(1) DEFAULT NULL,
  `has_receipt` tinyint(1) DEFAULT NULL,
  `has_benchmark` tinyint(1) DEFAULT NULL,
  `listing_type` enum('single_item','bundle','whole_pc','wanted','unknown') NOT NULL DEFAULT 'single_item',
  `is_deposit` tinyint(1) NOT NULL DEFAULT 0,
  `is_defective` tinyint(1) NOT NULL DEFAULT 0,
  `is_duplicate` tinyint(1) NOT NULL DEFAULT 0,
  `evidence_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `evidence_weight` decimal(6,4) NOT NULL DEFAULT 1.0000,
  `freshness_weight` decimal(6,4) NOT NULL DEFAULT 1.0000,
  `source_weight` decimal(6,4) NOT NULL DEFAULT 1.0000,
  `classification_confidence` decimal(5,2) NOT NULL DEFAULT 0.00,
  `final_weight` decimal(8,4) NOT NULL DEFAULT 1.0000,
  `quality_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`quality_flags`)),
  `verified_status` enum('pending','approved','rejected','excluded') NOT NULL DEFAULT 'pending',
  `observed_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `price_observations`
--

INSERT INTO `price_observations` (`id`, `raw_observation_id`, `product_id`, `product_variant_id`, `price_type`, `asking_price`, `price_value`, `currency`, `condition_level`, `warranty_months`, `has_box`, `has_receipt`, `has_benchmark`, `listing_type`, `is_deposit`, `is_defective`, `is_duplicate`, `evidence_level`, `evidence_weight`, `freshness_weight`, `source_weight`, `classification_confidence`, `final_weight`, `quality_flags`, `verified_status`, `observed_at`, `created_at`, `updated_at`) VALUES
(5, 5, 1, NULL, 'asking', 7500.00, 7500.00, 'THB', 'good', 5, 1, NULL, NULL, 'single_item', 0, 0, 0, 4, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 09:00:00', '2026-09-07 15:29:26', '2026-09-09 14:11:49'),
(6, 6, 1, NULL, 'asking', 7300.00, 7300.00, 'THB', 'good', 3, NULL, NULL, NULL, 'single_item', 0, 0, 0, 4, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 09:02:00', '2026-09-07 15:29:26', '2026-09-09 14:11:49'),
(7, 7, 1, NULL, 'asking', 7900.00, 7900.00, 'THB', 'like_new', NULL, 1, 1, NULL, 'single_item', 0, 0, 0, 4, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 09:03:00', '2026-09-07 15:29:26', '2026-09-09 14:11:49'),
(8, 9, 1, NULL, 'asking', 10500.00, 10500.00, 'THB', 'unknown', NULL, 1, NULL, NULL, 'bundle', 0, 0, 0, 4, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 09:10:00', '2026-09-07 15:29:26', '2026-09-09 14:11:49'),
(281, 199, 2, NULL, 'asking', 7800.00, 7800.00, 'THB', 'good', 2, NULL, NULL, NULL, 'single_item', 0, 0, 0, 4, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 09:15:00', '2026-09-07 17:10:31', '2026-09-09 14:11:49'),
(282, 208, 76, NULL, 'asking', 5400.00, 5400.00, 'THB', 'unknown', NULL, NULL, NULL, NULL, 'single_item', 0, 0, 0, 4, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 10:00:00', '2026-09-07 17:10:31', '2026-09-09 14:11:49'),
(283, 209, 76, NULL, 'asking', 6100.00, 6100.00, 'THB', 'unknown', NULL, 1, NULL, NULL, 'bundle', 0, 0, 0, 3, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 10:01:00', '2026-09-07 17:10:31', '2026-09-09 14:11:49'),
(284, 211, 77, NULL, 'asking', 9300.00, 9300.00, 'THB', 'unknown', NULL, NULL, NULL, NULL, 'single_item', 0, 0, 0, 4, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 10:05:00', '2026-09-07 17:10:31', '2026-09-09 14:11:49'),
(285, 212, 77, NULL, 'asking', 11500.00, 11500.00, 'THB', 'unknown', NULL, NULL, NULL, NULL, 'bundle', 0, 0, 0, 3, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 10:06:00', '2026-09-07 17:10:31', '2026-09-09 14:11:49'),
(286, 214, 78, NULL, 'asking', 18200.00, 18200.00, 'THB', 'unknown', NULL, NULL, NULL, NULL, 'single_item', 0, 0, 0, 4, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 10:10:00', '2026-09-07 17:10:31', '2026-09-09 14:11:49'),
(287, 215, 78, NULL, 'asking', 20000.00, 20000.00, 'THB', 'unknown', NULL, NULL, NULL, NULL, 'bundle', 0, 0, 0, 3, 1.0000, 1.0000, 1.0000, 95.00, 1.0000, NULL, 'pending', '2026-09-07 10:11:00', '2026-09-07 17:10:31', '2026-09-09 14:11:49');

-- --------------------------------------------------------

--
-- Table structure for table `price_snapshot_observations`
--

CREATE TABLE `price_snapshot_observations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `snapshot_id` bigint(20) UNSIGNED NOT NULL,
  `observation_id` bigint(20) UNSIGNED NOT NULL,
  `inclusion_status` enum('included','excluded') NOT NULL,
  `exclusion_reason` varchar(80) DEFAULT NULL,
  `calculation_price` decimal(12,2) DEFAULT NULL,
  `snapshot_verified_status` varchar(30) NOT NULL,
  `snapshot_price_type` varchar(30) NOT NULL,
  `snapshot_listing_type` varchar(30) NOT NULL,
  `snapshot_source_id` bigint(20) UNSIGNED DEFAULT NULL,
  `snapshot_observed_at` datetime NOT NULL,
  `weight` decimal(8,4) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `brand_id` bigint(20) UNSIGNED NOT NULL,
  `model_name` varchar(160) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `generation` varchar(100) DEFAULT NULL,
  `release_year` smallint(5) UNSIGNED DEFAULT NULL,
  `spec_summary` text DEFAULT NULL,
  `tdp_watt` smallint(5) UNSIGNED DEFAULT NULL,
  `recommended_psu_watt` smallint(5) UNSIGNED DEFAULT NULL,
  `socket` varchar(40) DEFAULT NULL,
  `chipset` varchar(40) DEFAULT NULL,
  `memory_type` varchar(20) DEFAULT NULL,
  `form_factor` varchar(30) DEFAULT NULL,
  `capacity_gb` int(10) UNSIGNED DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `brand_id`, `model_name`, `slug`, `full_name`, `generation`, `release_year`, `spec_summary`, `tdp_watt`, `recommended_psu_watt`, `socket`, `chipset`, `memory_type`, `form_factor`, `capacity_gb`, `image_path`, `is_popular`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Ryzen 7 5700X3D', 'amd-ryzen-7-5700x3d', 'AMD Ryzen 7 5700X3D', 'Zen 3', 2024, '8 cores / 16 threads, AM4, 96MB L3 cache', 105, NULL, 'AM4', NULL, 'DDR4', NULL, NULL, 'assets/images/admin-2.jpg', 1, 1, '2026-07-27 13:52:23', '2026-07-27 15:55:41'),
(2, 2, 2, 'GeForce RTX 3070', 'nvidia-geforce-rtx-3070', 'NVIDIA GeForce RTX 3070', 'Ampere', 2020, '8GB GDDR6, suitable for 1440p gaming', 220, 650, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-07-27 13:52:23', '2026-07-27 15:55:41'),
(3, 1, 3, 'Core i5-12400F', 'intel-core-i5-12400f', 'Intel Core i5-12400F', 'Alder Lake', 2022, '6 cores / 12 threads, LGA1700', 65, NULL, 'LGA1700', NULL, 'DDR4/DDR5', NULL, NULL, 'assets/images/admin-4.jpg', 1, 1, '2026-07-27 13:52:23', '2026-07-27 15:55:41'),
(4, 5, 4, 'TUF Gaming B450-PLUS II', 'asus-tuf-b450-plus-ii', 'ASUS TUF Gaming B450-PLUS II', NULL, NULL, 'AM4, 4 DIMM, PCIe 3.0, ATX', NULL, NULL, 'AM4', 'B450', 'DDR4', 'ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(5, 5, 5, 'MAG B550 Tomahawk', 'msi-mag-b550-tomahawk', 'MSI MAG B550 Tomahawk', NULL, NULL, 'AM4, 4 DIMM, PCIe 4.0, 2.5G LAN', NULL, NULL, 'AM4', 'B550', 'DDR4', 'ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(6, 5, 6, 'X470 AORUS Ultra Gaming', 'gigabyte-x470-aorus-ultra', 'Gigabyte X470 AORUS Ultra Gaming', NULL, NULL, 'AM4, 4 DIMM, Multi-GPU, ATX', NULL, NULL, 'AM4', 'X470', 'DDR4', 'ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(7, 5, 7, 'X570 Steel Legend', 'asrock-x570-steel-legend', 'ASRock X570 Steel Legend', NULL, NULL, 'AM4, PCIe 4.0, 2x M.2, ATX', NULL, NULL, 'AM4', 'X570', 'DDR4', 'ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(8, 5, 4, 'Prime H610M-K D4', 'asus-prime-h610m-k-d4', 'ASUS Prime H610M-K D4', NULL, NULL, 'LGA1700, 2 DIMM, entry-level', NULL, NULL, 'LGA1700', 'H610', 'DDR4', 'Micro-ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(9, 5, 5, 'PRO B660M-A DDR4', 'msi-pro-b660m-a-ddr4', 'MSI PRO B660M-A DDR4', NULL, NULL, 'LGA1700, 4 DIMM, PCIe 4.0', NULL, NULL, 'LGA1700', 'B660', 'DDR4', 'Micro-ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(10, 5, 6, 'B760M DS3H AX DDR4', 'gigabyte-b760m-ds3h-ax-ddr4', 'Gigabyte B760M DS3H AX DDR4', NULL, NULL, 'LGA1700, Wi-Fi 6E, 4 DIMM', NULL, NULL, 'LGA1700', 'B760', 'DDR4', 'Micro-ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(11, 5, 7, 'Z690 Steel Legend', 'asrock-z690-steel-legend', 'ASRock Z690 Steel Legend', NULL, NULL, 'LGA1700, PCIe 5.0, ATX', NULL, NULL, 'LGA1700', 'Z690', 'DDR4', 'ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(12, 3, 9, 'FURY Beast 16GB DDR4-3200', 'kingston-fury-beast-16gb-ddr4', 'Kingston FURY Beast 16GB DDR4-3200', NULL, NULL, '2x8GB DDR4-3200 CL16', NULL, NULL, NULL, NULL, 'DDR4', NULL, NULL, 'assets/images/home-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(13, 3, 8, 'Vengeance LPX 32GB DDR4-3200', 'corsair-vengeance-lpx-32gb', 'Corsair Vengeance LPX 32GB DDR4-3200', NULL, NULL, '2x16GB DDR4-3200 CL16', NULL, NULL, NULL, NULL, 'DDR4', NULL, NULL, 'assets/images/home-5.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(14, 4, 10, '970 EVO Plus 1TB', 'samsung-970-evo-plus-1tb', 'Samsung 970 EVO Plus 1TB', NULL, NULL, 'NVMe PCIe 3.0 x4', NULL, NULL, NULL, NULL, NULL, 'M.2 2280', NULL, 'assets/images/home-3.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(15, 4, 11, 'WD Blue SN570 1TB', 'wd-blue-sn570-1tb', 'WD Blue SN570 1TB', NULL, NULL, 'NVMe PCIe 3.0 x4', NULL, NULL, NULL, NULL, NULL, 'M.2 2280', NULL, 'assets/images/home-3.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(16, 6, 8, 'RM750x 750W Gold', 'corsair-rm750x', 'Corsair RM750x 750W 80+ Gold', NULL, NULL, 'Fully modular, 80+ Gold', NULL, NULL, NULL, NULL, NULL, 'ATX', NULL, 'assets/images/home-3.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(17, 6, 12, 'MWE Gold 650 V2', 'cooler-master-mwe-gold-650', 'Cooler Master MWE Gold 650 V2', NULL, NULL, '650W, 80+ Gold', NULL, NULL, NULL, NULL, NULL, 'ATX', NULL, 'assets/images/home-3.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(18, 7, 8, '4000D Airflow', 'corsair-4000d-airflow', 'Corsair 4000D Airflow', NULL, NULL, 'ATX Mid Tower, airflow front panel', NULL, NULL, NULL, NULL, NULL, 'ATX Mid Tower', NULL, 'assets/images/local-2.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(19, 8, 12, 'Hyper 212 Halo', 'cooler-master-hyper-212-halo', 'Cooler Master Hyper 212 Halo', NULL, NULL, 'Tower air cooler, AM4/LGA1700', NULL, NULL, 'AM4/LGA1700', NULL, NULL, 'Tower', NULL, 'assets/images/local-1.jpg', 0, 1, '2026-07-27 15:55:42', '2026-07-27 15:55:42'),
(20, 1, 1, 'Ryzen 5 5600', 'amd-ryzen-5-5600', 'AMD Ryzen 5 5600', 'Zen 3', 2022, '6 cores / 12 threads, unlocked', 65, 550, 'AM4', NULL, 'DDR4', NULL, NULL, 'assets/images/admin-2.jpg', 1, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(21, 1, 1, 'Ryzen 7 5800X', 'amd-ryzen-7-5800x', 'AMD Ryzen 7 5800X', 'Zen 3', 2020, '8 cores / 16 threads, unlocked', 105, 650, 'AM4', NULL, 'DDR4', NULL, NULL, 'assets/images/admin-2.jpg', 1, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(22, 1, 1, 'Ryzen 5 7600', 'amd-ryzen-5-7600', 'AMD Ryzen 5 7600', 'Zen 4', 2023, '6 cores / 12 threads, integrated graphics', 65, 550, 'AM5', NULL, 'DDR5', NULL, NULL, 'assets/images/admin-2.jpg', 1, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(23, 1, 1, 'Ryzen 7 7800X3D', 'amd-ryzen-7-7800x3d', 'AMD Ryzen 7 7800X3D', 'Zen 4', 2023, '8 cores / 16 threads, 3D V-Cache', 120, 650, 'AM5', NULL, 'DDR5', NULL, NULL, 'assets/images/admin-2.jpg', 1, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(24, 1, 3, 'Core i3-12100F', 'intel-core-i3-12100f', 'Intel Core i3-12100F', 'Alder Lake', 2022, '4 cores / 8 threads', 58, 500, 'LGA1700', NULL, 'DDR4/DDR5', NULL, NULL, 'assets/images/admin-4.jpg', 0, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(25, 1, 3, 'Core i5-13400F', 'intel-core-i5-13400f', 'Intel Core i5-13400F', 'Raptor Lake', 2023, '10 cores / 16 threads', 65, 600, 'LGA1700', NULL, 'DDR4/DDR5', NULL, NULL, 'assets/images/admin-4.jpg', 1, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(26, 1, 3, 'Core i5-13600K', 'intel-core-i5-13600k', 'Intel Core i5-13600K', 'Raptor Lake', 2022, '14 cores / 20 threads, unlocked', 125, 700, 'LGA1700', NULL, 'DDR4/DDR5', NULL, NULL, 'assets/images/admin-4.jpg', 1, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(27, 5, 4, 'Prime A620M-K', 'asus-prime-a620m-k', 'ASUS Prime A620M-K', NULL, NULL, 'AM5 entry-level, 2 DIMM, M.2 PCIe 4.0', NULL, NULL, 'AM5', 'A620', 'DDR5', 'Micro-ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(28, 5, 5, 'MAG B650 Tomahawk WiFi', 'msi-mag-b650-tomahawk-wifi', 'MSI MAG B650 Tomahawk WiFi', NULL, NULL, 'AM5, Wi-Fi 6E, 3x M.2, 2.5G LAN', NULL, NULL, 'AM5', 'B650', 'DDR5', 'ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(29, 5, 6, 'X670 AORUS Elite AX', 'gigabyte-x670-aorus-elite-ax', 'Gigabyte X670 AORUS Elite AX', NULL, NULL, 'AM5, PCIe 5.0 M.2, Wi-Fi 6E', NULL, NULL, 'AM5', 'X670', 'DDR5', 'ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(30, 5, 4, 'TUF Gaming Z790-PLUS WiFi D4', 'asus-tuf-z790-plus-wifi-d4', 'ASUS TUF Gaming Z790-PLUS WiFi D4', NULL, NULL, 'LGA1700, Wi-Fi 6, PCIe 5.0', NULL, NULL, 'LGA1700', 'Z790', 'DDR4', 'ATX', NULL, 'assets/images/admin-5.jpg', 0, 1, '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(69, 2, 2, 'GeForce GTX 1660 Super', 'nvidia-geforce-gtx-1660-super', 'NVIDIA GeForce GTX 1660 Super', 'Turing', 2019, '6GB GDDR6, 1080p gaming', 125, 450, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(70, 2, 2, 'GeForce RTX 2060', 'nvidia-geforce-rtx-2060', 'NVIDIA GeForce RTX 2060', 'Turing', 2019, '6GB GDDR6, entry ray tracing GPU', 160, 500, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(71, 2, 2, 'GeForce RTX 3060 Ti', 'nvidia-geforce-rtx-3060-ti', 'NVIDIA GeForce RTX 3060 Ti', 'Ampere', 2020, '8GB GDDR6, strong 1080p/1440p gaming', 200, 600, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(72, 2, 2, 'GeForce RTX 3080', 'nvidia-geforce-rtx-3080', 'NVIDIA GeForce RTX 3080', 'Ampere', 2020, '10GB GDDR6X, high-end 1440p/4K gaming', 320, 750, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(73, 2, 2, 'GeForce RTX 4060 Ti', 'nvidia-geforce-rtx-4060-ti', 'NVIDIA GeForce RTX 4060 Ti', 'Ada Lovelace', 2023, '8GB/16GB class, efficient 1080p gaming', 160, 550, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(74, 2, 2, 'GeForce RTX 4070', 'nvidia-geforce-rtx-4070', 'NVIDIA GeForce RTX 4070', 'Ada Lovelace', 2023, '12GB GDDR6X, efficient 1440p gaming', 200, 650, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(75, 2, 2, 'GeForce RTX 4070 Ti', 'nvidia-geforce-rtx-4070-ti', 'NVIDIA GeForce RTX 4070 Ti', 'Ada Lovelace', 2023, '12GB GDDR6X, high-refresh 1440p gaming', 285, 700, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(76, 2, 1, 'Radeon RX 6600 XT', 'amd-radeon-rx-6600-xt', 'AMD Radeon RX 6600 XT', 'RDNA 2', 2021, '8GB GDDR6, 1080p gaming', 160, 500, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(77, 2, 1, 'Radeon RX 6700 XT', 'amd-radeon-rx-6700-xt', 'AMD Radeon RX 6700 XT', 'RDNA 2', 2021, '12GB GDDR6, 1440p gaming', 230, 650, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(78, 2, 1, 'Radeon RX 7800 XT', 'amd-radeon-rx-7800-xt', 'AMD Radeon RX 7800 XT', 'RDNA 3', 2023, '16GB GDDR6, 1440p gaming', 263, 700, NULL, NULL, NULL, NULL, NULL, 'assets/images/admin-3.jpg', 1, 1, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(96, 1, 3, 'Core i7-12700F', 'intel-core-i7-12700f', 'Intel Core i7-12700F', 'Alder Lake', 2022, '12 cores / 20 threads, LGA1700', 65, 650, 'LGA1700', NULL, 'DDR4/DDR5', NULL, NULL, 'assets/images/admin-4.jpg', 1, 1, '2026-09-07 15:28:21', '2026-09-07 15:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `product_aliases`
--

CREATE TABLE `product_aliases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `alias_text` varchar(255) NOT NULL,
  `normalized_alias` varchar(255) NOT NULL,
  `source_note` varchar(255) DEFAULT NULL,
  `confidence` decimal(5,2) NOT NULL DEFAULT 100.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_aliases`
--

INSERT INTO `product_aliases` (`id`, `product_id`, `product_variant_id`, `alias_text`, `normalized_alias`, `source_note`, `confidence`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, '5700x3d', '5700x3d', NULL, 100.00, '2026-07-27 13:52:23', '2026-07-27 13:52:23'),
(2, 1, NULL, 'r7 5700 x3d', 'r75700x3d', NULL, 95.00, '2026-07-27 13:52:23', '2026-07-27 13:52:23'),
(3, 2, NULL, 'RTX3070', 'rtx3070', NULL, 100.00, '2026-07-27 13:52:23', '2026-07-27 13:52:23'),
(4, 3, NULL, '12400f', '12400f', NULL, 100.00, '2026-07-27 13:52:23', '2026-07-27 13:52:23'),
(5, 1, NULL, 'Ryzen 7 5700X3D', 'ryzen75700x3d', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(6, 1, NULL, 'AMD 5700X3D', 'amd5700x3d', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(7, 1, NULL, 'Ryzen5700X3D', 'ryzen5700x3d', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(8, 1, NULL, '5700 X3D', '5700x3d2', 'Phase 1 POC alias fixture', 95.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(9, 3, NULL, 'Core i5 12400F', 'corei512400f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(10, 3, NULL, 'i5 12400F', 'i512400f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(11, 3, NULL, 'Intel 12400F', 'intel12400f', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(12, 3, NULL, 'i5-12400F', 'i512400fdash', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(13, 3, NULL, '12400F tray', '12400ftray', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(14, 2, NULL, 'RTX 3070', 'rtx3070base', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(15, 2, NULL, '3070 8GB', '30708gb', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(16, 2, NULL, 'GeForce 3070', 'geforce3070', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(17, 2, NULL, 'NVIDIA 3070', 'nvidia3070', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(18, 2, NULL, 'RTX3070 8G', 'rtx30708g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(19, 20, NULL, 'Ryzen 5 5600', 'ryzen55600', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(20, 20, NULL, 'R5 5600', 'r55600', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(21, 20, NULL, '5600 AM4', '5600am4', 'Phase 1 POC alias fixture', 95.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(22, 20, NULL, 'AMD 5600', 'amd5600', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(23, 20, NULL, 'Ryzen5600', 'ryzen5600', 'Phase 1 POC alias fixture', 95.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(24, 21, NULL, 'Ryzen 7 5800X', 'ryzen75800x', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(25, 21, NULL, 'R7 5800X', 'r75800x', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(26, 21, NULL, '5800X', '5800x', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(27, 21, NULL, 'AMD 5800X', 'amd5800x', 'Phase 1 POC alias fixture', 97.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(28, 21, NULL, 'Ryzen5800X', 'ryzen5800x', 'Phase 1 POC alias fixture', 95.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(29, 22, NULL, 'Ryzen 5 7600', 'ryzen57600', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(30, 22, NULL, 'R5 7600', 'r57600', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(31, 22, NULL, '7600 AM5', '7600am5', 'Phase 1 POC alias fixture', 95.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(32, 22, NULL, 'AMD 7600', 'amd7600', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(33, 22, NULL, 'Ryzen7600', 'ryzen7600', 'Phase 1 POC alias fixture', 95.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(34, 23, NULL, 'Ryzen 7 7800X3D', 'ryzen77800x3d', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(35, 23, NULL, 'R7 7800X3D', 'r77800x3d', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(36, 23, NULL, '7800 X3D', '7800x3d', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(37, 23, NULL, 'AMD 7800X3D', 'amd7800x3d', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(38, 23, NULL, 'Ryzen7800X3D', 'ryzen7800x3d', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(39, 24, NULL, 'Core i3 12100F', 'corei312100f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(40, 24, NULL, 'i3 12100F', 'i312100f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(41, 24, NULL, '12100F', '12100f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(42, 24, NULL, 'Intel 12100F', 'intel12100f', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(43, 24, NULL, 'i3-12100F', 'i312100fdash', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(44, 25, NULL, 'Core i5 13400F', 'corei513400f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(45, 25, NULL, 'i5 13400F', 'i513400f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(46, 25, NULL, '13400F', '13400f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(47, 25, NULL, 'Intel 13400F', 'intel13400f', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(48, 25, NULL, 'i5-13400F', 'i513400fdash', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(49, 26, NULL, 'Core i5 13600K', 'corei513600k', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(50, 26, NULL, 'i5 13600K', 'i513600k', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(51, 26, NULL, '13600K', '13600k', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(52, 26, NULL, 'Intel 13600K', 'intel13600k', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(53, 26, NULL, 'i5-13600K', 'i513600kdash', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(54, 69, NULL, 'GTX 1660 Super', 'gtx1660super', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(55, 69, NULL, '1660 Super', '1660super', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(56, 69, NULL, 'GTX1660S', 'gtx1660s', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(57, 69, NULL, '1660S', '1660s', 'Phase 1 POC alias fixture', 95.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(58, 69, NULL, 'GeForce 1660 Super', 'geforce1660super', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(59, 70, NULL, 'RTX 2060', 'rtx2060', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(60, 70, NULL, '2060 6GB', '20606gb', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(61, 70, NULL, 'GeForce 2060', 'geforce2060', 'Phase 1 POC alias fixture', 97.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(62, 70, NULL, 'NVIDIA 2060', 'nvidia2060', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(63, 70, NULL, 'RTX2060 6G', 'rtx20606g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(64, 71, NULL, 'RTX 3060 Ti', 'rtx3060ti', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(65, 71, NULL, '3060Ti', '3060ti', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(66, 71, NULL, '3060 Ti 8GB', '3060ti8gb', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(67, 71, NULL, 'GeForce 3060 Ti', 'geforce3060ti', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(68, 71, NULL, 'RTX3060Ti 8G', 'rtx3060ti8g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(69, 72, NULL, 'RTX 3080', 'rtx3080', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(70, 72, NULL, '3080 10GB', '308010gb', 'Phase 1 POC alias fixture', 97.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(71, 72, NULL, 'GeForce 3080', 'geforce3080', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(72, 72, NULL, 'NVIDIA 3080', 'nvidia3080', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(73, 72, NULL, 'RTX3080 10G', 'rtx308010g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(74, 73, NULL, 'RTX 4060 Ti', 'rtx4060ti', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(75, 73, NULL, '4060Ti', '4060ti', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(76, 73, NULL, '4060 Ti 8GB', '4060ti8gb', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(77, 73, NULL, 'GeForce 4060 Ti', 'geforce4060ti', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(78, 73, NULL, 'RTX4060Ti 8G', 'rtx4060ti8g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(79, 74, NULL, 'RTX 4070', 'rtx4070', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(80, 74, NULL, '4070 12GB', '407012gb', 'Phase 1 POC alias fixture', 97.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(81, 74, NULL, 'GeForce 4070', 'geforce4070', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(82, 74, NULL, 'NVIDIA 4070', 'nvidia4070', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(83, 74, NULL, 'RTX4070 12G', 'rtx407012g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(84, 75, NULL, 'RTX 4070 Ti', 'rtx4070ti', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(85, 75, NULL, '4070Ti', '4070ti', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(86, 75, NULL, '4070 Ti 12GB', '4070ti12gb', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(87, 75, NULL, 'GeForce 4070 Ti', 'geforce4070ti', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(88, 75, NULL, 'RTX4070Ti 12G', 'rtx4070ti12g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(89, 76, NULL, 'RX 6600 XT', 'rx6600xt', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(90, 76, NULL, '6600XT', '6600xt', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(91, 76, NULL, 'Radeon 6600 XT', 'radeon6600xt', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(92, 76, NULL, '6600 XT 8GB', '6600xt8gb', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(93, 76, NULL, 'RX6600XT 8G', 'rx6600xt8g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(94, 77, NULL, 'RX 6700 XT', 'rx6700xt', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(95, 77, NULL, '6700XT', '6700xt', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(96, 77, NULL, 'Radeon 6700 XT', 'radeon6700xt', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(97, 77, NULL, '6700 XT 12GB', '6700xt12gb', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(98, 77, NULL, 'RX6700XT 12G', 'rx6700xt12g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(99, 78, NULL, 'RX 7800 XT', 'rx7800xt', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(100, 78, NULL, '7800XT', '7800xt', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(101, 78, NULL, 'Radeon 7800 XT', 'radeon7800xt', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(102, 78, NULL, '7800 XT 16GB', '7800xt16gb', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(103, 78, NULL, 'RX7800XT 16G', 'rx7800xt16g', 'Phase 1 POC alias fixture', 96.00, '2026-09-07 15:21:30', '2026-09-07 15:21:30'),
(133, 96, NULL, 'Core i7 12700F', 'corei712700f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:28:21', '2026-09-07 15:28:21'),
(134, 96, NULL, 'i7 12700F', 'i712700f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:28:21', '2026-09-07 15:28:21'),
(135, 96, NULL, '12700F', '12700f', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:28:21', '2026-09-07 15:28:21'),
(136, 96, NULL, 'Intel 12700F', 'intel12700f', 'Phase 1 POC alias fixture', 98.00, '2026-09-07 15:28:21', '2026-09-07 15:28:21'),
(137, 96, NULL, 'i7-12700F', 'i712700fdash', 'Phase 1 POC alias fixture', 100.00, '2026-09-07 15:28:21', '2026-09-07 15:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `name`, `slug`, `sort_order`, `created_at`) VALUES
(1, 'CPU', 'cpu', 1, '2026-07-27 13:52:23'),
(2, 'GPU', 'gpu', 2, '2026-07-27 13:52:23'),
(3, 'RAM', 'ram', 3, '2026-07-27 13:52:23'),
(4, 'Storage', 'storage', 4, '2026-07-27 13:52:23'),
(5, 'Motherboard', 'motherboard', 2, '2026-07-27 15:55:41'),
(6, 'PSU', 'psu', 6, '2026-07-27 15:55:41'),
(7, 'Case', 'case', 7, '2026-07-27 15:55:41'),
(8, 'Cooling', 'cooling', 8, '2026-07-27 15:55:41');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `brand_id` bigint(20) UNSIGNED DEFAULT NULL,
  `variant_name` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `cooler_type` varchar(80) DEFAULT NULL,
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `brand_id`, `variant_name`, `slug`, `cooler_type`, `is_premium`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Ryzen 7 5700X3D Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(2, 3, 3, 'Core i5-12400F Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(3, 20, 1, 'Ryzen 5 5600 Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(4, 21, 1, 'Ryzen 7 5800X Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(5, 22, 1, 'Ryzen 5 7600 Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(6, 23, 1, 'Ryzen 7 7800X3D Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(7, 24, 3, 'Core i3-12100F Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(8, 25, 3, 'Core i5-13400F Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(9, 26, 3, 'Core i5-13600K Tray', 'tray', NULL, 0, 'เนเธกเนเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธเนเธฅเธฐเธเธฅเนเธญเธเนเธเธ Retail', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(16, 1, 1, 'Ryzen 7 5700X3D Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(17, 3, 3, 'Core i5-12400F Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(18, 20, 1, 'Ryzen 5 5600 Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(19, 21, 1, 'Ryzen 7 5800X Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(20, 22, 1, 'Ryzen 5 7600 Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(21, 23, 1, 'Ryzen 7 7800X3D Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(22, 24, 3, 'Core i3-12100F Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(23, 25, 3, 'Core i5-13400F Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50'),
(24, 26, 3, 'Core i5-13600K Box', 'box', 'Stock cooler', 1, 'เธเธฅเนเธญเธ Retail; เธเธฒเธเธฃเธธเนเธเธกเธตเธเธธเธเธฃเธฐเธเธฒเธขเธเธงเธฒเธกเธฃเนเธญเธ', '2026-07-27 16:59:50', '2026-07-27 16:59:50');

-- --------------------------------------------------------

--
-- Table structure for table `raw_price_observations`
--

CREATE TABLE `raw_price_observations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_id` bigint(20) UNSIGNED NOT NULL,
  `collector_job_id` bigint(20) UNSIGNED DEFAULT NULL,
  `external_reference_hash` char(64) NOT NULL,
  `raw_title` varchar(500) NOT NULL,
  `raw_price_text` varchar(120) DEFAULT NULL,
  `raw_condition_text` varchar(255) DEFAULT NULL,
  `raw_warranty_text` varchar(255) DEFAULT NULL,
  `source_url_encrypted` text DEFAULT NULL,
  `evidence_path` varchar(255) DEFAULT NULL,
  `captured_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `processing_status` enum('new','processed','review','rejected') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `raw_price_observations`
--

INSERT INTO `raw_price_observations` (`id`, `source_id`, `collector_job_id`, `external_reference_hash`, `raw_title`, `raw_price_text`, `raw_condition_text`, `raw_warranty_text`, `source_url_encrypted`, `evidence_path`, `captured_at`, `expires_at`, `processing_status`, `created_at`, `updated_at`) VALUES
(5, 9002, 1, '094b536acd197a3fdb40b65ee5fa1599f1b0f0e6c5eb5a06336ebe25d5a029c0', 'ขาย AMD Ryzen 7 5700X3D ประกัน 5 เดือน มีกล่อง ใช้งานปกติ', '฿7,500', NULL, NULL, 'https://example.test/listings/5700x3d-good', NULL, '2026-09-07 09:00:00', NULL, 'review', '2026-09-07 15:29:26', '2026-09-07 15:29:26'),
(6, 9002, 1, 'adac5bc37ec69cb62eb9cdebb5d0956bcdee4fab9c552976d980c720aff58347', 'AMD 5700X3D มือสอง ใช้งานได้ดี ประกัน 3 เดือน', '7300', NULL, NULL, 'https://example.test/listings/5700x3d-good-2', NULL, '2026-09-07 09:02:00', NULL, 'review', '2026-09-07 15:29:26', '2026-09-07 15:29:26'),
(7, 9002, 1, '93387ff48dc1d5cf09c4107050d6fdab738b44d6d976cfbc62f98b2dde60da7a', 'R7 5700X3D สวย มีกล่องและใบเสร็จ', '7900', NULL, NULL, 'https://example.test/listings/5700x3d-good-3', NULL, '2026-09-07 09:03:00', NULL, 'review', '2026-09-07 15:29:26', '2026-09-07 15:29:26'),
(8, 9002, 1, '3ce2b004ea5a456476a1c0453c87dff985bf6b8d032214d1f5a714a68ebec622', 'รับซื้อ 5700x3d งบ 6000', '6000', NULL, NULL, 'https://example.test/listings/wanted-5700x3d', NULL, '2026-09-07 09:05:00', NULL, 'rejected', '2026-09-07 15:29:26', '2026-09-07 15:29:26'),
(9, 9002, 1, '262f3c6e603923fe967e98730c1ee967883bf4e51ebad9dbbd9857497e297ef0', 'Ryzen5700X3D + B550 bundle พร้อมกล่อง', '10500 บาท', NULL, NULL, 'https://example.test/listings/bundle-5700x3d', NULL, '2026-09-07 09:10:00', NULL, 'review', '2026-09-07 15:29:26', '2026-09-07 15:29:26'),
(199, 9002, 16, '0e90c06cba6ef4ab84ab5272f13134417c7fc3dbd20fac0677921dacef3b6255', 'ขาย RTX3070 8GB ใช้งานได้ดี ประกัน 2 เดือน', '7,800', NULL, NULL, 'https://example.test/listings/rtx3070', NULL, '2026-09-07 09:15:00', NULL, 'review', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(200, 9002, 16, '6de6ac031852577063b95bad22d2df8eede17e424d0cc96d70934d63f22d5c16', 'จอง RTX 3070 มัดจำก่อน 500', '500', NULL, NULL, 'https://example.test/listings/deposit-3070', NULL, '2026-09-07 09:30:00', NULL, 'rejected', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(206, 9002, 26, 'fd1222d485706a8cea9dde81a84ba3c0fa919702ee4e316f601f858fbfd8725a', 'คอมทั้งชุด i5 12400F RTX 3070', '18500', NULL, NULL, 'https://example.test/listings/fullpc-12400f', NULL, '2026-09-07 09:20:00', NULL, 'rejected', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(207, 9002, 26, '0ad03187a3ce4c090a79cba338505954225f76ad1490e98c76950a87d418da07', 'Core i5 12400F เปิดไม่ติด ขายเป็นอะไหล่', '900', NULL, NULL, 'https://example.test/listings/broken-12400f', NULL, '2026-09-07 09:25:00', NULL, 'rejected', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(208, 9002, 148, '9d270f42583fef73a17baa67fe04cf0b25919b65b871845a66aaa8f06f75c52b', 'AMD Radeon RX 6600 XT มือสอง', '5400 บาท', NULL, NULL, 'https://example.test/listings/mock-rx6600xt-good', NULL, '2026-09-07 10:00:00', NULL, 'review', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(209, 9002, 148, '387ea86f1b2cb0fab917cf9066a843cd6aa150ee064df396c675d8d374a0bd90', 'RX6600XT พร้อมกล่อง ราคาแบ่งได้', '6100', NULL, NULL, 'https://example.test/listings/mock-rx6600xt-bundleish', NULL, '2026-09-07 10:01:00', NULL, 'review', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(210, 9002, 148, '868f2b4a0fa7311f7f2928ff4151128a9ad802ebd187dbda2ce4a183cdd6bd05', 'รับซื้อ RX 6600 XT งบ 4200', '4200', NULL, NULL, 'https://example.test/listings/mock-rx6600xt-wanted', NULL, '2026-09-07 10:02:00', NULL, 'rejected', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(211, 9002, 149, '5ab40a3de0812de8365a053b3c3d53438a56272716c81476f3ae020f1b987a0f', 'AMD Radeon RX 6700 XT มือสอง', '9300', NULL, NULL, 'https://example.test/listings/mock-rx6700xt-good', NULL, '2026-09-07 10:05:00', NULL, 'review', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(212, 9002, 149, '79a5567b2afda657e388499d0a543bb4aac185f755095518019452d92256e930', 'RX 6700 XT + PSU 650W พร้อมใช้งาน', '11500', NULL, NULL, 'https://example.test/listings/mock-rx6700xt-bundle', NULL, '2026-09-07 10:06:00', NULL, 'review', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(213, 9002, 149, '0434d749298e3466fae8659886506b8de67d808cad78e248f1090c5c3868129b', 'RX6700XT เสีย เปิดไม่ติด ขายซ่อม', '2500', NULL, NULL, 'https://example.test/listings/mock-rx6700xt-broken', NULL, '2026-09-07 10:07:00', NULL, 'rejected', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(214, 9002, 150, '4d41621735b5515c1ac56d130e549132a01c71860fbc17c8e111c5150ea540a5', 'AMD Radeon RX 7800 XT มือสอง', '18200', NULL, NULL, 'https://example.test/listings/mock-rx7800xt-good', NULL, '2026-09-07 10:10:00', NULL, 'review', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(215, 9002, 150, 'db8e1736ae89eddb9dcdda3537ce27adbcc7feda9c148d625e70968e8f7e5e1d', 'Radeon 7800 XT พร้อมของแถม', '20000', NULL, NULL, 'https://example.test/listings/mock-rx7800xt-bundle', NULL, '2026-09-07 10:11:00', NULL, 'review', '2026-09-07 17:10:31', '2026-09-07 17:10:31'),
(216, 9002, 150, '5a155b5a8a616d5e96f0a55bd400425cdac98a24aead4760572f36a927c4c88a', 'จอง RX7800XT มัดจำ 1000 ก่อนนัดรับ', '1000', NULL, NULL, 'https://example.test/listings/mock-rx7800xt-deposit', NULL, '2026-09-07 10:12:00', NULL, 'rejected', '2026-09-07 17:10:31', '2026-09-07 17:10:31');

-- --------------------------------------------------------

--
-- Table structure for table `review_corrections`
--

CREATE TABLE `review_corrections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `price_observation_id` bigint(20) UNSIGNED NOT NULL,
  `review_decision_id` bigint(20) UNSIGNED DEFAULT NULL,
  `field_name` varchar(80) NOT NULL,
  `original_value` text DEFAULT NULL,
  `corrected_value` text DEFAULT NULL,
  `reason` text NOT NULL,
  `corrected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `corrected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `plan` enum('trial','starter','professional') NOT NULL DEFAULT 'trial',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `name`, `slug`, `email`, `phone`, `plan`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Radar Demo Shop', 'radar-demo', 'demo@example.test', NULL, 'professional', 1, '2026-07-27 14:09:55', '2026-07-27 14:09:55');

-- --------------------------------------------------------

--
-- Table structure for table `shop_inventory_imports`
--

CREATE TABLE `shop_inventory_imports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `status` enum('queued','processing','completed','partial','failed') NOT NULL DEFAULT 'queued',
  `row_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `matched_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `error_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shop_inventory_items`
--

CREATE TABLE `shop_inventory_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `raw_product_name` varchar(255) NOT NULL,
  `cost_price` decimal(12,2) NOT NULL,
  `asking_price` decimal(12,2) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `condition_level` enum('like_new','good','fair','poor','unknown') NOT NULL DEFAULT 'unknown',
  `acquired_at` date DEFAULT NULL,
  `status` enum('in_stock','reserved','sold','archived') NOT NULL DEFAULT 'in_stock',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shop_inventory_items`
--

INSERT INTO `shop_inventory_items` (`id`, `shop_id`, `product_id`, `sku`, `raw_product_name`, `cost_price`, `asking_price`, `quantity`, `condition_level`, `acquired_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'CPU-5700X3D-01', 'Ryzen 7 5700X3D', 6500.00, 7900.00, 2, 'good', '2026-06-12', 'in_stock', '2026-07-27 14:09:55', '2026-07-27 14:09:55'),
(2, 1, 2, 'GPU-RTX3070-01', 'RTX 3070 8GB', 6900.00, 8600.00, 1, 'fair', '2026-05-01', 'in_stock', '2026-07-27 14:09:55', '2026-07-27 14:09:55'),
(3, 1, 3, 'CPU-12400F-01', 'Core i5 12400F', 2500.00, 3400.00, 3, 'good', '2026-07-10', 'in_stock', '2026-07-27 14:09:55', '2026-07-27 14:09:55');

-- --------------------------------------------------------

--
-- Table structure for table `shop_users`
--

CREATE TABLE `shop_users` (
  `shop_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('owner','manager','staff') NOT NULL DEFAULT 'staff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `source_incidents`
--

CREATE TABLE `source_incidents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_id` bigint(20) UNSIGNED NOT NULL,
  `incident_type` enum('blocked','rate_limited','authentication_required','source_structure_changed','repeated_extraction_failure','manually_paused','manually_disabled','terms_change','quality_drop','manual_pause','other') NOT NULL,
  `message` text DEFAULT NULL,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `description` text NOT NULL,
  `action_taken` enum('paused','disabled','reduced_rate','monitor','none') NOT NULL DEFAULT 'paused',
  `created_by` varchar(80) NOT NULL DEFAULT 'system',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spec_builds`
--

CREATE TABLE `spec_builds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `budget_min` decimal(12,2) NOT NULL,
  `budget_max` decimal(12,2) NOT NULL,
  `intended_use` varchar(100) NOT NULL,
  `target_resolution` enum('1080p','1440p','4k','none') NOT NULL DEFAULT '1080p',
  `mix_preference` enum('used','mixed','new') NOT NULL DEFAULT 'used',
  `estimated_low` decimal(12,2) DEFAULT NULL,
  `estimated_expected` decimal(12,2) DEFAULT NULL,
  `estimated_high` decimal(12,2) DEFAULT NULL,
  `compatibility_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `spec_builds`
--

INSERT INTO `spec_builds` (`id`, `uuid`, `user_id`, `budget_min`, `budget_max`, `intended_use`, `target_resolution`, `mix_preference`, `estimated_low`, `estimated_expected`, `estimated_high`, `compatibility_notes`, `created_at`, `updated_at`) VALUES
(1, 'c8a7f42d-31e7-454f-9f3a-54864b34dcfd', NULL, 22500.00, 25000.00, 'gaming', '1440p', 'used', 14400.00, 15300.00, 16300.00, 'ตรวจ socket เมนบอร์ด, กำลัง PSU และขนาดเคสก่อนซื้อ; งบคงเหลือโดยประมาณ ฿9,700 สำหรับเมนบอร์ด RAM Storage PSU Case และ Cooling', '2026-07-27 14:47:58', '2026-07-27 14:47:58'),
(2, 'cd5c00eb-89e9-425f-940f-1a079256b731', NULL, 22500.00, 25000.00, 'gaming', '1080p', 'used', 14400.00, 15300.00, 16300.00, 'ตรวจ socket เมนบอร์ด, กำลัง PSU และขนาดเคสก่อนซื้อ; งบคงเหลือโดยประมาณ ฿9,700 สำหรับเมนบอร์ด RAM Storage PSU Case และ Cooling', '2026-07-27 15:16:02', '2026-07-27 15:16:02'),
(3, '8711e819-6f8e-4e1b-9291-79d949254ed6', NULL, 16200.00, 18000.00, 'gaming', '1080p', 'used', 10100.00, 11000.00, 12000.00, 'ตรวจ socket เมนบอร์ด, กำลัง PSU และขนาดเคสก่อนซื้อ; งบคงเหลือโดยประมาณ ฿7,000 สำหรับเมนบอร์ด RAM Storage PSU Case และ Cooling', '2026-07-27 15:23:09', '2026-07-27 15:23:09'),
(4, '7c1e0958-a911-49c2-87ae-59cc1ba22dfd', NULL, 58500.00, 65000.00, 'gaming', '1080p', 'mixed', 14400.00, 15300.00, 16300.00, 'ตรวจ socket เมนบอร์ด, กำลัง PSU และขนาดเคสก่อนซื้อ; งบคงเหลือโดยประมาณ ฿49,700 สำหรับเมนบอร์ด RAM Storage PSU Case และ Cooling', '2026-07-27 15:38:34', '2026-07-27 15:38:34'),
(5, '58828160-f560-4b41-b2ec-9ee8bc8fccd6', NULL, 31500.00, 35000.00, 'gaming', '1440p', 'used', 24850.00, 27050.00, 29550.00, 'CPU และเมนบอร์ดรองรับ socket AM4; งบคงเหลือโดยประมาณ ฿7,950 สำหรับเมนบอร์ด RAM Storage PSU Case และ Cooling', '2026-07-27 15:58:15', '2026-07-27 15:58:15'),
(6, '449ec99d-725d-4d8e-8545-b74aa99b4720', NULL, 27000.00, 30000.00, 'gaming', '1440p', 'used', 11400.00, 12400.00, 13600.00, 'CPU และเมนบอร์ดรองรับ socket AM5; งบคงเหลือโดยประมาณ ฿17,600 สำหรับ ram, storage, psu, case, cooling', '2026-07-27 17:00:59', '2026-07-27 17:00:59');

-- --------------------------------------------------------

--
-- Table structure for table `spec_build_items`
--

CREATE TABLE `spec_build_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `spec_build_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `component_role` varchar(50) NOT NULL,
  `quantity` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `price_low` decimal(12,2) DEFAULT NULL,
  `price_expected` decimal(12,2) DEFAULT NULL,
  `price_high` decimal(12,2) DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `is_alternative` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `spec_build_items`
--

INSERT INTO `spec_build_items` (`id`, `spec_build_id`, `product_id`, `component_role`, `quantity`, `price_low`, `price_expected`, `price_high`, `confidence_score`, `is_alternative`, `notes`, `created_at`) VALUES
(1, 1, 1, 'cpu', 1, 7200.00, 7500.00, 7800.00, 68.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 14:47:58'),
(2, 1, 2, 'gpu', 1, 7200.00, 7800.00, 8500.00, 82.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 14:47:58'),
(3, 2, 1, 'cpu', 1, 7200.00, 7500.00, 7800.00, 68.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:16:02'),
(4, 2, 2, 'gpu', 1, 7200.00, 7800.00, 8500.00, 82.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:16:02'),
(5, 3, 3, 'cpu', 1, 2900.00, 3200.00, 3500.00, 78.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:23:09'),
(6, 3, 2, 'gpu', 1, 7200.00, 7800.00, 8500.00, 82.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:23:09'),
(7, 4, 1, 'cpu', 1, 7200.00, 7500.00, 7800.00, 68.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:38:34'),
(8, 4, 2, 'gpu', 1, 7200.00, 7800.00, 8500.00, 82.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:38:34'),
(9, 5, 1, 'cpu', 1, 7200.00, 7500.00, 7800.00, 68.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:58:15'),
(10, 5, 5, 'motherboard', 1, 3200.00, 3500.00, 3900.00, 76.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:58:15'),
(11, 5, 2, 'gpu', 1, 7200.00, 7800.00, 8500.00, 82.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:58:15'),
(12, 5, 12, 'ram', 1, 950.00, 1100.00, 1250.00, 76.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:58:15'),
(13, 5, 14, 'storage', 1, 1400.00, 1600.00, 1800.00, 76.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:58:15'),
(14, 5, 16, 'psu', 1, 2500.00, 2800.00, 3100.00, 76.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:58:15'),
(15, 5, 18, 'case', 1, 1600.00, 1800.00, 2100.00, 76.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:58:15'),
(16, 5, 19, 'cooling', 1, 800.00, 950.00, 1100.00, 76.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 15:58:15'),
(17, 6, 22, 'cpu', 1, 5700.00, 6200.00, 6800.00, 80.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 17:00:59'),
(18, 6, 28, 'motherboard', 1, 5700.00, 6200.00, 6800.00, 80.00, 0, 'อ้างอิงดัชนีราคาประกาศล่าสุด', '2026-07-27 17:00:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','reviewer','operator','admin') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_article_author` (`author_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_user` (`user_id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `collection_runs`
--
ALTER TABLE `collection_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `run_id` (`run_id`),
  ADD KEY `idx_collection_runs_source` (`source_id`,`started_at`),
  ADD KEY `idx_collection_runs_mode` (`execution_mode`,`started_at`);

--
-- Indexes for table `collector_jobs`
--
ALTER TABLE `collector_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_jobs_source` (`source_id`),
  ADD KEY `fk_jobs_product` (`product_id`),
  ADD KEY `idx_jobs_status` (`status`,`created_at`),
  ADD KEY `idx_jobs_run_id` (`run_id`);

--
-- Indexes for table `data_sources`
--
ALTER TABLE `data_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_data_sources_key` (`source_key`);

--
-- Indexes for table `deal_checks`
--
ALTER TABLE `deal_checks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `fk_deal_product` (`product_id`),
  ADD KEY `fk_deal_variant` (`product_variant_id`);

--
-- Indexes for table `extraction_runs`
--
ALTER TABLE `extraction_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_extraction_evidence` (`evidence_id`),
  ADD KEY `idx_extraction_raw` (`raw_observation_id`,`created_at`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_faq_article` (`article_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_window` (`email_hash`,`ip_address`,`attempted_at`),
  ADD KEY `idx_login_attempts_success` (`success`,`attempted_at`);

--
-- Indexes for table `market_evidence`
--
ALTER TABLE `market_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_evidence_raw` (`raw_observation_id`),
  ADD KEY `idx_evidence_source` (`source_id`,`captured_at`),
  ADD KEY `idx_evidence_hash` (`content_hash`);

--
-- Indexes for table `observation_review_decisions`
--
ALTER TABLE `observation_review_decisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_review_observation` (`price_observation_id`),
  ADD KEY `fk_review_raw` (`raw_observation_id`),
  ADD KEY `fk_review_extraction` (`extraction_run_id`),
  ADD KEY `fk_review_user` (`reviewer_id`),
  ADD KEY `idx_review_lane_decision` (`lane`,`decision`,`created_at`);

--
-- Indexes for table `price_histories`
--
ALTER TABLE `price_histories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_history_snapshot` (`product_id`,`product_variant_id`,`snapshot_date`),
  ADD KEY `fk_history_variant` (`product_variant_id`);

--
-- Indexes for table `price_indices`
--
ALTER TABLE `price_indices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_index_variant` (`product_variant_id`),
  ADD KEY `idx_index_latest` (`product_id`,`product_variant_id`,`price_type`,`last_calculated_at`),
  ADD KEY `idx_index_hash` (`calculation_hash`);

--
-- Indexes for table `price_observations`
--
ALTER TABLE `price_observations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_obs_raw` (`raw_observation_id`),
  ADD KEY `fk_obs_variant` (`product_variant_id`),
  ADD KEY `idx_obs_index` (`product_id`,`verified_status`,`price_type`,`observed_at`),
  ADD KEY `idx_obs_review` (`verified_status`,`classification_confidence`);

--
-- Indexes for table `price_snapshot_observations`
--
ALTER TABLE `price_snapshot_observations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_snapshot_observation` (`snapshot_id`,`observation_id`),
  ADD KEY `idx_snapshot_observations_snapshot` (`snapshot_id`,`inclusion_status`),
  ADD KEY `idx_snapshot_observations_observation` (`observation_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_products_category` (`category_id`),
  ADD KEY `fk_products_brand` (`brand_id`),
  ADD KEY `idx_products_search` (`is_active`,`category_id`,`brand_id`);
ALTER TABLE `products` ADD FULLTEXT KEY `ft_products_name` (`model_name`,`full_name`,`spec_summary`);

--
-- Indexes for table `product_aliases`
--
ALTER TABLE `product_aliases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_normalized_alias` (`normalized_alias`),
  ADD KEY `fk_alias_product` (`product_id`),
  ADD KEY `fk_alias_variant` (`product_variant_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_variant_slug` (`product_id`,`slug`),
  ADD KEY `fk_variants_brand` (`brand_id`);

--
-- Indexes for table `raw_price_observations`
--
ALTER TABLE `raw_price_observations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_source_reference` (`source_id`,`external_reference_hash`),
  ADD KEY `fk_raw_job` (`collector_job_id`),
  ADD KEY `idx_raw_queue` (`processing_status`,`captured_at`);

--
-- Indexes for table `review_corrections`
--
ALTER TABLE `review_corrections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_correction_field` (`price_observation_id`,`field_name`),
  ADD KEY `fk_review_corrections_decision` (`review_decision_id`),
  ADD KEY `fk_review_corrections_user` (`corrected_by`),
  ADD KEY `idx_review_corrections_observation` (`price_observation_id`,`corrected_at`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `shop_inventory_imports`
--
ALTER TABLE `shop_inventory_imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_import_shop` (`shop_id`);

--
-- Indexes for table `shop_inventory_items`
--
ALTER TABLE `shop_inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inventory_product` (`product_id`),
  ADD KEY `idx_inventory_status` (`shop_id`,`status`,`acquired_at`);

--
-- Indexes for table `shop_users`
--
ALTER TABLE `shop_users`
  ADD PRIMARY KEY (`shop_id`,`user_id`),
  ADD KEY `fk_shop_user_user` (`user_id`);

--
-- Indexes for table `source_incidents`
--
ALTER TABLE `source_incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident_source` (`source_id`,`created_at`);

--
-- Indexes for table `spec_builds`
--
ALTER TABLE `spec_builds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `fk_build_user` (`user_id`);

--
-- Indexes for table `spec_build_items`
--
ALTER TABLE `spec_build_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_build_item_build` (`spec_build_id`),
  ADD KEY `fk_build_item_product` (`product_id`);

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
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1667;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `collection_runs`
--
ALTER TABLE `collection_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=220;

--
-- AUTO_INCREMENT for table `collector_jobs`
--
ALTER TABLE `collector_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=742;

--
-- AUTO_INCREMENT for table `data_sources`
--
ALTER TABLE `data_sources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9492;

--
-- AUTO_INCREMENT for table `deal_checks`
--
ALTER TABLE `deal_checks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `extraction_runs`
--
ALTER TABLE `extraction_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=674;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `market_evidence`
--
ALTER TABLE `market_evidence`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=674;

--
-- AUTO_INCREMENT for table `observation_review_decisions`
--
ALTER TABLE `observation_review_decisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=964;

--
-- AUTO_INCREMENT for table `price_histories`
--
ALTER TABLE `price_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT for table `price_indices`
--
ALTER TABLE `price_indices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=256;

--
-- AUTO_INCREMENT for table `price_observations`
--
ALTER TABLE `price_observations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2070;

--
-- AUTO_INCREMENT for table `price_snapshot_observations`
--
ALTER TABLE `price_snapshot_observations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1385;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT for table `product_aliases`
--
ALTER TABLE `product_aliases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `raw_price_observations`
--
ALTER TABLE `raw_price_observations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1820;

--
-- AUTO_INCREMENT for table `review_corrections`
--
ALTER TABLE `review_corrections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shop_inventory_imports`
--
ALTER TABLE `shop_inventory_imports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shop_inventory_items`
--
ALTER TABLE `shop_inventory_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `source_incidents`
--
ALTER TABLE `source_incidents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `spec_builds`
--
ALTER TABLE `spec_builds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `spec_build_items`
--
ALTER TABLE `spec_build_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `fk_article_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `collection_runs`
--
ALTER TABLE `collection_runs`
  ADD CONSTRAINT `fk_collection_runs_source` FOREIGN KEY (`source_id`) REFERENCES `data_sources` (`id`);

--
-- Constraints for table `collector_jobs`
--
ALTER TABLE `collector_jobs`
  ADD CONSTRAINT `fk_jobs_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_jobs_source` FOREIGN KEY (`source_id`) REFERENCES `data_sources` (`id`);

--
-- Constraints for table `deal_checks`
--
ALTER TABLE `deal_checks`
  ADD CONSTRAINT `fk_deal_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_deal_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `extraction_runs`
--
ALTER TABLE `extraction_runs`
  ADD CONSTRAINT `fk_extraction_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `market_evidence` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_extraction_raw` FOREIGN KEY (`raw_observation_id`) REFERENCES `raw_price_observations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faqs`
--
ALTER TABLE `faqs`
  ADD CONSTRAINT `fk_faq_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_evidence`
--
ALTER TABLE `market_evidence`
  ADD CONSTRAINT `fk_evidence_raw` FOREIGN KEY (`raw_observation_id`) REFERENCES `raw_price_observations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_evidence_source` FOREIGN KEY (`source_id`) REFERENCES `data_sources` (`id`);

--
-- Constraints for table `observation_review_decisions`
--
ALTER TABLE `observation_review_decisions`
  ADD CONSTRAINT `fk_review_extraction` FOREIGN KEY (`extraction_run_id`) REFERENCES `extraction_runs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_review_observation` FOREIGN KEY (`price_observation_id`) REFERENCES `price_observations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_review_raw` FOREIGN KEY (`raw_observation_id`) REFERENCES `raw_price_observations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_review_user` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `price_histories`
--
ALTER TABLE `price_histories`
  ADD CONSTRAINT `fk_history_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_indices`
--
ALTER TABLE `price_indices`
  ADD CONSTRAINT `fk_index_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_index_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_observations`
--
ALTER TABLE `price_observations`
  ADD CONSTRAINT `fk_obs_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_obs_raw` FOREIGN KEY (`raw_observation_id`) REFERENCES `raw_price_observations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_obs_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `price_snapshot_observations`
--
ALTER TABLE `price_snapshot_observations`
  ADD CONSTRAINT `fk_snapshot_observations_observation` FOREIGN KEY (`observation_id`) REFERENCES `price_observations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_snapshot_observations_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `price_indices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`);

--
-- Constraints for table `product_aliases`
--
ALTER TABLE `product_aliases`
  ADD CONSTRAINT `fk_alias_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alias_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `fk_variants_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `raw_price_observations`
--
ALTER TABLE `raw_price_observations`
  ADD CONSTRAINT `fk_raw_job` FOREIGN KEY (`collector_job_id`) REFERENCES `collector_jobs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_raw_source` FOREIGN KEY (`source_id`) REFERENCES `data_sources` (`id`);

--
-- Constraints for table `review_corrections`
--
ALTER TABLE `review_corrections`
  ADD CONSTRAINT `fk_review_corrections_decision` FOREIGN KEY (`review_decision_id`) REFERENCES `observation_review_decisions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_review_corrections_observation` FOREIGN KEY (`price_observation_id`) REFERENCES `price_observations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_corrections_user` FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shop_inventory_imports`
--
ALTER TABLE `shop_inventory_imports`
  ADD CONSTRAINT `fk_import_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_inventory_items`
--
ALTER TABLE `shop_inventory_items`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_users`
--
ALTER TABLE `shop_users`
  ADD CONSTRAINT `fk_shop_user_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_user_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `source_incidents`
--
ALTER TABLE `source_incidents`
  ADD CONSTRAINT `fk_incident_source` FOREIGN KEY (`source_id`) REFERENCES `data_sources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spec_builds`
--
ALTER TABLE `spec_builds`
  ADD CONSTRAINT `fk_build_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `spec_build_items`
--
ALTER TABLE `spec_build_items`
  ADD CONSTRAINT `fk_build_item_build` FOREIGN KEY (`spec_build_id`) REFERENCES `spec_builds` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_build_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
