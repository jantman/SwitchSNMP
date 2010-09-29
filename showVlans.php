#!/usr/bin/php
<?php
/**
 * Script to show VLAN information on a specific switch
 *
 * SwitchSNMP <http://switchsnmp.jasonantman.com>
 * A collection of classes and scripts to communicate with network devices via SNMP.
 *
 * Dependencies:
 * - PHP snmp
 * - PEAR Net_Ping
 *
 * Copyright (c) 2009 Jason Antman.
 * @author Jason Antman <jason@jasonantman.com> <http://www.jasonantman.com>
 *
 ******************************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or   
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to:
 * 
 * Free Software Foundation, Inc.
 * 59 Temple Place - Suite 330
 * Boston, MA 02111-1307, USA.
 ******************************************************************************
 * ADDITIONAL TERMS (pursuant to GPL Section 7):
 * 1) You may not remove any of the "Author" or "Copyright" attributions
 *     from this file or any others distributed with this software.
 * 2) If modified, you must make substantial effort to differentiate
 *     your modified version from the original, while retaining all
 *     attribution to the original project and authors.    
 ******************************************************************************
 * Please use the above URL for bug reports and feature/support requests.
 ******************************************************************************
 * $LastChangedRevision$
 * $HeadURL$
 ******************************************************************************
 */

require_once('com_jasonantman_SwitchSNMP.php');

$foo = doSwitchInterfaces(1, '128.6.31.131', 'public');

$debug = true;

echo '<pre>';
echo var_dump($foo);
echo '</pre>';

function doSwitchInterfaces($device_id, $ip, $rocommunity)
{
  global $debug;
    fwrite(STDERR, "Working on switch ".$ip." (device_id ".$device_id.")\n");
    try
    {
      $switch = new com_jasonantman_SwitchSNMP($ip, $rocommunity, $debug);
    }
    catch (Exception $e)
	{
	    fwrite(STDERR, "ERROR: Attempt to construct com_jasonantman_SwitchSNMP threw exception: ".$e->getMessage()."\n");
	    return false;
	}
    $fields = $switch->GET_PORT_RETURN_FIELDS;
    
    // get the info as RackMan interfaces
    $ports = $switch->getPorts();
    foreach($ports as $idx => $arr)
    {
	echo $idx."\t".$arr["IFMIB-index"]."\t".$arr["IFMIB-descr"]."\t".$arr['VLAN']."\n";
    }

    echo "++++++++++++++++++++++++++++++++++++++++\n";
    echo "++++++++++++++++++++++++++++++++++++++++\n";
    //echo var_dump($switch->getSwitchInfo())."\n";

    fwrite(STDERR, "Finished with switch ".$ip."\n");
    
}

// TODO - getPatches() function to associate MACs with swith interfaces

?>
