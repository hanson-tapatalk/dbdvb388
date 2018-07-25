<?php

$mbqDebug = false;
if(isset($_SERVER['HTTP_X_PHPDEBUG']))
{
    if(isset($_SERVER['HTTP_X_PHPDEBUGCODE']))
    {
        $code = trim($_SERVER['HTTP_X_PHPDEBUGCODE']);
        if (!class_exists('classTTConnection')){
            require_once(MBQ_3RD_LIB_PATH.'classTTConnection.php');
        }
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code,'PHPDEBUG');
        if($response)
        {
            $mbqDebug = $_SERVER['HTTP_X_PHPDEBUG'];
        }
    }
    else if(file_exists(MBQ_PATH . 'debug.on'))
    {
        $mbqDebug = $_SERVER['HTTP_X_PHPDEBUG'];
    }
}


define('MBQ_DEBUG', $mbqDebug);  /* is in debug mode flag */
if (MBQ_DEBUG) {
    ini_set('display_errors','1');
    ini_set('display_startup_errors','1');
    error_reporting($mbqDebug);
    require_once(MBQ_PATH . 'logger.php');
    TT_InitErrorLog();
    if(isset($_REQUEST['method_name']) && $_REQUEST['method_name']=='exception_test')
    {
        $code = $_REQUEST['code'];
        $exceptionType = $_REQUEST['exception_type'];
        include_once(MBQ_3RD_LIB_PATH . 'classTTConnection.php');
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code,'exception_test');
        if($response)
        {
            switch($exceptionType)
            {
                case 'error':
                    {
                        trigger_error('ERROR', E_USER_ERROR);
                        break;
                    }
                case 'warning':
                    {
                        trigger_error('WARNING', E_USER_WARNING);
                        break;
                    }
                case 'notice':
                    {
                        trigger_error('NOTICE', E_USER_NOTICE);
                        break;
                    }
            }
            die;
        }
    }
} else {    // Turn off all error reporting
    error_reporting(0);
}

