<?php
/**
 * Created by PhpStorm.
 * User: Łukasz
 * Date: 05.10.13
 * Time: 15:38
 */

class TCGenerator {
    public function TCGenerator($device){
        $this->device = $device;
        //3rd byte
        $this->hashTablesIndices[164] = 100;
        $this->hashTablesIndices[165] = 101;
    }
    private $device;
    private $hashTablesIndices;
    public function getClass($classID, $rate, $ceil){
        if ($rate == $ceil){
            $rate = (string)(intval($rate)/2);
        }
        return "\$TC class add dev " . $this->device . " parent 1:1 classid 1:0x" . $classID . " hfsc sc rate " . $rate . "kbit ul rate " . $ceil . "kbit\n";
    }
    public function getQdisc($classID){
        return "\$TC qdisc add dev " . $this->device . " parent 1:0x" .$classID . " sfq perturb 10\n";
    }
    public function getFilter($ip, $classID){
        //157.158.164.0 #hashtable 100
        //157.158.165.0 #hashtable 101
        $ipBytes = explode(".", $ip);
        return "\$TC filter add dev " . $this->device . " parent 1:0 protocol ip prio 1 u32 ht " . $this->hashTablesIndices[$ipBytes[2]] . ":" . dechex($ipBytes[3]) . " match ip dst " . $ip . " flowid 1:0x" .$classID . "\n";
    }
    public function getHeader(){
        $script = "#!/bin/bash
IMQ_D=$this->device
TC=/sbin/tc

DOWN=98
DOWN_UNCLASSIFIED=50 #unclassified
DOWN_PRIO_FAST=50

# kasowanie poprzednich kolejek
\$TC qdisc del root dev \$IMQ_D > /dev/null 2>&1

#
# DOWNLOAD
#

\$TC qdisc add dev \$IMQ_D root handle 1:0 hfsc default 9
\$TC class add dev \$IMQ_D parent 1:0 classid 1:1 hfsc ls rate \${DOWN}mbit ul rate \${DOWN}mbit # rate == m2

# default
\$TC class add dev \$IMQ_D parent 1:1 classid 1:9 hfsc ls rate \${DOWN_UNCLASSIFIED}mbit ul rate \${DOWN}mbit
\$TC qdisc add dev \$IMQ_D parent 1:9 sfq perturb 10

# PRIO + FAST #tutaj jakies ACK
\$TC class add dev \$IMQ_D parent 1:1 classid 1:2 hfsc sc d 20ms rate \${DOWN_PRIO_FAST}mbit ul rate \${DOWN}mbit # sc = rt + ls (service curve = real time + link sharing) ul (upper limit)
\$TC qdisc add dev \$IMQ_D parent 1:2 sfq perturb 10

# NOAUTH
\$TC class add dev \$IMQ_D parent 1:1 classid 1:8 hfsc sc d 20ms rate \${DOWN_PRIO_FAST}mbit ul rate \${DOWN}mbit # sc = rt + ls (service curve = real time + link sharing) ul (upper limit)
\$TC qdisc add dev \$IMQ_D parent 1:8 sfq perturb 10

#HASH TABLES
#ELEKTRON 157.158.164.0/24
\$TC filter add dev \$IMQ_D parent 1:0 prio 1 handle 100: protocol ip u32 divisor 256
\$TC filter add dev \$IMQ_D protocol ip parent 1:0 prio 1 u32 ht 800:: match ip dst 157.158.164.0/24 hashkey mask 0x000000ff at 16 link 100:
#ELEKTRON 157.158.165.0
\$TC filter add dev \$IMQ_D parent 1:0 prio 1 handle 101: protocol ip u32 divisor 256
\$TC filter add dev \$IMQ_D protocol ip parent 1:0 prio 1 u32 ht 800:: match ip dst 157.158.165.0/24 hashkey mask 0x000000ff at 16 link 101:
#ELEKTRON NO AUTH
\$TC filter add dev \$IMQ_D parent 1:0 protocol ip prio 2 u32 match ip dst 10.0.0.0/24 flowid 1:8\n";

        return $script;
    }

} 