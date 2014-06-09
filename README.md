PieterPost
==========

About
-----

PieterPost is a pop3/mail web interface written in PHP(4). It allows you to
access your mail through any normal browser provided it supports cookies.
  
It's kind of feature-less as of yet because the POP3 protocol is only limited,
but I hope to work around that in the future.
  
But it's primary function (at this time) is just viewing mail, and being able
to reply to it. 
  
PieterPost is probably not conform to any RFC out there, but I really don't
give a rat's ass. It works for me. But if you have any suggestions, feel free
to mail them to me.
  
  
Features
--------

* Clickable URL's in non-html messages.
* Language support,
* Abbility to show all, new or old messages in mailbox,
* Multiple addressed in To, CC and BCC fields,
* Addressbook for CC and BCC,
* Theme support. (colors), settable per user,
* Read and unread mail support,
* Preferences support (full name, sort, filter, language, theme and signature),
* Addressbook,
* Sending of attachment,
* Better error handling
* Setup script.
* CC and Bcc support,
* Support for deleting of mail,
* Full/Short header view,
* Viewing/downloading of attachments,
* Reading mail,
* Replying to mail,
* Composing mail.


Installation
------------

PieterPost requires :
  
* PHP4+, with imap.so module (or build in support).
* A POP3 daemon.
* A browser with CSS2 support
  
Unpack the source and place it in the webroot of your webserver.

  
If you want undeliverable mail errors to return to the user who sent them, you
must make sure that your smtp-server allows the webserver user to set the From:
field. For Exim, this means you'll have to add the webserver user (On Debian
systems www-data) to the trusted_users option in the configuration file:
  
    /etc/exim.conf:
    trusted_users = mail:www-data
  
For Sendmail, it means you either have to start using Exim, or figure it out
yourself. (I'll bet I'm gonna get some comments on this ;-)
  
Sometimes downloading attachments over SSL in IE might not work. This is caused
by a bug in the extra headers sent when using SSL. It can be fixed by adding
this line to you apache-ssl configuration:
  
  BrowserMatch ".*MSIE.*" nokeepalive ssl-unclean-shutdown downgrade-1.0 force-response-1.0
  
For Debian, this line should be added to the srm.conf file.

This fix will not always work. Some users report that it fixed the problem, for
some users it didn't work at all. There is nothing I can do about it, it's a
bug in Internet Explorer. Try upgrading to the latest version of IE, and hope
that Microsoft finally got some sence and adhered to the standards instead of
doing everything their own way.


To allow large attachments to be sent, you must change the `upload_max_filesize`
directive in the php.ini configuration file.

### Themes
  
Version 0.8.6+ has support for themes. The theme files are suffixed with a .pt
extention, and reside in the themes/ directory under the preferences dir.
During setup, you will be asked which theme you want to use. Bluegreen is the
default.
    
If you ever want to change the default theme, you can either re-run setup, or
you can edit your pp.php by hand and modify the $theme variable at the top
somewhere. 

Each user can change his own theme by choosing one in the preferences screen.
    
Creating your own theme should be fairly easy. Just copy a .pt file to a new
name, make sure your PieterPost uses the new theme file (see the paragraph
above for instructions) and start modifying the stylesheet. If your theme is
complete, please mail it to me, so I can include it in the next version of
PieterPost.
    
Sometimes PHP will be set up in a manner that will not allow including or
reading files from different dirs than the www dir, or some other directories
set in the php.ini configuration file.This will pose a problem for
themes/languages, because they can't be included/read from the script dir. A
(bad) fix for this is to disable safemode, or allow PHP to read from the
/usr/local/pieterpost (or whatever you entered during setup for the preferences
path) path.
  
###  Language support

As of Pieterpost 0.9, it now contains beta language support. I suppose it still
needs some work, but that's something to take care of in 1.0 beta's.

In the directory languages/ in the PP source package, you'll find files with a
.lang extention. These files simply contain a list of strings sed in the
interface.

To create a new language, take the english.lang (or any other if you please)
and translate the strings. I'd appreciate it if you'd sent the new translation
to me. Also mention if you are willing to maintain the language file. This will
be necassary whenever the format for the language file changes (as it did in
the v0.10 release), or whenever new items are added which need translation. 

Pay special attention to some of the strings which contain non-alpha characters
like quotes and stuff. They must be escaped. Sometimes using \\n, sometimes \n,
sometimes \', sometimes \\'. Escaping gets particulary weird when the message
will be displayed through javascript, because JS wants stuff to be escaped too.
It would be easier to simply copy the stuff in the english language file. It
shows how to escape things proparly.


  
For administrators
------------------

This section is intended for system administrators who would like to offer
Pieterpost to the users on their systems. It goes more in-depth on how
internals of Pieterpost works, so they can see if it meets their desires. This
section might also be usefull for developers. Might. 

Pieterpost calls pop3 directly. Both for retrieval of messages and
authentification. (use SSL ;) It does this every time. Messages are not stored
on the filesystem by Pieterpost. All emails remain on the pop3 server, unless
the user deletes them, or retrieves them using a different client which does
remove them and store them locally.

All settings (both global and user-specific) are stored in a preferences
directory which can be given when setting up Pieterpost. Default is (I think) :
/usr/local/pieterpost
  
For each user some files are kept with their settings. These are

      [username].address  : Addressbook 
      [username].msg      : Seen message-id's.
      [username].prefs    : Preferences
  
Users' files which do not exist on the system anymore may be deleted. I 
should write a script to automatically do this. Better add that to the 
TODO list.
  
The preferences also includes a directory for the themes/. Here you can 
add/modify/delete themes. PieterPost users can't choose their own theme,
so the theme you chose during setup is system wide.

When Pieterpost contacts the pop3 server, it retrieves some basic
information about the mails. It checks the .msg file to see if the 
specific email has already been read.

Pieterpost installs only one file in the webserver dir, namelly pp.php.


Security
--------

Pieterpost in its unstable condition (all version < 1.0) is considered 
insecure and an incarnation of the devil himself in the form of a php
script. Well, maybe not _that_ insecure, but anyway. Pieterpost tries 
to be as secure as possible, but security bugs are most probably still 
present in it. 

Authorization is done through the POP3 daemon, which should prove pretty
secure. But since it is very easy to sniff out passwords on networks,
you should always use Pieterpost over secure SSL connections only!

Pieterpost has been tested with the register_globals directive off and 
the safe_mode directive on, so you can use these to harden security for 
php scripts on your servers.

You should also note that the session data files which are stored by
PHP on your filesystem contain the username and password for a user, 
and if your php configuration is not secure enough, it can be read by
users allowed to create php files. This is just a configuration setup 
theory, but you could set the session save handler to memory or modify
the session save path to point to somewhere normal system users can't 
read them and make sure they can't be read using PHP too. (base dir 
restrictions and all that)

Anyway, the most important points are:

* Run it over SSL ONLY!
* Do not run your webserver as user nobody!
  
Also read the beautiful NO WARRANTY disclaimer in the GPL. ;)

  

9. Copyright stuff.
-------------------------------------------------------------------------------

  PieterPost is Copyright by Ferry Boender,
  licensed under the General Public License (GPL)
  
  Copyright (C), 2000-2004 by Ferry Boender 
  
  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.
      
  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.
      
  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
              
  For more information, see the COPYING file supplied with this
  program.

