#!/bin/bash
#
# Siemens k-space data from an MRI scanner is stored as .dat files on a drive shared on the network. 
# This script tries to map the dates/times of these .dat files against the DICOM files available 
# on the system (/data/site/raw).
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
# make sure this script runs only once (each .dat file is about 30G)
#
thisscript=`basename "$0"`
for pid in $(/usr/sbin/pidof -x "$thisscript"); do
    if [ $pid != $$ ]; then
        echo "[$(date)] : ${thisscript} : Process is already running with PID $pid"
        exit 1
    fi
done

# The scanner host exports the drive with the data
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
  bn=`basename "$file"`
  t=`echo $bn | rev | cut -d'_' -f3- | rev`
  d=`echo $bn | rev | cut -d'_' -f2 | rev`
  t=`echo $bn | rev | cut -d'_' -f1 | rev | cut -d'.' -f1`

  echo "found $file of type $t done on day $d time $t"

  # try to find an image series that was done at the same day/time
  # jq "[.StudyDate,.StudyTime,.SeriesInstanceUID,input_filename]" /data/site/raw/*/*.json

done
