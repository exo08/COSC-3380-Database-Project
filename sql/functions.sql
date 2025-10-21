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

DELIMITER ;
