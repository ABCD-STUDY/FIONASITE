#!/bin/bash
# filename: storescpd
#
# purpose: start storescp server for processing user at boot time to receive data
#          Move files to project specific file system
#
# (Hauke Bartsch)

od=/data/site/archive
pipe=/tmp/.processSingleFilePipe

port=`cat /data/config/config.json | jq -r ".SCANNERPORT"`
SERVERDIR=`dirname "$(readlink -f "$0")"`/../
pidfile=${SERVERDIR}/.pids/storescpd.pid
scriptfile=${SERVERDIR}/bin/receiveSingleFile.sh

export DCMDICTPATH=/usr/share/dcmtk/dicom.dic

#
# the received file will be written to a named pipe which is evaluated by processSingleFile.py
#
case $1 in
    'start')
        if [ ! -d "$od" ]; then
          mkdir $od
        fi
        # check if we have a pipe to send events to
        if [[ -p $pipe ]]; then
            echo "Found pipe"
        else
            echo "Error: the pipe of processSingleFile.py could not be found"
            exit -1
        fi
        echo "Check if storescp daemon is running..."
        /usr/bin/pgrep -f -u processing "storescp "
        RETVAL=$?
        [ $RETVAL = 0 ] && exit || echo "storescpd process not running, start now.."
        echo "Starting storescp daemon..."
        /usr/bin/nohup /usr/bin/storescp --fork \
            --write-xfer-little \
            --exec-on-reception "$scriptfile '#a' '#c' '#r' '#p' '#f' &" \
            --sort-on-study-uid scp \
            --output-directory "$od" \
            $port &>${SERVERDIR}/logs/storescpd.log &
        pid=$!
        echo $pid > $pidfile
        ;;
    'stop')
        #/usr/bin/pkill -F $pidfile
        /usr/bin/pkill -u processing storescp
        RETVAL=$?
        # [ $RETVAL -eq 0 ] && rm -f $pidfile
        [ $RETVAL = 0 ] && rm -f ${pidfile}
        ;;
    *)
        echo "usage: storescpd { start | stop }"
        ;;
esac
exit 0
