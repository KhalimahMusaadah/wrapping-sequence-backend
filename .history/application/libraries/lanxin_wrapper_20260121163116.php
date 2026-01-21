<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '../../lanxin_class.php';

class Lanxin_wrapper {
    
    protected $CI;
    protected $task;
    
    // testing mode
    private $TEST_MODE = true;
    
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->model('Wrapping_model');
        
        $db = $this->CI->db->conn_id;
        $this->task = new Task($db);
    }
    
    public function triggerRCS($deviceId, $port, $sequence = null)
    {
        if ($port == 26 && $sequence !== null) {
            return $this->triggerRCSWithSequence($deviceId, $port, $sequence);
        }

        $config = $this->getTriggerConfig($deviceId, $port);
        
        if (!$config) {
            log_message('warning', "[RCS] No trigger config for device={$deviceId} port={$port}");
            return [
                'success' => false,
                'message' => 'No trigger configuration found',
                'error_code' => 'NO_CONFIG'
            ];
        }
        
        if ($config->enable != 1) {
            log_message('info', "[RCS] Trigger disabled for device={$deviceId} port={$port}");
            return [
                'success' => false,
                'message' => 'Trigger is disabled',
                'error_code' => 'DISABLED'
            ];
        }
        
        $taskId = $config->taskId;
        $ipServer = $config->ipserver;
        
        return $this->executeTrigger($deviceId, $taskId, null,$ipServer);
    }
    
    private function triggerRCSWithSequence($deviceId, $port, $sequence)
    {
        $config = $this->CI->Wrapping_model->getTriggerConfigBySequence($deviceId, $port, $sequence);
        
        if (!$config) {
            log_message('warning', "[RCS] No config for device={$deviceId} port={$port} sequence={$sequence}");
            return [
                'success' => false,
                'message' => "No trigger configuration found for sequence {$sequence}",
                'error_code' => 'NO_CONFIG',
                'sequence' => $sequence
            ];
        }
        
        if ($config->enable != 1) {
            log_message('info', "[RCS] Trigger disabled for device={$deviceId} sequence={$sequence}");
            return [
                'success' => false,
                'message' => 'Trigger is disabled',
                'error_code' => 'DISABLED',
                'sequence' => $sequence
            ];
        }
        
        $taskId = $config->taskId;
        $ipServer = $config->ipserver;
        
        log_message('info', "[RCS] Sequence {$sequence} mapped to taskId {$taskId}");
        
        $result = $this->executeTrigger($deviceId, $taskId, $sequence, $ipServer);
        $result['sequence'] = $sequence;
        
        return $result;
    }
    
    
    private function executeTrigger($deviceId, $taskId, $sequence, $ipServer)
    {
        log_message('info', "[RCS] Triggering task={$taskId} for device={$deviceId} on server={$ipServer}");
        
        if ($this->TEST_MODE) {
            // TESTING MODE 
            log_message('info', "[RCS] TEST MODE - Skip actual RCS trigger");
            $errorCode = '0'; 
        } else {
            //trigger RCS sebenarnya
            $errorCode = $this->task->sendTriggertoRCS($taskId, $ipServer);
        }
        
        $success = ($errorCode == '0');
        
        // Log ke trigger_hist
        $flag = $success ? 1 : 0;
        $this->task->ExecQuery(
            "INSERT INTO trigger_hist(deviceId, taskId, sequence, flag, create_at) 
             VALUES('$deviceId', '$taskId', '$sequence', '$flag', NOW())"
        );
        
        log_message('info', "[RCS] Trigger result: errorCode={$errorCode}, success={$success}, testMode={$this->TEST_MODE}");
        
        return [
            'success' => $success,
            'task_id' => $taskId,
            'error_code' => $errorCode,
            'ip_server' => $ipServer,
            'test_mode' => $this->TEST_MODE,
            'message' => $success 
                ? 'RCS task triggered successfully' . ($this->TEST_MODE ? ' (TEST MODE)' : '')
                : "RCS trigger failed with error code: {$errorCode}"
        ];

        //untuk debug konek RCS
        if (!$this->TEST_MODE) {
            $result['debug_url'] = $this->task->lastUrl ?? null;
            $result['debug_raw_response'] = $this->task->lastRawResponse ?? null;
        }
        
        return $result;
    }

    private function getTriggerConfig($deviceId, $port)
    {
        $configs = $this->task->TriggerConfig(0, $deviceId, $port);
        
        if (!empty($configs)) {
            return $configs[0];
        }
        
        return null;
    }
}