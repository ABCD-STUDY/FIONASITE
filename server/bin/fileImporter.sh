#!/bin/bash
#
# DICOM importer based on a directory.
#
# Allow for import of DICOM files from a directory instead of storescu based imports.
#
# (!) Files will be deleted from the input directory after they have been imported !
#
# We assume that inotifywait is installed (yum install inotify-tools).
# This script will keep running and should be run by user processing.
#
# */1 * * * * /var/www/html/server/bin/fileImporter.sh >> /var/www/html/server/logs/fileImporter.log 2>&1
#

#
# check the user account, this script should run as processing
#
if [[ $USER !=  "processing" ]]; then
   echo "[$(date)] : This script must be run as processing"
   exit 1
fi

# import directory
dirloc=/data/inbox

# Housekeeping: Lets look for empty directories that are at least 1 minute old and delete them.
if [ -d "$dirloc" ]; then
  cd "$dirloc"
  find . -depth -type d -empty -cmin +1 -delete
fi

# make sure this script runs only once
thisscript=`basename "$0"`
for pid in $(/usr/sbin/pidof -x "$thisscript"); do
    if [ $pid != $$ ]; then
        echo "[$(date)] : ${thisscript} : Process is already running with PID $pid"
        exit 1
    fi
done

# create the import directory
if [[ ! -d "$dirlock" ]]; then
   mkdir -p -m 0777 "$dirloc"
fi

# make sure we can read all DICOM tags
export DCMDICTPATH=/usr/share/dcmtk/dicom.dic

work() {
  NEWFILE="$1"

  # check if this is a directory (don't do anything for directories)
  if [[ -d "${NEWFILE}" ]]; then
      continue
  fi
  # check if the file is still here
  if [[ ! -f "${NEWFILE}" ]]; then
      continue
  fi

  # check if we have a DICOM file
  /usr/bin/dcmftest "${NEWFILE}"
  if [[ $? != 0 ]]; then
      # delete and continue
      rm "${NEWFILE}"
      continue
  fi

  # get study and series instance UIDs from the file
  StudyInstanceUID=`/usr/bin/dcmdump +P StudyInstanceUID "${NEWFILE}"| cut -d'[' -f2 | cut -d']' -f1`
  Modality=`/usr/bin/dcmdump +P Modality "${NEWFILE}"| cut -d'[' -f2 | cut -d']' -f1`
  SOPInstanceUID=`/usr/bin/dcmdump +P SOPInstanceUID "${NEWFILE}"| cut -d'[' -f2 | cut -d']' -f1`

  # we need to copy this file to
  dest="/data/site/archive/scp_${StudyInstanceUID}/${Modality}.${SOPInstanceUID}"
  echo "[$(date)] : DICOM file move to ${dest}"
  mv "${NEWFILE}" "${dest}"

  # now tell our processSingleFile system service about the new file (we expect raw files to be created now)
  echo "${dest}" >> /tmp/.processSingleFilePipe
}

# If we are started and there is data in the directory already,
# lets try to process those first before establishing a watch.
echo "[$(date)] : initial processing of data in $dirloc"
find "${dirloc}" -depth -type f -print0 | while read -d $'\0' file
do
  work "$file"
done
echo "[$(date)] : initial processing of data in $dirloc done"


echo "[$(date)] : start watching $dirloc"

# now loop through the directory and process each file (copy to archive and parse)
inotifywait -m -r -e create,moved_to --format '%w%f' "${dirloc}" | while read NEWFILE
do
  # work on ${NEWFILE}
  work ${NEWFILE}
done
