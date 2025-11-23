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
        -- Get member's expiration date
        SELECT expiration_date INTO member_expiration
        FROM MEMBER
        WHERE member_id = NEW.member_id;
        
        -- check if member exists and is expired
        IF member_expiration IS NOT NULL THEN
            IF member_expiration < CURDATE() THEN
                -- Member is EXPIRED will NOT get discount
                SET validation_message = CONCAT(
                    'WARNING: Your membership expired on ', 
                    DATE_FORMAT(member_expiration, '%M %d, %Y'), 
                    '. You will NOT receive the member discount on this purchase. ',
                    'Please renew your membership to enjoy discount benefits.'
                );
                SET msg_type = 'warning';
                
                -- force discount to 0 since member is expired
                SET NEW.discount_amount = 0.00;
                -- Total stays as full price php sends the full subtotal
                
            ELSE
                -- member is ACTIVE will get discount
                SET validation_message = CONCAT(
                    'Member discount applied! Your membership is active until ',
                    DATE_FORMAT(member_expiration, '%M %d, %Y'),
                    '. Thank you for being a valued member!'
                );
                SET msg_type = 'info';
                
                -- keep the discount php calculated subtract discount from total
                SET NEW.total_amount = NEW.total_amount - NEW.discount_amount;
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
  DECLARE reorder_qty INT;
  
  -- reduce stock by quantity sold
  UPDATE SHOP_ITEM
  SET quantity_in_stock = quantity_in_stock - NEW.quantity
  WHERE item_id = NEW.item_id;
  
  -- get updated stock level and reorder parameters
  SELECT quantity_in_stock, reorder_threshold, reorder_quantity
  INTO new_stock_level, reorder_thresh, reorder_qty
  FROM SHOP_ITEM
  WHERE item_id = NEW.item_id;
  
  -- prevent negative stock for safety
  IF new_stock_level < 0 THEN
    UPDATE SHOP_ITEM
    SET quantity_in_stock = 0
    WHERE item_id = NEW.item_id;
    SET new_stock_level = 0;
  END IF;
  
  -- autoreorder logic: If stock falls below threshold automatically add reorder quantity
  IF new_stock_level <= reorder_thresh THEN
    UPDATE SHOP_ITEM
    SET quantity_in_stock = quantity_in_stock + reorder_qty,
        did_auto_reorder = TRUE,
        last_auto_reorder_date = NOW(),
        pending_reorder_quantity = reorder_qty
    WHERE item_id = NEW.item_id;
  END IF;
END$$



-- TRIGGER 2.1: clear reorder flag when stock is manually edited
CREATE TRIGGER trg_clear_auto_reorder_on_manual_update 
BEFORE UPDATE ON SHOP_ITEM
FOR EACH ROW BEGIN
  -- If staff is manually editing the item clear the auto-reorder flag as it's been acknowledged
  IF NEW.did_auto_reorder = 0 AND OLD.did_auto_reorder = 1 THEN
    SET NEW.pending_reorder_quantity = 0;
  END IF;
END$$

DELIMITER ;