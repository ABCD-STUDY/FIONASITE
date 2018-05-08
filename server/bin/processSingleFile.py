#!/usr/bin/env python
"""
Create a daemon process that listens to send messages and reads a DICOM file,
extracts the header information and creates a Study/Series symbolic link structure.

The parser for the Siemens CSA header have been adapted from 
   https://scion.duhs.duke.edu/svn/vespa/tags/0_1_0/libduke_mr/util_dicom_siemens.py
"""

import sys, os, time, atexit, stat, tempfile, copy, traceback
import dicom, json, re, logging, logging.handlers, threading, string
import struct
from signal import SIGTERM
from dicom.filereader import InvalidDicomError

ASSERTIONS_ENABLED = False

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
                    self.projname = ''
                    self.datadir = '/data'
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

                            # remove the pipe if it exists
                            if os.path.exists(self.pipename):
                                    os.remove(self.pipename)

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

        def _null_truncate(self, s):
                """Given a string, returns a version truncated at the first '\0' if
                there is one. If not, the original string is returned."""
                i = s.find(chr(0))
                if i != -1:
                        s = s[:i]
                        
                return s


        def _scrub(self, item):
                """Given a string, returns a version truncated at the first '\0' and
                stripped of leading/trailing whitespace. If the param is not a string,
                it is returned unchanged."""
                if isinstance(item, basestring):
                        return self._null_truncate(item).strip()
                else:
                        return item


        def _get_chunks(self, tag, index, format, little_endian=True):
                """Given a CSA tag string, an index into that string, and a format
                specifier compatible with Python's struct module, returns a tuple
                of (size, chunks) where size is the number of bytes read and
                chunks are the data items returned by struct.unpack(). Strings in the
                list of chunks have been run through _scrub().
                """
                # The first character of the format string indicates endianness.
                format = ('<' if little_endian else '>') + format
                size = struct.calcsize(format)
                chunks = struct.unpack(format, tag[index:index + size])

                chunks = [self._scrub(item) for item in chunks]
        
                return (size, chunks)

        def _my_assert(self, expression):
                if ASSERTIONS_ENABLED:
                        assert(expression)

        def _get(self, dataset, tag, default=None):
                """Returns the value of a dataset tag, or the default if the tag isn't
                in the dataset.
                PyDicom datasets already have a .get() method, but it returns a
                dicom.DataElement object. In practice it's awkward to call dataset.get()
                and then figure out if the result is the default or a DataElement,
                and if it is the latter _get the .value attribute. This function allows
                me to avoid all that mess.
                It is also a workaround for this bug (which I submitted) which should be
                fixed in PyDicom > 0.9.3:
                http://code.google.com/p/pydicom/issues/detail?id=72
                Also for this bug (which I submitted) which should be
                fixed in PyDicom > 0.9.4-1:
                http://code.google.com/p/pydicom/issues/detail?id=88
                """
                return default if tag not in dataset else dataset[tag].value

        def _parse_csa_header(self, tag, little_endian = True):
                """The CSA header is a Siemens private tag that should be passed as
                a string. Any of the following tags should work: (0x0029, 0x1010),
                (0x0029, 0x1210), (0x0029, 0x1110), (0x0029, 0x1020), (0x0029, 0x1220),
                (0x0029, 0x1120).
                
                The function returns a dictionary keyed by element name.
                """
                # Let's have a bit of fun, shall we? A Siemens CSA header is a mix of
                # binary glop, ASCII, binary masquerading as ASCII, and noise masquerading
                # as signal. It's also undocumented, so there's no specification to which
                # to refer.
                
                # The format is a good one to show to anyone who complains about XML being
                # verbose or hard to read. Spend an afternoon with this and XML will
                # look terse and read like a Shakespearean sonnet.
                
                # The algorithm below is a translation of the GDCM project's
                # CSAHeader::LoadFromDataElement() inside gdcmCSAHeader.cxx. I don't know
                # how that code's author figured out what's in a CSA header, but the
                # code works.
                
                # I added comments and observations, but they're inferences. I might
                # be wrong. YMMV.
                
                # Some observations --
                # - If you need to debug this code, a hexdump of the tag data will be
                #   your best friend.
                # - The data in the tag is a list of elements, each of which contains
                #   zero or more subelements. The subelements can't be further divided
                #   and are either empty or contain a string.
                # - Everything begins on four byte boundaries.
                # - This code will break on big endian data. I don't know if this data
                #   can be big endian, and if that's possible I don't know what flag to
                #   read to indicate that. However, it's easy to pass an endianness flag
                #   to _get_chunks() should the need to parse big endian data arise.
                # - Delimiters are thrown in here and there; they are 0x4d = 77 which is
                #   ASCII 'M' and 0xcd = 205 which has no ASCII representation.
                # - Strings in the data are C-style NULL terminated.
                
                # I sometimes read delimiters as strings and sometimes as longs.
                DELIMITERS = ("M", "\xcd", 0x4d, 0xcd)

                # This dictionary of elements is what this function returns
                elements = { }

                # I march through the tag data byte by byte (actually a minimum of four
                # bytes at a time), and current points to my current position in the tag
                # data.
                current = 0

                # The data starts with "SV10" followed by 0x04, 0x03, 0x02, 0x01.
                # It's meaningless to me, so after reading it, I discard it.
                size, chunks = self._get_chunks(tag, current, "4s4s")
                current += size

                self._my_assert(chunks[0] == "SV10")
                self._my_assert(chunks[1] == "\4\3\2\1")

                # get the number of elements in the outer list
                size, chunks = self._get_chunks(tag, current, "L")
                current += size
                element_count = chunks[0]
                
                # Eat a delimiter (should be 0x77)
                size, chunks = self._get_chunks(tag, current, "4s")
                current += size
                self._my_assert(chunks[0] in DELIMITERS)

                for i in range(element_count):
                        # Each element looks like this:
                        # - (64 bytes) Element name, e.g. ImagedNucleus, NumberOfFrames,
                        #   VariableFlipAngleFlag, MrProtocol, etc. Only the data up to the
                        #   first 0x00 is important. The rest is helpfully populated with
                        #   noise that has enough pattern to make it look like something
                        #   other than the garbage that it is.
                        # - (4 bytes) VM
                        # - (4 bytes) VR
                        # - (4 bytes) syngo_dt
                        # - (4 bytes) # of subelements in this element (often zero)
                        # - (4 bytes) a delimiter (0x4d or 0xcd)
                        size, chunks = self._get_chunks(tag, current,
                                                   "64s" + "4s" + "4s" + "4s" + "L" + "4s")
                        current += size

                        name, vm, vr, syngo_dt, subelement_count, delimiter = chunks
                        self._my_assert(delimiter in DELIMITERS)

                        # The subelements hold zero or more strings. Those strings are stored
                        # temporarily in the values list.
                        values = [ ]
                        
                        for j in range(subelement_count):
                                # Each subelement looks like this:
                                # - (4 x 4 = 16 bytes) Call these four bytes A, B, C and D. For
                                #   some strange reason, C is always a delimiter, while A, B and
                                #   D are always equal to one another. They represent the length
                                #   of the associated data string.
                                # - (n bytes) String data, the length of which is defined by
                                #   A (and A == B == D).
                                # - (m bytes) Padding if length is not an even multiple of four.
                                size, chunks = self._get_chunks(tag, current, "4L")
                                current += size
                                
                                self._my_assert(chunks[0] == chunks[1])
                                self._my_assert(chunks[1] == chunks[3])
                                self._my_assert(chunks[2] in DELIMITERS)
                                length = chunks[0]

                                # get a chunk-o-stuff, length indicated by code above.
                                # Note that length can be 0.
                                size, chunks = self._get_chunks(tag, current, "%ds" % length)
                                current += size
                                if chunks[0]:
                                        values.append(chunks[0])
                                        
                                # If we're not at a 4 byte boundary, move.
                                # Clever modulus code below swiped from GDCM
                                current += (4 - (length % 4)) % 4

                        # The value becomes a single string item (possibly "") or a list
                        # of strings
                        if len(values) == 0:
                                values = ""
                        if len(values) == 1:
                                values = values[0]
                        
                        self._my_assert(name not in elements)
                        elements[name] = values

                return elements
			
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
                                        if not isinstance(v, str):
                                                vstring = str(v)
					#if isinstance(v, (int, float)):
					#	#print "v is : ", v, " and v2 is: ", v2
					#	vstring = str(v)
                                        try:
                                                if isnegate(not pattern.search(vstring)):
                                                        # this pattern failed, fail the whole type and continue with the next
                                                        ok = False
                                                        break
                                        except TypeError:
                                                print ("pattern: %s, vstring: %s (of type: %s)" % (v2, vstring, type(str(vstring)).__name__))
                                                print ("%s" % pattern.search(vstring))
                                                
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
                        
                # remove any zero bytes from the filename
                _split = re.compile(r'[\0%s]' % re.escape(''.join([os.path.sep, os.path.altsep or ''])))
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
                                aetitlecaller = responses[0]
                                aetitlecaller = aetitlecaller.replace('"','')
                                aetitlecaller = aetitlecaller.replace(' ','')
                                aetitlecaller = aetitlecaller.replace('\\','')
                                aetitlecalled = responses[1]
                                aetitlecalled = aetitlecalled.replace('"','')
                                aetitlecalled = aetitlecalled.replace(' ','')
                                aetitlecalled = aetitlecalled.replace('\\','')
                                callerip      = responses[2]
                                if callerip == '':
                                        callerip = "0.0.0.0"
                                dicomdir      = responses[3]
                                # the dicomdir will encode for the current project
                                # if we are in abcd the path should look like /data/site/archive/<something>
                                # if we are in another project the path looks like /dataPCGC/site/archive/<something>
                                datadir = self.datadir
                                logging.info(dicomdir)
                                #print 'DEBUG: dicomdir: ', dicomdir
                                #print 'DEBUG: datadir: ', datadir
                                #print 'DEBUG: self.projname ', self.projname
                                #print 'DEBUG: self.pipename ', self.pipename

                                dicomfile     = responses[4]
                                response      = ''.join([dicomdir, os.path.sep, dicomfile])
                                try:
                                        dataset = dicom.read_file(response)
                                except OSError:
                                        print("Could not access file:", response)
                                        logging.error('Could not access file: %s' % response)
                                        continue                                        
                                except IOError:
                                        print("Could not find file:", response)
                                        logging.error('Could not find file: %s' % response)
                                        continue
                                except InvalidDicomError:
                                        print("Not a DICOM file: ", response)
                                        logging.error('Not a DICOM file: %s' % response)
                                        continue
                                # Ignore secondary captures etc., we don't need them and they could contain
                                # patient information burned in.
                                try:
                                        if dataset.Modality != "MR":
                                                # We want to remove this image again, don't keep it on our drive.
                                                # Even better would be to not accept them in storescp.
                                                logging.error('Non-MR modality DICOM image (%s) detected in %s, file will be removed' % (dataset.Modality, response))
                                                try:
                                                        os.remove(response)
                                                except OSError:
                                                        pass
                                                continue
                                except:
                                        pass
                                # if we have a Siemens file get the CSA header structure as well

                                ptag_img = { }
                                ptag_ser = { }

                                # (0x0029, 0x__10) is one of several possibilities
                                # - SIEMENS CSA NON-IMAGE, CSA Data Info
                                # - SIEMENS CSA HEADER, CSA Image Header Info
                                # - SIEMENS CSA ENVELOPE, syngo Report Data
                                # - SIEMENS MEDCOM HEADER, MedCom Header Info
                                # - SIEMENS MEDCOM OOG, MedCom OOG Info (MEDCOM Object Oriented Graphics)
                                # Pydicom identifies it as "CSA Image Header Info"
                                for tag in ( (0x0029, 0x1010), (0x0029, 0x1210), (0x0029, 0x1110) ):
                                        tag_data = self._get(dataset, tag, None)
                                        if tag_data:
                                                break
                                                
                                if tag_data:
                                        try:
                                                ptag_img = self._parse_csa_header(tag_data)
                                        except:
                                                ptag_img = None
                                                pass

                                # [IDL] Access the SERIES Shadow Data
                                # [PS] I don't know what makes this "shadow" data.
                                for tag in ( (0x0029, 0x1020), (0x0029, 0x1220), (0x0029, 0x1120) ):
                                        tag_data = self._get(dataset, tag, None)
                                        if tag_data:
                                                break
                                        
                                if tag_data:
                                        try:
                                                ptag_ser = self._parse_csa_header(tag_data)
                                        except:
                                                ptag_ser = None
                                                pass

                                arriveddir = datadir + '/site/.arrived'
                                #print 'DEBUG: arriveddir: ', arriveddir
                                if not os.path.exists(arriveddir):
                                        os.makedirs(arriveddir)
                                # write a touch file for each image of a series (to detect series arrival)
                                # make sure that the touch file name does not contain double quotes or back-slash characters, or null bytes
                                try:
                                        arrivedfile = os.path.join(arriveddir, _split.sub('', ''.join([ aetitlecaller, " ", aetitlecalled, " ", callerip, " ", dataset.StudyInstanceUID, " ", dataset.SeriesInstanceUID ] )))
                                except AttributeError:
                                        logging.error('Did not find Study or Series instance UID for arrivedfile')
                                        continue
                                with open(arrivedfile, 'a'):
                                        os.utime(arrivedfile, None)
                                patientdir = datadir + '/site/participants'
                                #print 'DEBUG: patientdir: ', patientdir
                                if not os.path.exists(patientdir):
                                        os.makedirs(patientdir)
                                outdir = datadir + '/site/raw'
                                #print 'DEBUG: outdir: ', outdir
                                if not os.path.exists(outdir):
                                        os.makedirs(outdir)
                                        os.chmod(outdir, 0o777)
                                infile = os.path.basename(response)
                                fn = os.path.join(outdir, dataset.StudyInstanceUID, dataset.SeriesInstanceUID)
                                if not os.path.exists(fn):
                                        try:
                                                os.makedirs(fn)
                                        except OSError:
                                                print "Error: no permissions to create this path ", fn
                                        if not os.path.exists(fn):
                                                print "Error: creating path ", fn, " did not work"
                                                logging.error('Error: creating path %s did not work' % fn)
                                                continue
                                        # for some reason os.makedirs does not create the path with the correct permissions (umask problem?)
                                        # set the permissions here to make sure we can later write into these directories
                                        os.umask(00000)
                                        os.chmod(fn,01777)
                                fn2 = os.path.join(fn, dataset.SOPInstanceUID)
                                if not os.path.isfile(fn2):
                                        try:
                                                os.symlink(response, fn2)
                                        except OSError:
                                                # the file could exist already? Something must be wrong with this link
                                                pass
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
                                #try:
                                #        data['CSAHeaderImg'] = ptag_img
                                #except:
                                #        pass
                                #try:
                                #        data['CSAHeaderSeries'] = ptag_ser
                                #except:
                                #        pass
                                try:
                                        data['Manufacturer'] = dataset.Manufacturer
                                except:
                                        pass
                                try:
                                        data['ManufacturerModelName'] = dataset.ManufacturerModelName
                                except:
                                        pass
                                try:
                                        data['SoftwareVersion'] = dataset.SoftwareVersion
                                except:
                                        pass
                                try:
                                        data['AcquisitionMatrix'] = str(dataset.AcquisitionMatrix)
                                except:
                                        pass
                                try:
                                        data['AcquisitionLength'] = str(dataset[0x51, 0x100a].value)
                                except:
                                        pass
                                try:
                                        data['Modality'] = dataset.Modality
                                except:
                                        pass
                                try:
                                        data['AcquisitionLength'] = str(dataset[0x51, 0x100a].value)
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
                                        data['SeriesTime'] = dataset.SeriesTime
                                except:
                                        pass
                                try:
                                        data['PatientID'] = dataset.PatientID
                                except:
                                        pass
                                try:
                                        data['SOPClassUID'] = str(dataset[0x08, 0x16].value)
                                except:
                                        pass
                                try:
                                        data['PatientName'] = dataset.PatientName
                                except:
                                        pass
                                try:
                                        data['PatientSex'] = dataset.PatientSex
                                except:
                                        pass
                                try:
                                        data['StudyDate'] = dataset.StudyDate
                                except:
                                        pass
                                try:
                                        data['StudyDescription'] = unicode(dataset.StudyDescription, "UTF-8", errors='ignore')
                                except:
                                        pass
                                try:
                                        data['SeriesDescription'] = dataset.SeriesDescription
                                except:
                                        pass
                                try:
                                        data['SequenceName'] = str(dataset[0x18,0x24].value)
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
                                        data['Slices'] = str(dataset[0x21,0x104f].value)
                                except:
                                        pass
                                try:
                                        data['ScanningSequence'] = str(dataset[0x18,0x20].value)
                                except:
                                        pass
                                try:
                                        data['SequenceVariant'] = str(dataset[0x018,0x021].value)
                                except:
                                        pass
                                try:
                                        data['SequenceType'] = str(dataset[0x2001,0x1020].value)
                                except:
                                        pass
                                try:
                                        data['PulseSequenceName'] = str(dataset[0x19,0x109c].value)
                                except:
                                        pass
                                try:
                                        data['ActiveCoils'] = str(dataset[0x51,0x100f].value)
                                except:
                                        pass
                                try:
                                        data['Private0019_105a'] = str(dataset[0x19,0x105a].value)
                                except:
                                        pass
                                try:
                                        data['SliceLocation'] = str(dataset[0x20,0x1041].value)
                                except:
                                        pass
                                try:
                                        data['ImagesInAcquisition'] = str(dataset[0x20,0x1002].value)
                                except:
                                        pass
                                try:
                                        rmp = str(dataset[0x18,0x1020].value)
                                        searchThat = re.compile(r'release:(?P<SystemOS>[^_"]+)')
                                        data['OSLevel'] = searchThat.search(rmp).group('SystemOS')
                                except:
                                        pass
                                try:
                                        data['AccessionNumber'] = str(dataset[0x08,0x50].value)
                                except:
                                        pass
                                try:
                                        data['NumberOfTemporalPositions'] = str(dataset[0x20,0x105].value)
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
                                
                                # Collect the UUID from the ptag_ser structure
                                if ptag_ser:
                                        try:
                                                tmp = ptag_ser['MrPhoenixProtocol']
                                                searchThis = re.compile(r'sWipMemBlock.tFree\t\s*=\s*\t\"\"(?P<UUID>[^\"]+)')
                                                data['siemensUUID'] = searchThis.search(tmp).group('UUID')
                                        except:
                                                pass
                                        try:
                                                tmp = ptag_ser['MrPhoenixProtocol']
                                                searchThat = re.compile(r'sProtConsistencyInfo.tMeasuredBaselineString\t\s*=\s*\t\"\"N4_(?P<SieOS>[^_"]+)')
                                                data['OSLevel'] = searchThat.search(tmp).group('SieOS')
                                        except:
                                                pass
                                        try:
                                                tmp = ptag_ser['MrPhoenixProtocol']
                                                searchThat = re.compile(r'sGroupArray.asGroup\[0\].nSize\t\s*=\s*\t(?P<NumSlice>[^\ns"]+)')
                                                data['NumSlices'] = searchThat.search(tmp).group('NumSlice')
                                        except:
                                                pass
                                        try:
                                                tmp = ptag_ser['MrPhoenixProtocol']
                                                searchThat = re.compile(r'SequenceID\t\s*=\s*\t(?P<MeaID>[^\n"]+)')
                                                data['MeasID'] = searchThat.search(tmp).group('MeaID')
                                        except:
                                                pass

                                # lets add up all the diffusion information we find for Siemens
                                siemensDiffusionInformation = None
                                if ptag_img:
                                        siemensDiffusionInformation = {}
                                        #try:
                                        #        siemensDiffusionInformation['B_matrix'] = ptag_img['B_matrix']
                                        #except:
                                        #        pass
                                        try:
                                                siemensDiffusionInformation['B_value'] = ptag_img['B_value']
                                                try:
                                                        siemensDiffusionInformation['SOPInstanceUID'] = str(dataset[0x08,0x18].value)
                                                except:
                                                        pass
                                                try:
                                                        siemensDiffusionInformation['InstanceNumber'] = str(dataset[0x20,0x13].value)
                                                except:
                                                        pass
                                        except:
                                                pass
                                        try:
                                                siemensDiffusionInformation['DiffusionDirectionality'] = ptag_img['DiffusionDirectionality']
                                        except:
                                                pass
                                        try:
                                                siemensDiffusionInformation['DiffusionGradientDirection'] = ptag_img['DiffusionGradientDirection']
                                        except:
                                                pass
                                        try: 
                                                data['PhaseEncodingDirectionPositive'] = ptag_img['PhaseEncodingDirectionPositive']
                                        except:
                                                pass

                                # keep the slice location (use the maximum values for all slice locations)
                                currentSliceLocation = None
                                try:
                                        currentSliceLocation = data['SliceLocation']
                                except:
                                        pass
                                currentMeasID = None
                                try:
                                        currentMeasID = data['MeasID']
                                except:
                                        pass
                                # this will overwrite the currently created data again - but only if it exists already (add after this section if you want to update every slice)
                                if os.path.exists(fn3):
                                        with open(fn3, 'r') as f:
                                                try:
                                                        data = json.load(f)
                                                except ValueError:
                                                        print("Error: could not read json file in %s, ValueError" % fn3)
                                                        pass
                                if currentSliceLocation != None:
                                        try:
                                                if float(data['SliceLocation']) > float(currentSliceLocation):
                                                        data['SliceLocation'] = currentSliceLocation;
                                        except:
                                                pass
                                if currentMeasID != None:
                                        try:
                                                data['MeasID'] = currentMeasID
                                        except:
                                                pass
                                if siemensDiffusionInformation != None:
                                        if not 'siemensDiffusionInformation' in data:
                                                data['siemensDiffusionInformation'] = []
                                        try:
                                                if siemensDiffusionInformation['DiffusionDirectionality'] != '':
                                                        data['siemensDiffusionInformation'].append( siemensDiffusionInformation )
                                        except:
                                                pass

                                if not 'ClassifyType' in data:
                                        data['ClassifyType'] = []
                                data['StudyInstanceUID'] = dataset.StudyInstanceUID
                                data['NumFiles'] = str( int(data['NumFiles']) + 1 )
                                # add new types as they are found (this will create all type that map to any of the images in the series)
				data['ClassifyType'] = self.classify(dataset, data, data['ClassifyType'])
                                #data['ClassifyType'] = data['ClassifyType'] + list(set(self.classify(dataset, data)) - set(data['ClassifyType']))

                                # we should sanitize this data first, otherwise there might be values in there can not be unicoded
                                for key,value in data.items():
                                        if isinstance(value,basestring) and not isinstance(value,unicode):
                                                data[key] = unicode(value, "UTF-8", errors='ignore')

                                # make sure that the file permissions are correct
                                os.umask(0)
                                fd = os.open(fn3, os.O_CREAT | os.O_WRONLY, 0o666)
                                with os.fdopen(fd,'w') as f:
                                        json.dump(data,f,indent=2,sort_keys=True)
                                os.chmod(fn3, 0o666)
                rp.close()

# There are two files that make this thing work, one is the .pid file for the daemon
# the second is the named pipe in /tmp/.processSingleFile
#  Hauke,    July 2015               
if __name__ == "__main__":
        projname = ''
        if (sys.argv[1] != "send") and (len(sys.argv) == 3):
                projname = sys.argv[2]
                #print ("DEBUG: projname: ", projname)

        # try to read the config file from this machine
        datadir = '/data'
        configFilename = '/data/config/config.json'
        settings = {}
        with open(configFilename,'r') as f:
                settings = json.load(f)

        #print ("DEBUG: config.json: ", settings)

        # Look for the datadir for the current project
        try:
                datadir = settings['SITES'][projname]['DATADIR'].encode("utf-8")
                #print ("DEBUG: datadir: ", datadir)
        except KeyError:
                print("Could not read local config files DATADIR value for project \"%s\" in %s, assume ABCD default: %s" % (projname, configFilename, datadir))
                pass

        pidfilename = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, '../.pids/processSingleFile' , projname , '.pid' ])
        #print ("DEBUG: pidfilename: ", pidfilename)

        p = os.path.dirname(os.path.abspath(pidfilename))
        if not os.path.exists(p):
                print "The path to the pids does not exist (%s), use alternative location for pid file" % p
                pidfilename = tempfile.gettempdir() + '/processSingleFile' + projname + '.pid'
        lfn = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, '/../logs/processSingleFile' , projname , '.log' ])

        #log = logging.handlers.RotatingFileHandler(lfn, 'a', 10*1024*1024, backupCount=5)
        #log.setFormatter(logging.Formatter('%(levelname)s:%(asctime)s: %(message)s'))
        logging.basicConfig(filename=lfn,format='%(levelname)s:%(asctime)s: %(message)s',level=logging.DEBUG)
        daemon = ProcessSingleFile(pidfilename)
        daemon.init()
        if (sys.argv[1] != "send") and (len(sys.argv) > 1):
                if 'start' == sys.argv[1]:
                        daemon.projname = projname
                        daemon.pipename = ''.join([daemon.pipename, projname])
                        daemon.datadir = datadir
                        try:
                                daemon.start()
                        except:
                                exc_type, exc_obj, exc_tb = sys.exc_info()
                                fname = os.path.split(exc_tb.tb_frame.f_code.co_filename)[1]
                                print(exc_type, fname, exc_tb.tb_lineno)
                                print(traceback.format_exc())
                                print "Error: could not create processing daemon: %s %s %s" % (sys.exc_info()[0] ,sys.exc_info()[1], sys.exc_info()[2])
                                logging.error("Error: could not create processing daemon: %s %s %s" % (sys.exc_info()[0],sys.exc_info()[1], sys.exc_info()[2]))
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
