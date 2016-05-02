This directory contains the server scripts, mostly controled by cron-jobs. The directory
should belong to the processing user which will run the cron jobs and write into the
following directories:

```
  server/logs/
  server/.pids/
```

The 'crontab -e' for the processing user should look like this:

```
*/10 * * * * /var/www/html/server/bin/storectl.sh start
*/10 * * * * /usr/bin/python2.7 /var/www/html/server/bin/processSingleFile.py start
0 */2 * * * /usr/bin/nice -n 3 /var/www/html/server/bin/sendFiles.sh
*/1 * * * * /usr/bin/nice -n 3 /var/www/html/server/bin/heartbeat.sh
*/1 * * * * /var/www/html/server/bin/detectStudyArrival.sh
*/1 * * * * sleep 15; /var/www/html/server/bin/detectStudyArrival.sh
*/1 * * * * sleep 30; /var/www/html/server/bin/detectStudyArrival.sh
*/1 * * * * sleep 45; /var/www/html/server/bin/detectStudyArrival.sh
*/1 * * * * /var/www/html/server/bin/moveFromScanner.sh
*/1 * * * * sleep 15; /var/www/html/server/bin/moveFromScanner.sh
*/1 * * * * sleep 30; /var/www/html/server/bin/moveFromScanner.sh
*/1 * * * * sleep 45; /var/www/html/server/bin/moveFromScanner.sh
*/1 * * * * /var/www/html/server/bin/mppsctl.sh start
```

The 'incrontab -e' for the processing user should look like this:

```
/data/scanner/ IN_CREATE /var/www/html/server/bin/newStudyOnScanner.sh $@ $#
/var/www/html/php/inventions IN_CREATE,IN_MOVED_TO /var/www/html/server/bin/createMachine.sh $@ $#
```

Additionally the root user needs the following 'incrontab -e' entries:

```
/data/ IN_MODIFY /var/www/html/server/bin/updateSystemStatus.sh $@ $#
```

## Files of interest

Each DICOM files that arrives on this system is parsed by a daemon process implemented as processSingleFile.py. The process will classify files on a series level and create a summary file as a json next to the image series. The particular rules used by processSingleFile.py are stored in classifyRules.json. 