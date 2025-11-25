# Homies Fine Arts Museum Database Application
**COSC 3380 Database Systems - Fall 2025 Team Project**

## Live Application
**URL:** https://homiesmuseum.site

## Project Overview

This is a comprehensive museum management system built for the University of Houston COSC 3380 Database Systems course. The application manages artwork collections, exhibitions, events, memberships, museum shop operations, and donations through a role based web interface.



---

## Repository Structure

```
# Homies Fine Arts Museum Database Application
**COSC 3380 Database Systems - Fall 2025 Team Project**

## Live Application
**URL:** https://homiesmuseum.site

## Project Overview

This is a comprehensive museum management system built for the University of Houston COSC 3380 Database Systems course. The application manages artwork collections, exhibitions, events, memberships, museum shop operations, and donations through a role based web interface.



---

## Repository Structure

```
- public // root directory of website
   - admin // pages pertaining to admin role
      - events.php
      - get_user_details.php
      - reports.php
      - shop.php
      - users.php
   - app // files for database connection
      - config.example.php
      - db.php
      - permissions.php
      - session.php
   - curator // pages pertaining to curator role and actions
      - acquisitions.php
      - artists.php
      - artworks.php
      - exhibitions.php
      - get_exhibition_artworks.php
      - reports.php
   - events // pages pertaining to event role and actions
      - buy_ticket.php
      - checkin.php
      - manage.php 
      - reports.php 
      - sell-ticket.php
   - member // pages pertaining to member role and actions
      - membership.php
      - settings.php
      - tickets.php
   - reports // all reports used in the system
   - shop // pages pertaining to shop role and actions
      - checkout.php
      - confirm-reorder.php
      - get-sale-details.php
      - inventory.php
      - new-sale.php
      - order-confirmation.php
      - reports.php
      - sales.php
   - templates // header and footer templates for consistency across pages
   - about.php
   - dashboard.php
   - diag.php
   - donate.php
   - events.php
   - exhibitions.php
   - index.php
   - login.php
   - logout.php
   - register.php
   - shop.php
- sql //

```

---

## User Access Credentials
 
For testing and evaluation, use these pre-configured accounts:
 
### Admin Account
- **Username:** `admin1`
- **Password:** `admin`
- **Access:** Full system access, all management capabilities
 
### Curator Account
- **Username:** `curator1`
- **Password:** `curator`
- **Access:** Artwork, artist, and exhibition management
 
### Shop Staff Account
- **Username:** `shop1`
- **Password:** `shop`
- **Access:** Inventory and sales management
 
### Event Staff Account
- **Username:** `event1`
- **Password:** `event`
- **Access:** Event and ticketing management
 
### Member Account
- **Username:** `herom`
- **Password:** `heromherom`
- **Access:** Self-service member portal, discount purchases
- **Membership Status:** Active until November 2026
 
### Testing Member (Expired)
- **Username:** `member1`
- **Password:** `member`
- **Membership Status:** Expired (for testing discount validation trigger)

---

## Database Information

**Database Name:** `u452501794_MuseumDB`  
**Database User:** `u452501794_DBuser`  
**Hosting:** Hostinger MySQL Server  
**phpMyAdmin URL:** https://auth-db1026.hstgr.io/

### Database Tables (25 total):
- **Core Entities:** ARTWORK, ARTIST, EXHIBITION, EVENT, LOCATION, STAFF, DEPARTMENT
- **Operations:** SALE, SALE_ITEM, SHOP_ITEM, TICKET, ACQUISITION, DONATION
- **People:** MEMBER, VISITOR, DONOR, USER
- **Relationships:** EXHIBITION_ARTWORK, ART_CREATOR, ARTWORK_CREATOR
- **System:** SALE_VALIDATION_MESSAGES

---

## Installation Instructions

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- phpMyAdmin (for database management)

### Local Setup

1. **Clone the Repository**
2. **Configure Database Connection**
   
   Edit `web/config.php` with your database credentials:
   ```php
   $servername = "localhost";
   $username = "your_db_username";
   $dbname = "your_db_name";
   $password = "your_db_password";
   ```

3. **Import Database**
   
   Using phpMyAdmin:
   - Create a new database
   - Import `sql/u452501794_MuseumDB.sql`
   - Verify all tables, triggers, and procedures are created

   Using command line:
   ```bash
   mysql -u your_username -p your_database_name < sql/u452501794_MuseumDB.sql
   ```

4. **Configure Web Server**
   
   Point document root to `web/public/` directory

5. **Access Application**
   
   Navigate to `http://localhost` or your configured domain
   Use test credentials listed above to log in

---

## Technology Stack

**Frontend:**
- Bootstrap 5.3.0 (responsive framework)
- Chart.js (data visualization)
- jQuery (AJAX interactions)

**Backend:**
- PHP 8.x
- MySQLi extension for database connectivity
- Session based authentication
- Prepared statements (SQL injection prevention)

**Database:**
- MySQL 8.0
- Stored procedures for complex queries
- Triggers for business logic automation
- Foreign key constraints for referential integrity
- Soft delete pattern (is_deleted flags)

**Hosting:**
- Hostinger shared hosting

---

## Key Features Implemented

### User Authentication
- Secure login system with role based access control
- Six distinct user roles with appropriate permissions
- Session management with timeout
- Password encryption (hashing)
- Role specific dashboards and navigation

### Data Entry Forms
- **Add:** Create new records for all entities
- **Modify:** Edit existing records with validation
- **Forms:** Artwork, Artist, Exhibition, Event, Shop Items, Sales, Tickets, Members, Donations, Staff

### Database Triggers
1. **Member Discount Validation** (`trg_check_member_discount_before_sale`)
   - EVENT: BEFORE INSERT ON SALE
   - CONDITION: Check member expiration status
   - ACTION: Validate discount eligibility, log messages, enforce pricing rules

2. **Automatic Inventory Management** (`trg_reduce_stock_after_sale`)
   - EVENT: AFTER INSERT ON SALE_ITEM
   - CONDITION: Stock level falls below reorder threshold
   - ACTION: Reduce inventory, prevent negative stock, trigger automatic reordering

3. **Reorder Flag Management** (`trg_clear_auto_reorder_on_manual_update`)
   - EVENT: BEFORE UPDATE ON SHOP_ITEM
   - CONDITION: Manual staff update to auto-reordered item
   - ACTION: Clear reorder flags once acknowledged

### Data Queries & Reports

**Stored Procedures (some of):**
- `ArtworksByArtist(artist_id)` - Collection filtering
- `ArtworksByPeriod(start_year, end_year)` - Historical analysis
- `ArtworksByMedium(medium)` - Medium based queries
- `GetUpcomingEvents()` - Event calendar
- `EventAttendanceReport(event_id)` - Attendance analytics
- `TicketsSoldVsCapacity(event_id)` - Capacity tracking
- `MemberVsVisitorAdmissions(date_from, date_to)` - Visitor analysis
- `DailySalesSummary(date)` - Financial reporting
- `TopSellingItems(days_back)` - Inventory analytics
- `GetLowStockAlerts(threshold)` - Reorder management
- `RevenueByDateRange(start_date, end_date)` - Revenue trends
- `GetMemberSales()` - Member value analysis
- `GetVisitorSales()` - Visitor value analysis

**Complex Reports:**
- Exhibition analytics (artworks, attendance, revenue)
- Financial summaries (sales, donations, membership)
- Collection insights (artists, mediums, periods, locations)
- Membership metrics (active, expired, renewals, savings)
- Inventory health (stock levels, reorder status, sales velocity)

---

## Business Logic Highlights

**Membership Discount System:**
- 10% discount for active members
- Automatic validation at checkout (via trigger)
- Real time expiration checking
- User notifications for expired memberships

**Inventory Management:**
- Configurable reorder thresholds per item
- Automatic stock reduction on sale
- Automated reordering when threshold reached
- Low stock alerts for staff
- Negative stock prevention

**Event Capacity:**
- Real time availability tracking
- Prevent overbooking
- Check-in system for attendance
- Capacity utilization reports

---

## Testing the Application

### Test Scenarios

1. **Member Discount Validation:**
   - Log in as active member (`herom`)
   - Add items to cart and checkout → Should receive 10% discount
   - Log in as expired member (`member1`)
   - Attempt checkout → Should see expiration warning, no discount applied unless membership is renewed

2. **Automatic Inventory Reorder:**
   - Log in as shop staff (`shop1`)
   - View an item with low stock below reorder threshold
   - Process a sale that drops stock below threshold
   - Check item details → Should show auto reorder flag and increased stock

3. **Event Capacity Management:**
   - Log in as event staff (`event1`)
   - Select an event near capacity
   - Attempt to sell tickets exceeding capacity → Should prevent overselling
   - View event report → Should show accurate sold/available counts

4. **Exhibition Management:**
   - Log in as curator (`curator1`)
   - Create new exhibition with date range
   - Add artworks to exhibition with display dates
   - View exhibition analytics → Should show artwork count, artists, value

5. **Role Based Access:**
   - Try accessing admin pages as curator → Should be denied
   - Try accessing shop functions as event staff → Should redirect
   - Verify each role can only access appropriate features

6. **Artwork Donation:**
   - Donate artwork by filling out the donation form
   - Login as curator and navigate to acquisitions page
   - Review the donation and accept or reject

---

##  Database Schema

The complete database schema includes 25 tables with proper relationships, foreign keys, and constraints. Key relationships include:

- **ARTWORK** → **ARTIST** (many-to-many through ART_CREATOR)
- **EXHIBITION** → **ARTWORK** (many-to-many through EXHIBITION_ARTWORK)
- **EVENT** → **TICKET** (one-to-many)
- **MEMBER/VISITOR** → **SALE** (many-to-one, mutually exclusive)
- **SALE** → **SALE_ITEM** → **SHOP_ITEM** (order details)
- **DONOR** → **DONATION** (one-to-many)
- **ACQUISITION** → **ARTWORK** (one-to-one for owned pieces)

---

## Team Information

**Course:** COSC 3380 Database Systems  
**Semester:** Fall 2025  
**Institution:** University of Houston  
**Team Project:** Homies Fine Arts Museum Management System - Team 7

---

## Notes

- All passwords in test accounts are for demonstration purposes only
- Database includes sample data for testing (artworks, events, members, sales)
- All monetary values use DECIMAL types for precision
- Dates and times are stored in appropriate formats with timezone considerations

---

## Security Considerations

- Prepared statements prevent SQL injection
- Session based authentication with timeout
- Role based access control enforced server side
- Passwords stored with secure hashing
- Input validation on all forms

---


## Final Notes

This application represents a comprehensive implementation of database systems concepts including transaction management, referential integrity, stored procedures, triggers, and role based access control. The system is production ready and demonstrates real world application of database design principles in a museum management context.

**Live Application:** https://homiesmuseum.site  
**Submission Date:** November 24, 2025



```

---

## Database Information

**Database Name:** `u452501794_MuseumDB`  
**Database User:** `u452501794_DBuser`  
**Hosting:** Hostinger MySQL Server  
**phpMyAdmin URL:** https://auth-db1026.hstgr.io/

### Database Tables (25 total):
- **Core Entities:** ARTWORK, ARTIST, EXHIBITION, EVENT, LOCATION, STAFF, DEPARTMENT
- **Operations:** SALE, SALE_ITEM, SHOP_ITEM, TICKET, ACQUISITION, DONATION
- **People:** MEMBER, VISITOR, DONOR, USER
- **Relationships:** EXHIBITION_ARTWORK, ART_CREATOR, ARTWORK_CREATOR
- **System:** SALE_VALIDATION_MESSAGES

---

## Installation Instructions

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- phpMyAdmin (for database management)

### Local Setup

1. **Clone the Repository**
2. **Configure Database Connection**
   
   Edit `web/config.php` with your database credentials:
   ```php
   $servername = "localhost";
   $username = "your_db_username";
   $dbname = "your_db_name";
   $password = "your_db_password";
   ```

3. **Import Database**
   
   Using phpMyAdmin:
   - Create a new database
   - Import `sql/u452501794_MuseumDB.sql`
   - Verify all tables, triggers, and procedures are created

   Using command line:
   ```bash
   mysql -u your_username -p your_database_name < sql/u452501794_MuseumDB.sql
   ```

4. **Configure Web Server**
   
   Point document root to `web/public/` directory

5. **Access Application**
   
   Navigate to `http://localhost` or your configured domain
   Use test credentials listed above to log in

---

## Technology Stack

**Frontend:**
- Bootstrap 5.3.0 (responsive framework)
- Chart.js (data visualization)
- jQuery (AJAX interactions)

**Backend:**
- PHP 8.x
- MySQLi extension for database connectivity
- Session based authentication
- Prepared statements (SQL injection prevention)

**Database:**
- MySQL 8.0
- Stored procedures for complex queries
- Triggers for business logic automation
- Foreign key constraints for referential integrity
- Soft delete pattern (is_deleted flags)

**Hosting:**
- Hostinger shared hosting

---

## Key Features Implemented

### User Authentication
- Secure login system with role based access control
- Six distinct user roles with appropriate permissions
- Session management with timeout
- Password encryption (hashing)
- Role specific dashboards and navigation

### Data Entry Forms
- **Add:** Create new records for all entities
- **Modify:** Edit existing records with validation
- **Forms:** Artwork, Artist, Exhibition, Event, Shop Items, Sales, Tickets, Members, Donations, Staff

### Database Triggers
1. **Member Discount Validation** (`trg_check_member_discount_before_sale`)
   - EVENT: BEFORE INSERT ON SALE
   - CONDITION: Check member expiration status
   - ACTION: Validate discount eligibility, log messages, enforce pricing rules

2. **Automatic Inventory Management** (`trg_reduce_stock_after_sale`)
   - EVENT: AFTER INSERT ON SALE_ITEM
   - CONDITION: Stock level falls below reorder threshold
   - ACTION: Reduce inventory, prevent negative stock, trigger automatic reordering

3. **Reorder Flag Management** (`trg_clear_auto_reorder_on_manual_update`)
   - EVENT: BEFORE UPDATE ON SHOP_ITEM
   - CONDITION: Manual staff update to auto-reordered item
   - ACTION: Clear reorder flags once acknowledged

### Data Queries & Reports

**Stored Procedures (some of):**
- `ArtworksByArtist(artist_id)` - Collection filtering
- `ArtworksByPeriod(start_year, end_year)` - Historical analysis
- `ArtworksByMedium(medium)` - Medium based queries
- `GetUpcomingEvents()` - Event calendar
- `EventAttendanceReport(event_id)` - Attendance analytics
- `TicketsSoldVsCapacity(event_id)` - Capacity tracking
- `MemberVsVisitorAdmissions(date_from, date_to)` - Visitor analysis
- `DailySalesSummary(date)` - Financial reporting
- `TopSellingItems(days_back)` - Inventory analytics
- `GetLowStockAlerts(threshold)` - Reorder management
- `RevenueByDateRange(start_date, end_date)` - Revenue trends
- `GetMemberSales()` - Member value analysis
- `GetVisitorSales()` - Visitor value analysis

**Complex Reports:**
- Exhibition analytics (artworks, attendance, revenue)
- Financial summaries (sales, donations, membership)
- Collection insights (artists, mediums, periods, locations)
- Membership metrics (active, expired, renewals, savings)
- Inventory health (stock levels, reorder status, sales velocity)

---

## Business Logic Highlights

**Membership Discount System:**
- 10% discount for active members
- Automatic validation at checkout (via trigger)
- Real time expiration checking
- User notifications for expired memberships

**Inventory Management:**
- Configurable reorder thresholds per item
- Automatic stock reduction on sale
- Automated reordering when threshold reached
- Low stock alerts for staff
- Negative stock prevention

**Event Capacity:**
- Real time availability tracking
- Prevent overbooking
- Check-in system for attendance
- Capacity utilization reports

---

## Testing the Application

### Test Scenarios

1. **Member Discount Validation:**
   - Log in as active member (`herom`)
   - Add items to cart and checkout → Should receive 10% discount
   - Log in as expired member (`member1`)
   - Attempt checkout → Should see expiration warning, no discount applied unless membership is renewed

2. **Automatic Inventory Reorder:**
   - Log in as shop staff (`shop1`)
   - View an item with low stock below reorder threshold
   - Process a sale that drops stock below threshold
   - Check item details → Should show auto reorder flag and increased stock

3. **Event Capacity Management:**
   - Log in as event staff (`event1`)
   - Select an event near capacity
   - Attempt to sell tickets exceeding capacity → Should prevent overselling
   - View event report → Should show accurate sold/available counts

4. **Exhibition Management:**
   - Log in as curator (`curator1`)
   - Create new exhibition with date range
   - Add artworks to exhibition with display dates
   - View exhibition analytics → Should show artwork count, artists, value

5. **Role Based Access:**
   - Try accessing admin pages as curator → Should be denied
   - Try accessing shop functions as event staff → Should redirect
   - Verify each role can only access appropriate features

6. **Artwork Donation:**
   - Donate artwork by filling out the donation form
   - Login as curator and navigate to acquisitions page
   - Review the donation and accept or reject

---

##  Database Schema

The complete database schema includes 25 tables with proper relationships, foreign keys, and constraints. Key relationships include:

- **ARTWORK** → **ARTIST** (many-to-many through ART_CREATOR)
- **EXHIBITION** → **ARTWORK** (many-to-many through EXHIBITION_ARTWORK)
- **EVENT** → **TICKET** (one-to-many)
- **MEMBER/VISITOR** → **SALE** (many-to-one, mutually exclusive)
- **SALE** → **SALE_ITEM** → **SHOP_ITEM** (order details)
- **DONOR** → **DONATION** (one-to-many)
- **ACQUISITION** → **ARTWORK** (one-to-one for owned pieces)

---

## Team Information

**Course:** COSC 3380 Database Systems  
**Semester:** Fall 2025  
**Institution:** University of Houston  
**Team Project:** Homies Fine Arts Museum Management System - Team 7

---

## Notes

- All passwords in test accounts are for demonstration purposes only
- Database includes sample data for testing (artworks, events, members, sales)
- All monetary values use DECIMAL types for precision
- Dates and times are stored in appropriate formats with timezone considerations

---

## Security Considerations

- Prepared statements prevent SQL injection
- Session based authentication with timeout
- Role based access control enforced server side
- Passwords stored with secure hashing
- Input validation on all forms

---


## Final Notes

This application represents a comprehensive implementation of database systems concepts including transaction management, referential integrity, stored procedures, triggers, and role based access control. The system is production ready and demonstrates real world application of database design principles in a museum management context.

**Live Application:** https://homiesmuseum.site  
**Submission Date:** November 24, 2025

