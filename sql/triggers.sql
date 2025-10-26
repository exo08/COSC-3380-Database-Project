-- =========================================================
-- Museum Database - Trigger Definitions
-- =========================================================
-- These triggers automate key database behaviors:
-- 1. Mark artworks as owned when acquired
-- 2. Decrement shop inventory after sale
-- 3. Extend memberships that are auto-renewed
-- 4. Update donation acquisition links
-- 5. Ensure exhibitions can’t exceed end date
-- =========================================================

DELIMITER $$

-- =========================================================
-- 1. Mark an artwork as owned once acquired
-- =========================================================
CREATE TRIGGER trg_artwork_owned_after_acquisition
AFTER INSERT ON ACQUISITION
FOR EACH ROW
BEGIN
  UPDATE ARTWORK
  SET is_owned = TRUE
  WHERE artwork_id = NEW.artwork_id;
END$$


-- =========================================================
-- 2. Reduce shop item stock after a sale
-- =========================================================
CREATE TRIGGER trg_reduce_stock_after_sale
AFTER INSERT ON SALE_ITEM
FOR EACH ROW
BEGIN
  UPDATE SHOP_ITEM
  SET quantity_in_stock = quantity_in_stock - NEW.quantity
  WHERE item_id = NEW.item_id;

  -- Optionally prevent negative stock
  UPDATE SHOP_ITEM
  SET quantity_in_stock = 0
  WHERE quantity_in_stock < 0;
END$$


-- =========================================================
-- 3. Auto-renew membership on sale if eligible
-- =========================================================
CREATE TRIGGER trg_auto_renew_membership
AFTER INSERT ON SALE
FOR EACH ROW
BEGIN
  IF NEW.member_id IS NOT NULL THEN
    UPDATE MEMBER
    SET expiration_date = DATE_ADD(expiration_date, INTERVAL 1 YEAR)
    WHERE member_id = NEW.member_id
      AND auto_renew = TRUE;
  END IF;
END$$


-- =========================================================
-- 4. If a donation is linked to an acquisition,
--    mark that artwork as owned (in case it wasn’t)
-- =========================================================
CREATE TRIGGER trg_donation_acquisition_sync
AFTER INSERT ON DONATION
FOR EACH ROW
BEGIN
  IF NEW.acquisition_id IS NOT NULL THEN
    UPDATE ARTWORK
    SET is_owned = TRUE
    WHERE artwork_id = (
      SELECT artwork_id FROM ACQUISITION
      WHERE acquisition_id = NEW.acquisition_id
    );
  END IF;
END$$


-- =========================================================
-- 5. Enforce exhibition date consistency
--    (no end_date before start_date)
-- =========================================================
CREATE TRIGGER trg_check_exhibition_dates
BEFORE INSERT ON EXHIBITION
FOR EACH ROW
BEGIN
  IF NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'End date cannot be earlier than start date.';
  END IF;
END$$

DELIMITER ;

-- =========================================================
-- 6. Prevent overbooking of events
-- =========================================================
DELIMITER $$

CREATE TRIGGER trg_prevent_event_overbooking
BEFORE INSERT ON TICKET
FOR EACH ROW
BEGIN
  DECLARE current_total INT DEFAULT 0;
  DECLARE max_capacity INT DEFAULT 0;

  -- Get current total tickets sold for this event
  SELECT IFNULL(SUM(quantity), 0)
  INTO current_total
  FROM TICKET
  WHERE event_id = NEW.event_id;

  -- Get the event’s capacity
  SELECT capacity
  INTO max_capacity
  FROM EVENT
  WHERE event_id = NEW.event_id;

  -- If adding new tickets would exceed capacity, block the insert
  IF (current_total + NEW.quantity) > max_capacity THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = CONCAT(
        'Overbooking prevented: Event ID ',
        NEW.event_id,
        ' is already at or above capacity (',
        max_capacity, ' tickets).'
      );
  END IF;
END$$

DELIMITER ;
