#!/bin/bash
#
# Siemens k-space data from an MRI scanner is stored as .dat files on a drive shared on the network. 
# This script tries to map the dates/times of these .dat files against the DICOM files available 
# on the system (/data/site/raw).
#
# Fast compression is enabled if you have pigz installed.
#
# This script should be run by user root (creates mount point).
#
# */30 * * * * /var/www/html/server/bin/importSiemensKSPACE.sh >> /var/www/html/server/logs/importSiemensKSPACE.log 2>&1
#
# If more than one scanner is specified in the /data/config/config.json script all scanners will be checked.
#   # jq -r ".OTHERSCANNER[].SCANNERIP" /data/config/config.json
#

# A .dat file needs to be at least that many seconds old (modification time) before it will be copied
oldtime=15

#
# check the user account, this script should run as root
#
if [[ $USER !=  "root" ]]; then
   echo "This script must be run by the root user"
   exit 1
fi

#
# Make sure this script runs only once (each .dat file is about 30G).
# We will wait until one run is done before we attempt the next.
#
thisscript=`basename "$0"`
for pid in $(/usr/sbin/pidof -x "$thisscript"); do
    if [ $pid != $$ ]; then
        echo "[$(date)] : ${thisscript} : Process is already running with PID $pid"
        exit 1
    fi
done

# The scanner host exports the usb3 drive with the data 
SCANNERIP=`cat /data/config/config.json | jq -r ".SCANNERIP"`
OTHERSCANNERIPS=`cat /data/config/config.json | jq -r ".OTHERSCANNER[].SCANNERIP"`

setupMounts () {
    # by default we will use the no IP
    scannerid=""
    if [ ! -z "$1" ]; then
	scannerid="$1"
    fi

    #
    # make sure we have the correct mount to the usb drive that holds the data
    #
    if grep -qs '/mnt/host_usb3${scannerid}' /proc/mounts; then
	echo "Ok, mount point /mnt/host_usb3${scannerid} exists."
    else
	echo "The mount point /mnt/host_usb3${scannerid} does not exist. Trying to create the mount now..."
	# check if we have the cifs credentials file
	if [ ! -f /data/config/cifs-credentials_unix${scannerid}.txt ]; then
	    echo "A credentials file (/data/config/cifs-credentials_unix${scannerid}.txt) is required to mount the scanner hosts usb drive."
            echo "You can find the file on your system's reconstruction machine (MARS) in the /root/ directory. Place it in"
            echo "   /data/config/cifs-credentials_unix${scannerid}.txt"
            return
	fi
	# set the permissions of this file to read by root only
	chmod 0600 /data/config/cifs-credentials_unix${scannerid}.txt
	
	# make sure mount directory exists on FIONA
	dirloc="/mnt/host_usb3${scannerid}"
	if [ ! -d "$dirloc" ]; then
	    mkdir -p -m 0777 "$dirloc"
	fi
	
	# try to create the mount point
        if [ ! -z "$1" ]; then
	    # if we have an 'other' IP we need to mark the directories
  	    /usr/bin/mount -t cifs //${scannerid}/ABCD_streaming_USB3 /mnt/host_usb3${scannerid} -o credentials=/data/config/cifs-credentials_unix${scannerid}.txt
	else
	    # the default mounts don't have the IP in the name
	    /usr/bin/mount -t cifs //${SCANNERIP}/ABCD_streaming_USB3 /mnt/host_usb3 -o credentials=/data/config/cifs-credentials_unix.txt
	fi

	# test if the mount point could be created
	if grep -qs '/mnt/host_usb3${scannerid}' /proc/mounts; then
	    echo "Ok, mount point could be created."
	else
            echo "Error: mount point could not be found after calling mount. Quit now."
	    return
	fi
    fi

    #
    # create the shares on FIONA (if they don't exist yet)
    #
    a=/data/site/scanner-share/ABCDstream/dicom_stream
    b=/data/site/scanner-share/ABCDstream/kspace_stream
    c=/data/site/scanner-share/ABCDstream/yarra_export
    if [ ! -d "$a" ]; then
	mkdir -p "$a"
	chmod 777 "$a"
    fi
    if [ ! -d "$b" ]; then
	mkdir -p "$b"
	chmod 777 "$b"
    fi
    if [ ! -d "$c" ]; then
	mkdir -p "$c"
	chmod 777 "$c"
    fi
}    

# create mounts for the default scanner with IP in SCANNERIP
setupMounts && importDatLocations="/mnt/host_usb3"


# do other scanners setup mounts, identify the scanner by IP
for u in $OTHERSCANNERIPS; do
  if [ ! -z "$u" ]; then
    setupMounts $u && importDatLocations="${importDatLocations} /mnt/host_usb3${u}"
  fi
done

#
# now go through all the files on the external drives
#
find ${importDatLocations} -type f -name *.dat -print0 | while read -d $'\0' file
do
  # only look at files that are at least oldtime seconds old
  if [ "$(( $(date +"%s") - $(stat -c "%Y" "$file") ))" -lt "$oldtime" ]; then
        echo "`date`: too young $file"
        continue
  fi

  # pattern is : ABCD_kspace_MID00185_20160830_015337.dat
  bn=`basename "$file"`
  ty=`echo $bn | rev | cut -d'_' -f4- | rev`
  me=`echo $bn | rev | cut -d'_' -f3 | rev`
  da=`echo $bn | rev | cut -d'_' -f2 | rev`
  ti=`echo $bn | rev | cut -d'_' -f1 | rev | cut -d'.' -f1 | sed 's/^[0]*//'`

  echo "Found: $file of type $ty with meas $me done on day $da time $ti"

  # try to find an image series that was done at the same day/time
  # jq "[.StudyDate,.StudyTime,.SeriesInstanceUID,input_filename]" /data/site/raw/*/*.json
  # jq '{ "SeriesDescription": .SeriesDescription, "StudyDate": .StudyDate,"StudyTime": .StudyTime,"SeriesTime": .SeriesTime, "SeriesInstanceUID": .SeriesInstanceUID,"filename": input_filename} | select(.StudyDate == "20160825")' /data/site/raw/*/*.json

  # we can check now for the MID number
  meNum=$(echo "$me" | sed -e 's/^MID[0]*//')
  hdrs=$(find /data/site/scanner-share/ABCDstream/yarra_export/measfiles/ -name "*\#M${meNum}\#*.hdr" -printf '%TY%Tm%Td "%p"\n' | grep "$da" | head -1 | cut -d' ' -f2- | sed 's/^"\(.*\)"$/\1/')

  if [ -z "${hdrs}" ]; then
     echo "`date`: Error could not find a hdr file for *\#M${meNum}\#*.hdr, \"$file\", we will not package this dat file."
     continue;
  fi

  # search all json files for one that matches this ScanDate
  find /data/site/raw -type f -iname '*.json' -print0 | while read -d $'\0' json
  do

      # Our rule is to match a k-space scan from usb3 with the SeriesInstanceUID that 
      #    is on the same day
      #    has a SeriesTime larger than ti
      #    has a fabs(SeriesTime - ti) < 2min
      sd=($(/usr/bin/jq '{ "StudyDate": .StudyDate } | select(.StudyDate == "'"${da}"'")' $json))
      if [ -z "$sd" ]; then
          #echo "Error: not the right StudyDate found in $json"
	  continue;
      fi
      # same day item found
      echo "Info: same day (${da}) StudyDate found in $json"
      
      # the meNum is shared between the .dat and the hdr files (if they come from the same day)
      
      st=($(/usr/bin/jq '.SeriesTime' $json | cut -d'.' -f1 | tr -d '"' | sed -e 's/^[0]*//'))
      if [ $st -le $ti ]; then
	  continue;
      fi
      # after time item found
      echo "Info: SeriesTime (${st}) is after $json"


      # this meNum is part of the yarra_export filename, collect the hdr files for this entry
      # but we get too many entries here, need to filter by date at least
      # we will get copies of the hdr files from ABCDfMRIadj and ABCDfMRIhdr, copy both right now to make this work
      # ls -lahrt /data/site/scanner-share/ABCDstream/yarra_export/measfiles/ABCD*/*\#M${meNum}\#*.hdr

      # get the UUID that appears in the DICOM
      UUID=""
      if [ ! -z "${hdrs}" ]; then
         UUID=$(/bin/strings $hdrs | grep sWipMemBlock.tFree | awk '{ print $3; }' | sed 's/^"\(.*\)"$/\1/')
      else
	  echo "`date`: Error could not find a hdr file for \"$file\", we will not package this dat file."
	  continue;
      fi
  
      #echo "header: \"$UUID ${hdrs}\""
      #
      # Assumption: some json files will have the same UUID in their jsons.
      # Those DICOM series and the k-space data belong together. We don't need to check
      # for the time in these cases.
      #
      seriesUUID=($(/usr/bin/jq '.siemensUUID' $json | cut -d'.' -f1 | tr -d '"'))

      # we can have more than one UUID in the UUID variable returned from the header, search for any match
      if [ ! -z "$UUID" ] && [ ! -z "$seriesUUID" ] && [ "$seriesUUID" == *"${UUID}"* ]; then
	  # found a json series that belongs to this dat and hdr file combination
	  echo "FOUND two UUID's that belong together $UUID"
      else
	  if [ $(( $st - $ti )) -gt 200 ]; then
	      echo "INFO: time between $st and $ti not sufficiently close ("$(( $st - $ti ))", <200), cancel"
	      continue;
	  fi
	  echo "Info: time between $st and $ti is sufficiently close together"
      fi

      # get the StudyInstanceUID and the SeriesInstanceUID
      PatientName=($(/usr/bin/jq '.PatientName' $json | tr -d '"'))
      StudyInstanceUID=($(/usr/bin/jq '.StudyInstanceUID' $json | tr -d '"'))
      SeriesInstanceUID=($(/usr/bin/jq '.SeriesInstanceUID' $json | tr -d '"'))
      SeriesNumber=($(/usr/bin/jq '.SeriesNumber' $json | tr -d '"'))

      fn1="/data/quarantine/SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.tgz"
      js1="/data/quarantine/SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.json"
      md1="/data/quarantine/SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.md5sum"
      fn2=$(ls "/data/outbox/*SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.tgz" 2> /dev/null)
      fn3=$(ls "/data/DAIC/*SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.tgz" 2> /dev/null)

      # does this file exists already, don't do anything
      if [ -e "$fn1" ] || [ ! -z "$fn2" ] || [ ! -z "$fn3" ]; then
	  echo "  Info: this file (\"$fn1\"|\"$fn2\"|\"$fn3\") already exists, skip"
	  continue; 
      fi

      echo "$json was scanned on the same day and just after $ti ($st)"

      # now package the dat file with a single DICOM as a k-space package
      dicom=$(ls "${json%.*}" | head -1)
      # resolve the symbolic link from raw into the real filename in archive
      dicom=$(/bin/readlink -f "${json%.*}/$dicom")
      if [ "$dicom" == "" ]; then
	  echo "Error: no DICOM file found for this series"
      fi
      echo "`date`: create now ${fn1} and ${js1} with $dicom"
      
      # we need to make sure again that all files are present - we don't want to create a tgz with missing files
      if [ ! -e "$file" ] || [ ! -e "${dicom}" ] || [ ! -e "${hdrs}" ]; then
	  echo "`date`: Error tried to package together \"$file\", \"$dicom\" and \"$hdrs\" but one of them could not be found, we will not create ${fn1}"
	  continue;
      fi

      echo "Create TGZ now: ${fn1} <- ${dicom} ${file} ${json} ${hdrs}"

      # setting the compression level to -1 should speed up the packaging
      if hash pigz 2>/dev/null; then
          tar cf - "${dicom}" "$file" "$json" ${hdrs} | pigz --fast -p 6 > "${fn1}"
	  packval=$?
      else
	  GZIP=-1 tar cvzf "${fn1}" "${dicom}" "$file" "$json" ${hdrs}
	  packval=$?
      fi
      if [ $packval -ne 0 ]; then
	  # something went wrong, remove this output, if it exists
	  echo "   ERRROR: could not create tar file ${fn1}"
	  if [ -e "${fn1}" ]; then
	      /bin/rm -f "${fn1}"
	  fi
	  # try again next time
	  continue
      fi

      # create a json that goes together with it
      echo "{ \"PatientName\": \"$PatientName\", \"SeriesInstanceUID\": \"${SeriesInstanceUID}\", \"StudyInstanceUID\": \"${StudyInstanceUID}\", \"dat\": \"$file\" }" > "${js1}"

      # and an md5sum file
      /usr/bin/md5sum -b "${fn1}" > "${md1}"
      
      # set permissions for processing user
      chown processing:processing "${fn1}" "${js1}" "${md1}"

      echo "`date`: packaging done"

      # If we are done with packaging and we have all data in there we should remove the files from the external usb
      # and from the hdr location, but not the DICOM or the json.
      echo "  REMOVE:  /bin/rm -f $file ${hdrs}"
      /bin/rm -f "${file}" "${hdrs}"
  done

done
