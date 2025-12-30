<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Wrapping_model');
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
    }

    /* POST api/wrapping/process */
    public function process()
    {
        $raw = $this->input->raw_input_stream;
        $input = json_decode($raw, true);

        $mac_address = $input['mac_address'] ?? null;
        $status      = $input['status'] ?? null;

        if (!$mac_address || !$status){
            echo json_encode([
                'status' => 'ERROR',
                'message' => 'mac_address & status not found'
            ]);
            return;
        }

        /** =========================
         * SIMPAN LOG IOT (DEFAULT)
         * ========================= */
        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $mac_address,
            'status'      => $status,
            'call_status' => 'RECEIVED'
        ]);

        switch ($status){

            /* =========================
             * BARANG SIAP WRAP
             * ========================= */
            case 'READY':

                // Cegah double WRAP
                if (!$this->Wrapping_model->hasActiveWrapCommand($mac_address)){
                    $this->Wrapping_model->insertWrapCommand($mac_address);
                }

                break;

            /* =========================
             * WRAPPING SELESAI
             * ========================= */
            case 'WRAPPING_DONE':

                // tutup WRAP command
                $this->Wrapping_model->closeActiveWrapCommand($mac_address);

                // generate sequence
                $seq = $this->Wrapping_model->generateSequence($mac_address);

                if (!$seq){
                    log_message('error', '[SEQUENCE] FAILED');
                    break;
                }

                // simpan relasi ke iot log
                $this->Wrapping_model->attachSequenceToLastIoTLog(
                    $mac_address,
                    $seq['id']
                );

                // TODO: call API FMR pakai $seq['task_id']

                break;
        }

        echo json_encode(['status' => 'OK']);
    }

    /* GET api/wrapping/command */
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
