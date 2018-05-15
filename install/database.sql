SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `Adminbacklog` (
  `type` varchar(20) NOT NULL,
  `title` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `starttime` timestamp NULL DEFAULT NULL,
  `messagetime` timestamp NULL DEFAULT NULL,
  `message_id` int(11) NOT NULL,
  `message` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `Adminbacklog_autodel` (
  `message_id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'text',
  `text` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `update_id` int(11) NOT NULL,
  `date` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `Adminbacklog_log` (
  `id` int(11) NOT NULL,
  `msg` text NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `Adminbacklog`
  ADD UNIQUE KEY `message_id` (`message_id`);

ALTER TABLE `Adminbacklog_autodel`
  ADD UNIQUE KEY `message_id` (`message_id`);

ALTER TABLE `Adminbacklog_log`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `Adminbacklog_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
