#!/bin/sh

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
ps aux | grep '[s]erver_script_uniqueid.php'
if [ $? -ne 0 ]
then
   echo "email sent from cron" | mailx -s  "UniqueID Server had to be restarted" amf15@imperial.ac.uk
   php /data/individual_project/php/server_script/uniqueid/server_script_uniqueid.php >/dev/null 2>&1 & 
fi
exit 0
