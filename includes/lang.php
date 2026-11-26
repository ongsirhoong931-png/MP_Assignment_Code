<?php
/**
 * LANGUAGE
 *
 * All page text is written once, in English, in language/en.php
 * (used by Module 6 through  h(t('key'))).
 * Chinese and Malay come from the Google Translate buttons
 * (see includes/site_language.php), so there are no per-language files.
 *
 * The chosen language (en / zh / ms) is kept in the session and saved to
 * user_accounts.preferred_language, so it is remembered next login.
 */

require_once __DIR__ . '/functions.php';

const LANG_NAMES = ['en' => 'English', 'zh' => '中文', 'ms' => 'Bahasa Melayu'];

function lang_current(): string
{
    $code = (string) ($_SESSION['lang'] ?? 'en');

    return isset(LANG_NAMES[$code]) ? $code : 'en';
}

/** @return array<string,string> */
function lang_strings(): array
{
    static $strings = null;

    if ($strings === null) {
        $file = __DIR__ . '/../language/en.php';
        $data = is_file($file) ? require $file : [];
        $strings = is_array($data) ? $data : [];
    }

    return $strings;
}

/**
 * Translate a key. Placeholders like {name} are replaced from $vars.
 * The result is plain text: wrap it in h() when printing in HTML.
 */
function t(string $key, array $vars = []): string
{
    $text = lang_strings()[$key] ?? $key;

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

    if ($wanted !== '' && isset(LANG_NAMES[$wanted])) {
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
            $code = isset(LANG_NAMES[$saved]) ? $saved : 'en';
        } catch (PDOException $e) {
            error_log('lang_init: ' . $e->getMessage());
        }

        $_SESSION['lang'] = $code;
    }
}