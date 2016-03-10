#!/bin/bash
# filename: ppsscpfs
#
# purpose: start DICOM multiple performed procedure steps server
#          Keeps track of scans on the server
#
#  */1 * * * * /var/www/html/server/bin/mppsctl.sh start
# (Hauke Bartsch)

od=/data/scanner
export DCMDICTPATH=/usr/share/dcmtk/dicom.dic

port=`cat /data/config/config.json | jq -r ".MPPSPORT"`
SERVERDIR=`dirname "$(readlink -f "$0")"`/../

pidfile=${SERVERDIR}/.pids/mpps.pid
#
# 
#
case $1 in
    'start')
        if [[ -f /data/enabled ]] && [[ -r /data/enabled ]]; then
           v=`cat /data/enabled | head -c 2 | tail -c 1`
           if [[ "$v" == "0" ]]; then
              echo "`date`: service disabled using /data/enabled control file" >> ${SERVERDIR}/logs/ppsscpfs.log
              echo "service disabled using /data/enabled control file"
              exit
           fi
        fi

        if [ ! -d "$od" ]; then
          mkdir $od
        fi
        echo "Check if ppsscpfs daemon is running..."
        /usr/bin/pgrep -f -u processing "ppsscpfs_e "
        RETVAL=$?
        [ $RETVAL = 0 ] && exit || echo "ppsscpfs process not running, start now.."
        echo "Starting multipe performed procedure step daemon... (see /var/www/html/server/logs/ppsscpfs.log)"
        /usr/bin/nohup /usr/bin/ppsscpfs_e --output-directory "$od" -ll info \
	    -L /usr/share/dcmtk/dcmpps.lic \
            $port &>${SERVERDIR}/logs/ppsscpfs.log &
        pid=$!
        echo $pid > $pidfile
        ;;
    'stop')
        #/usr/bin/pkill -F $pidfile
        /usr/bin/pkill -u processing ppsscpfs_e
        RETVAL=$?
        # [ $RETVAL -eq 0 ] && rm -f $pidfile
        [ $RETVAL = 0 ] && rm -f ${pidfile}
        ;;
    *)
        echo "usage: storescpd { start | stop }"
        ;;
esac
exit 0
