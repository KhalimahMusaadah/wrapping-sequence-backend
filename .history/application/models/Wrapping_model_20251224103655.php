<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping_model extends CI_Model {

    public function insertIoTLog($data)
    {
        return $this->db->insert('iot_communication_logs', $data);
    }

    /* cegah double wrap */
    public function hasActiveWrapCommand($mac_address)
    {
        return $this->db->where('mac_address', $mac_address)
                        ->where('status', 'WRAP')
                        ->where('call_status', 'TRANSMIT')
                        ->get('iot_communication_logs')
                        ->row();
    }

    public function insertWrapCommand($mac_address)
    {
        return $this
    }

    public function generateSequence()
    {
        $today = date('Y-m-d');

        $last = $this->db->order_by('id', 'Desc')->limit(1)->get('wrapping_sequence_logs')->row();
        
        if (!$last){
            /* data awal */
            $counter = 1;
        } else {
            $lastDate = date('Y-m-d', strtotime($last->created_at));
            if ($lastDate !== $today){
                /* ganti hari, reset counter */
                $counter = 1;
            } else {
                /* masih hari yang sama */
                $counter = $last->counter + 1;
            }
        }

        //$counter = $last ? $last->counter + 1 : 1;

        $sequence = (($counter - 1) % 6) + 1;

        /**
         * mapping task_idnya manual 
         * nanti diganti dengan API
         */

        $taskMap = [
            1 => 101, /* sequence 1 -> task_id 101 */
            2 => 102,
            3 => 103,
            4 => 104,
            5 => 105,
            6 => 106 
        ];

        $task_id = $taskMap[$sequence] ?? null;
        if (!$task_id){
            return null;
        }

        $data = [
            'counter' => $counter,
            'sequence' => $sequence,
            'task_id' => $task_id,
            'map_id' => 1 //sementara
        ];

        $this->db->insert('wrapping_sequence_logs', $data);

        return $data;

    }
}