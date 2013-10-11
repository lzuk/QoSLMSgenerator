<?php
/**
 * Created by PhpStorm.
 * User: Łukasz
 * Date: 05.10.13
 * Time: 12:16
 */

include('config.php');
include('Database.php');
$core = new Database();
$core->connect();
$core->generateQoSConfig();
$core->disconnect();
unset($core);
exec("dos2unix $qosScriptFile");
?>