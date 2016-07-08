#!/bin/bash

#
# run protocol compliance check
#
# This script will be called by incrond, install by adding to processing users incron
#   /var/www/html/php/request_compliance_check IN_CREATE,IN_MOVED_TO /var/www/html/server/bin/complianceCheck.sh $#
#

if [ "$#" -ne 1 ]; then
   echo "Usage: scp_study instance uid>"
   exit;
fi

SDIR=scp_$1

SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/compliance_check.log
echo "`date`: compliance check started ${SDIR}" >> $log


d=/data/site/output/${SDIR}/series_compliance
mkdir -p ${d}
machineid=compliance_check
SSDIR=${SDIR:4}
# remove the input file (for next time)
echo "`date`: remove the input file /var/www/html/php/request_compliance_check/$1" >> $log
rm -f "/var/www/html/php/request_compliance_check/$1"

echo "`date`: protocol compliance check (/usr/bin/nohup docker run -d -v /data/quarantine:/quarantine:ro -v ${d}:/output -v /data/site/archive/${SDIR}:/data/site/archive/${SDIR}:ro -v /data/site/raw/${SSDIR}:/input:ro ${machineid} /bin/bash -c \"/root/work.sh /input /output /quarantine\" 2>&1 >> $log &)" >> $log
id=$(docker run -v /data/quarantine:/quarantine:ro -v ${d}:/output -v /data/site/archive/${SDIR}:/data/site/archive/${SDIR}:ro -v /data/site/raw/${SSDIR}:/input:ro ${machineid} /bin/bash -c "/root/work.sh /input /output /quarantine" 2>&1 >> /tmp/watch.log)
echo "`date`: compliance check finished for ${SDIR} with \"$id\"" >> $log

# lets do some cleanup and remove any unused docker containers
#$(docker rm -v $(docker ps -a -q -f status=exited))
#echo "`date`: cleanup of unused containers done..." >> $log
