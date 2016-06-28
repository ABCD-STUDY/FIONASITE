#!/bin/bash

#
# run protocol compliance check
#

if [ "$#" -ne 1 ]; then
   echo "Usage: <study instance uid>"
   exit;
fi

SDIR=$1

SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/detectStudyArrival.log


d=/data/site/output/${SDIR}/series_compliance
mkdir -p ${d}
machineid=machine57080de9bbc3d
SSDIR=${SDIR:4}
echo "`date`: protocol compliance check (/usr/bin/nohup docker run -d -v /data/quarantine:/quarantine:ro -v ${d}:/output -v /data/site/archive/${SDIR}:/data/site/archive/${SDIR}:ro -v /data/site/raw/${SSDIR}:/input:ro ${machineid} /bin/bash -c \"/root/work.sh /input /output /quarantine\" 2>&1 >> $log &)" >> $log
id=$(docker run -v /data/quarantine:/quarantine:ro -v ${d}:/output -v /data/site/archive/${SDIR}:/data/site/archive/${SDIR}:ro -v /data/site/raw/${SSDIR}:/input:ro ${machineid} /bin/bash -c "/root/work.sh /input /output /quarantine" 2>&1 >> /tmp/watch.log)
echo "`date`: compliance check finished for ${SDIR} with \"$id\"" >> $log

# lets do some cleanup and remove any unused docker containers
$(docker rm -v $(docker ps -a -q -f status=exited))
echo "`date`: cleanup of unused containers done..." >> $log
