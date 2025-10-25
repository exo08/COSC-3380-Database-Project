USING museumdb

DELIMITER $$

CREATE PROCEDURE GetCurrentAndUpcomingExhibitions()
BEGIN
    SELECT EXHIBITION.exhibition_id, EXHIBITION.title, EXHIBITION.start_date, EXHIBITION.end_date
    FROM EXHIBITION
    WHERE EXHIBITION.end_date >= CURDATE()
    ORDER BY EXHIBITION.start_date;
END$$

DELIMITER ;