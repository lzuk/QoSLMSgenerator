<?php
/**
 * Created by PhpStorm.
 * User: Łukasz
 * Date: 05.10.13
 * Time: 15:38
 */

class TCGenerator {
    public function getClass($device, $classID, $rate, $ceil){
        if ($rate == $ceil){
            $rate = (string)(intval($rate)/2);
        }
        return "\$TC class add dev " . $device . " parent 1:1 classid 1:0x" . $classID . " hfsc sc rate " . $rate . "kbit ul rate " . $ceil . "kbit\n";
    }
    public function getQdisc($device, $classID){
        return "\$TC qdisc add dev " . $device . " parent 1:0x" .$classID . " sfq perturb 10\n";
    }
    public function getFilter($device, $ip, $classID){
        //157.158.164.0 #hashtable 100
        //157.158.165.0 #hashtable 101
        $ipBytes = explode(".", $ip);
        $hashTableHandle = "";
        if ($ipBytes[2] == 164){
            $hashTableHandle = 100;
        }
        if ($ipBytes[2] == 165){
            $hashTableHandle = "101";
        }
        return "\$TC filter add dev " . $device . " parent 1:0 protocol ip prio 1 u32 ht " . $hashTableHandle . ":" . dechex($ipBytes[3]) . " match ip dst " . $ip . " flowid 1:0x" .$classID . "\n";
    }

} 