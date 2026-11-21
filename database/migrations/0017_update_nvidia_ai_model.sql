-- Migration 0017: Upgrade EOL NVIDIA NIM model (google/diffusiongemma-26b-a4b-it) to active fast streaming model

UPDATE `settings`
SET `value` = 'nvidia/nemotron-3.5-bolt-e4b', `updated_at` = NOW()
WHERE `key` = 'ai_nvidia_model'
  AND (`value` IS NULL OR TRIM(`value`) = '' OR `value` = 'google/diffusiongemma-26b-a4b-it');

INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'ai_nvidia_model', 'nvidia/nemotron-3.5-bolt-e4b', 'NVIDIA NIM Model ID'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'ai_nvidia_model');
