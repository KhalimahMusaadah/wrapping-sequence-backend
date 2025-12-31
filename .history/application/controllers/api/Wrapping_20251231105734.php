<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/cekpoint.php';

class Wrapping extends CI_Controller {

    private $pointChecker;
    private $wrappingPoint = "-60.37 4.603";
    private $wrappingRadius = 1.3;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Wrapping_model');
        $this->pointChecker = new pointLocation();
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
    }

    /**
     * POST api/wrapping/process 
     * IoT mengirim status READY atau WRAPPING_DONE
     * 
     * Request body untuk READY:
     * {
     *   "mac_address": "AA:BB:CC:DD:EE:FF",
     *   "status": "READY",
     *   "fmr_x": -61.5,
     *   "fmr_y": 4.5
     * }
     * 
     * Request body untuk WRAPPING_DONE
     * {
     *  "mac_address": "AA:BB:CC:DD:EE:FF",
     *  "status": "WRAPPING_DONE"
     * }
     */

    //POST api/wrapping/process
    public function process()
    {
        $raw   = $this->input->raw_input_stream;
        $input = json_decode($raw, true);

        $mac_address = $input['mac_address'] ?? null;
        $status      = $input['status'] ?? null;

        if (!$mac_address || !$status){
            echo json_encode([
                'status'  => 'ERROR',
                'message' => 'mac_address & status required'
            ]);
            return;
        }

        //simpan log komunikasi IoT
        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $mac_address,
            'status'      => $status,
            'call_status' => 'RECEIVED'
        ]);

        switch ($status){
            //status READY
            case 'READY':

                //ini nanti api 10.8.128.37 diadiin API

                // Ambil posisi FMR dari request
                $fmr_x = $input['fmr_x'] ?? null;
                $fmr_y = $input['fmr_y'] ?? null;

                // Cegah double WRAP
                if (!$this->Wrapping_model->hasActiveWrapCommand($mac_address)){
                    $this->Wrapping_model->insertWrapCommand($mac_address);
                }
                break;
            
            //status WRAPPING_DONE
            case 'WRAPPING_DONE':

                // Tutup command WRAP yang aktif
                $this->Wrapping_model->closeActiveWrapCommand($mac_address);

                // Generate sequence wrapping
                $seq = $this->Wrapping_model->generateSequence($mac_address);

                if (!$seq){
                    log_message('error', '[SEQUENCE] FAILED');
                    break;
                }

                /**
                 * TODO (next step):
                 * trigger API FMR pakai $seq['task_id']
                 * lalu update status -> DONE / FAILED
                 */

                break;
        }

        echo json_encode(['status' => 'OK']);
    }

    //GET api/wrapping/command
    public function command()
    {
        $mac_address = $this->input->get('mac_address');

        if (!$mac_address){
            echo json_encode(['command' => null]);
            return;
        }

        $cmd = $this->db
            ->where('mac_address', $mac_address)
            ->where('status', 'WRAP')
            ->where('call_status', 'TRANSMIT')
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get('iot_communication_logs')
            ->row();

        // IoT sudah ambil command
        if ($cmd){
            $this->db->where('id', $cmd->id)
                     ->update('iot_communication_logs', [
                         'call_status' => 'SENT'
                     ]);
        }

        echo json_encode([
            'command' => $cmd ? $cmd->status : null
        ]);
    }
}
