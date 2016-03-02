#!/bin/bash

#
# MPPS generates a MPPS.* file in /data/scanner/MPPS.<study instance uid>
# incron detects the new file and calls this script (newStudyOnScanner.sh).
# This script will create a touch file in /data/active-scans/<study instance uid>
# For each file in /data/active-scans/ we will try to pull images using movescu for 
# each series that does not exist already.
#

# we have a new study on the scanner, lets try to find out more about it (series) and copy them
SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/newStudyOnScanner.log
echo "$*" >> $log

# we need to get the studyInstanceUID into the to pull folder
l=`/usr/bin/dcmdump +P "StudyInstanceUID" $1/$2`
val=`echo $l | cut -d'[' -f2 | cut -d']' -f1`
if [[ "$val" == "$l" ]]; then
  val="-"
  echo "ERROR: could not read StudyInstanceUID from $1/$2" >> $log
  exit
fi

# $val is now the study instance uid, work on this study until we are done
if [ ! -d "/data/active-scans" ]; then
   mkdir -p "/data/active-scans"
fi
tfile="/data/active-scans/${val}"
/usr/bin/touch "${tfile}"
if [ ! -f "$tfile" ]; then
  echo "ERROR: could not create touch file as /data/active-scans/${val}" >> $log
fi
