#!/bin/bash
BASEDIR=$(dirname $0)
cd $BASEDIR
LOGFILE=.ignore.canvas-ics-sync.log
exec >> $LOGFILE 2>&1
date +"%Y-%m-%d %r"
php ./sync.php $1 $2
echo "---"