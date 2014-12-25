SwitchSNMP
==========

[![Project Status: Unsupported - The project has reached a stable, usable state but the author(s) have ceased all work on it. A new maintainer may be desired.](http://www.repostatus.org/badges/0.1.0/unsupported.svg)](http://www.repostatus.org/#unsupported)

SwitchSNMP is a collection of PHP classes, and scripts which utilize them, to
interact with network switches (at this time, Cisco CatOS and IOS and HP
ProCurve). The classes do things like showing and setting VLANs on ports,
listing interfaces on switches and MAC addresses connected to those
interfaces, and downloading switch configurations. 

I originally wrote this code for a personal project (that never ended up going
very far), but at a former job we had a large base of PHP code, which this
project was used to extend. At some point around August, 2011 development was
moved to an internal SVN server at Rutgers University - Central Systems and
Services/netops (http://css-svn.rutgers.edu/repos/SwitchSNMP/ probably
accessible from anywhere on campus) when I started work on it for my day
job. This version does not incorporate those changes.


