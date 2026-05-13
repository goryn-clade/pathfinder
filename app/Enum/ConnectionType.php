<?php
declare(strict_types=1);

namespace Exodus4D\Pathfinder\Enum;

/**
 * Valid connection type strings stored in the `type` JSON column and used as
 * scope values in the `scope` VARCHAR column.
 *
 * DB storage is always the string value — no migration needed.
 * Read:  ConnectionType::tryFrom($string)
 * Write: $case->value
 *
 * Legacy 'wh_eol' is NOT a case here; callers must map it to WhEol1 explicitly.
 * It is still accepted on input in ConnectionModel::set_type() for backward compat
 * but will always be normalised to wh_eol1 before storage.
 */
enum ConnectionType: string
{
    // ── Scope / base types ────────────────────────────────────────────────────
    // These appear in both the `scope` column (single value) and the `type`
    // JSON array (as the primary type for non-wormhole connections).
    case Wh         = 'wh';
    case Stargate   = 'stargate';
    case Jumpbridge = 'jumpbridge';
    case Abyssal    = 'abyssal';

    // ── Wormhole mass-reduction states ────────────────────────────────────────
    case WhFresh    = 'wh_fresh';
    case WhReduced  = 'wh_reduced';
    case WhCritical = 'wh_critical';

    // ── Wormhole jump-mass capacity (mutually exclusive) ──────────────────────
    case WhJumpMassS  = 'wh_jump_mass_s';
    case WhJumpMassM  = 'wh_jump_mass_m';
    case WhJumpMassL  = 'wh_jump_mass_l';
    case WhJumpMassXl = 'wh_jump_mass_xl';

    // ── Wormhole EOL phases (mutually exclusive) ──────────────────────────────
    case WhEol1 = 'wh_eol1';   // Phase 1 (aging): 1–4 h remaining
    case WhEol2 = 'wh_eol2';   // Phase 2 (expiring): 0–1 h remaining
    case WhEol3 = 'wh_eol3';   // Phase 3 (zombie): past natural end

    // ── Other ─────────────────────────────────────────────────────────────────
    case PreserveMass = 'preserve_mass';

    // ── Utility ───────────────────────────────────────────────────────────────

    /**
     * All valid type strings — use instead of the old $connectionTypeWhitelist array.
     *
     * @return string[]
     */
    public static function whitelist(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The three EOL phase cases, in phase order.
     *
     * @return list<self>
     */
    public static function eolCases(): array
    {
        return [self::WhEol1, self::WhEol2, self::WhEol3];
    }

    /**
     * Base seconds added to the nominal-lifespan buffer for EOL expiry calculation.
     * Returns null for non-EOL cases.
     */
    public function eolBaseSeconds(): ?int
    {
        return match($this) {
            self::WhEol1 => 4 * 3600,
            self::WhEol2 => 1 * 3600,
            self::WhEol3 => 0,
            default      => null,
        };
    }

    /**
     * The four jump-mass cases, smallest to largest.
     *
     * @return list<self>
     */
    public static function jumpMassCases(): array
    {
        return [self::WhJumpMassS, self::WhJumpMassM, self::WhJumpMassL, self::WhJumpMassXl];
    }
}
