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

Additionally the root user needs the following 'incrontab -e' entries:

```
/data/ IN_MODIFY /var/www/html/server/bin/updateSystemStatus.sh $@ $#
```