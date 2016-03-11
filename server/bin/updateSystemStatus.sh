#!/bin/bash

#
# This script is called by incrond (root) if someone changes the contents of /data/enabled.
#
# Characters in the /data/enabled file are used to control storescp (first character "0" to disable)
# and the mpps system service (second character "0" to disable).
#
# enable all:
#   echo "111" > /data/enabled
#

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

# start storescp
if [[ "$v1" == "0" ]]; then
   su - processing -c "${SERVERDIR}/bin/storectl.sh stop"
   su - processing -c "/usr/bin/python2.7 ${SERVERDIR}/bin/processSingleFile.py stop"
   echo "`date`: disabled storescp services" >> $log
else
   su - processing -c "${SERVERDIR}/bin/storectl.sh start"
   su - processing -c "/usr/bin/python2.7 ${SERVERDIR}/bin/processSingleFile.py start"
   echo "`date`: enable storescp services" >> $log
fi
if [[ "$v2" == "0" ]]; then
   su - processing -c "${SERVERDIR}/bin/mppsctl.sh stop"
   echo "`date`: disabled mpps services" >> $log
else
   echo "`date`: enable mpps services" >> $log
   su - processing -c "${SERVERDIR}/bin/mppsctl.sh start"
fi
if [[ "$v3" == "0" ]]; then
   echo "`date`: disabled anonymizer services" >> $log
else
   echo "`date`: enable anonymizer services" >> $log
fi

