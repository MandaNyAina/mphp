<?php

use Core\Lib\Main\MphpCore as MPHP;

session_start();

try {
    setlocale(LC_TIME, "fr_FR");
} catch (Exception $error) {
    MPHP::show_error_server(500, $error);
}

function setSession(string $name, string $value): bool {
    try {
        $_SESSION[$name] = $value;
        return true;
    } catch (Exception $error) {
        MPHP::show_error_server(500, $error);
        return false;
    }
}

function getSession(string $name) {
    return $_SESSION[$name];
}

function clearSession(string $name = null): ?bool {
    if ($name == null) {
        session_unset();
        session_destroy();
    } else {
        $_SESSION[$name] = null;
    }
    return true;
}

function addToCookie(string $name, $value, int $expire_jours): bool {
    $expire = time() + ($expire_jours * 86400);
    setcookie($name, $value, $expire, "/");
    return true;
}

function getCookie(string $name) {
    return $_COOKIE[$name];
}

function deleteCookie(string $name): bool {
    setcookie($name, null, 0, "/");
    return true;
}

function encrypt(string $str): string {
    global $config;
    $key_encrypt = $config['key_encrypt'];
    return openssl_encrypt($str, "AES-128-CTR", $key_encrypt, 0, '8565825542115032');
}

function decrypt(string $str_encrypt): string {
    global $config;
    $key_encrypt = $config['key_encrypt'];
    return openssl_decrypt($str_encrypt, "AES-128-CTR", $key_encrypt, 0, '8565825542115032');
}

function randomValue(int $length, bool $specialChar = false): string {
    $result = "";
    $char = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    $charSpec = ".*',.#^$!-:;=+_%";
    $full = $specialChar ? $char . $charSpec : $char;
    $random = 0;
    for ($i = 1; $i <= $length; $i++) {
        $j = 0;
        while ($j < 15) {
            $random = round((rand(0, 10) / 10) * strlen($full));
            $j++;
        }
        $result = $result . $full[(int)($random - 1)];
    }
    return $result;
}

function password_encrypt(string $str): string {
    return password_hash($str, PASSWORD_BCRYPT);
}

function password_match(string $encrypt, string $value): bool {
    return password_verify($value, $encrypt);
}

function password_validator(string $password_string, int $min_length = 8): bool {
    $correct = preg_match('/(?=^.{8,}$)(?=.*\d)((?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/', $password_string);
    return ($correct && strlen($password_string) > $min_length);
}

function clearString(string $str, bool $clear_special_char = false): string {
    $str = trim($str);
    $str = stripslashes($str);
    $str = strip_tags($str);
    $str = htmlspecialchars($str);
    if ($clear_special_char) {
        $str = clearSpecialChar($str);
    }
    return $str;
}

function clearSpecialChar(string $str): string {
    $normalizeChars = array(
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'Eth',
        'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',

        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'eth',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y',

        'ß' => 'sz', 'þ' => 'thorn', 'ÿ' => 'y',

        '&' => 'en', '@' => 'at', '#' => 'h', '$' => 's', '%' => 'perc', '^' => '-', '*' => '-'
    );

    foreach ($normalizeChars as $k => $v) {
        if (strpos($k, $str) !== false) {
            $str = str_replace($k, $v, $str);
        }
    }

    return $str;
}

function currentDatetime(string $choice = "datetime", string $concat = "-"): string {
    switch ($choice) {
        case 'full':
            return date(" l  d  F  Y  H:i:s");

        case 'datetime':
            return date(" d$concat" . "m$concat" . "Y  H:i:s");

        case 'date':
            return date(" Y$concat" . "m$concat" . "d");

        case 'default_date':
            return date(" d$concat" . "m$concat" . "Y");

        case 'time':
            return date("H:i:s");

        default:
            return '';
    }
}

function compress_image(string $source, string $destination, string $quality) {
    $image = [];
    $info = getimagesize($source);
    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
    }
    imagejpeg($image, $destination, $quality);
}

function download($path, $file): bool {
    if (preg_match('/^[^.][-a-z0-9_.]+[a-z]$/i', $file)) {
        $filepath = $path . $file;
        if (file_exists($filepath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary");
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            flush();
            readfile($filepath);
            return true;
        } else {
            MPHP::show_error_server(500, 'File not exist');
        }
    } else {
        MPHP::show_error_server(500, 'Download failed');
    }
    return false;
}

function is_form_valid($data): bool {
    return isset($data) && !empty($data);
}

function str_exist(string $search_string, string $string): bool {
    return preg_match("/$search_string/i", $string);
}

function array_to_json(array $data) {
    return json_encode($data);
}

function json_to_array(string $json_data): array {
    return json_decode($json_data, true);
}
