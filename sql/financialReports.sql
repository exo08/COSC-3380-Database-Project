USING museumdb

DELIMITER $$


--Will return all aquisitions, their artist, date of acquisition, and price
CREATE PROCEDURE GetAcquisitionHistory()
BEGIN
    SELECT ACQUISITION.acquisition_id, ARTWORK.title, ARTIST.first_name, ARTIST.last_name, ACQUISITION.price_value, ACQUISITION.acquisition_date
    FROM ACQUISITION
    LEFT JOIN ARTWORK ON ACQUISITION.artwork_id = ARTWORK.artwork_id
    LEFT JOIN ARTWORK_CREATOR ON ARTWORK.artwork_id = ARTWORK_CREATOR.artwork_id
    LEFT JOIN ARTIST ON ARTWORK_CREATOR.artist_id = ARTIST.artist_id
    ORDER BY ACQUISITION.acquisition_date;
END$$

DELIMITER ;