#!/bin/sh

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

php /data/individual_project/php/stop_predictions/check_stop_predictions_process.php >/dev/null

ret=$?

if [ $ret -eq 1 ]
then
   echo "email sent from cron" | mailx -s  "Full Data transfer problem: Server had to be restarted" amf15@imperial.ac.uk
   php /data/individual_project/php/stop_predictions/stop_predictions_process.php >/dev/null 2>&1 &
elif [ $ret -eq 2 ]
then
   echo "email sent from cron" | mailx -s  "Full Data process not running: Server had to be restarted" amf15@imperial.ac.uk
   php /data/individual_project/php/stop_predictions/stop_predictions_process.php >/dev/null 2>&1 & 
fi
exit 0
