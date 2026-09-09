# ADR-004: Confidence Review Lanes

Status: Accepted

Collected records are routed to Green, Amber, or Red lanes.

During calibration, Green means rule-clean and high-confidence, not unattended acceptance.

Consequences:

- `auto_accept_enabled` remains false.
- Review decisions preserve reason codes and correction context.
- Red records are rejected/quarantined, not silently discarded.
