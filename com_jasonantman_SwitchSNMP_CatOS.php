<?php
/**
 * Switch class for Cisco CatOS switches.
 * Developed and tested on 2948G and 4912G running 8.4(11)GLX
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

// This class should only be called by com_jasonantman_SwitchSNMP.
class com_jasonantman_SwitchSNMP_CatOS implements com_jasonantman_SwitchSNMP_SwitchInterface
{

    /*
     * CLASS VARIABLES
     */
    private $IP = ""; // IP address of switch
    private $rocommunity = ""; // ro SNMP community string
    private $CISCO_STACK_MIB_portType = array(); // see end of this file
    public $GET_PORT_RETURN_FIELDS = array(); // see end of this file
    private $IFMIB_types = array(); // see end of this file
    private $ENTITY_MIB_classes = array(); // see end of this file
    public $type = "Cisco CatOS";
    /*
     * END CLASS VARIABLES
     */

    /*
     * constructor takes SNMP info as args, figures out what device/model class to use, and instantiates it
     * @arg rocommunity read-only community string (default 'public')
     * @arg IP IP address of switch (default "")
     * @return int 0 on success, 1 on invalid IP address, 2 if switch can't be pinged, 3 if SNMP fails, 4 if no class for switch type
     */
    public function __construct($IP, $rocommunity)
	{
	    if($IP == ""){ return 1;}
	    $this->rocommunity = $rocommunity;
	    $this->IP = $IP;
	    $this->makeConstants();
	}

    /*
     * Find out whether the switch at the specified IP should be handled by this class.
     * @return boolean
     */
    public function identifySwitch()
	{
	    $oid = ".1.3.6.1.2.1.1.1.0"; // SNMPv2-MIB::sysDescr.0
	    $sysDescr = snmpget($this->IP, $this->rocommunity, $oid);
	    if(strpos($sysDescr, "Cisco Catalyst Operating System Software"))
	    {
		return true;
	    }
	    // else:
	    return false;
	}

    /*
     * Get a list of all of the switch ports and information about them
     *  note - the *isUp fields contain boolean values.
     * RETURN ARRAY fields: "IFMIB-index", "IFMIB-descr", "IFMIB-name", "IFMIB-type", "IFMIB-speed", "IFMIB-macaddr"
     *   "IFMIB-adminIsUp", "IFMIB-operIsUp", "CISCO-modIndex", "CISCO-portIndex", "CISCO-portType", "CISCO-portName", 
     *    "ENTITY-physDescr", "ENTITY-physClass", "ENTITY-physName"
     * @return array
     */
    public function getPorts()
	{
      	    $myArray = array();
	    snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
	    $IFindicies = snmprealwalk($this->IP, $this->rocommunity, ".1.3.6.1.2.1.2.2.1.1");
	    $IFs = array(); // index numbers (integer)
	    foreach($IFindicies as $index)
	    {
		$IFs[$index] = $index;
	    }
            // we now have an array of all interface indicies

            // walk the CISCO-STACK-MIB and correlate with IF-MIB indexes
	    snmp_set_oid_numeric_print(TRUE); // we need numeric OIDs
	    $STACKmibWalk = snmprealwalk($this->IP, $this->rocommunity, ".1.3.6.1.4.1.9.5.1.4.1.1.11"); // CISCO-STACK-MIB::portIfIndex
	    $StackMibIndex = array();
	    foreach($STACKmibWalk as $key => $val)
	    {
		$temp = str_replace(".1.3.6.1.4.1.9.5.1.4.1.1.11.", "", $key);
		$StackMibIndex[$val] = $temp;
	    }
            // we now have an array of IF-MIB index => CISCO-STACK-MIB

	    foreach($IFs as $idx)
	    {
		$myIF = array();
		$myIF['IFMIB-index'] = $idx;
		snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
		$myIF['IFMIB-descr'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.2.2.1.2.".$idx); // IF-MIB::ifDescr
		$myIF['IFMIB-name'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.31.1.1.1.1.".$idx); // IF-MIB::ifName
		$temp = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.2.2.1.3.".$idx); // IF-MIB::ifType
		$myIF['IFMIB-type'] = $this->IFMIB_types[$temp];
		snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
		$myIF['IFMIB-speed'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.2.2.1.5.".$idx); // IF-MIB::ifSpeed
		snmp_set_valueretrieval(SNMP_VALUE_LIBRARY);
		$tempMAC = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.2.2.1.6.".$idx); // IF-MIB::ifPhysAddress
		$tempMAC = str_replace("STRING:", "", $tempMAC);
		$tempMAC = $this->parseMAC(trim($tempMAC));
		$tempMAC = strtoupper($tempMAC);
		$myIF['IFMIB-macaddr'] = $tempMAC;
		snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
		// IF-MIB::ifAdminStatus
		if(snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.2.2.1.7.".$idx) == 1)
		{ $myIF['IFMIB-adminIsUp'] = true; } else { $myIF['IFMIB-adminIsUp'] = false;}
		// IF-MIB::ifOperStatus
		if(snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.2.2.1.8.".$idx) == 1)
		{ $myIF['IFMIB-operIsUp'] = true; } else { $myIF['IFMIB-operIsUp'] = false;}
		
		// CISCO-STACK-MIB
		snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
		$myIF['CISCO-modIndex'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.4.1.9.5.1.4.1.1.1.".$StackMibIndex[$idx]); // CISCO-STACK-MIB::portModuleIndex
		$myIF['CISCO-portIndex'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.4.1.9.5.1.4.1.1.2.".$StackMibIndex[$idx]); // CISCO-STACK-MIB::portIndex
		$myIF['CISCO-portName'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.4.1.9.5.1.4.1.1.4.2.".$idx); // CISCO-STACK-MIB::portName
		$temp = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.4.1.9.5.1.4.1.1.5.".$StackMibIndex[$idx]);
		$myIF['CISCO-portType'] = $this->CISCO_STACK_MIB_portType[$temp]; // CISCO-STACK-MIB::portType

		// ENTITY-MIB (using indexes from CISCO-STACK-MIB)
		snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
		$ENTITY_index = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.4.1.9.5.1.4.1.1.25.".$StackMibIndex[$idx]); // CISCO-STACK-MIB::portEntPhysicalIndex
		$ENTITY_index;
		$myIF['ENTITY-physDescr'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.2.".$ENTITY_index); // ENTITY-MIB::entPhysicalDescr
		$temp = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.5.".$ENTITY_index); // ENTITY-MIB::entPhysicalClass - 10 is a port
		$myIF['ENTITY-physClass'] = $this->ENTITY_MIB_classes[$temp];
		snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
		$myIF['ENTITY-physName'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.7.".$ENTITY_index); // ENTITY-MIB::entPhysicalName
		$myArray[$idx] = $myIF;
	    }
	    return $myArray;
	}

    /*
     * Find out what MAC addresses are on the ports
     * @param array $vlans - unsupported in CatOS, so just ignored
     * @return array like ifIndex => array(MAC addresses)
     */
    public function getPortMACs($vlans)
	{
	    $resultArr = array();
	    $Dot1dTOif = array();

	    // dot1dBasePortIfIndex
	    snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
	    snmp_set_oid_numeric_print(true);
	    $temp = snmprealwalk($this->IP, $this->rocommunity, ".1.3.6.1.2.1.17.1.4.1.2");
	    $dot1dPortIndex = null;
	    foreach($temp as $oid => $val)
	    {
		$Dot1dTOif[(int)str_replace(".1.3.6.1.2.1.17.1.4.1.2.", "", $oid)] = $val;
		$reaultArr[$val] = array(); // create an array to hold the final values
	    }

	    // get the MACs from the bridge port index
	    $temp = snmprealwalk($this->IP, $this->rocommunity, ".1.3.6.1.2.1.17.4.3.1.2");
	    foreach($temp as $oid => $val)
	    {
		$if = $Dot1dTOif[$val];
		$decimalMAC = str_replace(".1.3.6.1.2.1.17.4.3.1.2.", "", $oid);
		// convert the decimal MAC to hex
		$final = "";
		$parts = explode(".", $decimalMAC);
		// iterate each part of the decimal MAC, convert to hex, LPAD with zeros, append to $final
		foreach($parts as $v)
		{
		    $v = (int)$v;
		    $v = dechex($v);
		    $final .= strtoupper(str_pad($v, 2, "0", STR_PAD_LEFT));
		}
		$final = trim($final, ":"); // trim the trailing ":"
		$resultArr[$if][] = $final;
	    }
	    return $resultArr;
	}

    /*
     * Only exists to move GIANT constants to the bottom of the source file.
     */
    private function makeConstants()
	{
	    // library values for CISCO-STACK-MIB::portType
	    // taken from 22-May-2008 version of CISCO-STACK-MIB-V1SMI.my
	    // for some reason, snmpget in PHP won't translate these...
	    $this->CISCO_STACK_MIB_portType = array(1 => 'other', 2 => 'cddi', 3 => 'fddi', 4 => 'tppmd', 5 => 'mlt3', 6 => 'sddi', 7 => 'smf', 8 => 'e10BaseT', 9 => 'e10BaseF', 10 => 'scf', 11 => 'e100BaseTX', 12 => 'e100BaseT4', 13 => 'e100BaseF', 14 => 'atmOc3mmf', 15 => 'atmOc3smf', 16 => 'atmOc3utp', 17 => 'e100BaseFsm', 18 => 'e10a100BaseTX', 19 => 'mii', 20 => 'vlanRouter', 21 => 'remoteRouter', 22 => 'tokenring', 23 => 'atmOc12mmf', 24 => 'atmOc12smf', 25 => 'atmDs3', 26 => 'tokenringMmf', 27 => 'e1000BaseLX', 28 => 'e1000BaseSX', 29 => 'e1000BaseCX', 30 => 'networkAnalysis', 31 => 'e1000Empty', 32 => 'e1000BaseLH', 33 => 'e1000BaseT', 34 => 'e1000UnsupportedGbic', 35 => 'e1000BaseZX', 36 => 'depi2', 37 => 't1', 38 => 'e1', 39 => 'fxs', 40 => 'fxo', 41 => 'transcoding', 42 => 'conferencing', 43 => 'atmOc12mm', 44 => 'atmOc12smi', 45 => 'atmOc12sml', 46 => 'posOc12mm', 47 => 'posOc12smi', 48 => 'posOc12sml', 49 => 'posOc48sms', 50 => 'posOc48smi', 51 => 'posOc48sml', 52 => 'posOc3mm', 53 => 'posOc3smi', 54 => 'posOc3sml', 55 => 'intrusionDetect', 56 => 'e10GBaseCPX', 57 => 'e10GBaseLX4', 59 => 'e10GBaseEX4', 60 => 'e10GEmpty', 61 => 'e10a100a1000BaseT', 62 => 'dptOc48mm', 63 => 'dptOc48smi', 64 => 'dptOc48sml', 65 => 'e10GBaseLR', 66 => 'chOc12smi', 67 => 'chOc12mm', 68 => 'chOc48ss', 69 => 'chOc48smi', 70 => 'e10GBaseSX4', 71 => 'e10GBaseER', 72 => 'contentEngine', 73 => 'ssl', 74 => 'firewall', 75 => 'vpnIpSec', 76 => 'ct3', 77 => 'e1000BaseCwdm1470', 78 => 'e1000BaseCwdm1490', 79 => 'e1000BaseCwdm1510', 80 => 'e1000BaseCwdm1530', 81 => 'e1000BaseCwdm1550', 82 => 'e1000BaseCwdm1570', 83 => 'e1000BaseCwdm1590', 84 => 'e1000BaseCwdm1610', 85 => 'e1000BaseBT', 86 => 'e1000BaseUnapproved', 87 => 'chOc3smi', 88 => 'mcr', 89 => 'coe', 90 => 'mwa', 91 => 'psd', 92 => 'e100BaseLX', 93 => 'e10GBaseSR', 94 => 'e10GBaseCX4', 95 => 'e10GBaseWdm1550', 96 => 'e10GBaseEdc1310', 97 => 'e10GBaseSW', 98 => 'e10GBaseLW', 99 => 'e10GBaseEW', 100 => 'lwa', 101 => 'aons', 102 => 'sslVpn', 103 => 'e100BaseEmpty', 104 => 'adsm', 105 => 'agsm', 106 => 'aces', 109 => 'intrusionProtect', 110 => 'e1000BaseSvc', 111 => 'e10GBaseSvc', 1000 => 'e1000BaseUnknown', 1001 => 'e10GBaseUnknown', 1002 => 'e10GBaseUnapproved', 1003 => 'e1000BaseWdmRxOnly', 1004 => 'e1000BaseDwdm3033', 1005 => 'e1000BaseDwdm3112', 1006 => 'e1000BaseDwdm3190', 1007 => 'e1000BaseDwdm3268', 1008 => 'e1000BaseDwdm3425', 1009 => 'e1000BaseDwdm3504', 1010 => 'e1000BaseDwdm3582', 1011 => 'e1000BaseDwdm3661', 1012 => 'e1000BaseDwdm3819', 1013 => 'e1000BaseDwdm3898', 1014 => 'e1000BaseDwdm3977', 1015 => 'e1000BaseDwdm4056', 1016 => 'e1000BaseDwdm4214', 1017 => 'e1000BaseDwdm4294', 1018 => 'e1000BaseDwdm4373', 1019 => 'e1000BaseDwdm4453', 1020 => 'e1000BaseDwdm4612', 1021 => 'e1000BaseDwdm4692', 1022 => 'e1000BaseDwdm4772', 1023 => 'e1000BaseDwdm4851', 1024 => 'e1000BaseDwdm5012', 1025 => 'e1000BaseDwdm5092', 1026 => 'e1000BaseDwdm5172', 1027 => 'e1000BaseDwdm5252', 1028 => 'e1000BaseDwdm5413', 1029 => 'e1000BaseDwdm5494', 1030 => 'e1000BaseDwdm5575', 1031 => 'e1000BaseDwdm5655', 1032 => 'e1000BaseDwdm5817', 1033 => 'e1000BaseDwdm5898', 1034 => 'e1000BaseDwdm5979', 1035 => 'e1000BaseDwdm6061', 1036 => 'e10GBaseWdmRxOnly', 1037 => 'e10GBaseDwdm3033', 1038 => 'e10GBaseDwdm3112', 1039 => 'e10GBaseDwdm3190', 1040 => 'e10GBaseDwdm3268', 1041 => 'e10GBaseDwdm3425', 1042 => 'e10GBaseDwdm3504', 1043 => 'e10GBaseDwdm3582', 1044 => 'e10GBaseDwdm3661', 1045 => 'e10GBaseDwdm3819', 1046 => 'e10GBaseDwdm3898', 1047 => 'e10GBaseDwdm3977', 1048 => 'e10GBaseDwdm4056', 1049 => 'e10GBaseDwdm4214', 1050 => 'e10GBaseDwdm4294', 1051 => 'e10GBaseDwdm4373', 1052 => 'e10GBaseDwdm4453', 1053 => 'e10GBaseDwdm4612', 1054 => 'e10GBaseDwdm4692', 1055 => 'e10GBaseDwdm4772', 1056 => 'e10GBaseDwdm4851', 1057 => 'e10GBaseDwdm5012', 1058 => 'e10GBaseDwdm5092', 1059 => 'e10GBaseDwdm5172', 1060 => 'e10GBaseDwdm5252', 1061 => 'e10GBaseDwdm5413', 1062 => 'e10GBaseDwdm5494', 1063 => 'e10GBaseDwdm5575', 1064 => 'e10GBaseDwdm5655', 1065 => 'e10GBaseDwdm5817', 1066 => 'e10GBaseDwdm5898', 1067 => 'e10GBaseDwdm5979', 1068 => 'e10GBaseDwdm6061', 1069 => 'e1000BaseBX10D', 1070 => 'e1000BaseBX10U', 1071 => 'e100BaseUnknown', 1072 => 'e100BaseUnapproved', 1073 => 'e100BaseSX', 1074 => 'e100BaseBX10D', 1075 => 'e100BaseBX10U', 1076 => 'e10GBaseBad', 1077 => 'e10GBaseZR', 1078 => 'e100BaseEX', 1079 => 'e100BaseZX', 1080 => 'e10GBaseLRM', 1081 => 'e10GBaseT');
	    
	    // an array of the fields returned by the getPort() method
	    $this->GET_PORT_RETURN_FIELDS = array("IFMIB-index", "IFMIB-descr", "IFMIB-name", "IFMIB-type", "IFMIB-speed", "IFMIB-macaddr", "IFMIB-adminIsUp", "IFMIB-operIsUp", "CISCO-modIndex", "CISCO-portIndex", "CISCO-portType", "ENTITY-physDescr", "CISCO-portName", "ENTITY-physClass", "ENTITY-physName");
	    
	    // taken from IANAifType-MIB October 10 2005
	    $this->IFMIB_types = array(1 => 'other', 2 => 'regular1822', 3 => 'hdh1822', 4 => 'ddnX25', 5 => 'rfc877x25', 6 => 'ethernetCsmacd', 7 => 'iso88023Csmacd', 8 => 'iso88024TokenBus', 9 => 'iso88025TokenRing', 10 => 'iso88026Man', 11 => 'starLan', 12 => 'proteon10Mbit', 13 => 'proteon80Mbit', 14 => 'hyperchannel', 15 => 'fddi', 16 => 'lapb', 17 => 'sdlc', 18 => 'ds1', 19 => 'e1', 20 => 'basicISDN', 21 => 'primaryISDN', 22 => 'propPointToPointSerial', 23 => 'ppp', 24 => 'softwareLoopback', 25 => 'eon', 26 => 'ethernet3Mbit', 27 => 'nsip', 28 => 'slip', 29 => 'ultra', 30 => 'ds3', 31 => 'sip', 32 => 'frameRelay', 33 => 'rs232', 34 => 'para', 35 => 'arcnet', 36 => 'arcnetPlus', 37 => 'atm', 38 => 'miox25', 39 => 'sonet', 40 => 'x25ple', 41 => 'iso88022llc', 42 => 'localTalk', 43 => 'smdsDxi', 44 => 'frameRelayService', 45 => 'v35', 46 => 'hssi', 47 => 'hippi', 48 => 'modem', 49 => 'aal5', 50 => 'sonetPath', 51 => 'sonetVT', 52 => 'smdsIcip', 53 => 'propVirtual', 54 => 'propMultiplexor', 55 => 'ieee80212', 56 => 'fibreChannel', 57 => 'hippiInterface', 58 => 'frameRelayInterconnect', 59 => 'aflane8023', 60 => 'aflane8025', 61 => 'cctEmul', 62 => 'fastEther', 63 => 'isdn', 64 => 'v11', 65 => 'v36', 66 => 'g703at64k', 67 => 'g703at2mb', 68 => 'qllc', 69 => 'fastEtherFX', 70 => 'channel', 71 => 'ieee80211', 72 => 'ibm370parChan', 73 => 'escon', 74 => 'dlsw', 75 => 'isdns', 76 => 'isdnu', 77 => 'lapd', 78 => 'ipSwitch', 79 => 'rsrb', 80 => 'atmLogical', 81 => 'ds0', 82 => 'ds0Bundle', 83 => 'bsc', 84 => 'async', 85 => 'cnr', 86 => 'iso88025Dtr', 87 => 'eplrs', 88 => 'arap', 89 => 'propCnls', 90 => 'hostPad', 91 => 'termPad', 92 => 'frameRelayMPI', 93 => 'x213', 94 => 'adsl', 95 => 'radsl', 96 => 'sdsl', 97 => 'vdsl', 98 => 'iso88025CRFPInt', 99 => 'myrinet', 100 => 'voiceEM', 101 => 'voiceFXO', 102 => 'voiceFXS', 103 => 'voiceEncap', 104 => 'voiceOverIp', 105 => 'atmDxi', 106 => 'atmFuni', 107 => 'atmIma', 108 => 'pppMultilinkBundle', 109 => 'ipOverCdlc', 110 => 'ipOverClaw', 111 => 'stackToStack', 112 => 'virtualIpAddress', 113 => 'mpc', 114 => 'ipOverAtm', 115 => 'iso88025Fiber', 116 => 'tdlc', 117 => 'gigabitEthernet', 118 => 'hdlc', 119 => 'lapf', 120 => 'v37', 121 => 'x25mlp', 122 => 'x25huntGroup', 123 => 'trasnpHdlc', 124 => 'interleave', 125 => 'fast', 126 => 'ip', 127 => 'docsCableMaclayer', 128 => 'docsCableDownstream', 129 => 'docsCableUpstream', 130 => 'a12MppSwitch', 131 => 'tunnel', 132 => 'coffee', 133 => 'ces', 134 => 'atmSubInterface', 135 => 'l2vlan', 136 => 'l3ipvlan', 137 => 'l3ipxvlan', 138 => 'digitalPowerline', 139 => 'mediaMailOverIp', 140 => 'dtm', 141 => 'dcn', 142 => 'ipForward', 143 => 'msdsl', 144 => 'ieee1394', 145 => 'if-gsn', 146 => 'dvbRccMacLayer', 147 => 'dvbRccDownstream', 148 => 'dvbRccUpstream', 149 => 'atmVirtual', 150 => 'mplsTunnel', 151 => 'srp', 152 => 'voiceOverAtm', 153 => 'voiceOverFrameRelay', 154 => 'idsl', 155 => 'compositeLink', 156 => 'ss7SigLink', 157 => 'propWirelessP2P', 158 => 'frForward', 159 => 'rfc1483', 160 => 'usb', 161 => 'ieee8023adLag', 162 => 'bgppolicyaccounting', 163 => 'frf16MfrBundle', 164 => 'h323Gatekeeper', 165 => 'h323Proxy', 166 => 'mpls', 167 => 'mfSigLink', 168 => 'hdsl2', 169 => 'shdsl', 170 => 'ds1FDL', 171 => 'pos', 172 => 'dvbAsiIn', 173 => 'dvbAsiOut', 174 => 'plc', 175 => 'nfas', 176 => 'tr008', 177 => 'gr303RDT', 178 => 'gr303IDT', 179 => 'isup', 180 => 'propDocsWirelessMaclayer', 181 => 'propDocsWirelessDownstream', 182 => 'propDocsWirelessUpstream', 183 => 'hiperlan2', 184 => 'propBWAp2Mp', 185 => 'sonetOverheadChannel', 186 => 'digitalWrapperOverheadChannel', 187 => 'aal2', 188 => 'radioMAC', 189 => 'atmRadio', 190 => 'imt', 191 => 'mvl', 192 => 'reachDSL', 193 => 'frDlciEndPt', 194 => 'atmVciEndPt', 195 => 'opticalChannel', 196 => 'opticalTransport', 197 => 'propAtm', 198 => 'voiceOverCable', 199 => 'infiniband', 200 => 'teLink', 201 => 'q2931', 202 => 'virtualTg', 203 => 'sipTg', 204 => 'sipSig', 205 => 'docsCableUpstreamChannel', 206 => 'econet', 207 => 'pon155', 208 => 'pon622', 209 => 'bridge', 210 => 'linegroup', 211 => 'voiceEMFGD', 212 => 'voiceFGDEANA', 213 => 'voiceDID', 214 => 'mpegTransport', 215 => 'sixToFour', 216 => 'gtp', 217 => 'pdnEtherLoop1', 218 => 'pdnEtherLoop2', 219 => 'opticalChannelGroup', 220 => 'homepna', 221 => 'gfp', 222 => 'ciscoISLvlan', 223 => 'actelisMetaLOOP', 224 => 'fcipLink', 225 => 'rpr', 226 => 'qam', 227 => 'lmp', 228 => 'cblVectaStar', 229 => 'docsCableMCmtsDownstream', 230 => 'adsl2');

	    // taken from ENTITY-MIB Dec 15 1999
	    $this->ENTITY_MIB_classes = array(1 => "other", 2 => "unknown", 3 => "chassis", 4 => "backplane", 5 => "container", 6 => "powerSupply", 7 => "fan", 8 => "sensor", 9 => "module", 10 => "port", 11 => "stack");

	}

    /*
     * Parses and formats a MAC to make sure each octet is two characters
     * @arg s string - the MAC, as shown by SNMP
     * @return string
     */
    private function parseMAC($s)
	{
	    $x = "";
	    $parts = explode(":", $s);
	    foreach($parts as $part)
	    {
		if($part == "0")
		{
		    $x .= "00";
		}
		else
		{
		    $x .= $part;
		}
	    }
	    $x = trim($x);
	    return $x;
	}

    /*
     * Get an array of IF-MIB indexes to port names.
     *
     * @TODO - implement this
     *
     * @return array
     */
    public function getPortIndexNameArray()
    {
      throw new Exception("Method not implemented in CatOS switch class."); // TODO - implement this method
    }

    /*
     * Set the specified port to the specified VLAN
     *
     * @TODO - implement this
     *
     * @param $ifIndex integer IF-MIB index number
     * @param $vlanNum integer VLAN number
     *
     * @return boolean
     */
    public function setPortVlan($ifIndex, $vlanNum)
    {
      throw new Exception("Method not implemented in CatOS switch class."); // TODO - implement this method
    }

    /*
     * Return identifying information about the switch, including model and serial numbers.
     *
     * RETURN ARRAY fields: 'sysDescr', 'sysContact', 'sysName', 'sysLocation', 'lastTftpDownload', 'defaultGateway', 'sysUpTime'
     *
     * @return array
     */
    public function getSwitchInfo()
    {
      $ret = array();

      snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
      $ret['sysDescr'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.1.1.0"); // SNMPv2-MIB::sysDescr
      $ret['sysContact'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.1.4.0"); // SNMPv2-MIB::sysContact
      $ret['sysName'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.1.5.0"); // SNMPv2-MIB::sysName
      $ret['sysLocation'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.1.6.0"); // SNMPv2-MIB::sysLocation

      try
	{
	  $ret['lastTftpDownload'] = @snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.16.19.6.0"); // RMON2-MIB::probeDownloadFile
	}
      catch (Exception $e)
	{
	  continue;
	}

      try
	{
	  $ret['defaultGateway'] = @snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.16.19.12.0"); // RMON2-MIB::netDefaultGateway
	}
      catch (Exception $e)
	{
	  continue;
	}

      $ret['sysUpTime'] = (float)snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.1.3.0"); // SNMPv2-MIB::sysUpTime - timeticks

      // get stuff from ENTITY-MIB
      snmp_set_oid_numeric_print(TRUE); // we need numeric OIDs
      $ENTITYmibWalk = snmprealwalk($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.7"); // ENTITY-MIB::entPhysicalName
      $EntityMibIndex = array();
      $EntityMibIndex_Sup = "";
      foreach($ENTITYmibWalk as $key => $val)
	{
	  $temp = str_replace(".1.3.6.1.2.1.47.1.1.1.1.7.", "", $key);
	  $EntityMibIndex[$val] = $temp;
	  if(strstr($val, "Linecard") != false)
	    {
	      $foo = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.2.".$temp);
	      if(strpos($foo, "Supervisor")){ $EntityMibIndex_Sup = $temp;} 
	    }
	}
      // we now have an array of IF-MIB::ifDescr to ENTITY-MIB::entPhysicalName

      if(isset($EntityMibIndex['Switch System']))
	{
	  $ret['System_Descr'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.2.".$EntityMibIndex['Switch System']);
	  $ret['System_Firmware'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.9.".$EntityMibIndex['Switch System']);
	  $ret['System_Software'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.10.".$EntityMibIndex['Switch System']);
	  $ret['System_Serial'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.11.".$EntityMibIndex['Switch System']);
	  $ret['System_Model'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.13.".$EntityMibIndex['Switch System']);
	}

      if(isset($EntityMibIndex['Backplane']))
	{
	  $ret['Backplane_Descr'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.2.".$EntityMibIndex['Backplane']);
	  $ret['Backplane_Firmware'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.9.".$EntityMibIndex['Backplane']);
	  $ret['Backplane_Software'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.10.".$EntityMibIndex['Backplane']);
	  $ret['Backplane_Serial'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.11.".$EntityMibIndex['Backplane']);
	  $ret['Backplane_Model'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.13.".$EntityMibIndex['Backplane']);
	}

      if($EntityMibIndex_Sup != "")
	{
	  $ret['Supervisor_Descr'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.2.".$EntityMibIndex_Sup);
	  $ret['Supervisor_Firmware'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.9.".$EntityMibIndex_Sup);
	  $ret['Supervisor_Software'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.10.".$EntityMibIndex_Sup);
	  $ret['Supervisor_Serial'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.11.".$EntityMibIndex_Sup);
	  $ret['Supervisor_Model'] = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.13.".$EntityMibIndex_Sup);
	}

      // get rid of anything empty
      foreach($ret as $key => $val)
	{
	  if(strlen(trim($val)) == 0){ unset($ret[$key]);}
	}

      return $ret;
    }

    /*
     * Return identifying information about the switch components, including model and serial numbers.
     *
     * RETURN ARRAY fields: name => array('Descr', 'Firmware', 'Software', 'Serial', 'Model')
     *
     * @return array
     */
    public function getComponentInfo()
    {
      $ret = array();

      snmp_set_valueretrieval(SNMP_VALUE_PLAIN);

      // get stuff from ENTITY-MIB
      snmp_set_oid_numeric_print(TRUE); // we need numeric OIDs
      $ENTITYmibWalk = snmprealwalk($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.7"); // ENTITY-MIB::entPhysicalName
      $EntityMibIndex = array();
      $EntityMibIndex_Sup = "";
      foreach($ENTITYmibWalk as $key => $val)
	{
	  $temp = str_replace(".1.3.6.1.2.1.47.1.1.1.1.7.", "", $key);
	  $EntityMibIndex[$val] = $temp;
	  if(strstr($val, "Linecard") != false)
	    {
	      $foo = snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.2.".$temp);
	      if(strpos($foo, "Supervisor")){ $EntityMibIndex_Sup = $temp;} 
	    }
	}
      // we now have an array of IF-MIB::ifDescr to ENTITY-MIB::entPhysicalName

      foreach($EntityMibIndex as $name => $index)
	{
	  $arr = array();
	  $arr['System_Descr'] = trim(snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.2.".$index));
	  $arr['Firmware'] = trim(snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.9.".$index));
	  $arr['Software'] = trim(snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.10.".$index));
	  $arr['Serial'] = trim(snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.11.".$index));
	  $arr['Model'] = trim(snmpget($this->IP, $this->rocommunity, ".1.3.6.1.2.1.47.1.1.1.1.13.".$index));
	  foreach($arr as $key => $val)
	    {
	      if(trim($val) == ""){ unset($arr[$key]);}
	    }
	  if(count($arr) > 1)
	    {
	      $ret[$name] = $arr;
	    }
	}

      return $ret;
    }



}

?>