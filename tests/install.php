<?php

/**
 * Open a connection via PDO to create databases.
 */

$host       = "127.0.0.1";
$username   = "root";
$password   = "";
$dbname     = "test_coral"; // will use later
$options    = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
$dbs        = [
  "test_coral_1",
  "test_coral_2",
  "test_coral_3",
  "test_coral_4",
  "test_coral_5",
  "test_coral_6",
  "test_coral_7",
  ];

try {
	$pdo = new PDO("mysql:host=$host", $username, $password, $options);
  foreach ($dbs as $dbname) {
    $pdo->exec("
      CREATE DATABASE IF NOT EXISTS $dbname;
      CREATE USER 'test_coral'@'localhost' IDENTIFIED BY 'test_coral';
      GRANT ALL ON `$dbname`.* TO 'test_coral'@'localhost';
      FLUSH PRIVILEGES;
    ");
  }
	echo "databases created successfully";
}

catch(PDOException $error) {
	echo $error->getMessage();
}
