#!/bin/bash
if [ $# -ne 2 ];
then
   echo "NAME:"
   echo "  created by buckets (`date +"%D"`)"
   echo "USAGE:"
   echo ""
   echo "  work.sh <dicom directory> <output directory>"
   exit; 
fi
input="$1"
output="$2"
# Add the call to your installed program here.
#    ./example_program "${input}" "${output}"
# Any result should be copied to ${output}. Don't change/delete anything in ${input}.
echo "Done..."
