<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of log events
 *
 * @package    mod
 * @subpackage webinar
 * Webinar module for Moodle - Copyright (C) 2011 Epic (http://www.epic.co.uk/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB;

$logs = array(
    array('module'=>'webinar', 'action'=>'add', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'delete', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'update', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'view', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'view all', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'add session', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'copy session', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'delete session', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'update session', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'view session', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'view attendees', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'take attendance', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'signup', 'mtable'=>'webinar', 'field'=>'name'),
    array('module'=>'webinar', 'action'=>'cancel', 'mtable'=>'webinar', 'field'=>'name')
);