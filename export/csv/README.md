# Seed data files

The `/setup` page imports these files. The same data is in `../sql/eve_universe.sql`.

| File | What it holds | Source |
|---|---|---|
| `system_static.csv` | Static wormholes of each system (`id;systemId;typeId`) | [Tripwire](https://bitbucket.org/daimian/tripwire/src/production/tools/statics.json) `tools/statics.json`, MIT licence. Regenerate with `../tools/build_system_static.php` |
| `wormhole.csv` | Signature strength of each wormhole type (attribute 1908), which ESI does not provide | Inherited from the original Pathfinder (exodus4d) |

CCP publishes no statics: they are in neither ESI nor the SDE. Tripwire's list is compiled from
player reports, and each change is a commit in its repository.

Ship, structure and wormhole types come from ESI: see `../tools/refresh_universe_types.php`.
