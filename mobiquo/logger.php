<?php
function TT_InitAccessLog()
{
    global $tapatalk_access_log;
    $logPath = MBQ_PATH . 'log';
    if(!empty($logPath) && is_writable($logPath) && file_exists(MBQ_PATH . 'log.on'))
    {
        include_once(MBQ_3RD_LIB_PATH .'KLogger.php');
        $tapatalk_access_log = new KLogger($logPath, KLogger::INFO, 'access_log_' . @date('Y-m-d') . '.txt');
    }
}
function TT_InitErrorLog()
{
    global $tapatalk_error_log, $tapatalk_old_error_handler;
    $logPath = MBQ_PATH . 'log';
    if(!empty($logPath) && is_writable($logPath) && MBQ_DEBUG)
    {
        include_once(MBQ_3RD_LIB_PATH .'KLogger.php');
        $tapatalk_error_log = new KLogger($logPath, KLogger::INFO, 'error_log_' . @date('Y-m-d') . '.txt');
    }
    if(is_a($tapatalk_error_log, 'KLogger'))
    {
        $tapatalk_old_error_handler = set_error_handler("TT_ErrorHandler", MBQ_DEBUG);
        if(defined('IN_MOBIQUO'))
        {
            set_exception_handler('TT_ExceptionHandler');
        }
    }
}
function TT_logCall($protocol, $method, $input)
{
    global $tapatalk_access_log;
    if(is_a($tapatalk_access_log, 'KLogger') && file_exists(MBQ_PATH . 'log.on'))
    {
        $ip= 'No ip available';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        unset($input['useragent']);

        $logString 	= $ip . ' - ' . strtoupper($protocol) . ' - '  . $method;
        if(!empty($input))
        {
            $logString .= ' => ' . base64_encode(print_r($input, true));
        }
        $tapatalk_access_log->logInfo($logString);
    }
}
function TT_ErrorHandler($errno, $errstr, $errfile, $errline)
{
    global $tapatalk_error_log, $tapatalk_old_error_handler;
    if(is_a($tapatalk_error_log, 'KLogger'))
    {
        $error_string 	= 'PHP ' . $errno . '::' . $errstr . " in " . $errfile . " on line " . $errline . PHP_EOL;
        switch ($errno) {
            case E_ERROR:
            case E_USER_ERROR:
                $tapatalk_error_log->logError($error_string);
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $tapatalk_error_log->logWarn($error_string);
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $tapatalk_error_log->logNotice($error_string);
                break;

            default:
                $tapatalk_error_log->logInfo($error_string);
                break;
        }
    }
    if(MBQ_DEBUG && isset($tapatalk_old_error_handler))
    {
        restore_error_handler();
        return false;
    }
    return true;
}
function TT_ExceptionHandler($ex)
{
    global $tapatalk_error_log;
    if(is_a($ex, 'Exception'))
    {
        if(is_a($tapatalk_error_log, 'KLogger'))
        {
            TT_ErrorHandler($ex->getCode(), $ex->getMessage() . PHP_EOL . $ex->getTraceAsString() . PHP_EOL . PHP_EOL . "Method input receive: " . PHP_EOL . print_r(MbqMain::$input, true), $ex->getFile(), $ex->getLine());
        }
    }
}