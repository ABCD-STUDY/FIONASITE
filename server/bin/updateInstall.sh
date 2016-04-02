#!/bin/bash

#
# Check the directories, files, permissions, owners and cronjobs
#
# no argument - reports errors found with the installation
# -repair     - attempt to repair directories, files, permissions, owners and cronjobs
#

log=/var/www/html/server/logs/updateInstall.log
echo "`date`: called updateInstall" >> $log

todo_old=(
    'existsDirectory' '/data' ''
    'existsDirectory' '/data/DAIC' ''
    'existsDirectory' '/data/active-scans' ''
    'existsDirectory' '/data/config' ''
    'existsDirectory' '/data/failed-scans' ''
    'existsDirectory' '/data/finished-scans' ''
    'existsDirectory' '/data/scanner' ''
    'existsDirectory' '/data/site' ''
    'existsDirectory' '/var/www/html/server' ''
    'existsDirectory' '/var/www/html/server/.pids' ''
    'existsDirectory' '/var/www/html/server/logs' ''

    'permission' '/data' '777'
    'permission' '/data/DAIC' '755'
    'permission' '/data/active-scans' '755'
    'permission' '/data/config' '755'
    'permission' '/data/failed-scans' '755'
    'permission' '/data/finished-scans' '755'
    'permission' '/data/scanner' '755'
    'permission' '/data/site' '755'
    'permission' '/var/www/html/server' '755'
    'permission' '/var/www/html/server/.pids' '777'
    'permission' '/var/www/html/server/logs' '777'

    'owner' '/data' 'processing:processing'
    'owner' '/data/DAIC' 'processing:processing'
    'owner' '/data/active-scans' 'processing:processing'
    'owner' '/data/config' 'processing:processing'
    'owner' '/data/failed-scans' 'processing:processing'
    'owner' '/data/finished-scans' 'processing:processing'
    'owner' '/data/scanner' 'processing:processing'
    'owner' '/data/site' 'processing:processing'
    'owner' '/var/www/html/server' 'root:root'
    'owner' '/var/www/html/server/.pids' 'processing:processing'
    'owner' '/var/www/html/server/logs' 'processing:processing'

    'existsFile' '/data/enabled' '111'
    'existsFile' '/data/config/config.json' '{ "DICOMIP": "137.110.181.168", "DICOMPORT": "4006", "DICOMAETITLE": "UCSDFIONA", "SCANNERIP": "172.20.141.70", "SCANNERPORT": "4006", "SCANNERAETITLE": "CTIPMUCSD1", "MPPSPORT": "4007", "SERVERUSER": "daic", "DAICSERVER": "137.110.181.166", "PFILEDIR": "/data/DAIC" }'

    'permission' '/data/enabled' '666'
    'permission' '/data/config/config.json' '644'

    'owner' '/data/enabled' 'processing:processing'
    'owner' '/data/config/config.json' 'apache:apache'
)

todo=(
    'existsDirectory' '/var/www/html/server/bin/alextest/testdir' ''
    'existsFile' '/var/www/html/server/bin/alextest/testfile' 'hello'
    'permission' '/var/www/html/server/bin/alextest/testdir' '777'
    'permission' '/var/www/html/server/bin/alextest/testfile' '666'
    'owner' '/var/www/html/server/bin/alextest/testdir' 'processing:processing'
    'owner' '/var/www/html/server/bin/alextest/testfile' 'processing:processing'
)

if [[ "$1" == "--help" ]]
then
    echo "NAME:"
    echo " updateInstall - check the directories, files, permissions, owners and cronjobs"
    echo ""
    echo "AUTHOR:"
    echo "  Hauke Bartsch - <HaukeBartsch@gmail.com>"
    echo "  Alex DeCastro - <AlexDeCastro2@gmail.com>"
    echo ""
    echo "USAGE:"
    echo ""
    exit 1
fi

force=0
check=1
while [[ $# > 0 ]]
do
    key="$1"
    
    case $key in
	force|-f)
	    force=1
	    ;;
	*)
	    echo "unknown option"
	    exit 1
	    ;;
    esac
    shift
done

# check if the directories exist
checkDirectoriesExist() {
    force=$1
    echo "`date`: check if the directories exist..."
    
    l=${#todo[@]}
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
	if [[ "${todo[$i]}" == "existsDirectory" ]]; then
	    dir=${todo[(($i+1))]}
	    
	    # As a test, first remove the directory
	    #rmdir $dir
	    
            if [[ ! -d "$dir" ]]; then
		echo "ERROR: Directory $dir does not exist"
		if [[ "$force" == "1" ]]; then
		    echo "FIX: mkdir -p $dir"
		    mkdir -p "$dir"
		fi
            fi
	fi
    done
}

# check if the files exist
checkFilesExist() {
    force=$1
    echo "`date`: check if the files exist..."
    
    l=${#todo[@]}
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
	if [[ "${todo[$i]}" == "existsFile" ]]; then
	    file=${todo[(($i+1))]}
	    expected=${todo[(($i+2))]}
            if [[ -f "$file" ]]; then
		# if the file exists...
		if [[ "$expected" != "" ]]; then
		    # and if the expected contents are specified...
		    filecontents=$(cat "$file")
		    if [[ "$filecontents" != "$expected" ]]; then
			# and if the file contents do not match the expected contents
			# then warn the user, but do not try to fix the file.
			echo "WARNING: File $file contents: $filecontents do not match the expected contents: $expected"
		    fi
		fi
	    else
		# if the file does not exist, then attempt to create the file
		# using the expected contents
		echo "ERROR: File $file does not exist"
		if [[ "$force" == "1" ]]; then
		    if [[ "$expected" == "" ]]; then
			echo "ERROR: Expected contents not provided. Cannot create file $file"
		    else
			echo "FIX: Creating file: echo $expected > $file"
			echo "$expected" > $file
		    fi
		fi
            fi
	fi
    done
}

# check the owners
checkOwners() {
    force=$1
    echo "`date`: check the owners..."
    
    l=${#todo[@]}
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
	# echo ${todo[$i]}
	if [[ "${todo[$i]}" == "owner" ]]; then
	    path=${todo[(($i+1))]}
	    expected=${todo[(($i+2))]}
	    owner=$(stat -c %U:%G "$path")
	    
	    # As a test, first change the owner to apache:apache
	    #chown apache:apache $path
	    
	    if [[ "$owner" != "$expected" ]]; then
		echo "ERROR: Owner of: $path is $owner. Expected to be: $expected"
		if [[ "$force" == "1" ]]; then
		    echo "FIX: chown $expected $path"
		    chown $expected $path
		fi
	    fi
	fi
    done
}

# check the permissions
checkPermissions() {
    force=$1
    echo "`date`: check the permissions..."
    
    l=${#todo[@]}
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
	# echo ${todo[$i]}
	if [[ "${todo[$i]}" == "permission" ]]; then
	    path=${todo[(($i+1))]}
	    expected=${todo[(($i+2))]}
	    permission=$(stat -c %a "$path")
	    
	    # As a test, first change the permission to apache:apache
	    #chmod 444 $path
	    
	    if [[ "$permission" != "$expected" ]]; then
		echo "ERROR: Permission for: $path is $permission. Expected to be: $expected"
		if [[ "$force" == "1" ]]; then
		    echo "FIX: chmod $expected $path"
		    chmod $expected $path
		fi
	    fi
	fi
    done
}

checkDirectoriesExist $force
checkFilesExist $force
checkOwners $force
checkPermissions $force
