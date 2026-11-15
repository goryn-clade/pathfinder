# Security policy

## Report a vulnerability

Report security problems in private. Do not open a public issue, and do not post them on Discord.

Use GitHub's private vulnerability reporting:

- Problems in the Pathfinder app, `pathfinder_esi` or `pathfinder_websocket`: [report in pathfinder](https://github.com/goryn-clade/pathfinder/security/advisories/new)
- Problems in the Docker setup (compose files, images, nginx, Traefik): [report in pathfinder-containers](https://github.com/goryn-clade/pathfinder-containers/security/advisories/new)

If you are not sure which one fits, use either. We will move the report if needed.

Please include:

- The version you run (the image tag, or `VERSION` in `app/pathfinder.ini`).
- The steps to reproduce the problem.
- What an attacker could do with it.

Only you and the maintainers can see the report. We publish an advisory when a fix is released, and we credit you unless you ask us not to.

## Supported versions

| Version | Security fixes |
|---|---|
| Newest 3.x release | Yes |
| Older 3.x releases | No. Upgrade to the newest release. |
| 2.x | No. End of support, see the [2.x notice](https://github.com/goryn-clade/pathfinder/issues/326). |

The newest release is on the [releases page](https://github.com/goryn-clade/pathfinder-containers/releases/latest). Published advisories are on the Security tab of [pathfinder](https://github.com/goryn-clade/pathfinder/security/advisories) and [pathfinder-containers](https://github.com/goryn-clade/pathfinder-containers/security/advisories).
