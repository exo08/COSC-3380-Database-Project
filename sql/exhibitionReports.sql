USING museumdb

DELIMITER $$

--returns all current and future exhibitions
CREATE PROCEDURE GetCurrentAndUpcomingExhibitions()
BEGIN
    SELECT EXHIBITION.exhibition_id, EXHIBITION.title, EXHIBITION.start_date, EXHIBITION.end_date
    FROM EXHIBITION
    WHERE EXHIBITION.end_date >= CURDATE()
    ORDER BY EXHIBITION.start_date;
END$$


--returns a list of all artworks to be displayed at a particular exhibition. Requires exact match for title
CREATE PROCEDURE GetExhibitionArtworkList(
    IN p_title varchar(255)
)
BEGIN
    SELECT ARTWORK.title, ARTIST.first_name, ARTIST.last_name
    FROM EXHIBITION
    LEFT JOIN EXHIBITION_ARTWORK ON EXHIBITION.exhibition_id = EXHIBITION_ARTWORK.exhibition_id
    LEFT JOIN ARTWORK ON EXHIBITION_ARTWORK.artwork_id = ARTWORK.artwork_id
    LEFT JOIN ARTWORK_CREATOR ON ARTWORK.artwork_id = ARTWORK_CREATOR.artwork_id
    LEFT JOIN ARTIST ON ARTWORK_CREATOR.artist_id = ARTIST.artist_id
    WHERE EXHIBITION.title = p_title
    ORDER BY ARTWORK.title;
END$$

DELIMITER ;