USING museumdb;

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
  BEGIN
    START TRANSACTION;
    SELECT COALESCE(MAX(artist_id),0)+1 INTO p_artist_id
    FROM ARTIST;

    INSERT INTO ARTIST(artist_id, first_name, last_name, birth_year, death_year, nationality, bio)
    VALUES(p_artist_id, p_first_name, p_last_name, p_birth_year, p_death_year, p_nationality, p_bio);
    COMMIT;
  END$$
DELIMITER ;
