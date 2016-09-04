#!/bin/sh

# check_stop_predictions_process.sh
# Anthony Miles Flynn
# (4/9/16)
# Script for running the process to determine whether the stop predictions process
# is running and whether data being received, and to restart the process if not

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

php /data/individual_project/php/stop_predictions/check_stop_predictions_process.php >/dev/null

ret=$?

if [ $ret -eq 1 ]
then
   echo "email sent from cron" | mailx -s  "Full Data transfer problem: process had to be restarted" amf15@imperial.ac.uk
   php /data/individual_project/php/stop_predictions/stop_predictions_process.php >/dev/null 2>&1 &
elif [ $ret -eq 2 ]
then
   echo "email sent from cron" | mailx -s  "Full Data process not running: process had to be restarted" amf15@imperial.ac.uk
   php /data/individual_project/php/stop_predictions/stop_predictions_process.php >/dev/null 2>&1 & 
fi
exit 0
