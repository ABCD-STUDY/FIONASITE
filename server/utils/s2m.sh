#!/bin/bash
#
# send to me 
# Sends a DICOM directory using the dcmtk docker container
#
myip=`cat /data/config/config.json | jq ".DICOMIP" | tr -d '"'`
myport=`cat /data/config/config.json | jq ".DICOMPORT" | tr -d '"'`
if [[ $# -ne 1 ]]; then
   echo "usage: $0 <dicom directory>"
   exit
fi

dir=`realpath "$1"`

echo "Send data in \"$dir\" to $myip : $myport"
docker run -it -v ${dir}:/input dcmtk /bin/bash -c "/usr/bin/storescu -v +sd +r -nh $myip $myport /input; exit"
