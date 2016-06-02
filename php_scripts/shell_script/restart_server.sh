#!/bin/sh

ps aux | grep '[s]erver_script.php'
if [ $? -ne 0 ]
then
    php /homes/amf15/individual_project/php/server_script/server_script.php
    >&2 echo "Server had to be restarted"
fi
