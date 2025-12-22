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
        //header('Access-Control-Allow-Methods: POST, GET');
    }

    /* POST api/wrapping/process */
    public function process(){
        $input = json_decode($this->input->raw_input_stream, true);

        $mac_address = $input['mac_address'] ?? null;
        $status = $input['status'] ?? null;

        if (!$mac_address || !$status){
            echo json_encode([
                'status' => 'ERROR',
                'message' => 'mac_address dan status wajib'
            ]);
            return;
        }

        /* Simpan log Iot */
        $this->Wrapping_model->insertIoTLog([
            'mac_address' =>
        ])
        
    }
}