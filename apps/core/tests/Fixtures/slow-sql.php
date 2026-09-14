<?php
$server=stream_socket_server('tcp://127.0.0.1:0');
echo stream_socket_get_name($server,false).PHP_EOL;flush();
$client=stream_socket_accept($server,15);
if($client){sleep(12);fclose($client);}fclose($server);
