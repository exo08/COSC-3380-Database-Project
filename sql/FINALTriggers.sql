-- Trigger 1: Check stock availability and warn when low
-- This prevents sales when stock is insufficient and warns when stock is low
CREATE TRIGGER `trg_validate_stock_before_sale` BEFORE INSERT ON `SALE_ITEM`
FOR EACH ROW BEGIN
  DECLARE current_stock INT;
  DECLARE item_name_var VARCHAR(255);
  
  -- Get current stock level and item name
  SELECT quantity_in_stock, item_name 
  INTO current_stock, item_name_var
  FROM SHOP_ITEM
  WHERE item_id = NEW.item_id;
  
  -- Check if sufficient stock is available
  IF current_stock < NEW.quantity THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Insufficient stock for this item. Please check availability.';
  END IF;
  
  -- Warning for low stock (less than 10 items remaining after sale)
  IF (current_stock - NEW.quantity) < 10 AND (current_stock - NEW.quantity) >= 0 THEN
    -- Note: MySQL triggers can't directly display warnings to users in the same way
    -- as SIGNAL, but we can log this or handle it in application layer
    -- For now, we'll allow the sale to proceed
    -- Consider adding to ACTIVITY_LOG or a separate LOW_STOCK_ALERT table
    SET @low_stock_warning = CONCAT('Warning: ', item_name_var, ' is low on stock. Only ', 
                                     (current_stock - NEW.quantity), ' remaining.');
  END IF;
END;

-- Trigger 2: Validate member benefits but allow expired members to purchase
-- This checks membership status only if trying to apply a discount
CREATE TRIGGER `trg_validate_member_discount` BEFORE INSERT ON `SALE`
FOR EACH ROW BEGIN
  DECLARE member_is_active BOOLEAN DEFAULT FALSE;
  
  -- Only validate if this is a member sale WITH a discount
  IF NEW.member_id IS NOT NULL AND NEW.discount_amount > 0 THEN
    -- Check if membership is currently active
    SELECT EXISTS (
      SELECT 1 FROM MEMBER 
      WHERE member_id = NEW.member_id 
      AND expiration_date >= CURDATE()
    ) INTO member_is_active;
    
    -- If member is expired but trying to use discount, reject the discount
    IF NOT member_is_active THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Member account is expired. Discount cannot be applied. Member may purchase at regular price.';
    END IF;
  END IF;
  
  -- If member_id is set but discount_amount is NULL or 0, sale proceeds normally
  -- If member_id is NULL (visitor purchase), sale proceeds normally
END;
