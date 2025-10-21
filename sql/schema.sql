CREATE TABLE `ARTIST` (
  `artist_id` int PRIMARY KEY AUTO_INCREMENT,
  `first_name` varchar(255),
  `last_name` varchar(255),
  `birth_year` int,
  `death_year` int,
  `nationality` varchar(255),
  `bio` text
);

ALTER TABLE `ARTIST` AUTO_INCREMENT = 1001; -- start at 1001

CREATE TABLE `ARTWORK` (
  `artwork_id` int PRIMARY KEY AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `creation_year` int,
  `medium` int,
  `height` decimal,
  `width` decimal,
  `depth` decimal,
  `is_owned` bool NOT NULL,
  `location_id` int,
  `description` text
);

ALTER TABLE `ARTWORK` AUTO_INCREMENT=1001;

CREATE TABLE `EXHIBITION` (
  `exhibition_id` int PRIMARY KEY AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `curator_id` int,
  `description` text,
  `theme_sponsor` varchar(255)
);

ALTER TABLE `EXHIBITION` AUTO_INCREMENT=1001;

CREATE TABLE `LOCATION` (
  `location_id` int PRIMARY KEY AUTO_INCREMENT,
  `location_type` smallint NOT NULL,
  `name` varchar(255)
);

ALTER TABLE `LOCATION` AUTO_INCREMENT=1001;

CREATE TABLE `DONOR` (
  `donor_id` int PRIMARY KEY AUTO_INCREMENT,
  `first_name` varchar(255),
  `last_name` varchar(255),
  `organization_name` varchar(255),
  `is_organization` bool NOT NULL,
  `address` varchar(255),
  `email` varchar(255),
  `phone` varchar(255)
);

ALTER TABLE `DONOR` AUTO_INCREMENT=1001;

CREATE TABLE `DONATION` (
  `donation_id` int PRIMARY KEY AUTO_INCREMENT,
  `donor_id` int NOT NULL,
  `amount` decimal NOT NULL,
  `donation_date` date NOT NULL,
  `purpose` smallint,
  `acquisition_id` int
);

ALTER TABLE `DONATION` AUTO_INCREMENT=1001;

CREATE TABLE `ACQUISITION` (
  `acquisition_id` int PRIMARY KEY AUTO_INCREMENT,
  `artwork_id` int UNIQUE NOT NULL,
  `acquisition_date` date NOT NULL,
  `price_value` decimal,
  `source_name` varchar(255),
  `method` smallint NOT NULL
);

ALTER TABLE `ACQUISITION` AUTO_INCREMENT=1001;

CREATE TABLE `MEMBER` (
  `member_id` int PRIMARY KEY AUTO_INCREMENT,
  `first_name` varchar(255),
  `last_name` varchar(255),
  `email` varchar(255),
  `phone` varchar(255),
  `address` varchar(255),
  `membership_type` smallint,
  `is_student` bool,
  `start_date` date,
  `expiration_date` date,
  `auto_renew` bool
);

ALTER TABLE `MEMBER` AUTO_INCREMENT=1001;

CREATE TABLE `VISITOR` (
  `visitor_id` int PRIMARY KEY AUTO_INCREMENT,
  `first_name` varchar(255),
  `last_name` varchar(255),
  `is_student` bool,
  `email` varchar(255),
  `phone` varchar(255),
  `created_at` date
);

ALTER TABLE `VISITOR` AUTO_INCREMENT=1001;

CREATE TABLE `TICKET` (
  `ticket_id` int PRIMARY KEY AUTO_INCREMENT,
  `event_id` int,
  `visitor_id` int,
  `member_id` int,
  `purchase_date` date,
  `quantity` int,
  `checked_in` bool,
  `check_in_time` datetime
);

ALTER TABLE `TICKET` AUTO_INCREMENT=1001;

CREATE TABLE `EVENT` (
  `event_id` int PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255),
  `event_date` date NOT NULL,
  `location_id` int,
  `exhibition_id` int,
  `capacity` int
);

ALTER TABLE `EVENT` AUTO_INCREMENT=1001;

CREATE TABLE `SHOP_ITEM` (
  `item_id` int PRIMARY KEY AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `description` text,
  `category` varchar(255),
  `price` decimal(6,2) NOT NULL,
  `quantity_in_stock` int
);

ALTER TABLE `SHOP_ITEM` AUTO_INCREMENT=1001;

CREATE TABLE `SALE` (
  `sale_id` int PRIMARY KEY AUTO_INCREMENT,
  `sale_date` datetime NOT NULL,
  `member_id` int,
  `visitor_id` int,
  `total_amount` decimal(6,2) NOT NULL,
  `discount_amount` decimal(4,2),
  `payment_method` int
);

ALTER TABLE `SALE` AUTO_INCREMENT=1001;

CREATE TABLE `STAFF` (
  `staff_id` int PRIMARY KEY AUTO_INCREMENT,
  `ssn` int,
  `department_id` int NOT NULL,
  `name` varchar(255),
  `email` varchar(255) UNIQUE NOT NULL,
  `title` varchar(255),
  `hire_date` date NOT NULL,
  `supervisor_id` int
);

ALTER TABLE `STAFF` AUTO_INCREMENT=1001;

CREATE TABLE `DEPARTMENT` (
  `department_id` int PRIMARY KEY AUTO_INCREMENT,
  `department_name` varchar(255) NOT NULL,
  `manager_id` int,
  `location` varchar(255)
);

ALTER TABLE `DEPARTMENT` AUTO_INCREMENT=1001;

CREATE TABLE `ARTWORK_CREATOR` (
  `artwork_id` int NOT NULL,
  `artist_id` int NOT NULL,
  `role` varchar(255)
);

CREATE TABLE `EXHIBITION_ARTWORK` (
  `exhibition_art_id` int PRIMARY KEY,
  `artwork_id` int NOT NULL,
  `location_id` int NOT NULL,
  `exhibition_id` int NOT NULL,
  `start_view_date` date,
  `end_view_date` date
);

CREATE TABLE `SALE_ITEM` (
  `sale_item_id` int PRIMARY KEY,
  `sale_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price_at_sale` decimal(6,2)
);

CREATE UNIQUE INDEX `ARTWORK_CREATOR_index_0` ON `ARTWORK_CREATOR` (`artwork_id`, `artist_id`);

CREATE INDEX `ARTWORK_CREATOR_index_1` ON `ARTWORK_CREATOR` (`artist_id`);

CREATE INDEX `ARTWORK_CREATOR_index_2` ON `ARTWORK_CREATOR` (`artwork_id`);

ALTER TABLE `ARTWORK` ADD FOREIGN KEY (`location_id`) REFERENCES `LOCATION` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE; 

ALTER TABLE `EXHIBITION` ADD FOREIGN KEY (`curator_id`) REFERENCES `STAFF` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `EXHIBITION_ARTWORK` ADD FOREIGN KEY (`exhibition_id`) REFERENCES `EXHIBITION` (`exhibition_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `EXHIBITION_ARTWORK` ADD FOREIGN KEY (`artwork_id`) REFERENCES `ARTWORK` (`artwork_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `EXHIBITION_ARTWORK` ADD FOREIGN KEY (`location_id`) REFERENCES `LOCATION` (`location_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `ARTWORK_CREATOR` ADD FOREIGN KEY (`artwork_id`) REFERENCES `ARTWORK` (`artwork_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ARTWORK_CREATOR` ADD FOREIGN KEY (`artist_id`) REFERENCES `ARTIST` (`artist_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `ACQUISITION` ADD FOREIGN KEY (`artwork_id`) REFERENCES `ARTWORK` (`artwork_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `DONATION` ADD FOREIGN KEY (`donor_id`) REFERENCES `DONOR` (`donor_id`) ON DELETE RESTRICT ON UPDATE CASCADE; -- But in our case donor_id shouldnt change

ALTER TABLE `DONATION` ADD FOREIGN KEY (`acquisition_id`) REFERENCES `ACQUISITION` (`acquisition_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `EVENT` ADD FOREIGN KEY (`location_id`) REFERENCES `LOCATION` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `EVENT` ADD FOREIGN KEY (`exhibition_id`) REFERENCES `EXHIBITION` (`exhibition_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `TICKET` ADD FOREIGN KEY (`event_id`) REFERENCES `EVENT` (`event_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `TICKET` ADD FOREIGN KEY (`member_id`) REFERENCES `MEMBER` (`member_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `TICKET` ADD FOREIGN KEY (`visitor_id`) REFERENCES `VISITOR` (`visitor_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `SALE` ADD FOREIGN KEY (`member_id`) REFERENCES `MEMBER` (`member_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `SALE` ADD FOREIGN KEY (`visitor_id`) REFERENCES `VISITOR` (`visitor_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `SALE_ITEM` ADD FOREIGN KEY (`sale_id`) REFERENCES `SALE` (`sale_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `SALE_ITEM` ADD FOREIGN KEY (`item_id`) REFERENCES `SHOP_ITEM` (`item_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `STAFF` ADD FOREIGN KEY (`department_id`) REFERENCES `DEPARTMENT` (`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `STAFF` ADD FOREIGN KEY (`supervisor_id`) REFERENCES `STAFF` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `DEPARTMENT` ADD FOREIGN KEY (`manager_id`) REFERENCES `STAFF` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;
