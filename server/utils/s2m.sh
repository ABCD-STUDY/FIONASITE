#!/bin/bash
#
# send to me (s2m)
# Sends a DICOM directory using the dcmtk docker container from the local 
# machine to the local DICOM node. This script can be used to re-classify
# DICOM files (creates /data/site/raw and /data/site/participant information).
#
# Usage:
#
#    # Send a single directory with DICOM files
#    s2m.sh <DICOM directory to send> [project]
#
#    # Send all studies of a single PatientID
#    s2m.sh <PatientID> [project]
#
#    # send all studies of the last 7 days
#    s2m.sh last 7 [project]
#

myip=`cat /data/config/config.json | jq -r ".DICOMIP"`
myport=`cat /data/config/config.json | jq -r ".DICOMPORT"`
project=""
if [[ $# -eq 0 ]]; then
   echo "usage: $0 <dicom directory> <project>"
   exit
fi

if [[ "$1" == "last" ]]; then
    days=1
    if [[ $# -eq 2 ]]; then
        days="$2"
    elif [[ $# -eq 3 ]]; then
        days="$2"
        project="$3"
        if [[ "$project" == "ABCD" ]]; then
            project=""
        fi
        if [[ ! -z "$project" ]]; then
            myip=`cat /data/config/config.json | jq -r ".SITES.${project}.DICOMIP"`
            myport=`cat /data/config/config.json | jq -r ".SITES.${project}.DICOMPORT"`
        fi
    else
        echo "Error: Wrong number of arguments for last, should be: last <days> [project]"
        exit
    fi

    find /data${project}/site/archive/ -mindepth 1 -type d -mtime "-$days" -print0 | while read -d $'\0' file
    do
	    dir=`realpath "$file"`
	    echo "Send data in \"$dir\" to $myip : $myport"
	    # make sure that we redirect stdin, otherwise this line will eat up the file variable in our loop and we can only submit a single line
	    /bin/bash -c "docker run -i -v ${dir}:/input dcmtk /bin/bash -c \"/usr/bin/storescu -v +sd +r -nh $myip $myport /input; exit\" </dev/null"
        if [[ $? -ne "0" ]]; then
	        # sending using docker is fastest, but it can fail due to network issues, lets send straight using storescu in that case
	        echo "sending with docker failed, send using storescu instead"
	        /usr/bin/storescu -v -aet me -aec me +sd +r -nh $myip $myport "${dir}"
	    fi
    done
    exit
fi

dir=`realpath "$1"`
if [[ $# -eq 2 ]]; then
    project="$2"
    if [[ "$project" == "ABCD" ]]; then
        project=""
    fi
    if [[ ! -z "$project" ]]; then
        myip=`cat /data/config/config.json | jq -r ".SITES.${project}.DICOMIP"`
        myport=`cat /data/config/config.json | jq -r ".SITES.${project}.DICOMPORT"`
    fi
fi
if [ ! -d "$dir" ]; then
    # path does not exist, perhaps we got a subject id - find and select first study with that ID
    dd=`jq -r "[.PatientID,.PatientName,.StudyInstanceUID] | @csv" /data${project}/site/raw/*/*.json | grep $1 | sort | uniq | cut -d',' -f3 | tr -d '"'`
    for a in $dd; do
	    # call ourselfs again with the participant id
	    dir=`realpath "/data${project}/site/archive/scp_$a"`
        # run this in an interactive shell
        echo "#################################"
        echo "# Run a new sub-send command    #"
        echo "#################################"
	    /usr/bin/bash -i -c "$0 $dir"
    done
    exit
fi

echo "Send data in \"$dir\" to $myip : $myport"
docker run -it -v ${dir}:/input dcmtk /bin/bash -c "/usr/bin/storescu -v +sd +r -nh $myip $myport /input; exit"
if [[ $? -ne "0" ]]; then
    # sending using docker is fastest, but it can fail due to network issues, lets send straight using storescu in that case
    echo "sending with docker failed, send using storescu instead"
    /usr/bin/storescu -v -aet me -aec me +sd +r -nh $myip $myport "${dir}"
fi
exit

