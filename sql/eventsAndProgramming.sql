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


--This takes an event id and gets the number of ticket holders who were present vs absent
CREATE PROCEDURE GetEventAttendance(
    IN p_id INT
)
BEGIN
    SELECT 
        e.name, 
        SUM(CASE WHEN t.checked_in = 1 THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN t.checked_in = 0 THEN 1 ELSE 0 END) AS absent
    FROM `EVENT` AS e
    LEFT JOIN `TICKET` AS t 
        ON e.event_id = t.event_id
    WHERE e.event_id = p_id
    GROUP BY e.name;
END$$


--Gets all events where tickets are almost sold out (capacity-10 tickets at least)
CREATE PROCEDURE GetEventsNearCapacity()
BEGIN
    SELECT EVENT.event_id, EVENT.name, COUNT(TICKET.ticket_id) AS tickets_sold, EVENT.capacity
    FROM EVENT
    LEFT JOIN TICKET ON EVENT.event_id = TICKET.event_id
    GROUP BY EVENT.event_id, EVENT.name, EVENT.capacity
    HAVING COUNT(TICKET.ticket_id) >= EVENT.capacity-10;
END$$

DELIMITER ;