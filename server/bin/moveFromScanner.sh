#!/bin/bash

#
# Called from cron-job, checks the study data in /data/active-scans for things to pull from the scanner.
# If all images are already found (only checks number of images) the series will not be pulled again.
# If all images of all series are present on the system the /data/active-scans/<StudyInstanceUID> file
# is copied to the /data/finished-scans/ folder.
#
# This script will only run if there is no "/var/www/html/server/.pids/moveFromScanner.lock". This prevents
# multiple executions of this script in cases that a single processing steps takes more than 15 seconds.
#
# This script will only run if there is no "0" in the second /data/enabled bin (like in "101").
#
# This script depends on the following dcmtk tools: dcm2dump, findscu, movescu.
#
# Install using a cron-job every 15 seconds
#  */1 * * * * /var/www/html/server/bin/moveFromScanner.sh
#  */1 * * * * sleep 30; /var/www/html/server/bin/moveFromScanner.sh
#  */1 * * * * sleep 15; /var/www/html/server/bin/moveFromScanner.sh
#  */1 * * * * sleep 45; /var/www/html/server/bin/moveFromScanner.sh

export DCMDICTPATH=/usr/share/dcmtk/dicom.dic

SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/moveFromScanner.log

# logger lock file is in ${SERVERDIR}/.pids/moveFromScanner.lock
# echo "`date`: lock file is at: ${SERVERDIR}/.pids/moveFromScanner.lock" >> $log

# Scanner we will ask for our images
SCANNERIP=`cat /data/config/config.json | jq -r ".SCANNERIP"`
SCANNERPORT=`cat /data/config/config.json | jq -r ".SCANNERPORT"`
SCANNERAETITLE=`cat /data/config/config.json | jq -r ".SCANNERAETITLE"`
# The aetitle of this fiona system (known to the scanner)
DICOMAETITLE=`cat /data/config/config.json | jq -r ".DICOMAETITLE"`


enabled=0
if [[ -f /data/enabled ]]; then
  # this will only work if there is no file named '2' in the /data directory
  enabled=`cat /data/enabled | head -c 2| tail -c 1`
fi

getSeries () {
  studyInstanceUID=$1
  seriesInstanceUID=$2

  echo "`date`: pull series $studyInstanceUID $seriesInstanceUID from $SCANNERIP : $SCANNERPORT" >> $log
  f=`mktemp`
  printf "# request all images for the seriesinstanceuid\n#\n(0008,0052) CS [SERIES]     # QueryRetrieveLevel\n(0020,000e) UI [${seriesInstanceUID}]    # SeriesInstanceUID\n" >> $f
  /usr/bin/dump2dcm +te $f ${f}.dcm
  cmd="/usr/bin/movescu -aet $DICOMAETITLE -aec $SCANNERAETITLE --study -aem $DICOMAETITLE $SCANNERIP $SCANNERPORT ${f}.dcm"
  echo "`date`: pull this series -> `cat $f`" >> $log
  echo "`date`: $cmd" >> $log
  eval $cmd
  # be nice and wait before asking for more trouble
  sleep 5

  # delete the temporary files again
  /bin/rm -f -- ${f} ${f}.dcm
}

getScans () {

  # go through the list of studies
  find /data/active-scans/ -type f -print0 | while read -d $'\0' file
  do
    echo "`date`: PROCESS: $file" >> $log
    numSeries=0
    numFinishedSeries=0
    studyInstanceUID=`basename "$file"`
    # get the list of series from the scanner for this study
    echo "`date`: call findscu with \"findscu -aet ${DICOMAETITLE} -aec ${SCANNERAETITLE} --study -k 0008,0052=SERIES -k \"0020,000d=${studyInstanceUID}\" ${SCANNERIP} ${SCANNERPORT}\" to get series for this study" >> $log
    series=`findscu -aet ${DICOMAETITLE} -aec ${SCANNERAETITLE} --study -k 0008,0052=SERIES -k "0020,000d=${studyInstanceUID}" ${SCANNERIP} ${SCANNERPORT} 2>&1`
    while read line; do
       # this failed because there was a file named '2' in the current directory (bummer)
       val=`echo $line | cut -d'[' -f2 | cut -d']' -f1`
       if [[ $val == $line ]]; then
          continue
       fi
       # remove any whitespaces from the value (we support only single words)
       val=`echo $val | tr -d '[[:space:]]'`
       if [[ $line =~ ^W:\ \(0020,1002\) ]]; then
	 imagesInAcquisition=$val

	 #
         # this is the highest tag, will be printed last, we should have a seriesInstanceUID for processing now
	 #
         numSeries=$((numSeries + 1))

	 # check if study directory exists
         if [[ -d /data/site/raw/${studyInstanceUID} ]]; then
           # check how many images we have already for this series
           numImages=`/bin/ls -1 /data/site/raw/${studyInstanceUID}/${seriesInstanceUID}/* | wc -l`
           if [ "$imagesInAcquisition" == "$numImages" ]; then
             # we are done
             echo "`date`: ${studyInstanceUID}/${seriesInstanceUID}, we do have $numImages of $imagesInAcquisition" >> $log
             # we can clear this series job now, we don't want to process it again
             numFinishedSeries=$((numFinishedSeries + 1))
             continue
           else
             echo "`date`: ${studyInstanceUID}/${seriesInstanceUID}, we do have $numImages images but like to see $imagesInAcquisition" >> $log
             getSeries ${studyInstanceUID} ${seriesInstanceUID}
           fi
         else
	   # no study directory, we need to pull this series
           echo "`date`: no study directory found ${studyInstanceUID}/${seriesInstanceUID}, we expect $imagesInAcquisition images" >> $log
           getSeries ${studyInstanceUID} ${seriesInstanceUID}
         fi
       fi
       if [[ $line =~ ^W:\ \(0020,000e\) ]]; then
         seriesInstanceUID=$val
       fi
    done < <(echo "$series")
    echo "`date`: done with scanning for series in study ${studyInstanceUID}" >> $log

    # if the study is in progress we will not have a PerformedProcedureStepEndTime yet, don't close the study in that case
    # MPPS files are named after their MediaStorageInstanceUID, we need to find the correct one for our current studyInstanceUID
    mppsfile=`grep -l ${studyInstanceUID} /data/scanner/*`
    stillInProcess=1
    if [[ -f "$mppsfile" ]]; then
        l=`/usr/bin/dcmdump +P "PerformedProcedureStepEndTime" "${mppsfile}"`
        val=`echo $l | cut -d'[' -f2 | cut -d']' -f1`
        #echo "`date` looking for \"no value available\" in $val" >> $log
        if [[ "$val" == *"no value available"* ]]; then
	    # we don't have an end-time yet
	    echo "`date`: no PerformedProcedureStepEndTime yet, still in progress" >> $log
	    stillInProcess=1
	else
	    echo "`date`: PerformedProcedureStepEndTime found ${val}" >> $log
	    stillInProcess=0
        fi
    else
	echo "`date`: could not find ${studyInstanceUID} MPPS file in /data/scanner/" >> $log
    fi

    # are we done with this study?
    if [[ $stillInProcess == 0 ]] && [[ "$numSeries" == "$numFinishedSeries" ]]; then
	# we are done if we have all images for all series
        echo "`date`: JOB $file to /data/finished-scans" >> $log
	mv "$file" /data/finished-scans/
    else
        echo "`date`: we are not done yet with this job" >> $log
    fi
    # we could also be done if a study was cancelled, we don't have all the images expected but the study is old
    testtime=86400
    if [ "$(( $(date +"%s") - $(stat -c "%Y" "$file") ))" -lt "$testtime" ]; then
       echo "`date`: \"$file\" is too new, keep it around longer" >> $log
    else
       echo "`date`: \"$file\" is old, download seems to have failed" >> $log
       mv "$file" /data/failed-scans/
    fi
    
  done
}

clearScans () {
  # if we are switched off we need to delete scans, if we keep them or?
  echo "`date`: try to clear the scans (todo)" >> $log
}

(
  flock -n 9 || exit 1
  # command executed under lock only if we enable MPPS functionality
  if [[ "$enabled" == "1" ]]; then
    echo "`date`: move enabled, start getScans" >> $log
    getScans
  else
    echo "`date`: move disabled, start clearScans" >> $log
    clearScans
  fi 
) 9>${SERVERDIR}/.pids/moveFromScanner.lock
