-----------------------------------------------------------------------------

Webinar module for Moodle
Copyright (C) 2012 Epic (http://www.epic.co.uk/)

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option)
any later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see http://www.gnu.org/copyleft/gpl.html.

-----------------------------------------------------------------------------

Summary
------------

This is an activity module for web conferencing which enables Adobe Connect webinar 
sessions to be added as an activity to any course. 

UPDATE May 2014: this has been updated for Moodle 2.6, and bugs fixed that previously prevented use on Adobe Connect 9.

Description
------------

The Webinar activity module enables Adobe Connect hosted webinars to be added as an 
activity to any course. This is intended for hosted Adobe deployments, not on-premise 
deployments - see http://www.adobe.com/products/adobeconnect.html

The Webinar activity module includes the following functionality:

* Add/edit/delete webinar session
* Assign a host user to a session - based on 'teacher' system role 
* Register for session / assign students to a session
* Unregister for session / unassign students from a session
* Automated email notifications to registered students
* Join session 
* View / record webinar
* Run attendance report

All webinar administration is done from within Moodle from setting up webinar sessions, 
registering users on webinar sessions and joining sessions. Only when the student clicks 
on Join Session do they leave Moodle and view the webinar in the Adobe Connect Player 
window. Upon exiting the webinar session they will be returned to the Moodle course page.

It has been tested using Adobe Connect Pro 9 hosted accounts, using the Adobe Connect 
Web Services API - see http://help.adobe.com/en_US/connect/9.0/webservices/index.html.

Requirements
-------------

* Moodle 2.6
* An Adobe Connect account - register for a 30-day free trial at 
http://www.adobe.com/cfusion/adobeconnect/index.cfm?event=trial
* All users attending session will be required to have Flash Player 10.1 or higher, 
with the ability to install Adobe Connect Add-Ins in order to share their screen.

Installation
-------------

1- Unpack the module into your moodle install in order to create a mod/webinar directory.

2- Visit the /admin/index.php page to trigger the database installation.

3- (Optional) Change the default options in the activity modules configuration.

4- (Optional) Assign roles of teacher or non-editing teacher to one or more users to be able 
to assign them as webinar hosts.

Bugs/patches
--------------

Feel free to send bug reports (and/or patches!) to the current maintainer:

  Mark Aberdour (maberdour@epiclearninggroup.com)
  Joe Barber (jbarber@epiclearninggroup.com)

Changes
-------------

(see the ChangeLog.txt file)
