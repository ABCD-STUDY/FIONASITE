#!/bin/bash

#
# This script is called by incrond (root) if someone changes the contents of /data/enabled.
#
# Characters in the /data/enabled file are used to control storescp (first character "0" to disable)
# and the mpps system service (second character "0" to disable).
#
# We can run in two different modes. We can either disable MPPS as a system service on this machine
# or we can leave it running but not react to its messages. If we don't leave it running the
# scanner console will show errors because it cannot deliver the PPS messages. If we don't react
# to the messages its hard to see from the outside if we are indeed switched off (no free lunch).
#
# enable all:
#   echo "111" > /data/enabled
#

# mppsoff will switch off the MPPS service (will produce transfer errors on the scanner console)
# If you leave this line commented out the service will continue to work but every received message
# will be removed uppon receipt and no DICOM pull to the server is started.
#mode="mppsoff"
mode=`cat /data/config/config.json | jq -r ".MPPSMODE"`

if [ $# -ne 2 ]; then
  echo "Usage: <directory> <file>"
  exit
fi

# P: path F: file
P=$1
F=$2
SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/enabled.log

if [[ ! ${F} == "enabled" ]]; then
  echo "only enabled is supported as a control file" >> $log
  exit
fi

if [[ ! -f "${P}/${F}" ]]; then
  echo "control file ${P}/${F} not found" >> $log
  exit
fi

export DCMDICTPATH=/usr/share/dcmtk/dicom.dic

vv=`cat ${P}/${F}`
v1=${vv:0:1}
v2=${vv:1:1}
v3=${vv:2:3}

echo "called with $P $F $v1, $v2, $v3" >> $log

# start storescp
if [[ "$v1" == "0" ]]; then
   su - processing -c "${SERVERDIR}/bin/storectl.sh stop; sleep 1; /usr/bin/python2.7 ${SERVERDIR}/bin/processSingleFile.py stop"
   echo "`date`: disabled storescp services" >> $log
else
   #echo "su - processing -c \"${SERVERDIR}/bin/storectl.sh start &>${SERVERDIR}/logs/storectl-sh.log\"" >> $log
   #su - processing -c "/usr/bin/python2.7 ${SERVERDIR}/bin/processSingleFile.py start; sleep 1; ${SERVERDIR}/bin/storectl.sh start &>${SERVERDIR}/logs/storectl-sh.log"
   su - processing -c "/usr/bin/python2.7 ${SERVERDIR}/bin/processSingleFile.py start; sleep 1; ${SERVERDIR}/bin/storectl.sh start &"
   echo "`date`: enable storescp services" >> $log
fi
if [[ "$v2" == "0" ]]; then
   # if you want to switch off the service leave this line in there
   if [[ "$mode" == "mppsoff" ]]; then
     su - processing -c "${SERVERDIR}/bin/mppsctl.sh stop"
     echo "`date`: disabled mpps services" >> $log
   fi
else
   if [[ "$mode" == "mppsoff" ]]; then
     echo "`date`: enable mpps services" >> $log
     # if you want to switch off the service leave this line in there
     su - processing -c "${SERVERDIR}/bin/mppsctl.sh start"
   fi
fi
if [[ "$v3" == "0" ]]; then
   echo "`date`: disabled anonymizer services" >> $log
else
   echo "`date`: enable anonymizer services" >> $log
fi

