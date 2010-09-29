<?php
/**
 * Interface to be implemented by all switch classes
 *
 * SwitchSNMP <http://switchsnmp.jasonantman.com>
 * A collection of classes and scripts to communicate with network devices via SNMP.
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


interface com_jasonantman_SwitchSNMP_SwitchInterface
{

    /*
     * constructor takes SNMP info as args, figures out what device/model class to use, and instantiates it
     * @arg rocommunity read-only community string (default 'public')
     * @arg IP IP address of switch (default "")
     * @return int 0 on success, 1 on invalid IP address, 2 if switch can't be pinged, 3 if SNMP fails, 4 if no class for switch type
     */
    public function __construct($IP, $rocommunity);

    /*
     * Find out whether the switch at the specified IP should be handled by this class.
     * @return boolean - true if this class claims the switch, false otherwise
     */
    public function identifySwitch();

    /*
     * Get a list of all of the switch ports and information about them
     *  note - the *isUp fields contain boolean values.
     * RETURN ARRAY fields: "IFMIB-index", "IFMIB-descr", "IFMIB-name", "IFMIB-type", "IFMIB-speed", "IFMIB-macaddr"
     *   "IFMIB-adminIsUp", "IFMIB-operIsUp", "CISCO-modIndex", "CISCO-portIndex", "CISCO-portType", "CISCO-portName", 
     *    "ENTITY-physDescr", "ENTITY-physClass", "ENTITY-physName"
     * @return array
     */
    public function getPorts();

    /*
     * Get an array of IF-MIB indexes to port names.
     * @return array
     */
    public function getPortIndexNameArray();

    /*
     * Return identifying information about the switch, including model and serial numbers.
     *
     * RETURN ARRAY fields: 'sysDescr', 'sysContact', 'sysName', 'sysLocation', 'lastTftpDownload', 'defaultGateway', 'sysUpTime'
     *
     * @return array
     */
    public function getSwitchInfo();

    /*
     * Return identifying information about the switch components, including model and serial numbers.
     *
     * RETURN ARRAY fields: name => array('Descr', 'Firmware', 'Software', 'Serial', 'Model')
     *
     * @return array
     */
    public function getComponentInfo();

    /*
     * Find out what MAC addresses are on the ports
     * @param $vlans array - array of vlan numbers to check
     * @return array like ifIndex => array(MAC addresses)
     */
    public function getPortMACs($vlans);

    /*
     * Set the specified port to the specified VLAN
     *
     * @param $ifIndex integer IF-MIB index number
     * @param $vlanNum integer VLAN number
     *
     * @return boolean
     */
    public function setPortVlan($ifIndex, $vlanNum);

}

?>