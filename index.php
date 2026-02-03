<?php
http_response_code(200);

file_put_contents(
    "PING.txt",
    "PING " . date("Y-m-d H:i:s") . "\n",
    FILE_APPEND
);

echo "OK";
