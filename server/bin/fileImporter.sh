#!/bin/bash

#
# DICOM importer based on a directory.
#
# Allow for import of DICOM files from a directory instead of storescu based imports.
#
# (!) Files will be deleted from the input directory after they have been imported !
#
# We assume that inotifywait is installed (yum install inotify-tools).
# This script will keep running. We still need to test if its up already.
#

dirloc=/data/inbox
if [[ ! -d "$dirlock" ]]; then
   mkdir -p -m 0666 "$dirlock"
fi

inotifywait -m -r -e create,moved_to --format '%w%f' "${dirloc}" | while read NEWFILE
do
  # work on ${NEWFILE}
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

  echo "We found a DICOM file that would be stored here /data/site/archive/scp_${StudyInstanceUID}/${Modality}.${SOPInstanceUID}\n"
  # we need to copy this file to
  dest="/data/site/archive/scp_${StudyInstanceUID}/${Modality}.${SOPInstanceUID}"
  mv "${NEWFILE}" "${dest}"

  # now tell our processSingleFile system service about the new file (we expect raw files to be created now)
  echo "local,local,local,/data/site/archive/scp_${StudyInstanceUID}/,${Modality}.${SOPInstanceUID}" >> /tmp/.processSingleFilePipe 

  # how to we delete old/empty directories?
done
