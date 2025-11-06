CREATE DATABASE IF NOT EXISTS brightsmile
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE brightsmile;

SET NAMES utf8mb4;
SET time_zone = '+08:00';


-- NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    
    -- Who should RECEIVE this notification? (e.g., the patient or doctor)
    recipient_id INT UNSIGNED NOT NULL, 
    
    -- Who CAUSED this event? (e.g., the patient or doctor)
    actor_id INT UNSIGNED NOT NULL, 
    
    -- Which appointment is this about?
    appointment_id INT UNSIGNED NULL, 
    
    -- What action happened?
    action_type ENUM('booked', 'rescheduled', 'canceled', 'completed') NOT NULL,
    
    -- Has the recipient seen this yet?
    is_read TINYINT(1) DEFAULT 0 NOT NULL,
    
    -- When did this happen?
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Define the relationships
    FOREIGN KEY (recipient_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE,
        
    FOREIGN KEY (actor_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE,
        
    FOREIGN KEY (appointment_id) 
        REFERENCES appointments(id) 
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;