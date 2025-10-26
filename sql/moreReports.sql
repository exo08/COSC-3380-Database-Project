USE u452501794_MuseumDB;

DELIMITER $$

-- =====================================================
-- CURATOR REPORT PROCEDURES (for curator_reports.php)
-- =====================================================

-- Aggregate report: Artworks grouped by artist with counts
CREATE PROCEDURE ArtworksByArtist()
BEGIN
    SELECT 
        CONCAT(ARTIST.first_name, ' ', ARTIST.last_name) AS artist_name,
        ARTIST.nationality,
        ARTIST.birth_year,
        ARTIST.death_year,
        COUNT(DISTINCT ARTWORK.artwork_id) AS artwork_count,
        GROUP_CONCAT(DISTINCT ARTWORK.title ORDER BY ARTWORK.title SEPARATOR ', ') AS sample_titles
    FROM ARTIST
    LEFT JOIN ARTWORK_CREATOR ON ARTIST.artist_id = ARTWORK_CREATOR.artist_id
    LEFT JOIN ARTWORK ON ARTWORK_CREATOR.artwork_id = ARTWORK.artwork_id
    GROUP BY ARTIST.artist_id, ARTIST.first_name, ARTIST.last_name, 
             ARTIST.nationality, ARTIST.birth_year, ARTIST.death_year
    HAVING artwork_count > 0
    ORDER BY artist_name;
END$$

-- Aggregate report: Artworks grouped by time period
CREATE PROCEDURE ArtworksByPeriod()
BEGIN
    SELECT 
        CASE 
            WHEN creation_year < 1400 THEN 'Medieval (Before 1400)'
            WHEN creation_year BETWEEN 1400 AND 1600 THEN 'Renaissance (1400-1600)'
            WHEN creation_year BETWEEN 1600 AND 1750 THEN 'Baroque (1600-1750)'
            WHEN creation_year BETWEEN 1750 AND 1850 THEN 'Neoclassical/Romantic (1750-1850)'
            WHEN creation_year BETWEEN 1850 AND 1900 THEN 'Modern (1850-1900)'
            WHEN creation_year BETWEEN 1900 AND 1945 THEN 'Early 20th Century (1900-1945)'
            WHEN creation_year BETWEEN 1945 AND 2000 THEN 'Post-War (1945-2000)'
            WHEN creation_year >= 2000 THEN 'Contemporary (2000+)'
            ELSE 'Unknown Period'
        END AS time_period,
        MIN(creation_year) AS earliest_year,
        MAX(creation_year) AS latest_year,
        COUNT(*) AS artwork_count,
        SUM(CASE WHEN is_owned = 1 THEN 1 ELSE 0 END) AS owned_count,
        SUM(CASE WHEN is_owned = 0 THEN 1 ELSE 0 END) AS loaned_count
    FROM ARTWORK
    WHERE creation_year IS NOT NULL
    GROUP BY time_period
    ORDER BY earliest_year;
END$$

-- Aggregate report: Artworks grouped by medium
CREATE PROCEDURE ArtworksByMedium()
BEGIN
    SELECT 
        CASE medium
            WHEN 1 THEN 'Oil Painting'
            WHEN 2 THEN 'Watercolor'
            WHEN 3 THEN 'Acrylic'
            WHEN 4 THEN 'Sculpture'
            WHEN 5 THEN 'Photography'
            WHEN 6 THEN 'Drawing'
            WHEN 7 THEN 'Mixed Media'
            WHEN 8 THEN 'Digital Art'
            WHEN 9 THEN 'Printmaking'
            WHEN 10 THEN 'Textile'
            ELSE 'Other'
        END AS medium_name,
        COUNT(*) AS artwork_count,
        SUM(CASE WHEN is_owned = 1 THEN 1 ELSE 0 END) AS owned_count,
        AVG(height * width) AS avg_size_cm2
    FROM ARTWORK
    WHERE medium IS NOT NULL
    GROUP BY medium
    ORDER BY artwork_count DESC;
END$$

-- =====================================================
-- EVENT REPORT PROCEDURES (for event_reports.php)
-- =====================================================

-- Report: Event attendance summary
CREATE PROCEDURE EventAttendanceReport()
BEGIN
    SELECT 
        EVENT.event_id,
        EVENT.name AS event_name,
        EVENT.event_date,
        EVENT.capacity,
        COUNT(DISTINCT TICKET.ticket_id) AS tickets_sold,
        SUM(TICKET.quantity) AS total_attendees,
        SUM(CASE WHEN TICKET.checked_in = 1 THEN TICKET.quantity ELSE 0 END) AS checked_in_count,
        CASE 
            WHEN COUNT(DISTINCT TICKET.ticket_id) = 0 THEN 0
            ELSE ROUND((SUM(CASE WHEN TICKET.checked_in = 1 THEN TICKET.quantity ELSE 0 END) / SUM(TICKET.quantity)) * 100, 2)
        END AS check_in_rate,
        LOCATION.name AS location_name
    FROM EVENT
    LEFT JOIN TICKET ON EVENT.event_id = TICKET.event_id
    LEFT JOIN LOCATION ON EVENT.location_id = LOCATION.location_id
    WHERE EVENT.event_date < CURDATE()
    GROUP BY EVENT.event_id, EVENT.name, EVENT.event_date, EVENT.capacity, LOCATION.name
    ORDER BY EVENT.event_date DESC;
END$$

-- Report: Tickets sold vs capacity for a specific event
CREATE PROCEDURE TicketsSoldVsCapacity(
    IN p_event_id INT
)
BEGIN
    SELECT 
        EVENT.event_id,
        EVENT.name AS event_name,
        EVENT.event_date,
        EVENT.capacity,
        COALESCE(SUM(TICKET.quantity), 0) AS tickets_sold,
        EVENT.capacity - COALESCE(SUM(TICKET.quantity), 0) AS tickets_remaining,
        ROUND((COALESCE(SUM(TICKET.quantity), 0) / EVENT.capacity) * 100, 2) AS percent_sold,
        CASE 
            WHEN COALESCE(SUM(TICKET.quantity), 0) >= EVENT.capacity THEN 'SOLD OUT'
            WHEN COALESCE(SUM(TICKET.quantity), 0) >= EVENT.capacity * 0.9 THEN 'NEARLY FULL'
            ELSE 'AVAILABLE'
        END AS status
    FROM EVENT
    LEFT JOIN TICKET ON EVENT.event_id = TICKET.event_id
    WHERE EVENT.event_id = p_event_id
    GROUP BY EVENT.event_id, EVENT.name, EVENT.event_date, EVENT.capacity;
END$$

-- Report: Member vs Visitor ticket purchases by date range
CREATE PROCEDURE MemberVsVisitorAdmissions(
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT 
        DATE(TICKET.purchase_date) AS purchase_date,
        SUM(CASE WHEN TICKET.member_id IS NOT NULL THEN TICKET.quantity ELSE 0 END) AS member_tickets,
        SUM(CASE WHEN TICKET.visitor_id IS NOT NULL THEN TICKET.quantity ELSE 0 END) AS visitor_tickets,
        SUM(TICKET.quantity) AS total_tickets,
        ROUND((SUM(CASE WHEN TICKET.member_id IS NOT NULL THEN TICKET.quantity ELSE 0 END) / SUM(TICKET.quantity)) * 100, 2) AS member_percentage
    FROM TICKET
    WHERE DATE(TICKET.purchase_date) BETWEEN p_start_date AND p_end_date
    GROUP BY DATE(TICKET.purchase_date)
    ORDER BY purchase_date;
END$$

-- Report: Upcoming events with ticket availability
CREATE PROCEDURE GetUpcomingEvents()
BEGIN
    SELECT 
        EVENT.event_id,
        EVENT.name AS event_name,
        EVENT.description,
        EVENT.event_date,
        EVENT.capacity,
        COALESCE(SUM(TICKET.quantity), 0) AS tickets_sold,
        EVENT.capacity - COALESCE(SUM(TICKET.quantity), 0) AS tickets_available,
        CASE 
            WHEN COALESCE(SUM(TICKET.quantity), 0) >= EVENT.capacity THEN 'SOLD OUT'
            WHEN COALESCE(SUM(TICKET.quantity), 0) >= EVENT.capacity * 0.9 THEN 'NEARLY FULL'
            ELSE 'AVAILABLE'
        END AS availability_status,
        LOCATION.name AS location_name,
        EXHIBITION.title AS exhibition_name
    FROM EVENT
    LEFT JOIN TICKET ON EVENT.event_id = TICKET.event_id
    LEFT JOIN LOCATION ON EVENT.location_id = LOCATION.location_id
    LEFT JOIN EXHIBITION ON EVENT.exhibition_id = EXHIBITION.exhibition_id
    WHERE EVENT.event_date >= CURDATE()
    GROUP BY EVENT.event_id, EVENT.name, EVENT.description, EVENT.event_date, 
             EVENT.capacity, LOCATION.name, EXHIBITION.title
    ORDER BY EVENT.event_date;
END$$

-- =====================================================
-- SHOP REPORT PROCEDURES (for shop_reports.php)
-- =====================================================

-- Report: Daily sales summary for a specific date
CREATE PROCEDURE DailySalesSummary(
    IN p_date DATE
)
BEGIN
    SELECT 
        DATE(SALE.sale_date) AS sale_date,
        COUNT(DISTINCT SALE.sale_id) AS total_transactions,
        SUM(SALE.total_amount) AS total_revenue,
        SUM(SALE.discount_amount) AS total_discounts,
        SUM(SALE.total_amount - SALE.discount_amount) AS net_revenue,
        AVG(SALE.total_amount) AS avg_transaction_value,
        COUNT(DISTINCT SALE.member_id) AS unique_members,
        COUNT(DISTINCT SALE.visitor_id) AS unique_visitors
    FROM SALE
    WHERE DATE(SALE.sale_date) = p_date
    GROUP BY DATE(SALE.sale_date);
END$$

-- Report: Top selling items within a time period
CREATE PROCEDURE TopSellingItems(
    IN p_days_back INT
)
BEGIN
    SELECT 
        SHOP_ITEM.item_id,
        SHOP_ITEM.item_name,
        SHOP_ITEM.category,
        SHOP_ITEM.price AS current_price,
        SHOP_ITEM.quantity_in_stock AS current_stock,
        COUNT(DISTINCT SALE_ITEM.sale_id) AS times_sold,
        SUM(SALE_ITEM.quantity) AS total_quantity_sold,
        SUM(SALE_ITEM.quantity * SALE_ITEM.price_at_sale) AS total_revenue
    FROM SHOP_ITEM
    INNER JOIN SALE_ITEM ON SHOP_ITEM.item_id = SALE_ITEM.item_id
    INNER JOIN SALE ON SALE_ITEM.sale_id = SALE.sale_id
    WHERE SALE.sale_date >= DATE_SUB(CURDATE(), INTERVAL p_days_back DAY)
    GROUP BY SHOP_ITEM.item_id, SHOP_ITEM.item_name, SHOP_ITEM.category, 
             SHOP_ITEM.price, SHOP_ITEM.quantity_in_stock
    ORDER BY total_revenue DESC
    LIMIT 20;
END$$

-- Report: Items with low stock
CREATE PROCEDURE GetLowStockAlerts(
    IN p_threshold INT
)
BEGIN
    SELECT 
        item_id,
        item_name,
        category,
        price,
        quantity_in_stock,
        CASE 
            WHEN quantity_in_stock = 0 THEN 'OUT OF STOCK'
            WHEN quantity_in_stock <= p_threshold THEN 'LOW STOCK'
            ELSE 'ADEQUATE'
        END AS stock_status
    FROM SHOP_ITEM
    WHERE quantity_in_stock <= p_threshold
    ORDER BY quantity_in_stock ASC, item_name;
END$$

-- Report: Revenue trends by date range
CREATE PROCEDURE RevenueByDateRange(
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT 
        DATE(SALE.sale_date) AS sale_date,
        COUNT(DISTINCT SALE.sale_id) AS transactions,
        SUM(SALE.total_amount - SALE.discount_amount) AS daily_revenue,
        SUM(SALE.discount_amount) AS daily_discounts,
        COUNT(DISTINCT SALE.member_id) AS unique_members
    FROM SALE
    WHERE DATE(SALE.sale_date) BETWEEN p_start_date AND p_end_date
    GROUP BY DATE(SALE.sale_date)
    ORDER BY sale_date;
END$$

DELIMITER ;

-- confirmation
SELECT 'Procedures created successfully!' AS status;
SELECT ROUTINE_NAME 
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_SCHEMA = 'u452501794_MuseumDB' 
  AND ROUTINE_TYPE = 'PROCEDURE'
  AND ROUTINE_NAME IN (
    'ArtworksByArtist',
    'ArtworksByPeriod', 
    'ArtworksByMedium',
    'EventAttendanceReport',
    'TicketsSoldVsCapacity',
    'MemberVsVisitorAdmissions',
    'GetUpcomingEvents',
    'DailySalesSummary',
    'TopSellingItems',
    'GetLowStockAlerts',
    'RevenueByDateRange'
  )
ORDER BY ROUTINE_NAME;