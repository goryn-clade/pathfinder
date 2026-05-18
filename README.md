# ![Pathfinder logo](favicon/favicon-32x32.png "Logo") *PATHFINDER*

#### Mapping tool for [*EVE ONLINE*](https://www.eveonline.com)

This is a community-maintained fork of [Pathfinder](https://github.com/exodus4d/pathfinder) by Exodus4d, kept alive with bug fixes, PHP 8 compatibility, and EVE universe data updates.

**For installation, see the [pathfinder-containers](https://github.com/goryn-clade/pathfinder-containers) repository.**

---

## v3.0 — What's new in the app

v3.0 is a major release. The full upgrade guide (backups, schema migration, env rewrite, bulk ESI token encryption) lives in [pathfinder-containers](https://github.com/goryn-clade/pathfinder-containers) — see its README and `MIGRATION-v2-to-v3.md`. The notes here cover only changes inside the Pathfinder application itself.

### Application-level breaking changes

| Area | v2.x | v3.0 |
|---|---|---|
| PHP runtime | 7.2 | 8.3 |
| DB schema | v2 | adds columns to `system`, `connection`, `map`, `character`; new `map_group` table |
| ESI tokens at rest | plaintext | encrypted (libsodium `crypto_secretbox`) |
| Email / SMTP notifications | supported | **removed** — use Slack or Discord webhooks |
| `pathfinder.ini` / `config.ini` | hand-edited ini files | driven by environment variables |
| `plugin.ini` | bind-mounted | bind-mounted (unchanged) |
| Login restriction settings | `WHITELIST_*` | renamed `ALLOWLIST_*` |

### Security hardening

- **ESI tokens are encrypted at rest** using libsodium `crypto_secretbox`. Requires a 32-byte hex key (`TOKEN_ENCRYPTION_KEY`); fails closed if blank.
- **WebSocket tokens** are HMAC-bound to the PHP session. The socket server refuses to start without `WS_TOKEN_SECRET`.
- **WebSocket origin checks** — the socket server refuses to start in production with no allowed origins configured.
- **PKCE on CCP SSO** — enabled by default; can be disabled via `CCP_SSO_USE_PKCE=0` if upstream changes break the flow.
- **`/setup` is gated** by `PF_SETUP_ENABLED` and should only be enabled during bootstrap.
- **Production startup guards** activate when `APP_ENV=production`.


---

## Community

Join the discussion on Discord: [https://discord.gg/PLACEHOLDER](https://discord.gg/PLACEHOLDER)

> **Note:** The Pathfinder Slack workspace is no longer active. Slack's commercial model change reduced free workspace history to 90 days, making it unsuitable for a small open-source community. Discord is now the primary community channel.

---

## Contributing

Issues and pull requests are welcome. Please report bugs in the [Issue tracker](https://github.com/goryn-clade/pathfinder/issues).

---

## License

[MIT](http://opensource.org/licenses/MIT)
