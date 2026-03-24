<?php
$port = getenv("PORT") ?: 8000;
echo "Server Running";
shell_exec("php -S 0.0.0.0:$port");
?>
