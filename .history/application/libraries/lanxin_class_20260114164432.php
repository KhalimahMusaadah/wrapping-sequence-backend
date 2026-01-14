<?php
class Task
{
    private $db;
    private $query;
    function __construct($konektor)
    {
        $this->db = $konektor;
    }

    function mycurl($url, $data, $action, $timeout = 60)
    {

        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $action);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'token: admin123',
            'name: lx-script'
        ]);
        $content = curl_exec($ch);

        //print $content;

        curl_close($ch);
        return $content;
    }

    function readTable()
    {
        $exec = $this->db->query($this->query);
        $data = [];
        if ($exec && $exec->num_rows > 0) {
            while ($row = $exec->fetch_object()) {
                $data[] = $row; // Menyimpan setiap objek ke array
            }
        }

        return $data;
    }

    function ExecQuery($query)
    {
        $this->db->query($query);
    }

    function getNumRows()
    {
        $exec = $this->db->query($this->query);
        return $exec->num_rows;
    }

    function getLastID()
    {
        $last_id = 0;
        $exec = $this->db->query('SELECT LAST_INSERT_ID() as id');
        $row = $exec->row();
        $last_id = $row->id;
        return $last_id;
    }

    function get_page_lanxing($url)
    {
        $options = array(
            CURLOPT_RETURNTRANSFER => true,   // return web page
            CURLOPT_HEADER         => false,  // don't return headers
            CURLOPT_FOLLOWLOCATION => true,   // follow redirects
            CURLOPT_MAXREDIRS      => 10,     // stop after 10 redirects
            CURLOPT_ENCODING       => "",     // handle compressed
            CURLOPT_USERAGENT      => "test", // name of client
            CURLOPT_AUTOREFERER    => true,   // set referrer on redirect
            CURLOPT_CONNECTTIMEOUT => 2,    // time-out on connect
            CURLOPT_TIMEOUT        => 2,    // time-out on response
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'token: admin123',
            'name: lx-script'
        ]);
        $content  = curl_exec($ch);
        curl_close($ch);
        return $content;
    }

    function JsonPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'token: admin123',
            'name: lx-script',
            'Content-Type: application/json'
        ]);
        $content = curl_exec($ch);
        curl_close($ch);
        return $content;
    }

    function plantIp($plant = "")
    {
        $this->query = "SELECT * FROM config_plant ";
        if ($plant != "") $this->query .= "WHERE plant='$plant'";
        $data = $this->readTable();
        return $data;
    }

    function PortConfig()
    {
        $this->query = "SELECT * FROM port_config";
        $data = $this->readTable();
        return $data;
    }

    function readArea($ip)
    {
        $ss = $this->JsonPost("http://$ip:4333/api/area/list", "{\"currentPage\":1,\"pageSize\":0}");
        $ss = json_decode($ss);
        if ($ss->data != '') {
            foreach ($ss->data->data as $d) {
                // $areaMgt[$row['id']] = array('areaId' => $row['id'], 'areaName' => $row['name']);
                #cek
                $this->query = "select * from area where ip='$ip' AND areaId='$d->id'";
                $num = $this->getNumRows();
                if ($num > 0) {
                    $this->ExecQuery("UPDATE area SET areaName='$d->name' where ip='$ip' AND areaId='$d->id'");
                } else {
                    $this->ExecQuery("INSERT INTO area(ip,areaName,areaId) VALUE('$ip','$d->name','$d->id')");
                }
            }
            return $ss->data->data;
        }
    }

    function sendTriggertoRCS($taskid, $ip = "")
    {
        $iprcs = $ip != "" ? $ip : "10.8.15.226";
        $portrcs = "4333";
        $return = 'no response';
        //$link = "http://$iprcs:$portrcs/api/taskChainTemplate/submit/$taskid";
        //$f = fopen("debug.txt", "w");

        $ss_ret = $this->get_page_lanxing($link);
        $ss = json_decode($ss_ret, TRUE);
        //{"errMsg":"Operation succeeded","errCode":"0","state":true,"data":19647,"differentInfo":null}
        if (count($ss) > 0) {
            $return = $ss['errCode'];
        }
        //fwrite($f, $link . " " . $ss_ret);
        //fclose($f);
        //$return = '0';

        //untuk debug info konek RCS
        $this->lastRawResponse = $ss_ret;
        $this->lastUrl = $link;
        
        return $return;
    }

    function TaskConfig($area)
    {
        $this->query = "SELECT * FROM task_config WHERE area='$area'";
    }

    function TaskTemplate($area = 0)
    {
        $this->query = "SELECT * FROM taskTemplateLanxing ";
        if ($area != 0) $this->query .= " WHERE workArea='$area'";
        $this->query .= " ORDER BY taskName ASC";
        $data = $this->readTable();
        return $data;
    }

    function TriggerConfig($area = 0, $id = 0, $port = 0)
    {
        $this->query = "SELECT * FROM trigger_config ";
        if ($area != 0) $this->query .= "LEFT JOIN port_config ON (trigger_config.port=port_config.port) LEFT JOIN taskTemplateLanxing ON trigger_config.taskId=taskTemplateLanxing.taskNumber AND area=taskTemplateLanxing.workArea WHERE area='$area' ";
        else $this->query .= "WHERE deviceId='$id' AND trigger_config.port='$port'";
        $this->query .= " ORDER BY deviceId,Id";
        return $this->readTable();
    }

    function WorkArea($plant = "")
    {
        $this->query = "SELECT areaName,areaId,ip FROM area";
        if ($plant != "") $this->query .= " WHERE ip='$plant'";
        $data = $this->readTable();
        return $data;
    }
}
class Lanxin extends Task
{
    function __construct($konektor)
    {
        return parent::__construct($konektor);
    }

    function getMapId($server)
    {
        $url = "http://$server:4333/api/map/mapList";
        // $maps = $this->get_page_lanxing($url);
        $maps = $this->mycurl($url, "", "GET");

        $maps_data = [];
        $maps_array = json_decode($maps);
        if ($maps_array->state == 1) {
            foreach ($maps_array->data->data as $d) {
                $t = new stdClass;
                $t->id = $d->id;
                $t->name = $d->name;
                $maps_data[] = $t;
            }
        }
        return $maps_data;
    }

    function getChargingId($server, $mapId)
    {
        $url = "http://$server:4333/api/chargePile/page";
    }
}
