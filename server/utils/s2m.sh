#!/bin/bash
#
# send to me (s2m)
# Sends a DICOM directory using the dcmtk docker container from the local 
# machine to the local DICOM node. This script can be used to re-classify
# DICOM files (creates /data/site/raw and /data/site/participant information).
#
# Usage:
#
#    # Send a single directory with DICOM files
#    s2m.sh <DICOM directory to send>
#
#    # Send all studies of a single PatientID
#    s2m.sh <PatientID>
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
   if [ ! -d "$dir" ]; then
       # path does not exist, perhaps we got a subject id - find and select first study with that ID
       dd=`jq -r "[.PatientID,.StudyInstanceUID] | @csv" /data/site/raw/*/*.json | grep $1 | sort | uniq | head -1 | cut -d',' -f2 | tr -d '"'`
       for a in $dd; do
	   # call ourselfs again with the participant id
	   dir=`realpath "/data/site/archive/scp_$a"`
	   echo "submit one study $dir.."
	   "$0" "$dir"
       done
       #if [ ! -d "$dir" ]; then
       #  echo "This directory or patient id could not be found"
       #	 exit
       #fi
   else
       echo "$dir is a path "
   fi

   echo "Send data in \"$dir\" to $myip : $myport"
   docker run -it -v ${dir}:/input dcmtk /bin/bash -c "/usr/bin/storescu -v +sd +r -nh $myip $myport /input; exit"
   if [[ $? -ne "0" ]]; then
       # sending using docker is fastest, but it can fail due to network issues, lets send straight using storescu in that case
       echo "sending with docker failed, send using storescu instead"
       /usr/bin/storescu -v -aet me -aec me +sd +r -nh $myip $myport "${dir}"
   fi
   exit
fi

if [[ $# -eq 2 ]]; then
   if [[ "$1" -eq "last" ]]; then
       find /data/site/archive/ -mindepth 1 -type d -mtime "-$2" -print0 | while read -d $'\0' file
       do
	   dir=`realpath "$file"`
	   echo "Send data in \"$dir\" to $myip : $myport"
	   # make sure that we redirect stdin, otherwise this line will eat up the file variable in our loop and we can only submit a single line
	   /bin/bash -c "docker run -i -v ${dir}:/input dcmtk /bin/bash -c \"/usr/bin/storescu -v +sd +r -nh $myip $myport /input; exit\" </dev/null"
           if [[ $? -ne "0" ]]; then
	       # sending using docker is fastest, but it can fail due to network issues, lets send straight using storescu in that case
	       echo "sending with docker failed, send using storescu instead"
	       /usr/bin/storescu -v -aet me -aec me +sd +r -nh $myip $myport "${dir}"
	   fi
       done
   else
       echo "Error: we only understand something like: \"./s2m.sh last 7\" to resend the data for the last 7 days"
       exit
   fi
else
    echo "Error: wrong arguemnts"
    exit
fi
