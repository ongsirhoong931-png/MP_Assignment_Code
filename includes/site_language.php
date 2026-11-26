<?php

require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/../config/database.php';

// Language code used by Google Translate for each of our codes.
const SITE_TRANSLATE_CODES = ['zh' => 'zh-CN', 'ms' => 'ms'];

if (isset($pdo) && $pdo instanceof PDO) {
    lang_init($pdo);
}

/** Floating "English | 中文 | Bahasa Melayu" buttons plus the translate script. */
function site_language_widget(): string
{
    $current = lang_current();
    $target = SITE_TRANSLATE_CODES[$current] ?? '';

    $html = '<style>
        .site-lang { position: fixed; right: 14px; top: 12px; z-index: 9999;
            display: flex; gap: 6px; padding: 6px; border-radius: 10px;
            background: var(--surface, #1b1f2a);
            border: 1px solid var(--line, #3a4052); }
        .site-lang a { padding: 5px 9px; border-radius: 7px; font-size: 13px;
            color: var(--text, #ffffff); text-decoration: none; }
        .site-lang a.active { background: var(--accent-dark, #2563eb); color: #ffffff; }
                .site-lang.in-header { position: static; }
        #google_translate_element, iframe.skiptranslate,
        .goog-te-banner-frame { display: none !important; }
        body { top: 0 !important; }
    </style>';

    $html .= '<div class="site-lang notranslate" translate="no" aria-label="Language">';

    foreach (LANG_NAMES as $code => $name) {
        $query = $_GET;
        $query['lang'] = $code;

        $html .= '<a href="?' . h(http_build_query($query)) . '"'
            . ($code === $current ? ' class="active" aria-current="true"' : '')
            . '>' . h($name) . '</a>';
    }

    $html .= '</div><div id="google_translate_element"></div>';

    $html .= '<script>
(function () {
    var box = document.querySelector(".header-actions");
    var bar = document.querySelector(".site-lang");
    if (box && bar) {                   // sit inside the navbar, before Profile
        box.insertBefore(bar, box.firstChild);
        bar.classList.add("in-header");
    }
    var target = ' . json_encode($target) . ';
    var expire = "; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";

    if (!target) {                      // English: no translation
        document.cookie = "googtrans=" + expire;
        return;
    }

    document.cookie = "googtrans=/en/" + target + "; path=/";

    window.googleTranslateElementInit = function () {
        new google.translate.TranslateElement({
            pageLanguage: "en",
            includedLanguages: "en,zh-CN,ms",
            autoDisplay: false
        }, "google_translate_element");
    };

    var s = document.createElement("script");
    s.src = "https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit";
    document.body.appendChild(s);
})();
</script>';

    return $html;
}