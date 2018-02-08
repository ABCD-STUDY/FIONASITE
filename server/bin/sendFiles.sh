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

me=$(whoami)
if [[ "$me" != "processing" ]]; then
   echo "Error: sendFiles should only be run by the processing user, not by $me"
   exit -1
fi

project=""
if [ "$#" -eq 1 ]; then
  project="$1"
fi

SERVERDIR=`dirname "$(readlink -f "$0")"`/../
log=${SERVERDIR}/logs/sendFiles${project}.log
commandScript=${SERVERDIR}/bin/CommandScript${project}
commandScriptMD5s=${SERVERDIR}/bin/CommandScriptMD5s
user=`cat /data/config/config.json | jq -r ".SERVERUSER"`
# directory storing the files that are ok to send
pfiles=/data${project}/outbox
endpoint=`cat /data/config/config.json | jq -r ".DAICSERVER"`
if [ "$project" != "" ]; then
    endpoint=`cat /data/config/config.json | jq -r ".SITES.${project}.DAICSERVER"`
fi
echo "Endpoint selected: $endpoint"

#
# connect to abcd-workspace.ucsd.edu using keyless ssh access
#
sendAllFiles () {
  # we should only send over files that we don't have already there
  # we can get a list of files present at the destination by pulling 
  # all md5sums calculated at the destination
  d=`mktemp -d /tmp/md5sums_server_XXXX`
  cd "$d"
  sftp -p -b ${commandScriptMD5s} ${user}@${endpoint}
  if [[ -e md5server_cache.tar ]]; then
     # untar the md5server file into the current directory, and go on
     tar xf md5server_cache.tar
  fi

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
	    shadow="${file%.*}.tgzshadow_copy"
	    if [[ -e "${shadow}" ]]; then
	    	echo "Shadow_copy file detected, don't copy this file ${shadow} - will be removed" >> $log
	    	rm -f "${shadow}"
	    fi
	    mv "${file%.*}"* /data${project}/DAIC/
            echo "`date`: we are done with ${file%.*}*, move to /data/DAIC now for posterity" >> $log
	else
            echo "`date`: MD5SUM in ${file} does not match with server version in ${file2}, send the corresponding TGZ file again" >> $log
	    # one reason of a mis-match is that the local md5sum file could be wrong (see below for a fix)
	    # echo " calculate md5sum ${file} > ${localFileName}.md5sum" >> $log
        fi
      fi
    done
  done


  # preserve times (-p)
  # add limit here to prevent too much traffic on the ABCD transfer
  echo "`date`: now copy everything else to the DAIC" >> $log  
  START=$(date +%s.%N)
  sftp -p -b ${commandScript} ${user}@${endpoint}
  END=$(date +%s.%N)
  dur=$(echo "$END - $START" | bc)
  echo "`date`: copy done (${dur}sec)" >> $log  

  # delete the folder with the md5sums again
  rm -Rf -- "$d"

  # just in case we end up with tgz files without an .md5sum files in this directory - calculate md5sum for those
  find "$pfiles" -type f -mtime +5 -name "*.tgz" -print0 | while read -d $'\0' file
  do
      f="${file%.*}"
      sf="${f}.md5sum"
      if [[ ! -e "${sf}" ]]; then 
	  echo "`date`: this file does not have an md5sum file ${file}, create one for it at ${sf}" >> $log
	  /usr/bin/md5sum -b "${file}" > "${sf}"
      fi; 
  done
  
  # another reason transfers might fail is if the local md5sum is not correct, we should recalculate in that case
  find "$pfiles" -type f -mtime +5 -name "*.tgz" -print0 | while read -d $'\0' file
  do
      f="${file%.*}"
      sf="${f}.md5sum"
      if [[ -e "${sf}" ]]; then
	  md5c=`/usr/bin/md5sum -b "${file}"`
	  md5=`echo ${md5c} | cut -d' ' -f1`
	  md52=`cat "${sf}" | cut -d' ' -f1`
	  if [[ "$md5" != "$md52" ]]; then
	      echo "`date`: Error: existing MD5sum in ${sf} (${md52}) is not the same as MD5Sum from ${file} (${md5}), rewrite ${sf}"
	      echo "${md5c}" > ${sf}
	  fi
      fi
  done

  # Ok, last resort effort:
  # If the transferred file is a symbolic link at the destination, the sftp send operation will fail.
  # In that case we can only send again if we change the filename (append shadow_copy). The server
  # will remove the "shadow_copy" extension and use that file. This should only be done for old files
  # (last resort).
  # Oh, and make sure this will work even if the disk is full.
  find "$pfiles" -type f -mtime +20 -name "*.tgz" -print0 | while read -d $'\0' file
  do
      echo "`date`: Try to create shadow copy \"${file}shadow_copy\"" >> $log
      f="${file%.*}.BAK"
      cp -p "${file}" "${f}"
      if [[ ! -f "${f}" ]]; then
	  echo "`date`: Error could not create copy of ${file} as ${f}" >> $log
      else
	  s1=$(/usr/bin/stat -c%s "${file}")
	  s2=$(/usr/bin/stat -c%s "${f}")
	  if [[ "$s1" -eq "$s2" ]]; then
	      # should keep the modification time
	      mv "${f}" "${file}shadow_copy"
	      echo "`date`: copy to ${file}shadow_copy done" >> $log
	  else
	      if [[ -f "${f}" ]]; then
		  echo "`date`: Error copy to ${file}shadow_copy failed (file size ${file} ($s1) not equal ${f} ($s2)), remove temp ${f} again" >> $log
		  rm -f "${f}"
	      fi
	  fi
      fi
  done

}

# The following section takes care of not starting this script more than once 
# in a row. If for example it takes too long to run a single iteration this 
# will ensure that no second call is executed prematurely.
(
  flock -n 9 || exit 1
  # command executed under lock
  sendAllFiles
) 9>${SERVERDIR}/.pids/sendFiles${project}.lock
