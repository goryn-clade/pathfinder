-- Wormhole data fixes
-- Confirmed against SDE 2025-07-07 TRANQUILITY and jambeeno.com/holes (April 2026)
-- References:
--   https://github.com/goryn-clade/pathfinder/issues/186 (M001/L005)
--   https://github.com/goryn-clade/pathfinder/issues/177 (Pochven wormholes)
--
-- Attribute IDs:
--   1381 = wormholeTargetSystemClass
--   1382 = wormholeMaxStableTime (minutes)
--   1383 = wormholeMaxStableMass (kg)
--   1384 = wormholeMassRegeneration (kg)
--   1385 = wormholeMaxJumpMass (kg)
--   1457 = scanWormholeStrength
--   1908 = wormholeEolAlertSet
--
-- Target system class values:
--   KSL=8, Pochven internal=25, Pochven exit to K-space=-1
--
-- ============================================================
-- 1. Frigate wormholes: 960 min (16h) → 270 min (4.5h)
-- ============================================================
-- typeIds: A009=34439, C008=34138, E004=34134, G008=34139, Q003=34140, Z006=34136
-- Also includes M001=34137, L005=34135 per issue #186
UPDATE type_attribute
SET value = 270
WHERE typeId IN (34134, 34135, 34136, 34137, 34138, 34139, 34140, 34439)
  AND attributeId = 1382;

-- ============================================================
-- 2. Pochven exit wormholes: 960 min (16h) → 720 min (12h)
-- ============================================================
-- typeIds: X450=56540, R081=56541, U372=56544
UPDATE type_attribute
SET value = 720
WHERE typeId IN (56540, 56541, 56544)
  AND attributeId = 1382;

-- ============================================================
-- 3. C729 buggy variant (56562): fix lifetime and mass values
-- ============================================================
-- All other C729 typeIds: 720 min, 1,000,000,000 kg total, 410,000,000 kg/jump
-- This variant was incorrectly set to 270 min and wrong mass values
UPDATE type_attribute
SET value = 720
WHERE typeId = 56562
  AND attributeId = 1382;

UPDATE type_attribute
SET value = 1000000000
WHERE typeId = 56562
  AND attributeId = 1383;

UPDATE type_attribute
SET value = 410000000
WHERE typeId = 56562
  AND attributeId = 1385;

-- ============================================================
-- 4. J377 (73749) and J492 (87827): INSERT missing attributes
-- ============================================================
-- KSL wormholes, 24h lifetime. Profile matches J244 (typeId 30667).
INSERT INTO type_attribute (typeId, attributeId, value)
VALUES
  (73749, 1381, 8),
  (73749, 1382, 1440),
  (73749, 1383, 1000000000),
  (73749, 1384, 0),
  (73749, 1385, 62000000),
  (73749, 1457, 303),
  (73749, 1908, 5),
  (87827, 1381, 8),
  (87827, 1382, 1440),
  (87827, 1383, 1000000000),
  (87827, 1384, 0),
  (87827, 1385, 62000000),
  (87827, 1457, 303),
  (87827, 1908, 5);

-- ============================================================
-- 5. I078 (92287), L687 (92288), O546 (92289): INSERT missing attributes
-- ============================================================
-- Pochven internal wormholes, 4.5h lifetime, cruiser mass class.
INSERT INTO type_attribute (typeId, attributeId, value)
VALUES
  (92287, 1381, 25),
  (92287, 1382, 270),
  (92287, 1383, 1000000000),
  (92287, 1384, 0),
  (92287, 1385, 62000000),
  (92288, 1381, 25),
  (92288, 1382, 270),
  (92288, 1383, 1000000000),
  (92288, 1384, 0),
  (92288, 1385, 62000000),
  (92289, 1381, 25),
  (92289, 1382, 270),
  (92289, 1383, 1000000000),
  (92289, 1384, 0),
  (92289, 1385, 62000000);

-- ============================================================
-- 6. F216 (56543): INSERT missing attributes
-- ============================================================
-- Pochven exit to K-space, 12h lifetime, capital mass class.
-- Profile matches F216 typeId 56542 (which already has correct data).
INSERT INTO type_attribute (typeId, attributeId, value)
VALUES
  (56543, 1381, -1),
  (56543, 1382, 720),
  (56543, 1383, 1000000000),
  (56543, 1384, 0),
  (56543, 1385, 410000000),
  (56543, 1457, 2934);
