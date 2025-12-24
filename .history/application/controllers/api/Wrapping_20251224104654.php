<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping extends CI_Controller {
    /** Question:
     * ganti hari sequencenya ambil dari database terakhir atau dimulai dari 1 setiap harinya? (sementara ikut kereset)
     * buat FMR inside atau outside sudah ada programnya, dari program itu nyimpan status in atau out, lalu dari backend ambil data nya terus ngetrigger IoT untuk mulai wrapping kah atau bagaimana?
     * 
     */

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
    public function process()
    {
        $raw = $this->input->raw_input_stream;
        $ip = $this->input->ip_address();

        /* log Request*/
        log_message('debug', 'IOT REQUEST FROM IP: '.$ip.' | DATA: '.$raw); 
        $input = json_decode($raw, true);

        $mac_address = $input['mac_address'] ?? null; 
        $status = $input['status'] ?? null;

        
        if (!$mac_address || !$status){
            echo json_encode([
                'status' => 'ERROR',
                'message' => 'mac_address & status not found'
            ]);
            return;
        }

        /* Simpan log Iot */
        $this->Wrapping_model->insertIoTLog([
            'mac_address' => $mac_address,
            'status' => $status,
            'call_status' => 'RECEIVED'
        ]);

        echo json_encode([
            'status' => 'OK',
            'message' => 'IoT Log saved'
        ]);

        switch ($status){
            case 'READY':
                log_message('debug', '[BRANCH] IOT READY');

                /**
                 * -Sementara FMR anggap outside 
                 * -jika FMR sudah outside, kirim command WRAP
                 */

                $fmr_position = 'OUTSIDE';

                if ($fmr_position === 'o')


                break;
            case 'WRAPPING_DONE':
                log_message('debug', '[BRANCH] IOT WRAPPING_DONE');

                /**
                 * -operasi sequence
                 * -trigger task FMR
                 */
                $seq = $this->Wrapping_model->generateSequence();

                if (!$seq){
                    log_message('error', '[SEQUENCE] FAILED TO GENERATE');
                    break;
                }

                log_message(
                    'debug',
                    '[SEQUENCE] counter='.$seq['counter'].' | sequence='.$seq['sequence'].' | task_id='.$seq['task_id']
                ); 
                
                break;
            default:
                log_message('debug', '[BRANCH] IOT STATUS UNKNOWN');
                break;
            
        }
        
    }
}