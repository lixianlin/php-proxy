<?php
error_reporting(E_ALL | E_STRICT);
set_time_limit(0);
require_once 'proxy.php';
$proxy = new Proxy();
$proxy->start();