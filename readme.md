FIONA Site Component for Data Capture
======================================

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
  "LOCALTIMEZONE":      "<php formatted local timezone identifier e.g. America/Chicago>"
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
            "CONNECTION":     "<REDCap token for participant list>"
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