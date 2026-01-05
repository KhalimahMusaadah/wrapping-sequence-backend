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

        // Validasi dasar
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

        // Simpan log READY dari IoT
        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $mac_address,
            'status'      => 'READY',
            'call_status' => 'RECEIVED'
        ]);

        log_message(
            'info',
            "[IOT READY] mac_address={$mac_address}"
        );

        //TRIGGER CEK FMR
        $fmrCheck = $this->checkFmrById(24);

        return $this->response([
            'success' => true,
            'message' => 'READY status received'
        ]);
    }

    private function response($data, $code = 200)
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    private function checkFmrById($fmrId)
    {
        $mapId = 6;

        // polygon wrapping
        $polygon = [
            "-61.04 3.800",
            "-61.04 6.2838",
            "-59.698 6.2838",
            "-59.698 3.800",
            "-61.04 3.800"
        ];

        $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=".$mapId;

        

        $cookie = 'JSESSIONID=67fb9985-a9f4-4600-b065-1c61dc46f243; userName=Developt';

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
        curl_close($ch);

        if (!$response) {
            return [
                'success' => false,
                'message' => 'Failed call AMR API'
            ];
        }

        $amrData = json_decode($response, true);

        if (!isset($amrData['data'])) {
            return [
                'success' => false,
                'message' => 'Invalid AMR response'
            ];
        }

        // cari FMR id 24
        foreach ($amrData['data'] as $fmr) {
            if ($fmr['id'] == $fmrId) {

                $x = $fmr['coordinate']['x'];
                $y = $fmr['coordinate']['y'];

                $point = $x." ".$y;

                $zoneResult = $this->pointlocation->pointInPolygon(
                    $point,
                    $polygon,
                    true
                );

                return [
                    'success' => true,
                    'fmr_id'  => $fmrId,
                    'x'       => $x,
                    'y'       => $y,
                    'zone'    => $zoneResult
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'FMR not found'
        ];
    }


    /**
     * GET api/wrapping/check_fmr
     */
    public function check_fmr()
    {
        //polygon zona wrapping
        $polygon = [
            "-61.04 3.800",
            "-61.04 6.2838",
            "-59.698 6.2838",
            "-59.698 3.800",
            "-61.04 3.800"
        ];

        //hardcore titik FMR (untuk testing)
        //INSIDE
        //$fmrPoint = "-60.37 4.947";

        //OUTSIDE
        //$fmrPoint = "-61.2327 7.2258";

        //BOUNDARY
        $fmrPoint = "-60.37 6.2838";

        //cekposisi
        $result = $this->pointlocation->pointInPolygon(
            $fmrPoint,
            $polygon,
            true
        );

        //response
        echo json_encode([
            'fmr_point' => $fmrPoint,
            'result'    => $result
        ]);
    }

    public function test_amr_only()
    {
        $mapId = 6;

        $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=".$mapId;
        //tiap login ganti cookienya
        $cookie = 'JSESSIONID=67fb9985-a9f4-4600-b065-1c61dc46f243; userName=Developt';

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

        // Kalau request ke AMR gagal
        if ($response === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed call AMR API',
                'error'   => $error
            ]);
            return;
        }

        // Kalau sukses, tampilkan response AMR apa adanya
        echo json_encode([
            'success'    => true,
            'http_code' => $httpCode,
            'amr_raw'   => json_decode($response, true)
        ]);
    }

}
