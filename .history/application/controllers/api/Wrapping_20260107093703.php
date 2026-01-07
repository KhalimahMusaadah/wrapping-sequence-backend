<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Wrapping_model');
        $this->load->library('Pointlocation');
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
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

        //test check FMR by ID
        //$fmrCheck = $this->checkFmrById(28);

        // //log untuk debugging
        // log_message('info', json_encode($fmrCheck));

        //start untuk polling
        $pollingResult = $this->pollingFmr();

        //trigger wrap setelah pengecekan polling selesai
        


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

    private function callAmrApi($mapId)
    {
        $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=".$mapId;

        // tiap login ganti cookienya
        $cookie = 'JSESSIONID=80bba04d-40f1-46b9-977b-7969a367a7a3; userName=Developt';

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

    private function pollingFmr()
    {
        $mapId = 6;
        $timeoutSeconds = 60;
        $interval = 3;
        $startTime = time();

        log_message('info', '[POLLING] START polling FMR wrapping zone');

        $polygon = [
            "-61.04 3.800",
            "-61.04 6.2838",
            "-59.698 6.2838",
            "-59.698 3.800",
            "-61.04 3.800"
        ];

        // Ambil semua FMR dari API
        $amrData = $this->callAmrApi($mapId);
        if (!$amrData) {
            return [
                'status' => 'FAILED',
                'message' => 'AMR API error'
            ];
        }

        // Cari FMR yang inside wrapping zone
        $insideFmr = [];
        foreach ($amrData as $robot) {
            if (!isset($robot['classType']) || $robot['classType'] !== 'FL') continue;

            $id = $robot['id'] ?? null;
            $x  = $robot['coordinate']['x'] ?? null;
            $y  = $robot['coordinate']['y'] ?? null;
            if ($x === null || $y === null) continue;

            $point = $x . " " . $y;
            $zone = $this->pointlocation->pointInPolygon($point, $polygon, true);

            if (in_array($zone, ['inside', 'boundary', 'vertex'])) {
                $insideFmr[$id] = [
                    'id' => $id,
                    'x'  => $x,
                    'y'  => $y
                ];
            }
        }

        if (empty($insideFmr)) {
            log_message('info', '[POLLING] No FMR inside wrapping zone');
            return [
                'status' => 'ALL_OUTSIDE',
                'message' => 'All FMR are outside wrapping zone'
            ];
        }

        log_message('info', '[POLLING] Found inside FMR, start polling: '.implode(',', array_keys($insideFmr)));

        // Polling FMR inside
        while (!empty($insideFmr)) {

            if ((time() - $startTime) >= $timeoutSeconds) {
                log_message('warning', '[POLLING] STOP - TIMEOUT, FMR still inside');
                return [
                    'status' => 'TIMEOUT',
                    'message' => 'Polling timeout, FMR still inside',
                    'inside_fmr' => array_keys($insideFmr)
                ];
            }

            $amrData = $this->callAmrApi($mapId);
            if (!$amrData) return ['status'=>'FAILED','message'=>'AMR API error'];

            foreach ($amrData as $robot) {
                $id = $robot['id'] ?? null;
                if (!isset($insideFmr[$id])) continue; // hanya FMR yang inside

                $x  = $robot['coordinate']['x'] ?? null;
                $y  = $robot['coordinate']['y'] ?? null;
                if ($x === null || $y === null) continue;

                $point = $x . " " . $y;
                $zone = $this->pointlocation->pointInPolygon($point, $polygon, true);

                log_message('debug', "[POLLING] FMR id={$id} x={$x} y={$y} zone={$zone}");

                if (!in_array($zone, ['inside', 'boundary', 'vertex'])) {
                    log_message('info', "[POLLING] FMR id={$id} now outside");
                    log_message('info', "[]")
                }
            }

            sleep($interval);
        }

        log_message('info', '[POLLING] FMR are now outside');

        return [
            'status' => 'ALL_OUTSIDE',
            'message' => 'All FMR are outside'
        ];
    }


//     /**
//      * Pengecekan berkala semua FMR apakah sudah keluar dari zona wrapping
//      */
//     private function pollingCheckAllFmr()
//     {
//         $mapId = 6; //nanti diganti 
//         $timeoutSeconds = 60;
//         $interval = 3;

//         $startTime = time();

//         log_message('info', '[POLLING] START polling FMR wrapping zone');

//         $polygon = [
//             "-61.04 3.800",
//             "-61.04 6.2838",
//             "-59.698 6.2838",
//             "-59.698 3.800",
//             "-61.04 3.800"
//         ];

//         while (true) {

//             // timeout
//             if ((time() - $startTime) >= $timeoutSeconds) {
//                 log_message('warning', '[POLLING] STOP - TIMEOUT, FMR still inside');

//                 return [
//                     'status' => 'TIMEOUT',
//                     'message' => 'Polling timeout, FMR still inside'
//                 ];
//             }

//             //call AMR API
//             log_message('debug', '[POLLING] Calling AMR API');
//             $amrData = $this->callAmrApi($mapId);
//             if (!$amrData) {
//                 log_message('error', '[POLLING] AMR API failed');
//                 return [
//                     'status' => 'FAILED',
//                     'message' => 'AMR API error'
//                 ];
//             }

//             $insideFound = false;
//             $checkedFmr = [];

//             //loop semua robot
//             foreach ($amrData as $robot) {

//                 // hanya FMR
//                 if (!isset($robot['classType']) || $robot['classType'] !== 'FL') {
//                     continue;
//                 }

//                 $id = $robot['id'] ?? null;
//                 $x  = $robot['coordinate']['x'] ?? null;
//                 $y  = $robot['coordinate']['y'] ?? null;

//                 if ($x === null || $y === null) {
//                     log_message(
//                         'warning',
//                         "[POLLING] FMR id={$id} coordinate missing"
//                     );
//                     continue;
//                 }

//                 $point = $x . " " . $y;

//                 $zone = $this->pointlocation->pointInPolygon(
//                     $point,
//                     $polygon,
//                     true
//                 );

//                 log_message(
//                     'debug',
//                     "[POLLING] FMR id={$id} x={$x} y={$y} zone={$zone}"
//                 );

//                 $checkedFmr[] = [
//                     'id'   => $id,
//                     'x'    => $x,
//                     'y'    => $y,
//                     'zone' => $zone
//                 ];

//                 if (in_array($zone, ['inside', 'boundary', 'vertex'])) {
//                     $insideFound = true;
//                 }
//             }

//             // STOP CONDITION
//             if (!$insideFound) {
//                 log_message('info', '[POLLING] STOP - all FMR outside wrapping zone');
//                 return [
//                     'status' => 'ALL_OUTSIDE',
//                     'message' => 'All FMR are outside wrapping zone',
//                     'checked_fmr' => $checkedFmr
//                 ];
//             }
//             log_message('debug', "[POLLING] Still inside, sleep {$interval}s");
//             sleep($interval);
//         }
//     }


//     private function checkFmrById($fmrId)
//     {
//         $mapId = 6;

//         // polygon wrapping
//         $polygon = [
//             "-61.04 3.800",
//             "-61.04 6.2838",
//             "-59.698 6.2838",
//             "-59.698 3.800",
//             "-61.04 3.800"
//         ];

//         $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=".$mapId;

//         // tiap login ganti cookienya
//         $cookie = 'JSESSIONID=67fb9985-a9f4-4600-b065-1c61dc46f243; userName=Developt';

//         $ch = curl_init($url);
//         curl_setopt_array($ch, [
//             CURLOPT_RETURNTRANSFER => true,
//             CURLOPT_TIMEOUT => 5,
//             CURLOPT_HTTPHEADER => [
//                 'Cookie: ' . $cookie,
//                 'Accept: application/json'
//             ]
//         ]);

//         $response = curl_exec($ch);
//         curl_close($ch);

//         if (!$response) {
//             return [
//                 'success' => false,
//                 'message' => 'Failed call AMR API'
//             ];
//         }

//         $amrData = json_decode($response, true);

//         if (!isset($amrData['data'])) {
//             return [
//                 'success' => false,
//                 'message' => 'Invalid AMR response'
//             ];
//         }

//         // cari FMR id 24
//         foreach ($amrData['data'] as $fmr) {
//             if ($fmr['id'] == $fmrId) {

//                 $x = $fmr['coordinate']['x'];
//                 $y = $fmr['coordinate']['y'];

//                 $point = $x." ".$y;

//                 $zoneResult = $this->pointlocation->pointInPolygon(
//                     $point,
//                     $polygon,
//                     true
//                 );

//                 return [
//                     'success' => true,
//                     'fmr_id'  => $fmrId,
//                     'x'       => $x,
//                     'y'       => $y,
//                     'zone'    => $zoneResult
//                 ];
//             }
//         }

//         return [
//             'success' => false,
//             'message' => 'FMR not found'
//         ];
//     }


//     /**
//      * check fmr hardcore
//      * GET api/wrapping/check_fmr
//      */
//     public function check_fmr()
//     {
//         //polygon zona wrapping
//         $polygon = [
//             "-61.04 3.800",
//             "-61.04 6.2838",
//             "-59.698 6.2838",
//             "-59.698 3.800",
//             "-61.04 3.800"
//         ];

//         //hardcore titik FMR (untuk testing)
//         //INSIDE
//         //$fmrPoint = "-60.37 4.947";

//         //OUTSIDE
//         //$fmrPoint = "-61.2327 7.2258";

//         //BOUNDARY
//         $fmrPoint = "-60.37 6.2838";

//         //cekposisi
//         $result = $this->pointlocation->pointInPolygon(
//             $fmrPoint,
//             $polygon,
//             true
//         );

//         //response
//         echo json_encode([
//             'fmr_point' => $fmrPoint,
//             'result'    => $result
//         ]);
//     }


//     /**
//      * GET api/wrapping/test_amr_only
//      */
//     public function test_amr_only()
//     {
//         $mapId = 6;

//         $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=".$mapId;
//         //tiap login ganti cookienya
//         $cookie = 'JSESSIONID=67fb9985-a9f4-4600-b065-1c61dc46f243; userName=Developt';

//         $ch = curl_init($url);
//         curl_setopt_array($ch, [
//             CURLOPT_RETURNTRANSFER => true,
//             CURLOPT_TIMEOUT => 5,
//             CURLOPT_HTTPHEADER => [
//                 'Cookie: ' . $cookie,
//                 'Accept: application/json'
//             ]
//         ]);

//         $response = curl_exec($ch);
//         $error    = curl_error($ch);
//         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

//         curl_close($ch);

//         // Kalau request ke AMR gagal
//         if ($response === false) {
//             echo json_encode([
//                 'success' => false,
//                 'message' => 'Failed call AMR API',
//                 'error'   => $error
//             ]);
//             return;
//         }

//         // Kalau sukses, tampilkan response AMR apa adanya
//         echo json_encode([
//             'success'    => true,
//             'http_code' => $httpCode,
//             'amr_raw'   => json_decode($response, true)
//         ]);
//     }

// }
}
?>