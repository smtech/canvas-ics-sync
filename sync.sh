#!/bin/bash
BASEDIR=$(dirname $0)
cd $BASEDIR
php ./sync.php $1 $2 > /dev/null 2>&1
