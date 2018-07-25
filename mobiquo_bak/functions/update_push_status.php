<?php

defined('IN_MOBIQUO') or exit;

require_once('./global.php');

function update_push_status_func($xmlrpc_params)
{
    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
    ), 'struct'));
}