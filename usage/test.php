<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once 'directory.php';

$queryObject = new Title();
// try to find the title via title identifiers
$result = $queryObject->getTitleIdByTitleIdentifier(22134379, 'eissn', 'Journal');
print_r($result);
