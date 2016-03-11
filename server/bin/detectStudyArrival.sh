#!/bin/bash

#
# check the study job directory created by receiveSingleFile.sh
# if the file is old enough process it using the information provided
# Add this to 'crontab -e' to check every 15 seconds if a new job arrived. 
# */1 * * * * /data/code/bin/detectStudyArrival.sh
# */1 * * * * sleep 30; /data/code/bin/detectStudyArrival.sh
# */1 * * * * sleep 15; /data/code/bin/detectStudyArrival.sh
# */1 * * * * sleep 45; /data/code/bin/detectStudyArrival.sh


DIR=/data/site/.arrived
if [ ! -d "$DIR" ]; then
  mkdir -p "$DIR"
  chmod 777 "$DIR"
fi

# Where is this script? The parent directory of this script is the server dir.
SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/detectStudyArrival.log

# only done if at least that old (in seconds)
oldtime=15

anonymize () {
  SDIR=$1
  SSERIESDIR=$2
  # This loop is very inefficient, dcmodify called for each file is not good.
  # We should group files together that not exceed the limit of the command line length in bash.
  # Even better we should replace this with some GDCM code.
  find /data/site/raw/${SDIR}/${SSERIESDIR}/ -print0 | while read -d $'\0' file2
  do 
      # find out the real name of this file
      f=`/bin/readlink -f "$file2"`
      # To properly annonymize data we need to follow http://dicom.nema.org/dicom/2013/output/chtml/part15/chapter_E.html
      # We keep here the patient name and patient id - they have to be anonymized seperately, they are required still for site identification
      /usr/bin/dcmodify -ie -nb -ea "(0010,0030)" -ea "(0020,000E)" -ea "(0020,000D)" \
  	-ea "(0008,0080)" -ea "(0008,1040)" -ea "(0008,0081)" -ea "(0008,0050)" \
  	-ea "(0008,0090)" -ea "(0008,1070)" -ea "(0008,1155)" -ea "(0010,0040)" \
  	-ea "(0010,1000)" -ea "(0010,1001)" -ea "(0010,1010)" -ea "(0010,1040)" \
  	-ea "(0020,0010)" -ea "(0020,4000)" "$f"
  done
  # We need to processSingleFile again after the anonymization is done. First delete the previous cached json file
  echo "`date`: anonymize  - delete now /data/site/raw/${SDIR}/${SSERIESDIR}.json" >> $log
  /bin/rm /data/site/raw/${SDIR}/${SSERIESDIR}.json
  # then send files to processSingleFile again
  echo "`date`: anonymize  - recreate /data/site/raw/${SDIR}/${SSERIESDIR}.json" >> $log
  find -L /data/site/raw/${SDIR}/${SSERIESDIR}/ -type f -print | xargs -i echo "{}" >> /tmp/.processSingleFilePipe
}

detect () {
  # every file in this directory is a potential job, but we need to find some that are old enough, there could be more coming
  find "$DIR" -print0 | while read -d $'\0' file
  do
    if [ "$file" == "$DIR" ]; then
       continue
    fi
    if [ "$(( $(date +"%s") - $(stat -c "%Y" "$file") ))" -lt "$oldtime" ]; then
        continue
    fi

    echo "`date`: Detected an old enough job \"$file\"" >> $log
    fileName=$(basename "$file")
    AETitleCaller=`echo "$fileName" | cut -d' ' -f1`
    AETitleCalled=`echo "$fileName" | cut -d' ' -f2`
    CallerIP=`echo "$fileName" | cut -d' ' -f3`
    SDIR=`echo "$fileName" | cut -d' ' -f4`
    SERIESDIR=`echo "$fileName" | cut -d' ' -f5`
    if (($? == 0)); then
      # remove the first 4 characters 'scp_' to get the series instance uid
      if [[ ${SERIESDIR} == scp_* ]]; then
        SSERIESDIR=${SERIESDIR:4}
      else
        SSERIESDIR=${SERIESDIR}
      fi
      # before we can do anything we need to anonymize this series (real file location, no symbolic links)
      anonimize=1
      if [[ -f /data/enabled ]]; then
        anonimize=`echo /data/enabled | head -c 3 | tail -c 1`
      fi
      if [[ $anonimize == "1" ]]; then
        echo "`date`: anonymize files linked to by /data/site/raw/${SDIR}/${SSERIESDIR}" >> $log
 	anonymize ${SDIR} ${SSERIESDIR}
        echo "`date`: anonymization is done" >> $log
      fi
      echo "`date`: series detected: \"$AETitleCaller\" \"$AETitleCalled\" $CallerIP /data/site/raw/$SDIR series: $SSERIESDIR" >> $log
    else
      echo "`date`: Study detected: \"$AETitleCaller\" \"$AETitleCalled\" $CallerIP /data/site/raw/$SDIR" >> $log
    fi
    #
    # /usr/bin/nohup /data/streams/bucket01/process.sh \"$AETitleCaller\" \"$AETitleCalled\" $CallerIP "/data/site/archive/$SDIR" &
    #
    echo "`date`: delete \"$file\"" >> $log
    /bin/rm -f -- "$file"
  done
}

# The following section takes care of not starting this script more than once 
# in a row. If for example it takes too long to run a single iteration this 
# will ensure that no second call to scrub is executed prematurely.
(
  flock -n 9 || exit 1
  # command executed under lock
  detect
) 9>${SERVERDIR}/.pids/detectStudyArrival.lock
