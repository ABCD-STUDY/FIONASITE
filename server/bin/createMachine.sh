#!/bin/bash

#
# Create/start/stop/save a virtual machine for our processing jobs (started by incron as user processing)
#
#

if [ $# -eq 0 ]; then
  echo "Usage: $0 <path to id file> <id file name>"
  exit 0
fi

startport=4200
endport=4220

path=$1
id=$2

# remove the control file again
if [[ -f "$1/$2" ]]; then
  rm -f "$1/$2"
fi

action=`echo $id | cut -d'_' -f1`
# container id is at the end of the first underline (start_<container id>)
id=`echo $id | cut -d'_' -f2-`

# find a free port
port=$startport
until [ "$port" -gt "$endport" ]; do
  /bin/netstat -tln | grep ":${port}"
  if [[ $? -eq 1 ]]; then
     # found noone listening in on this port yet
     echo "Found free port: $port"
     break;
  fi
  port=$((port+1))
done
# check if we found a free port here!
# maybe that machine already exists?

log=/var/www/html/server/logs/machines.log

worked=0
if [[ $action == "create" ]]; then
  echo "`date`: create a machine $id" >> $log
  cd /var/www/html/php/inventions/
  docker build -t $id -f /var/www/html/php/inventions/Dockerfile .
  # docker run -d -p ${port}:4200 $id && worked=1

  # we need to update the running machines files with the information for this machine
  # better here than in the web-frontend - might not know if its working
 
  cat /data/config/machines.json | jq ". |= .+ [ {name:\"$id\",id:\"$id\"} ]" > /tmp/_machines.json
  mv /tmp/_machines.json /data/config/machines.json
elif [[ $action == "save" ]]; then
  echo "`date`: save machine $id under the same id" >> $log
  # save the currently running incarnation of the container as the new image
  line=`docker ps | tail -n +2 | grep $id`
  if [[ ! $line == "" ]]; then
     c=`echo $line | cut -d' ' -f1`
     docker commit "$c" "$id"
  fi
elif [[ $action == "start" ]]; then
  echo "`date`: start a machine $id" >> $log
  # find out if we have this machine running
  line=`docker ps | tail -n +2 | grep $id`
  if [[ $line == "" ]]; then
     # we don't have that id in the list of running machines, start it now
     containerid=$(docker run -d -p ${port}:4200 $id)
     # add the port number
     cat /data/config/machines.json | jq "[.[] | select(.id == \"$id\") |= .+ {port:\"$port\"}] " > /tmp/_machines.json
     mv /tmp/_machines.json /data/config/machines.json     
  fi
elif [[ $action == "stop" ]]; then
  echo "`date`: stop a machine $id" >> $log
  # find out if we have this machine running
  line=`docker ps | tail -n +2 | grep $id`
  if [[ ! $line == "" ]]; then
     c=`echo $line | cut -d' ' -f1`
     # we do have that id in the list of running machines, stop it now
     docker stop $c
     docker rm $c
     # remove port number
     cat /data/config/machines.json | jq "[.[] | select(.id == \"$id\") |= .+ {port:\"\"}]" > /tmp/_machines.json
     mv /tmp/_machines.json /data/config/machines.json
  fi
fi
