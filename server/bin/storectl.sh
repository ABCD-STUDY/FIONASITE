#!/bin/bash
# filename: storescpd
#
# purpose: start storescp server for processing user at boot time to receive data
#          Move files to project specific file system
#
# This system service will fail if a control file /data/enabled exists and 
# its first character is a "0".
#
# (Hauke Bartsch)

SERVERDIR=`dirname "$(readlink -f "$0")"`/../

DATADIR=`cat /data/config/config.json | jq -r ".DATADIR"`
# if datadir has not be set in config
if [ "$DATADIR" == "null" ]; then
   echo "no datadir set in config file, assume ABCD default directory /data/"
   DATADIR="/data"
fi


port=`cat /data/config/config.json | jq -r ".DICOMPORT"`
pidfile=${SERVERDIR}/.pids/storescpd.pid
pipe=/tmp/.processSingleFilePipe

projname="$2"
if [ -z "$projname" ]; then
    projname="ABCD"
else
    if [ "$projname" != "ABCD" ]; then
	DATADIR=`cat /data/config/config.json | jq -r ".SITES.${projname}.DATADIR"`
	port=`cat /data/config/config.json | jq -r ".SITES.${projname}.DICOMPORT"`
	pidfile=${SERVERDIR}/.pids/storescpd${projname}.pid
	pipe=/tmp/.processSingleFilePipe${projname}
    fi
fi
ARRIVEDDIR=${DATADIR}/site/.arrived

#echo $projname
#echo $DATADIR
#echo $port

od="${DATADIR}/site/archive"

scriptfile=${SERVERDIR}/bin/receiveSingleFile.sh

export DCMDICTPATH=/usr/share/dcmtk/dicom.dic

#
# the received file will be written to a named pipe which is evaluated by processSingleFile.py
#
case $1 in
    'start')
        if [[ -f ${DATADIR}/config/enabled ]] && [[ -r ${DATADIR}/config/enabled ]]; then
           v=`cat ${DATADIR}/config/enabled | head -c 1`
           if [[ "$v" == "0" ]]; then
              echo "`date`: service disabled using ${SERVERDIR}/config/enabled control file" >> ${SERVERDIR}/logs/storescpd${projname}.log
              echo "service disabled using ${SERVERDIR}/config/enabled control file"
              exit
           fi
        fi

        if [ ! -d "$od" ]; then
          mkdir $od
        fi
        # check if we have a pipe to send events to
        if [[ "$(/usr/bin/test -p ${pipe})" != "0" ]]; then
            echo "Found pipe ${pipe}..."
        else
            echo "Error: the pipe of processSingleFile.py \"$pipe\" could not be found for ${projname}"
            exit -1
        fi
        echo "Check if storescp daemon is running..."
        /usr/bin/pgrep -f -u processing "storescpFIONA .*${port}"
        RETVAL=$?
        [ $RETVAL = 0 ] && exit || echo "storescpd process not running, start now.."
        echo "Starting storescp daemon..."
        echo "`date`: we try to start storescp by: /usr/bin/nohup /usr/bin/storescpFIONA --fork --promiscuous --write-xfer-little --exec-on-reception \"$scriptfile '#a' '#c' '#r' '#p' '#f' &\" --sort-on-study-uid scp --output-directory \"$od\" $port &>${SERVERDIR}/logs/storescpd.log &" >> ${SERVERDIR}/logs/storescpd-start.log

        /usr/bin/nohup /var/www/html/server/bin/storescpFIONA --fork \
	    --datadir ${ARRIVEDDIR} \
	    --datapipe ${pipe} \
	    --promiscuous \
            --write-xfer-little \
            --exec-on-reception "PleaseLookAtThis '#a' '#c' '#r' '#p' '#f'" \
            --sort-on-study-uid scp \
            --output-directory "$od" \
            $port &>${SERVERDIR}/logs/storescpd${projname}.log &
        pid=$!
        echo $pid > $pidfile
        ;;
    'stop')
        #/usr/bin/pkill -F $pidfile
        /usr/bin/pkill -u processing "storescpFIONA .*${port}"
        RETVAL=$?
        # [ $RETVAL -eq 0 ] && rm -f $pidfile
        [ $RETVAL = 0 ] && rm -f ${pidfile}
        ;;
    *)
        echo "usage: storescpd { start | stop }"
        ;;
esac
exit 0
