USE museumdb;

DELIMITER $$
CREATE PROCEDURE CreateArtist(
  IN p_first_name varchar(255),
  IN p_last_name varchar(255),
  IN p_birth_year int,
  IN p_death_year int,
  IN p_nationality varchar(255),
  IN p_bio text,
  OUT p_artist_id int
)
-- Changed artist_id to auto increment instead of manually assigning IDs
BEGIN
  INSERT INTO ARTIST (first_name, last_name, birth_year, death_year, nationality, bio)
  VALUES (p_first_name, p_last_name, p_birth_year, p_death_year, p_nationality, p_bio);
  SET p_artist_id = LAST_INSERT_ID(); 
END$$

CREATE PROCEDURE CreateArtwork(
  IN p_title varchar(255),
  IN p_creation_year int,
  IN p_medium int,
  IN p_height decimal,
  IN p_width decimal,
  IN p_depth decimal,
  IN p_is_owned bool,
  IN p_location_id int,
  IN p_description text,
  OUT p_artwork_id int
)

BEGIN
  INSERT INTO ARTWORK(title, creation_year, medium, height, width, depth, is_owned, location_id, description)
  VALUES (p_title, p_creation_year, p_medium, p_height, p_width, p_depth, p_is_owned, p_location_id, p_description);
  SET p_artwork_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateExhibition(
  IN p_title varchar(255),
  IN p_start_date date,
  IN p_end_date date,
  IN p_curator_id int,
  IN p_description text,
  IN p_theme_sponsor varchar(255),
  OUT p_exhibition_id int
)

BEGIN
  INSERT INTO EXHIBITION(title, start_date, end_date, curator_id, description, theme_sponsor)
  VALUES(p_title, p_start_date, p_end_date, p_curator_id, p_description, p_theme_sponsor)
  SET p_exhibition_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateLocation(
  IN p_location_type smallint,
  IN p_name varchar(255),
  OUT p_location_id
)

BEGIN 
  INSERT INTO LOCATION(location_type, name)
  VALUES(p_location_type, p_name)
  SET p_location_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateDonor(
  IN p_first_name varchar(255),
  IN p_last_name varchar(255),
  IN p_organization_name varchar(255),
  IN p_is_organization bool,
  IN p_address varchar(255),
  IN p_email varchar(255),
  IN p_phone varchar(255)
  OUT p_donor_id int
)

BEGIN
  INSERT INTO DONOR(first_name, last_name, organization_name, is_organization, address, email, phone)
  VALUES(p_first_name, p_last_name, p_organization_name, p_is_organization, p_address, p_email, p_phone)
  SET p_donor_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateDonation(
  IN p_donor_id int,
  IN p_amount decimal,
  IN p_donation_date date,
  IN p_purpose smallint,
  IN p_acquisition_id int,
  OUT p_donation_id
)

BEGIN
  INSERT INTO DONATION(donor_id, amount, donation_date, purpose, acquisition_id)
  VALUES(p_donor_id, p_amount, p_donation_date, p_purpose, p_acquisition_id)
  SET p_donation_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateAcquisition(
  IN p_artwork_id int,
  IN p_acquisition_date date,
  IN p_price_value decimal,
  IN p_source_name varchar(255),
  IN p_method smallint,
  OUT p_acquisition_id int
)

BEGIN
  INSERT INTO ACQUISITION(artwork_id, acquisition_date, price_value, source_name, method)
  VALUES(p_artwork_id, p_acquisition_date, p_price_value, p_source_name, p_method)
  SET p_acquisition_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateMember(
  IN p_first_name varchar(255),
  IN p_last_name varchar(255),
  IN p_email varchar(255),
  IN p_phone varchar(255),
  IN p_address varchar(255),
  IN p_membership_type smallint,
  IN p_is_student bool,
  IN p_start_date date,
  IN p_expiration_date date,
  IN p_auto_renew bool,
  OUT p_member_id int
)

BEGIN
  INSERT INTO MEMBER(first_name, last_name, email, phone, address, membership_type, is_student, start_date, expiration_date, auto_renew)
  VALUES(p_first_name, p_last_name, p_email, p_phone, p_address, p_membership_type, p_is_student, p_start_date, p_expiration_date, p_auto_renew)
  SET p_member_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateVisitor(
  IN p_first_name varchar(255),
  IN p_last_name varchar(255),
  IN p_is_student bool,
  IN p_email varchar(255),
  IN p_phone varchar(255),
  IN p_created_at date,
  OUT p_visitor_id int
)

BEGIN
  INSERT INTO VISITOR(first_name, last_name, is_student, email, phone, created_at)
  VALUES(p_first_name, p_last_name, p_is_student, p_email, p_phone, p_created_at)
  SET p_visitor_id = LAST_INSERT_ID();  
END$$

CREATE PROCEDURE CreateTicket(
  IN p_event_id int,
  IN p_visitor_id int,
  IN p_member_id int,
  IN p_purchase_date date,
  IN p_quantity int,
  IN p_checked_in bool,
  IN p_check_in_time datetime,
  OUT p_ticket_id int
)

BEGIN
  INSERT INTO TICKET(event_id, visitor_id, member_id, purchase_date, quantity, checked_in, check_in_time)
  VALUES(p_event_id, p_visitor_id, p_member_id, p_purchase_date, p_quantity, p_checked_in, p_check_in_time)
  SET p_ticket_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateEvent(
  IN p_name varchar(255),
  IN p_description varchar(255),
  IN p_event_date date,
  IN p_location_id int,
  IN p_exhibition_id int,
  IN p_capacity int,
  OUT p_event_id int
)

BEGIN
  INSERT INTO EVENT(name, description, event_date, location_id, exhibition_id, capacity)
  VALUES(p_name, p_description, p_event_date, p_location_id, p_exhibition_id, p_capacity)
  SET p_event_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateShopItem(
  IN p_name varchar(255),
  IN p_description text,
  IN p_category varchar(255),
  IN p_price decimal(6,2),
  IN p_quantity_in_stock int,
  OUT p_item_id int
)

BEGIN
  INSERT INTO SHOP_ITEM(name, description, category, price, quantity_in_stock)
  VALUES(p_name, p_description, p_category, p_price, p_quantity_in_stock)
  SET p_item_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateSale(
  IN p_sale_date datetime,
  IN p_member_id int,
  IN p_visitor_id int,
  IN p_total_amount decimal(6,2),
  IN p_discount_amount decimal(4,2),
  IN p_payment_method int,
  OUT p_sale_id int
)

BEGIN
  INSERT INTO SALE(sale_date, member_id, visitor_id, total_amount, discount_amount, payment_method)
  VALUES(p_sale_date, p_member_id, p_visitor_id, p_total_amount, p_discount_amount, p_payment_method)
  SET p-sale_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE CreateStaff(
  IN p_ssn int,
  IN p_department_id int,
  IN p_name varchar(255),
  IN p_email varchar(255),
  IN p_title varchar(255),
  IN p_hire_date date,
  IN p_supervisor_id int,
  OUT p_staff_id int
)

BEGIN
  INSERT INTO STAFF(ssn, department_id, name, email, title, hire_date, supervisor_id)
  VALUES(p_ssn, p_department_id, p_name, p_email, p_title, p_hire_date, p_supervisor_id)
  SET p_staff_id = LAST_INSERT_ID();
END$$

DELIMITER ;
