USING museumdb

DELIMITER $$

CREATE PROCEDURE GetFullArtworkCatalog()
BEGIN   
    SELECT ARTWORK.title, ARTIST.first_name, ARTIST.last_name, LOCATION.name, ARTWORK.is_owned
    FROM ARTWORK
    LEFT JOIN ARTWORK_CREATOR ON ARTWORK_CREATOR.artwork_id = ARTWORK.artwork_id
    LEFT JOIN ARTIST ON ARTIST.artist_id = ARTWORK_CREATOR.artist_id
    LEFT JOIN LOCATION ON LOCATION.location_id = ARTWORK.location_id
    ORDER BY ARTWORK.title;
END$$

--p_title IS NULL will return owned/loaned status of everything if no p_title provided, and title LIKE CONCAT(...) will return
--similar results as what is searched (exact match not required. If search for mona lisa but database only
--contains Mona Lisa, it will return Mona Lisa even though the search wasn't exact)
CREATE PROCEDURE OwnedOrLoaned(
    IN p_title varchar(255)
)
BEGIN
    SELECT artwork_id, title, is_owned
    FROM ARTWORK
    WHERE p_title IS NULL OR title LIKE CONCAT('%', p_title, '%')
    ORDER BY title;
END$$


--This function takes 2 inputs for first and last name, and outputs all artwork created by the artist(s)
--It will show all artist ids, artwork ids, and titles for the given first and last name
CREATE PROCEDURE ArtworkByArtist(
    IN p_first_name varchar(255),
    IN p_last_name varchar(255)
)
BEGIN
    SELECT ARTIST.artist_id, ARTWORK.artwork_id, ARTWORK.title
    FROM ARTIST
    LEFT JOIN ARTWORK_CREATOR ON ARTIST.artist_id = ARTWORK_CREATOR.artist_id
    LEFT JOIN ARTWORK ON ARTWORK_CREATOR.artwork_id = ARTWORK.artwork_id
    WHERE ARTIST.first_name = p_first_name AND ARTIST.last_name = p_last_name
    ORDER BY ARTIST.artist_id, ARTWORK.title;
END$$


--This takes an integer value for the medium and returns all art and respective artists that uses that medium
CREATE PROCEDURE ArtworkByMedium(
    IN p_medium int
)
BEGIN
    SELECT ARTWORK.artwork_id, ARTWORK.title, ARTIST.artist_id, ARTIST.first_name, ARTIST.last_name
    FROM ARTWORK
    LEFT JOIN ARTWORK_CREATOR ON ARTWORK.artwork_id = ARTWORK_CREATOR.artwork_id
    LEFT JOIN ARTIST ON ARTWORK_CREATOR.artist_id = ARTIST.artist_id
    WHERE ARTWORK.medium = p_medium
    ORDER BY ARTWORK.title;
END$$
DELIMITER ;