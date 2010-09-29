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
require_once('common.php.inc');
require_once('config.php');

/*
TODO: 
- table for device MACs on each port
- update timestamps on everything
*/ 

$tblpfx = $SWITCHSNMP_table_prefix; // too long a name to have in lots of queries

// DEBUG
$debug = 1;
// END DEBUG

// connect to MySQL
mysql_connect($SWITCHSNMP_db_host, $SWITCHSNMP_db_user, $SWITCHSNMP_db_pass) or die("Error connecting to MySQL.\n");
mysql_select_db($SWITCHSNMP_db_name) or die("Error selecting MySQL database: ".$dbName."\n");

// TODO - loop through our switches, call doSwitchInterfaces for each
$query = "SELECT * FROM ".$tblpfx."switch WHERE status=1;";
$result = mysql_query($query) or die("Error in query: ".$query."\nError: ".mysql_error());
while($row = mysql_fetch_assoc($result))
{
  fwrite(STDERR, "Working on switch ".$row['hostname']." (id ".$row['id'].")\n");
  try
    {
      $switch = new com_jasonantman_SwitchSNMP($row['hostname'], $row['rocommunity'], $debug);
    }
    catch (Exception $e)
	{
	    fwrite(STDERR, "ERROR: Attempt to construct com_jasonantman_SwitchSNMP threw exception: ".$e->getMessage()."\n");
	    continue;
	}

    doSwitchInfo($row['id'], $switch);
    $vlans = doSwitchInterfaces($row['id'], $switch);
    getSwitchMACs($row['id'], $switch, $vlans);
    fwrite(STDERR, "Finished with switch ".$row['hostname']."\n");
}

function doSwitchInfo($switchID, $switch)
{
  global $tblpfx;

  // update/add switch info
  $res = $switch->getSwitchInfo();
  $query = "INSERT INTO ".$tblpfx."switchinfo SET switch_id=$switchID,";
  $queryPart = "";
  if(isset($res['sysDescr'])){ $queryPart .= "sysDescr='".jaEscape(str_replace("\r\n", "\n", $res['sysDescr']))."',";}
  if(isset($res['sysName'])){ $queryPart .= "sysName='".jaEscape($res['sysName'])."',";}
  if(isset($res['sysLocation'])){ $queryPart .= "sysLocation='".jaEscape($res['sysLocation'])."',";}
  if(isset($res['lastTftpDownload'])){ $queryPart .= "lastTftpDownload='".jaEscape($res['lastTftpDownload'])."',";}
  if(isset($res['defaultGateway'])){ $queryPart .= "defaultGateway='".jaEscape($res['defaultGateway'])."',";}
  if(isset($res['System_Descr'])){ $queryPart .= "System_Descr='".jaEscape($res['System_Descr'])."',";}
  if(isset($res['Backplane_Descr'])){ $queryPart .= "Backplane_Descr='".jaEscape($res['Backplane_Descr'])."',";}
  if(isset($res['Supervisor_Descr'])){ $queryPart .= "Supervisor_Descr='".jaEscape($res['Supervisor_Descr'])."',";}
  if(isset($res['Supervisor_Firmware'])){ $queryPart .= "Supervisor_Firmware='".jaEscape($res['Supervisor_Firmware'])."',";}
  if(isset($res['Supervisor_Software'])){ $queryPart .= "Supervisor_Software='".jaEscape($res['Supervisor_Software'])."',";}
  if(isset($res['Supervisor_Serial'])){ $queryPart .= "Supervisor_Serial='".jaEscape($res['Supervisor_Serial'])."',";}
  if(isset($res['Supervisor_Model'])){ $queryPart .= "Supervisor_Model='".jaEscape($res['Supervisor_Model'])."',";}
  $queryPart .= 'updated_ts='.time();
  $query = $query.$queryPart." ON DUPLICATE KEY UPDATE ".$queryPart.";";
  mysql_query($query) or dbError($query, mysql_error());

  // update/add component info
  $res = $switch->getComponentInfo();
  foreach($res as $name => $arr)
    {
      $query = "INSERT INTO ".$tblpfx."switchparts SET switch_id=$switchID,name='".jaEscape($name)."',";
      $queryPart = "";
      if(isset($arr['System_Descr'])){ $queryPart .= "System_Descr='".mysql_real_escape_string($arr['System_Descr'])."',";}
      if(isset($arr['Serial'])){ $queryPart .= "Serial='".mysql_real_escape_string($arr['Serial'])."',";}
      if(isset($arr['Model'])){ $queryPart .= "Model='".mysql_real_escape_string($arr['Model'])."',";}
      if(isset($arr['Firmware'])){ $queryPart .= "Firmware='".mysql_real_escape_string($arr['Firmware'])."',";}
      if(isset($arr['Software'])){ $queryPart .= "Software='".mysql_real_escape_string($arr['Software'])."',";}
      $queryPart .= 'updated_ts='.time();
      $query = $query.$queryPart." ON DUPLICATE KEY UPDATE ".$queryPart.";";
      mysql_query($query) or dbError($query, mysql_error());
    }

}

function doSwitchInterfaces($switchID, $switch)
{
  global $tblpfx;

  $vlans = array();

    $fields = $switch->GET_PORT_RETURN_FIELDS;
    
    // get the info as RackMan interfaces
    $ports = $switch->getPorts();
    $rackManPorts = array();
    foreach($ports as $idx => $arr)
    {
	$temp = array();
	if(isset($arr['CISCO-portName'])){ $temp['alias'] = $arr['CISCO-portName'];} elseif(isset($arr["IFMIB-alias"])){ $temp['alias'] = $arr["IFMIB-alias"];}
	
	// figure out the type, as in 'opt_interface_types' table
	
	// serial sl0 slip
	if($arr['IFMIB-descr'] == "sl0" && $arr['IFMIB-type'] == "slip")
	{
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='Se' AND oit_connector='8P8C' AND oit_max_speed_bps=9600;";
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
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='E' AND oit_connector='8P8C' AND oit_max_speed_bps=10000000 AND oit_standard='10BASE-T';";
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
	// standard 10/100Base-TX port, CatOS
	elseif($arr['ENTITY-physDescr'] == '10/100BaseTX' && $arr['IFMIB-descr'] == '10/100 utp ethernet (cat 3/5)' && $arr['ENTITY-physClass'] == 'port')
	{
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='Fe' AND oit_standard='100BASE-TX' AND oit_media='UTP';";
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
	// standard GigE Copper port, IOS
	elseif($arr['ENTITY-physDescr'] == "Gigabit Ethernet Port" && $arr["ENTITY-physClass"] == "port")
	  {
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='Ge' AND oit_standard='1000BASE-T' AND oit_media='UTP';";
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
	// GigE SX port - CatOS
	elseif($arr['IFMIB-descr'] == 'short wave fiber gigabit ethernet' && $arr['ENTITY-physClass'] == 'port' && $arr['CISCO-portType'] == 'e1000BaseSX')
	{
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='Ge' AND oit_standard='1000BASE-SX' AND oit_media='MM Fiber';";
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
	// GigE SX port - IOS
	elseif($arr['ENTITY-physClass'] == 'port' && $arr["ENTITY-physDescr"] == "1000BaseSX")
	{
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='Ge' AND oit_standard='1000BASE-SX' AND oit_media='MM Fiber';";
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
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='Ge' AND oit_connector='Empty GBIC' AND oit_media='GBIC';";
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
	// propVirtual
	elseif($arr['IFMIB-type'] == "propVirtual")
	  {
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='pV' AND oit_media='Virtual';";
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
	// Null interface
	elseif($arr['IFMIB-name'] == "Nu0")
	  {
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='Nu' AND oit_standard='Null';";
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
	// weird GigE port, IOS (mixed copper/fiber)
	elseif($arr['IFMIB-type'] == "ethernetCsmacd" && substr($arr["IFMIB-descr"], 0, 15) == "GigabitEthernet")
	  {
	    $query = "SELECT oit_id FROM ".$tblpfx."opt_interface_types WHERE oit_type='Ge' AND oit_standard='1000BASE-T' AND oit_media='UTP';";
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
	// unknown interface type
	else
	{
	    $temp['oit_type'] = -1;
	    fwrite(STDERR, "Error: No interface type could be found for interface ".$idx."\n");
	}

	echo "========================\n"; // DEBUG
	$query = "INSERT INTO ".$tblpfx."ports SET switch_id=$switchID,IFMIB_index=$idx,";
	$queryPart = "";
	if(isset($arr['IFMIB-descr'])){ $queryPart .= "IFMIB_descr='".jaEscape($arr['IFMIB-descr'])."',";}
	if(isset($arr['IFMIB-name'])){ $queryPart .= "IFMIB_name='".jaEscape($arr['IFMIB-name'])."',";}
	if(isset($temp['oit_type']) && $temp['oit_type'] != -1){ $queryPart .= "oit_type=".$temp['oit_type'].",";}
	if(isset($temp['alias'])){ $queryPart .= "IFMIB_alias='".jaEscape($temp['alias'])."',";}
	if(isset($arr['IFMIB-type'])){ $queryPart .= "IFMIB_type='".jaEscape($arr['IFMIB-type'])."',";}
	if(isset($arr['IFMIB-speed'])){ $queryPart .= "max_speed_bps='".jaEscape($arr['IFMIB-speed'])."',";}
	if(isset($arr['IFMIB-macaddr'])){ $queryPart .= "macaddr='".jaEscape($arr['IFMIB-macaddr'])."',";}
	if(isset($arr['VLAN']) && $arr['VLAN']){ $queryPart .= "VLAN_num='".((int)$arr['VLAN'])."',";}
	if($arr['IFMIB-adminIsUp']){ $queryPart .= "admin_up=1,";}
	if($arr['IFMIB-operIsUp']){ $queryPart .= "oper_up=1,";}
	$queryPart .= 'updated_ts='.time();
	$query = $query.$queryPart." ON DUPLICATE KEY UPDATE ".$queryPart.";";
	mysql_query($query) or dbError($query, mysql_error());	
	echo $query."\n"; // DEBUG
	echo "==========================\n"; // DEBUG

	if(strlen($arr['VLAN']) >= 1){ $vlans[$arr['VLAN']] = $arr['VLAN'];}
    }

    return $vlans;
}

function getSwitchMACs($switchID, $switch, $vlans)
{
  global $tblpfx;

  $MACs = $switch->getPortMACs($vlans);

  echo var_dump($MACs)."\n\n"; // DEBUG

  trans_start();
  $query = "DELETE FROM ".$tblpfx."port_macs WHERE switch_id=".$switchID.";";
  $result = mysql_query($query) or dberror($query, mysql_error());

  // loop through the MACs for each port
  foreach($MACs as $index => $arr)
    {
      if($index == ""){ continue;} // the switch itself
      if(count($arr) == 0){ continue;} // no MACs on port
      
      foreach($arr as $mac)
	{
	  $query = "INSERT INTO ".$tblpfx."port_macs SET switch_id=$switchID,IFMIB_index=$index,mac='".mysql_real_escape_string($mac)."',updated_ts=".time().";";
	  echo $query."\n\n"; // DEBUG
	  $result = mysql_query($query) or dberror($query, mysql_error());
	}
      
    }

}

function trans_start()
{
  $query = "SET AUTOCOMMIT=0;";
  $result = mysql_query($query) or dberror($query, mysql_error());
  $query = "START TRANSACTION;";
  $result = mysql_query($query) or dberror($query, mysql_error());
}

function trans_commit()
{
  $query = "COMMIT;";
  $result = mysql_query($query) or dberror($query, mysql_error());
}

?>
