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

    /**
     * GET api/wrapping/check_fmr
     * Payload:
     * {
     *   "mac_address": "AA:BB:CC:DD:EE:FF",
     *   "status": "READY"
     * }
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
        //
    }
}
