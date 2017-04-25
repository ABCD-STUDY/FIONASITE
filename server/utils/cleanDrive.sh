#!/bin/bash

#
# Delete transferred data entries that are older than 120 days from the FIONA system.
# Only data that has been send already to the DAIC will be deleted (size set to zero).
#
# 0 */23 * * * /var/www/html/server/utils/cleanDrive.sh >> /var/www/html/server/logs/cleanDrive.log

find /data/DAIC -name "*.tgz" -type f -mtime +120 -print0 | while read -d $'\0' file
do
   # check amount of free space and quit if there is still enough space left (computed in megabytes 1 963 374)
   FREE=`df --output=avail -h "/data/site/archive/" -m | sed '1d;s/[^0-9]//g'`
   # lets keep at least 3TB free on the disk
   if [[ $FREE -gt 3000000 ]]; then
      echo "`date`: enough ($FREE) memory available on this system"
      exit;
   fi
   STAMP=`date -r "$file"`
   /usr/bin/truncate --size=0 $file
   touch -d "$STAMP" "$file"
   echo "`date`: truncate $file to size 0"
   #ls -lah $file
done
