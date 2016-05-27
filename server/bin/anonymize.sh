#!/usr/bin/env python
"""
Anonymize a list of DICOM files
"""

import sys, os, time, atexit, stat, tempfile, copy
import dicom, json, re, logging, logging.handlers, threading
import struct
from signal import SIGTERM
from dicom.filereader import InvalidDicomError
from dicom.dataelem import DataElement
from dicom.tag import Tag
from multiprocessing import Pool, TimeoutError

def anonymize( df ):
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
        num = 0
        for tagEntry in anonymizeThis:
                # remove this tag from this DICOM file
                newValue = ""
                if 'value' in tagEntry:
                        newValue = tagEntry['value']
                if 'group' in tagEntry:
                        if not 'element' in tagEntry:
                                print "Error: anonymizeTag element contains group but no tag"
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
        # find out if the file is a symbolic link, overwrite the origin instead
        #if os.path.islink(df):
        #        print("File is symlink, replace origin instead")
        #        log.error("File is symlink %s" % df)

        # and overwrite the file again
        try:
                #print("Save the file again in: %s" % df)
                log.error("Save the file again in: %s" % df)
                dataset.save_as(df)
        except:
                print("Error: could not overwrite DICOM %s" % df)
                log.error("Error: could not overwrite DICOM %s" % df)
        log.info("Anonymize %s (%d tags touched)" % (df, num))
        return 0


#  Hauke,    May 2016               
if __name__ == "__main__":
        lfn = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, '/../logs/anonymizer.log' ])
        # logging.basicConfig(filename=lfn,format='%(levelname)s:%(asctime)s: %(message)s',level=logging.DEBUG)
        log = logging.getLogger('MyLogger')
        log.setLevel(logging.DEBUG)
        handler = logging.handlers.RotatingFileHandler( lfn, maxBytes=1e+7, backupCount=5 )
        handler.setFormatter(logging.Formatter('%(levelname)s:%(asctime)s: %(message)s'))
        log.addHandler(handler)

        if len(sys.argv) == 2:
                # our directory is in sys.argv[1]
                if not os.path.isdir(sys.argv[1]):
                        print "Error: path does not exist"
                        log.error("Error: path does not exist")
                        sys.exit(0)

                # read the tags to delete
                tagsFile = ''.join([ os.path.dirname(os.path.abspath(__file__)), os.path.sep, 'anonymizeTags.json'])
                anonymizeThis = []
                if os.path.exists(tagsFile):
                        with open(tagsFile,'r') as f:
                                try:
                                        anonymizeThis = json.load(f)
                                except ValueError:
                                        print "Error: could not read anonymizeTags.json file, syntax error"
                                        log.error("Error: could not read anonymizeTags.json file, syntax error")
                                        sys.exit(0)
                else:
                        log.critical("Error: could not find the list of tags to remove (anonymizeTags)")
                        sys.exit(0)

                # create a pool of worker to speed things up
                # (tests show that with 6 CPU's we will still need about 2min to anonymize 14,000 files of a study,
                #  with 2 CPUs we need 4min)
                pool = Pool(processes=2)
                res  = []
                for r,d,f in os.walk(sys.argv[1]):
                        for file in f:
                                df = os.path.join(sys.argv[1],file)
                                #print df
                                res.append(pool.apply_async(anonymize, args=(df,)))
                for proc in res:
                        proc.get(timeout=1)
                pool.close()
                sys.exit(0)
        else:
                print "Anonymize a series of DICOM files in a directory (in place)."
                print "Requires as an argument the directory with the DICOM files to anonymize (in-place)."
                sys.exit(2)
