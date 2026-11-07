-- EDUVEX ACADEMY - COMPLETE XAMPP/MARIADB IMPORT
-- Database: tuition_attendance_db
-- Based on the uploaded EDUVEX records and existing schema.
-- Creates missing tables and adds missing sample data / login accounts.
-- No DROP TABLE, DROP DATABASE, DELETE, PREPARE, or stored procedures.
-- Existing login accounts/password hashes are NOT overwritten.
-- For local coursework testing; change example passwords before deployment.


-- ==========================================================
-- EDUVEX ACADEMY
-- COMPLETE DATABASE STRUCTURE + ATTENDANCE SYSTEM
-- XAMPP / MYSQL / MARIADB
--
-- SAFE STRUCTURE SETUP:
-- CREATE MISSING TABLES ONLY
-- NO DROP TABLE
-- NO DELETE
-- ==========================================================

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
-- 10. EXISTING ATTENDANCE RECORDS
-- PRESERVED FOR ORIGINAL ATTENDANCE MODULE
-- ==========================================================

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
-- 13. USER ACCOUNTS
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
-- NEW ATTENDANCE SYSTEM
-- ==========================================================


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
    code_nonce CHAR(32) NOT NULL,

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
    UNIQUE KEY uq_att_session_nonce (code_nonce),
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

    attendance_method ENUM(
        'code',
        'qr'
    ) NOT NULL,

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

    attendance_method ENUM(
        'code',
        'qr'
    ) NOT NULL DEFAULT 'code',

    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_att_attempt_student_time (
        student_id,
        attempted_at
    ),

    INDEX idx_att_attempt_session (session_id)
) ENGINE=InnoDB;


-- ==========================================================
-- VIEW 1: ATTENDANCE SESSION DETAILS
-- ==========================================================

CREATE OR REPLACE VIEW v_eduvex_attendance_sessions AS
SELECT
    ats.session_id,
    al.lesson_id,
    al.schedule_id,
    al.class_id,
    c.grade_level,
    c.section,
    c.academic_year,
    al.teacher_id,
    st.staff_name AS teacher_name,
    ts.subject_id,
    sub.subject_name,
    al.topic,
    al.lesson_date,
    al.starts_at,
    al.ends_at,

    DATE_SUB(
        al.starts_at,
        INTERVAL 2 HOUR
    ) AS attendance_window_start,

    DATE_ADD(
        al.ends_at,
        INTERVAL 2 HOUR
    ) AS attendance_window_end,

    ats.status,
    ats.generated_by_staff_id,
    ats.created_at,
    ats.opened_at,
    ats.closed_at

FROM attendance_sessions ats

INNER JOIN attendance_lessons al
    ON ats.lesson_id = al.lesson_id

INNER JOIN classes c
    ON al.class_id = c.class_id

INNER JOIN staff st
    ON al.teacher_id = st.staff_id

INNER JOIN timetable_schedules ts
    ON al.schedule_id = ts.schedule_id

INNER JOIN subjects sub
    ON ts.subject_id = sub.subject_id;


-- ==========================================================
-- VIEW 2: STUDENT ATTENDANCE SUBMISSIONS
-- ==========================================================

CREATE OR REPLACE VIEW v_eduvex_attendance_submissions AS
SELECT
    a.submission_id,
    a.session_id,
    a.student_id,
    s.student_name,
    l.class_id,
    c.grade_level,
    c.section,
    l.lesson_date,
    l.starts_at,
    l.ends_at,
    a.attendance_status,
    a.attendance_method,
    a.submitted_at,
    l.teacher_id,
    st.staff_name AS teacher_name

FROM attendance_submissions a

INNER JOIN students s
    ON a.student_id = s.student_id

INNER JOIN attendance_sessions ats
    ON a.session_id = ats.session_id

INNER JOIN attendance_lessons l
    ON ats.lesson_id = l.lesson_id

INNER JOIN classes c
    ON l.class_id = c.class_id

INNER JOIN staff st
    ON l.teacher_id = st.staff_id;


-- ==========================================================
-- VIEW 3: EXISTING ATTENDANCE SUMMARY
-- ==========================================================

CREATE OR REPLACE VIEW v_student_attendance_summary AS
SELECT
    s.student_id,
    s.student_name,
    a.class_id,

    COUNT(*) AS recorded_sessions,

    SUM(a.status = 'Present') AS present_sessions,

    SUM(a.status = 'Absent') AS absent_sessions,

    SUM(a.status = 'Late') AS late_sessions,

    ROUND(
        100.0 *
        SUM(a.status IN ('Present','Late')) /
        NULLIF(
            SUM(a.status IN ('Present','Late','Absent')),
            0
        ),
        2
    ) AS attendance_percentage

FROM students s

INNER JOIN attendance_records a
    ON s.student_id = a.student_id

GROUP BY
    s.student_id,
    s.student_name,
    a.class_id;


-- ==========================================================
-- VERIFY DATABASE TABLES
-- ==========================================================

SHOW FULL TABLES;


-- ==========================================================
-- END
-- ==========================================================




-- DATA FROM THE UPLOADED EXCEL WORKBOOKS
-- Source: Admin_Details(2).xlsx (10 records)
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

-- Source: Staff_Details(2).xlsx (10 records)
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

-- Source: Subjects_Master(2).xlsx (10 records)
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

-- Source: Classes_Master(2).xlsx (10 records)
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

-- Source: Students_Details(2).xlsx (10 records)
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

-- Source: Timetable_Schedules(2).xlsx (10 records)
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

-- Source: Academic_Calendar_Overrides(2).xlsx (10 records)
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV001', '2026-12-25', 'Public Holiday', 'Christmas Day');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV002', '2027-01-01', 'Public Holiday', 'New Year''s Day');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV003', '2027-01-09', 'Replacement Class', 'Saturday replacement class');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV004', '2027-01-15', 'University Event', 'Student orientation programme');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV005', '2027-02-01', 'Special Holiday', 'University special holiday');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV006', '2027-02-06', 'Replacement Class', 'Replacement for cancelled lecture');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV007', '2027-02-12', 'University Event', 'University sports day');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV008', '2027-03-05', 'Exam Day', 'Semester examination');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV009', '2027-03-15', 'Semester Break', 'Mid-semester break');
INSERT IGNORE INTO `academic_calendar_overrides` (`override_id`, `date`, `day_type`, `description`) VALUES ('OV010', '2027-04-10', 'Replacement Class', 'Additional Saturday lecture');

-- Source: Leave_Applications(2).xlsx (10 records)
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV001', 'STU001', 'Student', 'Medical Leave', '2026-11-02', '2026-11-03', 'Fever', 'Approved', 'ADM001');
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV002', 'STU002', 'Student', 'Personal Leave', '2026-11-04', '2026-11-04', 'Family matters', 'Approved', 'ADM002');
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV003', 'STU003', 'Student', 'Medical Leave', '2026-11-05', '2026-11-06', 'Food poisoning', 'Pending', NULL);
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV004', 'STU004', 'Student', 'Emergency Leave', '2026-11-09', '2026-11-10', 'Family emergency', 'Approved', 'ADM003');
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV005', 'STU005', 'Student', 'Personal Leave', '2026-11-12', '2026-11-12', 'Personal appointment', 'Rejected', 'ADM004');
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV006', 'STF001', 'Staff', 'Medical Leave', '2026-11-16', '2026-11-17', 'Medical treatment', 'Approved', 'ADM001');
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV007', 'STF002', 'Staff', 'Annual Leave', '2026-11-18', '2026-11-20', 'Family vacation', 'Approved', 'ADM002');
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV008', 'STU006', 'Student', 'Medical Leave', '2026-11-23', '2026-11-24', 'Hospital appointment', 'Pending', NULL);
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV009', 'STF003', 'Staff', 'Emergency Leave', '2026-11-25', '2026-11-25', 'Urgent family matter', 'Rejected', 'ADM003');
INSERT IGNORE INTO `leave_applications` (`leave_id`, `applicant_id`, `applicant_type`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `approved_by_id`) VALUES ('LV010', 'STU007', 'Student', 'Personal Leave', '2026-11-26', '2026-11-27', 'Family event', 'Pending', NULL);

-- Source: Attendance_Records(2).xlsx (10 records)
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT001', 'STU001', 'CLS001', '2026-11-02', 'Present');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT002', 'STU002', 'CLS001', '2026-11-02', 'Present');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT003', 'STU003', 'CLS001', '2026-11-02', 'Absent');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT004', 'STU004', 'CLS002', '2026-11-03', 'Present');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT005', 'STU005', 'CLS002', '2026-11-03', 'Late');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT006', 'STU006', 'CLS002', '2026-11-03', 'Present');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT007', 'STU007', 'CLS003', '2026-11-04', 'Absent');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT008', 'STU008', 'CLS003', '2026-11-04', 'Present');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT009', 'STU009', 'CLS003', '2026-11-04', 'Late');
INSERT IGNORE INTO `attendance_records` (`attendance_id`, `student_id`, `class_id`, `date`, `status`) VALUES ('ATT010', 'STU010', 'CLS004', '2026-11-05', 'Present');

-- Source: Study_Materials(2).xlsx (10 records)
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT001', 'Introduction to IT', 'Basic IT concepts', 'SUB001', 'CLS001', 'STF001', 'PDF', 'Introduction to computer systems and hardware.', 1, '2026-11-02');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT002', 'PHP Programming', 'PHP syntax and variables', 'SUB002', 'CLS002', 'STF002', 'PDF', 'Learn PHP variables, operators and functions.', 1, '2026-11-03');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT003', 'Database Design', 'ERD and database relationships', 'SUB003', 'CLS003', 'STF003', 'Image', 'Example of entity relationship diagrams.', 1, '2026-11-04');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT004', 'SQL Tutorial', 'SQL practice questions', 'SUB003', 'CLS004', 'STF004', 'Document', 'Practice SQL SELECT, INSERT, UPDATE and DELETE.', 1, '2026-11-05');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT005', 'Networking Basics', 'Introduction to computer networks', 'SUB004', 'CLS005', 'STF005', 'Presentation', 'Introduction to IP addressing and networking.', 1, '2026-11-06');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT006', 'Assignment Guidelines', 'Coursework instructions', 'SUB005', 'CLS006', 'STF006', 'PDF', 'Guidelines for completing coursework.', 1, '2026-11-09');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT007', 'PHP Code Examples', 'Sample PHP programs', 'SUB002', 'CLS007', 'STF007', 'ZIP', 'Sample PHP source code for practice.', 0, '2026-11-10');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT008', 'Revision Notes', 'Software development revision', 'SUB005', 'CLS008', 'STF008', 'PDF', 'Revision notes for software development.', 1, '2026-11-11');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT009', 'Networking Lab', 'Network configuration exercises', 'SUB004', 'CLS009', 'STF009', 'Document', 'Network configuration lab instructions.', 0, '2026-11-12');
INSERT IGNORE INTO `study_materials` (`material_id`, `title`, `description`, `subject_id`, `class_id`, `created_by_staff_id`, `content_type`, `rich_text_content`, `is_published`, `release_date`) VALUES ('MAT010', 'Software Design', 'UML and system design', 'SUB005', 'CLS010', 'STF010', 'Image', 'Examples of UML diagrams.', 1, '2026-11-13');

-- Source: Material_Attachments(2).xlsx (10 records)
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH001', 'MAT001', 'Lecture_1_Introduction.pdf', 'PDF');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH002', 'MAT002', 'Programming_Notes.pdf', 'PDF');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH003', 'MAT003', 'Database_ERD.png', 'PNG');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH004', 'MAT004', 'Tutorial_Questions.docx', 'DOCX');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH005', 'MAT005', 'Networking_Slides.pptx', 'PPTX');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH006', 'MAT006', 'Assignment_Guidelines.pdf', 'PDF');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH007', 'MAT007', 'PHP_Example_Code.zip', 'ZIP');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH008', 'MAT008', 'Revision_Notes.pdf', 'PDF');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH009', 'MAT009', 'Lab_Instructions.docx', 'DOCX');
INSERT IGNORE INTO `material_attachments` (`attachment_id`, `material_id`, `file_name`, `file_type`) VALUES ('ATTCH010', 'MAT010', 'Software_Design_Diagram.png', 'PNG');

-- Student enrolments derived only from Students_Details student_class assignments
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU001', 'CLS001');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU002', 'CLS001');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU003', 'CLS001');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU004', 'CLS002');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU005', 'CLS002');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU006', 'CLS002');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU007', 'CLS003');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU008', 'CLS003');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU009', 'CLS003');
INSERT IGNORE INTO `student_enrolments` (`student_id`,`class_id`) VALUES ('STU010', 'CLS004');


-- DEFAULT LOGINS (ONLY IF MISSING)
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

-- ADDITIONAL LOGINS (ONLY IF MISSING)
-- EDUVEX Academy: add missing administrator login accounts only
-- Does not update, delete or replace any existing admin or user account.
-- ADM001 is already linked to admin123 and is intentionally unchanged.
-- Passwords are bcrypt hashes generated using PHP password_hash().
-- For local development; change initial passwords before production.
-- Tan Wei Ming: admin002 / Admin002@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin002', 'tan.weiming.adm002@yahoo.com', '$2y$12$e6JkdIS9wNYDUn5MF42jOO76Xc0xES97cCoYbGGiCOXsmSY4dg9bC', 'admin', 'ADM002', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM002')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM002')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin002')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'tan.weiming.adm002@yahoo.com');

-- Nur Aisyah: admin003 / Admin003@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin003', 'nur.aisyah.adm003@outlook.com', '$2y$12$Hc4JiiDgMx6d8TBOEv7UtuZyWMesvKxpQwfehJYOF2fsP1OTj5MpO', 'admin', 'ADM003', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM003')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM003')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin003')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'nur.aisyah.adm003@outlook.com');

-- Lim Jia Hui: admin004 / Admin004@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin004', 'lim.jiahui.adm004@gmail.com', '$2y$12$7RVNstC7Krcj2iufUYhSQOg38LGAZdMsgvXcrsjeiFU35cALtm/MS', 'admin', 'ADM004', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM004')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM004')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin004')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'lim.jiahui.adm004@gmail.com');

-- Muhammad Danish: admin005 / Admin005@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin005', 'danish.adm005@yahoo.com', '$2y$12$X9qzQH0RxYtw7ULAWfaSAuNaRH5aFHIr5j0KJ43v4G9fpY7tkkPti', 'admin', 'ADM005', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM005')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM005')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin005')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'danish.adm005@yahoo.com');

-- Lee Xin Yi: admin006 / Admin006@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin006', 'lee.xinyi.adm006@outlook.com', '$2y$12$fTOe4qMGo3jLPs7objoRTe2gPbdxCH0Q3oHakVKV.HoApnxEC1mY.', 'admin', 'ADM006', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM006')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM006')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin006')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'lee.xinyi.adm006@outlook.com');

-- Siti Nurul: admin007 / Admin007@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin007', 'siti.nurul.adm007@gmail.com', '$2y$12$UJNEcdHnco0F6OsWF2dLueXlLkmVGunR5e5KscLJuqRWkm5593Nve', 'admin', 'ADM007', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM007')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM007')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin007')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'siti.nurul.adm007@gmail.com');

-- Wong Jun Hao: admin008 / Admin008@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin008', 'wong.junhao.adm008@yahoo.com', '$2y$12$PEtRBS.ddxEN1TAdvMvVDeJdYP6seGDPEymD7z.u7fYlwhcPrtymm', 'admin', 'ADM008', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM008')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM008')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin008')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'wong.junhao.adm008@yahoo.com');

-- Chong Mei Ling: admin009 / Admin009@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin009', 'chong.meiling.adm009@outlook.com', '$2y$12$SqnSpUU6e/P4NfDstIVajuqWPhYk1qSRsFAGOHCAiUHK3g4eu3S86', 'admin', 'ADM009', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM009')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM009')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin009')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'chong.meiling.adm009@outlook.com');

-- Farah Nadia: admin010 / Admin010@2026
INSERT INTO user_accounts (username, email, password_hash, role, admin_id, staff_id, student_id, is_active)
SELECT 'admin010', 'farah.nadia.adm010@gmail.com', '$2y$12$aRLKf4SPCehyrk4U/sR1LeLjFHpB1BjPGfZrSl7iHEtQCzf.F7EDm', 'admin', 'ADM010', NULL, NULL, 1
WHERE EXISTS (SELECT 1 FROM admins WHERE admin_id = 'ADM010')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE admin_id = 'ADM010')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'admin010')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'farah.nadia.adm010@gmail.com');

-- Verify linked administrator accounts (existing accounts included):
SELECT a.admin_id, a.admin_name, u.username, u.role, u.is_active
FROM admins a LEFT JOIN user_accounts u ON u.admin_id = a.admin_id
WHERE a.admin_id BETWEEN 'ADM001' AND 'ADM010'
ORDER BY a.admin_id;


-- EDUVEX Academy: Add missing staff login accounts only.
-- Preserves existing staff profiles, existing logins and passwords.
-- STF001 already has username staff123; no change is made to it.
-- Run in phpMyAdmin SQL tab. For local testing only.
-- STF002: staff002 / Staff002@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff002', 'tan.weijian.staff002@yahoo.com', '$2y$12$YlQcO1dx3bmiehj1vUr1C.Ca6533O.YJzNsm8aAkXqoNx5bwPX4xu', 'staff', 'STF002', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF002')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff002' OR email = 'tan.weijian.staff002@yahoo.com' OR staff_id = 'STF002');

-- STF003: staff003 / Staff003@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff003', 'nur.amirah.staff003@outlook.com', '$2y$12$Icutn0Yc29dWB.YzGL0n6uOQ1/xwpcPO9Sggr1/qSNZny1HpuYD0S', 'staff', 'STF003', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF003')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff003' OR email = 'nur.amirah.staff003@outlook.com' OR staff_id = 'STF003');

-- STF004: staff004 / Staff004@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff004', 'lim.kokleong.staff004@gmail.com', '$2y$12$9Kp5lysyBc4CD4yfNyodYOvLqiBNGMq45htKeXlh68g8xLVkERowG', 'staff', 'STF004', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF004')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff004' OR email = 'lim.kokleong.staff004@gmail.com' OR staff_id = 'STF004');

-- STF005: staff005 / Staff005@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff005', 'siti.hajar.staff005@yahoo.com', '$2y$12$Kbvw1cpW3VBMQiCGdmrqguu5DL.THqfPerrOZlpAq8k1MuF0hrUw.', 'staff', 'STF005', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF005')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff005' OR email = 'siti.hajar.staff005@yahoo.com' OR staff_id = 'STF005');

-- STF006: staff006 / Staff006@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff006', 'wong.jiawen.staff006@outlook.com', '$2y$12$od78awgAMLu337GsGsuZRu11wHbrlZpo3DEpWl8Jcfqj3S20hSaxi', 'staff', 'STF006', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF006')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff006' OR email = 'wong.jiawen.staff006@outlook.com' OR staff_id = 'STF006');

-- STF007: staff007 / Staff007@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff007', 'muhammad.hafiz.staff007@gmail.com', '$2y$12$j1dRyF63cv3aNblHWhweB.oPDjBJe7NZYbubLcPnGps.JAFs4XCjm', 'staff', 'STF007', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF007')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff007' OR email = 'muhammad.hafiz.staff007@gmail.com' OR staff_id = 'STF007');

-- STF008: staff008 / Staff008@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff008', 'lee.meixin.staff008@yahoo.com', '$2y$12$6PGfi0dMABqlz0Qfr3RzNeqaIGY3LPTIRVwc0sxgVTltvxWE4FO4i', 'staff', 'STF008', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF008')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff008' OR email = 'lee.meixin.staff008@yahoo.com' OR staff_id = 'STF008');

-- STF009: staff009 / Staff009@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff009', 'farah.izzati.staff009@outlook.com', '$2y$12$NB2ZbzaJLnfTdG2/ZcyqBumdRLnfodKFU8tZGEBjqKwLJnbjUoqBi', 'staff', 'STF009', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF009')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff009' OR email = 'farah.izzati.staff009@outlook.com' OR staff_id = 'STF009');

-- STF010: staff010 / Staff010@2026
INSERT INTO user_accounts (username, email, password_hash, role, staff_id, is_active)
SELECT 'staff010', 'chong.junwei.staff010@gmail.com', '$2y$12$qDTjS1gVmuQYI5k5lQ6M9uinSon18UsouuTFXzqBhz9tq3pS5dN1W', 'staff', 'STF010', 1
WHERE EXISTS (SELECT 1 FROM staff WHERE staff_id = 'STF010')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'staff010' OR email = 'chong.junwei.staff010@gmail.com' OR staff_id = 'STF010');

-- Verify staff account links after importing:
SELECT s.staff_id, s.staff_name, u.username, u.email, u.role, u.is_active
FROM staff s LEFT JOIN user_accounts u ON u.staff_id = s.staff_id
WHERE s.staff_id BETWEEN 'STF001' AND 'STF010' ORDER BY s.staff_id;


-- EDUVEX ACADEMY: ADD MISSING STUDENT LOGINS ONLY
-- Existing student records and existing accounts are not modified.
-- STU001 / student123 is preserved.
-- Temporary passwords for local testing; change before production.
-- STU002: student002 / Student002@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student002', 'tan.jiahao.stu002@yahoo.com', '$2y$12$..Uv8YZyg/ZbJhqR8s8UO.FPVlMqgKuu8g4lpq8jsDMaZC2LVYbcu', 'student', 'STU002', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU002')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU002')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student002')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'tan.jiahao.stu002@yahoo.com');

-- STU003: student003 / Student003@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student003', 'nur.aina.stu003@outlook.com', '$2y$12$.v5w.6/6H7rXse2dVrfS/OlPliPY7xi1aH6aUNHurJRbnfjsp8NJ6', 'student', 'STU003', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU003')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU003')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student003')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'nur.aina.stu003@outlook.com');

-- STU004: student004 / Student004@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student004', 'lim.weisheng.stu004@gmail.com', '$2y$12$LXeWJj4Y/V/tTgXBog7OLe0dCJnipOOEkxm5S6EOqekX0HhjZ4v7a', 'student', 'STU004', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU004')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU004')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student004')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'lim.weisheng.stu004@gmail.com');

-- STU005: student005 / Student005@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student005', 'lee.xinrou.stu005@yahoo.com', '$2y$12$uFfBftJlCGoJiirJNbWsLenIum9RB1PKRu7bAzg7/v3MCogGJd/lS', 'student', 'STU005', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU005')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU005')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student005')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'lee.xinrou.stu005@yahoo.com');

-- STU006: student006 / Student006@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student006', 'muhammad.amir.stu006@outlook.com', '$2y$12$MOTX6HQ0VSyJ67MVYjXz2OEmkbh1d07eFZ2R/xoDJwD1uJidf4ZZq', 'student', 'STU006', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU006')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU006')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student006')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'muhammad.amir.stu006@outlook.com');

-- STU007: student007 / Student007@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student007', 'chong.jiayi.stu007@gmail.com', '$2y$12$opWAoSK4skRLRMLlnQ.UBOnvJ9ugflbSAdlODtJ57VzW3L2AXHcpS', 'student', 'STU007', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU007')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU007')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student007')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'chong.jiayi.stu007@gmail.com');

-- STU008: student008 / Student008@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student008', 'siti.aisyah.stu008@yahoo.com', '$2y$12$ETRZTl6a2dSWN1bzOl29ge7uy5wUTeNyf/EMXlh.6ZrEQPzzfYvdO', 'student', 'STU008', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU008')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU008')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student008')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'siti.aisyah.stu008@yahoo.com');

-- STU009: student009 / Student009@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student009', 'ng.weilun.stu009@outlook.com', '$2y$12$6EN9R4R8D.EtiRTvncOhbOT5FjTemSmU/hK7jX7JTjkqpXO6R0Kqi', 'student', 'STU009', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU009')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU009')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student009')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'ng.weilun.stu009@outlook.com');

-- STU010: student010 / Student010@2026
INSERT INTO user_accounts (username, email, password_hash, role, student_id, is_active)
SELECT 'student010', 'farah.izzah.stu010@gmail.com', '$2y$12$kGOf7W9S.UwhxkmCJHednuMpFD3E2pLYk8/MOAd4V5AUlCFouBRW6', 'student', 'STU010', 1
WHERE EXISTS (SELECT 1 FROM students WHERE student_id = 'STU010')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE student_id = 'STU010')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE username = 'student010')
  AND NOT EXISTS (SELECT 1 FROM user_accounts WHERE email = 'farah.izzah.stu010@gmail.com');

-- Check all linked student login accounts
SELECT s.student_id, s.student_name, u.username, u.email, u.role, u.is_active
FROM students s LEFT JOIN user_accounts u ON u.student_id = s.student_id
WHERE s.student_id BETWEEN 'STU001' AND 'STU010'
ORDER BY s.student_id;

-- FINAL VERIFICATION
SELECT 'admins' AS entity, COUNT(*) AS total FROM admins
UNION ALL SELECT 'staff', COUNT(*) FROM staff
UNION ALL SELECT 'students', COUNT(*) FROM students
UNION ALL SELECT 'user_accounts', COUNT(*) FROM user_accounts
UNION ALL SELECT 'classes', COUNT(*) FROM classes
UNION ALL SELECT 'subjects', COUNT(*) FROM subjects
UNION ALL SELECT 'timetable_schedules', COUNT(*) FROM timetable_schedules;
SELECT username, role, admin_id, staff_id, student_id FROM user_accounts ORDER BY role, username;
