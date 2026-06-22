# Symcon MCP Bridge

Ein IP-Symcon-Modul, das die Kernel-Log-Meldungen (`KL_*` aus `IPS_LogMessage`) in
einen **gefilterten, beschränkten Ring-Buffer** sammelt und über eine **öffentliche
JSON-RPC-Funktion** abrufbar macht. Damit kann ein externer Agent (über den
[`ipsymcon`-MCP](https://github.com/Schimmilab/ipsymcon-mcp-server)) das Log zum
**Debuggen** lesen — ohne riesige Logdateien zu parsen und ohne Inline-PHP/RCE.

Teil des Projekts *home-automation-mcp*. Die „richtig implementierte" Lösung statt
Skript-/Logfile-Behelf: das Modul ist resident (echter `MessageSink`), hält nur die
letzten N relevanten Meldungen im Speicher und exponiert sie als saubere Funktion.

## Architektur

```
IP-Symcon Kernel ──(KL_WARNING/KL_ERROR/…)──▶ MCP Bridge (MessageSink, Ring-Buffer)
                                                      │
ipsymcon-MCP ──ips_call("MCPB_GetLog", [id,…])──▶ MCPB_GetLog() ──▶ JSON
```

## Öffentliche Funktionen (JSON-RPC)

Aufrufbar als `MCPB_<Name>($InstanceID, …)`:

| Funktion | Zweck |
|---|---|
| `MCPB_GetLog($Count, $Level, $Filter)` | gepufferte Meldungen als JSON. `$Count` = Anzahl (neueste, 0=alle), `$Level` = exakter Level z.B. `"ERROR"`, `$Filter` = Substring auf Absender+Text |
| `MCPB_ClearLog()` | Buffer leeren |
| `MCPB_GetDebug()` | Rohform der zuletzt empfangenen Nachricht — zum Verifizieren des Empfangswegs |

## Installation

1. Repo nach GitHub pushen (z.B. `Schimmilab/SymconMCPBridge`).
2. In IP-Symcon: **Verwaltungskonsole → Modules (Kerninstanz) → „+" → Repository-URL** des Repos eintragen.
3. Neue Instanz anlegen: **Objekt hinzufügen → Instanz → „MCP Bridge"** (Hersteller Schimmilab).
4. In der Instanz `MaxEntries` und `CaptureLevels` einstellen (Default: 200 Einträge, Level `4,5,7` = WARNING/ERROR/CUSTOM).

> Alternativ ohne GitHub: lokalen Klon des Repos auf dem IPS-Host ablegen und den lokalen Pfad in den Modules-Einstellungen angeben.

## Verifikation nach der Installation (wichtig)

Eine Annahme ist erst am laufenden System sicher: **dass `KL_*`-Log-Meldungen wirklich
am `MessageSink` ankommen** (Sender 0 / Message-IDs 10201–10207). So prüfst du es:

1. Instanz anlegen.
2. Eine Test-Meldung erzeugen: ein Skript mit `IPS_LogMessage('TEST', 'hallo welt');`
   ausführen (CUSTOM = Level 7, ist in den Defaults erfasst).
3. **`MCPB_GetDebug($instanzID)`** aufrufen → zeigt die Rohform der letzten empfangenen
   Nachricht (Sender, Message-ID, Data).
   - Kommt etwas an → Empfangsweg bestätigt, `MCPB_GetLog` liefert die Einträge.
   - Bleibt `null` → die Meldungen laufen nicht über Sender 0 / diese IDs. Dann am
     `GetDebug`-Befund den echten Sender/die echte ID ablesen und `ApplyChanges()`
     entsprechend anpassen.

**Fallback**, falls die Message-Subscription auf diesem System nicht greift: `GetLog`
liest stattdessen das Logfile-Tail über `IPS_GetLogDir()` (nur bei aktivem File-Logging,
Level auf Warnung/Fehler). Die Modul-Schnittstelle bleibt identisch.

## Sicherheit

- Reiner **Lese**-Dienst: das Modul schreibt nichts an der Automation, es sammelt nur
  Meldungen und gibt sie zurück.
- `MessageSink` ruft **niemals** `IPS_LogMessage()` auf — sonst entstünde eine
  Endlosschleife (es würde seine eigene Log-Zeile fangen).

## Status

v0.1 — geschrieben 2026-06-22. Empfangsweg (`MessageSink` für `KL_*`) wird beim ersten
Install live verifiziert; Default-Capture WARNING/ERROR/CUSTOM.
