<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wrapping_model extends CI_Model {

    /* =========================
     * IOT LOG
     * ========================= */
    public function insertIoTLog($data)
    {
        $data['datetime'] = date('Y-m-d H:i:s');
        return $this->db->insert('iot_communication_logs', $data);
    }

    public function hasActiveWrapCommand($mac_address)
    {
        return $this->db->where('mac_address', $mac_address)
                        ->where('status', 'WRAP')
                        ->where_in('call_status', ['TRANSMIT','SENT'])
                        ->get('iot_communication_logs')
                        ->row();
    }

    public function insertWrapCommand($mac_address)
    {
        return $this->insertIoTLog([
            'mac_address' => $mac_address,
            'status'      => 'WRAP',
            'call_status' => 'TRANSMIT'
        ]);
    }

    public function closeActiveWrapCommand($mac_address)
    {
        return $this->db->where('mac_address', $mac_address)
                        ->where('status', 'WRAP')
                        ->where('call_status', 'TRANSMIT')
                        ->update('iot_communication_logs', [
                            'call_status' => 'DONE'
                        ]);
    }

    /* =========================
     * SEQUENCE
     * ========================= */
    public function generateSequence($mac_address)
    {
        $today = date('Y-m-d');

        $last = $this->db
            ->where('mac_address', $mac_address)
            ->order_by('id','DESC')
            ->limit(1)
            ->get('wrapping_sequence_logs')
            ->row();

        if (!$last){
            $counter = 1;
        } else {
            $lastDate = date('Y-m-d', strtotime($last->created_at));
            $counter = ($lastDate === $today) ? $last->counter + 1 : 1;
        }

        $sequence = (($counter - 1) % 6) + 1;

        $taskMap = [
            1 => 101,
            2 => 102,
            3 => 103,
            4 => 104,
            5 => 105,
            6 => 106
        ];

        $task_id = $taskMap[$sequence] ?? null;
        if (!$task_id) return null;

        $data = [
            'mac_address' => $mac_address,
            'counter'     => $counter,
            'sequence'    => $sequence,
            'task_id'     => $task_id,
            'map_id'      => 1,
            'status'      => 'DONE',
            'created_at'  => date('Y-m-d H:i:s')
        ];

        $this->db->insert('wrapping_sequence_logs', $data);
        $data['id'] = $this->db->insert_id();

        return $data;
    }

    /* =========================
     * FK: iot -> sequence
     * ========================= */
    public function attachSequenceToLastIoTLog($mac_address, $sequence_id)
    {
        return $this->db
            ->where('mac_address', $mac_address)
            ->where('status', 'WRAPPING_DONE')
            ->order_by('id','DESC')
            ->limit(1)
            ->update('iot_communication_logs', [
                'wrapping_sequence_id' => $sequence_id
            ]);
    }
}
