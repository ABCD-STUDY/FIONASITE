#!/bin/bash

# define a study instance uid

lines=`findscu -aet CTIPMUCSD2 -aec CTIPMUCSD1 --study -k 0008,0052=SERIES -k 0020,000d=$1 172.20.141.70 4006`

