<?php
/*******************************************************************************
 *
 *  The Model Church — ChurchCRM Configuration
 *  Auto-generated for Vercel / cloud deployment
 *  All values are read from environment variables — set these in Vercel dashboard
 *
 ******************************************************************************/

// Database — set these in Vercel > Project > Settings > Environment Variables
$sSERVERNAME = getenv('DB_HOST')     ?: $_ENV['DB_HOST']     ?? 'localhost';
$dbPort      = getenv('DB_PORT')     ?: $_ENV['DB_PORT']     ?? '3306';
$sUSER       = getenv('DB_USER')     ?: $_ENV['DB_USER']     ?? 'churchcrm';
$sPASSWORD   = getenv('DB_PASSWORD') ?: $_ENV['DB_PASSWORD'] ?? '';
$sDATABASE   = getenv('DB_NAME')     ?: $_ENV['DB_NAME']     ?? 'churchcrm';

// App root — empty string = installed at domain root (e.g. https://themodelchurch.vercel.app/)
$sRootPath = '';

// Primary URL — set automatically from Vercel env, or override here
$URL[0] = getenv('APP_URL') ?: $_ENV['APP_URL'] ?? 'https://themodelchurch.vercel.app/';

error_reporting(E_ERROR);

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'LoadConfigs.php');
