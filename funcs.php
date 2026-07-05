<?php
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Access denied');
}

function load_env_file($path){
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);
        if ($name === '') {
            continue;
        }

        $value = trim($value, "\"' ");
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

$envFile = __DIR__.'/.env';
if (!is_file($envFile) && is_file(__DIR__.'/.env.example')) {
    $envFile = __DIR__.'/.env.example';
}
load_env_file($envFile);

$configFile = __DIR__ . '/config/sakura.php';
if (is_file($configFile)) {
    $sakuraConfig = include $configFile;
    if (is_array($sakuraConfig)) {
        foreach ($sakuraConfig as $name => $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value, "\"' ");
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function env_value($key, $default = ''){
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

function google_maps_api_key(){
    return trim(env_value('GOOGLE_MAPS_API_KEY', ''));
}

function google_maps_api_enabled(){
    return google_maps_api_key() !== '';
}

function google_maps_script_tag($libraries = 'places,marker'){
    $key = google_maps_api_key();
    if ($key === '') {
        return '<script>window.__GOOGLE_MAPS_API_KEY_MISSING__ = true;</script>';
    }

    $params = http_build_query([
        'key' => $key,
        'v' => 'weekly',
        'libraries' => $libraries,
    ]);

    return '<script src="https://maps.googleapis.com/maps/api/js?' . $params . '" async defer></script>';
}

// DB接続
function db_conn(){
    try {
        $db_name = env_value('DB_NAME', 'tripapp');
        $db_id   = env_value('DB_USER', 'root');
        $db_pw   = env_value('DB_PASS', '');
        $db_host = env_value('DB_HOST', 'localhost');
        $pdo = new PDO('mysql:dbname='.$db_name.';charset=utf8;host='.$db_host, $db_id, $db_pw);
        return $pdo;
    } catch (PDOException $e) {
        exit('DB Connection Error:'.$e->getMessage());
    }
}

// さくら用
function db_conn_sakura(){
    try {
        $db_name = env_value('SAKURA_DB_NAME', '');
        $db_id   = env_value('SAKURA_DB_USER', '');
        $db_pw   = env_value('SAKURA_DB_PASS', '');
        $db_host = env_value('SAKURA_DB_HOST', '');

        if ($db_name === '' || $db_id === '' || $db_pw === '' || $db_host === '') {
            exit('Sakura DB settings are not configured.');
        }

        $pdo = new PDO('mysql:dbname='.$db_name.';charset=utf8;host='.$db_host, $db_id, $db_pw);
        return $pdo;
    } catch (PDOException $e) {
        exit('DB Connection Error:'.$e->getMessage());
    }
}

//SQLエラー関数
    function sql_error($stmt){
        $error = $stmt->errorInfo();
        exit("SQLError:".$error[2]);
    }

// リダイレクト関数
    function redirect($file_name){
        header("Location: ".$file_name);
        exit();
    }









?>