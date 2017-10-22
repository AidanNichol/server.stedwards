<?php
require __DIR__ . '/../vendor/autoload.php';
use PHPOnCouch\Couch,
    PHPOnCouch\CouchAdmin,
    PHPOnCouch\CouchClient;

$couchdb_server_dsn = "http://localhost:5984";
$couchdb_database_name = 'devbookings';
$client = new CouchClient($couchdb_server_dsn, $couchdb_database_name);
$view = $client->limit(10)->descending(true)->getView('members','byMobile');
Kint::dump($view);
 ?>
