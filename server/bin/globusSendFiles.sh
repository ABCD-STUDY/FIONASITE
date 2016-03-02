#!/bin/bash

# Example crontab entry that starts this script every 30 minutes
#   */30 * * * * /usr/bin/nice -n 3 /data/server/bin/sendFiles.sh
# Add the above line to your machine using:
#   > crontab -e
#
# (inactive, use sftp instead for now)

SERVERDIR=`dirname "$(readlink -f "$0")"`/../
user=`cat /data/config/config.json | jq -r ".SERVERUSER"`
DAICSERVER=`cat /data/config/config.json | jq -r ".DAICSERVER"`
PFILEDIR=`cat /data/config/config.json | jq -r ".PFILEDIR"`

#
# connect to abcd-workspace.ucsd.edu using keyless ssh access
#
sendAllFiles () {
  cd /data/UCSD
  for u in `find . -type f -print`
  do
    globus-url-copy -vb -p 2 file:${PFILEDIR}/${u}  sshftp://${user}@${DAICSERVER}/data/home/acquisition_sites/ucsd/fiona
  done
}

# The following section takes care of not starting this script more than once 
# in a row. If for example it takes too long to run a single iteration this 
# will ensure that no second call is executed prematurely.
(
  flock -n 9 || exit 1
  # command executed under lock
  sendAllFiles
) 9>${SERVERDIR}/.pids/sendFiles.lock
