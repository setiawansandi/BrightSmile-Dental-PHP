USE brightsmile;

SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- USERS
INSERT INTO users
(id, email, password_hash, first_name, last_name, dob, avatar_url, phone, last_login, is_doctor, is_admin)
VALUES
-- user-doctors
-- password = 'P@ssw0rd'
(1, 'doc1@brightsmile.sg', '$argon2id$v=19$m=16,t=2,p=1$Y0ZIMG4zM3pHSjhUR01aZw$BcMaxr07YFeubR5YIRnhze5MlbP6m+yEqT+KF8uxGEo', 'Nicholas', 'Bedasso', '1980-05-14', 'assets/images/doc1.png', '+6581234561', NOW(), 1, 0),
(2, 'doc2@brightsmile.sg', '$argon2id$v=19$m=16,t=2,p=1$Y0ZIMG4zM3pHSjhUR01aZw$BcMaxr07YFeubR5YIRnhze5MlbP6m+yEqT+KF8uxGEo', 'Isabelle', 'Woo', '1984-07-22', 'assets/images/doc2.png', '+6581234562', NOW(), 1, 0),
(3, 'doc3@brightsmile.sg', '$argon2id$v=19$m=16,t=2,p=1$Y0ZIMG4zM3pHSjhUR01aZw$BcMaxr07YFeubR5YIRnhze5MlbP6m+yEqT+KF8uxGEo', 'Zhang', 'Jing', '1978-11-03', 'assets/images/doc3.png', '+6581234563', NOW(), 1, 0),
(4, 'doc4@brightsmile.sg', '$argon2id$v=19$m=16,t=2,p=1$Y0ZIMG4zM3pHSjhUR01aZw$BcMaxr07YFeubR5YIRnhze5MlbP6m+yEqT+KF8uxGEo', 'Amanda', 'See', '1986-02-10', 'assets/images/doc4.jpg', '+6581234564', NOW(), 1, 0),
-- user-patients
(5, 'whayyu.kham@example.com', '$argon2id$v=19$m=16,t=2,p=1$Y0ZIMG4zM3pHSjhUR01aZw$BcMaxr07YFeubR5YIRnhze5MlbP6m+yEqT+KF8uxGEo', 'Whay Yu', 'Kham', '2002-01-01', NULL, '+6590000005', NOW(), 0, 0),
(6, 'jane.doe@example.com',    '$argon2id$v=19$m=16,t=2,p=1$Y0ZIMG4zM3pHSjhUR01aZw$BcMaxr07YFeubR5YIRnhze5MlbP6m+yEqT+KF8uxGEo', 'Chop De', 'Sit', '1995-05-18', NULL, '+6590000006', NOW(), 0, 0),
(7, 'amir.rahman@example.com', '$argon2id$v=19$m=16,t=2,p=1$Y0ZIMG4zM3pHSjhUR01aZw$BcMaxr07YFeubR5YIRnhze5MlbP6m+yEqT+KF8uxGEo', 'La Tang', 'Mah','1991-09-07', NULL, '+6590000007', NOW(), 0, 0);
(8, 'admin@gmail.com',         '$argon2id$v=19$m=16,t=2,p=1$Y0ZIMG4zM3pHSjhUR01aZw$BcMaxr07YFeubR5YIRnhze5MlbP6m+yEqT+KF8uxGEo', 'Admin', 'User', '1991-09-07', NULL, '+6590000007', NOW(), 1, 1)


-- DOCTORS
INSERT INTO doctors (user_id, specialization, bio) VALUES
(1, 'General Dentistry', "Dr Nicholas Bidasso graduated from the University of Melbourne, Australia, with a Bachelor of Dental Surgery. He returned to Singapore and served his bond in the public sector, gaining broad experience at various polyclinics and the Health Promotion Board. This period provided him with a strong foundation in community dental care, preventive dentistry, and managing patients of all ages. After his public service, he transitioned to private practice before joining SmileFocus Dental. Dr Bidasso is a firm believer in patient-first dentistry, emphasizing clear communication and gentle care to help anxious patients feel comfortable."),
(2, 'Cosmetic Dentistry', "Dr Isabelle Woo graduated from King's College London, United Kingdom. Following her graduation, she practiced in London, where she developed a strong interest in aesthetic dentistry, prompting her to complete numerous advanced courses in smile design and ceramic veneers. Upon returning to Singapore, she worked exclusively in private practices focused on aesthetic transformations. Dr Woo is skilled in a range of cosmetic procedures, including veneers, teeth whitening, and full smile makeovers. She believes in combining the art and science of dentistry to create beautiful, natural-looking smiles that are unique to each patient."),
(3, 'Orthodontics', "Dr Zhang Jing received his Bachelor of Dental Surgery from the National University of Singapore (NUS). After a few years in general practice, he pursued his specialist training and obtained a Master of Dental Surgery in Orthodontics from NUS. He is a registered specialist with the Singapore Dental Council and practiced at the National Dental Centre Singapore, handling complex braces and aligner cases. Dr. Zhang is passionate about the functional and aesthetic benefits of a well-aligned bite. He is dedicated to using modern digital technologies to create precise, effective treatment plans for both children and adults."),
(4, 'General Dentistry', "Dr Amanda See graduated from the University of Southampton, United Kingdom. She returned to Singapore upon graduation and practised in several hospitals including Singapore General Hospital, National University Hospital and Changi General Hospital, where she has gained experience in multiple specialities such as General Medicine, General Surgery, and General Practice. She joined Raffles Medical Group for three years before joining BrightSmile. Dr See is particularly passionate about promoting women's health in Singapore. She strives to promote awareness of women's health through patient education, health screening and modification of lifestyle.");


-- APPOINTMENTS
INSERT INTO appointments
(patient_user_id, doctor_user_id, appt_date, appt_time, status)
VALUES
-- Upcoming / confirmed
(5, 1, '2025-10-30', '15:00:00', 'confirmed'),
(6, 2, '2025-11-02', '10:30:00', 'confirmed'),
(7, 4, '2025-11-05', '09:00:00', 'confirmed'),

-- Past / completed
(5, 1, '2025-09-01', '15:00:00', 'completed'),
(6, 3, '2025-09-20', '11:00:00', 'completed'),

-- Cancelled example
(7, 2, '2025-10-15', '14:00:00', 'cancelled');

