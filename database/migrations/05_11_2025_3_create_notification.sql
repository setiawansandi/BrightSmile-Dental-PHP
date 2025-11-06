CREATE DATABASE IF NOT EXISTS brightsmile
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE brightsmile;

SET NAMES utf8mb4;
SET time_zone = '+08:00';


-- NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT UNSIGNED NOT NULL, 
    actor_id INT UNSIGNED NOT NULL, 
    appointment_id INT UNSIGNED NULL, 
    action_type ENUM('booked', 'rescheduled', 'canceled', 'completed', 'booked_actor', 'rescheduled_actor', 'canceled_actor', 'completed_actor') NOT NULL,
    is_read TINYINT(1) DEFAULT 0 NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
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