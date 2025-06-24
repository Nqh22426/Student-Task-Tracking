<?php
header('Content-Type: application/json');
echo json_encode(['server_time' => date('Y-m-d H:i:s')]);
?> 