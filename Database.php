<?php
/**
 * Created by PhpStorm.
 * User: Łukasz
 * Date: 04.10.13
 * Time: 23:36
 */
include('TCGenerator.php');

class Database
{
    public function connect()
    {
        $this->readIniFile();
        $this->dblConnect();
    }

    public function disconnect()
    {
        unset($this->dbl);
    }

    private function readIniFile()
    {
        $inicfg = parse_ini_file("/etc/lms/lms.ini", true);

        define('DBL_HOST', 'localhost');
        define('DBL_USER', $inicfg['database']['user']);
        define('DBL_NAME', $inicfg['database']['database']);
        define('DBL_PASS', $inicfg['database']['password']);
    }

    private function dblConnect()
    {
        try {
            $this->dbl = new PDO('mysql:host=' . DBL_HOST . ';dbname=' . DBL_NAME, DBL_USER, DBL_PASS);
            $this->dbl->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->dbl->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $this->dbl->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $this->dbl->query("SET NAMES utf8");
        } catch (PDOException $e) {
            DEBUG ? die('DATABASE CONNECTION ERROR: ' . $e->getMessage()) : die();
        }
    }

    private function dehashIP($ipnum)
    {
        return long2ip($ipnum);
    }

    private function getMacForNode($nodeid)
    {
        try {
            $sql = "
                                SELECT
                                        `mac`
                                FROM
                                        `macs`
                                WHERE
                                        `nodeid` = :nodeid
                                ";

            $stmt = $this->dbl->prepare($sql);
            $stmt->bindValue(':nodeid', $nodeid, PDO::PARAM_INT);
            $stmt->execute();
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $retval[] = $result['mac'];
            }
            $stmt->closeCursor();
            return implode(",", $retval);
        } catch (PDOException $e) {
            DEBUG ? die('SQL Error: ' . $e->getMessage()) : die();
        }
    }

    public function getNodes()
    {
        try {
            $sql = "
                                SELECT
                                        `nodes`.*,
                                        `netdevices`.`nastype`,
                                        `nodegroups`.`description` AS majorband,
                                        `customers`.`pin`,
                                        CONCAT(`tariffs`.`downceil`,'/',`tariffs`.`upceil`) AS `minorband`,
                                        CONCAT(`tariffs`.`downceil_n`,'/',`tariffs`.`upceil_n`) AS `minorband_n`
                                FROM
                                        `nodes`
                                LEFT JOIN
                                        `customers`
                                ON
                                        `customers`.`id` = `nodes`.`ownerid`
                                LEFT JOIN
                                        `netdevices`
                                ON
                                        `netdevices`.`id` = `nodes`.`netdev`
                                LEFT JOIN
                                        `nodegroupassignments`
                                ON
                                        `nodegroupassignments`.`nodeid` = `nodes`.`id`
                                LEFT JOIN
                                        `nodegroups`
                                ON
                                        `nodegroups`.`id` = `nodegroupassignments`.`nodegroupid`
                                LEFT JOIN
                                        `nodeassignments`
                                ON
                                        `nodeassignments`.`nodeid` = `nodes`.`id`
                                LEFT JOIN
                                        `assignments`
                                ON
                                        `assignments`.`id` = `nodeassignments`.`assignmentid`
                                LEFT JOIN
                                        `tariffs`
                                ON
                                        `tariffs`.`id` = `assignments`.`tariffid`
                                WHERE
                                        `nodes`.`ownerid` > 0
                                ORDER BY
                                        `nodes`.`ipaddr` ASC
                                ";

            $stmt = $this->dbl->prepare($sql);
            $stmt->execute();
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                foreach ($result as $k => $v) {
                    $retval[$result['id']][$k] = trim(iconv('ISO-8859-2', 'UTF-8', $v));
                }
                $retval[$result['id']]['ipaddr'] = $this->dehashIP($retval[$result['id']]['ipaddr']);
                $retval[$result['id']]['ipaddr_pub'] = $this->dehashIP($retval[$result['id']]['ipaddr_pub']);
                $retval[$result['id']]['dhcpmac'] = $this->getMacForNode($result['id']);
                if ($retval[$result['id']]['nastype'] == 1000000 && $retval[$result['id']]['netdev']) {
                    $retval[$result['id']]['authmac'] = $this->getMacForApc($retval[$result['id']]['netdev']);
                } else {
                    $retval[$result['id']]['authmac'] = $retval[$result['id']]['dhcpmac'];
                }
            }
            $stmt->closeCursor();
            return $retval;
        } catch (PDOException $e) {
            DEBUG ? die('SQL Error: ' . $e->getMessage()) : die();
        }
    }
    public function generateWarningConfig($nodes){
        global $warningConfigFile;
        $config = "";
        foreach($nodes as $k => $v) {
            if ($v['warning'] == '1'){
               $config .= $v['ipaddr']."\n";
            }
        }
        file_put_contents($warningConfigFile, $config);
    }


    public function generateConfig($nodes)
    {
        global $configFile;
        $config = "# IP\t\tDOWN\tUP\tNDOWN\tNUP\tBLOK\tAUTHMAC\n\n";
        foreach ($nodes as $k => $v) {
            if ($v['majorband']) {
                $band = explode("/", $v['majorband']);
                $band_n = explode("/", $v['majorband']);
            } elseif ($v['minorband']) {
                $band = explode("/", $v['minorband']);
                $band_n = explode("/", $v['minorband_n']);
            } else {
                $band[0] = 0;
                $band[1] = 0;
                $band_n[0] = 0;
                $band_n[1] = 0;

                //$band[0] = 5120; $band[1] = 2048;
                //$band_n[0] = 20480; $band_n[1] = 20480;
            }
            if ($v['access'] == 0) {
                $block = 2;
            } elseif ($v['warning']) {
                $block = 1;
            } else {
                $block = 0;
            }
            $band_n[0] ? null : $band_n[0] = $band[0];
            $band_n[1] ? null : $band_n[1] = $band[1];
            $config .=
                $v['ipaddr'] . "\t" .
                $band[0] . "\t" .
                $band[1] . "\t" .
                $band_n[0] . "\t" .
                $band_n[1] . "\t" .
                $block . "\t" .
                $v['authmac'] . "\n";
        }
        if ($config) {
            if (file_exists($configFile)) {
                $fp = fopen($configFile, "r");
                $config_old = fread($fp, filesize($configFile));
                fclose($fp);
            }
            if (trim($config_old) != trim($config)) {
                $fp = fopen($configFile, 'w');
                fputs($fp, $config);
                fclose($fp);
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    private function getQosSpecificData(){
        try {
            $sql = "SELECT * FROM qosdata";
            $stmt = $this->dbl->prepare($sql);
            $stmt->execute();
            $targets = array("downceil", "downceil_n", "downrate", "downrate_n", "upceil", "upceil_n", "uprate", "uprate_n");
            while ($sqlresult = $stmt->fetch(PDO::FETCH_ASSOC)) {
                foreach ($sqlresult as $k => $v) {
                    $retval[$sqlresult['nodeID']][$k] = trim(iconv('ISO-8859-2', 'UTF-8', $v));
                }
                $retval[$sqlresult['nodeID']]['ipaddr'] = $this->dehashIP($retval[$sqlresult['nodeID']]['ipaddr']);
                foreach ($targets as $k => $t){
                    $retval[$sqlresult['id']][$t] = $retval[$sqlresult['id']][$t];
                }
            }
            $stmt->closeCursor();
            return $retval;

        }catch (PDOException $e) {
            DEBUG ? die('SQL Error: ' . $e->getMessage()) : die();
        }
    }
    public function generateQoSConfig()
    {
        global $qosConfigFile;
        global $qosScriptFile;
        global $qosDefaultSpeed;
        $nodes = $this->getQosSpecificData();
        $tcGenerator = new TCGenerator("eth1");
        $script = $tcGenerator->getHeader();
        $queueID = 10;
        $config = "# queueID\tIP\t\tDOWNRATE\tDOWNCEIL\tUPRATE\tUPCEIL\n\n";
        foreach ($nodes as $k => $v) {
            $band['queueID'] = dechex($queueID);
            $band['ip'] = $v['ipaddr'];
            if (!$band['ip'])
                continue;
            $targets = array("downceil", "downceil_n", "downrate", "downrate_n", "upceil", "upceil_n", "uprate", "uprate_n");
            foreach ($targets as $k => $t){
                if ($v[$t]){
                    $band[$t] = $v[$t];
                }
                else{
                    $band[$t] = $qosDefaultSpeed;
                }
            }
            $configLoc =
                $band['queueID'] . "\t" .
                $band['ip'] . "\t" .
                $band['downrate'] . "\t" .
                $band['downceil'] . "\t" .
                $band['uprate'] . "\t" .
                $band['upceil']
                . "\n";
            $scriptNode = $tcGenerator->getClass($band['queueID'], $band['downrate'], $band['downceil']);
            $scriptNode .= $tcGenerator->getQdisc($band['queueID']);
            $scriptNode .= $tcGenerator->getFilter($band['ip'], $band['queueID']);
            $queueID++;
            $config .= $configLoc;
            $script .= $scriptNode;
        }
        file_put_contents($qosConfigFile, $config);
        file_put_contents($qosScriptFile, $script);
    }

    public function shouldReload()
    {
        try {
            $sql = "
                                SELECT
                                        *
                                FROM
                                        `reload`
                                ";

            $stmt = $this->dbl->prepare($sql);
            $stmt->execute();
            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $retval[$result['command']] = $result['value'];
            }
            $stmt->closeCursor();
            return $retval['reload'] == '1';
        } catch (PDOException $e) {
            DEBUG ? die('SQL Error: ' . $e->getMessage()) : die();
        }
    }

    public function updateApi($command, $value)
    {
        try {
            $sql = "
                                UPDATE
                                        `reload`
                                SET
                                        `value` = :value
                                WHERE
                                        `command` = :command
                                LIMIT 1;
                                ";

            $stmt = $this->dbl->prepare($sql);
            $stmt->bindValue(':command', $command, PDO::PARAM_STR);
            $stmt->bindValue(':value', $value, PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();
            return true;
        } catch (PDOException $e) {
            DEBUG ? die('SQL Error: ' . $e->getMessage()) : die();
        }
    }
    private function getMacForApc($netdev)
    {
        try{
            $sql = "
                                SELECT
                                        `macs`.`mac`
                                FROM
                                        `nodes`
                                LEFT JOIN
                                        `macs`
                                ON
                                        `macs`.`nodeid` = `nodes`.`id`
                                WHERE
                                        `nodes`.`netdev` = :netdev
                                AND
                                        `nodes`.`ownerid` = 0
                                ";

            $stmt = $this->dbl->prepare($sql);
            $stmt->bindValue(':netdev', $netdev, PDO::PARAM_INT);
            $stmt->execute();
            while($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $retval[] = $result['mac'];
            }
            $stmt->closeCursor();
            return implode(",", $retval);
        } catch(PDOException $e){
            DEBUG ? die('SQL Error: '.$e->getMessage()) : die();
        }
    }

} 