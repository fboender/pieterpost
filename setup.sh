#!/bin/sh
#
# PieterPost setup/installation tool
#
# Damn, this script is ugly. But I have absolutely no experience in making
# installation scripts, nor in shell scripting.
#
# Any suggestions and comments are very much appreciated ;-)
#
#
#

VERSION=%%VERSION
DEFAULT_HOSTNAME=`dnsdomainname -f`
DEFAULT_PREFDIR=/var/pieterpost/
DEFAULT_SCRIPTDIR=/var/www/
DEFAULT_WEBUSER=www-data
DEFAULT_WEBGROUP=www-data
DEFAULT_THEME=bluegreen
DEFAULT_LANG=english
DEFAULT_POPSERVER=localhost
DEFAULT_POPPORT=110

#--------------------------------------------
# Show information
#--------------------------------------------
echo
echo PieterPost v$VERSION setup
echo ------------------------
echo
echo PieterPost is copyright by Ferry Boender. Released under the General 
echo Public License \(GPL\). See the COPYING file for more information.
echo
echo This setup script will install PieterPost on your system. In order to
echo be of any help with installing, this script will have to ask you a few
echo questions. The default values are between [ and ]. If you would like to
echo use these default values, just press return at the prompt.
echo 
echo If you are upgrading an older version of PieterPost, please first check 
echo the RElEASENOTES file.
echo
echo --

#-------------------------
# check if user is root
#-------------------------
if [ `id -u` != 0 ]; then 
	echo "------ README !! IMPORTANT !! -------"
	echo "In order to properly install PieterPost you will have to be root. You are"
	echo "not root. You may proceed with this setup script, but you will not be able"
	echo "to use the default installation values. To stop this script now, press"
	echo "ctrl-c"
	echo
	echo -n "Press enter to continue, or ctrl-c to stop: "
	read
	echo "--"
fi


#---------------------------------------
# get hostname
#---------------------------------------
echo "What is the default domainname from which sent mail should come?"
echo
read -p"[$DEFAULT_HOSTNAME] " HOSTNAME
echo "--"

if [ -z $HOSTNAME ]; then HOSTNAME=$DEFAULT_HOSTNAME; fi

#---------------------------------------
# get pop server to connect to
#---------------------------------------
echo "what is the domainname or the ip on which the pop3 server runs which"
echo "pieterpost should use to retrieve messages from?"
echo
read -p"[$DEFAULT_POPSERVER] " POPSERVER
echo "--"

if [ -z $POPSERVER ]; then POPSERVER=$DEFAULT_POPSERVER; fi

#---------------------------------------
# get port
#---------------------------------------
echo "On which port does the pop3 server run?"
echo
read -p"[$DEFAULT_POPPORT] " POPPORT
echo "--"

if [ -z $POPPORT ]; then POPPORT=$DEFAULT_POPPORT; fi

#---------------------------------------
# get preferences installation path
#---------------------------------------
echo "PieterPost needs a place to hold the user preferences files. Where would you"
echo "like them to be stored?"
echo
read -p"[$DEFAULT_PREFDIR] " PREFDIR
echo "--"

if [ -z $PREFDIR ]; then PREFDIR=$DEFAULT_PREFDIR; fi

#----------------------------------------
# get webserver username
#----------------------------------------
echo "The webserver needs to be able to write to the PieterPost preferences"
echo "directory? What is the username of your webserver?"
echo
read -p"[$DEFAULT_WEBUSER] " WEBUSER
echo "--"

if [ -z $WEBUSER ]; then WEBUSER=$DEFAULT_WEBUSER; fi

#----------------------------------------
# get webserver username
#----------------------------------------
echo "The webserver needs to be able to write to the PieterPost preferences"
echo "directory? What is the groupname of your webserver?"
echo
read -p"[$DEFAULT_WEBGROUP] " WEBGROUP
echo "--"

if [ -z $WEBGROUP ]; then WEBGROUP=$DEFAULT_WEBGROUP; fi

#----------------------------------------
# get pieterpost installation path
#----------------------------------------
echo "The PieterPost PHP script needs to be placed in a directory which is"
echo "accessible by your webserver. Where would you like it to be placed?"
echo
read -p"[$DEFAULT_SCRIPTDIR] " SCRIPTDIR
echo "--"

if [ -z $SCRIPTDIR ]; then SCRIPTDIR=$DEFAULT_SCRIPTDIR; fi

#----------------------------------------
# get pieterpost default theme
#----------------------------------------
echo "Pieterpost supports themes. The following themes are available:"
ls -1 themes/ | cut -d"." -f1 | sed "s/^/\ \ \ \ /"
echo "Which theme would you like to use?"
echo
read -p"[$DEFAULT_THEME] " THEME
echo "--"

if [ -z $THEME ]; then THEME=$DEFAULT_THEME; fi

#----------------------------------------
# get pieterpost default language
#----------------------------------------
echo "Pieterpost supports languages. "
echo
echo "The following themes are available:"
ls -1 languages/ | cut -d"." -f1 | sed "s/^/\ \ \ \ /"
echo "Which language would you like to use?"
echo
read -p"[$DEFAULT_LANG] " LANG
echo "--"

if [ -z $LANG ]; then LANG=$DEFAULT_LANG; fi

#---------------------------------------
# strip leading and trailing slashes
#---------------------------------------
PREFDIR=`echo $PREFDIR | sed -e 's/^\///;s/\/$//'`
SCRIPTDIR=`echo $SCRIPTDIR | sed -e 's/^\///;s/\/$//'`
PREFDIR=/$PREFDIR
SCRIPTDIR=/$SCRIPTDIR

#--------------------------------------
# Last chance
#--------------------------------------
echo "You have entered the following information:"
echo
echo "  Default domainname:    $HOSTNAME"
echo "  User preferences path: $PREFDIR/"
echo "  Webserver username:    $WEBUSER"
echo "  Webserver groupname:   $WEBGROUP"
echo "  PieterPost PHP path:   $SCRIPTDIR/(pp.php)"
echo "  Theme:                 $THEME"
echo "  Language:              $LANG"
echo "  POP3 server address:   $POPSERVER"
echo "  POP3 port:             $POPPORT"
echo
echo -n "Press enter to continue, or ctrl-c to stop: "
echo
read
echo "--"

echo "Proceeding with installation."
echo

#-----------------------------------------
# Prepare pp.php for user defined vars
#-----------------------------------------
echo "Preparing pp.php for new settings"
REPLACE=""
REPLACE="${REPLACE}s,^\$REF001.*,\$prefs[\"prefdir\"]=\"$PREFDIR/\";,;"
REPLACE="${REPLACE}s,^\$REF002.*,\$prefs[\"hostname\"]=\"$HOSTNAME\";,;";
REPLACE="${REPLACE}s,^\$REF003.*,\$prefs[\"theme\"]=\"$THEME\";,;"
REPLACE="${REPLACE}s,^\$REF004.*,\$prefs[\"language\"]=\"$LANG\";,;"
REPLACE="${REPLACE}s,^\$REF005.*,\$prefs[\"pop_server\"]=\"$POPSERVER\";,;"
REPLACE="${REPLACE}s,^\$REF006.*,\$prefs[\"pop_port\"]=\"$POPPORT\";,;"

cat pp.php | sed "${REPLACE}" > temp.pp.php

#----------------------------------------
# Install it
#-----------------------------------------

echo "Creating $PREFDIR/"; mkdir -p $PREFDIR/
echo "Creating $SCRIPTDIR/"; mkdir -p $SCRIPTDIR/
echo "Setting up themes"; cp -R themes $PREFDIR/
echo "Setting up languages"; cp -R languages $PREFDIR/
echo "Copying pp.php to $SCRIPTDIR/"; mv temp.pp.php $SCRIPTDIR/pp.php

#------------------------------------------
# Set some rights
#------------------------------------------

echo "Setting rights";
chmod 750 $PREFDIR/
chmod 750 $PREFDIR/themes
chmod 640 $PREFDIR/themes/*
chmod 750 $PREFDIR/languages
chmod 640 $PREFDIR/languages/*
chmod 750 $SCRIPTDIR/
chmod 644 $SCRIPTDIR/pp.php
chown $WEBUSER:$WEBGROUP $PREFDIR/
chown $WEBUSER:$WEBGROUP $SCRIPTDIR/
chown $WEBUSER:$WEBGROUP $SCRIPTDIR/pp.php
chown -R $WEBUSER:$WEBGROUP $PREFDIR/themes
chown -R $WEBUSER:$WEBGROUP $PREFDIR/languages

#---------------------------------------
# Write some info about installation 
#---------------------------------------
echo version=$VERSION > $PREFDIR/pp.info

echo
echo Installation is complete..
echo
echo If you did not read the README file, please do so now\!
echo If you are too lazy, you should still read the Installation section
echo of the README file, because it contains fixes for very common
echo problems. 
echo
echo Please note that release v0.10 featured a major code cleanup.
echo This may cause some lose of preferences if upgrading from a version 
echo prior to v0.10.x which the users will have to fill in again themselves.
echo
