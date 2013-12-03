<?php
/**
 * Created by PhpStorm.
 * User: Łukasz
 * Date: 04.10.13
 * Time: 23:41
 */
include('config.php');
include('Database.php');

$core = new Database();
$core->connect();
if ($core->shouldReload()) {
    //generate config
    //generate lmg-mgc part
    exec('sudo /bin/lms-mgc');
    exec('sudo /etc/init.d/isc-dhcp-server restart');
    exec('sudo arp -f /etc/ethers');
    //konfig dla denied jest z lms-mgc
    exec('sudo /serwer/rc.denied start');
    $nodes = $core->getNodes();
    $core->generateWarningConfig($nodes);
    exec('sudo /serwer/rc.warning start');
    $core->updateApi('reload', '0');
}
$core->disconnect();
unset($core);

if (date('H') >= 17 && date('H' < 23)){
    exec('/serwer/generator/qos.sh');
//    exec('/serwer/qosClients');
}
?>
