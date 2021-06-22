<?php
namespace App\Controllers;

use Liman\Toolkit\Shell\Command;

class SambaController{
    function verifyInstallation(){
        if(trim(runCommand('dpkg -s sambahvl | grep "Status" | grep -w "install" 1>/dev/null 2>/dev/null && echo "1" || echo "0"')) == "1"){
            return respond(true,200);
        }else{
            return respond(false,200);
        }
    } 

    function verifyInstallationPhp(){
        if(trim(runCommand('dpkg -s smbpy | grep "Status" | grep -w "install" 1>/dev/null 2>/dev/null && echo "1" || echo "0"')) == "1"){
            return true;
        }else{
            return false;
        }

    }

    function installSmbPackage(){   
        $commandLine1 = "apt install gnupg2 ca-certificates -y";
        $commandLine2 = "echo 'deb [arch=amd64] http://depo.aciklab.org/ onyedi main' | sudo tee /etc/apt/sources.list.d/acikdepo.list";
        $commandLine3 = "wget --no-check-certificate -qO - http://depo.aciklab.org/public.key | sudo apt-key add -";
        $commandLine4 = "apt update";
        $commandLine5 = "bash -c 'DEBIAN_FRONTEND=noninteractive apt install sambahvl -qqy >/tmp/smbpyLog 2>&1 & disown'";

        runCommand(sudo() . $commandLine1);
        runCommand(sudo() . $commandLine2);
        runCommand(sudo() . $commandLine3);
        runCommand(sudo() . $commandLine4);
        runCommand(sudo() . $commandLine5);

        return respond($a,200);
    }


    function observeInstallation(){
        if(verifyInstallationPhp() == true){
            $res = "smbHVL paketi zaten var !";
            
            return respond($res, 202);
        }

        if(verifyInstallationPhp() == false){
            $log = runCommand(sudo() . 'cat /tmp/smbpyLog');
            
            return respond($log, 200);
        }
    }

    function isFileExists($filePath){
        $existsCommand = 'test -e '. $filePath .' && echo 1 || echo 0';
        $existsFlag = runCommand(sudo() . $existsCommand);

        if($existsFlag == 1){
            return true;
        }

        if($existsFlag == 0){
            return false;
        }

    }

    function verifyDomain(){
        $smbConfigPath = "/etc/samba/smb.conf";

        if($this->isFileExists($smbConfigPath) == true){
            return respond(true,200);
        }
        
        if($this->isFileExists($smbConfigPath) == false){
            return respond(false,200);
        }
    }

    function createSambaDomain(){
        $domainName = extensionDb('domainName');
        $domainPassword = extensionDb('domainPassword');

        $createDomainCommand = "smb-create-domain -d " . $domainName . " -p " . $domainPassword;
        runCommand(sudo() . $createDomainCommand);
    }

    function returnDomainInformations(){
        $domainName = extensionDb('domainName');
        $getDomainInformationCommand = "samba-tool domain info " . $domainName;

        $domainInformations = runCommand(sudo() . $getDomainInformationCommand);
        return respond($domainInformations,200);
    }

    function returnSambaServiceStatus(){
        $output = runCommand(sudo() . "systemctl is-active samba4.service");

        if (trim($output) == "active") {
            return respond(true,200);
        } 
        else {
            return respond(false,200);
        }
    }

    function sambaLog(){
        $command = "systemctl status samba4.service";

        $output = runCommand(sudo() . $command);

        return respond($output, 200);
    }

    //FSMO

    function returnRolesTable(){
        $allData = runCommand(sudo()."samba-tool fsmo show");
        $allDataList = explode("\n",$allData);
        $dict = [
            "SchemaMasterRole" => "schema",
            "InfrastructureMasterRole" => "infrastructure",
            "RidAllocationMasterRole" => "rid",
            "PdcEmulationMasterRole" => "pdc",
            "DomainNamingMasterRole" => "naming",
            "DomainDnsZonesMasterRole" => "domaindns",
            "ForestDnsZonesMasterRole" => "forestdns",
        ];
        $data = [];
        for($i=0; $i<count($allDataList); $i++){
            $item = $allDataList[$i];
            $itemList = explode(",",$item);

            $nameItem = explode("=",$itemList[1]);
            $nameItem = $nameItem[1];

            if ($nameItem != "") {
                $roleItem = explode(" ",$itemList[0]);
                $roleItem = $roleItem[0];
                $data[] = [
                    "role" => $roleItem,
                    "name" => $nameItem,
                    "contraction" => $dict[$roleItem]
                    
                ];  
            }
      
        }
        return view('table', [
            "value" => $data,
            "title" => ["Rol","Sunucu","*hidden*"],
            "display" => ["role","name","contraction:contraction"],
            "menu" => [
                "Bu rolü al" => [
                    "target" => "takeTheRole",
                    "icon" => "fa-share"
                ],
            ],
        ]);
    }

    function takeTheRole(){
        $contraction = request("contraction");
        $output=runCommand(sudo()."samba-tool fsmo transfer --role=$contraction -UAdministrator");
        if($output == ""){
            $output=runCommand(sudo()."samba-tool fsmo transfer --role=$contraction -UAdministrator 2>&1");
        }
        return respond($output,200);
    }

    function takeAllRoles(){
        $output=runCommand(sudo()."samba-tool fsmo transfer --role=all -UAdministrator");
        return respond($output,200);
    }

    function seizeTheRole(){
        $contraction = request("contraction");
        $output=runCommand(sudo()."samba-tool fsmo seize --role=$contraction -UAdministrator");
        return respond($output,200);
    }

    //Trust
    function trustedServers(){
        $allData = runCommand(sudo() . "samba-tool domain trust list 2>&1");
        $allDataList = explode("\n", $allData);

        $data=[];
        foreach($allDataList as $item){
            if($item){
                $itemInfos = explode("[", $item);
                $data[] = [
                    "type" => substr($itemInfos[1], 0, strpos($itemInfos[1], "]")),
                    "transitive" => substr($itemInfos[2], 0, strpos($itemInfos[2], "]")),
                    "direction" => substr($itemInfos[3], 0, strpos($itemInfos[3], "]")),
                    "name" => substr($itemInfos[4], 0, strpos($itemInfos[4], "]"))
                ];
            }
        }                

        return view('table', [
            "value" => $data,
            "title" => ["Names of Servers", "*hidden*", "*hidden*", "*hidden*"],
            "display" => ["name", "type:type", "transitive:transitive", "direction:direction"],
            "onclick" => "showTrustedServerDetailsModal",
            "menu" => [

                "Delete" => [
                    "target" => "showDeleteTrustedServerModal",
                    "icon" => "fas fa-trash-alt"
                ],

            ],
        ]);
    }

    function destroyTrustRelation(){
        $name = request("name");
        $password = request("password");
        $output = runCommand(sudo() . "samba-tool domain trust delete " . $name .
                            " -U administrator@" . $name .
                            " --password=" . $password);
        return respond("Trust relation with " . $name . " was destroyed", 200);
    }

    function createTrustRelation(){
        $domainName = request("newDomainName");
        $ipAddr = request("newIpAddr");
        $type = request("newType");
        $direction = request("newDirection");
        $createLocation = request("newCreateLocation");
        $username = request("newUsername");
        $password = request("password");

        if(!($domainName && $ipAddr && $type && $direction && $createLocation && $username && $password))
            return respond("Please fill all fields!", 202);

        runCommand(sudo() . "samba-tool domain trust create " . $domainName .
                    " --type=" . $type . " --direction=" . $direction .
                    " --create-location=" . $createLocation . " -U " . $username .
                    "@" . $domainName . " --password=" . $password);
        return respond("Trust relation with " . $domainName . " has been created", 200);
    }

    //Replication
    function replicationOrganized(){
        $hostNameTo = runCommand("hostname");

        $allInfo = runCommand(sudo() . "samba-tool drs showrepl --json");
        $allInfo = json_decode($allInfo,true);

        $data = [];

        for ($i=0; $i < count($allInfo["repsFrom"]); $i++) {
            $pureHostName = str_replace("Default-First-Site-Name\\", "", $allInfo["repsFrom"][$i]["DSA"]);
            $data[] = [
                "hostNameTo" => $hostNameTo,
                "info" => $allInfo["repsFrom"][$i]["NC dn"],
                "hostNameFrom" => $pureHostName,
                "lastUpdateTime" => $allInfo["repsFrom"][$i]["last success"]
            ];
        }

        return view('table', [
            "value" => $data,
            "title" => ["Incoming Host Name", "Info", "Outgoing Host Name", "*hidden*"],
            "display" => ["hostNameTo", "info", "hostNameFrom", "lastUpdateTime:lastUpdateTime"],
            "onclick" => "test",
            "menu" => [

                "Update Replication" => [
                    "target" => "updateReplication",
                    "icon" => "fa-plus-circle"
                ],

                "Last Update Time" => [
                    "target" => "showUpdateTime",
                    "icon" => "fa-recycle"
                ],
            ],
        ]);   
    }

    function createBound(){ 
        $incomingHostName = request('inHost');
        $outgoingHostName = request('outHost');
        $info = request('info');
        runCommand(sudo() . 'samba-tool drs replicate ' . $incomingHostName . $outgoingHostName . $info);
        return respond("Islem basarili", 200);
    }

    function showUpdateTime(){
        $lastUpdateTime = request('lastUpdateTime');
        return respond($lastUpdateTime, 200);
        
    }

    //Migration
    
    function migrateDomain(){
        $ip = request("ip");
        $username = request("username");
        $password = request("password");
        runCommand(sudo()."smb-migrate-domain -s ".$ip." -a ".$username." -p ".$password,200);

        if($this->checkMigrate2() == true){
            //migrate edilebilir yani migrate edilmemiş.
            return respond(true,200);
        }
        else{
            return respond(false,200);
        }
    }

    function checkMigrate(){
        //check => true ise migrate edilebilir.
        $output=runCommand(sudo()."net ads info",200);
        if($output==""){
            $output=runCommand(sudo()."net ads info 2>&1",200);
        }
        if(str_contains($output, "Can't load /etc/samba/smb.conf")){
            return respond(true,200);
        }
        else{
            return respond(false,200);
        }
    }

    function checkMigrate2(){
        //check => true migrate edilebilir.
        $output=runCommand(sudo()."net ads info",200);
        if($output==""){
            $output=runCommand(sudo()."net ads info 2>&1",200);
        }
        if(str_contains($output, "Can't load /etc/samba/smb.conf")){
            return true;
        }
        else{
            return false;
        }
    }
    function migrateSite(){
        $ip = request("ip");
        $username = request("username");
        $password = request("password");
        $site = request("site");

        $command = "smb-migrate-domain -s ".$ip." -a ".$username." -p ".$password." -t ".$site." 2>&1 > /tmp/smb-migrate-logs.txt";
        runCommand(sudo().$command);
        return respond("Success", 200);
    }

    function migrateLog(){

        $log = runCommand(sudo() . 'cat /tmp/smb-migrate-logs.txt');
        if(str_contains($log, "servisler yeniden başlatılıyor")){
            return respond("bitti", 200);
        }
        return respond($log, 200);
    }

    function migrate2(){
        $ip = request("ip");
        $username = request("username");
        $password = request("password");
        $site = request("site");

        $command = "smb-migrate-domain -s ".$ip." -a ".$username." -p ".$password." -t ".$site." 2>&1 > /tmp/smb-migrate-logs.txt";
        runCommand(sudo().$command);
        return respond("Success", 200);
    }

}
?>