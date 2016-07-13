#!/bin/sh

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

php /data/individual_project/php/shell_script/uniqueid/restart_server_data_uniqueid.php >/dev/null

ret=$?

if [ $ret -eq 1 ]
then
   echo "email sent from cron" | mailx -s  "Uniqueid data transfer problem: Server had to be restarted" amf15@imperial.ac.uk
   php /data/individual_project/php/server_script/uniqueid/server_script_uniqueid.php >/dev/null 2>&1 & 
elif [ $ret -eq 2 ]
then
   echo "email sent from cron" | mailx -s  "Uniqueid process not running: Server had to be restarted" amf15@imperial.ac.uk
   php /data/individual_project/php/server_script/uniqueid/server_script_uniqueid.php >/dev/null 2>&1 & 
fi
exit 0
