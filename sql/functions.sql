USE museumdb;

DELIMITER $$
CREATE PROCEDURE CreateArtist(
  IN p_first_name varchar(255),
  IN p_last_name varchar(255),
  IN p_birth_year int,
  IN p_death_year int,
  IN p_nationality varchar(255),
  IN p_bio text,
  OUT p_artist_id int
)
-- Changed artist_id to auto increment instead of manually assigning IDs
BEGIN
  INSERT INTO ARTIST (first_name, last_name, birth_year, death_year, nationality, bio)
  VALUES (p_first_name, p_last_name, p_birth_year, p_death_year, p_nationality, p_bio);
  SET p_artist_id = LAST_INSERT_ID(); 
END$$

CREATE PROCEDURE CreateArtwork(
  IN p_title varchar(255),
  IN p_creation_year int,
  IN p_medium int,
  IN p_height decimal,
  IN p_width decimal,
  IN p_depth decimal,
  IN p_is_owned bool,
  IN p_location_id int,
  IN p_description text,
  OUT p_artwork_id int
)

BEGIN
  INSERT INTO ARTWORK(title, creation_year, medium, height, width, depth, is_owned, location_id, description)
  VALUES (p_title, p_creation_year, p_medium, p_height, p_width, p_depth, p_is_owned, p_location_id, p_description);
  SET p_artwork_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateExhibition(
  IN p_title varchar(255),
  IN p_start_date date,
  IN p_end_date date,
  IN p_curator_id int,
  IN p_description text,
  IN p_theme_sponsor varchar(255),
  OUT p_exhibition_id int
)

BEGIN
  INSERT INTO EXHIBITION(title, start_date, end_date, curator_id, description, theme_sponsor)
  VALUES(p_title, p_start_date, p_end_date, p_curator_id, p_description, p_theme_sponsor)
  SET p_exhibition_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateLocation(
  IN p_location_type smallint,
  IN p_name varchar(255),
  OUT p_location_id
)

BEGIN 
  INSERT INTO LOCATION(location_type, name)
  VALUES(p_location_type, p_name)
  SET p_location_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateDonor(
  IN p_first_name varchar(255),
  IN p_last_name varchar(255),
  IN p_organization_name varchar(255),
  IN p_is_organization bool,
  IN p_address varchar(255),
  IN p_email varchar(255),
  IN p_phone varchar(255)
  OUT p_donor_id int
)

BEGIN
  INSERT INTO DONOR(first_name, last_name, organization_name, is_organization, address, email, phone)
  VALUES(p_first_name, p_last_name, p_organization_name, p_is_organization, p_address, p_email, p_phone)
  SET p_donor_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateDonation(
  IN p_donor_id int,
  IN p_amount decimal,
  IN p_donation_date date,
  IN p_purpose smallint,
  IN p_acquisition_id int,
  OUT p_donation_id
)

BEGIN
  INSERT INTO DONATION(donor_id, amount, donation_date, purpose, acquisition_id)
  VALUES(p_donor_id, p_amount, p_donation_date, p_purpose, p_acquisition_id)
  SET p_donation_id = LAST_INSERT_ID();
END$$

DELIMITER ;
