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
DELIMITER ;