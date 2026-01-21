<?php
defined('BASEPATH') OR exit ('No direct script access allowed');

class Wrapping extends CI_Controller {

    /**
     * call API tanpa harus ganti jession ID
     * logic pengecekkan FMR
     * mapId ambil dari URL request
     * kalau trigger melalui task_do simpan ke trigger_hist
     */

    // public function test_baseurl() {
    //     echo "Base URL: " . base_url();
    //     echo "<br>";
    //     echo "Current URL: " . current_url();
    //     echo "<br>";
    //     echo "Site URL: " . site_url();
    // }

    private $DEBUG_POLLING = false;

    public function __construct()
    {
        parent::__construct();
        ini_set('serialize_precision', -1);
        ini_set('precision', -1);
        $this->load->model('Wrapping_model');
        $this->load->library('Pointlocation');
        $this->load->library('Lanxin_wrapper');
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
    }

    /**
     * POST api/wrapping/pallet
     * Payload:
     * {
     *      "mac_address" : "IOT_dummymacaddresswrapping",
     *      "status" : "PALLET_DETECTED"
     * }
     */
    public function pallet()
    {
        $input = json_decode($this->input->raw_input_stream, true);

        $mac_address = $input['mac_address'] ?? null;
        $status = $input['status'] ?? null;

        if (!$mac_address || !$status) {
            return $this->response([
                'success' => false,
                'message' => 'mac_address and status are required'
            ], 400);
        }

        if ($status !== 'PALLET_DETECTED') {
            return $this->response([
                'success' => false,
                'message' => 'Only PALLET_DETECTED status is allowed'
            ], 422);
        }

        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $mac_address,
            'status'      => 'PALLET_DETECTED',
            'call_status' => 'RECEIVED'
        ]);

        return $this->response([
            'success' => true,
            'message' => 'PALLET_DETECTED status received',
        ]);
    }

    /**
     * POST api/wrapping/check_fmr 
     * Payload:
     * {
     *      "mac_address" : "IOT_dummymacaddresswrapping",
     *      "action" : "CHECK_FMR"
     * }
     */

    public function check_fmr()
    {
        $input = json_decode($this->input->raw_input_stream, true);
        $mac_address = $input['mac_address'] ?? null;
        $action = $input['action'] ?? null;

        if (!$mac_address || $action !== 'CHECK_FMR') {
            return $this->response([
                'success' => false,
                'message' => 'Invalid request'
            ], 400);
        }

        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $mac_address,
            'status'      => 'CHECK_FMR',
            'call_status' => 'RECEIVED'
        ]);

        //polling FMR
        $poll = $this->pollingFmr();

        if ($poll['status'] === 'FMR_INSIDE') {

            $this->Wrapping_model->insertIoTLog([
                'mac_address' => $mac_address,
                'status'      => 'FMR_INSIDE',
                'call_status' => 'TRANSMIT'
            ]);

            return $this->response([
                'success' => true,
                'can_wrap' => false,
                'fmr_status' => 'INSIDE',
                'message' => 'Belum, FMR masih inside'
            ]);
        }

        if ($poll['status'] === 'FMR_OUTSIDE') {

            $this->Wrapping_model->insertIoTLog([
                'mac_address' => $mac_address,
                'status'      => 'FMR_OUTSIDE',
                'call_status' => 'TRANSMIT'
            ]);

            return $this->response([
                'success' => true,
                'can_wrap' => true,
                'fmr_status' => 'OUTSIDE',
                'message' => 'Ya, FMR sudah outside'
            ]);
        }

        return $this->response([
            'success' => false,
            'message' => 'FMR status unknown'
        ], 500);
    }

    private function callAmrApi()
    {
        /**
         * ganti jangan pakai session
         * mapid jangan hardcore
         */
        $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=6";

        $cookie = 'JSESSIONID=a673f46e-fd00-4676-b94e-33d516375a5f; userName=Developt';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Cookie: ' . $cookie,
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // CURL ERROR
        if ($response === false) {
            log_message('error', '[AMR API] Curl error: '.$error);
            return false;
        }

        log_message(
            'debug',
            "[AMR API] HTTP {$httpCode} response received"
        );

        $json = json_decode($response, true);

        if (!isset($json['data'])) {
            log_message('error', '[AMR API] Invalid response structure');
            return false;
        }

        return $json['data'];
    }

    public function pollingFmr()
    {
        $amrData = $this->callAmrApi(); 

        if (!$amrData || empty($amrData)) {
            return [
                'status' => 'FAILED',
                'message' => 'AMR API error'
            ];
        }

        // ambil mapId dari API
        $mapId = $amrData[0]['mapId'] ?? null;

        if (!$mapId) {
            return [
                'status' => 'FAILED',
                'message' => 'mapId not found'
            ];
        }
        $mapId = 6;

        $polygon = [
            "-61.57 3.403",
            "-61.57 6.2838",
            "-59.17 6.2838",
            "-59.17 3.403",
            "-61.57 3.403"
        ];

        $amrData = $this->callAmrApi($mapId);
        if (!$amrData) {
            return [
                'status' => 'FAILED',
                'message' => 'AMR API error'
            ];
        }

        $insideFmr = [];
        $debugFmr  = [];

        foreach ($amrData as $robot) {

            if (!isset($robot['classType']) || $robot['classType'] !== 'FL') continue;
            if (!isset($robot['coordinate'])) continue;

            $id = $robot['id'] ?? null;
            $x  = $robot['coordinate']['x'] ?? null;
            $y  = $robot['coordinate']['y'] ?? null;
            if ($x === null || $y === null) continue;

            $point = $x . " " . $y;
            $zone = $this->pointlocation->pointInPolygon($point, $polygon, true);

            if (in_array($zone, ['inside', 'boundary', 'vertex'])) {
                $insideFmr[] = [
                    'id' => $id,
                    'x'  => round($x,2),
                    'y'  => round($y,2)
                ];
            }

            if ($this->DEBUG_POLLING) {
                $debugFmr[] = [
                    'id'   => $id,
                    'x'    => round($x,2),
                    'y'    => round($y,2),
                    'zone' => $zone
                ];
            }
        }

        if (empty($insideFmr)) {

            $result = [
                'status'  => 'FMR_OUTSIDE',
                'message' => 'All FMR outside wrapping zone',
                'map_id'  => $mapId
            ];

        } else {

            $result = [
                'status'  => 'FMR_INSIDE',
                'message' => 'There are FMR inside wrapping zone',
                'inside_fmr' => $insideFmr,
                'map_id'  => $mapId
            ];
        }

        if ($this->DEBUG_POLLING) {
            $result['debug_fmr'] = $debugFmr;
        }

        return $result;
    }

    public function done()
    {
        $input = json_decode($this->input->raw_input_stream, true);

        $mac_address = $input['mac_address'] ?? null;
        $status = $input['status'] ?? null;

        if(!$mac_address || $status!='WRAPPING_DONE'){
            return $this->response([
                'success'=>false,
                'message'=>'invalid payload'
            ],400);
        }

        $this->Wrapping_model->insertIoTLog([
            'mac_address'=>$mac_address,
            'status'=>'WRAPPING_DONE',
            'call_status'=>'RECEIVED'
        ]);


        $lastCounter = $this->Wrapping_model->getLastCounterToday($mac_address);
        $lastSequence = $this->Wrapping_model->getLastSequence($mac_address);

        $counter = $lastCounter + 1;

        $maxSeq = 6;
        
        if ($lastSequence == 0){
            $sequence = 1;
        } else {
            $sequence = ($lastSequence % $maxSeq) + 1;
        }

        $rcsResult = $this->lanxin_wrapper->triggerRCS($mac_address, 26, $sequence);

        $fmr = $this->pollingFmr();
        $mapId = $fmr['map_id'] ?? 0;

        $this->Wrapping_model->insertSequence([
            'mac_address'=>$mac_address,
            'counter'=>$counter,
            'sequence'=>$sequence,
            'taskId'=>$rcsResult['task_id'] ?? 0,
            'map_id' =>$mapId
        ]);

        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $mac_address,
            'status' => 'RCS_TRIGGER_' . ($rcsResult['success'] ? 'SUCCESS' : 'FAILED'),
            'call_status' => 'TRANSMIT',
        ]);

        return $this->response([
            'success'=>true,
            'message'=>'Wrapping Done received',
            'counter'=>$counter,
            'last_sequence'=>$lastSequence,
            'sequence'=>$sequence,
            'rcs_trigger'=>$rcsResult
        ]);
    }

    private function response($data, $code = 200)
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
?>