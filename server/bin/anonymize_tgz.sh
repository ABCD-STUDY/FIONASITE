#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Anonymize the DICOM files and the JSON inside a TGZ,
write out a new TGZ.

There are a couple of reasons why this might fail. For example
SUID k-space data could not be anonymized by this method. Also,
this program assumes that there is a single DICOM series with a
single JSON in the TGZ. Only DICOM files will be copied. Question
is what we do with the cases in which the anonymization fails.
Those datasets would potentially stay in the outbox folder.
"""
from __future__ import print_function

import sys, os, time, atexit, stat, tempfile, copy, getopt, tarfile
import dicom, json, re, logging, logging.handlers, threading, hashlib
import struct, datetime
from signal import SIGTERM
from dicom.filereader import InvalidDicomError
from dicom.dataelem import DataElement
from dicom.tag import Tag
from multiprocessing import Pool, TimeoutError

def anonymize( df, anonInfo ):
        try:
                # read the file using pydicom
                dataset = dicom.read_file(df)
        except IOError:
                print("Error: reading dicom file %s" % df)
                log.error("Error: reading dicom file %s" % df)
                return 0
        except OSError:
                print("Error: reading dicom file %s" % df)
                log.error("Error: reading dicom file %s" % df)
                return 0
        except InvalidDicomError:
                print("Error: not a dicom file %s" % df)
                log.error("Error: not a dicom file %s" % df)
                return 0


        # keep the DeviceSerialNumber, we will remove it once we share the data
        #hash for DeviceSerialNumber
        #encoded = dataset[int("0x18",0),int("0x1000",0)].value.encode('utf-8')
        #h = "anon%s" % hashlib.sha224(encoded).hexdigest()
        #dataset[int("0x18",0),int("0x1000",0)].value = h[0:8]

        # reset patient name and patient id
        dataset[int("0x10",0),int("0x10",0)].value = anonInfo['PatientName']
        dataset[int("0x10",0),int("0x20",0)].value = anonInfo['PatientName']
        # get this value returned, its used as the file name in the generated TGZ
        anonInfo['ImageInstanceUID'] = dataset[int("0x08",0),int("0x18",0)].value

        num = 0
        for tagEntry in anonymizeThis:
                # remove this tag from this DICOM file
                newValue = ""
                if 'value' in tagEntry:
                        newValue = tagEntry['value']
                if 'group' in tagEntry:
                        if not 'element' in tagEntry:
                                print("Error: anonymizeTag element contains group but no tag")
                        else:
                                try:
                                        dataset[int(tagEntry['group'],0),int(tagEntry['element'],0)].value = newValue
                                        num = num + 1
                                except KeyError:
                                        #print "Erro: Key %s:%s does not exist" % (tagEntry['group'], tagEntry['element'])
                                        #log.error("Erro: Key %s:%s does not exist" % (tagEntry['group'], tagEntry['element']))
                                        pass
                if 'name' in tagEntry:
                        #newData = DataElement(dataset.data_element(tagEntry['name']).tag, dataset.data_element(tagEntry['name']).VR, newValue)
                        #dataset.add(newData)
                        try:
                                dataset.data_element(tagEntry['name']).value = newValue
                                num = num + 1
                        except KeyError:
                                print("Error: got keyerror trying to set the value of %s" % tagEntry['name'])
                                log.error("Error: got keyerror trying to set the value of %s" % tagEntry['name'])

        # and overwrite the file again
        try:
                log.info("Write anonymized file: %s" % df)
                dataset.save_as(df)
        except:
                print("Error: could not overwrite DICOM %s" % df)
                log.error("Error: could not overwrite DICOM %s" % df)
        log.info("Anonymize %s (%d tags touched)" % (df, num))
        return 0


def printProgressBar (iteration, total, prefix = '', suffix = '', decimals = 1, length = 100, fill = u'█'):
    """
    Call in a loop to create terminal progress bar
    @params:
        iteration   - Required  : current iteration (Int)
        total       - Required  : total iterations (Int)
        prefix      - Optional  : prefix string (Str)
        suffix      - Optional  : suffix string (Str)
        decimals    - Optional  : positive number of decimals in percent complete (Int)
        length      - Optional  : character length of bar (Int)
        fill        - Optional  : bar fill character (Str)
    """
    percent = ("{0:." + str(decimals) + "f}").format(100 * (iteration / float(total)))
    filledLength = int(length * iteration // total)
    bar = fill * filledLength + '-' * (length - filledLength)
    ss = '\r%s |%s| %s%% %s' % (prefix, bar, percent, suffix)
    print(ss.encode("utf-8"), end="\r")
    # Print New Line on Complete
    if iteration == total: 
        print()


#  Hauke,    Jan 2018               
if __name__ == "__main__":
        start_time = time.time()
        lfn = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, '/../logs/anonymizer.log' ])
        # logging.basicConfig(filename=lfn,format='%(levelname)s:%(asctime)s: %(message)s',level=logging.DEBUG)
        log = logging.getLogger('MyLogger')
        log.setLevel(logging.DEBUG)
        handler = logging.handlers.RotatingFileHandler( lfn, maxBytes=1e+7, backupCount=5 )
        handler.setFormatter(logging.Formatter('%(levelname)s:%(asctime)s: %(message)s'))
        log.addHandler(handler)
        
        inputfile = ''
        outputfile = ''
        patientName = 'anonymized'
        try:
                opts,args = getopt.getopt(sys.argv[1:],"hi:o:n:",["input=","output=","name="])
        except getopt.GetoptError:
                print('anonymize_tgz.sh -i <input tgz> -o <output tgz> -n <patient name>')
                sys.exit(2)
        for opt, arg in opts:
                if opt == '-h':
                        print('anonymize_tgz.sh -i <input tgz> -o <output tgz> -n <patient name>')
                        sys.exit()
                elif opt in ("-i", "--input"):
                        inputfile = arg
                elif opt in ("-o", "--output"):
                        outputfile = arg
                elif opt in ("-n", "--name"):
                        patientName = arg

        if not os.path.isfile(inputfile):
                if inputfile == "":
                        print('Usage:\n  anonymize_tgz.sh -i <input tgz> -o <output tgz> -n <patient name>')
                        sys.exit(-1)
                print("Error: input file \"%s\" does not exist" % inputfile)
                log.error("Error: input file \"%s\" does not exist" % inputfile)
                print('Usage:\n  anonymize_tgz.sh -i <input tgz> -o <output tgz> -n <patient name>')
                sys.exit(-1)

        if outputfile == '':
                print("Error: no outputfile given")
                print('Usage:\n  anonymize_tgz.sh -i <input tgz> -o <output tgz> -n <patient name>')
                log.error('Error: no outputfile given')
                sys.exit(-1)

        print("converting: %s to %s with name: %s" % (inputfile, outputfile, patientName)) 
        log.info("converting: %s to %s with name: %s" % (inputfile, outputfile, patientName))
                
        # read the tags to delete
        tagsFile = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, 'anonymizeTags.json'])
        anonymizeThis = []
        if os.path.exists(tagsFile):
                with open(tagsFile,'r') as f:
                        try:
                                anonymizeThis = json.load(f)
                        except ValueError:
                                print("Error: could not read anonymizeTags.json file, syntax error")
                                log.error("Error: could not read anonymizeTags.json file, syntax error")
                                sys.exit(0)
        else:
                log.critical("Error: could not find the list of tags to remove (anonymizeTags)")
                sys.exit(-1)

        # fn, fext = os.path.splitext(inputfile)
        if os.path.exists(outputfile):
                print("Error: file %s already exists, please move away before re-compressing..." % outputfile)
                log.error("output file already exists, stop processing.")
                os.sys.exit(-1)
        tarout = tarfile.open(outputfile, 'w:gz')
        tarin = tarfile.open(inputfile, 'r:*')
        members = tarin.getmembers()

        # find the json files inside the TGZ
        reT = re.compile(r'.*[.]+json')
        jfile = [m for m in members if reT.search(m.name)]
        anonInfo = { 'PatientName': patientName }
        if len(jfile) == 1:
                fobj = tarin.extractfile(jfile[0])
                # read the json data and convert to string for json decode
                str_fobj = fobj.read().decode("utf-8")
                data = json.loads(str_fobj)
                try:
                        data['PatientName'] = patientName
                        data['PatientID']   = patientName
                        data['StudyDescription'] = 'Adolescent Brain Cognitive Development Study'
                        now = datetime.datetime.now()
                        data['Anonymized']  = now.strftime("FIONA, @SITE %B %Y")
                        anonInfo['StudyInstanceUID']  = data['StudyInstanceUID']
                        anonInfo['SeriesInstanceUID'] = data['SeriesInstanceUID']
                except KeyError:
                        pass
                jsonfname = '%s/%s/%s.json' % (anonInfo['StudyInstanceUID'], anonInfo['SeriesInstanceUID'], anonInfo['SeriesInstanceUID'])
                with tempfile.NamedTemporaryFile() as temp:
                        with open(temp.name, 'w') as outfile:
                                json.dump(data, outfile, indent=4, sort_keys=True)
                        tarout.add(temp.name,jsonfname)

        else:
                print("Error: we expected to find a single json file in this TGZ (%s), this is probably not a normal DICOM TGZ" % inputfile)
                log.error("Error: we expected to find a single json file in this TGZ (%s), this is probably not a normal DICOM TGZ" % inputfile)
                tarin.close()
                tarout.close()
                # get rid of this broken output file again
                os.remove(outputfile)
                sys.exit(-1)

        i = 0
        for entry in tarin.getmembers():
                en = tarin.extractfile(entry)
                fobj = None
                if hasattr(en, 'read'):
                        fobj = en.read()
                if fobj == None:
                        continue
                #anonInfo['origFileName'] = entry
                # save the file temporarily (which is stupid, should instead work with a in memory file such as StringIO)
                with tempfile.NamedTemporaryFile() as temp:
                        try:
                                temp.write(fobj)
                        except OSError:
                                pass
                        temp.flush()
                        printProgressBar(i,len(members), prefix='Progress:', suffix=('(%d/%d) Complete' % (i,len(members)-1)), length = 50)
                        # Some fields will be added to anonInfo by this call
                        anonymize(temp.name, anonInfo)
                        # add the file to the output tgz
                        fname = "%s/%s/%s" % (anonInfo['StudyInstanceUID'], anonInfo['SeriesInstanceUID'],anonInfo['ImageInstanceUID'])
                        tarout.add(temp.name, fname)
                        i = i + 1
        tarin.close()
        tarout.close()
        print("\nInfo: finished, wrote %d entries into %s (%s seconds)" % (i, outputfile, round(time.time() - start_time)))
        log.info("Info: finished, wrote %d entries into %s (%s seconds)" % (i, outputfile, round(time.time() - start_time)))
