-- EDUVEX ACADEMY - COMPLETE XAMPP/MARIADB IMPORT
-- Database: tuition_attendance_db
-- SAFE STRUCTURE SETUP: CREATE MISSING TABLES ONLY

CREATE DATABASE IF NOT EXISTS tuition_attendance_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE tuition_attendance_db;


-- ==========================================================
-- 1. ADMINS
-- ==========================================================

CREATE TABLE IF NOT EXISTS admins (
    admin_id VARCHAR(12) PRIMARY KEY,
    admin_name VARCHAR(120) NOT NULL,
    admin_email VARCHAR(180) UNIQUE,
    admin_phone_number VARCHAR(30)
) ENGINE=InnoDB;


-- ==========================================================
-- 2. STAFF
-- ==========================================================

CREATE TABLE IF NOT EXISTS staff (
    staff_id VARCHAR(12) PRIMARY KEY,
    staff_name VARCHAR(120) NOT NULL,
    staff_email VARCHAR(180) UNIQUE,
    staff_phone_number VARCHAR(30),
    staff_role VARCHAR(60)
) ENGINE=InnoDB;


-- ==========================================================
-- 3. SUBJECTS
-- ==========================================================

CREATE TABLE IF NOT EXISTS subjects (
    subject_id VARCHAR(12) PRIMARY KEY,
    subject_name VARCHAR(180) NOT NULL,
    subject_code VARCHAR(30) UNIQUE,
    department VARCHAR(120)
) ENGINE=InnoDB;


-- ==========================================================
-- 4. CLASSES
-- ==========================================================

CREATE TABLE IF NOT EXISTS classes (
    class_id VARCHAR(12) PRIMARY KEY,
    grade_level VARCHAR(60),
    section VARCHAR(30),
    academic_year SMALLINT,
    class_teacher_id VARCHAR(12)
) ENGINE=InnoDB;


-- ==========================================================
-- 5. STUDENTS
-- ==========================================================

CREATE TABLE IF NOT EXISTS students (
    student_id VARCHAR(12) PRIMARY KEY,
    student_name VARCHAR(120) NOT NULL,
    student_email VARCHAR(180) UNIQUE,
    student_phone_number VARCHAR(30),
    student_class VARCHAR(12)
) ENGINE=InnoDB;


-- ==========================================================
-- 6. STUDENT ENROLMENTS
-- ==========================================================

CREATE TABLE IF NOT EXISTS student_enrolments (
    enrolment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(12) NOT NULL,
    class_id VARCHAR(12) NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_student_class (
        student_id,
        class_id
    )
) ENGINE=InnoDB;


-- ==========================================================
-- 7. TIMETABLE SCHEDULES
-- ==========================================================

CREATE TABLE IF NOT EXISTS timetable_schedules (
    schedule_id VARCHAR(12) PRIMARY KEY,
    class_id VARCHAR(12) NOT NULL,
    subject_id VARCHAR(12) NOT NULL,
    assigned_staff_id VARCHAR(12),

    day_of_week ENUM(
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    ) NOT NULL,

    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(50),
    room_type VARCHAR(60)
) ENGINE=InnoDB;


-- ==========================================================
-- 8. ACADEMIC CALENDAR
-- ==========================================================

CREATE TABLE IF NOT EXISTS academic_calendar_overrides (
    override_id VARCHAR(12) PRIMARY KEY,
    `date` DATE NOT NULL UNIQUE,
    day_type VARCHAR(60) NOT NULL,
    description VARCHAR(255),
    is_tuition_day TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;


-- ==========================================================
-- 9. LEAVE APPLICATIONS
-- ==========================================================

CREATE TABLE IF NOT EXISTS leave_applications (
    leave_id VARCHAR(12) PRIMARY KEY,
    applicant_id VARCHAR(12) NOT NULL,
    applicant_type ENUM('Student','Staff') NOT NULL,
    leave_type VARCHAR(80),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,

    status ENUM(
        'Pending',
        'Approved',
        'Rejected'
    ) NOT NULL DEFAULT 'Pending',

    approved_by_id VARCHAR(12),
    attachment_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_leave_applicant (
        applicant_id,
        applicant_type
    )
) ENGINE=InnoDB;


-- ==========================================================
-- 10. ATTENDANCE TABLES
-- ==========================================================

CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(12) NOT NULL,
    class_id VARCHAR(12) NOT NULL,
    attendance_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Present',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_records (
    attendance_id VARCHAR(12) PRIMARY KEY,
    student_id VARCHAR(12) NOT NULL,
    class_id VARCHAR(12) NOT NULL,
    `date` DATE NOT NULL,

    status ENUM(
        'Present',
        'Absent',
        'Late',
        'Excused'
    ) NOT NULL,

    schedule_id VARCHAR(12),
    marked_by_staff_id VARCHAR(12),
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_student_class_date (
        student_id,
        class_id,
        `date`
    ),

    INDEX idx_attendance_date (`date`)
) ENGINE=InnoDB;


-- ==========================================================
-- 11. STUDY MATERIALS
-- ==========================================================

CREATE TABLE IF NOT EXISTS study_materials (
    material_id VARCHAR(12) PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    subject_id VARCHAR(12),
    class_id VARCHAR(12),
    created_by_staff_id VARCHAR(12),
    content_type VARCHAR(60),
    rich_text_content LONGTEXT,
    is_published TINYINT(1) DEFAULT 0,
    release_date DATE
) ENGINE=InnoDB;


-- ==========================================================
-- 12. MATERIAL ATTACHMENTS
-- ==========================================================

CREATE TABLE IF NOT EXISTS material_attachments (
    attachment_id VARCHAR(16) PRIMARY KEY,
    material_id VARCHAR(12) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(30),
    file_path VARCHAR(500),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ==========================================================
-- 13. USER ACCOUNTS (WITH PROFILE IMAGE)
-- ==========================================================

CREATE TABLE IF NOT EXISTS user_accounts (
    user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    role ENUM(
        'admin',
        'staff',
        'student'
    ) NOT NULL,

    admin_id VARCHAR(12) UNIQUE NULL,
    staff_id VARCHAR(12) UNIQUE NULL,
    student_id VARCHAR(12) UNIQUE NULL,

    preferred_language VARCHAR(10) DEFAULT 'en',
    profile_image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ==========================================================
-- 14. PASSWORD RESET TOKENS
-- ==========================================================

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,

    INDEX idx_password_reset_user (user_id)
) ENGINE=InnoDB;


-- ==========================================================
-- 15. ANNOUNCEMENTS
-- ==========================================================

CREATE TABLE IF NOT EXISTS announcements (
    announcement_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,

    audience ENUM(
        'all',
        'staff',
        'student'
    ) DEFAULT 'all',

    class_id VARCHAR(12),
    created_by_admin_id VARCHAR(12),
    created_by_staff_id VARCHAR(12),
    published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL
) ENGINE=InnoDB;


-- ==========================================================
-- 16. ANNOUNCEMENT READS
-- ==========================================================

CREATE TABLE IF NOT EXISTS announcement_reads (
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (
        announcement_id,
        user_id
    )
) ENGINE=InnoDB;


-- ==========================================================
-- 17. ATTENDANCE LESSONS
-- ==========================================================

CREATE TABLE IF NOT EXISTS attendance_lessons (
    lesson_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id VARCHAR(12) NOT NULL,
    class_id VARCHAR(12) NOT NULL,
    teacher_id VARCHAR(12) NOT NULL,
    lesson_date DATE NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    topic VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_schedule_lesson_date (
        schedule_id,
        lesson_date
    ),

    INDEX idx_lesson_teacher (
        teacher_id,
        starts_at
    ),

    INDEX idx_lesson_class (
        class_id,
        starts_at
    )
) ENGINE=InnoDB;


-- ==========================================================
-- 18. ATTENDANCE SESSIONS
-- ==========================================================

CREATE TABLE IF NOT EXISTS attendance_sessions (
    session_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id BIGINT UNSIGNED NOT NULL,
    attendance_code CHAR(6) NOT NULL,

    status ENUM(
        'draft',
        'open',
        'closed'
    ) NOT NULL DEFAULT 'draft',

    generated_by_staff_id VARCHAR(12) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,

    UNIQUE KEY uq_att_session_lesson (lesson_id),
    UNIQUE KEY uq_attendance_code (attendance_code),
    INDEX idx_att_session_status (status)
) ENGINE=InnoDB;


-- ==========================================================
-- 19. ATTENDANCE SUBMISSIONS
-- ==========================================================

CREATE TABLE IF NOT EXISTS attendance_submissions (
    submission_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    student_id VARCHAR(12) NOT NULL,

    attendance_status ENUM(
        'Present',
        'Late'
    ) NOT NULL DEFAULT 'Present',

    attendance_method ENUM('code') NOT NULL DEFAULT 'code',

    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_att_student_session (
        session_id,
        student_id
    ),

    INDEX idx_att_submission_student (student_id),
    INDEX idx_att_submission_time (submitted_at)
) ENGINE=InnoDB;


-- ==========================================================
-- 20. ATTENDANCE ATTEMPTS
-- ==========================================================

CREATE TABLE IF NOT EXISTS attendance_attempts (
    attempt_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(12) NOT NULL,
    session_id BIGINT UNSIGNED NULL,

    attendance_method ENUM('code') NOT NULL DEFAULT 'code',

    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_att_attempt_student_time (
        student_id,
        attempted_at
    ),

    INDEX idx_att_attempt_session (session_id)
) ENGINE=InnoDB;


-- ==========================================================
-- SEED DATA INSERTS
-- ==========================================================

INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM001', 'Ahmad Hakim', 'ahmad.hakim.adm001@gmail.com', '010-000-1001');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM002', 'Tan Wei Ming', 'tan.weiming.adm002@yahoo.com', '011-0000-1002');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM003', 'Nur Aisyah', 'nur.aisyah.adm003@outlook.com', '012-000-1003');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM004', 'Lim Jia Hui', 'lim.jiahui.adm004@gmail.com', '013-000-1004');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM005', 'Muhammad Danish', 'danish.adm005@yahoo.com', '014-000-1005');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM006', 'Lee Xin Yi', 'lee.xinyi.adm006@outlook.com', '016-000-1006');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM007', 'Siti Nurul', 'siti.nurul.adm007@gmail.com', '017-000-1007');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM008', 'Wong Jun Hao', 'wong.junhao.adm008@yahoo.com', '018-000-1008');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM009', 'Chong Mei Ling', 'chong.meiling.adm009@outlook.com', '019-000-1009');
INSERT IGNORE INTO `admins` (`admin_id`, `admin_name`, `admin_email`, `admin_phone_number`) VALUES ('ADM010', 'Farah Nadia', 'farah.nadia.adm010@gmail.com', '011-0000-1010');

INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF001', 'Ahmad Faiz', 'ahmad.faiz.staff001@gmail.com', '012-000-2001', 'Lecturer');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF002', 'Tan Wei Jian', 'tan.weijian.staff002@yahoo.com', '011-0000-2002', 'Tutor');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF003', 'Nur Amirah', 'nur.amirah.staff003@outlook.com', '013-000-2003', 'Lecturer');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF004', 'Lim Kok Leong', 'lim.kokleong.staff004@gmail.com', '016-000-2004', 'Supervisor');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF005', 'Siti Hajar', 'siti.hajar.staff005@yahoo.com', '017-000-2005', 'Tutor');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF006', 'Wong Jia Wen', 'wong.jiawen.staff006@outlook.com', '018-000-2006', 'Lecturer');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF007', 'Muhammad Hafiz', 'muhammad.hafiz.staff007@gmail.com', '019-000-2007', 'Tutor');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF008', 'Lee Mei Xin', 'lee.meixin.staff008@yahoo.com', '014-000-2008', 'Supervisor');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF009', 'Farah Izzati', 'farah.izzati.staff009@outlook.com', '012-000-2009', 'Lecturer');
INSERT IGNORE INTO `staff` (`staff_id`, `staff_name`, `staff_email`, `staff_phone_number`, `staff_role`) VALUES ('STF010', 'Chong Jun Wei', 'chong.junwei.staff010@gmail.com', '011-0000-2010', 'Tutor');

INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB001', 'Introduction to Information Technology', 'IT101', 'Information Technology');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB002', 'Web Application Development', 'IT102', 'Information Technology');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB003', 'Database Management Systems', 'IT103', 'Information Technology');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB004', 'Computer Networking', 'IT104', 'Information Technology');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB005', 'Software Engineering', 'IT105', 'Information Technology');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB006', 'Object-Oriented Programming', 'IT106', 'Information Technology');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB007', 'Operating Systems', 'IT107', 'Information Technology');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB008', 'Data Structures and Algorithms', 'IT108', 'Computer Science');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB009', 'Cybersecurity Fundamentals', 'IT109', 'Information Security');
INSERT IGNORE INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `department`) VALUES ('SUB010', 'System Analysis and Design', 'IT110', 'Information Technology');

INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS001', 'Year 1', 'A', 2026, 'STF001');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS002', 'Year 1', 'B', 2026, 'STF002');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS003', 'Year 1', 'C', 2026, 'STF003');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS004', 'Year 2', 'A', 2026, 'STF004');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS005', 'Year 2', 'B', 2026, 'STF005');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS006', 'Year 2', 'C', 2026, 'STF006');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS007', 'Year 3', 'A', 2026, 'STF007');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS008', 'Year 3', 'B', 2026, 'STF008');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS009', 'Year 3', 'C', 2026, 'STF009');
INSERT IGNORE INTO `classes` (`class_id`, `grade_level`, `section`, `academic_year`, `class_teacher_id`) VALUES ('CLS010', 'Year 3', 'D', 2026, 'STF010');

INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU001', 'Wong Jun Jie', 'wong.junjie.stu001@gmail.com', '012-000-3001', 'CLS001');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU002', 'Tan Jia Hao', 'tan.jiahao.stu002@yahoo.com', '011-0000-3002', 'CLS001');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU003', 'Nur Aina', 'nur.aina.stu003@outlook.com', '013-000-3003', 'CLS001');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU004', 'Lim Wei Sheng', 'lim.weisheng.stu004@gmail.com', '016-000-3004', 'CLS002');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU005', 'Lee Xin Rou', 'lee.xinrou.stu005@yahoo.com', '017-000-3005', 'CLS002');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU006', 'Muhammad Amir', 'muhammad.amir.stu006@outlook.com', '018-000-3006', 'CLS002');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU007', 'Chong Jia Yi', 'chong.jiayi.stu007@gmail.com', '019-000-3007', 'CLS003');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU008', 'Siti Aisyah', 'siti.aisyah.stu008@yahoo.com', '014-000-3008', 'CLS003');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU009', 'Ng Wei Lun', 'ng.weilun.stu009@outlook.com', '012-000-3009', 'CLS003');
INSERT IGNORE INTO `students` (`student_id`, `student_name`, `student_email`, `student_phone_number`, `student_class`) VALUES ('STU010', 'Farah Izzah', 'farah.izzah.stu010@gmail.com', '011-0000-3010', 'CLS004');

INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH001', 'CLS001', 'SUB001', 'STF001', 'Monday', '08:00:00', '10:00:00', 'DKABA', 'Lecture');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH002', 'CLS002', 'SUB002', 'STF002', 'Tuesday', '10:00:00', '12:00:00', 'DKZ', 'Lecture');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH003', 'CLS003', 'SUB003', 'STF003', 'Wednesday', '09:00:00', '11:00:00', 'B101', 'Tutorial');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH004', 'CLS004', 'SUB003', 'STF004', 'Thursday', '11:00:00', '13:00:00', 'B102', 'Practical');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH005', 'CLS005', 'SUB004', 'STF005', 'Friday', '08:00:00', '10:00:00', 'C201', 'Practical');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH006', 'CLS006', 'SUB005', 'STF006', 'Monday', '14:00:00', '16:00:00', 'C202', 'Tutorial');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH007', 'CLS007', 'SUB002', 'STF007', 'Tuesday', '13:00:00', '15:00:00', 'D301', 'Practical');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH008', 'CLS008', 'SUB005', 'STF008', 'Wednesday', '15:00:00', '17:00:00', 'D302', 'Tutorial');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH009', 'CLS009', 'SUB004', 'STF009', 'Thursday', '09:00:00', '11:00:00', 'E101', 'Lecture');
INSERT IGNORE INTO `timetable_schedules` (`schedule_id`, `class_id`, `subject_id`, `assigned_staff_id`, `day_of_week`, `start_time`, `end_time`, `room_number`, `room_type`) VALUES ('SCH010', 'CLS010', 'SUB005', 'STF010', 'Friday', '14:00:00', '16:00:00', 'E102', 'Practical');

-- DEFAULT LOGINS
INSERT IGNORE INTO user_accounts
    (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
VALUES
    ('admin123', 'admin123@eduvex.test',
     '$2y$12$cJjOHhSLCfUZZBKaVR4yvOpCWHRdOBF0/2owOeJwmaPeEkN/hNhXm',
     'admin', 'ADM001', NULL, NULL, 1);

INSERT IGNORE INTO user_accounts
    (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
VALUES
    ('staff123', 'staff123@eduvex.test',
     '$2y$12$zRBEwr0jzjlhe4p6vm28I.sIYwjG9fJ6Vorskjw5.ZpPTW7HiFRUi',
     'staff', NULL, 'STF001', NULL, 1);

INSERT IGNORE INTO user_accounts
    (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
VALUES
    ('student123', 'student123@eduvex.test',
     '$2y$12$ODC.0efobva31e.URuQuWud48U6Gk5AmMwDRw5IjnA.nwi4MIJFyq',
     'student', NULL, NULL, 'STU001', 1);

SHOW FULL TABLES;