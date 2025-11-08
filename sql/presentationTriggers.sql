CREATE TRIGGER `trg_reduce_stock_after_sale` AFTER INSERT ON `SALE_ITEM`
 FOR EACH ROW BEGIN
  UPDATE SHOP_ITEM
  SET quantity_in_stock = quantity_in_stock - NEW.quantity
  WHERE item_id = NEW.item_id;

  -- prevent negative stock
  UPDATE SHOP_ITEM
  SET quantity_in_stock = 0
  WHERE quantity_in_stock < 0;
END

CREATE TRIGGER `trg_validate_active_member_sale` BEFORE INSERT ON `SALE`
 FOR EACH ROW BEGIN
  IF NEW.member_id IS NOT NULL THEN
    IF NOT EXISTS (
      SELECT 1 FROM MEMBER 
      WHERE member_id = NEW.member_id 
      AND expiration_date >= CURDATE()
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Member account is expired. Cannot apply member benefits.';
    END IF;
  END IF;
END
