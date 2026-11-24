<?php
// View partial: the create/edit form (HTML only, no logic).
// Included by create_material.php and edit_material.php, which define the variables below.

/** @var array  $m            the material (title, subject_id, class_id, ...) */
/** @var array  $errors       validation messages */
/** @var array  $subjects     rows from mat_form_options() */
/** @var array  $classes      rows from mat_form_options() */
/** @var string $submitLabel  text of the submit button */

// Opened directly in the browser (not included by a page): stop here.
if (!isset($m, $errors, $subjects, $classes, $submitLabel)) {
    http_response_code(404);
    exit('This file is only used by create_material.php and edit_material.php.');
}
?>
<div class="material-container">
    <?php foreach ($errors as $e): ?>
        <div class="material-alert material-alert-error"><?= h($e) ?></div>
    <?php endforeach; ?>

    <form method="post" class="material-form" id="material-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

        <label>Title *
            <input type="text" name="title" maxlength="200" value="<?= h($m['title']) ?>" required>
        </label>

        <label>Description
            <input type="text" name="description" value="<?= h($m['description'] ?? '') ?>">
        </label>

        <label>Subject *
            <select name="subject_id" required>
                <option value="">-- choose --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= h($s['subject_id']) ?>" <?= $m['subject_id'] === $s['subject_id'] ? 'selected' : '' ?>>
                        <?= h($s['subject_code'] . ' - ' . $s['subject_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Class *
            <select name="class_id" required>
                <option value="">-- choose --</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= h($c['class_id']) ?>" <?= $m['class_id'] === $c['class_id'] ? 'selected' : '' ?>>
                        <?= h($c['class_id'] . ' - ' . $c['grade_level'] . ' ' . $c['section']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Material type
            <select name="content_type" id="content_type">
                <?php foreach (MAT_TYPES as $t): ?>
                    <option <?= $m['content_type'] === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="material-field">
            <span class="material-label">Content</span>

            <div class="editor-toolbar">
                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                <button type="button" data-cmd="formatBlock" data-val="h2">H2</button>
                <button type="button" data-cmd="formatBlock" data-val="h3">H3</button>
                <button type="button" data-cmd="insertUnorderedList">&bull; List</button>
                <button type="button" data-cmd="insertOrderedList">1. List</button>
                <button type="button" data-cmd="link">Link</button>
                <button type="button" data-cmd="image">Image</button>
                <button type="button" data-cmd="media">Video / Audio</button>
                <button type="button" data-cmd="formula">&sum; Formula</button>
                <button type="button" data-cmd="template">Load template</button>
            </div>

            <div id="editor" class="editor" contenteditable="true"><?= mat_render_content($m['rich_text_content'] ?? '') ?></div>
            <input type="hidden" name="rich_text_content" id="rich_text_content">
            <small>Formulas: inline <code>\( x^2 \)</code> or block <code>$$ x^2 $$</code> (shown as maths when viewing).</small>
        </div>

        <label>Attachments (optional, max 10 MB each)
            <input type="file" name="attachments[]" multiple>
            <small>PDF, Word, PowerPoint, Excel, images, ZIP, TXT. Existing files are managed on the material's page.</small>
        </label>

        <label>Release date
            <input type="date" name="release_date" value="<?= h($m['release_date'] ?? '') ?>"
       min="<?= h(min(date('Y-m-d'), $m['release_date'] ?: date('Y-m-d'))) ?>">
        </label>

        <label class="material-check">
            <input type="checkbox" name="is_published" value="1" <?= !empty($m['is_published']) ? 'checked' : '' ?>>
            Published (students can see it)
        </label>

        <div class="material-actions">
            <button class="button" type="submit"><?= h($submitLabel) ?></button>
            <a class="button button-outline" href="<?= h(url('materials/index.php')) ?>">Cancel</a>
        </div>
    </form>

    <!-- Input dialog for Link / Image / Video / Formula / Template (replaces browser prompt()) -->
    <div id="ed-modal" class="ed-modal" hidden>
        <div class="ed-modal-box" role="dialog" aria-modal="true">
            <p id="ed-modal-title"></p>
            <input type="text" id="ed-modal-input" autocomplete="off">
            <label class="ed-block" id="ed-modal-blockwrap" hidden>
                <input type="checkbox" id="ed-modal-block"> Block formula (own line)
            </label>
            <div class="ed-modal-actions">
                <button type="button" id="ed-modal-ok" class="button">OK</button>
                <button type="button" id="ed-modal-cancel" class="button button-outline">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form    = document.getElementById('material-form');
    var editor  = document.getElementById('editor');
    var field   = document.getElementById('rich_text_content');
    var typeSel = document.getElementById('content_type');
    var templates = <?= json_encode(mat_templates(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

    var modal   = document.getElementById('ed-modal');
    var mTitle  = document.getElementById('ed-modal-title');
    var mInput  = document.getElementById('ed-modal-input');
    var mBlockW = document.getElementById('ed-modal-blockwrap');
    var mBlock  = document.getElementById('ed-modal-block');
    var mOk     = document.getElementById('ed-modal-ok');
    var mCancel = document.getElementById('ed-modal-cancel');
    var onOk = null;
    var savedRange = null;

    /* ---- remember where the cursor / selection was in the editor ---- */
    function saveSel() {
        var s = window.getSelection();
        if (s.rangeCount && editor.contains(s.anchorNode)) {
            savedRange = s.getRangeAt(0).cloneRange();
        }
    }
    function restoreSel() {
        editor.focus();
        if (savedRange) {
            var s = window.getSelection();
            s.removeAllRanges();
            s.addRange(savedRange);
        }
    }

    function run(cmd, val) {
        restoreSel();
        document.execCommand(cmd, false, val || null);
        saveSel();
    }

    /* ---- small dialog (works even if the browser blocks prompt()) ---- */
    function ask(title, placeholder, showBlock, cb, confirmOnly) {
        mTitle.textContent = title;
        mInput.value = '';
        mInput.placeholder = placeholder || '';
        mInput.hidden = !!confirmOnly;
        mBlockW.hidden = !showBlock;
        mBlock.checked = false;
        onOk = cb;
        modal.hidden = false;
        if (confirmOnly) { mOk.focus(); } else { mInput.focus(); }
    }
    function closeModal() { modal.hidden = true; onOk = null; }
    function submitModal() {
        var v = mInput.value.trim();
        var block = mBlock.checked;
        var cb = onOk;
        var confirmOnly = mInput.hidden;
        closeModal();
        if (cb && (confirmOnly || v !== '')) { cb(v, block); }
    }
    mOk.addEventListener('click', submitModal);
    mCancel.addEventListener('click', closeModal);
    modal.addEventListener('mousedown', function (e) { if (e.target === modal) { closeModal(); } });
    mInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); submitModal(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { closeModal(); }
    });

    function esc(u) {
        return u.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function embedHtml(url) {
        var yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{11})/);
        if (yt) {
            return '<iframe src="https://www.youtube.com/embed/' + yt[1] + '" width="560" height="315" allowfullscreen></iframe><p></p>';
        }
        var vm = url.match(/vimeo\.com\/(\d+)/);
        if (vm) {
            return '<iframe src="https://player.vimeo.com/video/' + vm[1] + '" width="560" height="315" allowfullscreen></iframe><p></p>';
        }
        if (/\.(mp4|webm)(\?.*)?$/i.test(url)) {
            return '<video controls src="' + esc(url) + '"></video><p></p>';
        }
        if (/\.(mp3|wav|ogg)(\?.*)?$/i.test(url)) {
            return '<audio controls src="' + esc(url) + '"></audio><p></p>';
        }
        return '';
    }

    // keep the selection when a toolbar button is pressed
    editor.addEventListener('keyup', saveSel);
    editor.addEventListener('mouseup', saveSel);
    editor.addEventListener('blur', saveSel);

    document.querySelectorAll('.editor-toolbar [data-cmd]').forEach(function (b) {
        b.addEventListener('mousedown', function (e) { saveSel(); e.preventDefault(); });
        b.addEventListener('click', function () {
            var c = b.dataset.cmd;

            if (c === 'link') {
                ask('Link URL (https://...)', 'https://', false, function (u) {
                    restoreSel();
                    var sel = window.getSelection();
                    if (sel.isCollapsed) {
                        // nothing selected: insert the link with its own address as text
                        run('insertHTML', '<a href="' + esc(u) + '">' + esc(u) + '</a>');
                    } else {
                        run('createLink', u);
                    }
                });
            } else if (c === 'image') {
                ask('Image URL (https://...)', 'https://example.com/picture.png', false, function (u) {
                    run('insertImage', u);
                });
            } else if (c === 'media') {
                ask('YouTube / Vimeo link, or a direct .mp4 / .webm / .mp3 / .wav / .ogg link', 'https://youtu.be/...', false, function (u) {
                    var html = embedHtml(u);
                    if (html) { run('insertHTML', html); }
                    else { ask('Link type not supported. Use YouTube, Vimeo or a direct media file.', '', false, null, true); }
                });
            } else if (c === 'formula') {
                ask('LaTeX formula, e.g. x^2 + y^2 = z^2', 'x^2 + y^2 = z^2', true, function (t, block) {
                    run('insertText', block ? '$$' + t + '$$' : '\\(' + t + '\\)');
                });
            } else if (c === 'template') {
                var tpl = templates[typeSel.value];
                if (!tpl) {
                    ask('No template for this material type.', '', false, null, true);
                    return;
                }
                var apply = function () { editor.innerHTML = tpl; };
                if (editor.innerText.trim() !== '') {
                    ask('Replace the current content with the template?', '', false, apply, true);
                } else {
                    apply();
                }
            } else if (c === 'formatBlock') {
                run('formatBlock', '<' + b.dataset.val + '>');
            } else {
                run(c);
            }
        });
    });

    form.addEventListener('submit', function () {
        field.value = editor.innerHTML;
    });
})();
</script>