-- Fix M001 and L005 wormhole lifespan
-- CCP changed these from 16h (960 min) to 4.5h (270 min)
-- Confirmed against SDE 2025-07-07 TRANQUILITY (dgmTypeAttributes attributeID=1382)
-- References: https://github.com/goryn-clade/pathfinder/issues/186
--
-- typeId 34135 = Wormhole L005
-- typeId 34137 = Wormhole M001
-- attributeId 1382 = wormholeMaxStableTime (stored in minutes)

UPDATE type_attribute
SET value = 270
WHERE typeId IN (34135, 34137)
  AND attributeId = 1382;
