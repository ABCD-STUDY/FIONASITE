#!/bin/bash

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
log=${SERVERDIR}/logs/observe_anonymize${project}.log

$checkdir = '/data${project}/outbox_anonymize'
if [ ! -d "${checkdir}" ]; then
    echo "Error: check directory in ${checkdir} does not exist"
    exit -1
fi

runAnonymize() {
    # look into the checkdir for TGZ files that need processing (copy all files later regardless of extension)
    find "${checkdir}" -name "*.TGZ" -type f -print0 | while read -d $'\0' file
    do
	# later we would move all the files (md5sum and json) that belong to this TGZ
	file=`readlink -f "${file}"`
	echo "`date`: found TGZ to anonymize: ${file}" >> $log
	all=${file%.TGZ}
	base=$(basename $file)
	# run the anonymizer now
	echo "`date`: run /var/www/html/server/bin/anonymize_tgz.sh -i \"${file}\" -o \"/tmp/${base}\""
	/var/www/html/server/bin/anonymize_tgz.sh -i "${file}" -o "/tmp/${base}"
	if [ "$?" -eq "0" ] && [ -f "/tmp/${base}" ]; then
	    echo "`date`: Anonymize_tgz worked without producing an error, move /tmp/${base} to /data${project}/outbox/" >> $log
	    mv "/tmp/${base}" "/data${project}/outbox/"
	    if [ -f "/data${project}/outbox/${base}" ]; then
		echo "`date`: remove non-anonymized data ${file}" >> $log 
		rm -rf "${file}"
		if [ "$?" -eq "0" ]; then
		    echo "`date`: copy ${all}.* to outbox" >> $log
		    /usr/bin/mv "${all}.*" "/data${project}/outbox/"
		else
		    echo "`date`: Error, could not delete ${file}"
		fi
	    else
		echo "`date`: Error, move of /tmp/${base} to /data${project}/outbox failed" >> $log
	    fi
	fi
	if [ -f "/tmp/${base}" ]; then
	    echo "`date`: delete /tmp/${base} again..." >> $log
	    rm -rf "/tmp/${base}"
	fi
    done
}

# The following section takes care of not starting this script more than once 
# in a row. If for example it takes too long to run a single iteration this 
# will ensure that no second call is executed prematurely.
(
  flock -n 9 || exit 1
  # command executed under lock
  runAnonymize
) 9>${SERVERDIR}/.pids/observe_anonymize${project}.lock
