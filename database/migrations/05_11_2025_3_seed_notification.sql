USE brightsmile;

SET NAMES utf8mb4;
SET time_zone = '+08:00';

INSERT INTO notifications
(recipient_id, actor_id, appointment_id, action_type, is_read, created_at)
VALUES
-- Appt 1 (Patient 5, Doctor 1) - "booked"
-- Doctor 1 receives a notification that Patient 5 booked.
(1, 5, 1, 'booked', 1, '2025-10-20 11:00:00'),

-- Appt 2 (Patient 6, Doctor 2) - "booked"
-- Doctor 2 receives a notification that Patient 6 booked.
(2, 6, 2, 'booked', 1, '2025-10-22 14:30:00'),

-- Appt 3 (Patient 7, Doctor 4) - "booked"
-- Doctor 4 receives a notification that Patient 7 booked.
(4, 7, 3, 'booked', 0, '2025-10-25 09:15:00'), -- Unread, will show on dot

-- Appt 4 (Patient 5, Doctor 1) - "completed"
-- This one has two notifications: the booking and the completion.
-- 1. Doctor 1 receives "booked" notification
(1, 5, 4, 'booked', 1, '2025-08-25 10:00:00'),
-- 2. Patient 5 receives "completed" notification from Doctor 1
(5, 1, 4, 'completed', 1, '2025-09-01 16:00:00'), -- Read

-- Appt 5 (Patient 6, Doctor 3) - "completed"
-- Two notifications:
-- 1. Doctor 3 receives "booked" notification
(3, 6, 5, 'booked', 1, '2025-09-10 17:00:00'),
-- 2. Patient 6 receives "completed" notification from Doctor 3
(6, 3, 5, 'completed', 0, '2025-09-20 12:00:00'), -- Unread

-- Appt 6 (Patient 7, Doctor 2) - "cancelled"
-- Two notifications (assuming Patient 7 canceled it)
-- 1. Doctor 2 receives "booked" notification
(2, 7, 6, 'booked', 1, '2025-10-01 08:00:00'),
-- 2. Doctor 2 receives "canceled" notification from Patient 7
(2, 7, 6, 'canceled', 1, '2025-10-10 13:00:00'),

-- Example "rescheduled" notification
-- Let's pretend Doctor 1 rescheduled Appt 1.
-- Patient 5 receives a "rescheduled" notification from Doctor 1.
(5, 1, 1, 'rescheduled', 0, '2025-10-21 09:00:00'); -- Unread