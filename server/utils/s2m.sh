#!/bin/bash
#
# send to me 
# Sends a DICOM directory using the dcmtk docker container from the local 
# machine to the local DICOM node. This script can be used to re-classify
# DICOM files (creates /data/site/raw and /data/site/participant information).
#
# Usage:
#
#    # Send a single directory with DICOM files
#    s2m.sh <DICOM directory to send>
#
#    # send all studies of the last 7 days
#    s2m.sh last 7
#

myip=`cat /data/config/config.json | jq ".DICOMIP" | tr -d '"'`
myport=`cat /data/config/config.json | jq ".DICOMPORT" | tr -d '"'`
if [[ $# -eq 0 ]]; then
   echo "usage: $0 <dicom directory>"
   exit
fi

if [[ $# -eq 1 ]]; then
   dir=`realpath "$1"`
   echo "Send data in \"$dir\" to $myip : $myport"
   docker run -it -v ${dir}:/input dcmtk /bin/bash -c "/usr/bin/storescu -v +sd +r -nh $myip $myport /input; exit"
   exit
fi

if [[ $# -eq 2 ]]; then
   if [[ "$1" -eq "last" ]]; then
       find /data/site/archive/ -mindepth 1 -type d -mtime "-$2" -print0 | while read -d $'\0' file
       do
	   dir=`realpath "$file"`
	   echo "Send data in \"$dir\" to $myip : $myport"
	   docker run -i -v ${dir}:/input dcmtk /bin/bash -c "/usr/bin/storescu -v +sd +r -nh $myip $myport /input; exit"
       done
   else
       echo "Error: we only understand something like: \"./s2m.sh last 7\" to resend the data for the last 7 days"
       exit
   fi
else
    echo "Error: wrong arguemnts"
    exit
fi
