<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Wrapping_model');
    }

    public function index()
    {
        $data['title'] = 'Wrapping System - Monitoring';
        $data['page'] = 'monitoring';
        
        $this->load->view('dashboard/header', $data);
        $this->load->view('dashboard/monitoring', $data);
        $this->load->view('dashboard/footer', $data);
    }

    public function testing()
    {
        $data['title'] = 'Wrapping System - Testing';
        $data['page'] = 'testing';
        
        $this->load->view('dashboard/header', $data);
        $this->load->view('dashboard/testing', $data);
        $this->load->view('dashboard/footer', $data);
    }

    public function getSummary()
    {
        header('Content-Type: application/json');
        
        $today = date('Y-m-d');
        
        // Total wrapping hari ini
        $this->db->select('COUNT(*) as total');
        $this->db->where('DATE(created_at)', $today);
        $totalQuery = $this->db->get('wrapping_sequence_logs');
        $totalWraps = $totalQuery->row()->total ?? 0;
        
        // Data terakhir
        $this->db->select('counter, sequence, taskId, created_at');
        $this->db->where('DATE(created_at)', $today);
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $lastQuery = $this->db->get('wrapping_sequence_logs');
        $lastWrap = $lastQuery->row();
        
        $response = [
            'success' => true,
            'data' => [
                'total_wraps' => (int)$totalWraps,
                'current_sequence' => $lastWrap ? (int)$lastWrap->sequence : 0,
                'last_task' => $lastWrap ? (int)$lastWrap->taskId : 0,
                'last_time' => $lastWrap ? $lastWrap->created_at : null
            ]
        ];
        
        echo json_encode($response);
    }

    public function getRecentHistory()
    {
        header('Content-Type: application/json');
        
        $limit = $this->input->get('limit') ?? 10;
        
        $this->db->select('id, mac_address, counter, sequence, taskId, created_at');
        $this->db->order_by('id', 'DESC');
        $this->db->limit($limit);
        $query = $this->db->get('wrapping_sequence_logs');
        
        $data = [];
        foreach ($query->result() as $row) {
            $data[] = [
                'id' => (int)$row->id,
                'mac_address' => $row->mac_address,
                'counter' => (int)$row->counter,
                'sequence' => (int)$row->sequence,
                'taskId' => (int)$row->taskId,
                'created_at' => $row->created_at,
                'time' => date('H:i:s', strtotime($row->created_at)),
                'status' => 'Success' // Bisa ditambah logic dari trigger_hist
            ];
        }
        
        $response = [
            'success' => true,
            'data' => $data
        ];
        
        echo json_encode($response);
    }

    public function testReady()
    {
        header('Content-Type: application/json');
        
        $input = json_decode($this->input->raw_input_stream, true);
        
        $mac = $input['mac_address'] ?? null;
        $testMode = $input['test_mode'] ?? false;
        
        if (!$mac) {
            echo json_encode([
                'success' => false,
                'message' => 'mac_address is required'
            ]);
            return;
        }
        
        // Call internal API
        $apiUrl = base_url('api/wrapping/ready');
        $payload = [
            'mac_address' => $mac,
            'status' => 'Ready',
            'test' => $testMode
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to call API'
            ]);
            return;
        }
        
        echo $response;
    }

    public function testDone()
    {
        header('Content-Type: application/json');
        
        $input = json_decode($this->input->raw_input_stream, true);
        
        $mac = $input['mac_address'] ?? null;
        
        if (!$mac) {
            echo json_encode([
                'success' => false,
                'message' => 'mac_address is required'
            ]);
            return;
        }
        
        // Call internal API
        $apiUrl = base_url('api/wrapping/done');
        $payload = [
            'mac_address' => $mac,
            'status' => 'Wrapping Done'
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to call API'
            ]);
            return;
        }
        
        echo $response;
    }
}

?>