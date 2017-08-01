FIONA Site Component for Data Capture
======================================

Simple system to capture MR images and k-space data from medical image systems. Imaging data is received from an MRI scanner, anonymized and uploaded to a centralized storage server. The ABCD project is using this software at its 21 data collection sites. The systems name is derived from a NSF funded project creating a Flash-memory based Input/Output Network Appliance (http://qi.ucsd.edu/news-article.php?id=2342&go=newer). This hardware platform is running the FIONASITE software that provides a web-interface to automate the data review (image viewer), to integrate with the centralized electronic data record for assigning anonymized id's and to forward the data to the central archive.

FAQ
===

Where do you place the FIONA system?
------------------------------------

At UCSD we place FIONA inside the scanner control room. That way we have the fastest transfer rate from the scanner to the machine, which in turn will forward the data to the Data Analysis and Informatics Core (DAIC) at UCSD. The amount of data transferred for each scan is about 150GB. If your site has a sufficiently fast connectivity and you control the firewall configuration, you could place the box anywhere in your institution.
Cautionary note: At UCSD we have a medical center network which is especially secured. That network does not allow direct connections of machines to the scanner. We have to place the FIONA inside the medical center network and allow scp (secure copy) for p-files and DICOM (for image files) connectivity between the scanner and FIONA.

It might happen that we will be scanning in parallel two kids on two MRIs at the same time. Will this be a problem?
-------------------------------------------------------------------------------------------------------------------

We expect this configuration to work (single FIONA connected to 2 or more scanners). Please perform tests at your institution. In case both scanners send data at the same time the transfer speed will be slower (half) but it should not fail. The slow transfer speed can be alleviated by providing a fast connection to at least one of the scanners. Using the 10Gb connection and/or a single switch between scanner and FIONA transfer times are not expected to exceed a couple of minutes - probably the transfer to FIONA is done while the child gets off the table.
Sending the data from FIONA to the DAIC can be done at a later point, please contact us for the details of this setup.

Does the system use Java?
-------------------------

No, it does not.

Does FIONA have any interaction with Active-Directory?
------------------------------------------------------

The system will not provide general purpose site accounts, therefore we hope that a small number of local user accounts are sufficient. Sites will have administrator rights to implement site specific changes to this policy in collaboration with the DAIC.

The IPMI configuration interface does support Active Directory integration for IT administrators. The system can be used to remotely reboot the system and to verify its correct operation.

Who will setup usernames and passwords for accessing the web client?
--------------------------------------------------------------------

Sites will have an administrator account for the setup of web-client users directly from the web-interface. The DAIC will also be able to create accounts remotely if access to the site FIONA is provided to the DAIC.

Does the web client use SSL?
----------------------------

Yes, we are using let’s encrypt (https://letsencrypt.org/) to provide ssl only connections to the FIONA web interface.

What type of Database is used?
------------------------------

We are using flat-file structures in json format for the limited amount of meta data storage required on each machine. Most of the data stored on the machine is binary (DICOM files and vendor specific p-file formats).

Will any automatic security patching of the Operating System be taking place?
-----------------------------------------------------------------------------

FIONA system is running CentOS 7.2 with automatic updates of security relevant patches. This might require system restarts to apply some of the patches that are automatically installed. After a restart the system is expected to be accessible again.

Will subjects be de-identified at any point in the data transfer? If so at what point in the process?
-----------------------------------------------------------------------------------------------------

We are using a centralized participant ID system to identify our subjects. Those GUID’s should be used on the scanner and nothing else to identify ABCD participants. Therefore, in the best of all worlds, we don’t need anonymization as no PII data is entered on the scanner console.

That said, we will scrub a selection of DICOM tags (based on DICOM's E Attribute Confidentiality Profiles) on FIONA as they arrive from the scanner and before they are permanently saved or send off to our central data collection site. We want to be careful with the process of anonymization of private tags as some are needed for data processing (distortion correction). Having a centralized on-site anonymization procedure implemented inside FIONA will help us to improve the overall data quality of our study.

What make\model of device is FIONA?
-----------------------------------

The Flash based Input/Output Network Appliance (FIONA) contains the follow hardware components (Feb 2016):
```
Microway Tower WhisperStation Chassis (Black)
Based on Chenbro tower workstation chassis
Dimensions: 24.4" D x 16.7" H x 8.7" W 
Exposed Drive Bays:
 (3) 5.25" (3.5" bracket available)
 (1) 3.5"
 Internal Drive Bays: (8) 3.5" (hot-swap optional) 
 Two front-mounted USB 2.0 ports
 Two center and one rear cooling fan
(2) Four Drive Hot-Swap SAS/SATA 6Gbps Canister for WhisperStation
 6-Tray 2.5" SAS/SATA 6Gbps Hot-Swap Hard Drive Carrier in One 5.25" bay
 1050W SS-1050XM2 High-Efficiency Power Supply 80PLUS Gold Certified
 
NumberSmasher Intel Xeon Motherboard (X10SRH-CF)
 Supports one Intel Xeon E5-1600/E5-2600 Socket R3 processor
 Intel C612 Express chipset
 Integrated LSI 3008 SAS3 (12Gbps) controller 
 Eight slots for up to 512GB ECC DDR4-2133/1866 memory
 Dual Integrated Intel i350-AM2 Gigabit Ethernet ports
 One PCI-Express 3.0 x16 slot (x8 link)
 One PCI-Express 3.0 x8 slots (x4 links)
 Two PCI-Express 3.0 x8 slots
 One PCI-Express 2.0 x4 slot (x2 link)
 Integrated AST2400 Graphics Controller
 Integrated SATA controller with ten SATA3 6Gb/s ports
 IPMI 2.0 w/ Virtual Media, KVM and Dedicated LAN Support
 1 VGA, 1 COM, 2 Gigabit LAN, 1 IPMI LAN, 2 USB 3.0 ports, 2 USB 2.0 ports 
 Additional USB and Serial Headers
 
Intel Xeon E5-2640v3 Haswell-EP 2.60 GHz Eight Core 22nm CPU with 20MB L3 Cache, 
DDR4-1866, 8.0 GT/sec QPI, 90W Supports Hyper-Threading and Turbo Boost up to 3.4 GHz 
(8) 16GB DDR4 2133 MHz ECC/Registered Memory (Dual Rank, 1.2V)
  (128GB Total Memory @ 1866MHz)
Ultra-Quiet Active Xeon Socket Workstation Heatsink 
(6) 6 TB Seagate Enterprise Capacity 3.5" V4 SATA 6Gbps 512E ST6000NM0024 128MB Cache, 6Gb/s, NCQ, 7200RPM, 2.0 million hours MTBF

(2) 200GB Intel DC S3710 2.5" SATA 6Gbps HET-MLC SSD (20nm)
 SATA 6Gb/s Interface (Supports 3Gb/s)
 MLC Internal Solid State Drive with High Endurance Technology (HET) 
 Endurance Rating (Lifetime Writes): 10 drive writes per day for 5 years, 3.6 PBW 
 Full data path   and Power loss protection; 256 bit AES encryption
 
2 Million Hours Mean Time Before Failure (MTBF)
 Uncorrectable Bit Error Rate (UBER): 1 sector per 10^17 bits read Sustained sequential read: up to 550 MB/s
 Sustained sequential write: up to 300 MB/s
 Random 4KB IOPS: up to 85,000 read; up to 43,000 write Average Latency: 55μs read, 66 μs write  
 
(3) Crucial 500GB MX200 2.5" SATA III MLC NAND SSD
 Solid State Disk (SSD), 1.5 million hours MTBF
  Endurance Rating (Lifetime Writes): 160TBW, 87GB/day for 5 years Read: up to 555 MB/sec (SATA 6Gbps)
   Write: up to 500 MB/sec (SATA 6Gbps)
    4k random read: Up to 100,000 IOPS
     4k random write: Up to 87,000 IOPS
      AES 256-bit Hardware Encryption

LITE-ON Dual Layer 24X DVD/CD Burner (Black) SATA DVD-R/+R: 24X, DVD+RW: 8X, DVD-RW: 6X
CD-R: 48X, CD-RW: 32X
```

Is says that FIONA is not rack mountable. But can it be?
--------------------------------------------------------

The dimensions of FIONA (height: 18’’ with detachable feet, depth: 26’’, width: 9’') would allow it to be rack-mounted side-ways. On its side the machine does fit into a 19'' rack (4 poster) well enough to not impede air flow. There are no rack-mounts or slides that come with the machine nor are there any holes to attach them. The machine can be placed on its side on top of an existing unit. Below is a picture taken in our server room at UCSD. The FIONA system (Microway 5U) is placed on top of a UPS.

Our site requires all scans to be submitted for a clinical read
---------------------------------------------------------------

The ABCD-DAIC provides clinical reads by a board certified radiologist for each ABCD subject and any incidental findings will be reported back to the site PI’s in a timely manner. Sites that mandate an additional local read of the data should be able to provide such a read on de-identified data utilizing the NDAR GUIs.

Is there a network diagram we can use to show how FIONA is integrated?
----------------------------------------------------------------------

You can use one of the following two options for integrating the FIONA system into your site network.

![Network 1](/images/network1.png "Network Layout 1")

![Network 2](/images/network2.png "Network Layout 2")


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
 ├── inbox			// DICOM data placed into this directory will be imported                      
 ├── scanner                     // MPPS file storage received from scanner
 ├── site                        
 │   ├── archive                 // receives incoming DICOM, sorted by StudyInstanceUID
 │   ├── participants            // receives incoming DICOM, sorted by PatientID/Date_Time
 │   └── raw                     // receives incoming DICOM, sorted by Study/Series/
 ├── DAIC                        // files that could be send successfully to the DAIC
 ├── outbox                      // files submitted by the user for send to DAIC
 └── quarantine                  // receives p-files+MD5SUM by scp from scanner + tgz'ed DICOM
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
  "DATADIR": 		"<default projects data directory - (default /data)>"
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

### Web interface:

/var/www/html/ (git clone https://github.com/ABCD-STUDY/FIONASITE.git)

In order to provide secure access to the web-interface the following permission levels are supported:

#### Permission level "abcd-data-entry"

Default user level that allows for ABCD data uploads.

#### User "admin"

Only the "admin" user is able to create new user accounts on the system and change the system setup.

#### Permission level "see-scanner"

Only user that have the permission "see-scanner" will be able to see the current list of scans available on the scanner (Data View: Scanner).

#### Permission level "developer"

Only user that have the permission level "developer" will be able to create new processing buckets (docker container) using the web-interface.

### Server components:

See readme in server directory.


Debug
======

Use a vagrant setup such as https://github.com/ABCD-STUDY/abcd-dev.git for debugging and development (requires developer permissions).

Processing containers inside FIONA use the DAIC Invention system which is docker based and provides a web interface that allows users to define a docker container, change its content and specify for which events the container will be started. The list of events currently supported is:

 * a study arrives
 * a particular series arrived as identified by a classification type

![Invention user interface](/images/docker-interface.png?raw=true "User interface for docker containers on FIONA.")