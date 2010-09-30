<?php

/**
 * com_jasonantman_SwitchSNMP main class.
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

require_once("Net/Ping.php");
require_once('com_jasonantman_SwitchSNMP_SwitchInterface.php');

/**
 * Main com_jasonantman_SwitchSNMP class (constructed by client scripts)
 * this is a generic class to do some SNMP things with switches.
 * this class should extend a manufacturer- or model-specific class
 * the classes that it extends should be in a file with a name containing "SwitchSNMP" and have the class and file named the same
 * each class should implement a identifySwitch() method
 * returning boolean true if the switch at the specified IP is the right type for the class
 */
class com_jasonantman_SwitchSNMP
{
    private $switch; // reference to our platform-specific switch object
    public $type; // manufacturer and OS type
    private $debug;

    /*
     * constructor takes SNMP info as args, figures out what device/model class to use, and instantiates it
     * @arg IP IP address of switch
     * @arg rocommunity read-only community string
     */
    public function __construct($IP, $rocommunity, $debug)
	{
	    $this->debug = $debug;

	    if($IP == ""){ throw new InvalidArgumentException("IP address not specified.");}
	    $this->rocommunity = $rocommunity;
	    $this->IP = $IP;
	    // ping the switch
	    $ping = Net_Ping::factory();
	    if(PEAR::isError($ping))
	    {
		echo $ping->getMessage();
	    }
	    else
	    {
		$ping->setArgs(array('count' => 2));
		$result = $ping->ping($this->IP);
		if(PEAR::isError($result) || $result->_received == 0)
		{
		    throw new Exception("No reply on ping.");
		}
	    }

	    // try to get SNMP on the switch
	    if(! @snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.1.1.0"))
	    {
		throw new Exception("Cannot execute SNMPget on specified IP.");
	    }

	    // try each of the classes we have
	    $mySwitch = null;
	    
	    // DEBUG - should use getSwitchClasses() and a loop
	    $switchClasses = $this->getSwitchClasses();
	    if(count($switchClasses) == 0){ throw new Exception("No switch classes found.");}
	    
	    foreach($switchClasses as $path)
	      {
		$className = substr($path, 0, strlen($path) - 4);
		require_once($path);
		if($this->debug) { fwrite(STDERR, "Checking switch class ".$className." ........ ");}
		$temp = new $className($this->IP, $this->rocommunity, $this->debug);
		if($this->debug) { fwrite(STDERR, "Done.\n");}
		if($temp->identifySwitch()){ $mySwitch = $temp; break;}
	      }
	    // loop through the others
	    // END DEBUG

	    // get the right class for the switch
	    if($mySwitch == null)
	    {
		throw new Exception("No class found to handle device.");
	    }
	    else
	      {
		  if($this->debug) { fwrite(STDERR, "Found switch type as: ".$className."\n");}
	      }
	    $this->switch = $temp;
	    $this->GET_PORT_RETURN_FIELDS = $mySwitch->GET_PORT_RETURN_FIELDS;
	    $this->type = $mySwitch->type;
	}

    /*
     * This function finds all of the switch type classes in the current directory.
     * @return array array like fileName => className
     */
    private static function getSwitchClasses()
	{
	  $foo = array();
	  $dh = opendir(".");
	  while($entry = readdir($dh))
	    {
	      if(is_file($entry) && substr($entry, 0, 27) == "com_jasonantman_SwitchSNMP_" && substr($entry, strlen($entry) - 4) == ".php" && $entry != 'com_jasonantman_SwitchSNMP_SwitchInterface.php')
		{
		  $foo[] = $entry;
		}
	    }
	  closedir($dh);
	  return $foo;
	}

    /*
     * PHP5 function to autoload a class that hasn't been loaded yet
     */
    function __autoload($class_name)
	{
	    require_once $class_name . '.php';
	}

    /*
     * Just calls our mySwitch's getPorts() and returns the return value thereof.
     */
    public function getPorts()
	{
	    return $this->switch->getPorts();
	}

    /*
     * Just calls our mySwitch's getSwitchInfo() and returns the return value thereof.
     */
    public function getSwitchInfo()
	{
	    return $this->switch->getSwitchInfo();
	}

    /*
     * Just calls out mySwitch's getPortMACs() method and returns the value thereof.
     */
    public function getPortMACs($vlans)
	{
	    return $this->switch->getPortMACs($vlans);
	}

    public function getComponentInfo()
    {
      return $this->switch->getComponentInfo();
    }

    /*
     * Just calls the method on the switch.
     */
    public function setPortVlan($ifIndex, $vlanNum)
    {
	return $this->switch->setPortVlan($ifIndex, $vlanNum);
    }

    public function getPortIndexNameArray()
    {
	return $this->switch->getPortIndexNameArray();
    }

    public function copyRunningConfigTftp($tftp_server, $upload_path, $local_path)
    {
      if($this->debug){ fwrite(STDERR, __CLASS__."->".__FUNCTION__.": using local path: $local_path\n");}

      // check that the local file exists and has correct permissions
      if(! file_exists($local_path))
	{
	  throw new Exception("copyRunningConfigTftp: local file $local_path does not exist, cannot continue.");
	}

      if(fileperms($local_path) != 33279) // 777
	{
	  throw new Exception("copyRunningConfigTftp: local file $local_path permissions wrong (are ".decoct(fileperms($local_path))." should be 0777), cannot continue.");
	}

      $before_mtime = filemtime($local_path);

      // todo - catch exceptions
      $foo = $this->switch->copyRunningConfigTftp($tftp_server, $upload_path, $local_path);

      if(! $foo){ return false;}

      if(filemtime($local_path) == $before_mtime)
	{
	  // file was not changed, error
	  throw new Exception("copyRunningConfigTftp: local file was not modified, error in TFTP operation.");
	}
      return true;
    }

}

?>