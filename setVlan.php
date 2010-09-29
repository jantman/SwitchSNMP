#!/usr/bin/php
<?php
//
// +----------------------------------------------------------------------+
// | RackMan      http://rackman.jasonantman.com                          |
// +----------------------------------------------------------------------+
// | Copyright (c) 2009 Jason Antman.                                     |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 3 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 59 Temple Place - Suite 330                                          |
// | Boston, MA 02111-1307, USA.                                          |
// +----------------------------------------------------------------------+
// | ADDITIONAL TERMS (pursuant to GPL Section 7):                        |
// | 1) You may not remove any of the "Author" or "Copyright" attributions|
// |     from this file or any others distributed with this software.     |
// | 2) If modified, you must make substantial effort to differentiate    |
// |     your modified version from the original, while retaining all     |
// |     attribution to the original project and authors.                 |
// +----------------------------------------------------------------------+
// |Please use the above URL for bug reports and feature/support requests.|
// +----------------------------------------------------------------------+
// | Authors: Jason Antman <jason@jasonantman.com>                        |
// +----------------------------------------------------------------------+
// | $LastChangedRevision:: 5                                           $ |
// | $HeadURL:: http://svn.jasonantman.com/rackman/bin/getSwitchInterfa#$ |
// +----------------------------------------------------------------------+

require_once('com_jasonantman_SwitchSNMP.php');
//require_once('../inc/funcs.php.inc');

// CONFIG
$debug = false;
$ip = '128.6.31.131';
$rocommunity = 'private';
// END CONFIG

// INTERFACE NAME TRANSLATION
$ifRegex = "/([a-zA-Z]+)\d*\/\d*/";
$nameTranslations = array("fastethernet" => "FastEthernet", "fe" => "FastEthernet", "fa" => "FastEthernet", "gigabitethernet" => "GigabitEthernet", "ge" => "GigabitEthernet", "gi" => "GigabitEthernet");
// END INTERFACE NAME TRANSLATION

echo "working on switch: $ip\n";

try
{
    $switch = new com_jasonantman_SwitchSNMP($ip, $rocommunity, $debug);
}
catch (Exception $e)
{
    fwrite(STDERR, "ERROR: Attempt to construct com_jasonantman_SwitchSNMP threw exception: ".$e->getMessage()."\n");
    return false;
}

$args = parseArgs($argv);

if(! isset($args['vlan']) || (! isset($args['ifindex']) && ! isset($args['ifname'])) )
{
    echo "ERROR: invalid arguments.\n";
    usage();
    die();
}

$ifindex = -1;

// do stuff
if(isset($args['ifindex']))
{
    $ifindex = (int)$args['ifindex'];
}
else
{
    // translate ifname
    $matches = array();
    preg_match($ifRegex, $args['ifname'], $matches);
    $ifname = str_ireplace($matches[1], $nameTranslations[strtolower($matches[1])], $args['ifname']);

    // get ifindex from ifname
    $indexes = $switch->getPortIndexNameArray();
    foreach($indexes as $idx => $name)
    {
	if(strcasecmp($name, $ifname) == 0)
	{
	    $ifindex = $idx;
	}
    }
    if($ifindex == -1)
    {
	fwrite(STDERR, "ERROR: Interface index for  '".$args['ifname']."' (".$ifname.") not found!\n");
	die();
    }
}
$vlan = (int)$args['vlan'];

echo "Setting ifindex $ifindex to VLAN $vlan\n";

$foo = $switch->setPortVlan($ifindex, $vlan);
if($foo){ echo "Done.\n";} else { echo "Failed.\n";}

function usage()
{
    echo "setVlan.php --ifindex=INT --vlan=INT\n";
}

function parseArgs($arr)
{
    $ret = array();
    array_shift($arr);
    foreach($arr as $val)
    {
	$parts = explode("=", $val);
	$parts[0] = trim($parts[0], "- ");
	$ret[$parts[0]] = $parts[1];
    }
    return $ret;
}


?>
