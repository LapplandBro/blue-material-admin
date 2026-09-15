<?php 
if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}

class CErrorHandler {
    private $fatalcodes = array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR);

    public function __construct() {
        set_error_handler([$this, 'BasicErrorCatcher']);
        register_shutdown_function([$this, 'FatalErrorCatcher']);
        
        $this->StartOutputBuffer();
    }
    
    private function CloseOutputBuffer($bRender) {
        if ($bRender)
            ob_end_flush();
        else
            ob_end_clean();
    }
    
    private function StartOutputBuffer() {
        ob_start();
    }
    
    private function DrawErrorMessage($message, $function = null, $title = "Ошибка системы") {
        $this->CloseOutputBuffer(false);

        if (!headers_sent())
            header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head><body>';
        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<pre style="white-space:pre-wrap;">' . htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') . '</pre>';
        if ($function)
            echo '<p>' . str_replace("\n", "<br />", htmlspecialchars((string)$function, ENT_QUOTES, 'UTF-8')) . '</p>';
        echo '<p><a href="index.php?p=admin">Админка</a> · <a href="index.php">На главную</a></p>';
        echo '</body></html>';
    }
    
    public function BasicErrorCatcher($errno, $errstr, $errfile, $errline) {
        // set_error_handler получает ВСЕ уровни, даже если error_reporting их маскирует.
        // Deprecated/Strict от легаси-кода на PHP 8.2+ не показываем и не логируем.
        if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED || $errno === E_STRICT || $errno === E_NOTICE)
            return true;

        /* Moved from /var/www/g-44/data/www/bans.g-44.ru/init.php */
        if(!is_object($GLOBALS['log']))
            return false;
        
        $retValue = true;
        if (function_exists('error_clear_last'))
            error_clear_last();
        
        switch ($errno) {
            case E_USER_ERROR:
                $msg = "[$errno] $errstr<br />\n";
                $msg .= "Произошла фатальная ошибка на строке $errline в файле $errfile";
                $log = new CSystemLog("e", "PHP Error", $msg);
        
                // SourceBans Fatal Error Handler //
                // include(INCLUDES_PATH.'/FatalErrorHandler.php');
                $this->DrawErrorMessage($msg, $log->parent_function);
                // SourceBans Fatal Error Handler //

                $retValue = false;
                exit(1);
                break;

            case E_USER_WARNING:
                $msg = "[$errno] $errstr<br />\n";
                $msg .= "Ошибка на строке $errline в файле $errfile";
                $GLOBALS['log']->AddLogItem("w", "PHP Warning", $msg);
                break;

            case E_USER_NOTICE:
                $msg = "[$errno] $errstr<br />\n";
                $msg .= "Уведомление на строке $errline в файле $errfile";
                $GLOBALS['log']->AddLogItem("m", "PHP Notice", $msg);
                break;

            default:
                $retValue = false;
                break;
        }

        /* Don't execute PHP internal error handler */
        return $retValue;
    }
    
    public function FatalErrorCatcher() {
        $error = error_get_last();
        if ($error === NULL || $error['type'] !== E_ERROR) {
            $this->CloseOutputBuffer(true);
            return;
        }

        if (in_array($error['type'], $this->fatalcodes)) {
            $this->CloseOutputBuffer(false);
            $ctype = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
            $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0)
                || stripos($ctype, 'application/json') !== false
                || !empty($_POST['sb_ajax']);
            if ($isAjax) {
                if (!headers_sent()) {
                    header('HTTP/1.1 200 OK');
                    header('Content-Type: application/json; charset=utf-8');
                }
                $msg = 'Ошибка PHP: ' . $error['message'];
                $js = 'if(typeof ShowBox==="function"){ShowBox("Ошибка",' . json_encode($msg, JSON_UNESCAPED_UNICODE) . ',"red","",true);}else{alert(' . json_encode($msg, JSON_UNESCAPED_UNICODE) . ');}';
                echo json_encode(array('ok' => false, 'error' => $msg, 'cmds' => array(array('n' => 'js', 'data' => $js))), JSON_UNESCAPED_UNICODE);
                return;
            }
            $this->DrawErrorMessage("Произошла фатальная ошибка PHP\n" . $error['message'] . "\n\n" . $error['file'] . "::" . $error['line'], null, "Критическая ошибка PHP");
        }
    }
}
