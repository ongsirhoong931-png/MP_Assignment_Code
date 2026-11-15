<?php
/**
 * MULTI-LANGUAGE (English / Chinese / Malay)
 *
 * Text lives in the language/ folder:
 *   language/en.php     English  (code "en")
 *   language/chi.php    Chinese  (code "zh")
 *   language/malay.php  Malay    (code "ms")
 * Each file returns an array:  'key' => 'text'.
 *
 * Use  h(t('key'))  in a page. A missing key falls back to English,
 * then to the key itself, so a page never breaks.
 *
 * The chosen language is kept in the session and saved to
 * user_accounts.preferred_language, so it is remembered next login.
 */

require_once __DIR__ . '/functions.php';

const LANG_FILES = ['en' => 'en.php', 'zh' => 'chi.php', 'ms' => 'malay.php'];
const LANG_NAMES = ['en' => 'English', 'zh' => '中文', 'ms' => 'Bahasa Melayu'];

function lang_current(): string
{
    $code = (string) ($_SESSION['lang'] ?? 'en');

    return isset(LANG_FILES[$code]) ? $code : 'en';
}

/** @return array<string,string> */
function lang_strings(string $code): array
{
    static $cache = [];

    if (!isset(LANG_FILES[$code])) {
        $code = 'en';
    }

    if (!isset($cache[$code])) {
        $file = __DIR__ . '/../language/' . LANG_FILES[$code];
        $data = is_file($file) ? require $file : [];
        $cache[$code] = is_array($data) ? $data : [];
    }

    return $cache[$code];
}

/**
 * Translate a key. Placeholders like {name} are replaced from $vars.
 * The result is plain text: wrap it in h() when printing in HTML.
 */
function t(string $key, array $vars = []): string
{
    $text = lang_strings(lang_current())[$key]
        ?? lang_strings('en')[$key]
        ?? $key;

    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }

    return $text;
}

/**
 * Call once at the top of a page (before any output).
 *  - ?lang=xx switches language, saves it, and reloads the page
 *  - otherwise loads the saved preference the first time
 */
function lang_init(PDO $pdo): void
{
    $wanted = (string) ($_GET['lang'] ?? '');

    if ($wanted !== '' && isset(LANG_FILES[$wanted])) {
        $_SESSION['lang'] = $wanted;

        if (logged_in()) {
            try {
                $pdo->prepare(
                    'UPDATE user_accounts SET preferred_language = ?
                     WHERE user_id = ?'
                )->execute([$wanted, (int) me()['id']]);
            } catch (PDOException $e) {
                error_log('lang_init: ' . $e->getMessage());
            }
        }

        // Reload the same page without ?lang= (path only, never a host).
        $path = '/' . ltrim((string) parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        ), '/');
        $query = $_GET;
        unset($query['lang']);

        header('Location: ' . $path . ($query ? '?' . http_build_query($query) : ''));
        exit;
    }

    if (!isset($_SESSION['lang']) && logged_in()) {
        $code = 'en';

        try {
            $stmt = $pdo->prepare(
                'SELECT preferred_language FROM user_accounts
                 WHERE user_id = ? LIMIT 1'
            );
            $stmt->execute([(int) me()['id']]);
            $saved = (string) $stmt->fetchColumn();
            $code = isset(LANG_FILES[$saved]) ? $saved : 'en';
        } catch (PDOException $e) {
            error_log('lang_init: ' . $e->getMessage());
        }

        $_SESSION['lang'] = $code;
    }
}

/** Small "English | 中文 | Bahasa Melayu" switcher that keeps the URL's filters. */
function lang_switcher(): string
{
    $current = lang_current();
    $html = '<span class="lv-lang" aria-label="Language">';

    foreach (LANG_NAMES as $code => $name) {
        $query = $_GET;
        $query['lang'] = $code;

        $html .= '<a href="?' . h(http_build_query($query)) . '"'
            . ($code === $current ? ' class="active" aria-current="true"' : '')
            . '>' . h($name) . '</a>';
    }

    return $html . '</span>';
}