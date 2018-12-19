#!/bin/bash

#
# MPPS generates a MPPS.* file in /data/scanner/MPPS.<study instance uid>
# incron detects the new file and calls this script (newStudyOnScanner.sh).
# This script will create a touch file in /data/active-scans/<study instance uid>
# For each file in /data/active-scans/ we will try to pull images using movescu for 
# each series that does not exist already.
#

export DCMDICTPATH=/usr/share/dcmtk/dicom.dic

# we have a new study on the scanner, lets try to find out more about it (series) and copy them
SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/newStudyOnScanner.log
echo "`date`: $*" >> $log

if [ "$#" -ne 2 ]; then
   echo "`date`: Error: incorrect call, expects two arguments, got $#" >> $log
   exit
fi

# Check if we have MPPS on (if we have it off we should not copy to active-scans
# actually we should remove the MPPS file as it might contain patient information for
# non ABCD participants.
if [[ -f /data/config/enabled ]]; then
  enabled=`cat /data/config/enabled | head -c 2 | tail -c 1`
  if [[ "$enabled" == "0" ]]; then
     /bin/rm -f "$1/$2"
     echo "`date`: Switched OFF, deleted MPPS file $1/$2 uppon receive" >> $log
     exit
  fi
fi

# we need to get the studyInstanceUID into the to pull folder
# lets give the system some time to write the file
l=`sleep 1; /usr/bin/dcmdump +P "StudyInstanceUID" $1/$2`
val=`echo $l | cut -d'[' -f2 | cut -d']' -f1`
if [[ "$val" == "$l" ]]; then
  echo "`date`: ERROR: could not read StudyInstanceUID from $1/$2, got \"$val\"" >> $log
  val="-"
  # call yourself in 5 seconds again, it might take a while to get a StudyInstanceUID from MPPS
  /usr/bin/nohup /bin/bash -c "sleep 5; $0 $1 $2 &" &
  exit
else
  echo "`date`: got StudyInstanceUID: \"$val\"" >> $log
fi

# $val is now the study instance uid, work on this study until we are done
if [ ! -d "/data/active-scans" ]; then
   mkdir -p "/data/active-scans"
fi
tfile="/data/active-scans/${val}"
/usr/bin/touch "${tfile}"
if [ ! -f "$tfile" ]; then
  echo "`date`: ERROR: could not create touch file as /data/active-scans/${val}" >> $log
else
  echo "`date`: Info: created ${tfile}" >> $log
fi
