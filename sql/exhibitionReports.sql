USE u452501794_MuseumDB;

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


--Returns the number of tickets sold for specified exhibition and number of people who actually showed up
CREATE PROCEDURE GetExhibitionAttendance(
    IN p_title varchar(255)
)
BEGIN
    SELECT COUNT(TICKET.ticket_id) AS Total_tickets_sold, SUM(CASE WHEN TICKET.checked_in = 1 THEN 1 ELSE 0 END) AS Total_checked_in
    FROM EXHIBITION
    LEFT JOIN EVENT ON EXHIBITION.exhibition_id = EVENT.exhibition_id
    LEFT JOIN TICKET ON EVENT.event_id = TICKET.event_id
    WHERE EXHIBITION.title = p_title
    GROUP BY EXHIBITION.exhibition_id;
END$$


--Outputs all exhibitions curated by specified staff member
CREATE PROCEDURE GetCuratorPortfolio(
    IN p_id int
)
BEGIN
    SELECT EXHIBITION.exhibition_id, EXHIBITION.title, EXHIBITION.start_date, EXHIBITION.end_date
    FROM STAFF
    LEFT JOIN EXHIBITION ON STAFF.staff_id = EXHIBITION.curator_id
    WHERE STAFF.staff_id = p_id
    ORDER BY EXHIBITION.start_date;
END$$


--Gets all exhibitions in order of start date
CREATE PROCEDURE GetExhibitionTimeline()
BEGIN
    SELECT EXHIBITION.exhibition_id, EXHIBITION.title, EXHIBITION.start_date, EXHIBITION.end_date
    FROM EXHIBITION
    ORDER BY EXHIBITION.start_date;
END$$


DELIMITER ;