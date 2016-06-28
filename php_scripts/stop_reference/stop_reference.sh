#!/bin/sh

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

php /data/individual_project/php/stop_reference/server_script/server_script_stop_reference.php >/dev/null 2>&1 

ret=$?

if [ $ret -eq 1 ]
then
  echo "email sent from cron" | mailx -s  "Stop reference data updated" amf15@imperial.ac.uk
fi
exit 0
