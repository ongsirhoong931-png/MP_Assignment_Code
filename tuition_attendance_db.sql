-- Tuition Attendance Management System - XAMPP MySQL/MariaDB
-- Import using phpMyAdmin > Import. Sample records are from the supplied spreadsheets.
CREATE DATABASE IF NOT EXISTS tuition_attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tuition_attendance_db;
SET FOREIGN_KEY_CHECKS=0;
DROP VIEW IF EXISTS v_student_attendance_summary;
DROP TABLE IF EXISTS announcements, announcement_reads, password_reset_tokens, user_accounts, attendance_records, leave_applications, material_attachments, study_materials, academic_calendar_overrides, timetable_schedules, student_enrolments, students, classes, subjects, staff, admins;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE admins (admin_id VARCHAR(12) PRIMARY KEY, admin_name VARCHAR(120) NOT NULL, admin_email VARCHAR(180) UNIQUE, admin_phone_number VARCHAR(30)) ENGINE=InnoDB;
CREATE TABLE staff (staff_id VARCHAR(12) PRIMARY KEY, staff_name VARCHAR(120) NOT NULL, staff_email VARCHAR(180) UNIQUE, staff_phone_number VARCHAR(30), staff_role VARCHAR(60)) ENGINE=InnoDB;
CREATE TABLE subjects (subject_id VARCHAR(12) PRIMARY KEY, subject_name VARCHAR(180) NOT NULL, subject_code VARCHAR(30) UNIQUE, department VARCHAR(120)) ENGINE=InnoDB;
CREATE TABLE classes (class_id VARCHAR(12) PRIMARY KEY, grade_level VARCHAR(60), section VARCHAR(30), academic_year SMALLINT, class_teacher_id VARCHAR(12), FOREIGN KEY(class_teacher_id) REFERENCES staff(staff_id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE students (student_id VARCHAR(12) PRIMARY KEY, student_name VARCHAR(120) NOT NULL, student_email VARCHAR(180) UNIQUE, student_phone_number VARCHAR(30), student_class VARCHAR(12), FOREIGN KEY(student_class) REFERENCES classes(class_id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE student_enrolments (enrolment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, student_id VARCHAR(12) NOT NULL, class_id VARCHAR(12) NOT NULL, enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_student_class(student_id,class_id), FOREIGN KEY(student_id) REFERENCES students(student_id) ON DELETE CASCADE, FOREIGN KEY(class_id) REFERENCES classes(class_id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE timetable_schedules (schedule_id VARCHAR(12) PRIMARY KEY, class_id VARCHAR(12) NOT NULL, subject_id VARCHAR(12) NOT NULL, assigned_staff_id VARCHAR(12), day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, room_number VARCHAR(50), room_type VARCHAR(60), FOREIGN KEY(class_id) REFERENCES classes(class_id), FOREIGN KEY(subject_id) REFERENCES subjects(subject_id), FOREIGN KEY(assigned_staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL, CHECK(end_time>start_time)) ENGINE=InnoDB;
CREATE TABLE academic_calendar_overrides (override_id VARCHAR(12) PRIMARY KEY, `date` DATE NOT NULL UNIQUE, day_type VARCHAR(60) NOT NULL, description VARCHAR(255), is_tuition_day TINYINT(1) NOT NULL DEFAULT 0) ENGINE=InnoDB;
CREATE TABLE leave_applications (leave_id VARCHAR(12) PRIMARY KEY, applicant_id VARCHAR(12) NOT NULL, applicant_type ENUM('Student','Staff') NOT NULL, leave_type VARCHAR(80), start_date DATE NOT NULL, end_date DATE NOT NULL, reason TEXT, status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending', approved_by_id VARCHAR(12), attachment_path VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(approved_by_id) REFERENCES admins(admin_id) ON DELETE SET NULL, CHECK(end_date>=start_date), INDEX idx_leave_applicant(applicant_id,applicant_type)) ENGINE=InnoDB;
CREATE TABLE attendance_records (attendance_id VARCHAR(12) PRIMARY KEY, student_id VARCHAR(12) NOT NULL, class_id VARCHAR(12) NOT NULL, `date` DATE NOT NULL, status ENUM('Present','Absent','Late','Excused') NOT NULL, schedule_id VARCHAR(12) NULL, marked_by_staff_id VARCHAR(12) NULL, marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_student_class_date(student_id,class_id,`date`), FOREIGN KEY(student_id) REFERENCES students(student_id), FOREIGN KEY(class_id) REFERENCES classes(class_id), FOREIGN KEY(schedule_id) REFERENCES timetable_schedules(schedule_id) ON DELETE SET NULL, FOREIGN KEY(marked_by_staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL, INDEX idx_attendance_date(`date`)) ENGINE=InnoDB;
CREATE TABLE study_materials (material_id VARCHAR(12) PRIMARY KEY, title VARCHAR(200) NOT NULL, description TEXT, subject_id VARCHAR(12), class_id VARCHAR(12), created_by_staff_id VARCHAR(12), content_type VARCHAR(60), rich_text_content LONGTEXT, is_published TINYINT(1) DEFAULT 0, release_date DATE, FOREIGN KEY(subject_id) REFERENCES subjects(subject_id) ON DELETE SET NULL, FOREIGN KEY(class_id) REFERENCES classes(class_id) ON DELETE SET NULL, FOREIGN KEY(created_by_staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE material_attachments (attachment_id VARCHAR(16) PRIMARY KEY, material_id VARCHAR(12) NOT NULL, file_name VARCHAR(255) NOT NULL, file_type VARCHAR(30), file_path VARCHAR(500), uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(material_id) REFERENCES study_materials(material_id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE user_accounts (user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) NOT NULL UNIQUE, email VARCHAR(180) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('admin','staff','student') NOT NULL, admin_id VARCHAR(12) UNIQUE NULL, staff_id VARCHAR(12) UNIQUE NULL, student_id VARCHAR(12) UNIQUE NULL, preferred_language VARCHAR(10) DEFAULT 'en', is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE, FOREIGN KEY(staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE, FOREIGN KEY(student_id) REFERENCES students(student_id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE password_reset_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL, FOREIGN KEY(user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE announcements (announcement_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, audience ENUM('all','staff','student') DEFAULT 'all', class_id VARCHAR(12) NULL, created_by_admin_id VARCHAR(12) NULL, created_by_staff_id VARCHAR(12) NULL, published_at DATETIME DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NULL, FOREIGN KEY(class_id) REFERENCES classes(class_id) ON DELETE SET NULL, FOREIGN KEY(created_by_admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL, FOREIGN KEY(created_by_staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE announcement_reads (announcement_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(announcement_id,user_id), FOREIGN KEY(announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE) ENGINE=InnoDB;

-- Imported from Admin_Details(1).xlsx

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Ahmad Hakim', 'ADM001', 'ahmad.hakim.adm001@gmail.com', '010-000-1001');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Tan Wei Ming', 'ADM002', 'tan.weiming.adm002@yahoo.com', '011-0000-1002');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Nur Aisyah', 'ADM003', 'nur.aisyah.adm003@outlook.com', '012-000-1003');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Lim Jia Hui', 'ADM004', 'lim.jiahui.adm004@gmail.com', '013-000-1004');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Muhammad Danish', 'ADM005', 'danish.adm005@yahoo.com', '014-000-1005');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Lee Xin Yi', 'ADM006', 'lee.xinyi.adm006@outlook.com', '016-000-1006');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Siti Nurul', 'ADM007', 'siti.nurul.adm007@gmail.com', '017-000-1007');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Wong Jun Hao', 'ADM008', 'wong.junhao.adm008@yahoo.com', '018-000-1008');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Chong Mei Ling', 'ADM009', 'chong.meiling.adm009@outlook.com', '019-000-1009');

INSERT INTO `admins` (`admin_name`, `admin_id`, `admin_email`, `admin_phone_number`) VALUES ('Farah Nadia', 'ADM010', 'farah.nadia.adm010@gmail.com', '011-0000-1010');

-- Imported from Staff_Details(1).xlsx

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Ahmad Faiz', 'STF001', 'ahmad.faiz.staff001@gmail.com', '012-000-2001', 'Lecturer');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Tan Wei Jian', 'STF002', 'tan.weijian.staff002@yahoo.com', '011-0000-2002', 'Tutor');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Nur Amirah', 'STF003', 'nur.amirah.staff003@outlook.com', '013-000-2003', 'Lecturer');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Lim Kok Leong', 'STF004', 'lim.kokleong.staff004@gmail.com', '016-000-2004', 'Supervisor');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Siti Hajar', 'STF005', 'siti.hajar.staff005@yahoo.com', '017-000-2005', 'Tutor');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Wong Jia Wen', 'STF006', 'wong.jiawen.staff006@outlook.com', '018-000-2006', 'Lecturer');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Muhammad Hafiz', 'STF007', 'muhammad.hafiz.staff007@gmail.com', '019-000-2007', 'Tutor');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Lee Mei Xin', 'STF008', 'lee.meixin.staff008@yahoo.com', '014-000-2008', 'Supervisor');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Farah Izzati', 'STF009', 'farah.izzati.staff009@outlook.com', '012-000-2009', 'Lecturer');

INSERT INTO `staff` (`staff_name`, `staff_id`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('Chong Jun Wei', 'STF010', 'chong.junwei.staff010@gmail.com', '011-0000-2010', 'Tutor');

-- Imported from Subjects_Master(1).xlsx

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB001', 'Introduction to Information Technology', 'IT101', 'Information Technology');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB002', 'Web Application Development', 'IT102', 'Information Technology');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB003', 'Database Management Systems', 'IT103', 'Information Technology');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB004', 'Computer Networking', 'IT104', 'Information Technology');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB005', 'Software Engineering', 'IT105', 'Information Technology');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB006', 'Object-Oriented Programming', 'IT106', 'Information Technology');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB007', 'Operating Systems', 'IT107', 'Information Technology');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB008', 'Data Structures and Algorithms', 'IT108', 'Computer Science');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB009', 'Cybersecurity Fundamentals', 'IT109', 'Information Security');

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB010', 'System Analysis and Design', 'IT110', 'Information Technology');

-- Imported from Classes_Master(1).xlsx

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS001', 'Year 1', 'A', 2026, 'STF001');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS002', 'Year 1', 'B', 2026, 'STF002');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS003', 'Year 1', 'C', 2026, 'STF003');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS004', 'Year 2', 'A', 2026, 'STF004');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS005', 'Year 2', 'B', 2026, 'STF005');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS006', 'Year 2', 'C', 2026, 'STF006');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS007', 'Year 3', 'A', 2026, 'STF007');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS008', 'Year 3', 'B', 2026, 'STF008');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS009', 'Year 3', 'C', 2026, 'STF009');

INSERT INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS010', 'Year 3', 'D', 2026, 'STF010');

-- Imported from Students_Details(1).xlsx

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Wong Jun Jie', 'STU001', 'wong.junjie.stu001@gmail.com', '012-000-3001', 'CLS001');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Tan Jia Hao', 'STU002', 'tan.jiahao.stu002@yahoo.com', '011-0000-3002', 'CLS001');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Nur Aina', 'STU003', 'nur.aina.stu003@outlook.com', '013-000-3003', 'CLS001');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Lim Wei Sheng', 'STU004', 'lim.weisheng.stu004@gmail.com', '016-000-3004', 'CLS002');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Lee Xin Rou', 'STU005', 'lee.xinrou.stu005@yahoo.com', '017-000-3005', 'CLS002');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Muhammad Amir', 'STU006', 'muhammad.amir.stu006@outlook.com', '018-000-3006', 'CLS002');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Chong Jia Yi', 'STU007', 'chong.jiayi.stu007@gmail.com', '019-000-3007', 'CLS003');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Siti Aisyah', 'STU008', 'siti.aisyah.stu008@yahoo.com', '014-000-3008', 'CLS003');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Ng Wei Lun', 'STU009', 'ng.weilun.stu009@outlook.com', '012-000-3009', 'CLS003');

INSERT INTO `students` (`student_name`, `student_id`, `student_email`, `student_phone_number`, `student_class`) VALUES ('Farah Izzah', 'STU010', 'farah.izzah.stu010@gmail.com', '011-0000-3010', 'CLS004');

-- Imported from Timetable_Schedules(1).xlsx

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH001', 'CLS001', 'SUB001', 'STF001', 'Monday', '08:00:00', '10:00:00', 'DKABA', 'Lecture');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH002', 'CLS002', 'SUB002', 'STF002', 'Tuesday', '10:00:00', '12:00:00', 'DKZ', 'Lecture');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH003', 'CLS003', 'SUB003', 'STF003', 'Wednesday', '09:00:00', '11:00:00', 'B101', 'Tutorial');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH004', 'CLS004', 'SUB003', 'STF004', 'Thursday', '11:00:00', '13:00:00', 'B102', 'Practical');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH005', 'CLS005', 'SUB004', 'STF005', 'Friday', '08:00:00', '10:00:00', 'C201', 'Practical');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH006', 'CLS006', 'SUB005', 'STF006', 'Monday', '14:00:00', '16:00:00', 'C202', 'Tutorial');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH007', 'CLS007', 'SUB002', 'STF007', 'Tuesday', '13:00:00', '15:00:00', 'D301', 'Practical');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH008', 'CLS008', 'SUB005', 'STF008', 'Wednesday', '15:00:00', '17:00:00', 'D302', 'Tutorial');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH009', 'CLS009', 'SUB004', 'STF009', 'Thursday', '09:00:00', '11:00:00', 'E101', 'Lecture');

INSERT INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH010', 'CLS010', 'SUB005', 'STF010', 'Friday', '14:00:00', '16:00:00', 'E102', 'Practical');

-- Imported from Academic_Calendar_Overrides(1).xlsx

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV001', '2026-12-25', 'Public Holiday', 'Christmas Day', 0);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV002', '2027-01-01', 'Public Holiday', 'New Year''s Day', 0);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV003', '2027-01-09', 'Replacement Class', 'Saturday replacement class', 1);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV004', '2027-01-15', 'University Event', 'Student orientation programme', 0);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV005', '2027-02-01', 'Special Holiday', 'University special holiday', 0);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV006', '2027-02-06', 'Replacement Class', 'Replacement for cancelled lecture', 1);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV007', '2027-02-12', 'University Event', 'University sports day', 0);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV008', '2027-03-05', 'Exam Day', 'Semester examination', 0);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV009', '2027-03-15', 'Semester Break', 'Mid-semester break', 0);

INSERT INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`, `is_tuition_day`) VALUES ('OV010', '2027-04-10', 'Replacement Class', 'Additional Saturday lecture', 1);

-- Imported from Leave_Applications(1).xlsx

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV001', 'STU001', 'Student', 'Medical Leave', '2026-11-02', '2026-11-03', 'Fever', 'Approved', 'ADM001');

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV002', 'STU002', 'Student', 'Personal Leave', '2026-11-04', '2026-11-04', 'Family matters', 'Approved', 'ADM002');

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV003', 'STU003', 'Student', 'Medical Leave', '2026-11-05', '2026-11-06', 'Food poisoning', 'Pending', NULL);

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV004', 'STU004', 'Student', 'Emergency Leave', '2026-11-09', '2026-11-10', 'Family emergency', 'Approved', 'ADM003');

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV005', 'STU005', 'Student', 'Personal Leave', '2026-11-12', '2026-11-12', 'Personal appointment', 'Rejected', 'ADM004');

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV006', 'STF001', 'Staff', 'Medical Leave', '2026-11-16', '2026-11-17', 'Medical treatment', 'Approved', 'ADM001');

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV007', 'STF002', 'Staff', 'Annual Leave', '2026-11-18', '2026-11-20', 'Family vacation', 'Approved', 'ADM002');

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV008', 'STU006', 'Student', 'Medical Leave', '2026-11-23', '2026-11-24', 'Hospital appointment', 'Pending', NULL);

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV009', 'STF003', 'Staff', 'Emergency Leave', '2026-11-25', '2026-11-25', 'Urgent family matter', 'Rejected', 'ADM003');

INSERT INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV010', 'STU007', 'Student', 'Personal Leave', '2026-11-26', '2026-11-27', 'Family event', 'Pending', NULL);

-- Imported from Attendance_Records(1).xlsx

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT001', 'STU001', 'CLS001', '2026-11-02', 'Present');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT002', 'STU002', 'CLS001', '2026-11-02', 'Present');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT003', 'STU003', 'CLS001', '2026-11-02', 'Absent');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT004', 'STU004', 'CLS002', '2026-11-03', 'Present');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT005', 'STU005', 'CLS002', '2026-11-03', 'Late');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT006', 'STU006', 'CLS002', '2026-11-03', 'Present');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT007', 'STU007', 'CLS003', '2026-11-04', 'Absent');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT008', 'STU008', 'CLS003', '2026-11-04', 'Present');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT009', 'STU009', 'CLS003', '2026-11-04', 'Late');

INSERT INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT010', 'STU010', 'CLS004', '2026-11-05', 'Present');

-- Imported from Study_Materials(1).xlsx

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT001', 'Introduction to IT', 'Basic IT concepts', 'SUB001', 'CLS001', 'STF001', 'PDF', 'Introduction to computer systems and hardware.', 1, '2026-11-02');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT002', 'PHP Programming', 'PHP syntax and variables', 'SUB002', 'CLS002', 'STF002', 'PDF', 'Learn PHP variables, operators and functions.', 1, '2026-11-03');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT003', 'Database Design', 'ERD and database relationships', 'SUB003', 'CLS003', 'STF003', 'Image', 'Example of entity relationship diagrams.', 1, '2026-11-04');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT004', 'SQL Tutorial', 'SQL practice questions', 'SUB003', 'CLS004', 'STF004', 'Document', 'Practice SQL SELECT, INSERT, UPDATE and DELETE.', 1, '2026-11-05');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT005', 'Networking Basics', 'Introduction to computer networks', 'SUB004', 'CLS005', 'STF005', 'Presentation', 'Introduction to IP addressing and networking.', 1, '2026-11-06');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT006', 'Assignment Guidelines', 'Coursework instructions', 'SUB005', 'CLS006', 'STF006', 'PDF', 'Guidelines for completing coursework.', 1, '2026-11-09');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT007', 'PHP Code Examples', 'Sample PHP programs', 'SUB002', 'CLS007', 'STF007', 'ZIP', 'Sample PHP source code for practice.', 0, '2026-11-10');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT008', 'Revision Notes', 'Software development revision', 'SUB005', 'CLS008', 'STF008', 'PDF', 'Revision notes for software development.', 1, '2026-11-11');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT009', 'Networking Lab', 'Network configuration exercises', 'SUB004', 'CLS009', 'STF009', 'Document', 'Network configuration lab instructions.', 0, '2026-11-12');

INSERT INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT010', 'Software Design', 'UML and system design', 'SUB005', 'CLS010', 'STF010', 'Image', 'Examples of UML diagrams.', 1, '2026-11-13');

-- Imported from Material_Attachments(1).xlsx

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH001', 'MAT001', 'Lecture_1_Introduction.pdf', 'PDF');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH002', 'MAT002', 'Programming_Notes.pdf', 'PDF');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH003', 'MAT003', 'Database_ERD.png', 'PNG');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH004', 'MAT004', 'Tutorial_Questions.docx', 'DOCX');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH005', 'MAT005', 'Networking_Slides.pptx', 'PPTX');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH006', 'MAT006', 'Assignment_Guidelines.pdf', 'PDF');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH007', 'MAT007', 'PHP_Example_Code.zip', 'ZIP');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH008', 'MAT008', 'Revision_Notes.pdf', 'PDF');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH009', 'MAT009', 'Lab_Instructions.docx', 'DOCX');

INSERT INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH010', 'MAT010', 'Software_Design_Diagram.png', 'PNG');

-- Enrolments derived from each student's Student_Class column.
INSERT IGNORE INTO student_enrolments(student_id,class_id) SELECT student_id,student_class FROM students WHERE student_class IS NOT NULL;
-- Default TEST logins for tuition_attendance_db
-- Run once in phpMyAdmin SQL tab on an existing database.
-- Passwords are stored as PHP-compatible bcrypt hashes, not plaintext.
USE tuition_attendance_db;

INSERT INTO user_accounts (username,email,password_hash,role,admin_id,is_active)
SELECT 'admin123', COALESCE(NULLIF(admin_email,''),'admin123@example.invalid'), '$2y$12$gI6YIgqKI0JEZqvVg/CAe.Qa69UNhL9aM4YBFXzu3MQBzAW.AUNyK', 'admin', admin_id, 1
FROM admins WHERE admin_id='ADM001'
ON DUPLICATE KEY UPDATE username=VALUES(username), password_hash=VALUES(password_hash), is_active=1;

INSERT INTO user_accounts (username,email,password_hash,role,staff_id,is_active)
SELECT 'staff123', COALESCE(NULLIF(staff_email,''),'staff123@example.invalid'), '$2y$12$kW77G34hsJOwtIWlLiQ9luFZBkclOWg3gJUWkj6WujS28aT3gnwMK', 'staff', staff_id, 1
FROM staff WHERE staff_id='STF001'
ON DUPLICATE KEY UPDATE username=VALUES(username), password_hash=VALUES(password_hash), is_active=1;

INSERT INTO user_accounts (username,email,password_hash,role,student_id,is_active)
SELECT 'student123', COALESCE(NULLIF(student_email,''),'student123@example.invalid'), '$2y$12$T1AxM2v1T/5VutghYRl1K.a0evb3nYUBE4ACr7NwMNpy22LhWh9C.', 'student', student_id, 1
FROM students WHERE student_id='STU001'
ON DUPLICATE KEY UPDATE username=VALUES(username), password_hash=VALUES(password_hash), is_active=1;

-- The supplied attachment file names are metadata only; actual files must be uploaded separately.
-- Attendance is unique per student/class/date. For multiple sessions in the same class on one date,
-- revise the uniqueness rule to include a non-null session identifier.
CREATE OR REPLACE VIEW v_student_attendance_summary AS
SELECT s.student_id,s.student_name,a.class_id,
 COUNT(*) AS recorded_sessions,
 SUM(a.status='Present') AS present_sessions,
 SUM(a.status='Absent') AS absent_sessions,
 SUM(a.status='Late') AS late_sessions,
 ROUND(100.0*SUM(a.status IN ('Present','Late'))/NULLIF(SUM(a.status IN ('Present','Late','Absent')),0),2) AS attendance_percentage
FROM students s JOIN attendance_records a ON s.student_id=a.student_id
GROUP BY s.student_id,s.student_name,a.class_id;
