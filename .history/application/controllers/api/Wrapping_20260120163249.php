<?php
defined('BASEPATH') OR exit ('No direct script access allowed');

class Wrapping extends CI_Controller {

    /**
     * IoT kirim status PALLET_DETECTED (DONE)
     * IoT kirim pesan wrapping? (DONE)
     * Backend cek FMR (DONE) call AMR API tanpa harus ganti jessionID, logic pengecekan FMR, mapidnya angan hardcore 
     * jika masih inside jeda beberapa detik tanya lagi (DONE)
     * Backend cek FMR (DONE)
     * kalau sudah outside kirim outside ke IoT (DONE)
     * IoT wrapping
     * jika sudah selesai kirim wrapping selesai backend
     * backend hitung sequencce (DONE)
     * backend call RCS (DONE)
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

        $cookie = 'JSESSIONID=4b6e49f6-3d82-4b08-8f49-9c590a9702a1; userName=Developt';

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


    /**
     * POST api/wrapping/ready
     * Payload:
     * {
     *   "mac_address": "AA:BB:CC:DD:EE:FF",
     *   "status": "READY"
     * }
     */
    public function ready()
    {
        $input = json_decode($this->input->raw_input_stream, true);

        //testing mode 
        $testMode = $input['test'] ?? false;        

        $mac_address = $input['mac_address'] ?? null;
        $status      = $input['status'] ?? null;

        if (!$mac_address || !$status) {
            return $this->response([
                'success' => false,
                'message' => 'mac_address and status are required'
            ], 400);
        }

        // Validasi status (sementara hanya READY)
        if ($status !== 'READY') {
            return $this->response([
                'success' => false,
                'message' => 'Only READY status is allowed'
            ], 422);
        }

        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $mac_address,
            'status'      => 'READY',
            'call_status' => 'RECEIVED'
        ]);

        log_message(
            'info',
            "[IOT READY] mac_address={$mac_address}"
        );

        //testing mode
        if ($testMode) {
            $pollingResult = [
                'status' => 'FMR_OUTSIDE',
                'fmr_id' => 999,
                'coordinate' => ['x'=>-60.0,'y'=>5.0],
                'zone'=>'outside'
            ];

            // langsung trigger wrap
            $wrapResult = $this->triggerWrap($mac_address);

            return $this->response([
                'success' => true,
                'message' => 'READY status received (TEST MODE)',
                'polling' => $pollingResult,
                'wrap' => $wrapResult
            ]);
        }

        //start untuk polling
        $pollingResult = $this->pollingFmr();

        //trigger wrap setelah pengecekan polling selesai
        if ($pollingResult['status'] === 'FMR_OUTSIDE') {
            $this->triggerWrap($mac_address);
        }

        return $this->response([
            'success' => true,
            'message' => 'READY status received',
            'polling' => $pollingResult
        ]);
    }

    private function response($data, $code = 200)
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    // private function pollingFmr()
    // {
    //     $mapId = 6;
    //     $timeoutSeconds = 60;
    //     $interval = 3;
    //     $startTime = time();

    //     log_message('info', '[POLLING] START polling FMR wrapping zone');

    //     $polygon = [
    //         "-61.57 3.403", //kiri bawah
    //         "-61.57 6.2838", //kiri atas
    //         "-59.17 6.2838", //kanan atas
    //         "-59.17 3.403", //kanan bawah
    //         "-61.57 3.403"
    //     ];

    //     // Ambil semua FMR dari API
    //     $amrData = $this->callAmrApi($mapId);
    //     if (!$amrData) {
    //         return [
    //             'status' => 'FAILED',
    //             'message' => 'AMR API error'
    //         ];
    //     }

    //     // Cari FMR yang inside wrapping zone
    //     $insideFmr = [];
    //     $debugFmr = [];
    //     foreach ($amrData as $robot) {
    //         if (!isset($robot['classType']) || $robot['classType'] !== 'FL') continue;
    //         if (!isset($robot['coordinate'])) continue;

    //         $id = $robot['id'] ?? null;
    //         $x  = $robot['coordinate']['x'] ?? null;
    //         $y  = $robot['coordinate']['y'] ?? null;
    //         if ($x === null || $y === null) continue;

    //         $point = $x . " " . $y;
    //         $zone = $this->pointlocation->pointInPolygon($point, $polygon, true);

    //         if (in_array($zone, ['inside', 'boundary', 'vertex'])) {
    //             $insideFmr[$id] = [
    //                 'id' => $id,
    //                 'x'  => $x,
    //                 'y'  => $y
    //             ];
    //         }

    //         // simpan semua untuk DEBUG
    //         if ($this->DEBUG_POLLING) {
    //             $debugFmr[] = [
    //                 'id'   => $id,
    //                 'x'    => round($x, 2),
    //                 'y'    => round($y, 2),
    //                 'zone' => $zone
    //             ];
    //         }
    //     }

    //     if (empty($insideFmr)) {
    //         log_message('info', '[POLLING] No FMR inside wrapping zone');
    //         $result = [
    //             'status' => 'ALL_OUTSIDE',
    //             'message' => 'All FMR are outside wrapping zone'
    //         ];

    //         if ($this->DEBUG_POLLING) {
    //             $result['debug_fmr'] = $debugFmr;
    //         }

    //         return $result;
    //     }

    //     log_message('info', '[POLLING] Found inside FMR, start polling: '.implode(',', array_keys($insideFmr)));

    //     // Polling FMR inside
    //     while (!empty($insideFmr)) {

    //         if ((time() - $startTime) >= $timeoutSeconds) {
    //             log_message('warning', '[POLLING] STOP - TIMEOUT, FMR still inside');
    //             return [
    //                 'status' => 'TIMEOUT',
    //                 'message' => 'Polling timeout, FMR still inside',
    //                 'inside_fmr' => array_keys($insideFmr)
    //             ];
    //         }

    //         $amrData = $this->callAmrApi($mapId);
    //         if (!$amrData) return ['status'=>'FAILED','message'=>'AMR API error'];

    //         foreach ($amrData as $robot) {
    //             $id = $robot['id'] ?? null;
    //             if (!isset($insideFmr[$id])) continue;

    //             $x  = $robot['coordinate']['x'] ?? null;
    //             $y  = $robot['coordinate']['y'] ?? null;
    //             if ($x === null || $y === null) continue;

    //             $point = $x . " " . $y;
    //             $zone = $this->pointlocation->pointInPolygon($point, $polygon, true);

    //             log_message('debug', "[POLLING] FMR id={$id} x={$x} y={$y} zone={$zone}");

    //             if (!in_array($zone, ['inside', 'boundary', 'vertex'])) {
    //                 log_message('info', "[POLLING] FMR id={$id} now outside");
    //                 $result = [
    //                     'status' => 'FMR_OUTSIDE',
    //                     'fmr_id' => $id,
    //                 ];

    //                 if ($this->DEBUG_POLLING) {
    //                     $result['coordinate'] = [
    //                         'x' => round($x, 2),
    //                         'y' => round($y, 2)
    //                     ];
    //                     $result['zone'] = $zone;
    //                 }

    //                 return $result;
    //             }
    //         }

    //         sleep($interval);
    //     }
    // }

    private function triggerWrap($macAddress)
    {
        //trigger melalui HTTP API
        $iotUrl = base_url('api/wrapping/command');
        $payload = [
            'mac_address' => $macAddress,
            'command'     => 'WRAP'
        ];

        $ch = curl_init($iotUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            log_message('error', "[WRAP TRIGGER] Curl error: $error");
            $this->Wrapping_model->insertIoTLog([
                'mac_address' => $macAddress,
                'status'      => 'WRAP_FAILED',
                'call_status' => 'ERROR',
                'message'     => $error
            ]);
            return false;
        }

        $iotResult = json_decode($response, true);

        log_message('info', "[WRAP TRIGGER] Response HTTP {$httpCode}: {$response}");

        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $macAddress,
            'status'      => 'WRAP',
            'call_status' => ($httpCode==200 ? 'TRANSMIT' : 'ERROR'),
        ]);

        return $iotResult;
    }

    public function command()
    {
        $input = json_decode($this->input->raw_input_stream, true);
        return $this->response([
            'success' => true,
            'message' => 'Dummy IoT command received',
            'payload' => $input
        ]);
    }
}
?>