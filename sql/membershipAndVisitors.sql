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


DELIMITER ;