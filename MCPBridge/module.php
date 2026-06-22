<?php

declare(strict_types=1);

/**
 * MCP Bridge — exposes IP-Symcon kernel log messages as a queryable, filtered
 * ring buffer so an external agent (via the ipsymcon MCP / JSON-RPC) can read
 * the log for debugging without parsing huge logfiles.
 *
 * Public functions, callable over JSON-RPC as MCPB_<Name>($InstanceID, ...):
 *   - MCPB_GetLog($Count, $Level, $Filter) → JSON array of captured entries
 *   - MCPB_ClearLog()                      → empties the buffer
 *   - MCPB_GetDebug()                      → raw shape of the last received message
 *                                            (to verify the capture path on install)
 */
class MCPBridge extends IPSModule
{
    private const KL_BASE = 10200; // IPS_LOGMESSAGE category; KL_* = 10201..10207

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('MaxEntries', 200);
        // KL levels to capture (comma list): 4=WARNING 5=ERROR 7=CUSTOM by default
        $this->RegisterPropertyString('CaptureLevels', '4,5,7');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Subscribe to kernel log messages. Sender 0 = kernel. We register for the
        // category base AND each KL_* id to cover both delivery conventions; if the
        // real sender/id differs, MCPB_GetDebug() reveals what actually arrives so we
        // can adjust (this is the one runtime-verified assumption of the module).
        foreach (array_merge([self::KL_BASE], range(self::KL_BASE + 1, self::KL_BASE + 7)) as $msg) {
            $this->RegisterMessage(0, $msg);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // CRITICAL: never call IPS_LogMessage() in here — it would emit a new log
        // message that we would immediately catch again → infinite loop.

        // Always record the raw shape of the last message for verification.
        $this->SetBuffer('LastRaw', json_encode([
            'timestamp' => $TimeStamp,
            'senderID'  => $SenderID,
            'message'   => $Message,
            'data'      => $Data,
        ]));

        // Only handle kernel log messages KL_MESSAGE..KL_CUSTOM (10201..10207).
        if ($Message <= self::KL_BASE || $Message > self::KL_BASE + 7) {
            return;
        }
        $level = $Message - self::KL_BASE; // 1..7

        $capture = array_map(
            'intval',
            array_filter(array_map('trim', explode(',', $this->ReadPropertyString('CaptureLevels'))), 'strlen')
        );
        if (!in_array($level, $capture, true)) {
            return;
        }

        // $Data shape is verified at runtime; be defensive. Typically [text, sender].
        $text = '';
        $sender = '';
        if (is_array($Data)) {
            $text   = isset($Data[0]) ? (string) $Data[0] : json_encode($Data);
            $sender = isset($Data[1]) ? (string) $Data[1] : '';
        } else {
            $text = (string) $Data;
        }

        $log = json_decode($this->GetBuffer('Log'), true);
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = [
            'ts'     => date('Y-m-d H:i:s', (int) $TimeStamp),
            'level'  => $this->levelName($level),
            'sender' => $sender,
            'text'   => $text,
        ];

        $max = max(1, $this->ReadPropertyInteger('MaxEntries'));
        if (count($log) > $max) {
            $log = array_slice($log, -$max);
        }
        $this->SetBuffer('Log', json_encode($log));
    }

    /**
     * Return captured log entries as JSON.
     * @param int    $Count  max entries to return (newest), 0 = all
     * @param string $Level  optional exact level filter, e.g. "ERROR"
     * @param string $Filter optional case-insensitive substring on sender+text
     */
    public function GetLog(int $Count = 50, string $Level = '', string $Filter = '')
    {
        $log = json_decode($this->GetBuffer('Log'), true);
        if (!is_array($log)) {
            $log = [];
        }
        $level = strtoupper(trim($Level));
        $result = [];
        foreach ($log as $e) {
            if ($level !== '' && strtoupper((string) $e['level']) !== $level) {
                continue;
            }
            if ($Filter !== '' && stripos($e['sender'] . ' ' . $e['text'], $Filter) === false) {
                continue;
            }
            $result[] = $e;
        }
        if ($Count > 0 && count($result) > $Count) {
            $result = array_slice($result, -$Count);
        }
        return json_encode($result);
    }

    public function ClearLog()
    {
        $this->SetBuffer('Log', json_encode([]));
        return true;
    }

    public function GetDebug()
    {
        $raw = $this->GetBuffer('LastRaw');
        return $raw === '' ? json_encode(null) : $raw;
    }

    private function levelName(int $level): string
    {
        $map = [1 => 'MESSAGE', 2 => 'SUCCESS', 3 => 'NOTIFY', 4 => 'WARNING', 5 => 'ERROR', 6 => 'DEBUG', 7 => 'CUSTOM'];
        return $map[$level] ?? (string) $level;
    }
}
