<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
    }

    /**
     * POST api/wrapping/ready
     * IoT Wrapping mengirim status READY
     * Body JSON:
     * {
     *   "mac_address": "AA:BB:CC:DD:EE:FF",
     *   "status": "READY"
     * }
     */
    public function ready()
    {
        $raw   = $this->input->raw_input_stream;
        $input = json_decode($raw, true);

        $mac_address = $input['mac_address'] ?? null;
        $status      = $input['status'] ?? null;

        if (!$mac_address || !$status) {
            echo json_encode([
                'status'  => 'ERROR',
                'message' => 'mac_address & status required'
            ]);
            return;
        }

        // Simpan log READY IoT
        $this->db->insert('iot_communication_logs', [
            'mac_address' => $mac_address,
            'status'      => $status,
            'call_status' => 'RECEIVED'
        ]);

        // Log CI untuk debugging
        log_message('info', "[READY] MAC: $mac_address | Status: $status | Log saved");

        // Response ke IoT
        echo json_encode([
            'status' => 'OK',
            'message' => 'READY status received and logged'
        ]);
    }
}
