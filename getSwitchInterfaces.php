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
require_once('../inc/com_jasonantman_SwitchSNMP.php');
require_once('../inc/funcs.php.inc');
require_once('../config/config.php');

// connect to MySQL
rackman_mysql_connect() or die("Error connecting to MySQL.\n");
mysql_select_db($dbName) or die("Error selecting MySQL database: ".$dbName."\n");

// TODO - use another script for correlating MAC -> port (interface_patches)

// TODO - loop through our switches, call doSwitchInterfaces for each
$query = "SELECT d.device_id,d.device_name FROM devices AS d LEFT JOIN opt_device_classes AS odc ON odc.odc_id=d.device_class_id LEFT JOIN opt_device_types AS odt ON odt.odt_id=d.device_type_id WHERE odc.odc_name='Network' AND odt.odt_name LIKE '%switch%';";
$result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
while($row = mysql_fetch_assoc($result))
{
    doSwitchInterfaces($row['device_id'], $row['device_name'], 'public');
}

function doSwitchInterfaces($device_id, $ip, $rocommunity)
{
    fwrite(STDERR, "Working on switch ".$ip." (device_id ".$device_id.")\n");
    try
    {
	$switch = new com_jasonantman_SwitchSNMP($ip, $rocommunity);
    }
    catch (Exception $e)
	{
	    fwrite(STDERR, "ERROR: Attempt to construct com_jasonantman_SwitchSNMP threw exception: ".$e->getMessage()."\n");
	    return false;
	}
    $fields = $switch->GET_PORT_RETURN_FIELDS;
    
    // get the info as RackMan interfaces
    $ports = $switch->getPorts();
    $rackManPorts = array();
    foreach($ports as $idx => $arr)
    {
	$temp = array();
	$temp['name'] = $arr['IFMIB-name'];
	$temp['alias'] = $arr['CISCO-portName'];
	$temp['default_speed_bps'] = $arr['IFMIB-speed'];

	$temp['mac_address'] = $arr['IFMIB-macaddr'];
	
	// figure out the type, as in 'opt_interface_types' table
	
	// serial sl0 slip
	if($arr['IFMIB-descr'] == "sl0" && $arr['IFMIB-type'] == "slip" && $switch->type == "Cisco CatOS")
	{
	    $query = "SELECT oit_id FROM opt_interface_types WHERE oit_type='Se' AND oit_connector='8P8C' AND oit_max_speed_bps=9600;";
	    $result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
	    if(mysql_num_rows($result) > 0)
	    {
		$row = mysql_fetch_assoc($result);
		$temp['oit_type'] = $row['oit_id'];
	    }
	    else
	    {
		fwrite(STDERR, "Error: Interface Index ".$idx.": Unknown serial/slip type.\n");
		$temp['oit_type'] = -1;
	    }
	}
	// ethernet UTP port, 10 Mbps, management
	elseif($arr['IFMIB-type'] == "ethernetCsmacd" && $arr['IFMIB-speed'] == 10000000 && $arr['IFMIB-descr'] == "me1")
	{
	    $query = "SELECT oit_id FROM opt_interface_types WHERE oit_type='E' AND oit_connector='8P8C' AND oit_max_speed_bps=10000000 AND oit_standard='10BASE-T';";
	    $result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
	    if(mysql_num_rows($result) > 0)
	    {
		$row = mysql_fetch_assoc($result);
		$temp['oit_type'] = $row['oit_id'];
	    }
	    else
	    {
		fwrite(STDERR, "Error: Interface Index ".$idx.": Unknown me1 Ethernet type.\n");
		$temp['oit_type'] = -1;
	    }
	}
	// standard 10/100Base-TX port
	elseif($arr['ENTITY-physDescr'] == '10/100BaseTX' && $arr['IFMIB-descr'] == '10/100 utp ethernet (cat 3/5)' && $arr['ENTITY-physClass'] == 'port')
	{
	    $query = "SELECT oit_id FROM opt_interface_types WHERE oit_type='Fe' AND oit_standard='100BASE-TX' AND oit_media='UTP';";
	    $result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
	    if(mysql_num_rows($result) > 0)
	    {
		$row = mysql_fetch_assoc($result);
		$temp['oit_type'] = $row['oit_id'];
	    }
	    else
	    {
		fwrite(STDERR, "Error: Interface Index ".$idx.": Unknown 10/100 utp ethernet type.\n");
		$temp['oit_type'] = -1;
	    }
	}
	// GigE SX port
	elseif($arr['IFMIB-descr'] == 'short wave fiber gigabit ethernet' && $arr['ENTITY-physClass'] == 'port' && $arr['CISCO-portType'] == 'e1000BaseSX')
	{
	    $query = "SELECT oit_id FROM opt_interface_types WHERE oit_type='Ge' AND oit_standard='1000BASE-SX' AND oit_media='MM Fiber';";
	    $result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
	    if(mysql_num_rows($result) > 0)
	    {
		$row = mysql_fetch_assoc($result);
		$temp['oit_type'] = $row['oit_id'];
	    }
	    else
	    {
		fwrite(STDERR, "Error: Interface Index ".$idx.": Unknown e1000BaseSX type.\n");
		$temp['oit_type'] = -1;
	    }
	}
	// EMPTY GBIC
	elseif($arr['IFMIB-descr'] == 'gigabit ethernet without GBIC installed')
	{
	    $query = "SELECT oit_id FROM opt_interface_types WHERE oit_type='Ge' AND oit_connector='Empty GBIC' AND oit_media='GBIC';";
	    $result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
	    if(mysql_num_rows($result) > 0)
	    {
		$row = mysql_fetch_assoc($result);
		$temp['oit_type'] = $row['oit_id'];
	    }
	    else
	    {
		fwrite(STDERR, "Error: Interface Index ".$idx.": Unknown gigabit ethernet without GBIC installed type.\n");
		$temp['oit_type'] = -1;
	    }
	}
	// unknown interface type
	else
	{
	    $temp['oit_type'] = -1;
	    fwrite(STDERR, "Error: No interface type could be found for interface ".$idx."\n");
	}
	
        // do we add this in MySQL or not?
	$query = "SELECT di_pkey FROM device_interfaces WHERE di_device_id=".$device_id." AND di_IF_MIB_ifindex=".$arr['IFMIB-index'].";";
	$result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
	if(mysql_num_rows($result) < 1 && $temp['oit_type'] != -1)
	{
	    // not already there, and have a type, add it
	    $query = "INSERT INTO device_interfaces SET di_device_id=".$device_id.",di_name='".$temp['name']."',di_type=".$temp['oit_type'].",di_mac_address='".$temp['mac_address']."',di_alias='".$temp['alias']."',di_default_speed_bps=".$temp['default_speed_bps'].",di_IF_MIB_ifindex=".$arr['IFMIB-index'].";";
	    $result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
	}
    }
    fwrite(STDERR, "Finished with switch ".$ip."\n");
}

// TODO - getPatches() function to associate MACs with swith interfaces

?>
