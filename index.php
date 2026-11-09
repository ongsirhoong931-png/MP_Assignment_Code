<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';

$role = logged_in() ? (string) me()['role'] : '';

$dashboard = match ($role) {
    'admin' => 'admin/dashboard.php',
    'staff' => 'tutor/dashboard.php',
    'student' => 'student/dashboard.php',
    default => 'auth/login.php'
};

require __DIR__ . '/includes/header.php';
?>

<!-- HERO SECTION -->
<section class="home-hero" id="home">

    <div class="hero-copy">

        <div class="eyebrow">
            INNOVATIVE LEARNING WITH EDUVEX ACADEMY
        </div>

        <h1>
            Smarter Learning,<br>
            <span>Effortless Management.</span>
        </h1>

        <p>
            Empowering administrators, tutors, and students on a single platform. Take full control of classes, schedules, and learning materials with confidence.
        </p>

        <div class="hero-actions">

            <a class="button"
               href="<?= h(url($dashboard)) ?>">

                <?= logged_in()
                    ? 'Open Dashboard'
                    : 'Get Started' ?> →
            </a>

            <a class="button button-outline"
               href="#features">
                Explore Features
            </a>

        </div>
    </div>

    <!-- SYSTEM PREVIEW () -->
    <div class="hero-preview">

        <div class="preview-title">
            <span class="status-dot"></span>
            
        </div>

        <!-- Dynamic Profile Link based on Role -->
        <?php if ($role === 'admin'): ?>
            <a href="<?= h(url('admin/profile.php')) ?>" class="preview-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; transition: background 0.2s ease;">
                <span></span>
                Admin Profile
            </a>
        <?php elseif ($role === 'staff'): ?>
            <a href="<?= h(url('tutor/profile.php')) ?>" class="preview-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; transition: background 0.2s ease;">
                <span>👤</span>
                Tutor Profile
            </a>
        <?php elseif ($role === 'student'): ?>
            <a href="<?= h(url('student_management/view_student.php?id=' . urlencode(me()['student_id'] ?? ''))) ?>" class="preview-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; transition: background 0.2s ease;">
                <span></span>
                Student Profile
            </a>
        <?php endif; ?>

        <!-- 1. Student Management Link -->
        <a href="<?= h(url('student_management/index.php')) ?>" class="preview-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; transition: background 0.2s ease;">
            <span>♙</span>
            Student Management
        </a>

        <!-- 2. Tutor Management Link (Admin Only) -->
        <?php if ($role === 'admin'): ?>
            <a href="<?= h(url('tutor_management/index.php')) ?>" class="preview-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; transition: background 0.2s ease;">
                <span>🗂</span>
                Tutor Management
            </a>
        <?php endif; ?>

        <!-- 3. Class Management Link -->
        <a href="<?= h(url('class_management/index.php')) ?>" class="preview-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; transition: background 0.2s ease;">
            <span>▤</span>
            Class Management
        </a>

        <!-- 4. Attendance Tracking Link (Role Differentiated) -->
        <a href="<?= h(url($role === 'admin' ? 'admin/attendance.php' : ($role === 'staff' ? 'tutor/attendance.php' : 'student/attendance.php'))) ?>" class="preview-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; transition: background 0.2s ease;">
            <span>▦</span>
            <?= $role === 'admin' ? 'Attendance Overview' : ($role === 'staff' ? 'Attendance Management' : 'Mark Attendance') ?>
        </a>

        <!-- 5. Timetable Management Link -->
        <a href="<?= h(url('class_management/timetable.php')) ?>" class="preview-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; transition: background 0.2s ease;">
            <span>◷</span>
            Timetable Management
        </a>

    </div>

</section>

<!-- FEATURES SECTION -->
<section class="home-section" id="features">

    <div class="eyebrow">
        BUILT FOR YOUR TUITION CENTRE
    </div>

    <h2>Everything in One Place</h2>

    <div class="feature-grid">

        <article class="feature-card">
            <span class="feature-symbol">♙</span>
            <h3>Students</h3>
            <p>
                Manage student profiles
                and enrolments.
            </p>
        </article>

        <article class="feature-card">
            <span class="feature-symbol">▤</span>
            <h3>Classes</h3>
            <p>
                Organize classes, subjects
                and schedules.
            </p>
        </article>

        <article class="feature-card">
            <span class="feature-symbol">▦</span>
            <h3>Attendance</h3>
            <p>
                Track participation and
                attendance records.
            </p>
        </article>

        <article class="feature-card">
            <span class="feature-symbol">▥</span>
            <h3>Study Materials</h3>
            <p>
                Keep learning resources
                organized.
            </p>
        </article>

        <article class="feature-card">
            <span class="feature-symbol">◷</span>
            <h3>Timetable</h3>
            <p>
                Access and manage
                class schedules.
            </p>
        </article>

        <!-- 7. User Access Card -->
        <a href="<?= h(url($role === 'admin' ? 'auth/users.php' : 'auth/login.php')) ?>" class="dashboard-card-link" style="text-decoration: none; color: inherit; display: block; transition: transform 0.2s ease;">
            <article class="feature-card">
                <span class="feature-symbol">⚙</span>
                <h3>User Access</h3>
                <p>
                    Role-based access for admins,
                    tutors and students.
                </p>
            </article>
        </a>

    </div>

</section>

<!-- ABOUT SECTION -->
<section class="home-section about-section" id="about">

    <div class="eyebrow">
        ABOUT EDUVEX ACADEMY
    </div>

    <h2>Empowering Students. Inspiring Success.</h2>

    <p>
        At EDUVEX Academy, we believe every student has the potential to succeed. Our mission is to create a supportive and engaging learning environment where students can build confidence, strengthen their knowledge, and achieve their academic goals.

With our modern digital management system, we bring students, tutors, and administrators together on one connected platform, making learning and tuition centre management simpler, smarter, and more efficient.
    </p>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>