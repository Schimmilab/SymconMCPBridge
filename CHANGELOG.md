# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.
Format orientiert an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/);
Versionierung nach [SemVer](https://semver.org/lang/de/).

## [0.1.0] – 2026-06-22

### Hinzugefügt
- Erste Veröffentlichung.
- `MessageSink`-Ring-Buffer, der IP-Symcon-Kernel-Log-Meldungen (`KL_*`) sammelt.
- Öffentliche JSON-RPC-Funktionen `MCPB_GetLog($Count, $Level, $Filter)`,
  `MCPB_ClearLog()`, `MCPB_GetDebug()`.
- Konfigurierbare Puffergröße (`MaxEntries`) und erfasste Level (`CaptureLevels`,
  Default `4,5,7` = WARNING/ERROR/CUSTOM).
- `MessageSink` loggt bewusst nicht selbst (vermeidet Endlosschleife).

### Offen / zu verifizieren
- Empfangsweg der `KL_*`-Meldungen am `MessageSink` (Sender 0 / IDs 10201–10207)
  wird beim ersten Install via `MCPB_GetDebug` live bestätigt; Logfile-Fallback
  über `IPS_GetLogDir()` dokumentiert.
