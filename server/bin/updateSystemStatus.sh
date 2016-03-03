#!/bin/bash

#
# This script is called by incrond (root) if someone changes the contents of /data/enabled.
#
# If the file contains the character "0" all receive actions on this machine are disabled.
# If the file contains the character "1" all receive actions on this machine are enabled.
#
# enable:
#   echo "1" > /data/enabled
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

# We should find out if we have our services enabled right now
# (storescp,mpps,heartbeat). We don't have to enable again
# if its already running.
enabled=0
if [[ `service cron status` == "cron stop/waiting" ]]; then
  enabled=0
else 
  enabled=1
fi

v=`cat ${P}/${F}`
if [[ "$v" == "0" ]]; then
   # stop the cron system service
   # service cron stop
   systemctl stop crond
   su - processing -c "${SERVERDIR}/bin/storectl.sh stop"
   su - processing -c "${SERVERDIR}/bin/mppsctl.sh stop"
   su - processing -c "${SERVERDIR}/bin/heartbeat.sh stop"
   echo "`date`: disabled system services" >> $log
else
   # start the cron system service (which starts all other services)
   su - processing -c "${SERVERDIR}/bin/storectl.sh start"
   su - processing -c "${SERVERDIR}/bin/mppsctl.sh start"
   su - processing -c "${SERVERDIR}/bin/heartbeat.sh start"
   #service cron start
   systemctl start crond
   echo "`date`: enabled system services" >> $log
fi

