<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('PointLocation'); // load library PointLocation
    }

    /**
     * Endpoint IoT mengirim status READY
     * POST data: mac_address
     */
    public function ready() {
        $mac_address = $this->input->post('mac_address');

        if (!$mac_address) {
            $this->output
                ->set_status_header(400)
                ->set_output(json_encode([
                    'status' => false,
                    'msg' => 'mac_address required'
                ]));
            return;
        }

        // 1️⃣ simpan log READY IoT
        $this->db->insert('iot_communication_logs', [
            'mac_address' => $mac_address,
            'status' => 'READY',
            'call_status' => 'received'
        ]);

        // 2️⃣ ambil data FMR (mock data untuk testing)
        $fmrData = $this->getFMRData();
        if (!$fmrData) {
            $this->output
                ->set_status_header(500)
                ->set_output(json_encode([
                    'status' => false,
                    'msg' => 'Failed to get FMR data'
                ]));
            return;
        }

        // 3️⃣ polygon wrapping meter (sesuai titik yang kamu berikan)
        $polygon = [
            "-61.04 3.800",  // kiri bawah
            "-61.04 6.2838", // kiri atas
            "-59.698 6.2838",// kanan atas
            "-59.698 3.800", // kanan bawah
            "-61.04 3.800"   // tutup loop
        ];

        // 4️⃣ cek posisi FMR
        $results = [];
        foreach($fmrData as $fmr) {
            $x = $fmr['coordinate']['x'];
            $y = $fmr['coordinate']['y'];
            $fmrPoint = "$x $y";

            $status = $this->pointlocation->pointInPolygon($fmrPoint, $polygon);

            // simpan hasil cek ke array untuk response
            $results[] = [
                'name' => $fmr['name'],
                'x' => $x,
                'y' => $y,
                'status_position' => $status
            ];

            // jika keluar polygon -> trigger IoT wrap (placeholder)
            if ($status == 'outside') {
                log_message('info', "FMR {$fmr['name']} keluar polygon, trigger wrap IoT.");
            } else {
                log_message('info', "FMR {$fmr['name']} berada di polygon: $status");
            }
        }

        // 5️⃣ kirim response
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'msg' => 'READY processed',
                'data' => $results
            ]));
    }

    /**
     * Helper untuk ambil FMR dari API
     * Versi mock untuk test Postman
     */
    private function getFMRData() {
        // Mock data FMR
        return [
            [
                "id" => "24",
                "name" => "AMR_INJ8",
                "coordinate" => [
                    "x" => -60.37,
                    "y" => 4.603
                ]
            ]
        ];

        // Jika mau pakai API nyata, ganti dengan:
        /*
        $url = "http://10.8.15.226:4333/api/amr/onlineAmr?mapId=6";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) return false;
        $json = json_decode($response, true);
        return isset($json['data']) ? $json['data'] : false;
        */
    }
}
