USE museumdb;

DELIMITER $$
CREATE PROCEDURE CreateArtist(
  IN p_first_name varchar(255),
  IN p_last_name varchar(255),
  IN p_birth_year int,
  IN p_death_year int,
  IN p_nationality varchar(255),
  IN p_bio text,
  OUT p_artist_id
)
-- Changed artist_id to auto increment instead of manually assigning IDs
BEGIN
  INSERT INTO ARTIST (first_name, last_name, birth_year, death_year, nationality, bio)
  VALUES (p_first_name, p_last_name, p_birth_year, p_death_year, p_nationality, p_bio);
  SET p_artist_id = LAST_INSERT_ID();
END$$

DELIMITER ;

-- TODO: Run in MySQL terminal to make artist_id auto increment
-- ALTER TABLE ARTIST MODIFY artist_id INT NOT NULL AUTO_INCREMENT,
-- ALTER TABLE ARTIST AUTO_INCREMENT = 1001; 
-- Set starting point for artist_id to 1001 but we can change it to something else if needed
