<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping_model extends CI_Model {

    public function insertIoTLog($data)
    {
        $data['datetime'] = date('Y-m-d H:i:s');

        return $this->db->insert('iot_communication_logs', $data);
    }

    public function getLastCounterToday($mac)
    {
        $today = date('Y-m-d');

        $this->db->where('mac_address',$mac);
        $this->db->like('created_at',$today);
        $this->db->order_by('counter','DESC');
        $this->db->limit(1);
        $q = $this->db->get('wrapping_sequence_logs');

        if($q->num_rows()>0){
            return $q->row()->counter;
        }
        return 0;
    }

    public function getLastSequence($mac)
    {
        $this->db->where('mac_address',$mac);
        $this->db->order_by('id','DESC');
        $this->db->limit(1);
        $q = $this->db->get('wrapping_sequence_logs');

        if($q->num_rows()>0){
            return $q->row()->sequence;
        }
        return 0;
    }

    public function insertSequence($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->db->insert('wrapping_sequence_logs',$data);
    }

    public function getWrappingTaskConfig($deviceId, $port)
    {
        $this->db->where('deviceId', $deviceId);
        $this->db->where('port', $port);
        $this->db->where('enable', 1);
        $this->db->order_by('sequence', 'ASC');
        $query = $this->db->get('trigger_config');

        if ($query->num_rows() > 0) {
            return $query->result();
        }
        return [];
    }

    //mengambil config berdasarkan mac address, port, dan sequence
    public function getTriggerConfigBySequence($deviceId, $port, $sequence)
    {
        $this->db->where('deviceId', $deviceId);
        $this->db->where('port', $port);
        $this->db->where('sequence', $sequence);
        $this->db->where('enable', 1);
        $query = $this->db->get('trigger_config');

        if ($query->num_rows() > 0) {
            return $query->row();
        }
        return null;
    }
}
