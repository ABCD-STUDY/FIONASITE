#!/bin/bash

# Example crontab entry that starts this script every 30 minutes
#   */30 * * * * /usr/bin/nice -n 3 /var/www/html/server/bin/sendFiles.sh
# Add the above line to your machine using:
#   > crontab -e
#
# This script is supposed to send compressed data files for DICOM and k-space
# to the DAIC endpoint using sftp. All data in the /data/outbox directory will
# be send using local and DAIC md5sum files.
#

SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/sendFiles.log
commandScript=${SERVERDIR}/bin/CommandScript
commandScriptMD5s=${SERVERDIR}/bin/CommandScriptMD5s
user=`cat /data/config/config.json | jq -r ".SERVERUSER"`
# directory storing the files that are ok to send
pfiles=/data/outbox


#
# connect to abcd-workspace.ucsd.edu using keyless ssh access
#
sendAllFiles () {
  # we should only send over files that we don't have already there
  # we can get a list of files present at the destination by pulling 
  # all md5sums calculated at the destination
  d=`mktemp -d /tmp/md5sums_server_XXXX`
  cd "$d"
  sftp -p -b ${commandScriptMD5s} ${user}@abcd-workspace.ucsd.edu

  # now we should only upload files that have no, or no correct md5sum calculated on the server
  # find out what exists already on the server
  find "$pfiles" -type f -name "*.md5sum" -print0 | while read -d $'\0' file
  do
    filename="${file##*/}"
    localFileName="${filename%.*}"
    localFileMD5=`cat "$file" | cut -d' ' -f 1`
    echo "  find md5sum and m5sum_server for $localFileMD5" >> $log
    find "$d" -type f -name "*.md5sum_server" -print0 | while read -d $'\0' file2
    do
      filename="${file2##*/}"
      serverFileName="${filename%.*}"
      # if we have the same files MD5
      # echo "compare $localFileName and $serverFileName" >> $log
      if [[ "$localFileName" == "$serverFileName" ]]; then
        serverFileMD5=`cat "$file2" | cut -d' ' -f 1`
        #echo "compare MD5s $serverFileMD5 and $localFileMD5" >> $log
	if [[ "$serverFileMD5" == "$localFileMD5" ]]; then
	    # we don't have to transfer this file, move it to local permanent storage
	    mv "${file%.*}"* /data/DAIC/
            echo "`date`: we are done with ${file}, move to /data/DAIC now for posterity" >> $log
        fi
      fi
    done
  done

  # preserve times (-p)
  # add limit here to prevent too much traffic on the ABCD transfer
  echo "`date`: now copy everything else to the DAIC" >> $log  
  sftp -p -b ${commandScript} ${user}@abcd-workspace.ucsd.edu

  # delete the folder with the md5sums again
  #rm -Rf -- "$d"
}

# The following section takes care of not starting this script more than once 
# in a row. If for example it takes too long to run a single iteration this 
# will ensure that no second call is executed prematurely.
(
  flock -n 9 || exit 1
  # command executed under lock
  sendAllFiles
) 9>${SERVERDIR}/.pids/sendFiles.lock
