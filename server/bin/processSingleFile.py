#!/usr/bin/env python
"""
Create a daemon process that listens to send messages and reads a DICOM file,
extracts the header information and creates a Study/Series symbolic link structure.
"""

import sys, os, time, atexit, stat, tempfile, copy
import dicom, json, re, logging, logging.handlers, threading
from signal import SIGTERM
from dicom.filereader import InvalidDicomError


class Daemon:
        """
        A generic daemon class.
        
        Usage: subclass the Daemon class and override the run() method
        """
        def __init__(self, pidfile, stdin='/dev/null', stdout='/dev/null', stderr='/dev/null'):
                    self.stdin    = stdin
                    self.stdout   = stdout
                    self.stderr   = stderr
                    self.pidfile  = pidfile
                    self.pipename = '/tmp/.processSingleFilePipe'
                    
        def daemonize(self):
                    """
                    do the UNIX double-fork magic, see Stevens' "Advanced
                    Programming in the UNIX Environment" for details (ISBN 0201563177)
                    http://www.erlenstar.demon.co.uk/unix/faq_2.html#SEC16
                    """
                    try:
                                pid = os.fork()
                                if pid > 0:
                                            # exit first parent
                                            sys.exit(0)
                    except OSError, e:
                                sys.stderr.write("fork #1 failed: %d (%s)\n" % (e.errno, e.strerror))
                                sys.exit(1)

                    # decouple from parent environment
                    os.chdir("/")
                    os.setsid()
                    os.umask(0)
                
                    # do second fork
                    try:
                                pid = os.fork()
                                if pid > 0:
                                            # exit from second parent
                                            sys.exit(0)
                    except OSError, e:
                                sys.stderr.write("fork #2 failed: %d (%s)\n" % (e.errno, e.strerror))
                                sys.exit(1)

                    # redirect standard file descriptors
                    sys.stdout.flush()
                    sys.stderr.flush()
                    #si = file(self.stdin, 'r')
                    #so = file(self.stdout, 'a+')
                    #se = file(self.stderr, 'a+', 0)
                    #os.dup2(si.fileno(), sys.stdin.fileno())
                    #os.dup2(so.fileno(), sys.stdout.fileno())
                    #os.dup2(se.fileno(), sys.stderr.fileno())

                    # write pidfile
                    atexit.register(self.delpid)
                    pid = str(os.getpid())
                    file(self.pidfile,'w+').write("%s\n" % pid)
                    
        def delpid(self):
                    try:
                            os.remove(self.pidfile)
                    except:
                            pass
        def delpipe(self):
                    try:
                            os.remove(self.pipename)
                    except:
                            pass
        def start(self):
                    """
                    Start the daemon
                    """
                    # Check for a pidfile to see if the daemon already runs
                    try:
                                pf = file(self.pidfile,'r')
                                pid = int(pf.read().strip())
                                pf.close()
                    except IOError:
                                pid = None

                    if pid:
                            message = "pidfile %s already exist. Daemon already running?\n"
                            sys.stderr.write(message % self.pidfile)
                            # Maybe the pid file exits - but the process is not running (crashed).
                            try:
                                    os.kill(pid, 0)
                            except OSError:
                                    # The process does not exist, forget the pid and wait to be restarted..
                                    pid = None
                                    os.remove(self.pidfile)

                            sys.exit(1)
                            
                    # Start the daemon
                    print(' start the daemon')
                    self.daemonize()
                    print ' done'
                    self.run()

        def send(self,arg):
                    """
                    Send a message to the daemon via pipe
                    """
                    # open a named pipe and write to it
                    if stat.S_ISFIFO(os.stat(self.pipename).st_mode):
                            try:
                                    wd = open(self.pipename, 'w')
                                    wd.write(arg + "\n")
                                    wd.flush()
                                    wd.close()
                            except IOError:
                                    print 'Error: could not open the pipe %s' % self.pipename
                                    logging.error('Error: could not open the pipe %s' % self.pipename)
                    else:
                            sys.stderr.write(self.pipename)
                            sys.stderr.write("Error: the connection to the daemon does not exist\n")
                            logging.error('Error: the connection to the daemon does not exist')
                            sys.exit(1)

        def stop(self):
                    """
                    Stop the daemon
                    """
                    # Get the pid from the pidfile
                    try:
                            pf = file(self.pidfile,'r')
                            pid = int(pf.read().strip())
                            pf.close()
                    except IOError:
                            pid = None
                            
                    if not pid:
                            message = "pidfile %s does not exist. Daemon not running?\n"
                            sys.stderr.write(message % self.pidfile)
                            logging.error(message % self.pidfile)
                            return # not an error in a restart
                                
                    # Try killing the daemon process
                    try:
                                while 1:
                                            os.kill(pid, SIGTERM)
                                            time.sleep(0.1)
                    except OSError, err:
                                err = str(err)
                                if err.find("No such process") > 0:
                                            if os.path.exists(self.pidfile):
                                                        os.remove(self.pidfile)
                                                        os.remove(self.pipename)
                                else:
                                            print str(err)
                                            logging.error(str(err))
                                            sys.exit(1)
                                                        
        def restart(self):
                    """
                    Restart the daemon
                    """
                    self.stop()
                    self.start()
                    
        def run(self):
                    """
                    You should override this method when you subclass Daemon. It will be called after the process has been
                    daemonized by start() or restart().
                    """


class ProcessSingleFile(Daemon):
        def init(self):
                    self.classify_rules = 0
                    self.rulesFile = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, 'classifyRules.json'])
                    if os.path.exists(self.rulesFile):
                            with open(self.rulesFile,'r') as f:
                                    self.classify_rules = json.load(f)
                            # we should resolve dependencies between rules, this could introduce a problem with termination,
                            # Todo: add a check to the program to make sure that the rules are ok
                            # we need to be able to reference a specific rule (id tag?)
                            self.classify_rules = self.resolveClassifyRules(self.classify_rules)
                            
                    else:
                            print "Warning: no %s/classifyRules.json file could be found" % os.path.dirname(os.path.abspath(__file__))

        def resolveClassifyRules(self, classify_rules ):
                # add recursively rules back until no more changes can be done
                for attempt in range(100):
                        didChange = False
                        for rule in range(len(classify_rules)):
                                for entry in range(len(classify_rules[rule]['rules'])):
                                        r = classify_rules[rule]['rules'][entry]
					negate = ("notrule" in r)
					ruleornotrule = "rule"
					if negate:
						ruleornotrule = "notrule"
                                        if ruleornotrule in r:
                                                # find the rule with that ID
                                                findID = False
                                                for rule2 in range(len(classify_rules)):
                                                        if "id" in classify_rules[rule2] and classify_rules[rule2]['id'] == r[ruleornotrule]:
                                                                # found the id this rule refers to
                                                                # copy the rules and append instead of the reference rule
                                                                findID = True
                                                                classify_rules[rule]['rules'].remove(r)
                                                                cr = copy.deepcopy(classify_rules[rule2]['rules'])
                                                                if negate:
                                                                        # add the negate flag to this rule
                                                                        for i in cr:
										i['negate'] = "yes"
                                                                classify_rules[rule]['rules'].extend(cr)
                                                                didChange = True
                                                if not findID:
                                                        print "Error: could not find a rule with ID %s" % r[ruleornotrule]
                                                        logging.info("Error: could not find a rule with ID %s" % r[ruleornotrule])
                                                        continue
                                
                        if not didChange:
                                break
                return classify_rules
                
	def resolveValue(self,tag,dataset,data):
		# a value can be a tag (array of string length 1) or a tag (array of string length 2) or a specific index into a tag (array of string length 3)
		v = ''
		taghere = True
		if len(tag) == 1:
			if not tag[0] in data:
				if not tag[0] in dataset:
					taghere = False
				else:
					v = dataset[tag[0]]
			else:
				v = data[tag[0]]
		elif len(tag) == 2:
			if not ( int(tag[0],0), int(tag[1],0) ) in dataset:
				taghere = False
			else:
				v = dataset[int(tag[0],0), int(tag[1],0)].value
		elif len(tag) == 3:
			if not ( int(tag[0],0), int(tag[1],0) ) in dataset:
				taghere = False
			else:
				v = dataset[int(tag[0],0), int(tag[1],0)].value[int(tag[2],0)]
		else:
			raise ValueError('Error: tag with unknown structure, should be 1, 2, or 3 entries in array')
			print("Error: tag with unknown structure, should be 1, 2, or 3 entries in array")
                        logging.error("Error: tag with unknown structure, should be 1, 2, or 3 entries in array")
		return taghere, v
			
        def classify(self,dataset,data,classifyTypes):
                # read the classify rules
                if self.classify_rules == 0:
                        print "Warning: no classify rules found in %s, ClassifyType tag will be empty" % self.rulesFile
                        logging.info("Warning: no classify rules found in %s, ClassifyType tag will be empty" % self.rulesFile)
                        return classifyTypes
                for rule in range(len(self.classify_rules)):
                        t = self.classify_rules[rule]['type']
			# if we check on the series level all rules have to be true for every image in the series (remove at the end)
			seriesLevelCheck = False
			if ('check' in self.classify_rules[rule]) and (self.classify_rules[rule]['check'] == "SeriesLevel"):
				seriesLevelCheck = True
                        ok = True
                        for entry in range(len(self.classify_rules[rule]['rules'])):
                                r = self.classify_rules[rule]['rules'][entry]
				# we could have a negated rule here
				def isnegate(x): return x
				if ('negate' in r) and (r['negate'] == "yes"):
					def isnegate(x): return not x
                                # check if this regular expression matches the current type t
                                taghere = True
				try:
					taghere, v = self.resolveValue(r['tag'],dataset,data)
				except ValueError:
					continue
				# the 'value' could in some cases be a tag, that would allow for relative comparisons in the classification
				v2 = r['value']
				taghere2 = True
				try:
					taghere2, v2 = self.resolveValue(v2,dataset,data)
				except ValueError:
					v2 = r['value']
				if taghere2 == False:
					v2 = r['value']

                                if not "operator" in r:
                                        r["operator"] = "regexp"  # default value
                                op = r["operator"]
                                if op == "notexist":
					if isnegate(tagthere):
                                           ok = False
                                           break
                                elif  op == "regexp":
                                        pattern = re.compile(v2)
					vstring = v
					if isinstance(v, (int, float)):
						#print "v is : ", v, " and v2 is: ", v2
						vstring = str(v)
                                        if isnegate(not pattern.search(vstring)):
                                           # this pattern failed, fail the whole type and continue with the next
                                           ok = False
                                           break
                                elif op == "==":
					try:
                                          if isnegate(not float(v2) == float(v)):
                                             ok = False
                                             break
					except ValueError:
					  pass
                                elif op == "!=":
					try:
                                          if isnegate(not float(v2) != float(v)):
                                             ok = False
                                             break
					except ValueError:
					  pass
                                elif op == "<":
                                        try:
                                          if isnegate(not float(v2) > float(v)):
                                             ok = False
                                             break
					except ValueError:
					  pass
                                elif op == ">":
                                        try:
                                          if isnegate(not float(v2) < float(v)):
                                             ok = False
                                             break
					except ValueError:
					  pass
                                elif op == "exist":
					if isnegate(not tagthere):
                                           ok = False
                                           break
				elif op == "contains":
					if isnegate(v2 not in v):
						ok = False
						break
                                elif op == "approx":
                                        # check each numerical entry if its close to a specific value
                                        approxLevel = 1e-4
                                        if 'approxLevel' in r:
                                                approxLevel = float(r['approxLevel'])
					if (not isinstance(v, list)) and (not isinstance( v, (int, float) )):
						# we get this if there is no value in v, fail in this case
						ok = False
						break
                                        if isinstance( v, list ) and isinstance(v2, list) and len(v) == len(v2):
                                                for i in range(len(v)):
                                                        if isnegate(abs(float(v[i])-float(v2[i])) > approxLevel):
								#print "approx does not fit here"
                                                                ok = False
                                                                break
                                        if isinstance( v, (int, float) ):
                                                if isnegate(abs(float(v)-float(v2)) > approxLevel):
                                                        ok = False
                                                        break
                                else:
                                        ok = False
                                        break

                        # ok nobody failed, this is it
                        if ok:
				classifyTypes = classifyTypes + list(set([t]) - set(classifyTypes))
			if seriesLevelCheck and not ok and (t in classifyTypes):
				classifyTypes = [y for y in classifyTypes if y != t]
                return classifyTypes
                                
        def run(self):
                try:
                        os.mkfifo(self.pipename)
                        atexit.register(self.delpipe)
                except OSError:
                        print 'OSERROR on creating the named pipe %s' % self.pipename
                        logging.error('OSERROR on creating the named pipe %s' % self.pipename)
                        pass
                try:
                        rp = open(self.pipename, 'r')
                except OSError:
                        print 'Error: could not open named pipe for reading commands'
                        logging.error('Error: could not open named pipe for reading commands')
                        sys.exit(1)
                        
                while True:
                        response = rp.readline()[:-1]
                        if not response:
                                time.sleep(0.1)
                                continue
                        else:
                                responses = response.split(',')
                                if len(responses) != 5:
                                        if len(responses) == 1:
                                                responses = [ "unknown", "unknown", "unknown", os.path.dirname(responses[0]), os.path.basename(responses[0]) ]
                                        else:
                                                print 'Error: expected 5 arguments in line from pipe separated by commas'
                                                logging.error('Error: expected 5 arguments in line from pipe separated by commas but got %s' % response)
                                                continue
                                aetitlecaller = responses[0][1:-1]
                                aetitlecalled = responses[1][1:-1]
                                callerip      = responses[2]
                                dicomdir      = responses[3]
                                dicomfile     = responses[4]
                                response      = ''.join([dicomdir, os.path.sep, dicomfile])
                                try:
                                        dataset = dicom.read_file(response)
                                except IOError:
                                        print("Could not find file:", response)
                                        logging.error('Could not find file: %s' % response)
                                        continue
                                except InvalidDicomError:
                                        print("Not a DICOM file: ", response)
                                        logging.error('Not a DICOM file: %s' % response)
                                        continue
                                arriveddir = '/data/site/.arrived'
                                if not os.path.exists(arriveddir):
                                        os.makedirs(arriveddir)
                                # write a touch file for each image of a series (to detect series arrival)
                                try:
                                        arrivedfile = os.path.join(arriveddir, ''.join([ aetitlecaller, " ", aetitlecalled, " ", callerip, " ", dataset.StudyInstanceUID, " ", dataset.SeriesInstanceUID ] ))
                                except AttributeError:
                                        logging.error('Did not find Study or Series instance UID for arrivedfile')
                                        continue
                                with open(arrivedfile, 'a'):
                                        os.utime(arrivedfile, None)
                                patientdir = '/data/site/participants'
                                if not os.path.exists(patientdir):
                                        os.makedirs(patientdir)
                                outdir = '/data/site/raw'
                                if not os.path.exists(outdir):
                                        os.makedirs(outdir)
                                infile = os.path.basename(response)
                                fn = os.path.join(outdir, dataset.StudyInstanceUID, dataset.SeriesInstanceUID)
                                if not os.path.exists(fn):
                                        os.makedirs(fn)
                                        if not os.path.exists(fn):
                                                print "Error: creating path ", fn, " did not work"
                                                logging.error('Error: creating path %s did not work' % fn)
                                fn2 = os.path.join(fn, dataset.SOPInstanceUID)
                                if not os.path.isfile(fn2):
                                  os.symlink(response, fn2)
                                if dataset.PatientID:
                                        patdir = os.path.join(patientdir, dataset.PatientID)
                                        if not os.path.exists(patdir):
                                                os.makedirs(patdir)
                                        studydir = os.path.join(patdir, ''.join([dataset.StudyDate, "_", dataset.StudyTime]))
                                        if not os.path.islink( studydir ):
                                                os.symlink( os.path.join(outdir, dataset.StudyInstanceUID), studydir )
                                #else:
                                #  continue # don't  do anything because the file exists already
                                # lets store some data in a series specific file
                                fn3 = os.path.join(outdir, dataset.StudyInstanceUID, dataset.SeriesInstanceUID) + ".json"
                                data = { 'IncomingConnection': { 'AETitleCaller': aetitlecaller, 'AETitleCalled': aetitlecalled, 'CallerIP': callerip } }
                                try:
                                        data['Manufacturer'] = dataset.Manufacturer
                                except:
                                        pass
                                try:
                                        data['Modality'] = dataset.Modality
                                except:
                                        pass
                                try:
                                        data['StudyInstanceUID'] = dataset.StudyInstanceUID
                                except:
                                        pass
                                try:
                                        data['SeriesInstanceUID'] = dataset.SeriesInstanceUID
                                except:
                                        pass
                                try:
                                        data['PatientID'] = dataset.PatientID
                                except:
                                        pass
                                try:
                                        data['PatientName'] = dataset.PatientName
                                except:
                                        pass
                                try:
                                        data['StudyDate'] = dataset.StudyDate
                                except:
                                        pass
                                try:
                                        data['StudyDescription'] = dataset.StudyDescription
                                except:
                                        pass
                                try:
                                        data['SeriesDescription'] = dataset.SeriesDescription
                                except:
                                        pass
                                try:
                                        data['EchoTime'] = str(dataset.EchoTime)
                                except:
                                        pass
                                try:
                                        data['RepetitionTime'] = str(dataset.RepetitionTime)
                                except:
                                        pass
                                try:
                                        data['SeriesNumber'] = str(dataset.SeriesNumber)
                                except:
                                        pass
                                try:
                                        data['InstanceNumber'] = str(dataset.InstanceNumber)
                                except:
                                        pass
                                try:
                                        data['SliceThickness'] = str(dataset[0x18,0x50].value)
                                except:
                                        pass
                                try:
                                        data['ImageType'] = str(dataset[0x08,0x08].value)
                                except:
                                        pass
                                try:
                                        data['SliceSpacing'] = str(dataset[0x18,0x88].value)
                                except:
                                        pass
                                try:
                                        data['ScanningSequence'] = str(dataset[0x18,0x20].value)
                                except:
                                        pass
                                try:
                                        data['PulseSequenceName'] = str(dataset[0x19,0x109c].value)
                                except:
                                        pass
                                try:
                                        data['SliceLocation'] = str(dataset[0x20,0x1041].value)
                                except:
                                        pass
                                try:
                                        data['AccessionNumber'] = str(dataset[0x08,0x50].value)
                                except:
                                        pass
                                try:
                                        data['StudyTime'] = str(dataset[0x08,0x30].value)
                                except:
                                        pass
                                data['NumFiles'] = str(0)
                                try:
                                         data['Private0019_10BB'] = str(dataset[0x0019,0x10BB].value)
                                except:
                                        pass
                                try:
                                        data['Private0043_1039'] = dataset[0x0043,0x1039].value
                                except:
                                        pass
                                        
                                # keep the slice location (use the maximum values for all slice locations)
                                currentSliceLocation = None
                                try:
                                        currentSliceLocation = data['SliceLocation']
                                except:
                                        pass
                                if os.path.exists(fn3):
                                        with open(fn3, 'r') as f:
                                                data = json.load(f)

                                if currentSliceLocation != None:
                                        try:
                                                if float(data['SliceLocation']) > float(currentSliceLocation):
                                                        data['SliceLocation'] = currentSliceLocation;
                                        except:
                                                pass
                                if not 'ClassifyType' in data:
                                        data['ClassifyType'] = []
                                data['StudyInstanceUID'] = dataset.StudyInstanceUID
                                data['NumFiles'] = str( int(data['NumFiles']) + 1 )
                                # add new types as they are found (this will create all type that map to any of the images in the series)
				data['ClassifyType'] = self.classify(dataset, data, data['ClassifyType'])
                                #data['ClassifyType'] = data['ClassifyType'] + list(set(self.classify(dataset, data)) - set(data['ClassifyType']))
                                with open(fn3,'w') as f:
                                        json.dump(data,f,indent=2,sort_keys=True)
                rp.close()

# There are two files that make this thing work, one is the .pid file for the daemon
# the second is the named pipe in /tmp/.processSingleFile
#  Hauke,    July 2015               
if __name__ == "__main__":
        pidfilename = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, '/../.pids/processSingleFile.pid' ])
        p = os.path.abspath(pidfilename)
        if not os.path.exists(p):
                pidfilename = tempfile.gettempdir() + '/processSingleFile.pid'
        lfn = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, '/../logs/processSingleFile.log' ])  
        #log = logging.handlers.RotatingFileHandler(lfn, 'a', 10*1024*1024, backupCount=5)
        #log.setFormatter(logging.Formatter('%(levelname)s:%(asctime)s: %(message)s'))
        logging.basicConfig(filename=lfn,format='%(levelname)s:%(asctime)s: %(message)s',level=logging.DEBUG)
        daemon = ProcessSingleFile(pidfilename)
        daemon.init()
        if len(sys.argv) == 2:
                if 'start' == sys.argv[1]:
                        try:
                                daemon.start()
                        except:
                                print "Error: could not create processing daemon: %s %s %s" % (sys.exc_info()[0] ,sys.exc_info()[1], sys.exc_info()[2])
                                logging.error("Error: could not create processing daemon: %s" % sys.exc_info()[0])
                                sys.exit(-1)
                elif 'stop' == sys.argv[1]:
                        daemon.stop()
                elif 'restart' == sys.argv[1]:
                        daemon.restart()
                elif 'test' == sys.argv[1]:
                        r = daemon.resolveClassifyRules( daemon.classify_rules )
                        print json.dumps(r, sort_keys=True, indent=2)
                else:
                        print "Unknown command"
                        logging.info("Unknown command")
                        sys.exit(2)
                sys.exit(0)
        elif len(sys.argv) == 3:
                if 'send' == sys.argv[1]:
                        daemon.send(sys.argv[2])
                sys.exit(0)
        else:
                print "Process DICOM files fast using a daemon process that creates study/series directories with symbolic links."
                print "Use 'start' to start the daemon in the background. Send file names for processing using 'send'."
                print "Test the rules by running 'test' which will print out the imported rules:"
                print "   python2.7 %s test" % sys.argv[0]
                print ""
                print "Usage: %s start|stop|restart|send|test" % sys.argv[0]
                print ""
                print "For a simple test send a DICOM directory by:"
                print "  find <dicomdir> -type f -print | grep -v .json  | xargs -i echo \"/path/to/input/{}\" >> /tmp/.processSingleFilePipe"
                print ""
                sys.exit(2)
