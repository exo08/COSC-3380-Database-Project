USE u452501794_MuseumDB;

DELIMITER $$


--shows number of different kinds of active memberships
CREATE PROCEDURE GetNumberActiveMemberships()
BEGIN
    SELECT MEMBER.membership_type, COUNT(MEMBER.member_id) AS total_members, MEMBER.is_student
    FROM MEMBER
    WHERE MEMBER.expiration_date >= CURDATE()
    GROUP BY MEMBER.membership_type, MEMBER.is_student
    ORDER BY MEMBER.membership_type, MEMBER.is_student;
END$$


--Gets all members who's memberships expire in 7 days or less AND they do not have auto-renew
CREATE PROCEDURE GetExpiringMembers()
BEGIN
    SELECT MEMBER.member_id, MEMBER.first_name, MEMBER.last_name, MEMBER.email, MEMBER.membership_type
    FROM MEMBER
    WHERE MEMBER.auto_renew = 0 AND DATEDIFF(MEMBER.expiration_date, CURDATE()) BETWEEN 0 AND 7
    ORDER BY MEMBER.member_id;
END$$


--returns how many tickets sold to members who are students/not students and visitors who are students/not students
CREATE PROCEDURE GetDemographics()
BEGIN
    SELECT 'Visitor' AS customer_type, VISITOR.is_student, COUNT(TICKET.ticket_id) AS total_tickets, SUM(TICKET.quantity) AS total_attendees
    FROM TICKET
    INNER JOIN VISITOR ON TICKET.visitor_id = VISITOR.visitor_id
    GROUP BY VISITOR.is_student
    
    UNION ALL
    
    SELECT 'Member' AS customer_type, MEMBER.is_student, COUNT(TICKET.ticket_id) AS total_tickets, SUM(TICKET.quantity) AS total_attendees
    FROM TICKET
    INNER JOIN MEMBER ON TICKET.member_id = MEMBER.member_id
    GROUP BY MEMBER.is_student
    
    ORDER BY customer_type, is_student;
END$$


--will return percentages of how many visitors come back for more than just one visit
--example output:
--| visit_frequency | number_of_visitors | percentage |
--|-----------------|--------------------|-----------| 
--| 1 visit         | 375                | 75.00      |
--| 2 visits        | 80                 | 16.00      |
--| 3 visits        | 25                 | 5.00       |
--| 4 visits        | 12                 | 2.40       |
--| 5 visits        | 5                  | 1.00       |
--| 6+ visits       | 3                  | 0.60       |
CREATE PROCEDURE GetVisitorFrequencyAnalysis()
BEGIN
    SELECT CASE
        WHEN visit_count = 1 THEN '1 visit'
        WHEN visit_count = 2 THEN '2 visits'
        WHEN visit_count = 3 THEN '3 visits'
        WHEN visit_count = 4 THEN '4 visits'
        WHEN visit_count = 5 THEN '5 visits'
        WHEN visit_count >= 6 THEN '6+ visits'
    END AS visit_frequency,
    COUNT(visitor_visits.visitor_id) AS number_of_visitors,
    ROUND(COUNT(visitor_visits.visitor_id)*100/(SELECT COUNT(DISTINCT visitor_id) FROM TICKET WHERE visitor_id IS NOT NULL), 2) AS percentage
    FROM (
        SELECT TICKET.visitor_id, COUNT(TICKET.ticket_id) AS visit_count
        FROM TICKET
        WHERE TICKET.visitor_id IS NOT NULL
        GROUP BY TICKET.visitor_id
    ) AS visitor_visits
    GROUP BY CASE
        WHEN visit_count = 1 THEN '1 visit'
        WHEN visit_count = 2 THEN '2 visits'
        WHEN visit_count = 3 THEN '3 visits'
        WHEN visit_count = 4 THEN '4 visits'
        WHEN visit_count = 5 THEN '5 visits'
        WHEN visit_count >= 6 THEN '6+ visits'
    END
    ORDER BY MIN(visit_count);
END$$

DELIMITER ;