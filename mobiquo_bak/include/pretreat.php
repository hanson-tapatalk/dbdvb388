<?php
if (isset($_GET['welcome']))
{
    include('./smartbanner/app.php');
    exit;
}

if (isset($_POST['method_name']) && $_POST['method_name'] == 'verify_connection'){
    $type = isset($_POST['type']) ? $_POST['type'] : 'both';
    $code = isset($_POST['code']) ? $_POST['code'] : '';
    $connection = new classTTConnection();
    @ob_clean();
    echo serialize($connection->verify_connection($type, $code));
    exit;
}

if($_SERVER['REQUEST_METHOD'] == 'GET' && (!isset($_GET['method_name']) || (isset($_GET['method_name']) && $_GET['method_name'] != 'set_api_key')))
{
    include 'web.php';
    exit;
}