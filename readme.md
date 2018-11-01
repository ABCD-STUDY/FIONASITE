FIONA Site Component for Data Capture
======================================

[![RRID:SCR_016012](/images/rrid.svg)](https://scicrunch.org/resolver/SCR_016012)

Simple system to capture MR images and k-space data from medical image systems. Imaging data is received from an MRI scanner, anonymized and uploaded to a centralized storage server. The ABCD project is using this software at its 21 data collection sites. The systems name is derived from a NSF funded project creating a Flash-memory based Input/Output Network Appliance (http://qi.ucsd.edu/news-article.php?id=2342&go=newer). This hardware platform is running the FIONASITE software that provides a web-interface to automate the data review (image viewer), to integrate with the centralized electronic data record for assigning anonymized id's and to forward the data to the central archive.

![Web Interface](/images/webinterface.png "Web Interface")


Configuration
=============

### Directory structure for data storage

```
/data/
 ├── active-scans                // StudyInstanceUID touch files for active scans
 ├── config
 │   └── config.json             // system configuration (see below)
 ├── DAIC                        // files that have been copied to the DAIC (permanent storage)
 ├── enabled                     // text file 0/1 to disable/enable data receive (incron by root)
 ├── failed-scans                // MPPS files with old dates or too fiew images per series
 ├── finished-scans              // MPPS files after data is received
 ├── outbox                      
 ├── inbox			 // DICOM data placed into this directory will be imported                      
 ├── scanner                     // MPPS file storage received from scanner
 ├── site                        
 │   ├── archive                 // receives incoming DICOM, sorted by StudyInstanceUID
 │   ├── participants            // receives incoming DICOM, sorted by PatientID/Date_Time
 │   └── raw                     // receives incoming DICOM, sorted by Study/Series/
 ├── DAIC                        // files that could be send successfully to the DAIC
 ├── outbox                      // files submitted by the user for send to DAIC
 └── quarantine                  // receives p-files+MD5SUM by scp from scanner + tgz'ed DICOM
```

### System configuration file /data/config/config.json

```javascript
{
  "DICOMIP":            "<IP of this computer as seen by the scanner>",
  "DICOMPORT":          "<Port number that receives DICOM data (4006)>",
  "DICOMAETITLE":       "<Application Entity Title of this system <site>FIONA>",
  "SCANNERIP":          "<IP of the scanner console sending DICOM data>",
  "SCANNERPORT":        "<Port on the scanner console receiving findscu/storescu messages (4006)>",
  "SCANNERAETITLE":     "<Application Entity Title of the scanner console>",
  "SCANNERTYPE":        "<SIEMENS|GE|PHILIPS>",
  "OTHERSCANNER": [
      {
         "SCANNERIP":      "<IP of an additional scanner that is sending k-space data>",
	 "SCANNERAETITLE": "<Application Entity Title of the scanner console>",
	 "SCANNERPORT":    "<Port on the scanner console receiving findscu/storescu messages (4006)>"
      }
  ],
  "MPPSPORT":           "<Multiple Performed Procedure Steps port number on this system (4007)>",
  "SERVERUSER":         "<Name of the user account on the DAIC server system>",
  "DAICSERVER":         "137.110.181.166",
  "PFILEDIR":           "/data/<site>",
  "MPPSMODE":           "mppson" | "mppsoff",
  "CONNECTION":         "<REDCap token for participant list>",
  "WEBPROXY":           "<web proxy IP if one is used to connect to the internet>",
  "WEBPROXYPORT":       "<port of the web proxy>",
  "DATADIR": 		"<default projects data directory - (default /data)>",
  "LOCALTIMEZONE":      "<php formatted local timezone identifier e.g. America/Chicago>",
  "SUBJECTID":      	"<REDCap id, first field in project, default=id_redcap>",
  "HIDEPATTERN":        "\\\\^" 
}
```

#### Multiple projects on FIONA
With the latest version of FIONA more than one project can now be hosted on FIONA. Additionally to the default location /data/ a number of further directories - one per new project - can be specified. For example your second project might be called ABCDF. Your configuration file contain one more key with a section for each additional scanner connection:

```
    "SITES": {
        "ABCDE": {
            "DICOMIP":        "<IP of this computer as seen by the scanner>",
            "DICOMPORT":      "<Port number that receives DICOM data (4006)>",
            "DICOMAETITLE":   "<Application Entity Title of this system <site>FIONA>",
            "SCANNERIP":      "<IP of the scanner console sending DICOM data",
            "SCANNERPORT":    "<Port on the scanner console receiving findscu/storescu messages (4006)>",
            "SCANNERAETITLE": "<Application Entity Title of the scanner console>",
            "SCANNERTYPE":    "SIEMENS|GE|PHILIPS",
            "MPPSPORT":       "<Multiple Performed Procedure Steps port number on this system (4007)>",
            "SERVERUSER":     "<Name of the user account on the DAIC server system>",
            "DAICSERVER":     "137.110.180.232",
            "DATADIR":	      "/data<SITE>",
            "PFILEDIR":       "\/data<SITE>\/outbox",
            "CONNECTION":     "<REDCap token for participant list>",
	    "SUBJECTID":      "<REDCap id, first field in project, default=id_redcap>"
        }
    }

```

### Web interface

The content of this git repository should be placed in the website directory of your system:
```
cd /var/www/html/ &&  git clone https://github.com/ABCD-STUDY/FIONASITE.git
```

In order to provide secure access to the web-interface for data transfer the following permission levels are supported:

#### Permission level "abcd-data-entry"

Default user level that allows for ABCD data uploads.

#### User "admin"

Only the "admin" user is able to create new user accounts on the system and change the system setup.

#### Permission level "see-scanner"

Only user that have the permission "see-scanner" will be able to see the current list of scans available on the scanner (Data View: Scanner).

#### Permission level "developer"

Only user that have the permission level "developer" will be able to create new processing buckets (docker container) using the web-interface.

The admin component of the web-interface provides access to a role-based account system that can be used to change and add permissions. 

Debug
======

Use a vagrant setup such as https://github.com/ABCD-STUDY/abcd-dev.git for debugging and development (requires developer permissions).

Processing containers inside FIONA use the DAIC Invention system which is docker based and provides a web interface that allows users to define a docker container, change its content and specify for which events the container will be started. The list of events currently supported is:

 * a study arrives
 * a particular series arrived as identified by a classification type

![Invention user interface](/images/docker-interface.png?raw=true "User interface for docker containers on FIONA.")

Server Endpoint
===============

The site FIONA running this software forwards data to a project end-point computer, which is accessed using sftp. This FIONA end-point is responsible to calculate for each incoming triple of TGZ, md5sum, and json an md5sum_server file. All of the md5sum_server files are packaged at regular intervals into a md5sum server cache on the end-point, which is pulled from the site FIONA. The site FIONA uses that file to compare the site md5sum with the server md5sum file. Only if both contain the same hash the file is considered to be transferred correctly (and moved to /data/DAIC).

There are two scripts running on the end-point FIONA. One is an incrontab deamon that observes the directories for each site FIONA.

checkIncomingFile.sh
```
#!/bin/bash

# run by 'incrontab -e' entry for each incoming file:
#   /data/home/acquisition_sites/ucsd/fiona/outbox IN_CLOSE_WRITE /home/fiona/checkIncomingFile.sh "$@" "$#"

#
# Calculate server based md5sum's to allow checks of correct file transfer from client
#
echo "`date`: CALLED WITH $*" >> /tmp/md5sumCalculation

if [ $# -ne 2 ]; then
    echo "Usage: <directory to tgz file> <tgz file name>"
    echo "Usage: <directory to tgz file> <tgz file name>" >> /tmp/md5sumCalculation
    exit 1
fi
D=`echo "$1" | tr -d '"'`
F=`echo "$2" | tr -d '"'`


# we expect two arguments, one the directory, the second the file inside
file="${D}/${F}"
echo "FILE: $file" >> /tmp/md5sumCalculation

# skip if the file is an md5sum
if [[ "$file" == *md5sum ]]; then
    exit
fi
if [[ "$file" == *md5sum_server ]]; then
    exit
fi
if [[ "$file" == *json ]]; then
    exit
fi
if [[ "$file" == *md5server_cache.tar ]]; then
    exit
fi
if [[ "$file" == *md5server_cache.tar_new ]]; then
    exit
fi
if [[ "$file" == *shadow_copy ]]; then
    # in this case we should remove destination of the non-shadow copy version of this file (if link exists)
    echo "Found a shadow_copy file: ${file}" >> /tmp/md5sumCalculation
    file2=${file%shadow_copy}
    if [ -L ${file2} ]; then
	echo " delete ${file2} link and destination" >> /tmp/md5sumCalculation
	l=`readlink "${file2}"`
	rm -f "${l}"
	rm -f "${file2}"
    fi
    # and keep the file locally
    echo " move ${file} to ${file2}" >> /tmp/md5sumCalculation
    mv "${file}" "${file2}"
    file="${file2}"
fi

# the previous md5sum file if it exists
md5filename="${file%.*}.md5sum_server"
echo "`date`: calculate md5sum on $file and store in $md5filename" >> /tmp/md5sumCalculation
/usr/bin/nice -10 /usr/bin/md5sum -b "$file" > "${md5filename}"
echo "`date`: calculate md5sum done on $file" >> /tmp/md5sumCalculation

# create a symbolic link to the directory we want to run the tar file creation in
# (instead of calling tar directly, we can wait a while and start calling tar once its save
# - every 30 seconds? Call it in a cron job every minute
saveDname=`echo "${D}" | sed -e 's/\///g'`
ln -s "${D}" "/home/fiona/request_server_cache/${saveDname}"

# keep a cache of md5sum_server files for each site, the site FIONA systems will get this single file
# and use it to compare it with their local md5sum version (files that compare are moved to /home/DAIC)
#cd "${D}"
#find . -name '*.md5sum_server' -print0 | tar --null --no-recursion -uf "${D}/md5server_cache.tar" --files-from -

```

The second file is a cronjob that is started every minute. It computes the md5sum cache files for the sites (runServerCache.sh):
```
#!/bin/bash

# call this every minute to compute all the caches that need to be recreated
# don't run this more than once (use flock)
#
#  * * * * * flock -w 0 /home/fiona/request_server_cache/.runServerCache.lock /home/fiona/request_server_cache/runServerCache.sh >> /home/fiona/request_server_cache/runServerCache.log

# old enough means that at least this many seconds needs to have passed from the creation of the file
# (e.g. last time the symbolic link has been created)
oldtime=60

p=/home/fiona/request_server_cache
find "${p}" -type l -print0 | while read -d $'\0' file
do
    valid=1
    sft=`/usr/bin/stat -c "%Y" "${file}"`
    echo "file is \"${file}\" and stat returns: \"$sft\""
    if [ "$(( $(date +"%s") - $sft ))" -lt "$oldtime" ]; then
        echo "`date`: too young $file" >> $log
        valid=0
    fi
    if [[ "$valid" == "1" ]]; then
        cd "${p}"
        D=`readlink -f "${file}"`
        # remove the link file, we will process this directory now (might take some time)
        /bin/rm "${file}"
        echo "`date`: create Tar file in ${D}"
        cd "${D}"
        # tar into a temporary file (I am not sure about the -u here, is that required?)
        find . -name '*.md5sum_server' -print0 | tar --null --no-recursion -uf "${D}/md5server_cache.tar_new" --files-from -
        # use the new file going forward
        mv "${D}/md5server_cache.tar_new" "${D}/md5server_cache.tar"
        echo "`date`: removed ${file}, computation done"
    fi
done
```