#!/bin/bash

echo >> /etc/securetty
echo "# shellinabox" >> /etc/securetty
declare -i COUNT=0
while [ $COUNT -le 40 ]; do
    echo "pts/$COUNT" >> /etc/securetty
    ((COUNT=$COUNT+1))
done
