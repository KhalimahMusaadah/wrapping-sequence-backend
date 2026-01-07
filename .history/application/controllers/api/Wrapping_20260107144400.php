<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping extends CI_Controller {

    private $DEBUG_POLLING = true;

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

    private function callAmrApi($mapId)
    {
        $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=".$mapId;

        // tiap login ganti cookienya
        $cookie = 'JSESSIONID=4a0e0eac-832c-4ab7-ae32-6c58d5768a72; userName=Developt';

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
        $debugFmr = [];
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
                $insideFmr[$id] = [
                    'id' => $id,
                    'x'  => $x,
                    'y'  => $y
                ];
            }

            // simpan semua untuk DEBUG
            if ($this->DEBUG_POLLING) {
                $debugFmr[] = [
                    'id'   => $id,
                    'x'    => $x,
                    'y'    => $y,
                    'zone' => $zone
                ];
            }
        }

        if (empty($insideFmr)) {
            log_message('info', '[POLLING] No FMR inside wrapping zone');
            $result = [
                'status' => 'ALL_OUTSIDE',
                'message' => 'All FMR are outside wrapping zone'
            ];

            if ($this->DEBUG_POLLING) {
                $result['debug_fmr'] = $debugFmr;
            }

            return $result;
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
                if (!isset($insideFmr[$id])) continue;

                $x  = $robot['coordinate']['x'] ?? null;
                $y  = $robot['coordinate']['y'] ?? null;
                if ($x === null || $y === null) continue;

                $point = $x . " " . $y;
                $zone = $this->pointlocation->pointInPolygon($point, $polygon, true);

                log_message('debug', "[POLLING] FMR id={$id} x={$x} y={$y} zone={$zone}");

                if (!in_array($zone, ['inside', 'boundary', 'vertex'])) {
                    log_message('info', "[POLLING] FMR id={$id} now outside");
                    $result = [
                        'status' => 'FMR_OUTSIDE',
                        'fmr_id' => $id,
                    ];

                    if ($this->DEBUG_POLLING) {
                        $result['coordinate'] = [
                            'x' => $x,
                            'y' => $y
                        ];
                        $result['zone'] = $zone;
                    }

                    return $result;
                }
            }

            sleep($interval);
        }
    }

    private function triggerWrap($macAddress)

    //trigger IoT melalui HTTP API
    $iotUrl = "http://10.8.15.230:8080/api/wrapping/command"; //nanti diganti
    
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



    {
        log_message('info', "[WRAP TRIGGER] Sending WRAP command to mac_address={$macAddress}");

        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $macAddress,
            'status'      => 'WRAP_TRIGGERED',
            'call_status' => 'TRANSMIT'
        ]);
    }

    //lanjut trigger IoT



    // private function checkFmrById($fmrId)
    // {
    //     $mapId = 6;

    //     // polygon wrapping
    //     $polygon = [
    //         "-61.04 3.800",
    //         "-61.04 6.2838",
    //         "-59.698 6.2838",
    //         "-59.698 3.800",
    //         "-61.04 3.800"
    //     ];

    //     $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=".$mapId;

    //     // tiap login ganti cookienya
    //     $cookie = 'JSESSIONID=67fb9985-a9f4-4600-b065-1c61dc46f243; userName=Developt';

    //     $ch = curl_init($url);
    //     curl_setopt_array($ch, [
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_TIMEOUT => 5,
    //         CURLOPT_HTTPHEADER => [
    //             'Cookie: ' . $cookie,
    //             'Accept: application/json'
    //         ]
    //     ]);

    //     $response = curl_exec($ch);
    //     curl_close($ch);

    //     if (!$response) {
    //         return [
    //             'success' => false,
    //             'message' => 'Failed call AMR API'
    //         ];
    //     }

    //     $amrData = json_decode($response, true);

    //     if (!isset($amrData['data'])) {
    //         return [
    //             'success' => false,
    //             'message' => 'Invalid AMR response'
    //         ];
    //     }

    //     // cari FMR id 24
    //     foreach ($amrData['data'] as $fmr) {
    //         if ($fmr['id'] == $fmrId) {

    //             $x = $fmr['coordinate']['x'];
    //             $y = $fmr['coordinate']['y'];

    //             $point = $x." ".$y;

    //             $zoneResult = $this->pointlocation->pointInPolygon(
    //                 $point,
    //                 $polygon,
    //                 true
    //             );

    //             return [
    //                 'success' => true,
    //                 'fmr_id'  => $fmrId,
    //                 'x'       => $x,
    //                 'y'       => $y,
    //                 'zone'    => $zoneResult
    //             ];
    //         }
    //     }

    //     return [
    //         'success' => false,
    //         'message' => 'FMR not found'
    //     ];
    // }


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