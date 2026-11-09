
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
            ✦ YOUR DIGITAL LEARNING SPACE
        </div>

        <h1>
            Education,<br>
            <span>Simplified.</span>
        </h1>

        <p>
            One connected platform for administrators,
            tutors and students. Manage classes,
            attendance and learning with confidence.
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

    <!-- SYSTEM PREVIEW -->
    <div class="hero-preview">

        <div class="preview-title">
            <span class="status-dot"></span>
            Your Workspace
        </div>

        <div class="preview-item">
            <span>♙</span>
            Student Management
        </div>

        <div class="preview-item">
            <span>▤</span>
            Class Management
        </div>

        <div class="preview-item">
            <span>▦</span>
            Attendance Tracking
        </div>

        <div class="preview-item">
            <span>◷</span>
            Timetable Management
        </div>

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
            <a href="/class_management/index.php">Classes</a>

            <p>
                Organize classes, subjects
                and schedules.
            </p>
        </article>

        <article class="feature-card">
            <span class="feature-symbol">▦</span>
            <a href="/attendance/index.php">Study Materials</a>
            <p>
                Track participation and
                attendance records.
            </p>
        </article>

        <article class="feature-card">
            <span class="feature-symbol">▥</span>
            <a href="/materials/index.php">Study Materials</a>

            <p>
                Keep learning resources
                organized.
            </p>
        </article>

        <article class="feature-card">
            <span class="feature-symbol">◷</span>
            <a href="/class_management/timetable.php">Timetable</a>
            <p>
                Access and manage
                class schedules.
            </p>
        </article>

        <article class="feature-card">
            <span class="feature-symbol">⚙</span>
            <h3>User Access</h3>
            <p>
                Role-based access for admins,
                tutors and students.
            </p>
        </article>

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
