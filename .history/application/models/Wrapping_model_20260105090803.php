<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping_model extends CI_Model {

    /**
     * Simpan log komunikasi IoT
     */
    public function insertIoTLog($data)
    {
        $data['datetime'] = date('Y-m-d H:i:s');

        return $this->db->insert('iot_communication_logs', $data);
    }
}
