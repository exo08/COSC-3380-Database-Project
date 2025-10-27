USE u452501794_MuseumDB;

DELIMITER $$


--Shows all upcoming events, their dates, locations, capacity, and tickets sold
CREATE PROCEDURE GetUpcomingEvents()
BEGIN
    SELECT EVENT.event_id, EVENT.name, EVENT.event_date, LOCATION.name, EVENT.capacity, COUNT(TICKET.ticket_id) AS number_tickets_sold
    FROM EVENT
    LEFT JOIN LOCATION ON EVENT.location_id = LOCATION.location_id
    LEFT JOIN TICKET ON EVENT.event_id = TICKET.event_id
    WHERE EVENT.event_date >= CURDATE()
    GROUP BY EVENT.event_id, EVENT.name, EVENT.event_date, LOCATION.name, EVENT.capacity
    ORDER BY EVENT.event_date;
END$$

DELIMITER ;