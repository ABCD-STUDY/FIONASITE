#!/bin/bash

#
# We still have a problem with processSingleFiles. It creates
# sometimes directories that belong to root instead of processing.
# This is only a temporary fix - have to find out what is happening
# there.
#
# Run as root user cron job
#       */10 * * * * /var/www/html/server/utilts/fixDirectories.sh
#

dirloc=/data/site/raw/

# make sure this script runs only once
thisscript=`basename "$0"`
for pid in $(/usr/sbin/pidof -x "$thisscript"); do
    if [ $pid != $$ ]; then
        echo "[$(date)] : ${thisscript} : Process is already running with PID $pid"
        exit 1
    fi
done

find "${dirloc}" -maxdepth 1 -type d -print0 | while read -d $'\0' file
do
  chown processing:processing  "$file"
done

# now loop through the directory and process each file
inotifywait -m -e create,moved_to --format '%w%f' "${dirloc}" | while read NEWFILE
do
  # set the correct owner
  chown processing:processing "${NEWFILE}"
done
