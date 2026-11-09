<?php
// includes/material_functions.php
// All logic for the Study Materials module lives here.
// The files in /materials only call these functions.

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';   // creates $pdo
require_once __DIR__ . '/role_check.php';           // allow_roles()

const MAT_TYPES = ['Lesson Note', 'Lab Manual', 'Assignment Brief', 'Syllabus Update', 'Study Card Set', 'PDF', 'Document', 'Presentation', 'Image', 'ZIP'];
const MAT_ALLOWED_EXT = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'zip', 'txt'];
const MAT_MAX_BYTES = 10 * 1024 * 1024; // 10 MB

/* ---------------------------------------------------------
   DATABASE CONNECTION
--------------------------------------------------------- */

// Finds the PDO connection created in config/config.php.
function mat_pdo(): PDO
{
    foreach (['pdo', 'db', 'conn', 'dbh'] as $name) {
        if (isset($GLOBALS[$name]) && $GLOBALS[$name] instanceof PDO) {
            return $GLOBALS[$name];
        }
    }
    foreach (['db', 'pdo', 'get_pdo', 'get_db', 'db_connect', 'connect_db', 'connect'] as $fn) {
        if (function_exists($fn)) {
            try {
                $c = $fn();
            } catch (Throwable $e) {
                continue;
            }
            if ($c instanceof PDO) {
                return $c;
            }
        }
    }
    http_response_code(500);
    exit('Database connection not found. Check how config/config.php creates the PDO connection.');
}

/* ---------------------------------------------------------
   USER / PERMISSION
--------------------------------------------------------- */

function mat_role(): string
{
    return logged_in() ? (string) (me()['role'] ?? '') : '';
}

// me() only holds id / username / role, so look up the linked staff/student id once.
function mat_linked_ids(): array
{
    $pdo = mat_pdo();
    static $ids = null;

    if ($ids === null) {
        $ids = ['staff_id' => null, 'student_id' => null];
        $uid = me()['id'];
        if ($uid) {
            $stmt = $pdo->prepare('SELECT staff_id, student_id FROM user_accounts WHERE user_id = ?');
            $stmt->execute([$uid]);
            $ids = $stmt->fetch(PDO::FETCH_ASSOC) ?: $ids;
        }
    }
    return $ids;
}

function mat_staff_id(): ?string
{
    return mat_linked_ids()['staff_id'] ?: null;
}

function mat_student_id(): ?string
{
    return mat_linked_ids()['student_id'] ?: null;
}

function mat_require_role(array $roles): void
{
    allow_roles(...$roles);   // shared check from includes/role_check.php
}

// admin: everything. staff: only materials they created.
function mat_can_manage(array $m): bool
{
    if (mat_role() === 'admin') {
        return true;
    }
    return mat_role() === 'staff'
        && mat_staff_id() !== null
        && $m['created_by_staff_id'] === mat_staff_id();
}

function mat_student_class(): ?string
{
    $pdo = mat_pdo();
    $sid = mat_student_id();
    if (!$sid) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT student_class FROM students WHERE student_id = ?');
    $stmt->execute([$sid]);
    return $stmt->fetchColumn() ?: null;
}

// admin/staff see everything. student: published + released + own class only.
function mat_can_view(array $m): bool
{
    $role = mat_role();
    if ($role === 'admin' || $role === 'staff') {
        return true;
    }
    if ($role !== 'student') {
        return false;
    }
    if ((int) $m['is_published'] !== 1) {
        return false;
    }
    if (!empty($m['release_date']) && $m['release_date'] > date('Y-m-d')) {
        return false;
    }
    $class = mat_student_class();
    return empty($m['class_id']) || $m['class_id'] === $class;
}

/* ---------------------------------------------------------
   LOAD
--------------------------------------------------------- */

function mat_find(string $id): ?array
{
    $pdo = mat_pdo();
    $stmt = $pdo->prepare('SELECT * FROM study_materials WHERE material_id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// load a material the current user is allowed to manage, or stop with 404/403
function mat_load_for_manage(string $id): array
{
    $m = mat_find($id);
    if (!$m) {
        http_response_code(404);
        exit('Material not found.');
    }
    if (!mat_can_manage($m)) {
        http_response_code(403);
        exit('You can only manage your own materials.');
    }
    return $m;
}

// load a material the current user is allowed to see, or stop with 404
function mat_load_for_view(string $id): array
{
    $m = mat_find($id);
    if (!$m || !mat_can_view($m)) {
        http_response_code(404);
        exit('Material not found or not available.');
    }
    return $m;
}

// extra display info + attachments for the view page
function mat_details(string $id): array
{
    $pdo = mat_pdo();

    $stmt = $pdo->prepare(
        'SELECT s.subject_name, s.subject_code, c.grade_level, c.section, st.staff_name
         FROM study_materials sm
         LEFT JOIN subjects s ON s.subject_id = sm.subject_id
         LEFT JOIN classes c ON c.class_id = sm.class_id
         LEFT JOIN staff st ON st.staff_id = sm.created_by_staff_id
         WHERE sm.material_id = ?'
    );
    $stmt->execute([$id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare('SELECT * FROM material_attachments WHERE material_id = ? ORDER BY uploaded_at');
    $stmt->execute([$id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [$info, $attachments];
}

// dropdown data for the form
function mat_form_options(): array
{
    $pdo = mat_pdo();
    $subjects = $pdo->query('SELECT subject_id, subject_name, subject_code FROM subjects ORDER BY subject_name')->fetchAll(PDO::FETCH_ASSOC);
    $classes  = $pdo->query('SELECT class_id, grade_level, section FROM classes ORDER BY class_id')->fetchAll(PDO::FETCH_ASSOC);
    return [$subjects, $classes];
}

/* ---------------------------------------------------------
   REDIRECT
--------------------------------------------------------- */

function mat_back(string $msg, string $page = 'materials/index.php'): void
{
    notice($msg);   // shown by show_notice() in header.php
    go($page);
}

/* ---------------------------------------------------------
   FORM DATA
--------------------------------------------------------- */

function mat_default(): array
{
    return [
        'title' => '', 'description' => '', 'subject_id' => '', 'class_id' => '',
        'content_type' => 'Document', 'rich_text_content' => '',
        'is_published' => 0, 'release_date' => date('Y-m-d'),
    ];
}

// read + validate the create/edit form. Returns [data, errors]
function mat_read_post(): array
{
    $pdo = mat_pdo();
    check_csrf();
    $m = [
        'title'             => trim($_POST['title'] ?? ''),
        'description'       => trim($_POST['description'] ?? ''),
        'subject_id'        => $_POST['subject_id'] ?? '',
        'class_id'          => $_POST['class_id'] ?? '',
        'content_type'      => $_POST['content_type'] ?? 'Document',
        'rich_text_content' => mat_sanitize_html(trim($_POST['rich_text_content'] ?? '')),
        'is_published'      => isset($_POST['is_published']) ? 1 : 0,
        'release_date'      => trim($_POST['release_date'] ?? ''),
    ];
    $errors = [];

    if ($m['title'] === '' || mb_strlen($m['title']) > 200) {
        $errors[] = 'Title is required (max 200 characters).';
    }
    if (!in_array($m['content_type'], MAT_TYPES, true)) {
        $errors[] = 'Invalid content type.';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM subjects WHERE subject_id = ?');
    $stmt->execute([$m['subject_id']]);
    if (!$stmt->fetchColumn()) {
        $errors[] = 'Please choose a subject.';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE class_id = ?');
    $stmt->execute([$m['class_id']]);
    if (!$stmt->fetchColumn()) {
        $errors[] = 'Please choose a class.';
    }

    if ($m['release_date'] === '') {
        $m['release_date'] = null;
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $m['release_date']);
        if (!$d || $d->format('Y-m-d') !== $m['release_date']) {
            $errors[] = 'Release date is invalid.';
        }
    }

    return [$m, $errors];
}

/* ---------------------------------------------------------
   DATABASE ACTIONS
--------------------------------------------------------- */

function mat_create(array $m): string
{
    $pdo = mat_pdo();
    $id = next_id($pdo, 'study_materials', 'material_id', 'MAT');
    $stmt = $pdo->prepare(
        'INSERT INTO study_materials
         (material_id, title, description, subject_id, class_id, created_by_staff_id,
          content_type, rich_text_content, is_published, release_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id, $m['title'], $m['description'], $m['subject_id'], $m['class_id'],
        mat_role() === 'staff' ? mat_staff_id() : null,
        $m['content_type'], $m['rich_text_content'], $m['is_published'], $m['release_date'],
    ]);
    return $id;
}

function mat_update(string $id, array $m): void
{
    $pdo = mat_pdo();
    $stmt = $pdo->prepare(
        'UPDATE study_materials
         SET title = ?, description = ?, subject_id = ?, class_id = ?, content_type = ?,
             rich_text_content = ?, is_published = ?, release_date = ?
         WHERE material_id = ?'
    );
    $stmt->execute([
        $m['title'], $m['description'], $m['subject_id'], $m['class_id'], $m['content_type'],
        $m['rich_text_content'], $m['is_published'], $m['release_date'], $id,
    ]);
}

function mat_set_published(string $id, int $value): void
{
    $pdo = mat_pdo();
    $pdo->prepare('UPDATE study_materials SET is_published = ? WHERE material_id = ?')
        ->execute([$value, $id]);
}

function mat_delete(string $id): void
{
    $pdo = mat_pdo();

    // remove files from disk first
    $stmt = $pdo->prepare('SELECT file_path FROM material_attachments WHERE material_id = ?');
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        if ($path) {
            $full = __DIR__ . '/../' . $path;
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM material_attachments WHERE material_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM study_materials WHERE material_id = ?')->execute([$id]);
    $pdo->commit();
}

/* ---------------------------------------------------------
   ATTACHMENTS
--------------------------------------------------------- */

// Saves ONE uploaded file for a material. Returns an error message, or null when OK / nothing chosen.
function mat_store_file(string $materialId, array $file): ?string
{
    $pdo = mat_pdo();

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $origName = basename((string) $file['name']);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return $origName . ': upload failed (check upload_max_filesize in php.ini).';
    }
    if ($file['size'] > MAT_MAX_BYTES) {
        return $origName . ': file is too large (max 10 MB).';
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, MAT_ALLOWED_EXT, true)) {
        return $origName . ': file type not allowed (' . implode(', ', MAT_ALLOWED_EXT) . ').';
    }

    $dir = __DIR__ . '/../uploads/materials';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return 'Could not create the upload folder.';
    }

    $storedName = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $storedName)) {
        return $origName . ': could not save the file.';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO material_attachments (attachment_id, material_id, file_name, file_type, file_path)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        next_id($pdo, 'material_attachments', 'attachment_id', 'ATTCH'),
        $materialId,
        mb_substr($origName, 0, 255),
        strtoupper($ext),
        'uploads/materials/' . $storedName,
    ]);

    return null;
}

// Saves every file of a multi-file input (name="attachments[]"). Returns [saved count, errors].
function mat_store_uploads(string $materialId, string $field = 'attachments'): array
{
    $saved = 0;
    $errors = [];

    if (empty($_FILES[$field]['name'])) {
        return [0, []];
    }

    foreach ((array) $_FILES[$field]['name'] as $i => $name) {
        $file = [
            'name'     => $name,
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error'    => $_FILES[$field]['error'][$i],
            'size'     => $_FILES[$field]['size'][$i],
        ];
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $err = mat_store_file($materialId, $file);
        if ($err) {
            $errors[] = $err;
        } else {
            $saved++;
        }
    }
    return [$saved, $errors];
}

// Used by create/edit: uploads the form's files and builds the notice text.
function mat_upload_summary(string $materialId, string $message): string
{
    [$saved, $errors] = mat_store_uploads($materialId);
    if ($saved) {
        $message .= ' ' . $saved . ' file(s) uploaded.';
    }
    if ($errors) {
        $message .= ' ' . implode(' ', $errors);
    }
    return $message;
}

// Upload from the view page (single file), then redirect back.
function mat_handle_upload(): void
{
    $m = mat_load_for_manage($_POST['material_id'] ?? '');
    $back = 'materials/view_material.php?id=' . urlencode($m['material_id']) . '#attachments';

    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
        mat_back('Please choose a file.', $back);
    }

    $err = mat_store_file($m['material_id'], $_FILES['attachment']);
    mat_back($err ?? 'File uploaded.', $back);
}

// Remove one attachment (database row + file on disk).
function mat_handle_delete_attachment(string $attachmentId): void
{
    $pdo = mat_pdo();

    $stmt = $pdo->prepare('SELECT * FROM material_attachments WHERE attachment_id = ?');
    $stmt->execute([$attachmentId]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        mat_back('Attachment not found.');
    }

    $m = mat_load_for_manage($a['material_id']);

    if (!empty($a['file_path'])) {
        $full = realpath(__DIR__ . '/../' . $a['file_path']);
        $root = realpath(__DIR__ . '/../uploads/materials');
        if ($full && $root && strpos($full, $root) === 0 && is_file($full)) {
            @unlink($full);
        }
    }

    $pdo->prepare('DELETE FROM material_attachments WHERE attachment_id = ?')->execute([$attachmentId]);
    mat_back('Attachment removed.', 'materials/view_material.php?id=' . urlencode($m['material_id']) . '#attachments');
}

// Loads the module stylesheet directly, so it does not depend on header.php.
function mat_css(): void
{
    echo '<link rel="stylesheet" href="' . h(url('assets/css/material.css')) . '">' . "\n";
}

// sends the file to the browser after a permission check
function mat_handle_download(string $attachmentId): void
{
    $pdo = mat_pdo();

    $stmt = $pdo->prepare('SELECT * FROM material_attachments WHERE attachment_id = ?');
    $stmt->execute([$attachmentId]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);

    $m = $a ? mat_find($a['material_id']) : null;
    if (!$a || !$m || !mat_can_view($m)) {
        http_response_code(404);
        exit('File not found or not available.');
    }

    $full = realpath(__DIR__ . '/../' . ($a['file_path'] ?? ''));
    $root = realpath(__DIR__ . '/../uploads/materials');

    // only serve files that really live inside uploads/materials
    if (!$full || !$root || strpos($full, $root) !== 0 || !is_file($full)) {
        http_response_code(404);
        exit('File is missing on the server. Please ask your tutor to upload it again.');
    }

    $downloadName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $a['file_name']);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($full));
    header('X-Content-Type-Options: nosniff');
    readfile($full);
    exit;
}

/* ---------------------------------------------------------
   INDEX PAGE (subject cards + material list)
--------------------------------------------------------- */

// Subject cards with material counts. Returns [subjects, error message or null].
// student: subjects in their class timetable, counting only visible materials.
// admin/staff: all subjects, counting all materials.
function mat_subjects_for_user(): array
{
    $pdo = mat_pdo();

    if (mat_role() === 'student') {
        $class = mat_student_class();
        if (!$class) {
            return [[], 'Your student account is not linked to a class. Please contact the administrator.'];
        }
        $stmt = $pdo->prepare(
            'SELECT s.subject_id, s.subject_name, s.subject_code,
                    COUNT(DISTINCT sm.material_id) AS material_count
             FROM timetable_schedules ts
             JOIN subjects s ON s.subject_id = ts.subject_id
             LEFT JOIN study_materials sm
                    ON sm.subject_id = s.subject_id
                   AND (sm.class_id = ? OR sm.class_id IS NULL)
                   AND sm.is_published = 1
                   AND (sm.release_date IS NULL OR sm.release_date <= CURDATE())
             WHERE ts.class_id = ?
             GROUP BY s.subject_id, s.subject_name, s.subject_code
             ORDER BY s.subject_name'
        );
        $stmt->execute([$class, $class]);
        return [$stmt->fetchAll(PDO::FETCH_ASSOC), null];
    }

    $rows = $pdo->query(
        'SELECT s.subject_id, s.subject_name, s.subject_code,
                COUNT(sm.material_id) AS material_count
         FROM subjects s
         LEFT JOIN study_materials sm ON sm.subject_id = s.subject_id
         GROUP BY s.subject_id, s.subject_name, s.subject_code
         ORDER BY s.subject_name'
    )->fetchAll(PDO::FETCH_ASSOC);

    return [$rows, null];
}

// Materials of one subject that the current user is allowed to see.
function mat_list(string $subjectId): array
{
    $pdo = mat_pdo();

    $sql = 'SELECT sm.*, c.grade_level, c.section, st.staff_name
            FROM study_materials sm
            LEFT JOIN classes c ON c.class_id = sm.class_id
            LEFT JOIN staff st ON st.staff_id = sm.created_by_staff_id
            WHERE sm.subject_id = ?';
    $params = [$subjectId];

    if (mat_role() === 'student') {
        $sql .= ' AND sm.is_published = 1
                  AND (sm.class_id = ? OR sm.class_id IS NULL)
                  AND (sm.release_date IS NULL OR sm.release_date <= CURDATE())';
        $params[] = mat_student_class();
    }

    $sql .= ' ORDER BY sm.release_date DESC, sm.material_id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------------------------------------------------------
   RICH TEXT: TEMPLATES, SANITISER, RENDERING
--------------------------------------------------------- */

// Pre-built templates loaded by the "Template" button in the editor.
function mat_templates(): array
{
    return [
        'Lesson Note' =>
            '<h2>Lecture Summary</h2><p><strong>Topic:</strong> </p>'
            . '<h3>Learning Objectives</h3><ul><li></li><li></li></ul>'
            . '<h3>Key Points</h3><ul><li></li><li></li></ul>'
            . '<h3>Summary</h3><p></p>',
        'Lab Manual' =>
            '<h2>Lab Guide</h2><p><strong>Lab title:</strong> </p>'
            . '<h3>Objective</h3><p></p>'
            . '<h3>Equipment / Software</h3><ul><li></li></ul>'
            . '<h3>Procedure</h3><ol><li></li><li></li></ol>'
            . '<h3>Expected Result</h3><p></p>',
        'Assignment Brief' =>
            '<h2>Assignment Brief</h2><p><strong>Due date:</strong> </p>'
            . '<h3>Task Description</h3><p></p>'
            . '<h3>Requirements</h3><ul><li></li></ul>'
            . '<h3>Marking Criteria</h3><ul><li></li></ul>'
            . '<h3>Submission</h3><p></p>',
        'Syllabus Update' =>
            '<h2>Syllabus Update</h2><p><strong>Effective from:</strong> </p>'
            . '<h3>What Changed</h3><ul><li></li></ul>'
            . '<h3>Reason</h3><p></p>',
        'Study Card Set' =>
            '<h2>Study Cards</h2>'
            . '<h3>Card 1</h3><p><strong>Q:</strong> </p><p><strong>A:</strong> </p>'
            . '<h3>Card 2</h3><p><strong>Q:</strong> </p><p><strong>A:</strong> </p>',
    ];
}

// Only http(s) links, relative links, or YouTube/Vimeo embeds are allowed.
function mat_safe_url(string $url, string $tag): bool
{
    $clean = preg_replace('/[\x00-\x20]+/', '', $url);
    if ($clean === '' || $clean === null) {
        return false;
    }
    if ($tag === 'iframe') {
        return (bool) preg_match(
            '#^https://(www\.youtube\.com/embed/|www\.youtube-nocookie\.com/embed/|player\.vimeo\.com/video/)[A-Za-z0-9_-]+#',
            $clean
        );
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $clean)) {
        return (bool) preg_match('#^https?://#i', $clean);
    }
    return strpos($clean, '//') !== 0;
}

// Allow-list HTML cleaner: stops script injection (XSS) in saved content.
function mat_sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $allowedTags = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li',
                    'blockquote', 'pre', 'code', 'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
                    'div', 'span', 'hr', 'video', 'audio', 'source', 'iframe'];
    $dropTags = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select',
                 'link', 'meta', 'svg', 'math', 'base'];
    $allowedAttrs = [
        'a'      => ['href', 'title'],
        'img'    => ['src', 'alt', 'width', 'height'],
        'video'  => ['src', 'controls', 'width', 'height'],
        'audio'  => ['src', 'controls'],
        'source' => ['src', 'type'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'title'],
    ];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) {
        return '';
    }

    $clean = function (DOMNode $node) use (&$clean, $allowedTags, $dropTags, $allowedAttrs): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, $dropTags, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, $allowedTags, true)) {
                // unknown tag: keep its text/children, drop the tag itself
                $clean($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowedAttrs[$tag] ?? [], true)) {
                    $child->removeAttribute($attr->name);
                } elseif (in_array($name, ['href', 'src'], true) && !mat_safe_url($attr->value, $tag)) {
                    $child->removeAttribute($attr->name);
                }
            }

            if ($tag === 'iframe' && !$child->hasAttribute('src')) {
                $node->removeChild($child);
                continue;
            }
            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
                $child->setAttribute('target', '_blank');
            }

            $clean($child);
        }
    };
    $clean($root);

    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return $out;
}

// HTML to show on the page / in the editor. Old plain-text rows still work.
function mat_render_content(?string $text): string
{
    $text = (string) $text;
    if (trim($text) === '') {
        return '';
    }
    if ($text === strip_tags($text)) {
        return nl2br(h($text));
    }
    return mat_sanitize_html($text);
}

// KaTeX (LaTeX formulas): use \( ... \) for inline and $$ ... $$ for block.
function mat_katex_assets(): void
{
    ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.querySelector('.material-content');
        if (el && window.renderMathInElement) {
            renderMathInElement(el, {delimiters: [
                {left: '$$', right: '$$', display: true},
                {left: '\\(', right: '\\)', display: false}
            ]});
        }
    });
    </script>
    <?php
}

// Styled confirm dialog. Any <form data-confirm="Question?"> asks before it submits.
function mat_confirm_assets(): void
{
    ?>
    <div id="mat-modal" class="mat-modal" hidden>
        <div class="mat-modal-box" role="dialog" aria-modal="true">
            <p id="mat-modal-text"></p>
            <div class="mat-modal-actions">
                <button type="button" id="mat-modal-ok" class="button">Yes, continue</button>
                <button type="button" id="mat-modal-cancel" class="button button-outline">Cancel</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('mat-modal');
        var text  = document.getElementById('mat-modal-text');
        var ok    = document.getElementById('mat-modal-ok');
        var cancel = document.getElementById('mat-modal-cancel');
        var pending = null;

        function close() { modal.hidden = true; pending = null; }

        document.addEventListener('submit', function (e) {
            var f = e.target;
            var msg = f.getAttribute ? f.getAttribute('data-confirm') : null;
            if (!msg) { return; }
            e.preventDefault();
            pending = f;
            text.textContent = msg;
            modal.hidden = false;
            cancel.focus();
        });

        ok.addEventListener('click', function () {
            var f = pending;
            close();
            if (f) { f.submit(); }
        });
        cancel.addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    })();
    </script>
    <?php
}