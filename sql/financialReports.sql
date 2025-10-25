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


--Gets all donations from a specified human donor
CREATE PROCEDURE GetHumanDonorSummary(
    IN p_first_name varchar(255),
    IN p_last_name varchar(255),
    IN p_email varchar(255)
)
BEGIN
    SELECT DONATION.donation_id, DONATION.donation_date, DONATION.amount, DONATION.purpose
    FROM DONOR
    LEFT JOIN DONATION ON DONOR.donor_id = DONATION.donor_id
    WHERE DONOR.first_name = p_first_name AND DONOR.last_name = p_last_name AND DONOR.email = p_email
    ORDER BY DONATION.donation_date;
END$$


--Gets all donations from a specified organization
CREATE PROCEDURE GetOrgDonorSummary(
    IN p_organization_name varchar(255),
    IN p_email varchar(255)
)
BEGIN
    SELECT DONATION.donation_id, DONATION.donation_date, DONATION.amount, DONATION.purpose
    FROM DONOR
    LEFT JOIN DONATION ON DONOR.donor_id = DONATION.donor_id
    WHERE DONOR.organization_name = p_organization_name AND DONOR.email = p_email
    ORDER BY DONATION.donation_date;
END$$

DELIMITER ;