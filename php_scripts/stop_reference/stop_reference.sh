#!/bin/sh

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
   php /data/individual_project/php/stop_reference/server_script/server_script_stop_reference.php >/dev/null 2>&1 & 
exit 0
