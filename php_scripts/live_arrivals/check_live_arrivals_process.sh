#!/bin/sh

# check_live_arrivals_process.sh
# Anthony Miles Flynn
# (4/9/16)
# Script for running the process to determine whether the live arrivals process
# is running, and to restart the process if not

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

php /data/individual_project/php/live_arrivals/check_live_arrivals_process.php >/dev/null

ret=$?

if [ $ret -eq 1 ]
then
   echo "email sent from cron" | mailx -s  "Live Arrivals process not running: process had to be restarted" amf15@imperial.ac.uk
   php /data/individual_project/php/live_arrivals/live_arrivals_process_5min.php >/dev/null 2>&1 & 
fi
exit 0
