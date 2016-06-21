<?php
/**
 * @package format_fpd
 * @copyright 2013 Institut Obert de Catalunya
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Albert Gasset <albert@ioc.cat>
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2016062100;
$plugin->requires  = 2014051200;
$plugin->component = 'format_fpd';
$plugin->dependencies = array(
    'mod_fpdquadern' => 2013102908,
    'mod_oublog' => 2014012703,
);
