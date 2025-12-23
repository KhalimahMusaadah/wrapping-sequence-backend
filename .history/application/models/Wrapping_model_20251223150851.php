<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping_model extends CI_Model {

    public function insertIoTLog($data)
    {
        return $this->db->insert('iot_communication_logs', $data);
    }

    public function generateSequence()
    {
        $last = $this->db->order_by('id', 'Desc')->limit(1)->get('wrapping_sequences')->row();
        
        $counter = $last ? $last->counter + 1 : 1;

        $sequence = (($counter - 1) % 6) + 1;

        /**
         * mapping task_idnya manual 
         * nanti diganti dengan API
         */

        $taskMap = [
            1 => 101, //sequence 1 -> task_id 101
            2 => 102,
            3 => 103,
            4 => 104,
            5 => 105,
            6 => 106 
        ];
        $task_id = $taskMap[$sequence];

    }
}