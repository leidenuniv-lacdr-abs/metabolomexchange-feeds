<?php
header("Access-Control-Allow-Origin: *"); // required for all clients to connect
header('Content-type: application/json');
echo file_get_contents('providers.json');
?>