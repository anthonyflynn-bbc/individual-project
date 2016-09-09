#!/bin/sh

# check_reference_update.sh
# Anthony Miles Flynn
# (9/9/16)
# Script for running the php script to determine whether reference data
# (stop reference and route reference) needs to be updated

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

php /data/individual_project/php/reference_update/update_reference.php >/dev/null 2>&1 
ret=$?

if [ $ret -eq 1 ]
then
  echo "email sent from cron" | mailx -s  "Stop reference data updated" amf15@imperial.ac.uk
fi
exit 0
