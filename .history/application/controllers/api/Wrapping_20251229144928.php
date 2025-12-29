<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping extends CI_Controller {
    /** Question:
     * ganti hari sequencenya ambil dari database terakhir atau dimulai dari 1 setiap harinya? (sementara ikut kereset)
     * buat FMR inside atau outside sudah ada programnya, dari program itu nyimpan status in atau out, lalu dari backend ambil data nya terus ngetrigger IoT untuk mulai wrapping kah atau bagaimana?
     * 
     */
    /** Kurang:
     * IP Address IoT FMR
     */

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Wrapping_model');
        $this->load->library('cekpoint');
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
                log_message('debug', '[BRANCH] READY - CEK FMR POSITION');

                /**
                 * -Sementara FMR anggap outside 
                 * -jika FMR sudah outside, kirim command WRAP
                 */

                //koordinat FMR yang dikirim oleh IoT
                $lat = $input['lat'] ?? null;
                $lon = $input['lon'] ?? null;
                if (!$lat || !$lon){
                    log_message('error', '[FMR] LAT/LON NOT FOUND');
                    break;
                }

                //cek inside/outside
                log_message(
                    'debug',
                    '[CHECKPOINT] status='.$fmr['status'].
                    ' | distance='.$fmr['distance']
                );

                if ($fmr['status'] === 'OUTSIDE'){
                    //cek double wrap
                    $activeWrap = $this->Wrapping_model->hasActiveWrapCommand($mac_address);

                    if (!$activeWrap){
                        $this->Wrapping_model->insertWrapCommand($mac_address);
                        log_message('debug', '[WRAP] COMMAND INSERTED');
                    } else {
                        log_message('debug', '[DOUBLE WRAP] Command WRAP already active for mac_address='.$mac_address);
                    }
                } else {
                    log_message('debug', '[FMR] INSIDE - NO WRAP COMMAND SENT');
                }

                break;

            case 'WRAPPING_DONE':
                log_message('debug', '[BRANCH] IOT WRAPPING_DONE');

                /**
                 * -operasi sequence
                 * -trigger task FMR
                 */

                //tutup command WRAP yang aktif
                $this->Wrapping_model->closeActiveWrapCommand($mac_address);

                //operasi sequence
                $seq = $this->Wrapping_model->generateSequence();

                if (!$seq){
                    log_message('error', '[SEQUENCE] FAILED TO GENERATE');
                    break;
                }

                log_message(
                    'debug',
                    '[SEQUENCE] counter='.$seq['counter'].
                    ' | sequence='.$seq['sequence'].
                    ' | task_id='.$seq['task_id']
                ); 
                
                break;

            default:
                log_message('debug', '[BRANCH] IOT STATUS UNKNOWN');
                break;
            
        }
        
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
        
        //command sudah diambil IoT
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