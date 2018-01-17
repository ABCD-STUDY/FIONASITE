#!/bin/bash

# 
# Check if the process is running but the pipe does not exist. Remove the program if that is the case.
#
# /usr/bin/python2.7 /var/www/html/server/bin/processSingleFile.py start
# Run this as a cron job for the processing user, instead of the above line.
#

me=$(whoami)
if [[ "$me" != "processing" ]]; then
   echo "Error: checkProcessSingleFile.sh should only be run by the processing user, not by $me"
   exit -1
fi

project="$1"
if [ "$project" == "ABCD" ]; then
  project=""
fi

startString="/var/www/html/server/bin/processSingleFile.py start"
if [ ! -z "$project" ]; then
    startString="/var/www/html/server/bin/processSingleFile.py start $project"
fi

pid=`pgrep -f "/usr/bin/python2.7 ${startString}\s*\$"`
RETVAL=$?
if [ "$RETVAL" == "0" ]; then
    # processSingleFile is running right now, does the pipe exists?
    pipename="/tmp/.processSingleFilePipe${project}"
    if [ ! -p "$pipename" ]; then
	echo "`date`: Error, processSingleFile is running for project \"$project\" but pipe \"$pipename\" does not exist. Restart now..."
	# we should stop processSingleFile and restart it again
	/usr/bin/python2.7 /var/www/html/server/bin/processSingleFile.py stop "${project}"
	/usr/bin/python2.7 /var/www/html/server/bin/processSingleFile.py start "${project}"
    fi
    # find out if the correct user is running processSingleFile
    # echo "ps -o user= --pid \"${pid}\""
    owner=`ps -o user= --pid "${pid}"`
    if [ "${owner}" != "processing" ]; then
	echo "`date`: Error, user \"${owner}\" is running processSingleFile, should be run by processing user only."
    fi
else
    # restart if its not running already
    echo "`date`: Warning, starting processSingleFile for project \"${project}\" again, its not running already."
    /usr/bin/python2.7 /var/www/html/server/bin/processSingleFile.py stop "${project}"
    /usr/bin/python2.7 /var/www/html/server/bin/processSingleFile.py start "${project}"
fi
