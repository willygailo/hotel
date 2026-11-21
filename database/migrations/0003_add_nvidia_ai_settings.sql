-- Migration 0003: Add NVIDIA NIM AI integration settings
INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'ai_provider', 'nvidia', 'AI Engine provider: nvidia or local'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'ai_provider');

INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'ai_nvidia_key', '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', 'NVIDIA NIM API Key'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'ai_nvidia_key');

INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'ai_nvidia_model', 'nvidia/nemotron-3.5-bolt-e4b', 'NVIDIA NIM Model ID'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'ai_nvidia_model');
