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
# */1 * * * * /var/www/html/server/bin/importSiemensKSPACE.sh >> /var/www/html/server/logs/importSiemensKSPACE.log 2>&1
#

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

#
# make sure we have the correct mount to the usb drive that holds the data
#
if grep -qs '/mnt/host_usb3' /proc/mounts; then
    echo "Ok, mount point /mnt/host_usb3 exists."
else
    echo "The mount point /mnt/host_usb3 does not exist. Trying to create the mount now..."
    # check if we have the cifs credentials file
    if [ ! -f /data/config/cifs-credentials_unix.txt ]; then
	echo "A credentials file (/data/config/cifs-credentials_unix.txt) is required to mount the scanner hosts usb drive."
        echo "You can find the file on your system's reconstruction machine (MARS) in the /root/ directory. Place it in"
        echo "   /data/config/cifs-credentials_unix.txt"
        exit
    fi
    # set the permissions of this file to read by root only
    chmod 0600 /data/config/cifs-credentials_unix.txt

    # make sure mount directory exists on FIONA
    dirloc=/mnt/host_usb3 
    if [[ ! -d "$dirlock" ]]; then
      mkdir -p -m 0777 "$dirloc"
    fi
    
    # try to create the mount point
    /usr/bin/mount -t cifs //${SCANNERIP}/ABCD_streaming_USB3 /mnt/host_usb3 -o credentials=/data/config/cifs-credentials_unix.txt
    
    # test if the mount point could be created
    if grep -qs '/mnt/host_usb3' /proc/mounts; then
	echo "Ok, mount point could be created."
    else
        echo "Error: mount point could not be found after calling mount. Quit now."
	exit
    fi
fi

#
# now go through all the files on the external drive
#
find /mnt/host_usb3/ -type f -name *.dat -print0 | while read -d $'\0' file
do
  # pattern is : ABCD_kspace_MID00185_20160830_015337.dat
  bn=`basename "$file"`
  ty=`echo $bn | rev | cut -d'_' -f4- | rev`
  me=`echo $bn | rev | cut -d'_' -f3 | rev`
  da=`echo $bn | rev | cut -d'_' -f2 | rev`
  ti=`echo $bn | rev | cut -d'_' -f1 | rev | cut -d'.' -f1`

  echo "found $file of type $ty with meas $me done on day $da time $ti"

  # try to find an image series that was done at the same day/time
  # jq "[.StudyDate,.StudyTime,.SeriesInstanceUID,input_filename]" /data/site/raw/*/*.json
  # jq '{ "SeriesDescription": .SeriesDescription, "StudyDate": .StudyDate,"StudyTime": .StudyTime,"SeriesTime": .SeriesTime, "SeriesInstanceUID": .SeriesInstanceUID,"filename": input_filename} | select(.StudyDate == "20160825")' /data/site/raw/*/*.json

  # search all json files for one that matches this ScanDate
  find /data/site/raw -type f -iname '*.json' -print0 | while read -d $'\0' json
  do

      # Our rule is to match a k-space scan from usb3 with the SeriesInstanceUID that 
      #    is on the same day
      #    has a SeriesTime larger than ti
      #    has a fabs(SeriesTime - ti) < 2min
      sd=($(/usr/bin/jq '{ "StudyDate": .StudyDate } | select(.StudyDate == "'"${da}"'")' $json))
      if [ -z "$sd" ]; then
	  continue;
      fi
      # same day item found
      
      st=($(/usr/bin/jq '.SeriesTime' $json | cut -d'.' -f1 | tr -d '"'))
      if [ $st -le $ti ]; then
	  continue;
      fi
      # after time item found
      
      if [ $(( $st - $ti )) -gt 200 ]; then
	  continue;
      fi

      # get the StudyInstanceUID and the SeriesInstanceUID
      PatientName=($(/usr/bin/jq '.PatientName' $json | tr -d '"'))
      StudyInstanceUID=($(/usr/bin/jq '.StudyInstanceUID' $json | tr -d '"'))
      SeriesInstanceUID=($(/usr/bin/jq '.SeriesInstanceUID' $json | tr -d '"'))
      SeriesNumber=($(/usr/bin/jq '.SeriesNumber' $json | tr -d '"'))

      fn1="/data/quarantine/SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.tgz"
      js1="/data/quarantine/SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.json"
      md1="/data/quarantine/SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.md5sum"
      fn2=$(ls "/data/outbox/*SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.tgz")
      fn3=$(ls "/data/DAIC/*SUID_${StudyInstanceUID}_subjid_${PatientName}_${SeriesInstanceUID}_se${SeriesNumber}_${me}.tgz")

      # does this file exists already, don't do anything
      if [ -e "$fn1" ] || [ ! -z "$fn2" ] || [ ! -z "$fn3" ]; then
	  continue; 
      fi

      echo "$json was scanned on the same day and just after $ti ($st)"

      # now package the dat file with a single DICOM as a k-space package
      dicom=$(ls "${json%.*}" | head -1)
      if [ "$dicom" == "" ]; then
	  echo "Error: no DICOM file found for this series"
      fi
      echo "`date`: create now ${fn1},${js1} with ${json%.*}/$dicom"
      
      #
      # If there are other files that need to be added they should
      # be appended to the end of the next line.
      #

      # setting the compression level to -1 should speed up the packaging
      if hash pigz 2>/dev/null; then
          tar cf - "${json%.*}/${dicom}" "$file" "/data/site/scanner-share/ABCDstream/yarra_export/measfiles/ABCDfMRIhdr/*${me}*.hdr" "/data/site/scanner-share/ABCDstream/yarra_export/measfiles/ABCDMPR/*${me}*.dat" | pigz --fast -p 6 > "${fn1}"
      else
	  GZIP=-1 tar cvzf "${fn1}" "${json%.*}/${dicom}" "$file"  "/data/site/scanner-share/ABCDstream/yarra_export/measfiles/ABCDfMRIhdr/*${me}*.hdr" "/data/site/scanner-share/ABCDstream/yarra_export/measfiles/ABCDMPR/*${me}*.dat"
      fi

      # create a json that goes together with it
      echo "{ \"PatientName\": \"$PatientName\", \"SeriesInstanceUID\": \"${SeriesInstanceUID}\", \"StudyInstanceUID\": \"${StudyInstanceUID}\", \"dat\": \"$file\" }" > "${js1}"

      # and an md5sum file
      /usr/bin/md5sum -b "${fn1}" > "${md1}"
      echo "`date`: packaging done"
  done

done
