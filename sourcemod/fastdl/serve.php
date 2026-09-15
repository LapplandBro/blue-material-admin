<?php
/*This file is part of SourceMod Re-Banner.
    https://github.com/Nolo001-Aha/SourceMod-ReBanner
    Please consult the Wiki page regarding this file.
    
    FIXED VERSION - Compatible with updated fingerprint format (hex-based)
    
    IMPORTANT: If Content-Length header is missing, check PHP-FPM pool configuration.
    In /etc/php/8.2/fpm/pool.d/www.conf (or your pool config), add:
    php_admin_value[output_buffering] = Off
    php_admin_flag[zlib.output_compression] = Off
    Then restart PHP-FPM: systemctl restart php8.2-fpm

    КРИТИЧНО ДЛЯ ИГРЫ (браузер качает, клиент Source Engine — нет):
    Если перед PHP стоит NGINX — он может включать gzip для ответа. Тогда nginx
    убирает Content-Length и отдаёт Transfer-Encoding: chunked. Браузер это
    понимает, а Source Engine — нет, и выдаёт "Error downloading".
    РЕШЕНИЕ: в конфиге nginx для этого location отключить gzip, например:
    location ~ /fastdl/serve\.php {
        gzip off;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    Либо отключить gzip для всего каталога fastdl.
    
    ============================================================================
    КАК РАБОТАЕТ СКРИПТ:
    ============================================================================
    
    1. ОБЫЧНЫЕ ФАЙЛЫ:
       - Клиент запрашивает файл через параметры: ?url=/path/to/file&id=fingerprint
       - Скрипт проверяет безопасность пути и отдает файл напрямую
       - Source Engine сначала пытается загрузить .bz2 версию (сжатую) для экономии трафика
       - Если .bz2 нет, клиент автоматически запрашивает обычную версию
       - Это НОРМАЛЬНОЕ поведение - не критично, если .bz2 версии нет
    
    2. ФАЙЛ ОТПЕЧАТКА (FINGERPRINT FILE):
       - Файл отпечатка указан в переменной $fingerprintFilePath
       - Когда клиент запрашивает этот файл, скрипт НЕ отдает реальный файл
       - Вместо этого вызывается processFingerprintFile(), которая:
         * Создает файл динамически с содержимым = значению fingerprint (параметр id)
         * Отдает этот файл клиенту
         * Файл создается только в момент запроса и содержит только hex-строку
       
    3. ЗАПРОСЫ .bz2 ДЛЯ ФАЙЛА ОТПЕЧАТКА:
       - Source Engine ВСЕГДА сначала пытается загрузить .bz2 версию
       - Для файла отпечатка это выглядит так:
         * Запрос: /sound/consnd/server_joining2.mp3.bz2 -> 404 (файл не найден)
         * Запрос: /sound/consnd/server_joining2.mp3 -> 200 (файл создан динамически)
       - Это НОРМАЛЬНО и НЕ является ошибкой!
       - Файл отпечатка создается динамически, поэтому .bz2 версия не нужна
       - Клиент автоматически переключится на обычную версию после 404
    
    4. ЛОГИРОВАНИЕ:
       - Если в логах видите "ERROR: File not found: ...bz2" для файла отпечатка - 
         это НОРМАЛЬНО, можно игнорировать
       - Скрипт продолжит работу и обработает запрос на обычную версию
*/

    // DEBUG MODE: Set to 1 to enable logging, 0 to disable
    // ВАЖНО: Отключите в продакшене для безопасности!
    $DEBUG = 0;

    // Жёсткий лог в /tmp — создаётся при ЛЮБОМ запуске скрипта (не зависит от прав на папку).
    // Смотри на сервере: tail -f /tmp/fastdl_serve.log
    // Если при запросе из игры в логе пусто — запрос не доходит до этого скрипта (URL/хост/фейрвол).
    @file_put_contents('/tmp/fastdl_serve.log',
        date('Y-m-d H:i:s') . ' | ' . ($_SERVER['REQUEST_URI'] ?? '?') . ' | UA: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'none') . "\n",
        FILE_APPEND
    );

    // Enable error logging for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    if($DEBUG) {
        ini_set('error_log', __DIR__ . '/fastdl_debug.log');
    }

    //MODIFY THIS VARIABLE AND PLACE YOUR CHOSEN FINGERPRINT PATH INSIDE.
    //Example: "materials/models/texture.vmt"
    //Based on client logs, the plugin is requesting tier_null.vmt
    $fingerprintFilePath = "models/ahit_smug_dance/hatkid_leg_right.mdl";
    
    
    $allowedFolders = array
    ( //folders we're allowed to download from
        "materials",
        "models",
        "sound",
        "cfg",
        "maps",
        "scripts"
    );

    // NEW: Extract server_id and userid for multi-server support
    // These parameters help identify which server and client the request is for
    $serverId = isset($_GET['srv']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['srv']) : 'unknown';
    $userId = isset($_GET['uid']) ? intval($_GET['uid']) : 0;

    // Debug logging - now includes server_id and userid
    if($DEBUG) {
        file_put_contents(__DIR__ . '/fastdl_debug.log', 
            date('Y-m-d H:i:s') . " - Request: " . 
            "url=" . ($_GET['url'] ?? 'MISSING') . 
            " id=" . ($_GET['id'] ?? 'MISSING') . 
            " srv=" . $serverId . 
            " uid=" . $userId . 
            " IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'NONE') . 
            " UA=" . ($_SERVER['HTTP_USER_AGENT'] ?? 'NONE') . "\n", 
            FILE_APPEND
        );
    }

    if(!array_key_exists("id", $_GET) || !array_key_exists("url", $_GET)) //if we didn't get both url and id params, die
    {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: Missing parameters\n", FILE_APPEND);
        }
        http_response_code(404);
        die();        
    }

    $requestedFile = $_GET['url']; // entry in the downloadtable that client requests off fastdownload.
    if(isPathMalicious($requestedFile)) //if the path is malicious, die
    {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: Malicious path detected: $requestedFile\n", FILE_APPEND);
        }
        http_response_code(404);
        die();
    }

    // Normalize: Source Engine may pass path with leading slashes or without.
    // We accept both and strip ALL leading slashes for consistent handling.
    $requestedFile = ltrim($requestedFile, '/');
    $arrrayRequestedFileFolders = explode("/", $requestedFile);
    if(!in_array($arrrayRequestedFileFolders[0], $allowedFolders)) //explode the real filepath by / and check whether the first folder is in allowed, if not - 404 and die.
    {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: Folder not allowed: " . $arrrayRequestedFileFolders[0] . "\n", FILE_APPEND);
        }
        http_response_code(404);
        die();
    }

    $requestedFingerprint = $_GET['id']; //fingerprint string
    
    // FIXED: Updated regex to accept hexadecimal characters (0-9, a-f, A-F)
    // The plugin now generates fingerprints in hex format: %08x%08x%08x
    if(!preg_match("/^[0-9a-fA-F]+$/", $requestedFingerprint)) //if id contains anything other than hex digits, die
    {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: Invalid fingerprint format: $requestedFingerprint\n", FILE_APPEND);
        }
        http_response_code(404);
        die();
    }
    
    // ADDED: Validate fingerprint length (must be exactly 32 characters for hex format)
    if(strlen($requestedFingerprint) !== 32)
    {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: Invalid fingerprint length: " . strlen($requestedFingerprint) . "\n", FILE_APPEND);
        }
        http_response_code(404);
        die();
    }

    if($DEBUG) {
        file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - Processing file: $requestedFile (fingerprint path: $fingerprintFilePath)\n", FILE_APPEND);
    }

    // ПРОВЕРКА: Является ли запрошенный файл файлом отпечатка?
    // ВАЖНО: Эта проверка срабатывает ТОЛЬКО для точного совпадения пути
    // Если клиент запросил .bz2 версию (например, server_joining2.mp3.bz2),
    // то $requestedFile !== $fingerprintFilePath, и функция НЕ вызовется
    // Это нормально - клиент получит 404 для .bz2 и запросит обычную версию
    if($requestedFile === $fingerprintFilePath)
        processFingerprintFile($requestedFile, $requestedFingerprint, $DEBUG, $serverId, $userId);

    $filePath = __DIR__."/".$requestedFile;
    
    // БЕЗОПАСНОСТЬ: Проверка существования файла выполняется после всех проверок безопасности
    if(!file_exists($filePath)) {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: File not found: $filePath\n", FILE_APPEND);
        }
        http_response_code(404);
        die();
    }
    
    if($DEBUG) {
        file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - SUCCESS: Serving file: $requestedFile\n", FILE_APPEND);
    }
    
    // БЕЗОПАСНОСТЬ: Проверяем, что файл действительно существует и это файл (не директория)
    if(!file_exists($filePath) || !is_file($filePath)) {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: File not found or is directory: $filePath\n", FILE_APPEND);
        }
        http_response_code(404);
        die();
    }
    
    // БЕЗОПАСНОСТЬ: Проверяем на симлинки (может быть уязвимостью)
    if(is_link($filePath)) {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: Symlink detected: $filePath\n", FILE_APPEND);
        }
        http_response_code(403);
        die();
    }
    
    // БЕЗОПАСНОСТЬ: Проверяем, что путь находится внутри __DIR__ (защита от path traversal)
    $realPath = realpath($filePath);
    $basePath = realpath(__DIR__);
    if($realPath === false || strpos($realPath, $basePath) !== 0) {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: Path traversal attempt: $filePath\n", FILE_APPEND);
        }
        http_response_code(403);
        die();
    }
    
    $fileSize = filesize($filePath);
    
    // БЕЗОПАСНОСТЬ: Ограничиваем максимальный размер файла (защита от больших файлов)
    $maxFileSize = 500 * 1024 * 1024; // 500 MB
    if($fileSize > $maxFileSize) {
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: File too large: $fileSize bytes\n", FILE_APPEND);
        }
        http_response_code(413);
        die();
    }
    
    // ОПТИМИЗАЦИЯ: Используем mod_xsendfile если доступен (Apache отдает файл напрямую)
    // Это намного быстрее, чем читать файл через PHP
    if (function_exists('apache_get_modules') && in_array('mod_xsendfile', apache_get_modules())) {
        // Очищаем буферы
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // БЕЗОПАСНОСТЬ: Используем realpath для X-Sendfile (защита от path traversal)
        $realFilePath = realpath($filePath);
        if($realFilePath === false || strpos($realFilePath, $basePath) !== 0) {
            http_response_code(403);
            die();
        }
        
        // Отправляем заголовки
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Disposition: attachment; filename="'.basename($requestedFile).'"');
        header('Content-Length: ' . $fileSize, true, 200);
        
        // Используем X-Sendfile - Apache отдает файл напрямую
        header('X-Sendfile: ' . $realFilePath);
        die();
    }
    
    // Fallback: отдача через PHP (если mod_xsendfile недоступен)
    // Очищаем все уровни буферизации
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Отключаем буферизацию на уровне PHP
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    ini_set('output_buffering', 'Off');
    ini_set('zlib.output_compression', 'Off');
    
    // Удаляем заголовки, которые ломают загрузку в Source Engine (ему нужен точный Content-Length, без chunked/gzip)
    if (function_exists('header_remove')) {
        header_remove('Transfer-Encoding');
        header_remove('Content-Encoding');
    }
    
    // Устанавливаем переменную окружения для Apache
    if (function_exists('apache_setenv')) {
        @apache_setenv('CONTENT_LENGTH', $fileSize);
    }
    putenv("CONTENT_LENGTH=$fileSize");
    
    // Отправляем заголовки
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Disposition: attachment; filename="'.basename($requestedFile).'"');
    header('Content-Length: ' . $fileSize, true, 200);
    
    // Логируем заголовки для отладки
    if($DEBUG) {
        $headers = headers_list();
        file_put_contents(__DIR__ . '/fastdl_debug.log', 
            date('Y-m-d H:i:s') . " - Headers sent:\n" . 
            print_r($headers, true) . 
            "File size: $fileSize bytes\n",
            FILE_APPEND
        );
    }
    
    // ОПТИМИЗАЦИЯ: Используем readfile с большим буфером для лучшей производительности
    // readfile() использует системный sendfile() если доступен
    readfile($filePath);
    die();
    
    function isPathMalicious($filePath) : bool
    {
        // Replace backslashes with forward slashes FIRST
        $filePath = str_replace('\\', '/', $filePath);
        $originalFilePath = $filePath;
        
        // Remove any occurrences of "../" or "./" in the path
        $filePath = str_replace(array('../', './'), '', $filePath);
      
        // Remove any characters that aren't letters, numbers, periods, hyphens, underscores or slashes
        $filePath = preg_replace('/[^a-zA-Z0-9.\-_\/]/', '', $filePath);
      
        // Normalize leading slashes: Source Engine may send with or without.
        // Only treat as malicious if sanitization changes something OTHER than leading slashes.
        $origNorm = ltrim($originalFilePath, '/');
        $sanNorm  = ltrim($filePath, '/');
        
        return $origNorm === $sanNorm ? false : true;
    }
    
    /**
     * Обрабатывает запрос на файл отпечатка (fingerprint file)
     * 
     * ВАЖНО: Эта функция вызывается ТОЛЬКО когда запрашивается файл,
     * указанный в $fingerprintFilePath (без расширения .bz2)
     * 
     * Как это работает:
     * 1. Клиент запрашивает файл отпечатка с параметром id (fingerprint)
     * 2. Функция создает файл динамически с содержимым = значению fingerprint
     * 3. Файл отдается клиенту напрямую (без записи на диск!)
     * 
     * ПРИМЕЧАНИЕ: Если клиент запросил .bz2 версию, эта функция НЕ вызывается,
     * т.к. проверка сравнивает $requestedFile с $fingerprintFilePath
     * без учета расширения .bz2. Это нормально - клиент получит 404 и запросит
     * обычную версию, которая уже обработается этой функцией.
     * 
     * @param string $filePath Путь к файлу отпечатка
     * @param string $fingerprintValue Значение fingerprint для отправки клиенту
     * @param bool $DEBUG Включить отладку
     * @param string $serverId ID сервера (для логирования, multi-server support)
     * @param int $userId ID пользователя на сервере (для логирования)
     */
    function processFingerprintFile($filePath, $fingerprintValue, $DEBUG, $serverId = 'unknown', $userId = 0)
    {
        $finalFile = __DIR__."/".$filePath;
        
        // БЕЗОПАСНОСТЬ: Ограничиваем размер fingerprint
        if(strlen($fingerprintValue) > 1024) {
            if($DEBUG) {
                file_put_contents(__DIR__ . '/fastdl_debug.log', date('Y-m-d H:i:s') . " - ERROR: Fingerprint too large\n", FILE_APPEND);
            }
            http_response_code(400);
            die();
        }
        
        // Логируем fingerprint запрос с информацией о сервере
        if($DEBUG) {
            file_put_contents(__DIR__ . '/fastdl_debug.log', 
                date('Y-m-d H:i:s') . " - FINGERPRINT SERVED: " . 
                "fp=" . $fingerprintValue . 
                " srv=" . $serverId . 
                " uid=" . $userId . 
                " IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'NONE') . "\n", 
                FILE_APPEND
            );
        }
        
        // DIRECT OUTPUT - DO NOT WRITE TO FILE
        // Writing to file causes race conditions when multiple players connect,
        // and leaves a static file that others might accidentally download.
        
        // Clear all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Disable PHP buffering/compression
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        ini_set('output_buffering', 'Off');
        ini_set('zlib.output_compression', 'Off');
        
        // Remove Transfer-Encoding if set
        if (function_exists('header_remove')) {
            header_remove('Transfer-Encoding');
            header_remove('Content-Encoding');
        }
        
        $contentLength = strlen($fingerprintValue);
        
        // Send headers
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Expires: 0');
        header('Cache-Control: no-cache, no-store, must-revalidate'); // Prevent caching!
        header('Pragma: no-cache');
        header('Content-Disposition: attachment; filename="'.basename($finalFile).'"');
        header('Content-Length: ' . $contentLength, true);
        
        // Output fingerprint directly
        echo $fingerprintValue;
            
        die();
    }
?>