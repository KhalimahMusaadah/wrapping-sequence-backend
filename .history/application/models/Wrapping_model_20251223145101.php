<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping_model extends CI_Model {

    public function insertIoTLog($data)
    {
        return $this->db->insert('iot_communication_logs', $data);
    }

    public function generateSequence()
    
}