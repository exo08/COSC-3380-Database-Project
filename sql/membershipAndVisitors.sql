USING museumdb

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

DELIMITER ;