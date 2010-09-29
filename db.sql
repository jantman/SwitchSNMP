-- SwitchSNMP <http://switchsnmp.jasonantman.com>
-- A collection of classes and scripts to communicate with network devices via SNMP.
--
-- Dependencies:
-- - PHP snmp
-- - PEAR Net_Ping
--
-- Copyright (c) 2009 Jason Antman.
-- @author Jason Antman <jason@jasonantman.com> <http://www.jasonantman.com>
--
--*****************************************************************************
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or   
-- (at your option) any later version.
-- 
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to:
-- 
-- Free Software Foundation, Inc.
-- 59 Temple Place - Suite 330
-- Boston, MA 02111-1307, USA.
--*****************************************************************************
-- ADDITIONAL TERMS (pursuant to GPL Section 7):
-- 1) You may not remove any of the "Author" or "Copyright" attributions
--     from this file or any others distributed with this software.
-- 2) If modified, you must make substantial effort to differentiate
--     your modified version from the original, while retaining all
--     attribution to the original project and authors.    
--*****************************************************************************
-- Please use the above URL for bug reports and feature/support requests.
--*****************************************************************************
-- $LastChangedRevision$
-- $HeadURL$
--*****************************************************************************
--

-- MySQL dump 10.13  Distrib 5.1.35, for koji-linux-gnu (x86_64)
--
-- Host: localhost    Database: phpsa
-- ------------------------------------------------------
-- Server version	5.1.35

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `switchsnmp_device_macs`
--

DROP TABLE IF EXISTS `switchsnmp_device_macs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `switchsnmp_device_macs` (
  `mac` varchar(12) NOT NULL,
  `ip` varchar(15) DEFAULT NULL,
  `hostname` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`mac`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `switchsnmp_opt_interface_types`
--

DROP TABLE IF EXISTS `switchsnmp_opt_interface_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `switchsnmp_opt_interface_types` (
  `oit_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `oit_type` varchar(20) DEFAULT NULL,
  `oit_media` varchar(20) DEFAULT NULL,
  `oit_max_speed_bps` int(10) unsigned DEFAULT NULL,
  `oit_connector` varchar(20) DEFAULT NULL,
  `oit_standard` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`oit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `switchsnmp_port_macs`
--

DROP TABLE IF EXISTS `switchsnmp_port_macs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `switchsnmp_port_macs` (
  `switch_id` int(10) unsigned NOT NULL,
  `IFMIB_index` int(10) unsigned NOT NULL,
  `mac` varchar(12) NOT NULL,
  `updated_ts` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`switch_id`,`IFMIB_index`,`mac`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `switchsnmp_ports`
--

DROP TABLE IF EXISTS `switchsnmp_ports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `switchsnmp_ports` (
  `switch_id` int(10) unsigned NOT NULL,
  `IFMIB_index` int(10) unsigned NOT NULL,
  `IFMIB_descr` varchar(100) DEFAULT NULL,
  `IFMIB_name` varchar(50) DEFAULT NULL,
  `IFMIB_alias` varchar(100) DEFAULT NULL,
  `IFMIB_type` varchar(50) DEFAULT NULL,
  `oit_type` int(10) unsigned DEFAULT NULL,
  `max_speed_bps` int(10) unsigned DEFAULT NULL,
  `macaddr` varchar(30) DEFAULT NULL,
  `VLAN_num` int(10) unsigned DEFAULT NULL,
  `updated_ts` int(10) unsigned DEFAULT NULL,
  `admin_up` tinyint(1) unsigned DEFAULT '0',
  `oper_up` tinyint(1) unsigned DEFAULT '0',
  PRIMARY KEY (`switch_id`,`IFMIB_index`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `switchsnmp_switch`
--

DROP TABLE IF EXISTS `switchsnmp_switch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `switchsnmp_switch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hostname` varchar(100) DEFAULT NULL,
  `rocommunity` varchar(100) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` tinyint(1) DEFAULT '1',
  `updated_ts` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `switchsnmp_switchinfo`
--

DROP TABLE IF EXISTS `switchsnmp_switchinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `switchsnmp_switchinfo` (
  `switch_id` int(10) unsigned NOT NULL,
  `sysDescr` text,
  `sysName` varchar(100) DEFAULT NULL,
  `sysLocation` varchar(50) DEFAULT NULL,
  `lastTftpDownload` varchar(255) DEFAULT NULL,
  `defaultGateway` varchar(20) DEFAULT NULL,
  `System_Descr` varchar(255) DEFAULT NULL,
  `Backplane_Descr` varchar(255) DEFAULT NULL,
  `Supervisor_Descr` varchar(255) DEFAULT NULL,
  `Supervisor_Firmware` varchar(100) DEFAULT NULL,
  `Supervisor_Software` varchar(100) DEFAULT NULL,
  `Supervisor_Serial` varchar(50) DEFAULT NULL,
  `Supervisor_Model` varchar(50) DEFAULT NULL,
  `updated_ts` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`switch_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `switchsnmp_switchparts`
--

DROP TABLE IF EXISTS `switchsnmp_switchparts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `switchsnmp_switchparts` (
  `switch_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `System_Descr` varchar(100) DEFAULT NULL,
  `Serial` varchar(100) DEFAULT NULL,
  `Model` varchar(100) DEFAULT NULL,
  `Firmware` varchar(100) DEFAULT NULL,
  `Software` varchar(100) DEFAULT NULL,
  `updated_ts` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`switch_id`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-09-22 18:06:50
