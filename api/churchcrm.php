<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir(__DIR__ . '/../src');
require 'index.php';