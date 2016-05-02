FIONA Site Component for Data Capture
======================================

Simple system to capture site information.

Imaging data is received from the scanner, anonymized and uploaded to a centralized storage server.


Configuration
=============

### Data directory:

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
├── scanner                     // MPPS file storage received from scanner
├── site                        
│   ├── archive                 // receives incoming DICOM, sorted by StudyInstanceUID
│   ├── participants            // receives incoming DICOM, sorted by PatientID/Date_Time
│   └── raw                     // receives incoming DICOM, sorted by Study/Series/
└── UCSD                        // receives p-files+MD5SUM by scp from scanner
```

(/data/config/config.json)
```javascript
{
  "DICOMIP":            "<IP of this computer as seen by the scanner>",
  "DICOMPORT":          "<Port number that receives DICOM data (4006)>",
  "DICOMAETITLE":       "<Application Entity Title of this system <site>FIONA>",
  "SCANNERIP":          "<IP of the scanner console sending DICOM data>",
  "SCANNERPORT":        "<Port on the scanner console receiving findscu/storescu messages (4006)>",
  "SCANNERAETITLE":     "<Application Entity Title of the scanner console>",
  "MPPSPORT":           "<Multiple Performed Procedure Steps port number on this system (4007)>",
  "SERVERUSER":         "<Name of the user account on the DAIC server system>",
  "DAICSERVER":         "137.110.181.166",
  "PFILEDIR":           "/data/<site>",
  "MPPSMODE":           "mppson" | "mppsoff"
}
```

### Web interface:

/var/www/html/ (git clone https://github.com/ABCD-STUDY/FIONASITE.git)

### Server components:

See readme in server directory.


Debug
======

Use a vagrant setup such as https://github.com/ABCD-STUDY/abcd-dev.git for debugging and development.

Processing containers inside FIONA use the Invention system which is docker based.

![Invention user interface](/images/docker-interface.png?raw=true "User interface for docker containers on FIONA.")