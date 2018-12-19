#!/bin/bash

#
# check the study job directory created by receiveSingleFile.sh
# if the file is old enough process it using the information provided
# Add this to 'crontab -e' to check every 15 seconds if a new job arrived. 
# */1 * * * * /data/code/bin/detectStudyArrival.sh
# */1 * * * * sleep 30; /data/code/bin/detectStudyArrival.sh
# */1 * * * * sleep 15; /data/code/bin/detectStudyArrival.sh
# */1 * * * * sleep 45; /data/code/bin/detectStudyArrival.sh
# */1 * * * * /data/code/bin/detectStudyArrival.sh PCGC
# */1 * * * * sleep 30; /data/code/bin/detectStudyArrival.sh PCGC
# */1 * * * * sleep 15; /data/code/bin/detectStudyArrival.sh PCGC
# */1 * * * * sleep 45; /data/code/bin/detectStudyArrival.sh PCGC

SERVERDIR=`dirname "$(readlink -f "$0")"`/../

# Default directories for ABCD project
datadir=`cat /data/config/config.json | jq -r ".DATADIR"`
pfiledir=`cat /data/config/config.json | jq -r ".PFILEDIR"`
DIR=/data/site/.arrived
log=${SERVERDIR}/logs/detectStudyArrival.log

projname="$1"
if [ -z "$projname" ]; then
    projname="ABCD"
else
    if [ "$projname" != "ABCD" ]; then
	# Override directories if non-ABCD project
	datadir=`cat /data/config/config.json | jq -r ".SITES.${projname}.DATADIR"`
	pfiledir=`cat /data/config/config.json | jq -r ".SITES.${projname}.PFILEDIR"`
	DIR=/data${projname}/site/.arrived
	log=${SERVERDIR}/logs/detectStudyArrival${projname}.log
    fi
fi

if [ ! -d "$DIR" ]; then
  mkdir -p "$DIR"
  chmod 777 "$DIR"
fi

# only done if at least that old (in seconds)
oldtime=15

anonymize () {
  SDIR=$1
  SSERIESDIR=$2
  projname=$3
  if [ -z "$projname" ]; then
      projname = ""
  else
      if [ "$projname" == "ABCD" ]; then
	  projname = ""
      fi
  fi
  # This loop is very inefficient, dcmodify called for each file is not good.
  # We should group files together that not exceed the limit of the command line length in bash.
  # Even better we should replace this with some GDCM code.
  #find /data/site/raw/${SDIR}/${SSERIESDIR}/ -print0 | while read -d $'\0' file2
  #do 
  #    # find out the real name of this file
  #    f=`/bin/readlink -f "$file2"`
  #    # To properly annonymize data we need to follow http://dicom.nema.org/dicom/2013/output/chtml/part15/chapter_E.html
  #    # We keep here the patient name and patient id - they have to be anonymized seperately, they are required still for site identification
  #    /usr/bin/dcmodify -ie -nb -ea "(0010,0030)" -ea "(0020,000E)" -ea "(0020,000D)" \
  # 	-ea "(0008,0080)" -ea "(0008,1040)" -ea "(0008,0081)" -ea "(0008,0050)" \
  # 	-ea "(0008,0090)" -ea "(0008,1070)" -ea "(0008,1155)" -ea "(0010,0040)" \
  # 	-ea "(0010,1000)" -ea "(0010,1001)" -ea "(0010,1010)" -ea "(0010,1040)" \
  #	-ea "(0020,0010)" -ea "(0020,4000)" "$f"
  #done
  # run python version of anonymizer
  echo "We will run now: ${SERVERDIR}/bin/anonymize.sh ${datadir}/site/raw/${SDIR}/${SSERIESDIR}" >> $log
  ${SERVERDIR}/bin/anonymize.sh ${datadir}/site/raw/${SDIR}/${SSERIESDIR} 2>&1 >> $log

  # we need to cache the connection information for this file, lets get the values first
  AETitleCalled=`cat ${datadir}/site/raw/${SDIR}/${SSERIESDIR}.json | jq [.IncomingConnection.AETitleCalled] | jq .[]`
  AETitleCaller=`cat ${datadir}/site/raw/${SDIR}/${SSERIESDIR}.json | jq [.IncomingConnection.AETitleCaller] | jq .[]`
  CallerIP=`cat ${datadir}/site/raw/${SDIR}/${SSERIESDIR}.json | jq [.IncomingConnection.CallerIP] | jq .[] | tr -d '"' | tr -d '\\'`

  # We need to processSingleFile again after the anonymization is done. First delete the previous cached json file
  echo "`date`: anonymize  - delete now ${datadir}/site/raw/${SDIR}/${SSERIESDIR}.json" >> $log
  /bin/rm ${datadir}/site/raw/${SDIR}/${SSERIESDIR}.json
  # then send files to processSingleFile again
  echo "`date`: anonymize  - recreate ${datadir}/site/raw/${SDIR}/${SSERIESDIR}.json" >> $log
  cd ${datadir}/site/raw/${SDIR}/${SSERIESDIR}
  echo "`date`: run find -L . -type f -print | xargs -i echo \"${AETitleCalled},${AETitleCaller},${CallerIP},${datadir}/site/raw/${SDIR}/${SSERIESDIR},{}\" in ${datadir}/site/raw/${SDIR}/${SSERIESDIR}" >> $log
  find -L . -type f -print | xargs -i echo "${AETitleCalled},${AETitleCaller},${CallerIP},${datadir}/site/raw/${SDIR}/${SSERIESDIR},{}" >> /tmp/.processSingleFilePipe${projname}
}

runSeriesInventions () {
  # "$AETitleCaller" "$AETitleCalled" $CallerIP /data/site/raw/$SDIR $SSERIESDIR
  AETitleCaller=$1
  AETitleCalled=$2
  CallerIP=$3
  SDIR=$4
  SSERIESDIR=${5%/}

  echo "`date`: series inventions only required for phantom scan" >> $log
  # test for phantom scan
  erg=`cat ${datadir}/site/raw/${SDIR}/${SSERIESDIR}.json | jq ".ClassifyType"[] | grep -i ABCD-Phantom`
  if [[ ! "$erg" == "" ]]; then
      # run the phantom QC on this series, create an output directory first
      d=${SDIR}/${SSERIESDIR}_`date | tr ' ' '_'`
      # lets move the docker's info file as documentation in there
      dproc=ABCDPhantomQC
      # 
      # check first if the docker container actually exist
      #
      docker images | grep ${dproc}
      if [ $? == "1" ]; then
        mkdir -p ${d}
        $(docker run --rm ${dproc} /bin/bash -c "cat /root/info.json") | jq "." > ${d}.json
        erg=$(docker run --rm -d -v ${d}:/output -v ${datadir}/site/archive/${SDIR}:${datadir}/site/archive/${SDIR} -v ${datadir}/site/raw/${SDIR}/${SSERIESDIR}:/input ${dproc} /bin/bash -c "/root/work.sh /input /output /quarantine" 2>&1)
        echo "`date`: docker run finished for $dproc with \"$erg\"" >> $log
      fi
  fi
}

runStudyInventions () {
  # "$AETitleCaller" "$AETitleCalled" $CallerIP /data/site/raw/$SDIR
  AETitleCaller=$1
  AETitleCalled=$2
  CallerIP=$3
  SDIR=$4

  echo "`date`: study inventions implements series tests for ABCD complicance" >> $log
  # run the series compliance QC on this series, create an output directory first
  d=${datadir}/site/output/${SDIR}/series_compliance_`date | tr ' ' '_' | tr ':' '_'`
  mkdir -p ${d}
  # lets move the docker's info file as documentation in there
  dproc=compliance_check
  $(docker run --rm ${dproc} /bin/bash -c "cat /root/info.json") | jq "." > ${d}.json
  echo "`date`: ${SDIR} -> docker run -d -v ${d}:/output -v ${datadir}/site/archive/${SDIR}:${datadir}/site/archive/${SDIR} -v ${SDIR}:/input ${dproc} /bin/bash -c \"/root/work.sh /input /output\"" >> $log
  erg=$(docker run --rm -d -v ${datadir}/quarantine:/quarantine:ro -v ${d}:/output -v ${datadir}/site/archive/${SDIR}:${datadir}/site/archive/${SDIR} -v ${datadir}/site/raw/${SSDIR}:/input:ro ${dproc} /bin/bash -c "/root/work.sh /input /output /quarantine" 2>&1)
  echo "`date`: docker run finished for $dproc with \"$erg\"" >> $log
}

runAtInterval () {
  interval=$1
  machineid=$2
  SDIR=$3

  # find out if we are running already
  j=`ps aux | egrep "watch.*${SDIR}" | grep -v grep`
  echo "`date`: is it already running? \"${j}\"" >> $log
  if [[ ! "${j}" == "" ]]; then
     return 0
  fi
  # if we are not running already start a job now
  echo "`date`: start the job now" >> $log
  d=${datadir}/site/output/${SDIR}/series_compliance
  mkdir -p ${d}
  echo "/usr/bin/nohup watch -n $interval docker run --rm -d -v ${d}:/output -v ${datadir}/site/archive/${SDIR}:${datadir}/site/archive/${SDIR} -v ${SDIR}:/input ${machineid} /bin/bash -c \"/root/work.sh /input /output\" 2>&1 >> $log &" >> $log
  /usr/bin/nohup watch -n $interval /usr/bin/bash -c "docker run --rm -d -v ${d}:/output -v ${datadir}/site/archive/${SDIR}:${datadir}/site/archive/${SDIR} -v ${SDIR}:/input ${machineid} /bin/bash -c \"/root/work.sh /input /output\" 2>&1 >> /tmp/watch.log" &
}

detect () {
  # we can have jobs that we need to run at regular intervals - like study compliance
  # first get a list of the current studies
  echo "`date`: read $DIR for new files" >> $log
  find "$DIR" -print0 | while read -d $'\0' file
  do
    if [ "$file" == "$DIR" ]; then
       continue
    fi
    fileName=$(basename "$file")
    SDIR=`echo "$fileName" | cut -d' ' -f4`
    SERIESDIR=`echo "$fileName" | cut -d' ' -f5`
    if [[ "${SERIESDIR}" == "" ]]; then
      # we have a study instance uid in SDIR, start the study compliance check
      d=${datadir}/site/output/${SDIR}/series_compliance
      mkdir -p ${d}
      machineid=compliance_check
      SSDIR=${SDIR:4}
      echo "`date`: protocol compliance check (/usr/bin/nohup docker run --rm -d -v ${datadir}/quarantine:/quarantine:ro -v ${d}:/output -v ${datadir}/site/archive/${SDIR}:${datadir}/site/archive/${SDIR}:ro -v ${datadir}/site/raw/${SSDIR}:/input:ro ${machineid} /bin/bash -c \"/root/work.sh /input /output /quarantine\" 2>&1 >> $log &)" >> $log
      id=$(docker run --rm -v ${datadir}/quarantine:/quarantine:ro -v ${d}:/output -v ${datadir}/site/archive/${SDIR}:${datadir}/site/archive/${SDIR}:ro -v ${datadir}/site/raw/${SSDIR}:/input:ro ${machineid} /bin/bash -c "/root/work.sh /input /output /quarantine" 2>&1 >> /tmp/watch.log)
      echo "`date`: compliance check finished for ${SDIR} with \"$id\"" >> $log

      # lets do some cleanup and remove any unused docker containers (not needed if we run with --rm)
      docker ps -aq --filter status=exited | xargs --no-run-if-empty docker rm
      # $(docker rm -v $(docker ps -a -q -f status=exited))
      echo "`date`: cleanup of unused containers done..." >> $log

      # we could run something at specific intervals, by compliance check should run every time
      # runAtInterval 10 machine57080de9bbc3d ${SDIR}
    fi
  done

  # every file in this directory is a potential job, but we need to find some that are old enough, there could be more coming
  echo "`date`: DIR to check for files is \"${DIR}\"" >> $log
  #find "$DIR" -print0 | while read -d $'\0' file
  #do
  #    echo " -> \"$file\"    \n" >> $log
  #done
  #find "$DIR" -print0 | while read -d $'\0' file
  find "$DIR" -print0 | while IFS= read -r -d '' file
  do
    valid=1
    if [ "$file" == "$DIR" ]; then
        #echo "`date`: same dir $file == $DIR" >> $log
        valid=0
    fi
    #echo " RUN2 \"$file\"  stat: \"`stat -c "%Y" "$file"`\"  \n" >> $log
    sft=`/usr/bin/stat -c "%Y" "${file}"`
    echo "file is\"${file}\" and stat returns: \"$sftp\""
    if [ "$(( $(date +"%s") - $sft ))" -lt "$oldtime" ]; then
        echo "`date`: too young $file" >> $log
        valid=0
    fi

    if [[ "$valid" == "1" ]]; then
      echo "`date`: Detected an old enough job \"$file\"" >> $log
      fileName=$(basename "$file")
      AETitleCaller=`echo "$fileName" | cut -d' ' -f1`
      AETitleCalled=`echo "$fileName" | cut -d' ' -f2`
      CallerIP=`echo "$fileName" | cut -d' ' -f3`
      SDIR=`echo "$fileName" | cut -d' ' -f4`
      SERIESDIR=`echo "$fileName" | cut -d' ' -f5`
      if [[ ! "${SERIESDIR}" == "" ]]; then
        # remove the first 4 characters 'scp_' to get the series instance uid
        if [[ ${SERIESDIR} == scp_* ]]; then
          SSERIESDIR=${SERIESDIR:4}
        else
          SSERIESDIR=${SERIESDIR}
        fi
        # before we can do anything we need to anonymize this series (real file location, no symbolic links)
        anonymize=1
        if [[ -f ${datadir}/config/enabled ]]; then
          anonymize=`cat ${datadir}/config/enabled | head -c 3 | tail -c 1`
        fi
        # This functionality has been replaced, instead of anonymizing on receiving a study we now anonymize if we send the study out
        # This way we can anonymize both the patient ID and the patient name as well. 
        #if [[ "$anonymize" == "1" ]]; then
          #echo "`date`: anonymize files linked to by ${datadir}/site/raw/${SDIR}/${SSERIESDIR}" >> $log  
   	  #anonymize ${SDIR} ${SSERIESDIR} ${projname}
          #echo "`date`: anonymization is done" >> $log
        #fi
        echo "`date`: series detected: \"$AETitleCaller\" \"$AETitleCalled\" $CallerIP ${datadir}/site/raw/$SDIR series: $SSERIESDIR" >> $log
        runSeriesInventions "$AETitleCaller" "$AETitleCalled" $CallerIP $SDIR $SSERIESDIR

	# lets update the series JSON to include the correct number of images found in NumFiles
	if [ -d "${SDIR}/${SSERIESDIR}/" ]; then
	    # don't list directories and all strange filenames should be on a single line
	    NumFiles=`ls -1ql "${SDIR}/${SSERIESDIR}/" | tail -n+2 | wc -l`
	    echo "`date`: fix NumFiles, set to ${NumFiles}" >> $log
	    tmp=$(mktemp)
	    /usr/bin/jq '.NumFiles = "'${NumFiles}'"' "${SDIR}/${SSERIESDIR}.json" > "$tmp" && mv "$tmp" "${SDIR}/${SSERIESDIR}.json" && chmod gou+rw "${SDIR}/${SSERIESDIR}.json"
	fi

        # we have a series store it as a tar
        mkdir -p ${datadir}/quarantine/
        # allow the site user to write to this directory (from the scanner)
        chmod 777 ${datadir}/quarantine
        echo "`date`: write tar file ${datadir}/quarantine/${SDIR}_${SSERIESDIR}.tgz, created from ${datadir}/site/raw/${SDIR}/${SSERIESDIR}/" >> $log
        out=${datadir}/quarantine/${SDIR}_${SSERIESDIR}.tgz
        cd ${datadir}/site/raw
        # speed up compression if we have pigz installed on this machine
        if hash pigz 2>/dev/null; then
           tar --dereference -cf - "${SDIR}/${SSERIESDIR}/" "${SDIR}/${SSERIESDIR}.json" "${datadir}/site/output/${SDIR}/series_compliance/*.json" | pigz --fast -p 6 > "$out"
        else
           GZIP=-1 tar --dereference -cvzf "$out" "${SDIR}/${SSERIESDIR}/" "${SDIR}/${SSERIESDIR}.json" "${datadir}/site/output/${SDIR}/series_compliance/*.json"
        fi
        md5sum -b "$out" > ${datadir}/quarantine/${SDIR}_${SSERIESDIR}.md5sum
        cp "${SDIR}/${SSERIESDIR}.json" ${datadir}/quarantine/${SDIR}_${SSERIESDIR}.json
        echo "`date`:    test for series compliance file ${datadir}/site/output/${SDIR}/series_compliance/compliance_output.json" >> $log
        if [[ -f "${datadir}/site/output/scp_${SDIR}/series_compliance/compliance_output.json" ]]; then
          cp "${datadir}/site/output/scp_${SDIR}/series_compliance/compliance_output.json" ${datadir}/quarantine/${SDIR}.json
          echo "`date`:    copy compliance_output.json to ${datadir}/quarantine/scp_${SDIR}.json" >> $log
        fi
        echo "`date`: done with creating tar file and md5sum" >> $log
        # now the user interface needs to display this as new data

      else
        echo "`date`: Study detected: \"$AETitleCaller\" \"$AETitleCalled\" $CallerIP ${datadir}/site/raw/$SDIR" >> $log

        runStudyInventions "$AETitleCaller" "$AETitleCalled" $CallerIP $SDIR

        # We have a study we can pack&go for sending it off to the DAIC.
        # We should do this in two stages - first get all the DICOM files into a single tar file (add md5sum).
        # Next store them in a to-be-send-of directory and ask the user on the interface if that is ok.
        # Next send them using sendFiles.sh (looks into /data/<site>) for files. If they are all send over they end up in /data/DAIC/.
      
        # copy the study data to the $pfiledir directory (use tar without compression and resolve symbolic links)
       if [[ -f ${pfiledir}/${SSERIESDIR}.tar ]]; then
           # delete any previous file (we got new series data so file needs to be updated)
           rm -f -- ${pfiledir}/${SSERIESDIR}.*
        fi
      fi
      #
      # /usr/bin/nohup /data/streams/bucket01/process.sh \"$AETitleCaller\" \"$AETitleCalled\" $CallerIP "/data/site/archive/$SDIR" &
      #
      echo "`date`: delete \"$file\"" >> $log
      /bin/rm -f -- "$file"
    else 
      echo "`date`: JOB NOT VALID \"$file\"" >> $log	
    fi
  done
}

# The following section takes care of not starting this script more than once 
# in a row. If for example it takes too long to run a single iteration this 
# will ensure that no second call to scrub is executed prematurely.
(
  flock -n 9 || exit 1
  # command executed under lock
  echo "`date`: run another detectStudyArrival" >> $log
  detect
) 9>${SERVERDIR}/.pids/detectStudyArrival${projname}.lock
