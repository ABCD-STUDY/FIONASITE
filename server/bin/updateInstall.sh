#!/bin/bash

#
# Check the directories, files, permissions, owners and cronjobs
#
# no argument - reports errors found with the installation
# -repair     - attempt to repair directories, files, permissions, owners and cronjobs
#

log=/var/www/html/server/logs/updateInstall.log

todo=(
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
    'existsDirectory' '/data/site/scanner-share/ABCDstream/dicom_stream' ''
    'permission' '/data/site/scanner-share/ABCDstream/dicom_stream' '777'
    'existsDirectory' '/data/site/scanner-share/ABCDstream/kspace_stream' ''
    'permission' '/data/site/scanner-share/ABCDstream/kspace_stream' '777'
    'existsDirectory' '/data/site/scanner-share/ABCDstream/yarra_export' ''
    'permission' '/data/site/scanner-share/ABCDstream/yarra_export' '777'
    'existsLink' '/var/www/html/php/output' '/data/site/output'

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
    'permission' '/var/www/html/php/request_compliance_check' '777'
    'owner' '/data/quarantine' 'processing:processing'
    'permission' '/data/quarantine' '777'

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
    'existsFile' '/var/www/html/server/logs/audit.log' ''
    'owner' '/var/www/html/server/logs/audit.log' 'apache:apache'
    'existsFile' '/data/config/config.json' '{ "DICOMIP": "137.110.181.168", "DICOMPORT": "4006", "DICOMAETITLE": "UCSDFIONA", "SCANNERIP": "172.20.141.70", "SCANNERPORT": "4006", "SCANNERAETITLE": "CTIPMUCSD1", "SCANNERTYPE": "SIEMENS", "MPPSPORT": "4007", "SERVERUSER": "daic", "DAICSERVER": "137.110.181.166", "PFILEDIR": "/data/DAIC", "CONNECTION": "" }'
    'existsFile' '/data/config/machines.json' '[]'
    'owner' '/data/config/machines.json' 'processing:processing'
    'permission' '/data/config/machines.json' '644'

    'permission' '/data/enabled' '666'
    'permission' '/data/config/config.json' '644'

    'owner' '/data/enabled' 'apache:apache'
    'owner' '/data/config/config.json' 'apache:apache'
)

if [[ "$1" == "--help" ]] || [[ "$1" == "-h" ]]; then
    echo "NAME:"
    echo " updateInstall - check the directories, files, permissions, and owners"
    echo ""
    echo " This program is part of the FIONA computer and checks the system health."
    echo ""
    echo "AUTHOR:"
    echo "  Hauke Bartsch - <HaukeBartsch@gmail.com>"
    echo "  Alex DeCastro - <AlexDeCastro2@gmail.com>"
    echo ""
    echo "USAGE:"
    echo ""
    echo " > sudo $0          # print out errors found with this installation"
    echo " > sudo $0 -f       # fix errors found with this installation"
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

# check if we have the tools to check on a checkable system
checkTools() {
    force=$1
    echo -n "`date`: check tools..."

    # operating system check
    operatingSystem=`uname -s`
    if [[ ! $operatingSystem == "Linux" ]]; then
        echo "Error: only Linux is currently supported (found $operatingSystem instead). Giving up..." 1>&2
        exit 1;
    fi
    if [ ! -f "$log" ]; then
        touch "$log"
    fi

    # check for logfile location
    if [[ ! -w "$log" ]]; then
        echo "Error: cannot write messages to logfile $log" 1>&2
        if [[ "$force" == "1" ]]; then
             log=/tmp/updateInstall.log
             echo "`date`: Attempt to create logfile in $log" >> $log
             if [[ -w "$log" ]]; then
                 echo "FIX: logs will be written to $log instead" 1>&2
             fi             
        fi
    fi
    # are we root?
    if [[ $EUID -ne 0 ]]; then
       echo "Error: This script should be run as root" 1>&2
       exit 1
    fi
    printf "\n"
}


#######################################
#
#  check if link exists
#
#######################################

# check if a single directory exists
checkLinkExist() {
    path=$1
    target=$2
    if [[ -L "$path" ]]; then
       # we should check if the target is correct as well
       if [[ $(realpath "$path") != "$target" ]]; then
	   echo "0"
       else
           echo "1"
       fi
    else
       echo "0"
    fi
}

# fix a single directory
fixLinkExist() {
    path=$1
    target=$2
    ln -s "$target" "$path"
}

# check if the directories exist
checkLinksExist() {
    force=$1
    echo -n "`date`: check links..."
    
    l=${#todo[@]}
    count=0
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
      if [[ "${todo[$i]}" == "existsLink" ]]; then
        count=$((count+1))
        printf "\e[0K\r`date`: check link... [$count]"
        dir=${todo[(($i+1))]}
        target=${todo[(($i+2))]}
        
        if [[ $(checkLinkExist "$dir" "$target") == "0" ]]; then
           printf "\nError: Link \"$dir\" does not exist\n"
           if [[ "$force" == "1" ]]; then
              fixLinkExist "$dir" "$target"
              if [[ $(checkLinkExist "$dir" "$target") == "0" ]]; then
                 echo "Error: could not create symbolic link \"$dir\" to target \"$target\""
              fi
           fi
        fi
      fi
    done
    printf "\n"
}


#######################################
#
#  check if directory exists
#
#######################################

# check if a single directory exists
checkDirectoryExist() {
    path=$1
    if [[ -d "$path" ]]; then
       echo "1"
    else
       echo "0"
    fi
}

# fix a single directory
fixDirectoryExist() {
    path=$1
    expected=$2
    if [[ "$expected" == "" ]]; then
       mkdir -p $path
    else
       echo "$expected" > $file
    fi
}

# check if the directories exist
checkDirectoriesExist() {
    force=$1
    echo -n "`date`: check directories..."
    
    l=${#todo[@]}
    count=0
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
      if [[ "${todo[$i]}" == "existsDirectory" ]]; then
        count=$((count+1))
        printf "\e[0K\r`date`: check directories... [$count]"
        dir=${todo[(($i+1))]}
        
        # As a test, first remove the directory
        #rmdir $dir
        
        if [[ $(checkDirectoryExist "$dir") == "0" ]]; then
           printf "\nError: Directory \"$dir\" does not exist\n"
           if [[ "$force" == "1" ]]; then
              fixDirectoryExist "$dir"
              if [[ $(checkDirectoryExist "$dir") == "0" ]]; then
                 echo "Error: could not create directory \"$dir\""
              fi
           fi
        fi
      fi
    done
    printf "\n"
}

#######################################
#
#  check if files exist
#
#######################################

# check if a single file exists
checkFileExist() {
    path=$1
    expected=$2
    if [[ -f "$path" ]]; then
       echo "1"
    else
       echo "0"
    fi
}

# fix a single permission
fixFileExist() {
    path=$1
    expected=$2
    if [[ "$expected" == "" ]]; then
       touch "$path"
    else
       echo "$expected" > $path
    fi
}

# check if the files exist
checkFilesExist() {
    force=$1
    echo -n "`date`: check files..."
    
    l=${#todo[@]}
    count=0
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
      if [[ "${todo[$i]}" == "existsFile" ]]; then
        count=$((count+1))
        printf "\e[0K\r`date`: check files... [$count]"
        path=${todo[(($i+1))]}
        expected=${todo[(($i+2))]}
        if [[ $(checkFileExist "$path" "$expected") == "0" ]]; then
           printf "\nError: file \"$path\" does not exist.\n"
           if [[ "$force" == "1" ]]; then
              fixFileExist "$path" "$expected"
              if [[ $(checkFileExist "$path" "$expected") == "0" ]]; then
                 echo "Error: could not create file $path"
              fi
           fi
        fi
      fi
    done
    printf "\n"
}

#######################################
#
#  check owner
#
#######################################

# check a single files owner
checkOwner() {
    path=$1
    expected=$2
    owner=$(stat -c %U:%G "$path")
    if [[ "$owner" != "$expected" ]]; then
       echo "0"
    else
       echo "1"
    fi
}

# fix a single permission
fixOwner() {
    path=$1
    expected=$2
    chown "$expected" "$path"
}

# check the owners
checkOwners() {
    force=$1
    echo -n "`date`: check owners..."
    
    l=${#todo[@]}
    count=0
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
      # echo ${todo[$i]}
      if [[ "${todo[$i]}" == "owner" ]]; then
         count=$((count+1))
         printf "\e[0K\r`date`: check owners... [$count]"
          path=${todo[(($i+1))]}
          expected=${todo[(($i+2))]}
          if [[ $(checkOwner "$path" "$expected") == "0" ]]; then
             printf "\nERROR: owner for \"$path\" is wrong. Expected to be \"$expected\" but found $(stat -c %U:%G $path)\n"
             if [[ "$force" == "1" ]]; then
                fixOwner "$path" "$expected"
                if [[ $(checkOwner "$path" "$expected") == "0" ]]; then
                   echo "Error: could not fix owner on $path to $expected"
                fi
             fi
          fi
      fi
    done
    printf "\n"
}

#######################################
#
#  check permissions
#
#######################################

# check a single permission
checkPermission() {
    path=$1
    expected=$2
    permission=$(stat -c %a "$path")
    if [[ "$permission" != "$expected" ]]; then
       echo "0"
    else
       echo "1"
    fi
}

# fix a single permission
fixPermission() {
    path=$1
    expected=$2
    chmod "$expected" "$path"
}

# check all permissions
checkPermissions() {
    force=$1
    echo -n "`date`: check permissions..."
    
    l=${#todo[@]}
    count=0
    for (( i=0; i<${l}+1; i=$i+3 ));
    do
      # echo ${todo[$i]}
      if [[ "${todo[$i]}" == "permission" ]]; then
          count=$((count+1))
          printf "\e[0K\r`date`: check permissions... [$count]"
          path=${todo[(($i+1))]}
          expected=${todo[(($i+2))]}
          if [[ $(checkPermission "$path" "$expected") == "0" ]]; then
             printf "\nError: permission wrong for $path. Expected to be: $expected but found $(stat -c %a $path)\n"
             if [[ "$force" == "1" ]]; then
                fixPermission "$path" "$expected"
                if [[ $(checkPermission "$path" "$expected") == "0" ]]; then
                   echo "Error: could not fix permissions on $path to $expected"
                fi          
             fi
          fi
      fi
    done
    printf "\n"
}

checkTools $force
checkDirectoriesExist $force
checkLinksExist $force
checkFilesExist $force
checkOwners $force
checkPermissions $force
printf "\n"
