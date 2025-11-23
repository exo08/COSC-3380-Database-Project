-- new table to store validation messages for sales
-- used by trigger to log messages about member discount status
CREATE TABLE IF NOT EXISTS SALE_VALIDATION_MESSAGES (
    validation_id INT AUTO_INCREMENT PRIMARY KEY,
    session_key VARCHAR(100) UNIQUE NOT NULL,
    member_id INT,
    message TEXT,
    message_type ENUM('warning', 'error', 'info') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Business Rules:
-- 1. Member discount validation before sale 
-- 2. Automatic reorder system based on minimum stock thresholds
    -- 2.1 clear reorder flag when stock replenished above threshold


-- TRIGGER 1: meber discount validation before sale
-- EVENT: BEFORE INSERT ON SALE (before a sale is recorded)
-- CONDITION: IF member_id is provided, check membership status
-- ACTION: log message about discount eligibility and send warning to app if expired for user to see

DELIMITER $$

CREATE TRIGGER trg_check_member_discount_before_sale
BEFORE INSERT ON SALE
FOR EACH ROW
BEGIN
    DECLARE member_expiration DATE;
    DECLARE validation_message TEXT;
    DECLARE msg_type VARCHAR(20);
    
    -- only check if this is a member sale
    IF NEW.member_id IS NOT NULL THEN
        -- get members expiration date
        SELECT expiration_date INTO member_expiration
        FROM MEMBER
        WHERE member_id = NEW.member_id;
        
        -- check if member exists and is expired
        IF member_expiration IS NOT NULL THEN
            IF member_expiration < CURDATE() THEN
                -- member is EXPIRED will NOT get discount
                -- warning message will be caught on app side
                SET validation_message = CONCAT(
                    'WARNING: Your membership expired on ', 
                    DATE_FORMAT(member_expiration, '%M %d, %Y'), 
                    '. You will NOT receive the member discount on this purchase. ',
                    'Please renew your membership to enjoy discount benefits.'
                );
                SET msg_type = 'warning';
                
                -- force discount to 0 since member is expired
                SET NEW.discount_amount = 0.00;
            ELSE
                -- member is ACTIVE will get discount
                SET validation_message = CONCAT(
                    'Member discount applied! Your membership is active until ',
                    DATE_FORMAT(member_expiration, '%M %d, %Y'),
                    '. Thank you for being a valued member!'
                );
                SET msg_type = 'info';
            END IF;
        ELSE
            -- member not found
            SET validation_message = 'Member account not found. Standard pricing will apply.';
            SET msg_type = 'warning';
            SET NEW.discount_amount = 0.00;
        END IF;
    ELSE
        -- not a member sale
        SET validation_message = 'Guest checkout - no member discount available.';
        SET msg_type = 'info';
        SET NEW.discount_amount = 0.00;
    END IF;
    
    -- store the message for app to retrieve after sale is processed
    -- we'll use a combination of member_id and timestamp as a session key
    IF validation_message IS NOT NULL THEN
        INSERT INTO SALE_VALIDATION_MESSAGES (session_key, member_id, message, message_type)
        VALUES (
            CONCAT('sale_', IFNULL(NEW.member_id, 0), '_', UNIX_TIMESTAMP()),
            NEW.member_id,
            validation_message,
            msg_type
        )
        ON DUPLICATE KEY UPDATE 
            message = VALUES(message),
            message_type = VALUES(message_type),
            created_at = CURRENT_TIMESTAMP;
    END IF;
END$$

-- TRIGGER 2: reduce stock and flag for reorder after sale
-- EVENT: AFTER INSERT ON SALE_ITEM (after a sale is completed)
-- CONDITION: IF stock falls below reorder_threshold after reducing
-- ACTION: Mark item as needs_reorder and set alert timestamp

CREATE TRIGGER trg_reduce_stock_after_sale 
AFTER INSERT ON SALE_ITEM
FOR EACH ROW
BEGIN
  -- declare variables
  DECLARE new_stock_level INT;
  DECLARE reorder_thresh INT;
  
  -- reduce stock by quantity sold
  UPDATE SHOP_ITEM
  SET quantity_in_stock = quantity_in_stock - NEW.quantity
  WHERE item_id = NEW.item_id;
  
  -- get updated stock level and reorder threshold
  SELECT quantity_in_stock, reorder_threshold
  INTO new_stock_level, reorder_thresh
  FROM SHOP_ITEM
  WHERE item_id = NEW.item_id;
  
  -- prevent negative stock for safety
  IF new_stock_level < 0 THEN
    UPDATE SHOP_ITEM
    SET quantity_in_stock = 0
    WHERE item_id = NEW.item_id;
    SET new_stock_level = 0;
  END IF;
  
  -- check if stock has fallen below reorder threshold
  IF new_stock_level <= reorder_thresh THEN
    -- flag item for reorder
    UPDATE SHOP_ITEM
    SET needs_reorder = TRUE,
        last_reorder_alert = NOW()
    WHERE item_id = NEW.item_id 
    AND needs_reorder = FALSE;  -- Only update if not already flagged
  END IF;
END$$



-- TRIGGER 2.1: clear reorder flag when stock is replenished
-- EVENT: AFTER UPDATE ON SHOP_ITEM (when inventory is restocked)
-- CONDITION: IF quantity_in_stock increased AND now exceeds reorder_quantity
-- ACTION: Clear the needs_reorder flag

CREATE TRIGGER trg_clear_reorder_flag 
AFTER UPDATE ON SHOP_ITEM
FOR EACH ROW
BEGIN
  -- check if stock increased and exceeds threshold
  IF NEW.quantity_in_stock > OLD.quantity_in_stock THEN
    -- If stock is now above reorder_quantity, clear the reorder flag
    IF NEW.quantity_in_stock >= (NEW.reorder_quantity) THEN
      -- clear reorder flag
      UPDATE SHOP_ITEM
      SET needs_reorder = FALSE
      WHERE item_id = NEW.item_id;
    END IF;
  END IF;
END$$

DELIMITER ;


-- =========================================================
-- Query for shop staff dashboard to show items needing reorder
-- =========================================================
/*
SELECT 
  item_id,
  item_name,
  quantity_in_stock,
  reorder_threshold,
  reorder_quantity,
  last_reorder_alert,
  DATEDIFF(NOW(), last_reorder_alert) AS days_since_alert
FROM SHOP_ITEM
WHERE needs_reorder = TRUE
ORDER BY quantity_in_stock ASC, last_reorder_alert ASC;
*/